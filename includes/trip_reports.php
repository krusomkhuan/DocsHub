<?php
/**
 * รายงานการไปราชการ: ผูกกับเอกสารในหมวดที่เปิดใช้ (categories.has_trip_report) เช่น คำสั่งไปราชการ
 * ภาพประกอบเก็บที่ uploads/docs/trips/ (ย่อขนาดอัตโนมัติ) แสดงผ่าน trip-image.php ที่ตรวจสิทธิ์
 */

define('TRIP_IMG_MAX', 1600);     // ด้านยาวสูงสุดของภาพ (px)
define('TRIP_THUMB_MAX', 480);    // ภาพย่อ
define('TRIP_MAX_IMAGES', 30);    // จำนวนภาพสูงสุดต่อรายงาน

function trip_category_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = array_map('intval', array_column(db_all('SELECT id FROM categories WHERE has_trip_report = 1'), 'id'));
    }
    return $ids;
}

function doc_allows_trip_report(array $doc): bool
{
    return $doc['category_id'] && in_array((int)$doc['category_id'], trip_category_ids(), true);
}

/** ดึงรายงานพร้อมข้อมูลคำสั่งที่อ้างอิง */
function trip_get(int $id): ?array
{
    return db_row('SELECT r.*, d.title AS doc_title, d.doc_no, d.doc_date, d.status AS doc_status, d.is_public AS doc_public,
                          d.uploaded_by AS doc_owner, d.department_id, dp.name AS dept_name, u.fullname AS reporter
                   FROM trip_reports r JOIN documents d ON d.id = r.document_id
                   LEFT JOIN departments dp ON dp.id = d.department_id
                   LEFT JOIN users u ON u.id = r.created_by
                   WHERE r.id = ?', 'i', [$id]);
}

function trip_can_view(array $r): bool
{
    if (is_staff()) return true;
    $u = current_user();
    if ($u && (int)$r['created_by'] === (int)$u['id']) return true;
    $doc = ['status' => $r['doc_status'], 'is_public' => $r['doc_public'], 'uploaded_by' => $r['doc_owner']];
    if (!can_view_document($doc)) return false;
    return $r['is_public'] ? true : is_logged_in();
}

function trip_can_edit(array $r): bool
{
    $u = current_user();
    return is_staff() || ($u && (int)$r['created_by'] === (int)$u['id']);
}

/** เงื่อนไข SQL สำหรับรายการรายงานที่ผู้ใช้ปัจจุบันมองเห็น (alias r = trip_reports, d = documents) */
function trip_visible_sql(string &$types, array &$params): string
{
    if (is_staff()) return '1=1';
    if (is_logged_in()) {
        $types .= 'i';
        $params[] = (int)current_user()['id'];
        return "(d.status = 'approved' OR r.created_by = ?)";
    }
    return "d.status = 'approved' AND d.is_public = 1 AND r.is_public = 1";
}

/** แปลง $_FILES แบบหลายไฟล์ให้เป็นรายการไฟล์ */
function normalize_files(array $f): array
{
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function trip_resize(GdImage $src, int $max)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    if ($scale >= 1) return $src;
    $nw = (int)round($w * $scale);
    $nh = (int)round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/**
 * ตรวจสอบ หมุนตาม EXIF ย่อขนาด แล้วบันทึกภาพเป็น JPEG พร้อมภาพย่อ
 * คืนค่า [file_name, width, height, file_size] หรือโยน RuntimeException
 */
function trip_save_image(array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดภาพ ' . $file['name'] . ' ไม่สำเร็จ');
    }
    if ($file['size'] > 15 * 1024 * 1024) {
        throw new RuntimeException('ภาพ ' . $file['name'] . ' มีขนาดเกิน 15 MB');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('ไฟล์ ' . $file['name'] . ' ไม่ใช่ภาพ JPG, PNG หรือ WEBP');
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('อ่านภาพ ' . $file['name'] . ' ไม่ได้');
    }
    // ภาพจากมือถือ: หมุนตามค่า Orientation ใน EXIF
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }
    // PNG โปร่งใส: รองพื้นสีขาวก่อนแปลงเป็น JPEG
    $flat = imagecreatetruecolor(imagesx($src), imagesy($src));
    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
    imagecopy($flat, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));

    $sub = 'trips/' . date('Y/m');
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บภาพได้');
    }
    $base = $sub . '/' . date('Ymd_His_') . bin2hex(random_bytes(6));
    $main = trip_resize($flat, TRIP_IMG_MAX);
    imagejpeg($main, UPLOAD_DIR . '/' . $base . '.jpg', 82);
    imagejpeg(trip_resize($main, TRIP_THUMB_MAX), UPLOAD_DIR . '/' . $base . '-t.jpg', 78);
    $size = (int)filesize(UPLOAD_DIR . '/' . $base . '.jpg');
    $ref = $base . '.jpg';
    // Google Drive: ส่งภาพหลักและภาพย่อ เก็บเป็น "gdrive:<ภาพหลัก>|<ภาพย่อ>"
    // (เก็บใน Drive เท่านั้น: ส่งไม่สำเร็จจะลบไฟล์ชั่วคราวทั้งคู่ และไม่ทิ้งภาพครึ่งเดียวไว้ใน Drive)
    try {
        $mainRef = storage_push($base . '.jpg', 'trip_' . basename($base) . '.jpg', 'trip-reports');
    } catch (RuntimeException $ex) {
        @unlink(UPLOAD_DIR . '/' . $base . '-t.jpg');
        throw $ex;
    }
    if (storage_is_drive($mainRef)) {
        try {
            $thumbRef = storage_push($base . '-t.jpg', 'trip_' . basename($base) . '-t.jpg', 'trip-reports');
        } catch (RuntimeException $ex) {
            storage_delete($mainRef);
            throw $ex;
        }
        $ref = $mainRef . '|' . substr($thumbRef, strlen(GDRIVE_PREFIX));
    }
    return [$ref, imagesx($main), imagesy($main), $size];
}

