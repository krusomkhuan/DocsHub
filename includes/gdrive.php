<?php
/**
 * จัดเก็บไฟล์ใน Google Drive (เปิด/ปิด และกำหนด Folder ID ได้ที่ หลังบ้าน → ตั้งค่าระบบ)
 *
 * การทำงาน
 * - ผู้ดูแลระบบกด "เชื่อมต่อ Google Drive" เลือกบัญชี Google แล้วอนุญาต → ระบบเก็บ refresh token ไว้ (ใช้ OAuth Client เดียวกับเข้าสู่ระบบด้วย Google)
 * - ไฟล์ที่อัปโหลดถูกบันทึกลงเครื่องชั่วคราว แล้วส่งขึ้นโฟลเดอร์ใน Drive และลบสำเนาในเครื่อง
 *   ฐานข้อมูลเก็บตำแหน่งเป็น "gdrive:<fileId>" (ภาพประกอบรายงาน: "gdrive:<ภาพหลัก>|<ภาพย่อ>")
 * - ไฟล์ใน Drive เป็นส่วนตัวของบัญชีที่เชื่อม (ไม่แชร์สาธารณะ) ผู้ใช้ดาวน์โหลดผ่านระบบซึ่งตรวจสิทธิ์ก่อนทุกครั้ง
 * - ไฟล์เดิมที่อยู่ใน uploads/docs ยังใช้งานได้ตามปกติ (ระบบดูจากค่าที่เก็บในฐานข้อมูลว่าไฟล์อยู่ที่ไหน)
 * - เปิดสวิตช์แล้ว = เก็บใน Google Drive เท่านั้น: ถ้าส่งขึ้น Drive ไม่สำเร็จ ระบบลบไฟล์ชั่วคราว แจ้งผู้ใช้ให้ลองใหม่ และบันทึก gdrive_error
 *   (ไม่มีการเก็บสำรองไว้ในเครื่อง) · ระหว่างไม่ได้เชื่อมต่อ Drive จะอัปโหลดไฟล์ไม่ได้
 */

const GDRIVE_PREFIX = 'gdrive:';
const GDRIVE_SCOPE  = 'https://www.googleapis.com/auth/drive';
const GDRIVE_API    = 'https://www.googleapis.com/drive/v3/files';
const GDRIVE_UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

function gdrive_connected(): bool
{
    return setting('gdrive_refresh_token') !== '' && setting('google_client_id') !== '' && setting('google_client_secret') !== '';
}

/** เปิดเก็บไฟล์ใหม่ใน Drive และพร้อมใช้งาน */
function storage_drive_enabled(): bool
{
    return setting('storage_driver') === 'gdrive' && gdrive_connected() && setting('gdrive_folder_id') !== '';
}

/** ดึง Folder ID จากลิงก์โฟลเดอร์ หรือรหัสที่วางมา */
function gdrive_parse_folder_id(string $input): string
{
    $input = trim($input);
    if (preg_match('#/folders/([A-Za-z0-9_-]{10,})#', $input, $m)) return $m[1];
    if (preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $input, $m)) return $m[1];
    return preg_match('/^[A-Za-z0-9_-]{10,}$/', $input) ? $input : '';
}

function gdrive_save_setting(string $key, string $value): void
{
    db_exec('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', [$key, $value]);
}

/** access token (อายุ 1 ชั่วโมง) — เก็บไว้ใช้ซ้ำ หมดอายุแล้วขอใหม่ด้วย refresh token */
function gdrive_access_token(bool $force = false): string
{
    static $mem = null;
    if (!$force && $mem && $mem['exp'] > time() + 60) return $mem['token'];
    $cached = setting('gdrive_access_token');
    $exp = (int)setting('gdrive_token_exp', '0');
    if (!$force && $cached !== '' && $exp > time() + 60 && !$mem) {
        $mem = ['token' => $cached, 'exp' => $exp];
        return $cached;
    }
    if (setting('gdrive_refresh_token') === '') throw new RuntimeException('ยังไม่ได้เชื่อมต่อ Google Drive');
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'refresh_token', 'refresh_token' => setting('gdrive_refresh_token'),
            'client_id' => setting('google_client_id'), 'client_secret' => setting('google_client_secret'),
        ]),
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    if ($status !== 200 || empty($json['access_token'])) {
        $reason = $json['error'] ?? ($err ?: 'HTTP ' . $status);
        $hints = [
            'invalid_grant'       => 'สิทธิ์เข้าถึง Google Drive หมดอายุหรือถูกยกเลิก — ผู้ดูแลระบบกด "เชื่อมต่อ Google Drive" ใหม่ในหน้าตั้งค่า',
            'unauthorized_client' => 'สิทธิ์ Google Drive ที่บันทึกไว้ออกให้ Client ID ตัวเดิม (มีการเปลี่ยน Client ID) — ผู้ดูแลระบบกด "เชื่อมต่อ Google Drive" ใหม่ในหน้าตั้งค่า',
            'invalid_client'      => 'Client ID หรือ Client Secret ไม่ถูกต้อง — ผู้ดูแลระบบตรวจค่าในการ์ด "เข้าสู่ระบบด้วย Google"',
        ];
        throw new RuntimeException(($hints[$reason] ?? 'ขอสิทธิ์ Google Drive ไม่สำเร็จ') . ' (' . $reason . ')');
    }
    $mem = ['token' => $json['access_token'], 'exp' => time() + (int)($json['expires_in'] ?? 3600)];
    gdrive_save_setting('gdrive_access_token', $mem['token']);
    gdrive_save_setting('gdrive_token_exp', (string)$mem['exp']);
    return $mem['token'];
}

