<?php
/**
 * ระบบสำรองและกู้คืนข้อมูล
 * ไฟล์สำรองเป็น .zip หนึ่งไฟล์ ประกอบด้วย
 *   manifest.json   ข้อมูลการสำรอง (เวอร์ชัน วันที่ จำนวนข้อมูล)
 *   database.sql    ข้อมูลทุกตารางในฐานข้อมูล (หนึ่งคำสั่งต่อหนึ่งบรรทัด)
 *   uploads/docs/   ไฟล์เอกสารทั้งหมด (เฉพาะการสำรองแบบเต็ม)
 */

define('BACKUP_DIR', APP_ROOT . '/backups');
define('BACKUP_NAME_RE', '/^docshub-backup-[0-9]{8}-[0-9]{6}-(db|full)(-[a-z]+)?\.zip$/');

function backup_path(string $name): string
{
    if (!preg_match(BACKUP_NAME_RE, $name)) {
        throw new RuntimeException('ชื่อไฟล์สำรองไม่ถูกต้อง');
    }
    return BACKUP_DIR . '/' . $name;
}

function backup_ensure_dir(): void
{
    if (!is_dir(BACKUP_DIR) && !mkdir(BACKUP_DIR, 0775, true)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ backups ได้');
    }
    if (!file_exists(BACKUP_DIR . '/.htaccess')) {
        file_put_contents(BACKUP_DIR . '/.htaccess', "# ห้ามดาวน์โหลดไฟล์สำรองโดยตรง\nRequire all denied\n");
    }
}

/** เขียนข้อมูลทุกตารางลงไฟล์ SQL คืนค่าจำนวนแถวต่อตาราง */
function backup_dump_database(string $sqlFile): array
{
    global $db;
    $fh = fopen($sqlFile, 'w');
    fwrite($fh, "-- Docs Hub database backup\n-- version " . APP_VERSION . ' · ' . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n");
    $counts = [];
    $tables = array_column($db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetch_all(), 0);
    foreach ($tables as $table) {
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
        fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($fh, str_replace(["\r", "\n"], ' ', $create) . ";\n");
        $res = $db->query("SELECT * FROM `$table`", MYSQLI_USE_RESULT);
        $batch = [];
        $n = 0;
        while ($row = $res->fetch_row()) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string($v) . "'", $row);
            $batch[] = '(' . implode(',', $vals) . ')';
            $n++;
            if (count($batch) >= 200) {
                fwrite($fh, "INSERT INTO `$table` VALUES " . implode(',', $batch) . ";\n");
                $batch = [];
            }
        }
        $res->free();
        if ($batch) {
            fwrite($fh, "INSERT INTO `$table` VALUES " . implode(',', $batch) . ";\n");
        }
        $counts[$table] = $n;
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($fh);
    return $counts;
}

/**
 * สร้างไฟล์สำรอง คืนค่าชื่อไฟล์
 * $tag: auto, prerestore, cli ฯลฯ (ใช้แยกที่มาของไฟล์)
 */
