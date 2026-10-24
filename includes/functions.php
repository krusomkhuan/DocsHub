<?php
/**
 * ฟังก์ชันกลางของระบบ Docs Hub
 */

/* ---------------- ฐานข้อมูล (mysqli prepared statements) ---------------- */

function db_query(string $sql, string $types = '', array $params = [])
{
    global $db;
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    return $res; // mysqli_result สำหรับ SELECT, false สำหรับคำสั่งอื่น
}

function db_all(string $sql, string $types = '', array $params = []): array
{
    $res = db_query($sql, $types, $params);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_row(string $sql, string $types = '', array $params = []): ?array
{
    $res = db_query($sql, $types, $params);
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function db_val(string $sql, string $types = '', array $params = [])
{
    $res = db_query($sql, $types, $params);
    $row = $res ? $res->fetch_row() : null;
    return $row ? $row[0] : null;
}

/** INSERT/UPDATE/DELETE คืนค่า insert_id (ถ้ามี) หรือจำนวนแถวที่กระทบ */
function db_exec(string $sql, string $types = '', array $params = []): int
{
    global $db;
    db_query($sql, $types, $params);
    return $db->insert_id ?: $db->affected_rows;
}

/* ---------------- ทั่วไป ---------------- */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://|^/#', $path) ? $path : url($path)));
    exit;
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? (is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key]) : $default;
}

function get(string $key, $default = '')
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/* ---------------- CSRF & Flash ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('คำขอไม่ถูกต้อง (CSRF token) กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
              . e($f['msg']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------------- สมาชิก / สิทธิ์ ---------------- */

function current_user(): ?array
{
    global $CURRENT_USER;
    return $CURRENT_USER;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return is_logged_in() && current_user()['role'] === 'admin';
}

/** เจ้าหน้าที่หรือผู้ดูแล เข้าหลังบ้านได้ */
function is_staff(): bool
{
    return is_logged_in() && in_array(current_user()['role'], ['admin', 'staff'], true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_staff(): void
{
    require_login();
    if (!is_staff()) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('หน้านี้สำหรับผู้ดูแลระบบเท่านั้น');
    }
}

function role_label(string $role): string
{
    return ['admin' => 'ผู้ดูแลระบบ', 'staff' => 'เจ้าหน้าที่', 'member' => 'สมาชิก'][$role] ?? $role;
}

function status_badge(string $status): string
{
    $map = [
        'pending'  => ['warning', 'รออนุมัติ'],
        'active'   => ['success', 'ใช้งาน'],
        'approved' => ['success', 'อนุมัติแล้ว'],
        'disabled' => ['secondary', 'ระงับ'],
        'rejected' => ['danger', 'ไม่อนุมัติ'],
    ];
    [$c, $t] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge text-bg-' . $c . '">' . $t . '</span>';
}

function log_activity(string $action, string $detail = ''): void
{
    $uid = current_user()['id'] ?? null;
    db_exec('INSERT INTO activity_logs (user_id, action, detail, ip) VALUES (?,?,?,?)',
        'isss', [$uid, $action, mb_substr($detail, 0, 255), client_ip()]);
}

/* ---------------- วันที่ภาษาไทย ---------------- */

function thai_date(?string $date, bool $short = true, bool $time = false): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '-';
    }
    $ts = strtotime($date);
    $full  = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $abbr  = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $m = (int)date('n', $ts);
    $out = date('j', $ts) . ' ' . ($short ? $abbr[$m] : $full[$m]) . ' ' . ((int)date('Y', $ts) + 543);
    return $time ? $out . ' ' . date('H:i', $ts) . ' น.' : $out;
}

function current_year_be(): int
{
    return (int)date('Y') + 543;
}

/** ปีการศึกษาปัจจุบัน (เริ่ม พ.ค.) */
/** ปีการศึกษาปัจจุบัน: ตามที่ผู้ดูแลกำหนด หรือคำนวณจากวันที่ (เริ่ม พ.ค.) */
function current_academic_year(): int
{
    $set = db_val('SELECT year_be FROM academic_years WHERE is_current = 1 LIMIT 1');
    return $set ? (int)$set : current_year_be() - ((int)date('n') < 5 ? 1 : 0);
}

/** รายการปีการศึกษาที่ผู้ดูแลเพิ่มไว้ (ใหม่ → เก่า) */
function academic_years(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = array_column(db_all('SELECT year_be FROM academic_years ORDER BY year_be DESC'), 'year_be');
    }
    return $rows;
}

