<?php
/**
 * ตรวจสอบซอฟต์แวร์และสภาพแวดล้อมของระบบ
 * - สภาพแวดล้อม PHP / ส่วนขยาย / ค่าใน php.ini
 * - ฐานข้อมูล: การเชื่อมต่อ เวอร์ชันโครงสร้าง ตารางครบ ชุดอักขระ
 * - โฟลเดอร์และพื้นที่ดิสก์
 * - ความปลอดภัย: ไฟล์ป้องกัน .htaccess, การเข้าถึงตรงจากเว็บ, ไฟล์สคริปต์แปลกปลอมในโฟลเดอร์อัปโหลด
 * - ความครบถ้วนของไฟล์ที่อัปโหลด (มีในฐานข้อมูลแต่ไม่มีไฟล์จริง)
 * - ความถูกต้องของไฟล์โปรแกรม: เทียบ SHA-256 กับค่าอ้างอิง (database/integrity.json)
 * ผลแต่ละข้อ: ['status' => ok|warn|fail|info, 'label' => ..., 'detail' => ...]
 */

define('INTEGRITY_FILE', APP_ROOT . '/database/integrity.json');

function sc_item(string $status, string $label, string $detail = ''): array
{
    return ['status' => $status, 'label' => $label, 'detail' => $detail];
}

/** อ่านส่วนต้นของไฟล์ (ใช้ fread เพราะ file_get_contents แบบกำหนดความยาวมีปัญหาบน Windows) */
function sc_head(string $path, int $len): string
{
    $fh = @fopen($path, 'rb');
    if (!$fh) return '';
    $data = (string)fread($fh, $len);
    fclose($fh);
    return $data;
}

function sc_bytes(string $v): int
{
    $v = trim($v);
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

/** ไฟล์โปรแกรมที่ใช้คำนวณค่าความถูกต้อง (ไม่รวมไฟล์ผู้ใช้ ไฟล์สำรอง และไฟล์ที่เปลี่ยนตามการติดตั้ง) */
function sc_program_files(): array
{
    $files = [];
    $skipDirs = ['uploads', 'backups', '.git', '.claude', 'skills']; // skills = สกิล ChatGPT สำหรับนักพัฒนา (ไม่อัปโหลดขึ้นเซิร์ฟเวอร์)
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS),
        fn($f) => !($f->isDir() && in_array($f->getFilename(), $skipDirs, true))
    ));
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(APP_ROOT) + 1));
        if (preg_match('/\.(php|js|css|sql|htaccess|bat)$/i', $rel) || $f->getFilename() === '.htaccess') {
            if (in_array($rel, ['includes/installed.lock', 'database/integrity.json'], true)) continue;
            $files[$rel] = hash_file('sha256', $f->getPathname());
        }
    }
    ksort($files);
    return $files;
}

