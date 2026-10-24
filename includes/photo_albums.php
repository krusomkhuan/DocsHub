<?php
/**
 * คลังรูปภาพ: เก็บลิงก์อัลบั้มภาพกิจกรรม (Google Photos, Google Drive, Facebook ฯลฯ)
 * - บุคคลทั่วไปค้นหาและเปิดอัลบั้มที่อนุมัติแล้ว
 * - สมาชิกส่งลิงก์ (รออนุมัติ) แก้ไข/ลบได้จนกว่าจะอนุมัติ
 * - เจ้าหน้าที่/ผู้ดูแลระบบ อนุมัติ แก้ไข ลบ และนำเข้าทีละหลายรายการ
 */

/** ลิงก์ต้องเป็น http/https เท่านั้น (กัน javascript: และลิงก์แปลก ๆ) */
function album_valid_url(string $url): bool
{
    return strlen($url) <= 500 && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/** ชื่อโดเมนสำหรับแสดงประเภทลิงก์ */
function album_source(string $url): array
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $map = [
        'photos.app.goo.gl' => ['Google Photos', 'bi-images'], 'photos.google.com' => ['Google Photos', 'bi-images'],
        'drive.google.com' => ['Google Drive', 'bi-google'], 'facebook.com' => ['Facebook', 'bi-facebook'], 'fb.watch' => ['Facebook', 'bi-facebook'],
        'youtube.com' => ['YouTube', 'bi-youtube'], 'youtu.be' => ['YouTube', 'bi-youtube'], 'onedrive.live.com' => ['OneDrive', 'bi-cloud'],
        '1drv.ms' => ['OneDrive', 'bi-cloud'], 'flickr.com' => ['Flickr', 'bi-camera'],
    ];
    foreach ($map as $domain => $info) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) return $info;
    }
    return [$host ?: 'ลิงก์', 'bi-link-45deg'];
}

function album_can_edit(array $a): bool
{
    if (is_staff()) return true;
    $u = current_user();
    return $u && (int)$a['submitted_by'] === (int)$u['id'] && $a['status'] !== 'approved';
}

/** มีอัลบั้มที่ใช้ลิงก์นี้อยู่แล้วหรือไม่ (ไม่นับรายการที่กำลังแก้ไข) */
function album_duplicate(string $url, int $exceptId = 0): ?array
{
    return db_row("SELECT id, title, status FROM photo_albums WHERE url = ? AND id <> ? AND status <> 'rejected' LIMIT 1", 'si', [$url, $exceptId]);
}
