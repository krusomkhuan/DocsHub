<?php
/**
 * เข้าสู่ระบบด้วย Google (OAuth 2.0 / OpenID Connect แบบ Authorization Code + PKCE) — ไม่ใช้ไลบรารีภายนอก
 * เปิด/ปิดและตั้งค่า Client ID / Secret ได้ที่ หลังบ้าน → ตั้งค่าระบบ
 *
 * ความปลอดภัย
 * - state สุ่มใช้ครั้งเดียว (กัน CSRF) หมดอายุ 10 นาที, PKCE (S256) และ nonce ผูกกับ ID token
 * - ID token ได้จาก token endpoint ของ Google โดยตรงผ่าน HTTPS จึงตรวจเฉพาะ claim (iss, aud, exp, nonce) ตามมาตรฐาน OIDC
 * - รับเฉพาะอีเมลที่ Google ยืนยันแล้ว และจำกัดโดเมนได้ (เช่น เฉพาะอีเมลของวิทยาลัย)
 * - ไม่ผูกบัญชีเดิมอัตโนมัติจากอีเมล (กันการยึดบัญชีล่วงหน้า) ผู้ใช้เดิมต้องเข้าสู่ระบบแล้วกด "เชื่อมบัญชี Google" เอง
 */

const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

function google_login_available(): bool
{
    return setting('google_login_enabled') === '1' && setting('google_client_id') !== '' && setting('google_client_secret') !== '';
}

function google_redirect_uri(): string
{
    return abs_url('google-callback.php');
}

function google_allowed_domain(): string
{
    return strtolower(trim(setting('google_allowed_domain'), " @"));
}

function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/** สร้าง URL ไปหน้าเลือกบัญชีของ Google และเก็บค่าที่ต้องตรวจตอนกลับมาไว้ใน session */
function google_auth_url(string $mode, string $next = ''): string
{
    $state = bin2hex(random_bytes(16));
    $verifier = b64url(random_bytes(48));
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['google_oauth'] = [
        'state' => $state, 'verifier' => $verifier, 'nonce' => $nonce, 'mode' => $mode, 'next' => $next,
        'uid' => in_array($mode, ['link', 'drive'], true) ? (int)(current_user()['id'] ?? 0) : 0, 'created' => time(),
    ];
    $params = [
        'client_id'             => setting('google_client_id'),
        'redirect_uri'          => google_redirect_uri(),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => b64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
        'prompt'                => 'select_account',
    ];
    if ($mode === 'drive') {
        // เชื่อมต่อ Google Drive: ขอสิทธิ์ Drive และ refresh token (ใช้งานต่อเนื่องโดยไม่ต้องเข้าสู่ระบบใหม่)
        $params['scope'] = 'openid email ' . GDRIVE_SCOPE;
        $params['access_type'] = 'offline';
        $params['prompt'] = 'consent select_account';
    } elseif (google_allowed_domain() !== '') {
        $params['hd'] = google_allowed_domain();
    }
    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/** แลก authorization code เป็น token คืนค่า array (มี id_token) หรือโยน RuntimeException */
function google_exchange_code(string $code, string $verifier): array
{
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => setting('google_client_id'),
            'client_secret' => setting('google_client_secret'),
            'redirect_uri'  => google_redirect_uri(),
            'code_verifier' => $verifier,
        ]),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('ติดต่อ Google ไม่ได้: ' . $err);
    $json = json_decode((string)$body, true);
    if ($status !== 200 || !is_array($json) || empty($json['id_token'])) {
        $msg = $json['error_description'] ?? $json['error'] ?? ('HTTP ' . $status);
        throw new RuntimeException('Google ปฏิเสธการเข้าสู่ระบบ (' . $msg . ') — ตรวจสอบ Client ID / Secret และ Redirect URI');
    }
    return $json;
}

/** อ่าน payload ของ JWT (ไม่ตรวจลายเซ็น เพราะได้รับจาก Google โดยตรงผ่าน HTTPS) */
function google_jwt_claims(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new RuntimeException('ID token ไม่ถูกต้อง');
    $claims = json_decode((string)base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!is_array($claims)) throw new RuntimeException('ID token ไม่ถูกต้อง');
    return $claims;
}

/** ตรวจ claim ของ ID token คืนค่าข้อความผิดพลาด หรือ null ถ้าผ่าน */
function google_validate_claims(array $c, string $nonce, ?int $now = null, bool $checkDomain = true): ?string
{
    $now = $now ?? time();
    if (!in_array($c['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) return 'ผู้ออก token ไม่ใช่ Google';
    $aud = $c['aud'] ?? '';
    if (is_array($aud) ? !in_array(setting('google_client_id'), $aud, true) : $aud !== setting('google_client_id')) return 'token ไม่ได้ออกให้ระบบนี้';
    if ((int)($c['exp'] ?? 0) < $now - 60) return 'token หมดอายุ กรุณาลองใหม่';
    if ((int)($c['iat'] ?? 0) > $now + 300) return 'เวลาใน token ไม่ถูกต้อง (ตรวจสอบนาฬิกาของเซิร์ฟเวอร์)';
    if (!hash_equals($nonce, (string)($c['nonce'] ?? ''))) return 'การตรวจสอบความปลอดภัยไม่ผ่าน (nonce) กรุณาลองใหม่';
    if (($c['sub'] ?? '') === '') return 'ไม่พบรหัสบัญชี Google';
    $verified = $c['email_verified'] ?? false;
    if (empty($c['email']) || !($verified === true || $verified === 'true')) return 'อีเมลของบัญชี Google นี้ยังไม่ได้รับการยืนยัน';
    $domain = $checkDomain ? google_allowed_domain() : '';
    if ($domain !== '') {
        $emailDomain = strtolower(substr(strrchr((string)$c['email'], '@'), 1));
        if (strtolower((string)($c['hd'] ?? '')) !== $domain || $emailDomain !== $domain) {
            return 'ระบบนี้รับเฉพาะบัญชี Google ของโดเมน @' . $domain;
        }
    }
    return null;
}

/** เข้าสู่ระบบด้วยบัญชีที่พบ (ตรวจสถานะบัญชีก่อน) คืนค่าข้อความผิดพลาด หรือ null ถ้าสำเร็จ */
function google_sign_in(array $user): ?string
{
    if (!empty($user['anonymized_at'])) return 'บัญชีนี้ถูกลบข้อมูลตามคำขอ PDPA แล้ว';
    if ($user['status'] === 'pending') return 'บัญชีของคุณอยู่ระหว่างรอผู้ดูแลระบบอนุมัติ';
    if ($user['status'] !== 'active') return 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', 'i', [$user['id']]);
    $GLOBALS['CURRENT_USER'] = $user;
    log_activity('login_google', $user['username']);
    return null;
}

/** ชื่อผู้ใช้ที่ยังไม่ซ้ำ จากส่วนหน้าของอีเมล */
function google_suggest_username(string $email): string
{
    $base = preg_replace('/[^a-z0-9_.]/', '', strtolower(strstr($email, '@', true) ?: 'user'));
    $base = substr(str_pad($base, 4, '0'), 0, 24);
    $name = $base;
    for ($i = 2; db_val('SELECT id FROM users WHERE username = ?', 's', [$name]); $i++) {
        $name = $base . $i;
    }
    return $name;
}

/** ปลายทางหลังเข้าสู่ระบบ (เฉพาะภายในระบบ) */
function google_safe_next(string $next): string
{
    return ($next !== '' && str_starts_with($next, BASE_URL . '/') && !str_starts_with($next, '//')) ? $next : url('index.php');
}