function backup_create(bool $withFiles, string $tag = ''): string
{
    @set_time_limit(0);
    backup_ensure_dir();
    $name = 'docshub-backup-' . date('Ymd-His') . '-' . ($withFiles ? 'full' : 'db') . ($tag !== '' ? '-' . $tag : '') . '.zip';
    $zipPath = BACKUP_DIR . '/' . $name;
    $sqlTmp = tempnam(sys_get_temp_dir(), 'dhsql');

    $counts = backup_dump_database($sqlTmp);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($sqlTmp);
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ zip ได้');
    }
    $zip->addFile($sqlTmp, 'database.sql');

    $fileCount = 0;
    if ($withFiles && is_dir(UPLOAD_DIR)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(UPLOAD_DIR) + 1));
                $zip->addFile($file->getPathname(), 'uploads/docs/' . $rel);
                $zip->setCompressionName('uploads/docs/' . $rel, ZipArchive::CM_STORE); // PDF/รูปบีบอัดแล้ว ไม่ต้องบีบซ้ำ
                $fileCount++;
            }
        }
    }
    $zip->addFromString('manifest.json', json_encode([
        'app'        => 'docshub',
        'version'    => APP_VERSION,
        'db_version' => setting('db_version', '1'),
        'created_at' => date('c'),
        'created_by' => current_user()['fullname'] ?? 'system',
        'type'       => $withFiles ? 'full' : 'db',
        'tables'     => $counts,
        'files'      => $fileCount,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $ok = $zip->close();
    @unlink($sqlTmp);
    if (!$ok) {
        @unlink($zipPath);
        throw new RuntimeException('บันทึกไฟล์สำรองไม่สำเร็จ (พื้นที่ดิสก์เต็มหรือไม่มีสิทธิ์เขียน)');
    }
    backup_push_to_drive($name); // ส่งสำเนาไป Google Drive: DocsHub/uploads/backups (ถ้าเชื่อมต่อไว้)
    return $name;
}

function backup_read_manifest(string $zipPath): ?array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return null;
    $json = $zip->getFromName('manifest.json');
    $hasSql = $zip->locateName('database.sql') !== false;
    $zip->close();
    $m = $json ? json_decode($json, true) : null;
    return (is_array($m) && ($m['app'] ?? '') === 'docshub' && $hasSql) ? $m : null;
}

function backup_list(): array
{
    if (!is_dir(BACKUP_DIR)) return [];
    $rows = [];
    foreach (scandir(BACKUP_DIR) as $f) {
        if (preg_match(BACKUP_NAME_RE, $f)) {
            $path = BACKUP_DIR . '/' . $f;
            $rows[] = ['name' => $f, 'size' => filesize($path), 'time' => filemtime($path), 'manifest' => backup_read_manifest($path)];
        }
    }
    usort($rows, fn($a, $b) => $b['time'] <=> $a['time']);
    return $rows;
}

/** เก็บไฟล์สำรองล่าสุดไว้ $keep ไฟล์ และไฟล์ที่สำรองก่อนกู้คืน (prerestore) ล่าสุด 3 ไฟล์ */
function backup_prune(int $keep): int
{
    if ($keep < 1) return 0;
    $removed = 0;
    $all = backup_list();
    $isPre = fn($b) => strpos($b['name'], '-prerestore') !== false;
    $old = array_merge(
        array_slice(array_values(array_filter($all, fn($b) => !$isPre($b))), $keep),
        array_slice(array_values(array_filter($all, $isPre)), 3)
    );
    foreach ($old as $b) {
        if (@unlink(BACKUP_DIR . '/' . $b['name'])) {
            $removed++;
            backup_drive_forget($b['name'], true); // ลบสำเนาใน Drive ตามจำนวนที่เก็บไว้เช่นกัน
        }
    }
    return $removed;
}

/**
 * กู้คืนจากไฟล์สำรอง
 * ระบบจะสร้างไฟล์สำรองสถานะปัจจุบัน (-prerestore) ก่อนเสมอ เพื่อย้อนกลับได้หากกู้คืนผิดไฟล์
 */
function backup_restore(string $zipPath, bool $restoreDb, bool $restoreFiles, bool $safetyBackup = true): array
{
    global $db;
    @set_time_limit(0);
    $m = backup_read_manifest($zipPath);
    if (!$m) {
        throw new RuntimeException('ไฟล์นี้ไม่ใช่ไฟล์สำรองของ Docs Hub หรือไฟล์เสียหาย');
    }
    if (version_compare($m['version'] ?? '0', APP_VERSION, '>')) {
        throw new RuntimeException('ไฟล์สำรองมาจากเวอร์ชันใหม่กว่า (' . $m['version'] . ') กรุณาอัปเดตระบบก่อนกู้คืน');
    }

    $safety = $safetyBackup ? backup_create($restoreFiles, 'prerestore') : '(ไม่ได้สำรอง)';
    $zip = new ZipArchive();
    $zip->open($zipPath);
    $result = ['safety' => $safety, 'statements' => 0, 'files' => 0];

    if ($restoreDb) {
        $stream = $zip->getStream('database.sql');
        if (!$stream) throw new RuntimeException('ไม่พบ database.sql ในไฟล์สำรอง');
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            while (($line = fgets($stream)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '' || str_starts_with($line, '--')) continue;
                $db->query($line);
                $result['statements']++;
            }
        } catch (mysqli_sql_exception $ex) {
            throw new RuntimeException('กู้คืนฐานข้อมูลไม่สำเร็จ: ' . $ex->getMessage() . ' — สามารถกู้คืนกลับด้วยไฟล์ ' . $safety);
        } finally {
            fclose($stream);
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    if ($restoreFiles) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!str_starts_with($entry, 'uploads/docs/') || substr($entry, -1) === '/') continue;
            $rel = substr($entry, strlen('uploads/docs/'));
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            // ป้องกัน path traversal และไฟล์ที่ไม่ใช่เอกสาร
            if ($rel === '' || strpos($rel, '..') !== false || !preg_match('#^[A-Za-z0-9_./-]+$#', $rel) || !in_array($ext, ALLOWED_EXT, true)) continue;
            $dest = UPLOAD_DIR . '/' . $rel;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
            $in = $zip->getStream($entry);
            $out = fopen($dest, 'w');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            $result['files']++;
        }
    }
    $zip->close();
    return $result;
}