/** ตำแหน่งภาพย่อ (ในเครื่อง: xxx-t.jpg · ใน Drive: ส่วนหลัง | หรือใช้ภาพหลักถ้าไม่มี) */
function trip_thumb_name(string $fileName): string
{
    if (storage_is_drive($fileName)) {
        $ids = storage_drive_ids($fileName);
        return GDRIVE_PREFIX . ($ids[1] ?? $ids[0] ?? '');
    }
    return preg_replace('/\.jpg$/', '-t.jpg', $fileName);
}

/** ตำแหน่งภาพหลัก */
function trip_main_name(string $fileName): string
{
    return storage_is_drive($fileName) ? GDRIVE_PREFIX . (storage_drive_ids($fileName)[0] ?? '') : $fileName;
}

function trip_delete_image_files(string $fileName): void
{
    delete_stored_file($fileName); // ค่าจาก Drive (ภาพหลัก|ภาพย่อ) ลบทั้งสองไฟล์ในครั้งเดียว
    if (!storage_is_drive($fileName)) delete_stored_file(trip_thumb_name($fileName));
}

/** ลบไฟล์ทั้งหมดของรายงาน (เรียกก่อนลบแถวในฐานข้อมูล) */
function trip_delete_report_files(int $reportId): void
{
    foreach (db_all('SELECT file_name FROM trip_report_images WHERE report_id = ?', 'i', [$reportId]) as $img) {
        trip_delete_image_files($img['file_name']);
    }
    delete_stored_file(db_val('SELECT file_name FROM trip_reports WHERE id = ?', 'i', [$reportId]));
}

/** ลบไฟล์รายงานทั้งหมดที่ผูกกับเอกสาร (แถวในฐานข้อมูลถูกลบตาม ON DELETE CASCADE) */
function trip_delete_files_for_document(int $docId): void
{
    foreach (db_all('SELECT id FROM trip_reports WHERE document_id = ?', 'i', [$docId]) as $r) {
        trip_delete_report_files((int)$r['id']);
    }
}

function thai_date_range(string $start, string $end): string
{
    return $start === $end ? thai_date($start, false) : thai_date($start, false) . ' – ' . thai_date($end, false);
}
