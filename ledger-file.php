<?php
/** ดาวน์โหลดหลักฐานของรายการบัญชี (เฉพาะครูในแผนกเจ้าของบัญชี) */
require_once __DIR__ . '/includes/bootstrap.php';
$dept = require_ledger_access();

$en = ledger_entry((int)get('id'), (int)$dept['id']);
if (!$en || !$en['file_name']) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}
$inline = in_array($en['file_ext'], ['pdf', 'jpg', 'jpeg', 'png'], true);
$mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$en['file_ext']] ?? 'application/octet-stream';
$fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $en['file_original']);
$disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($en['file_original']);
if (!storage_serve($en['file_name'], $mime, $disposition, ['Cache-Control: private, no-store'])) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}
