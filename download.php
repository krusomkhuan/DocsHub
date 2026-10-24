<?php
require_once __DIR__ . '/includes/bootstrap.php';

$d = db_row('SELECT * FROM documents WHERE id = ?', 'i', [(int)get('id')]);
if (!$d) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}
if (!can_view_document($d)) {
    if (!is_logged_in()) require_login();
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์ดาวน์โหลดไฟล์นี้');
}

// inline = ดูในหน้าเว็บ (ไม่นับเป็นการดาวน์โหลด) · docx ถูกดึงไปแสดงผลในเบราว์เซอร์ด้วย docx-preview
$inline = get('inline') === '1' && in_array($d['file_ext'], ['pdf', 'jpg', 'jpeg', 'png', 'docx'], true);
$fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $d['file_original']);
$disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($d['file_original']);

// ส่งไฟล์จากเครื่อง หรือจาก Google Drive (ตามตำแหน่งที่เก็บไว้)
if (!storage_serve($d['file_name'], storage_mime($d['file_original'] ?: $d['file_name']), $disposition)) {
    http_response_code(404);
    exit('ไม่พบไฟล์ในระบบจัดเก็บ');
}
if (!$inline) {
    db_exec('UPDATE documents SET downloads = downloads + 1 WHERE id = ?', 'i', [$d['id']]);
}
