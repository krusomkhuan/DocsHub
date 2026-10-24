<?php
/**
 * กู้คืนข้อมูลผ่านบรรทัดคำสั่ง ใช้เมื่อเข้าหน้าเว็บไม่ได้ (เช่น ฐานข้อมูลเสียหาย/ถูกลบ)
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\restore.php                 แสดงรายการไฟล์สำรอง
 *   C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\restore.php <ไฟล์.zip>       กู้คืนฐานข้อมูล + ไฟล์เอกสาร
 *   ตัวเลือก: --db-only  --files-only  --yes (ไม่ต้องถามยืนยัน)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ใช้งานผ่านบรรทัดคำสั่งเท่านั้น');
}
require_once __DIR__ . '/../includes/config.php';

// เชื่อมต่อเอง (ไม่ผ่าน bootstrap) เพื่อให้ทำงานได้แม้ฐานข้อมูลถูกลบหรือเสียหาย
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    $db->set_charset('utf8mb4');
    $db->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->select_db(DB_NAME);
} catch (mysqli_sql_exception $ex) {
    fwrite(STDERR, "เชื่อมต่อ MySQL ไม่ได้: " . $ex->getMessage() . "\nตรวจสอบว่าเปิด MySQL แล้ว และค่าใน includes/config.php ถูกต้อง\n");
    exit(1);
}
$CURRENT_USER = null;
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup.php';

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => !str_starts_with($a, '--')));
$opts = array_filter(array_slice($argv, 1), fn($a) => str_starts_with($a, '--'));

if (!$args) {
    echo "ไฟล์สำรองใน " . BACKUP_DIR . ":\n";
    foreach (backup_list() as $b) {
        printf("  %s  %10s  %s\n", date('Y-m-d H:i', $b['time']), format_size((int)$b['size']), $b['name']);
    }
    echo "\nวิธีใช้: php tools/restore.php <ไฟล์.zip> [--db-only|--files-only] [--yes]\n";
    exit(0);
}

$path = $args[0];
if (!is_file($path) && is_file(BACKUP_DIR . '/' . basename($path))) {
    $path = BACKUP_DIR . '/' . basename($path);
}
if (!is_file($path)) {
    fwrite(STDERR, "ไม่พบไฟล์ $path\n");
    exit(1);
}
$m = backup_read_manifest($path);
if (!$m) {
    fwrite(STDERR, "ไฟล์นี้ไม่ใช่ไฟล์สำรองของ Docs Hub หรือไฟล์เสียหาย\n");
    exit(1);
}
$restoreDb = !in_array('--files-only', $opts, true);
$restoreFiles = !in_array('--db-only', $opts, true) && $m['type'] === 'full';

echo "ไฟล์สำรอง : " . basename($path) . "\n";
echo "สร้างเมื่อ  : {$m['created_at']} โดย {$m['created_by']} (v{$m['version']})\n";
echo "ข้อมูล     : เอกสาร " . ($m['tables']['documents'] ?? 0) . " · สมาชิก " . ($m['tables']['users'] ?? 0) . " · ไฟล์ {$m['files']}\n";
echo "จะกู้คืน   : " . ($restoreDb ? 'ฐานข้อมูล ' : '') . ($restoreFiles ? 'ไฟล์เอกสาร' : '') . "\n";

if (!in_array('--yes', $opts, true)) {
    echo "ข้อมูลปัจจุบันจะถูกแทนที่ พิมพ์ yes เพื่อยืนยัน: ";
    if (trim((string)fgets(STDIN)) !== 'yes') {
        echo "ยกเลิก\n";
        exit(1);
    }
}

// สำรองสถานะปัจจุบันก่อน หากฐานข้อมูลยังใช้งานได้
$canSnapshot = (bool)$db->query("SHOW TABLES LIKE 'settings'")->num_rows;
try {
    $r = backup_restore($path, $restoreDb, $restoreFiles, $canSnapshot);
    echo "กู้คืนสำเร็จ: ฐานข้อมูล {$r['statements']} คำสั่ง · ไฟล์ {$r['files']} ไฟล์\n";
    echo "สำรองข้อมูลก่อนกู้คืนไว้ที่: {$r['safety']}\n";
    if (!file_exists(APP_ROOT . '/includes/installed.lock')) {
        file_put_contents(APP_ROOT . '/includes/installed.lock', date('c'));
    }
    $db->query("INSERT INTO activity_logs (action, detail) VALUES ('backup_restore', '" . $db->real_escape_string('cli: ' . basename($path)) . "')");
    exit(0);
} catch (Throwable $ex) {
    fwrite(STDERR, 'ERROR ' . $ex->getMessage() . "\n");
    exit(1);
}