/** เรียก Drive API คืนค่า [status, body, headers] (ขอ token ใหม่อัตโนมัติถ้าได้ 401) */
function gdrive_request(string $method, string $url, array $opts = [], bool $retry = true): array
{
    $headers = [];
    $extraHeaders = $opts['headers'] ?? [];
    $curlOpts = $opts;
    unset($curlOpts['headers']); // ไม่ใช่ค่า CURLOPT
    $ch = curl_init($url);
    curl_setopt_array($ch, $curlOpts + [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => array_merge(['Authorization: Bearer ' . gdrive_access_token()], $extraHeaders),
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('ติดต่อ Google Drive ไม่ได้: ' . $err);
    if ($status === 401 && $retry) {
        gdrive_access_token(true);
        return gdrive_request($method, $url, $opts, false);
    }
    return [$status, (string)$body, $headers];
}

function gdrive_error(int $status, string $body): string
{
    $msg = json_decode($body, true)['error']['message'] ?? '';
    $hints = [403 => 'ไม่มีสิทธิ์ในโฟลเดอร์ หรือยังไม่ได้เปิดใช้ Google Drive API', 404 => 'ไม่พบโฟลเดอร์/ไฟล์ (ตรวจสอบ Folder ID และบัญชีที่เชื่อม)'];
    return trim(($hints[$status] ?? 'HTTP ' . $status) . ($msg ? ' — ' . $msg : ''));
}

/**
 * ตรวจว่า Client ID + Client Secret เป็นคู่ที่ถูกต้อง (ไม่ต้องให้ผู้ใช้เข้าสู่ระบบ)
 * ส่ง refresh token ปลอมไปที่ Google: ตอบ invalid_grant = client ถูกต้อง · invalid_client = ID/Secret ผิด
 * คืนค่า [ok(bool), ข้อความ]
 */
function google_check_client(string $clientId, string $secret): array
{
    if ($clientId === '' || $secret === '') return [false, 'ยังไม่ได้กรอก Client ID หรือ Client Secret'];
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => 'docshub-client-check', 'client_id' => $clientId, 'client_secret' => $secret]),
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) return [false, 'ติดต่อ Google ไม่ได้: ' . $err];
    $reason = json_decode((string)$body, true)['error'] ?? '';
    return match ($reason) {
        'invalid_grant'       => [true, 'Client ID และ Client Secret ถูกต้อง (เป็นคู่เดียวกัน)'],
        'invalid_client'      => [false, 'Client ID หรือ Client Secret ไม่ถูกต้อง หรือไม่ใช่คู่เดียวกัน — คัดลอกทั้งสองค่าจาก OAuth client ตัวเดียวกันใน Google Cloud Console (invalid_client)'],
        'unauthorized_client' => [false, 'Client นี้ใช้ขอสิทธิ์แบบนี้ไม่ได้ — ตรวจว่าประเภทเป็น Web application (unauthorized_client)'],
        default               => [false, 'Google ตอบกลับไม่คาดคิด' . ($reason !== '' ? ' (' . $reason . ')' : '')],
    };
}

/**
 * ตรวจการเชื่อมต่อ Google Drive ทีละขั้น คืนค่ารายการ [หัวข้อ, ok(true/false/null=ข้าม), ข้อความ]
 * หยุดที่ขั้นแรกที่ไม่ผ่าน เพื่อบอกจุดที่ต้องแก้ชัดเจน
 */
