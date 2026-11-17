<?php
/**
 * ไฟล์ของระบบ PLC (ตรวจสิทธิ์ก่อนส่ง)
 * ?sig=<user_id>  ลายมือชื่อ: เจ้าของ เจ้าหน้าที่ ผู้อนุมัติ หรือผู้ที่อยู่กลุ่ม PLC เดียวกัน
 * ?photo=<id>[&thumb=1]  ภาพประกอบ: ผู้ที่ดูกลุ่มนั้นได้
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in() || !plc_can_access()) {
    http_response_code(404);
    exit;
}
$me = (int)current_user()['id'];

if (get('sig') !== '') {
    $uid = (int)get('sig');
    $allowed = $uid === $me || is_staff() || plc_is_approver() || plc_is_approver($uid)
        || db_val('SELECT 1 FROM plc_members a JOIN plc_members b ON b.group_id = a.group_id WHERE a.user_id = ? AND b.user_id = ? LIMIT 1', 'ii', [$me, $uid]);
    $sig = $allowed ? db_val('SELECT signature FROM users WHERE id = ?', 'i', [$uid]) : null;
    $path = plc_signature_path($sig);
    if (!$path) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

$ph = db_row('SELECT * FROM plc_photos WHERE id = ?', 'i', [(int)get('photo')]);
$g = $ph ? plc_group_get((int)$ph['group_id']) : null;
if (!$ph || !$g || !plc_can_view_group($g)) {
    http_response_code(404);
    exit;
}
$cache = ['Cache-Control: private, max-age=86400'];
$main = trip_main_name($ph['file_name']);
$want = get('thumb') === '1' ? trip_thumb_name($ph['file_name']) : $main;
if (!storage_serve($want, 'image/jpeg', null, $cache) && ($want === $main || !storage_serve($main, 'image/jpeg', null, $cache))) {
    http_response_code(404);
}
