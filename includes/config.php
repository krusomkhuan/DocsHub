<?php
/**
 * Docs Hub - การตั้งค่าหลักของระบบ
 * แก้ไขค่าการเชื่อมต่อฐานข้อมูลให้ตรงกับเซิร์ฟเวอร์ก่อนติดตั้ง
 */

// ข้อมูลระบบ
define('APP_VERSION', '1.23.3');
define('APP_DEVELOPER', 'ครูสมควร สากำ');
define('APP_DEVELOPER_DEPT', 'แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'docshub');
define('DB_PORT', 3306);

// โฟลเดอร์เก็บไฟล์ (ป้องกันการเข้าถึงตรงด้วย .htaccess ดาวน์โหลดผ่าน download.php เท่านั้น)
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads/docs');

// นามสกุลไฟล์ที่อนุญาตให้อัปโหลด
define('ALLOWED_EXT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar']);

date_default_timezone_set('Asia/Bangkok');

// คำนวณ BASE_URL อัตโนมัติ เช่น /docshub
if (!defined('BASE_URL')) {
    $root = str_replace('\\', '/', realpath(APP_ROOT));
    $doc  = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $base = ($doc !== '' && stripos($root, $doc) === 0) ? substr($root, strlen($doc)) : '/docshub';
    define('BASE_URL', rtrim($base, '/'));
}