function gdrive_diagnose(string $folderId): array
{
    $steps = [];
    $add = function (string $label, ?bool $ok, string $msg) use (&$steps) { $steps[] = [$label, $ok, $msg]; return $ok; };
    [$ok, $msg] = google_check_client(setting('google_client_id'), setting('google_client_secret'));
    if (!$add('Client ID / Client Secret', $ok, $msg)) return $steps;
    if (setting('gdrive_refresh_token') === '') {
        $add('บัญชี Google Drive', false, 'ยังไม่ได้เชื่อมต่อ — กด "เชื่อมต่อ Google Drive"');
        return $steps;
    }
    try {
        gdrive_access_token(true);
        $add('สิทธิ์เข้าถึง Drive (' . (setting('gdrive_account_email') ?: 'บัญชีที่เชื่อม') . ')', true, 'ขอสิทธิ์สำเร็จ');
    } catch (Throwable $ex) {
        $add('สิทธิ์เข้าถึง Drive', false, $ex->getMessage());
        return $steps;
    }
    if ($folderId === '') {
        $add('โฟลเดอร์ปลายทาง', false, 'ยังไม่ได้ระบุ Folder ID');
        return $steps;
    }
    try {
        $info = gdrive_folder_info($folderId);
        $add('โฟลเดอร์ปลายทาง', true, 'เข้าถึงโฟลเดอร์ "' . $info['name'] . '" ได้');
    } catch (Throwable $ex) {
        $add('โฟลเดอร์ปลายทาง', false, $ex->getMessage());
        return $steps;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'dh');
    try {
        file_put_contents($tmp, 'Docs Hub connection test ' . date('c'));
        // ทดสอบเขียนจริงในโฟลเดอร์ใบเสร็จ (สร้างให้ถ้ายังไม่มี) แล้วย้ายไฟล์ทดสอบไปถังขยะ
        gdrive_trash(gdrive_upload($tmp, 'docshub-connection-test.txt', 'text/plain', storage_category_folder('receipt')));
        $add('ทดสอบอัปโหลดไฟล์', true, 'เขียนไฟล์ในโฟลเดอร์ ' . storage_category_path('receipt') . ' ได้');
    } catch (Throwable $ex) {
        $add('ทดสอบอัปโหลดไฟล์', false, $ex->getMessage());
        return $steps;
    } finally {
        @unlink($tmp);
    }
    $bad = array_filter(storage_ensure_all_folders(), fn($v) => $v !== 'ok');
    $add('โฟลเดอร์ย่อยตามประเภท', !$bad, $bad ? 'สร้าง/เข้าถึงไม่ได้: ' . implode(', ', array_keys($bad)) : 'พร้อมใช้งานครบ');
    return $steps;
}

