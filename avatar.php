<?php
/** แสดงภาพโปรไฟล์สมาชิก (เฉพาะผู้ที่เข้าสู่ระบบ) ?id= */
require_once __DIR__ . '/includes/bootstrap.php';

$name = is_logged_in() ? db_val('SELECT avatar FROM users WHERE id = ?', 'i', [(int)get('id')]) : null;
// URL มี ?v= เปลี่ยนตามไฟล์ จึงแคชได้นาน
if (!$name || !storage_serve($name, 'image/jpeg', null, ['Cache-Control: private, max-age=604800'])) {
    http_response_code(404);
    exit;
}
