<?php
/** Google ส่งผู้ใช้กลับมาที่นี่หลังเลือกบัญชี (ต้องลงทะเบียน URL นี้เป็น Authorized redirect URI ใน Google Cloud Console) */
require_once __DIR__ . '/includes/bootstrap.php';

$oauth = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']); // state ใช้ได้ครั้งเดียว
$mode = $oauth['mode'] ?? '';
$failTo = ['link' => 'profile.php', 'drive' => 'admin/settings.php#drive'][$mode] ?? 'login.php';
$fail = function (string $msg) use ($failTo) {
    log_activity('login_google_fail', mb_substr($msg, 0, 200));
    flash('danger', $msg);
    redirect($failTo);
};

if ($mode === 'drive') {
    if (setting('google_client_id') === '' || setting('google_client_secret') === '') $fail('ยังไม่ได้ตั้งค่า Client ID / Secret');
} elseif (!google_login_available()) {
    $fail('ระบบยังไม่เปิดให้เข้าสู่ระบบด้วย Google');
}
if (get('error') !== '') {
    flash('warning', get('error') === 'access_denied' ? 'ยกเลิกการเข้าสู่ระบบด้วย Google แล้ว' : 'Google แจ้งข้อผิดพลาด: ' . get('error'));
    redirect($failTo);
}
if (!$oauth || get('state') === '' || !hash_equals($oauth['state'], get('state'))) $fail('การตรวจสอบความปลอดภัยไม่ผ่าน (state) กรุณากดเข้าสู่ระบบด้วย Google ใหม่อีกครั้ง');
if (time() - $oauth['created'] > 600) $fail('หมดเวลาเข้าสู่ระบบ กรุณาลองใหม่');
if (get('code') === '') $fail('ไม่ได้รับรหัสยืนยันจาก Google');

try {
    $token = google_exchange_code(get('code'), $oauth['verifier']);
    $claims = google_jwt_claims($token['id_token']);
} catch (RuntimeException $ex) {
    $fail($ex->getMessage());
}
if ($err = google_validate_claims($claims, $oauth['nonce'], null, $mode !== 'drive')) $fail($err);

// ---------- เชื่อมต่อ Google Drive สำหรับจัดเก็บไฟล์ (ผู้ดูแลระบบ) ----------
if ($mode === 'drive') {
    $me = current_user();
    if (!$me || !is_admin() || (int)$me['id'] !== (int)$oauth['uid']) $fail('เฉพาะผู้ดูแลระบบที่เข้าสู่ระบบอยู่เท่านั้น');
    if (empty($token['refresh_token'])) $fail('Google ไม่ส่งสิทธิ์ใช้งานต่อเนื่อง (refresh token) — เปิด https://myaccount.google.com/permissions ลบสิทธิ์ของแอปนี้ แล้วกดเชื่อมต่อใหม่');
    if (!str_contains((string)($token['scope'] ?? ''), GDRIVE_SCOPE)) $fail('ยังไม่ได้อนุญาตสิทธิ์ Google Drive กรุณากดเชื่อมต่อใหม่และติ๊กอนุญาตการเข้าถึง Drive');
    gdrive_save_setting('gdrive_refresh_token', $token['refresh_token']);
    gdrive_save_setting('gdrive_account_email', strtolower((string)$claims['email']));
    gdrive_save_setting('gdrive_access_token', (string)($token['access_token'] ?? ''));
    gdrive_save_setting('gdrive_token_exp', (string)(time() + (int)($token['expires_in'] ?? 0)));
    log_activity('gdrive_connect', (string)$claims['email']);
    flash('success', 'เชื่อมต่อ Google Drive ของ ' . $claims['email'] . ' แล้ว — ตั้ง Folder ID แล้วกด "ทดสอบการเชื่อมต่อ"');
    redirect('admin/settings.php#drive');
}

$sub = (string)$claims['sub'];
$email = strtolower((string)$claims['email']);
$owner = db_row('SELECT * FROM users WHERE google_sub = ?', 's', [$sub]);

// ---------- เชื่อมบัญชี Google กับบัญชีที่เข้าสู่ระบบอยู่ ----------
if ($oauth['mode'] === 'link') {
    $me = current_user();
    if (!$me || (int)$me['id'] !== (int)$oauth['uid']) $fail('กรุณาเข้าสู่ระบบก่อนเชื่อมบัญชี Google');
    if ($owner && (int)$owner['id'] !== (int)$me['id']) $fail('บัญชี Google ' . $email . ' ถูกเชื่อมกับสมาชิกคนอื่นแล้ว');
    db_exec('UPDATE users SET google_sub = ?, google_email = ? WHERE id = ?', 'ssi', [$sub, $email, $me['id']]);
    log_activity('google_link', $email);
    flash('success', 'เชื่อมบัญชี Google ' . $email . ' แล้ว ครั้งต่อไปกด "เข้าสู่ระบบด้วย Google" ได้เลย');
    redirect('profile.php');
}

// ---------- เข้าสู่ระบบ ----------
if ($owner) {
    if ($owner['google_email'] !== $email) db_exec('UPDATE users SET google_email = ? WHERE id = ?', 'si', [$email, $owner['id']]);
    if ($err = google_sign_in($owner)) $fail($err);
    flash('success', 'ยินดีต้อนรับ ' . $owner['fullname']);
    redirect(google_safe_next($oauth['next']));
}

// ยังไม่มีบัญชีที่เชื่อมกับ Google นี้
if (db_val('SELECT id FROM users WHERE LOWER(email) = ? OR LOWER(google_email) = ?', 'ss', [$email, $email])) {
    flash('warning', 'มีบัญชีที่ใช้อีเมล ' . $email . ' อยู่แล้ว กรุณาเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน แล้วกด "เชื่อมบัญชี Google" ที่หน้าข้อมูลส่วนตัว (เพื่อความปลอดภัย ระบบไม่เชื่อมให้อัตโนมัติ)');
    redirect('login.php');
}
if (setting('google_allow_register', '1') !== '1') {
    $fail('ยังไม่มีบัญชีสำหรับ ' . $email . ' และระบบไม่เปิดให้สมัครด้วย Google กรุณาติดต่อผู้ดูแลระบบ');
}
// เก็บข้อมูลจาก Google ไว้ชั่วคราว แล้วให้เลือกแผนกและยอมรับนโยบายก่อนสร้างบัญชี
$_SESSION['google_pending'] = [
    'sub' => $sub, 'email' => $email, 'name' => mb_substr(trim((string)($claims['name'] ?? '')), 0, 150), 'created' => time(), 'next' => $oauth['next'],
];
redirect('google-register.php');