/** ข้อมูลโฟลเดอร์ปลายทาง (ใช้ทดสอบการตั้งค่า) */
function gdrive_folder_info(string $folderId): array
{
    [$st, $body] = gdrive_request('GET', GDRIVE_API . '/' . rawurlencode($folderId) . '?supportsAllDrives=true&fields=' . rawurlencode('id,name,mimeType,trashed,webViewLink,capabilities(canAddChildren)'));
    if ($st !== 200) throw new RuntimeException(gdrive_error($st, $body));
    $info = json_decode($body, true);
    if (($info['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') throw new RuntimeException('รหัสนี้ไม่ใช่โฟลเดอร์');
    if (!empty($info['trashed'])) throw new RuntimeException('โฟลเดอร์นี้อยู่ในถังขยะ');
    if (empty($info['capabilities']['canAddChildren'])) throw new RuntimeException('บัญชีที่เชื่อมไม่มีสิทธิ์เพิ่มไฟล์ในโฟลเดอร์นี้ (ต้องเป็นผู้แก้ไขได้)');
    return $info;
}

/** อัปโหลดไฟล์ขึ้น Drive (resumable upload รองรับไฟล์ใหญ่) คืนค่า file id */
function gdrive_upload(string $localPath, string $name, string $mime, ?string $folderId = null): string
{
    $size = filesize($localPath);
    $meta = json_encode(['name' => $name, 'parents' => [$folderId ?: setting('gdrive_folder_id')], 'description' => 'อัปโหลดจาก ' . setting('site_name') . ' ' . date('Y-m-d H:i')], JSON_UNESCAPED_UNICODE);
    [$st, $body, $h] = gdrive_request('POST', GDRIVE_UPLOAD . '?uploadType=resumable&supportsAllDrives=true&fields=id', [
        CURLOPT_POSTFIELDS => $meta,
        'headers' => ['Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Type: ' . $mime, 'X-Upload-Content-Length: ' . $size],
    ]);
    if ($st !== 200 || empty($h['location'])) throw new RuntimeException('เริ่มอัปโหลดขึ้น Drive ไม่สำเร็จ: ' . gdrive_error($st, $body));
    $fh = fopen($localPath, 'rb');
    [$st, $body] = gdrive_request('PUT', $h['location'], [
        CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fh, CURLOPT_INFILESIZE => $size,
        'headers' => ['Content-Type: ' . $mime],
    ]);
    fclose($fh);
    $id = json_decode($body, true)['id'] ?? '';
    if (!in_array($st, [200, 201], true) || $id === '') throw new RuntimeException('อัปโหลดขึ้น Drive ไม่สำเร็จ: ' . gdrive_error($st, $body));
    return $id;
}

/** ย้ายไฟล์ใน Drive ไปถังขยะ (กู้คืนได้ใน 30 วัน) */
function gdrive_trash(string $fileId): void
{
    gdrive_request('PATCH', GDRIVE_API . '/' . rawurlencode($fileId) . '?supportsAllDrives=true&fields=id', [
        CURLOPT_POSTFIELDS => '{"trashed":true}', 'headers' => ['Content-Type: application/json'],
    ]);
}

/* ======================= ชั้นจัดเก็บไฟล์ (ใช้ทั้งระบบ) ======================= */

function storage_is_drive(?string $ref): bool
{
    return $ref !== null && str_starts_with($ref, GDRIVE_PREFIX);
}

/** รหัสไฟล์ Drive ทั้งหมดในค่าที่เก็บ (ภาพประกอบรายงานมี 2 ไฟล์: ภาพหลัก|ภาพย่อ) */
function storage_drive_ids(string $ref): array
{
    return array_values(array_filter(explode('|', substr($ref, strlen(GDRIVE_PREFIX)))));
}

function storage_mime(string $name): string
{
    return [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar',
    ][strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

/**
 * ส่งไฟล์ที่บันทึกไว้ในเครื่อง (UPLOAD_DIR/$rel) ขึ้น Drive ถ้าเปิดใช้
 * สำเร็จ: ลบไฟล์ในเครื่อง คืนค่า "gdrive:<id>" · ไม่สำเร็จหรือไม่ได้เปิด: คืนค่า $rel เดิม (ไฟล์อยู่ในเครื่อง)
 */
function storage_push(string $rel, string $displayName = '', string $category = ''): string
{
    if (setting('storage_driver') !== 'gdrive') return $rel; // ปิดสวิตช์: เก็บในเครื่อง
    // เปิดสวิตช์ = เก็บใน Google Drive เท่านั้น: ไฟล์ในเครื่องเป็นเพียงไฟล์ชั่วคราว ส่งไม่สำเร็จให้ลบทิ้งและแจ้งผู้ใช้
    $path = UPLOAD_DIR . '/' . $rel;
    try {
        if (!storage_drive_enabled()) throw new RuntimeException('ยังไม่ได้เชื่อมต่อ Google Drive หรือยังไม่ได้กำหนด Folder ID');
        $name = date('Y-m-d_His') . '_' . ($displayName !== '' ? preg_replace('/[\\\\\/:*?"<>|]+/', '_', $displayName) : basename($rel));
        $id = gdrive_upload($path, $name, storage_mime($rel), storage_category_folder($category));
        @unlink($path);
        return GDRIVE_PREFIX . $id;
    } catch (Throwable $ex) {
        @unlink($path);
        log_activity('gdrive_error', mb_substr('อัปโหลด ' . basename($rel) . ': ' . $ex->getMessage(), 0, 255));
        throw new RuntimeException('บันทึกไฟล์ลง Google Drive ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้งผู้ดูแลระบบ (' . $ex->getMessage() . ')');
    }
}

/** ลบไฟล์ตามค่าที่เก็บ (ในเครื่อง หรือย้ายไปถังขยะของ Drive) */
function storage_delete(?string $ref): void
{
    if (!$ref) return;
    if (storage_is_drive($ref)) {
        foreach (storage_drive_ids($ref) as $id) {
            try {
                gdrive_trash($id);
            } catch (Throwable $ex) {
                log_activity('gdrive_error', mb_substr('ลบ ' . $id . ': ' . $ex->getMessage(), 0, 255));
            }
        }
        return;
    }
    if (strpos($ref, '..') === false) {
        $path = UPLOAD_DIR . '/' . $ref;
        if (is_file($path)) @unlink($path);
    }
}

/**
 * ส่งไฟล์ให้เบราว์เซอร์ (จากเครื่อง หรือดึงจาก Drive แบบ stream) คืนค่า false ถ้าไม่พบไฟล์
 * $disposition: null = ไม่ส่ง Content-Disposition, หรือข้อความเต็ม เช่น 'attachment; filename="a.pdf"'
 */
function storage_serve(string $ref, string $mime, ?string $disposition = null, array $extraHeaders = []): bool
{
    $send = function (?int $len) use ($mime, $disposition, $extraHeaders) {
        header('Content-Type: ' . $mime);
        if ($len !== null) header('Content-Length: ' . $len);
        header('X-Content-Type-Options: nosniff');
        if ($disposition) header('Content-Disposition: ' . $disposition);
        foreach ($extraHeaders as $h) header($h);
    };
    if (!storage_is_drive($ref)) {
        $path = UPLOAD_DIR . '/' . $ref;
        if ($ref === '' || strpos($ref, '..') !== false || !is_file($path)) return false;
        $send(filesize($path));
        readfile($path);
        return true;
    }
    $id = storage_drive_ids($ref)[0] ?? '';
    if ($id === '') return false;
    try {
        $token = gdrive_access_token();
    } catch (Throwable $ex) {
        log_activity('gdrive_error', mb_substr('ดาวน์โหลด: ' . $ex->getMessage(), 0, 255));
        return false;
    }
    $status = 0;
    $started = false;
    $len = null;
    $ch = curl_init(GDRIVE_API . '/' . rawurlencode($id) . '?alt=media&supportsAllDrives=true');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 300,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$status, &$len) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) $status = (int)$m[1];
            elseif (stripos($line, 'content-length:') === 0) $len = (int)trim(substr($line, 15));
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$status, &$started, &$len, $send) {
            if ($status !== 200) return strlen($chunk); // ทิ้งข้อความผิดพลาด ไม่ส่งให้ผู้ใช้
            if (!$started) { $send($len); $started = true; }
            echo $chunk;
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
    if (!$started) {
        log_activity('gdrive_error', 'ดาวน์โหลดไฟล์ ' . $id . ' ไม่สำเร็จ (HTTP ' . $status . ')');
        return false;
    }
    return true;
}

/* ======================= ย้ายไฟล์เดิมจากเครื่องขึ้น Google Drive ======================= */

/** รายการไฟล์ในเครื่องที่ยังไม่ได้ย้าย: [ตาราง, คอลัมน์, id, ค่าที่เก็บ, ชื่อไฟล์ที่ใช้ใน Drive] */
function storage_local_items(): array
{
    $items = [];
    $sources = [
        ['documents', 'file_name', "SELECT d.id, d.file_name AS f, d.file_original AS n, CONCAT('doc:', t.slug) AS c FROM documents d JOIN doc_types t ON t.id = d.type_id WHERE d.file_name NOT LIKE 'gdrive:%'"],
        ['trip_reports', 'file_name', "SELECT id, file_name AS f, file_original AS n, 'trip-reports' AS c FROM trip_reports WHERE file_name IS NOT NULL AND file_name NOT LIKE 'gdrive:%'"],
        ['trip_report_images', 'file_name', "SELECT id, file_name AS f, NULL AS n, 'trip-reports' AS c FROM trip_report_images WHERE file_name NOT LIKE 'gdrive:%'"],
        ['ledger_entries', 'file_name', "SELECT id, file_name AS f, file_original AS n, 'receipt' AS c FROM ledger_entries WHERE file_name IS NOT NULL AND file_name NOT LIKE 'gdrive:%'"],
        // ภาพโปรไฟล์ไม่ย้าย: เก็บในเครื่องที่ uploads/docs/avatars เสมอ
    ];
    foreach ($sources as [$table, $col, $sql]) {
        foreach (db_all($sql) as $r) {
            $items[] = ['table' => $table, 'col' => $col, 'id' => (int)$r['id'], 'ref' => $r['f'], 'name' => $r['n'] ?: basename($r['f']), 'cat' => $r['c']];
        }
    }
    return $items;
}

/**
 * ย้ายไฟล์ในเครื่องขึ้นโฟลเดอร์ Drive ทีละรายการ (สูงสุด $limit รายการต่อครั้ง เพื่อไม่ให้หน้าเว็บหมดเวลา)
 * ลำดับที่ปลอดภัย: อัปโหลดสำเร็จ → แก้ตำแหน่งในฐานข้อมูล → ลบไฟล์ในเครื่อง (ถ้าขั้นใดล้มเหลว ไฟล์เดิมยังอยู่)
 * คืนค่า ['moved' => n, 'missing' => [...], 'failed' => [...], 'remaining' => n]
 */
function storage_migrate_to_drive(int $limit = 25, ?callable $progress = null): array
{
    if (!gdrive_connected() || setting('gdrive_folder_id') === '') {
        throw new RuntimeException('ยังไม่ได้เชื่อมต่อ Google Drive หรือยังไม่ได้กำหนด Folder ID');
    }
    gdrive_folder_info(setting('gdrive_folder_id')); // ตรวจสิทธิ์โฟลเดอร์ก่อนเริ่ม
    @set_time_limit(0);
    $result = ['moved' => 0, 'missing' => [], 'failed' => [], 'remaining' => 0];
    $items = storage_local_items();
    $done = 0;
    foreach ($items as $it) {
        if ($limit > 0 && $done >= $limit) break;
        $label = $it['table'] . ' #' . $it['id'] . ' (' . $it['name'] . ')';
        $path = UPLOAD_DIR . '/' . $it['ref'];
        if (strpos($it['ref'], '..') !== false || !is_file($path)) {
            $result['missing'][] = $label; // ไม่มีไฟล์ในเครื่อง (ข้าม ไม่แก้ฐานข้อมูล)
            continue;
        }
        $done++;
        try {
            $prefix = ['documents' => 'doc', 'trip_reports' => 'trip-file', 'trip_report_images' => 'trip-img', 'ledger_entries' => 'ledger', 'users' => 'avatar'][$it['table']];
            $name = $prefix . '_' . $it['id'] . '_' . preg_replace('/[\\\/:*?"<>|]+/', '_', $it['name']);
            $newRef = GDRIVE_PREFIX . gdrive_upload($path, $name, storage_mime($it['ref']), $folder = storage_category_folder($it['cat']));
            $thumbPath = null;
            if ($it['table'] === 'trip_report_images') {
                $thumbPath = UPLOAD_DIR . '/' . trip_thumb_name($it['ref']);
                if (is_file($thumbPath)) {
                    $newRef .= '|' . gdrive_upload($thumbPath, preg_replace('/\.jpg$/i', '-t.jpg', $name), 'image/jpeg', $folder);
                }
            }
            db_exec("UPDATE `{$it['table']}` SET `{$it['col']}` = ? WHERE id = ? AND `{$it['col']}` = ?", 'sis', [$newRef, $it['id'], $it['ref']]);
            @unlink($path);
            if ($thumbPath && is_file($thumbPath)) @unlink($thumbPath);
            $result['moved']++;
            if ($progress) $progress('ok', $label);
        } catch (Throwable $ex) {
            $result['failed'][] = $label . ': ' . $ex->getMessage();
            log_activity('gdrive_error', mb_substr('ย้าย ' . $label . ': ' . $ex->getMessage(), 0, 255));
            if ($progress) $progress('fail', $label . ': ' . $ex->getMessage());
        }
    }
    $result['remaining'] = count(array_filter(storage_local_items(), fn($i) => strpos($i['ref'], '..') === false && is_file(UPLOAD_DIR . '/' . $i['ref'])));
    log_activity('gdrive_migrate', 'ย้าย ' . $result['moved'] . ' ไฟล์ · เหลือ ' . $result['remaining'] . ' · ล้มเหลว ' . count($result['failed']));
    return $result;
}

/** ดาวน์โหลดไฟล์จาก Drive ลงเครื่อง */
function gdrive_download_to(string $fileId, string $dest): void
{
    $fh = fopen($dest, 'wb');
    $ch = curl_init(GDRIVE_API . '/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true');
    curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 120, CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . gdrive_access_token()]]);
    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fh);
    if (!$ok || $status !== 200) {
        @unlink($dest);
        throw new RuntimeException('ดาวน์โหลดจาก Drive ไม่สำเร็จ (' . ($err ?: 'HTTP ' . $status) . ')');
    }
}