/** สำรองอัตโนมัติเมื่อไฟล์ล่าสุดเก่ากว่า N วัน (เรียกจากแดชบอร์ดหลังบ้าน) */
function backup_auto_if_due(): ?string
{
    $days = (int)setting('auto_backup_days', '0');
    if ($days < 1) return null;
    $latest = backup_list()[0]['time'] ?? 0;
    if (time() - $latest < $days * 86400) return null;
    $name = backup_create(setting('auto_backup_full', '0') === '1', 'auto');
    backup_prune((int)setting('backup_keep', '10'));
    log_activity('backup_auto', $name);
    return $name;
}

/* ======================= สำเนาไฟล์สำรองใน Google Drive (DocsHub/uploads/backups) ======================= */

/** สามารถส่งไฟล์สำรองขึ้น Drive ได้หรือไม่ (เครื่องมือกู้คืนฉุกเฉินไม่ได้โหลดโมดูล Drive) */
function backup_drive_available(): bool
{
    return function_exists('gdrive_connected') && gdrive_connected() && setting('gdrive_folder_id') !== '';
}

/** รายการไฟล์สำรองที่อยู่ใน Drive: [ชื่อไฟล์ => file id] */
function backup_drive_index(): array
{
    // อ่านจากฐานข้อมูลตรง (ไม่ใช้ค่าแคชของ setting) เพราะสำรองแล้วลบไฟล์เก่าในคำขอเดียวกัน
    $idx = json_decode((string)db_val("SELECT svalue FROM settings WHERE skey = 'backup_drive_index'"), true);
    return is_array($idx) ? $idx : [];
}

function backup_drive_index_save(array $idx): void
{
    db_exec("INSERT INTO settings (skey, svalue) VALUES ('backup_drive_index', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", 's', [json_encode($idx)]);
}

/**
 * ส่งไฟล์สำรองขึ้น Drive (ไม่ทำให้การสำรองล้มเหลว — ไฟล์ในเครื่องยังอยู่เสมอ)
 * ผลลัพธ์ล่าสุดเก็บไว้ที่ $GLOBALS['BACKUP_DRIVE_RESULT'] เพื่อแจ้งผู้ใช้
 */
function backup_push_to_drive(string $name): void
{
    $GLOBALS['BACKUP_DRIVE_RESULT'] = null;
    if (!backup_drive_available()) return;
    try {
        $id = gdrive_upload(BACKUP_DIR . '/' . $name, $name, 'application/zip', storage_category_folder('backups'));
        $idx = backup_drive_index();
        $idx[$name] = $id;
        backup_drive_index_save($idx);
        $GLOBALS['BACKUP_DRIVE_RESULT'] = ['ok' => true, 'msg' => 'ส่งสำเนาไปที่ Google Drive: ' . storage_display_path('backups') . ' แล้ว'];
    } catch (Throwable $ex) {
        log_activity('gdrive_error', mb_substr('ส่งไฟล์สำรอง ' . $name . ': ' . $ex->getMessage(), 0, 255));
        $GLOBALS['BACKUP_DRIVE_RESULT'] = ['ok' => false, 'msg' => 'ส่งสำเนาไป Google Drive ไม่สำเร็จ (ไฟล์สำรองยังอยู่ในเครื่อง): ' . $ex->getMessage()];
    }
}

/** ลบรายการไฟล์สำรองออกจากดัชนี และ (ถ้าระบุ) ย้ายสำเนาใน Drive ไปถังขยะ */
function backup_drive_forget(string $name, bool $trash): void
{
    $idx = backup_drive_index();
    if (!isset($idx[$name])) return;
    if ($trash && backup_drive_available()) {
        try {
            gdrive_trash($idx[$name]);
        } catch (Throwable $ex) {
            log_activity('gdrive_error', mb_substr('ลบสำเนาไฟล์สำรอง ' . $name . ': ' . $ex->getMessage(), 0, 255));
        }
    }
    unset($idx[$name]);
    backup_drive_index_save($idx);
}
