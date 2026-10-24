<?php
/**
 * พิมพ์โครงสร้างฐานข้อมูลปัจจุบัน ใช้สร้างไฟล์ของสกิล ChatGPT (skills/build_skill.py เรียกใช้)
 *   php tools\dump-schema.php            โครงสร้างอย่างเดียว (ไม่มีข้อมูล)
 *   php tools\dump-schema.php --install  โครงสร้าง + ข้อมูลตั้งต้นที่ไม่ใช่ข้อมูลส่วนบุคคล (ใช้แทน database/docshub.sql ในชุดซอร์ส)
 * --install ไม่รวม users, activity_logs, PDPA consents/requests, เอกสาร, บัญชี และค่าลับใน settings
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ใช้งานผ่านบรรทัดคำสั่งเท่านั้น');
}
require_once __DIR__ . '/../includes/bootstrap.php';

$install = in_array('--install', $argv, true);
// ตารางที่เป็นข้อมูลตั้งต้นของระบบ (ไม่ใช่ข้อมูลส่วนบุคคล)
$seedTables = ['doc_types', 'categories', 'departments', 'academic_years', 'pdpa_policies'];
// settings ที่ส่งออกได้ (ค่าลับ/โทเคน/ข้อมูลติดต่อ DPO/ค่าเฉพาะเครื่อง ไม่ส่งออก)
$safeSettings = ['allow_register', 'auto_approve_member', 'book_format', 'book_prefix', 'college_name', 'db_version',
                 'google_allow_register', 'log_retention_days', 'max_upload_mb', 'site_name', 'site_slogan'];
$forced = ['storage_driver' => 'local', 'google_login_enabled' => '0'];

$q = fn($v) => $v === null ? 'NULL' : "'" . $GLOBALS['db']->real_escape_string((string)$v) . "'";

echo '-- Docs Hub ' . ($install ? 'install schema + default data' : 'database structure (no data)') . "\n";
echo '-- APP_VERSION ' . APP_VERSION . ' · db_version ' . setting('db_version') . ' (DB_SCHEMA_LATEST ' . DB_SCHEMA_LATEST . ")\n";
echo "-- สร้างด้วย tools/dump-schema.php" . ($install ? ' --install (ไม่มีผู้ใช้ ข้อมูลส่วนบุคคล หรือค่าลับ)' : '') . "\n\n";
if ($install) echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach (db_all('SHOW TABLES') as $row) {
    $table = str_replace('`', '', array_values($row)[0]);
    $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', db_row("SHOW CREATE TABLE `$table`")['Create Table']);
    if ($install) $create = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $create);
    echo $create, ";\n\n";
    if (!$install) continue;
    if (in_array($table, $seedTables, true)) {
        foreach (db_all("SELECT * FROM `$table`") as $r) {
            echo "INSERT IGNORE INTO `$table` (`" . implode('`, `', array_keys($r)) . '`) VALUES (' . implode(', ', array_map($q, $r)) . ");\n";
        }
        echo "\n";
    } elseif ($table === 'settings') {
        $vals = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
            if (in_array($r['skey'], $safeSettings, true)) $vals[$r['skey']] = $r['svalue'];
        }
        foreach ($forced + $vals as $k => $v) echo "INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES (" . $q($k) . ', ' . $q($v) . ");\n";
        echo "\n";
    }
}
if ($install) echo "SET FOREIGN_KEY_CHECKS = 1;\n";