/**
 * ภาพโปรไฟล์เก็บในเครื่องเสมอ: ดึงภาพโปรไฟล์ที่เคยถูกย้ายขึ้น Drive กลับมาไว้ที่ uploads/docs/avatars
 * ลำดับ: ดาวน์โหลด → ตรวจว่าเป็นภาพ → แก้ฐานข้อมูล → ย้ายไฟล์ใน Drive ไปถังขยะ
 */
function storage_pull_avatars_from_drive(): array
{
    $rows = db_all("SELECT id, avatar FROM users WHERE avatar LIKE 'gdrive:%'");
    $res = ['moved' => 0, 'failed' => []];
    if (!$rows) return $res;
    if (!gdrive_connected()) throw new RuntimeException('ต้องเชื่อมต่อ Google Drive ก่อน จึงดึงภาพโปรไฟล์กลับมาได้');
    $dir = UPLOAD_DIR . '/avatars';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('สร้างโฟลเดอร์ uploads/docs/avatars ไม่ได้');
    foreach ($rows as $r) {
        $name = 'avatars/u' . $r['id'] . '_' . bin2hex(random_bytes(5)) . '.jpg';
        $dest = UPLOAD_DIR . '/' . $name;
        try {
            gdrive_download_to(storage_drive_ids($r['avatar'])[0] ?? '', $dest);
            if (!@getimagesize($dest)) {
                @unlink($dest);
                throw new RuntimeException('ไฟล์ที่ได้ไม่ใช่รูปภาพ');
            }
            db_exec('UPDATE users SET avatar = ? WHERE id = ? AND avatar = ?', 'sis', [$name, $r['id'], $r['avatar']]);
            storage_delete($r['avatar']);
            $res['moved']++;
        } catch (Throwable $ex) {
            $res['failed'][] = 'สมาชิก #' . $r['id'] . ': ' . $ex->getMessage();
            log_activity('gdrive_error', mb_substr('ดึงภาพโปรไฟล์ #' . $r['id'] . ': ' . $ex->getMessage(), 0, 255));
        }
    }
    log_activity('avatars_to_local', 'ดึงภาพโปรไฟล์กลับมาเก็บในเครื่อง ' . $res['moved'] . ' ภาพ');
    return $res;
}

