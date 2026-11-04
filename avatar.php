<?php
/** แสดงภาพโปรไฟล์สมาชิก (เฉพาะผู้ที่เข้าสู่ระบบ) ?id= */
require_once __DIR__ . '/includes/bootstrap.php';

$name = is_logged_in() ? db_val('SELECT avatar FROM users WHERE id = ?', 'i', [(int)get('id')]) : null;
// URL มี ?v= เปลี่ยนตามไฟล์ จึงแคชได้นาน
// ปกติเป็น .jpg แต่ถ้าเซิร์ฟเวอร์ไม่มี GD จะเก็บไฟล์ต้นฉบับ (.png/.webp)
$mime = ['png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo((string)$name, PATHINFO_EXTENSION))] ?? 'image/jpeg';
if (!$name || !storage_serve($name, $mime,null, ['Cache-Control: private, max-age=604800'])) {
    http_response_code(404);
    exit;
}