function sc_save_integrity(): int
{
    $files = sc_program_files();
    file_put_contents(INTEGRITY_FILE, json_encode([
        'app_version' => APP_VERSION,
        'created_at'  => date('c'),
        'created_by'  => current_user()['fullname'] ?? 'system',
        'files'       => $files,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return count($files);
}

/** เปรียบเทียบไฟล์โปรแกรมกับค่าอ้างอิง คืนค่า [manifest|null, changed[], missing[], added[]] */
function sc_compare_integrity(): array
{
    if (!is_file(INTEGRITY_FILE)) return [null, [], [], []];
    $m = json_decode((string)file_get_contents(INTEGRITY_FILE), true);
    if (!is_array($m) || !isset($m['files'])) return [null, [], [], []];
    $now = sc_program_files();
    $changed = array_keys(array_filter($m['files'], fn($h, $f) => isset($now[$f]) && $now[$f] !== $h, ARRAY_FILTER_USE_BOTH));
    $missing = array_keys(array_diff_key($m['files'], $now));
    $added = array_keys(array_diff_key($now, $m['files']));
    return [$m, $changed, $missing, $added];
}

/** ทดสอบว่าเปิด URL ตรงจากเว็บไม่ได้ (คาดหวัง 403/404) คืนค่ารหัส HTTP หรือ null ถ้าทดสอบไม่ได้ */
function sc_http_status(string $path): ?int
{
    $url = detected_base_url() . '/' . $path;
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0],
                                  'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $h = @get_headers($url, false, $ctx);
    if (!$h || !preg_match('#\s(\d{3})\s#', $h[0], $m)) return null;
    return (int)$m[1];
}

function run_system_checks(bool $httpProbe = true): array
{
    global $db;
    $r = [];

    /* ---------- ซอฟต์แวร์ / PHP ---------- */
    $g = [];
    $g[] = sc_item('info', 'ระบบ ' . setting('site_name'), 'เวอร์ชัน ' . APP_VERSION . ' · พัฒนาโดย ' . APP_DEVELOPER);
    $g[] = sc_item(version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail', 'PHP ' . PHP_VERSION, 'ต้องการ 8.0 ขึ้นไป');
    $g[] = sc_item('info', 'เว็บเซิร์ฟเวอร์', $_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI);
    foreach (['mysqli' => 'เชื่อมต่อฐานข้อมูล', 'mbstring' => 'ภาษาไทย', 'gd' => 'ย่อ/ครอปภาพ', 'zip' => 'สำรองข้อมูล', 'json' => 'ข้อมูล JSON', 'exif' => 'หมุนภาพจากมือถือ', 'fileinfo' => 'ตรวจชนิดไฟล์'] as $ext => $use) {
        $req = !in_array($ext, ['exif', 'fileinfo'], true);
        $g[] = sc_item(extension_loaded($ext) ? 'ok' : ($req ? 'fail' : 'warn'), 'ส่วนขยาย ' . $ext, $use . (extension_loaded($ext) ? '' : ' — ยังไม่เปิดใช้ใน php.ini'));
    }
    $upMax = min(sc_bytes(ini_get('upload_max_filesize')), sc_bytes(ini_get('post_max_size')));
    $want = (int)setting('max_upload_mb', '20') * 1024 * 1024;
    $g[] = sc_item($upMax >= $want ? 'ok' : 'warn', 'ขนาดอัปโหลดสูงสุด', 'php.ini: upload_max_filesize ' . ini_get('upload_max_filesize') . ' / post_max_size ' . ini_get('post_max_size') . ' · ระบบตั้งไว้ ' . setting('max_upload_mb', '20') . ' MB');
    $g[] = sc_item(sc_bytes(ini_get('memory_limit')) >= 128 * 1024 * 1024 || ini_get('memory_limit') === '-1' ? 'ok' : 'warn', 'memory_limit ' . ini_get('memory_limit'), 'แนะนำ 128M ขึ้นไปสำหรับย่อภาพขนาดใหญ่');
    $g[] = sc_item(ini_get('display_errors') && !in_array(strtolower((string)ini_get('display_errors')), ['0', 'off', 'stderr'], true) ? 'warn' : 'ok', 'display_errors', ini_get('display_errors') ? 'เปิดอยู่ — ควรปิดบนเครื่องใช้งานจริง เพื่อไม่ให้แสดงรายละเอียดข้อผิดพลาดแก่ผู้ใช้' : 'ปิดอยู่');
    $g[] = sc_item('info', 'เขตเวลา', date_default_timezone_get() . ' · ' . thai_date(date('Y-m-d H:i:s'), false, true));
    $r['ซอฟต์แวร์และ PHP'] = $g;

    /* ---------- ฐานข้อมูล ---------- */
    $g = [];
    $g[] = sc_item('ok', 'เชื่อมต่อ MySQL/MariaDB', $db->server_info . ' · ฐานข้อมูล ' . DB_NAME);
    $g[] = sc_item($db->character_set_name() === 'utf8mb4' ? 'ok' : 'warn', 'ชุดอักขระการเชื่อมต่อ', $db->character_set_name());
    $ver = (int)setting('db_version', '1');
    $g[] = sc_item($ver >= DB_SCHEMA_LATEST ? 'ok' : 'fail', 'เวอร์ชันโครงสร้างฐานข้อมูล', $ver . ' / ล่าสุด ' . DB_SCHEMA_LATEST);
    $expected = ['departments', 'users', 'doc_types', 'categories', 'documents', 'book_numbers', 'settings', 'activity_logs', 'trip_reports', 'trip_report_images',
                 'academic_years', 'ledger_categories', 'ledger_entries', 'ledger_managers', 'pdpa_policies', 'pdpa_consents', 'pdpa_requests', 'book_number_starts', 'photo_albums'];
    $have = array_column($db->query('SHOW TABLES')->fetch_all(), 0);
    $miss = array_diff($expected, $have);
    $g[] = sc_item($miss ? 'fail' : 'ok', 'ตารางในฐานข้อมูล', $miss ? 'ขาด: ' . implode(', ', $miss) : 'ครบ ' . count($expected) . ' ตาราง');
    $nonInno = db_all("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND engine <> 'InnoDB'", 's', [DB_NAME]);
    $g[] = sc_item($nonInno ? 'warn' : 'ok', 'Storage engine', $nonInno ? 'ไม่ใช่ InnoDB: ' . implode(', ', array_column($nonInno, 't')) : 'InnoDB ทุกตาราง (รองรับ transaction)');
    $size = (int)db_val('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = ?', 's', [DB_NAME]);
    $g[] = sc_item('info', 'ขนาดฐานข้อมูล', format_size($size));
    $admins = (int)db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
    $g[] = sc_item($admins >= 1 ? ($admins === 1 ? 'warn' : 'ok') : 'fail', 'ผู้ดูแลระบบที่ใช้งานได้', $admins . ' คน' . ($admins === 1 ? ' — แนะนำให้มีอย่างน้อย 2 คน กันกรณีลืมรหัสผ่าน' : ''));
    $r['ฐานข้อมูล'] = $g;

    /* ---------- โฟลเดอร์และพื้นที่ ---------- */
    $g = [];
    foreach ([UPLOAD_DIR => 'โฟลเดอร์จัดเก็บไฟล์ (uploads/docs)', APP_ROOT . '/backups' => 'โฟลเดอร์ไฟล์สำรอง (backups)'] as $dir => $lbl) {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $g[] = sc_item(is_dir($dir) && is_writable($dir) ? 'ok' : 'fail', $lbl, is_writable($dir) ? 'เขียนได้' : 'เขียนไม่ได้ — ตรวจสอบสิทธิ์โฟลเดอร์');
    }
    $free = @disk_free_space(APP_ROOT);
    $g[] = sc_item($free === false ? 'info' : ($free < 1024 ** 3 ? 'warn' : 'ok'), 'พื้นที่ว่างบนดิสก์', $free === false ? 'ตรวจไม่ได้' : format_size((int)$free) . ($free < 1024 ** 3 ? ' — เหลือน้อยกว่า 1 GB' : ''));
    $used = 0;
    $count = 0;
    if (is_dir(UPLOAD_DIR)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) { $used += $f->getSize(); $count++; }
        }
    }
    $g[] = sc_item('info', 'ไฟล์ที่จัดเก็บ', number_format($count) . ' ไฟล์ · ' . format_size($used));
    $lastBackup = 0;
    foreach (glob(APP_ROOT . '/backups/docshub-backup-*.zip') ?: [] as $b) $lastBackup = max($lastBackup, filemtime($b));
    $days = $lastBackup ? (int)floor((time() - $lastBackup) / 86400) : null;
    $g[] = sc_item($days === null ? 'fail' : ($days > 7 ? 'warn' : 'ok'), 'การสำรองข้อมูลล่าสุด', $days === null ? 'ยังไม่เคยสำรองข้อมูล' : thai_date(date('Y-m-d H:i:s', $lastBackup), true, true) . ' (' . $days . ' วันที่แล้ว)');
    $r['โฟลเดอร์และการสำรองข้อมูล'] = $g;

    /* ---------- ความปลอดภัย ---------- */
    $g = [];
    foreach (['uploads', 'includes', 'database', 'tools', 'backups'] as $d) {
        $ok = is_file(APP_ROOT . "/$d/.htaccess") && stripos((string)file_get_contents(APP_ROOT . "/$d/.htaccess"), 'Require all denied') !== false;
        $g[] = sc_item($ok ? 'ok' : 'fail', "ไฟล์ป้องกัน $d/.htaccess", $ok ? 'ปิดการเข้าถึงจากเว็บ' : 'ไม่พบหรือไม่ได้ปิดการเข้าถึง');
    }
    if ($httpProbe && ini_get('allow_url_fopen')) {
        foreach (['includes/config.php' => 'ไฟล์ตั้งค่า', 'database/install.sql' => 'ไฟล์ติดตั้งฐานข้อมูล', 'uploads/.htaccess' => 'โฟลเดอร์อัปโหลด', 'tools/backup.php' => 'เครื่องมือบรรทัดคำสั่ง'] as $p => $lbl) {
            $code = sc_http_status($p);
            $g[] = sc_item($code === null ? 'info' : (in_array($code, [403, 404], true) ? 'ok' : 'fail'), 'ทดสอบเปิดตรงจากเว็บ: ' . $lbl, $code === null ? 'ทดสอบไม่ได้จากเซิร์ฟเวอร์นี้' : 'HTTP ' . $code . (in_array($code, [403, 404], true) ? ' (ถูกปิดกั้น)' : ' — เปิดได้! ตรวจสอบ AllowOverride ของ Apache'));
        }
    }
    // สคริปต์แปลกปลอมในโฟลเดอร์อัปโหลด (เช่น web shell)
    $bad = [];
    if (is_dir(UPLOAD_DIR)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && (preg_match('/\.(php\d?|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh|exe|bat|cmd|htaccess)$/i', $f->getFilename()) && $f->getFilename() !== '.htaccess'
                || (in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'pdf'], true) && $f->getSize() < 5 * 1024 * 1024 && preg_match('/<\?php|eval\s*\(|base64_decode\s*\(/i', sc_head($f->getPathname(), 1024 * 1024))))) {
                $bad[] = str_replace('\\', '/', substr($f->getPathname(), strlen(UPLOAD_DIR) + 1));
            }
        }
    }
    $g[] = sc_item($bad ? 'fail' : 'ok', 'ไฟล์สคริปต์แปลกปลอมในโฟลเดอร์อัปโหลด', $bad ? 'พบ ' . count($bad) . ' ไฟล์: ' . implode(', ', array_slice($bad, 0, 10)) : 'ไม่พบ');
    $g[] = sc_item(is_file(APP_ROOT . '/includes/installed.lock') ? 'ok' : 'fail', 'ล็อกตัวติดตั้ง (installed.lock)', is_file(APP_ROOT . '/includes/installed.lock') ? 'หน้า install.php ถูกปิด' : 'ไม่พบ — ผู้อื่นอาจติดตั้งทับได้');
    $g[] = sc_item(DB_PASS === '' ? 'warn' : 'ok', 'รหัสผ่านฐานข้อมูล', DB_PASS === '' ? 'บัญชี ' . DB_USER . ' ไม่มีรหัสผ่าน — ควรตั้งรหัสผ่านเมื่อเปิดใช้งานจริงบนเครือข่าย' : 'ตั้งค่าไว้แล้ว');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(setting('site_url'), 'https://');
    $g[] = sc_item($https ? 'ok' : 'warn', 'HTTPS', $https ? 'ใช้การเชื่อมต่อแบบเข้ารหัส' : 'ยังใช้ http — แนะนำ https เมื่อเปิดให้ใช้จากภายนอก');
    $r['ความปลอดภัย'] = $g;

    /* ---------- ความครบถ้วนของข้อมูลไฟล์ ---------- */
    $g = [];
    $checks = [
        'ไฟล์เอกสาร'             => db_all('SELECT id, file_name AS f FROM documents'),
        'ภาพรายงานไปราชการ'      => db_all('SELECT id, file_name AS f FROM trip_report_images'),
        'ไฟล์แนบรายงานไปราชการ'  => db_all('SELECT id, file_name AS f FROM trip_reports WHERE file_name IS NOT NULL'),
        'ไฟล์หลักฐานบัญชีแผนก'   => db_all('SELECT id, file_name AS f FROM ledger_entries WHERE file_name IS NOT NULL'),
        'ภาพโปรไฟล์ (uploads/docs/avatars)' => db_all('SELECT id, avatar AS f FROM users WHERE avatar IS NOT NULL'),
    ];
    $driveTotal = 0;
    foreach ($checks as $lbl => $rows) {
        // ไฟล์ใน Google Drive ตรวจรวมในหัวข้อ Google Drive (ไม่เรียก API ทีละไฟล์)
        $drive = array_filter($rows, fn($x) => storage_is_drive($x['f']));
        $driveTotal += count($drive);
        $lost = array_filter($rows, fn($x) => !storage_is_drive($x['f']) && !is_file(UPLOAD_DIR . '/' . $x['f']));
        $g[] = sc_item($lost ? 'fail' : 'ok', $lbl, count($rows) . ' รายการ' . ($drive ? ' (ใน Google Drive ' . count($drive) . ')' : '')
            . ($lost ? ' · ไม่พบไฟล์ ' . count($lost) . ' รายการ (id: ' . implode(', ', array_slice(array_column($lost, 'id'), 0, 15)) . ')' : ' · ไฟล์ครบ'));
    }
    $r['ความครบถ้วนของไฟล์ข้อมูล'] = $g;

    /* ---------- Google Drive ---------- */
    $g = [];
    $mode = setting('storage_driver', 'local');
    $g[] = sc_item($mode === 'gdrive' && !storage_drive_enabled() ? 'fail' : 'info', 'ที่เก็บไฟล์ใหม่', storage_drive_enabled() ? 'Google Drive เท่านั้น (' . setting('gdrive_account_email') . ')' : ($mode === 'gdrive' ? 'ตั้งเป็น Google Drive แต่ยังเชื่อมต่อไม่ครบ — ผู้ใช้อัปโหลดไฟล์ไม่ได้' : 'ในเครื่อง (uploads/docs)'));
    if ($mode === 'gdrive' || $driveTotal || gdrive_connected()) {
        if (!gdrive_connected()) {
            $g[] = sc_item($driveTotal ? 'fail' : 'warn', 'การเชื่อมต่อ Google Drive', 'ยังไม่ได้เชื่อมต่อ' . ($driveTotal ? ' — มีไฟล์ ' . $driveTotal . ' ไฟล์อยู่ใน Drive ซึ่งดาวน์โหลดไม่ได้' : ''));
        } elseif ($httpProbe) {
            try {
                $info = gdrive_folder_info(setting('gdrive_folder_id'));
                $g[] = sc_item('ok', 'โฟลเดอร์ Google Drive', '"' . $info['name'] . '" · เขียนไฟล์ได้ · มีไฟล์ของระบบ ' . number_format($driveTotal) . ' ไฟล์');
            } catch (Throwable $ex) {
                $g[] = sc_item('fail', 'โฟลเดอร์ Google Drive', $ex->getMessage());
            }
        } else {
            $g[] = sc_item('info', 'โฟลเดอร์ Google Drive', 'เชื่อมต่อแล้ว (' . setting('gdrive_account_email') . ') · ไฟล์ใน Drive ' . number_format($driveTotal) . ' ไฟล์ · ตรวจการเข้าถึงได้จากหน้าเว็บ');
        }
        $errs = (int)db_val("SELECT COUNT(*) FROM activity_logs WHERE action = 'gdrive_error' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $g[] = sc_item($errs ? 'warn' : 'ok', 'ข้อผิดพลาด Google Drive ใน 7 วัน', $errs ? $errs . ' ครั้ง (ดูรายละเอียดที่บันทึกการใช้งาน ค้นหา gdrive_error) — ผู้ใช้ได้รับแจ้งให้อัปโหลดใหม่ (ไม่มีไฟล์เก็บในเครื่อง)' : 'ไม่มี');
    }
    $r['Google Drive'] = $g;

    /* ---------- ความถูกต้องของไฟล์โปรแกรม ---------- */
    $g = [];
    [$m, $changed, $missing, $added] = sc_compare_integrity();
    if (!$m) {
        $g[] = sc_item('warn', 'ค่าอ้างอิงไฟล์โปรแกรม', 'ยังไม่ได้สร้าง — กด "บันทึกค่าอ้างอิง" เมื่อระบบอยู่ในสภาพที่ถูกต้อง');
    } else {
        $g[] = sc_item('info', 'ค่าอ้างอิงไฟล์โปรแกรม', count($m['files']) . ' ไฟล์ · v' . $m['app_version'] . ' · บันทึกเมื่อ ' . thai_date($m['created_at'], true, true) . ' โดย ' . $m['created_by']);
        $g[] = sc_item($changed ? 'warn' : 'ok', 'ไฟล์ที่ถูกแก้ไข', $changed ? implode(', ', $changed) : 'ไม่มี');
        $g[] = sc_item($missing ? 'fail' : 'ok', 'ไฟล์ที่หายไป', $missing ? implode(', ', $missing) : 'ไม่มี');
        $g[] = sc_item($added ? 'warn' : 'ok', 'ไฟล์ที่เพิ่มเข้ามาใหม่', $added ? implode(', ', $added) : 'ไม่มี');
        if ($m['app_version'] !== APP_VERSION) {
            $g[] = sc_item('warn', 'เวอร์ชันของค่าอ้างอิง', 'ค่าอ้างอิงเป็นของ v' . $m['app_version'] . ' แต่ระบบเป็น v' . APP_VERSION . ' — หลังอัปเดตระบบให้บันทึกค่าอ้างอิงใหม่');
        }
    }
    $r['ความถูกต้องของไฟล์โปรแกรม'] = $g;

    return $r;
}