/* ======================= โฟลเดอร์ย่อยตามประเภทไฟล์ ======================= */

/** โฟลเดอร์ย่อยของแต่ละประเภท (ภายในโฟลเดอร์หลักตาม Folder ID ซึ่งคือโฟลเดอร์ DocsHub) */
/** โฟลเดอร์ใน Drive ตามประเภทเอกสาร (slug ของประเภท) — ประเภทอื่นทั้งหมดไปที่ uploads/docs */
const STORAGE_DOC_FOLDERS = [
    'orders'       => 'uploads/commands',
    'lesson-plans' => 'uploads/plans',
    'course-plans' => 'uploads/plans',
    'textbooks'    => 'uploads/books',
    'certificates' => 'uploads/certs',
];
const STORAGE_OTHER_FOLDER = 'uploads/docs';
const STORAGE_TRIP_FOLDER  = 'uploads/trips/reports'; // ภาพประกอบและไฟล์แนบรายงานไปราชการ
const STORAGE_BACKUP_FOLDER = 'uploads/backups';     // ไฟล์สำรองข้อมูล (.zip)

/**
 * เส้นทางโฟลเดอร์ย่อย (ภายในโฟลเดอร์ DocsHub ตาม Folder ID) ของแต่ละประเภทไฟล์
 * 'doc:<slug>' = เอกสารตามประเภท · 'receipt' = ใบเสร็จ · 'trip-reports' = รายงานไปราชการ · อื่น ๆ = uploads/docs
 */