/* ---------------- ไฟล์ ---------------- */

function format_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = $bytes;
    while ($size >= 1024 && $i < 3) {
        $size /= 1024;
        $i++;
    }
    return round($size, $i ? 1 : 0) . ' ' . $units[$i];
}

function file_icon(string $ext): string
{
    $ext = strtolower($ext);
    if ($ext === 'pdf') return 'bi-file-earmark-pdf text-danger';
    if (in_array($ext, ['doc', 'docx'])) return 'bi-file-earmark-word text-primary';
    if (in_array($ext, ['xls', 'xlsx'])) return 'bi-file-earmark-excel text-success';
    if (in_array($ext, ['ppt', 'pptx'])) return 'bi-file-earmark-ppt text-warning';
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) return 'bi-file-earmark-image text-info';
    if (in_array($ext, ['zip', 'rar'])) return 'bi-file-earmark-zip text-secondary';
    return 'bi-file-earmark';
}

/**
 * ตรวจสอบและบันทึกไฟล์อัปโหลด คืนค่า array ข้อมูลไฟล์ หรือโยน RuntimeException
 */
function save_upload(array $file, string $category = ''): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE   => 'ไฟล์มีขนาดเกินค่าที่เซิร์ฟเวอร์กำหนด (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE  => 'ไฟล์มีขนาดใหญ่เกินไป',
            UPLOAD_ERR_PARTIAL    => 'อัปโหลดไฟล์ไม่สมบูรณ์',
            UPLOAD_ERR_NO_FILE    => 'กรุณาเลือกไฟล์',
        ][$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'อัปโหลดไฟล์ไม่สำเร็จ';
        throw new RuntimeException($msg);
    }
    $max = (int)setting('max_upload_mb', '20') * 1024 * 1024;
    if ($file['size'] > $max) {
        throw new RuntimeException('ไฟล์มีขนาดเกิน ' . setting('max_upload_mb', '20') . ' MB');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        throw new RuntimeException('ไม่อนุญาตไฟล์นามสกุล .' . $ext . ' (อนุญาต: ' . implode(', ', ALLOWED_EXT) . ')');
    }
    $sub = date('Y/m');
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บไฟล์ได้');
    }
    $stored = $sub . '/' . date('Ymd_His_') . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    }
    return [
        'file_name'     => storage_push($stored, basename($file['name']), $category), // ส่งขึ้น Google Drive ถ้าเปิดใช้ (ใบเสร็จ: โฟลเดอร์ย่อยของตนเอง)
        'file_original' => mb_substr(basename($file['name']), 0, 255),
        'file_size'     => (int)$file['size'],
        'file_ext'      => $ext,
    ];
}

/** ลบไฟล์ที่บันทึกไว้ (ในเครื่อง หรือใน Google Drive) */
function delete_stored_file(?string $stored): void
{
    storage_delete($stored);
}

/* ---------------- ข้อมูลอ้างอิง ---------------- */

function doc_types(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        foreach (db_all('SELECT * FROM doc_types ORDER BY sort_order, id') as $r) {
            $rows[$r['id']] = $r;
        }
    }
    return $rows;
}

function doc_type_by_slug(string $slug): ?array
{
    foreach (doc_types() as $t) {
        if ($t['slug'] === $slug) return $t;
    }
    return null;
}

function departments(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        foreach (db_all('SELECT * FROM departments ORDER BY sort_order, name') as $r) {
            $rows[$r['id']] = $r;
        }
    }
    return $rows;
}

function categories(?int $typeId = null): array
{
    if ($typeId) {
        return db_all('SELECT * FROM categories WHERE type_id = ? ORDER BY sort_order, name', 'i', [$typeId]);
    }
    return db_all('SELECT c.*, t.name AS type_name FROM categories c JOIN doc_types t ON t.id = c.type_id ORDER BY t.sort_order, c.sort_order, c.name');
}

/** ผู้ใช้คนนี้ดูเอกสารได้หรือไม่ */
function can_view_document(array $doc): bool
{
    if (is_staff()) return true;
    $u = current_user();
    if ($u && (int)$doc['uploaded_by'] === (int)$u['id']) return true;
    if ($doc['status'] !== 'approved') return false;
    return $doc['is_public'] ? true : is_logged_in();
}

function can_edit_document(array $doc): bool
{
    if (is_staff()) return true;
    $u = current_user();
    return $u && (int)$doc['uploaded_by'] === (int)$u['id'] && $doc['status'] !== 'approved';
}

