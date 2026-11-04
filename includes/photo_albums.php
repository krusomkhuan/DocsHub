<?php
/**
 * คลังรูปภาพ: เก็บลิงก์อัลบั้มภาพกิจกรรม (Google Photos, Google Drive, Facebook ฯลฯ)
 * - บุคคลทั่วไปค้นหาและเปิดอัลบั้มที่อนุมัติแล้ว
 * - ทุกคนส่งลิงก์ได้โดยไม่ต้องเป็นสมาชิก (รออนุมัติเสมอ)
 * - สมาชิกแก้ไข/ลบลิงก์ของตนได้จนกว่าจะอนุมัติ ผู้ที่ไม่ได้เข้าสู่ระบบแก้ไขภายหลังไม่ได้
 * - เจ้าหน้าที่/ผู้ดูแลระบบ อนุมัติ แก้ไข ลบ และนำเข้าทีละหลายรายการ
 */

// จำนวนลิงก์สูงสุดต่อ IP ต่อชั่วโมง สำหรับผู้ที่ไม่ได้เข้าสู่ระบบ (เผื่อเครือข่ายวิทยาลัยที่ใช้ IP ร่วมกัน)
const ALBUM_GUEST_HOURLY_LIMIT = 20;

/** ผู้ที่ไม่ได้เข้าสู่ระบบส่งลิงก์จาก IP นี้ครบจำนวนต่อชั่วโมงแล้วหรือไม่ */
function album_guest_limit_reached(): bool
{
    return (int)db_val("SELECT COUNT(*) FROM activity_logs WHERE action = 'album_create' AND user_id IS NULL AND ip = ?
                        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", 's', [client_ip()]) >= ALBUM_GUEST_HOURLY_LIMIT;
}

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
