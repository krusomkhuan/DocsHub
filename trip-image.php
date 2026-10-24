<?php
/** แสดงภาพประกอบรายงานการไปราชการ (ตรวจสิทธิ์ก่อนส่งภาพ) ?id=&thumb=1 */
require_once __DIR__ . '/includes/bootstrap.php';

$img = db_row('SELECT * FROM trip_report_images WHERE id = ?', 'i', [(int)get('id')]);
$report = $img ? trip_get((int)$img['report_id']) : null;
if (!$img || !$report || !trip_can_view($report)) {
    http_response_code(404);
    exit;
}
$disposition = get('download') === '1' ? 'attachment; filename="trip-' . $report['id'] . '-' . $img['id'] . '.jpg"' : null;
$cache = ['Cache-Control: private, max-age=86400'];
$main = trip_main_name($img['file_name']);
$want = get('thumb') === '1' ? trip_thumb_name($img['file_name']) : $main;
// ไม่มีภาพย่อ ใช้ภาพจริงแทน
if (!storage_serve($want, 'image/jpeg', $disposition, $cache) && ($want === $main || !storage_serve($main, 'image/jpeg', $disposition, $cache))) {
    http_response_code(404);
    exit;
}