/* ---------------- ออกเลขหนังสือราชการ ---------------- */

function book_kinds(): array
{
    return ['out' => 'หนังสือส่ง', 'in' => 'หนังสือรับ', 'memo' => 'บันทึกข้อความ', 'order' => 'คำสั่ง'];
}

/** เลขเริ่มต้นที่กำหนดไว้ของแผนก/ประเภท/ปี (1 ถ้าไม่ได้กำหนด) */
function book_start_number(int $deptId, string $kind, int $yearBe): int
{
    return (int)(db_val('SELECT start_no FROM book_number_starts WHERE department_id = ? AND book_kind = ? AND year_be = ?', 'isi', [$deptId, $kind, $yearBe]) ?? 1);
}

/** เลขถัดไป = ค่าที่มากกว่าระหว่าง เลขเริ่มต้นที่กำหนด กับ เลขล่าสุดที่ออกแล้ว + 1 (เลขไม่ย้อนหลังและไม่ซ้ำ) */
function book_next_number(int $deptId, string $kind, int $yearBe): int
{
    $last = (int)db_val('SELECT COALESCE(MAX(running_no), 0) FROM book_numbers WHERE department_id = ? AND book_kind = ? AND year_be = ?', 'isi', [$deptId, $kind, $yearBe]);
    return max($last + 1, book_start_number($deptId, $kind, $yearBe));
}

/**
 * ออกเลขหนังสือถัดไปของแผนก/ประเภท/ปี แบบปลอดภัยเมื่อมีผู้ขอพร้อมกัน (row lock ใน transaction)
 * คืนค่า array ของแถวที่สร้าง
 */
function issue_book_number(int $deptId, string $kind, string $date, string $subject, string $toWhom = '', string $note = '', ?int $documentId = null): array
{
    global $db;
    if (!isset(book_kinds()[$kind])) {
        throw new RuntimeException('ประเภทหนังสือไม่ถูกต้อง');
    }
    $dept = departments()[$deptId] ?? null;
    if (!$dept) {
        throw new RuntimeException('ไม่พบแผนกวิชา');
    }
    $yearBe = (int)date('Y', strtotime($date)) + 543;

    $db->begin_transaction();
    try {
        // ล็อกแถวของแผนกเพื่อเรียงคิวการออกเลข
        db_query('SELECT id FROM departments WHERE id = ? FOR UPDATE', 'i', [$deptId]);
        $next = book_next_number($deptId, $kind, $yearBe);
        $fullNo = strtr(setting('book_format', '{code} {no}/{year}'), [
            '{prefix}' => setting('book_prefix'),
            '{code}'   => $dept['code'],
            '{no}'     => str_pad((string)$next, 3, '0', STR_PAD_LEFT),
            '{year}'   => (string)$yearBe,
        ]);
        $uid = current_user()['id'] ?? null;
        $id = db_exec('INSERT INTO book_numbers (department_id, book_kind, year_be, running_no, full_no, book_date, subject, to_whom, note, document_id, created_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)', 'isiisssssii',
            [$deptId, $kind, $yearBe, $next, $fullNo, $date, $subject, $toWhom, $note, $documentId, $uid]);
        $db->commit();
    } catch (Throwable $ex) {
        $db->rollback();
        throw $ex;
    }
    log_activity('book_number', $fullNo . ' ' . $subject);
    return db_row('SELECT * FROM book_numbers WHERE id = ?', 'i', [$id]);
}

/* ---------------- แบ่งหน้า ---------------- */

function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($pages, max(1, (int)get('page', '1')));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) return '';
    $q = $_GET;
    $html = '<nav><ul class="pagination pagination-sm justify-content-center flex-wrap">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 10 && abs($i - $p['page']) > 2 && $i !== 1 && $i !== $p['pages']) {
            if (abs($i - $p['page']) === 3) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
        $q['page'] = $i;
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($q)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