function storage_category_path(string $category): string
{
    if ($category === 'receipt') $path = trim(setting('gdrive_receipt_path', 'uploads/receipt'), '/ ');
    elseif ($category === 'trip-reports') $path = STORAGE_TRIP_FOLDER;
    elseif ($category === 'backups') $path = STORAGE_BACKUP_FOLDER;
    elseif (str_starts_with($category, 'doc:')) $path = STORAGE_DOC_FOLDERS[substr($category, 4)] ?? STORAGE_OTHER_FOLDER;
    else $path = STORAGE_OTHER_FOLDER;
    // Folder ID ชี้ที่โฟลเดอร์ uploads เอง → ตัด uploads/ ข้างหน้าออก ไม่สร้าง uploads ซ้อนใน uploads
    if (strcasecmp(setting('gdrive_folder_name'), 'uploads') === 0 && str_starts_with($path, 'uploads/')) $path = substr($path, 8);
    return $path;
}

/** ตำแหน่งใน Drive สำหรับแสดงผล เช่น DocsHub/uploads/backups (ชื่อโฟลเดอร์ตาม Folder ID + เส้นทางย่อย) */
function storage_display_path(string $category): string
{
    return (setting('gdrive_folder_name') ?: 'DocsHub') . '/' . storage_category_path($category);
}

/** หา (หรือสร้าง) โฟลเดอร์ย่อยตามเส้นทาง เช่น uploads/receipt ภายในโฟลเดอร์ $parentId คืนค่า folder id */
function gdrive_ensure_path(string $parentId, string $path): string
{
    static $memo = []; // โฟลเดอร์ที่หาเจอ/สร้างแล้วในคำขอนี้ (ผลค้นหาของ Drive อาจยังไม่เห็นโฟลเดอร์ที่เพิ่งสร้าง → กันสร้างซ้ำ)
    foreach (array_filter(explode('/', $path), fn($p) => trim($p) !== '') as $part) {
        $part = trim($part);
        if (isset($memo[$parentId . '/' . $part])) { $parentId = $memo[$parentId . '/' . $part]; continue; }
        $q = "mimeType = 'application/vnd.google-apps.folder' and trashed = false and name = '" . str_replace(["\\", "'"], ["\\\\", "\'"], $part) . "' and '" . $parentId . "' in parents";
        [$st, $body] = gdrive_request('GET', GDRIVE_API . '?' . http_build_query([
            'q' => $q, 'fields' => 'files(id,name)', 'pageSize' => 1, 'orderBy' => 'createdTime', // มีชื่อซ้ำ → ใช้โฟลเดอร์ที่เก่าที่สุดเสมอ
            'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
        ]));
        if ($st !== 200) throw new RuntimeException('ค้นหาโฟลเดอร์ "' . $part . '" ไม่สำเร็จ: ' . gdrive_error($st, $body));
        $found = json_decode($body, true)['files'][0]['id'] ?? '';
        if ($found === '') { // ยังไม่มี → สร้างให้ (ครั้งเดียว ใช้ซ้ำจาก $memo และค่าที่จำไว้)
            [$st, $body] = gdrive_request('POST', GDRIVE_API . '?supportsAllDrives=true&fields=id', [
                CURLOPT_POSTFIELDS => json_encode(['name' => $part, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parentId]], JSON_UNESCAPED_UNICODE),
                'headers' => ['Content-Type: application/json; charset=UTF-8'],
            ]);
            $found = json_decode($body, true)['id'] ?? '';
            if ($st !== 200 || $found === '') throw new RuntimeException('สร้างโฟลเดอร์ "' . $part . '" ไม่สำเร็จ: ' . gdrive_error($st, $body));
            log_activity('gdrive_folder', 'สร้างโฟลเดอร์ ' . $part);
        }
        $memo[$parentId . '/' . $part] = $found;
        $parentId = $found;
    }
    return $parentId;
}

/** folder id ปลายทางของประเภทไฟล์ (null = โฟลเดอร์หลัก) จำค่าไว้ และสร้างใหม่ถ้าโฟลเดอร์ถูกลบ */
function storage_category_folder(string $category): ?string
{
    $path = storage_category_path($category);
    if ($path === '') return null;
    $root = setting('gdrive_folder_id');
    $key = 'gdrive_dir_' . substr(md5($path), 0, 12); // จำ folder id ตามเส้นทาง (หลายประเภทใช้โฟลเดอร์เดียวกันได้)
    $cached = json_decode(setting($key), true);
    if (is_array($cached) && ($cached['root'] ?? '') === $root && ($cached['path'] ?? '') === $path && !empty($cached['id'])) {
        return $cached['id'];
    }
    $id = gdrive_ensure_path($root, $path);
    gdrive_save_setting($key, json_encode(['root' => $root, 'path' => $path, 'id' => $id]));
    return $id;
}

