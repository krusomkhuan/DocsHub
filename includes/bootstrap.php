<?php
/**
 * โหลดทุกหน้า: config, เชื่อมต่อฐานข้อมูล (mysqli), session และฟังก์ชันกลาง
 */
require_once __DIR__ . '/config.php';

if (!file_exists(__DIR__ . '/installed.lock')) {
    header('Location: ' . BASE_URL . '/install.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    http_response_code(500);
    exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบ includes/config.php');
}

if (PHP_SAPI !== 'cli') {
    session_name('DOCSHUBSESSID');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => BASE_URL . '/']);
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/trip_reports.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/pdpa.php';
require_once __DIR__ . '/photo_albums.php';
require_once __DIR__ . '/google_auth.php';
require_once __DIR__ . '/gdrive.php';

// โหลดข้อมูลผู้ใช้ล่าสุดทุกครั้ง เพื่อให้การระงับสิทธิ์มีผลทันที
$CURRENT_USER = null;
if (!empty($_SESSION['uid'])) {
    $CURRENT_USER = db_row('SELECT u.*, d.name AS dept_name, d.code AS dept_code
                            FROM users u LEFT JOIN departments d ON d.id = u.department_id
                            WHERE u.id = ?', 'i', [$_SESSION['uid']]);
    if (!$CURRENT_USER || $CURRENT_USER['status'] !== 'active') {
        $CURRENT_USER = null;
        unset($_SESSION['uid']);
    }
}

// PDPA: ต้องยอมรับนโยบายคุ้มครองข้อมูลส่วนบุคคลฉบับล่าสุดก่อนใช้งาน
pdpa_enforce();