/** สร้างเงื่อนไขค้นหาเอกสาร (ใช้ร่วมหน้าบ้าน/หลังบ้าน) */
function document_filters(array $f, string &$where, string &$types, array &$params): void
{
    if (!empty($f['type_id']))   { $where .= ' AND d.type_id = ?';       $types .= 'i'; $params[] = (int)$f['type_id']; }
    if (!empty($f['cat']))       { $where .= ' AND d.category_id = ?';   $types .= 'i'; $params[] = (int)$f['cat']; }
    if (!empty($f['dept']))      { $where .= ' AND d.department_id = ?'; $types .= 'i'; $params[] = (int)$f['dept']; }
    if (!empty($f['year']))      { $where .= ' AND d.academic_year = ?'; $types .= 's'; $params[] = $f['year']; }
    if (!empty($f['status']))    { $where .= ' AND d.status = ?';        $types .= 's'; $params[] = $f['status']; }
    if (!empty($f['q'])) {
        // ค้นได้ทุกประเภทเอกสาร: แยกคำด้วยช่องว่าง ทุกคำต้องพบในช่องใดช่องหนึ่ง (รวมชื่อประเภท หมวด แผนก ปีการศึกษา ชื่อไฟล์)
        $fields = ['d.title', 'd.doc_no', 'd.owner_name', 'd.subject_code', 'd.subject_name', 'd.description', 'd.academic_year', 'd.file_original',
                   'd.semester', '(SELECT t.name FROM doc_types t WHERE t.id = d.type_id)', '(SELECT c.name FROM categories c WHERE c.id = d.category_id)',
                   '(SELECT dp.name FROM departments dp WHERE dp.id = d.department_id)', '(SELECT dp.code FROM departments dp WHERE dp.id = d.department_id)'];
        $words = array_slice(array_filter(preg_split('/\s+/u', trim($f['q'])), fn($w) => $w !== ''), 0, 8);
        foreach ($words as $w) {
            $where .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $fields)) . ')';
            $like = '%' . addcslashes($w, '%_\\') . '%';
            $types .= str_repeat('s', count($fields));
            array_push($params, ...array_fill(0, count($fields), $like));
        }
    }
}

/* ---------------- URL เต็มของระบบ ---------------- */

/** ที่อยู่ระบบจากคำขอปัจจุบัน เช่น http://localhost/docshub */
function detected_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
}

/** ลิงก์เต็มสำหรับคัดลอก/แชร์ ใช้ URL จากการตั้งค่าระบบ (ถ้ามี) */
function abs_url(string $path = ''): string
{
    $base = setting('site_url') !== '' ? rtrim(setting('site_url'), '/') : detected_base_url();
    return $base . '/' . ltrim($path, '/');
}

/* ---------------- ภาพโปรไฟล์ ---------------- */

/** แสดงภาพโปรไฟล์ หรือวงกลมอักษรแรกของชื่อเมื่อยังไม่มีภาพ */
function avatar_html(array $user, int $size = 32, string $class = ''): string
{
    $style = "width:{$size}px;height:{$size}px";
    if (!empty($user['avatar'])) {
        $src = url('avatar.php?id=' . (int)$user['id'] . '&v=' . substr(md5($user['avatar']), 0, 8));
        return '<img src="' . $src . '" alt="" class="rounded-circle object-fit-cover flex-shrink-0 ' . e($class) . '" style="' . $style . '">';
    }
    $initial = mb_substr(trim((string)($user['fullname'] ?? '?')), 0, 1);
    return '<span class="avatar-initial rounded-circle flex-shrink-0 ' . e($class) . '" style="' . $style . ';font-size:' . round($size * 0.45) . 'px">' . e($initial) . '</span>';
}

/**
 * บันทึกภาพโปรไฟล์: หมุนตาม EXIF ครอปกึ่งกลางเป็นสี่เหลี่ยมจัตุรัส 320px แล้วเก็บเป็น JPEG
 * คืนค่าชื่อไฟล์ (relative กับ UPLOAD_DIR) หรือโยน RuntimeException
 */
function save_avatar(array $file, int $userId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดภาพโปรไฟล์ไม่สำเร็จ');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('ภาพโปรไฟล์ต้องมีขนาดไม่เกิน 10 MB');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('ภาพโปรไฟล์ต้องเป็นไฟล์ JPG, PNG หรือ WEBP');
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('อ่านไฟล์ภาพไม่ได้');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $rot = [3 => 180, 6 => -90, 8 => 90][@exif_read_data($file['tmp_name'])['Orientation'] ?? 1] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $out = imagecreatetruecolor(320, 320);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), 320, 320, $side, $side);

    $dir = UPLOAD_DIR . '/avatars';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บภาพได้');
    }
    $name = 'avatars/u' . $userId . '_' . bin2hex(random_bytes(5)) . '.jpg';
    imagejpeg($out, UPLOAD_DIR . '/' . $name, 85);
    return $name; // ภาพโปรไฟล์เก็บในเครื่องเสมอ (uploads/docs/avatars) ไม่ส่งขึ้น Google Drive
}