/** ย้ายไฟล์ Drive หนึ่งไฟล์เข้าโฟลเดอร์ปลายทาง (ถ้ายังไม่ได้อยู่) คืนค่า true ถ้ามีการย้าย */
function gdrive_move_into(string $fileId, string $target): bool
{
    [$st, $body] = gdrive_request('GET', GDRIVE_API . '/' . rawurlencode($fileId) . '?supportsAllDrives=true&fields=parents');
    if ($st !== 200) throw new RuntimeException(gdrive_error($st, $body));
    $parents = json_decode($body, true)['parents'] ?? [];
    if (in_array($target, $parents, true)) return false;
    [$st, $body] = gdrive_request('PATCH', GDRIVE_API . '/' . rawurlencode($fileId) . '?' . http_build_query([
        'addParents' => $target, 'removeParents' => implode(',', $parents), 'supportsAllDrives' => 'true', 'fields' => 'id',
    ]), [CURLOPT_POSTFIELDS => '{}', 'headers' => ['Content-Type: application/json']]);
    if ($st !== 200) throw new RuntimeException(gdrive_error($st, $body));
    return true;
}

/**
 * จัดไฟล์ที่อยู่ใน Drive แล้วเข้าโฟลเดอร์ตามประเภท (commands / plans / books / certs / receipt / docs)
 * ใช้เมื่อไฟล์ถูกย้ายขึ้นก่อนแยกโฟลเดอร์ หรือมีการเปลี่ยนการจัดโฟลเดอร์
 */
function storage_organize_drive(): array
{
    $res = ['moved' => 0, 'failed' => []];
    $sources = [
        ['ใบเสร็จ', "SELECT id, file_name AS f, 'receipt' AS c FROM ledger_entries WHERE file_name LIKE 'gdrive:%'"],
        ['เอกสาร', "SELECT d.id, d.file_name AS f, CONCAT('doc:', t.slug) AS c FROM documents d JOIN doc_types t ON t.id = d.type_id WHERE d.file_name LIKE 'gdrive:%'"],
        ['ไฟล์รายงานไปราชการ', "SELECT id, file_name AS f, 'trip-reports' AS c FROM trip_reports WHERE file_name LIKE 'gdrive:%'"],
        ['ภาพรายงานไปราชการ', "SELECT id, file_name AS f, 'trip-reports' AS c FROM trip_report_images WHERE file_name LIKE 'gdrive:%'"],
    ];
    foreach ($sources as [$label, $sql]) {
        foreach (db_all($sql) as $r) {
            try {
                $target = storage_category_folder($r['c']);
                foreach (storage_drive_ids($r['f']) as $fid) {
                    if (gdrive_move_into($fid, $target)) $res['moved']++;
                }
            } catch (Throwable $ex) {
                $res['failed'][] = $label . ' #' . $r['id'] . ': ' . $ex->getMessage();
                log_activity('gdrive_error', mb_substr('จัดโฟลเดอร์ ' . $label . ' #' . $r['id'] . ': ' . $ex->getMessage(), 0, 255));
            }
        }
    }
    // สำเนาไฟล์สำรองข้อมูล (ดัชนีอยู่ใน settings.backup_drive_index)
    $idx = json_decode((string)db_val("SELECT svalue FROM settings WHERE skey = 'backup_drive_index'"), true);
    foreach (is_array($idx) ? $idx : [] as $name => $fid) {
        try {
            if (gdrive_move_into($fid, storage_category_folder('backups'))) $res['moved']++;
        } catch (Throwable $ex) {
            $res['failed'][] = 'ไฟล์สำรอง ' . $name . ': ' . $ex->getMessage();
            log_activity('gdrive_error', mb_substr('จัดโฟลเดอร์ไฟล์สำรอง ' . $name . ': ' . $ex->getMessage(), 0, 255));
        }
    }
    if ($res['moved']) log_activity('gdrive_organize', 'ย้ายไฟล์ใน Drive เข้าโฟลเดอร์ตามประเภท ' . $res['moved'] . ' ไฟล์');
    return $res;
}

/** สร้างโฟลเดอร์ย่อยทั้งหมดใน Drive ทันที (ไม่ต้องรอไฟล์แรก) คืนค่า [เส้นทาง => 'ok' หรือข้อความผิดพลาด] */
function storage_ensure_all_folders(): array
{
    $cats = array_merge(array_map(fn($slug) => 'doc:' . $slug, array_keys(STORAGE_DOC_FOLDERS)), ['receipt', 'trip-reports', '', 'backups']);
    $out = [];
    foreach ($cats as $cat) {
        $p = storage_category_path($cat);
        if (isset($out[$p])) continue; // หลายประเภทใช้โฟลเดอร์เดียวกัน
        try {
            storage_category_folder($cat); // หา/สร้างแล้วจำ folder id ไว้
            $out[$p] = 'ok';
        } catch (Throwable $ex) {
            $out[$p] = $ex->getMessage();
        }
    }
    return $out;
}
