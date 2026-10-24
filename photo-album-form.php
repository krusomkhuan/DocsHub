<?php
/** ส่ง/แก้ไขลิงก์อัลบั้มรูปภาพ: สมาชิกส่งแล้วรออนุมัติ เจ้าหน้าที่บันทึกแล้วเผยแพร่ทันที */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$album = null;
if (get('id') !== '') {
    $album = db_row('SELECT * FROM photo_albums WHERE id = ?', 'i', [(int)get('id')]);
    if (!$album || !album_can_edit($album)) {
        flash('danger', 'ไม่พบอัลบั้ม หรือคุณไม่มีสิทธิ์แก้ไข (อัลบั้มที่อนุมัติแล้วต้องให้เจ้าหน้าที่แก้ไข)');
        redirect('photo-albums.php');
    }
}
$staff = is_staff();
$errors = [];
$dup = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'title'         => mb_substr(post('title'), 0, 255),
        'url'           => trim(post('url')),
        'event_date'    => post('event_date') ?: null,
        'academic_year' => in_array(post('academic_year'), array_map('strval', academic_years()), true) ? post('academic_year') : null,
        'department_id' => isset(departments()[(int)post('department_id')]) ? (int)post('department_id') : null,
        'description'   => mb_substr(post('description'), 0, 2000) ?: null,
    ];
    if ($data['title'] === '') $errors[] = 'กรุณากรอกชื่ออัลบั้ม';
    if (!album_valid_url($data['url'])) $errors[] = 'ลิงก์ต้องขึ้นต้นด้วย https:// หรือ http:// เช่น https://photos.app.goo.gl/xxxx';
    if ($data['event_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['event_date'])) $errors[] = 'วันที่ไม่ถูกต้อง';
    if (!$errors && ($dup = album_duplicate($data['url'], (int)($album['id'] ?? 0))) && post('allow_dup') !== '1') {
        $errors[] = 'ลิงก์นี้มีในคลังแล้ว: "' . $dup['title'] . '" — ถ้าต้องการบันทึกซ้ำ ให้ติ๊กยืนยันด้านล่าง';
    }

    if (!$errors) {
        $status = $staff ? (in_array(post('status'), ['approved', 'pending', 'rejected'], true) ? post('status') : 'approved') : 'pending';
        $approver = $status === 'approved' ? (int)current_user()['id'] : null;
        if ($album) {
            db_exec('UPDATE photo_albums SET title=?, url=?, event_date=?, academic_year=?, department_id=?, description=?, status=?, approved_by=?, reject_reason=IF(?=\'rejected\', reject_reason, NULL), updated_at=NOW() WHERE id=?',
                'ssssissisi', array_merge(array_values($data), [$status, $approver, $status, $album['id']]));
            $id = (int)$album['id'];
            log_activity('album_update', '#' . $id . ' ' . $data['title']);
        } else {
            $id = db_exec('INSERT INTO photo_albums (title, url, event_date, academic_year, department_id, description, status, approved_by, submitted_by) VALUES (?,?,?,?,?,?,?,?,?)',
                'ssssissii', array_merge(array_values($data), [$status, $approver, current_user()['id']]));
            log_activity('album_create', '#' . $id . ' ' . $data['title']);
        }
        flash('success', $status === 'approved' ? 'บันทึกและเผยแพร่อัลบั้มแล้ว' : 'ส่งลิงก์อัลบั้มแล้ว รอเจ้าหน้าที่ตรวจสอบก่อนเผยแพร่');
        redirect($staff && post('back') === 'admin' ? 'admin/photo-albums.php' : 'photo-albums.php');
    }
}

$v = fn($k, $def = '') => e($_SERVER['REQUEST_METHOD'] === 'POST' ? post($k, $def) : ($album[$k] ?? $def));
$pageTitle = $album ? 'แก้ไขลิงก์อัลบั้ม' : 'ส่งลิงก์อัลบั้มรูปภาพ';
$activeNav = 'albums';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('photo-albums.php') ?>">คลังรูปภาพ</a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-1"><i class="bi bi-images me-2"></i><?= e($pageTitle) ?></h4>
        <p class="small text-muted">อัปโหลดรูปภาพไว้ที่ Google Photos / Google Drive ฯลฯ ตั้งค่าให้ "ทุกคนที่มีลิงก์ดูได้" แล้วคัดลอกลิงก์มาวางที่นี่<?= $staff ? '' : ' · ลิงก์จะแสดงในคลังหลังเจ้าหน้าที่ตรวจสอบ' ?></p>
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
          <?= csrf_field() ?><input type="hidden" name="back" value="<?= e(get('back', post('back'))) ?>">
          <div class="col-12"><label class="form-label">ชื่ออัลบั้ม / กิจกรรม *</label>
            <input class="form-control" name="title" required maxlength="255" value="<?= $v('title') ?>" placeholder="เช่น โครงการอบรมคุณธรรม จริยธรรม <?= current_year_be() ?>"></div>
          <div class="col-12"><label class="form-label">ลิงก์อัลบั้ม *</label>
            <input type="url" class="form-control" name="url" required maxlength="500" value="<?= $v('url') ?>" placeholder="https://photos.app.goo.gl/xxxxxxxx"></div>
          <?php if ($dup): ?>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_dup" value="1" id="dup"><label class="form-check-label small" for="dup">ยืนยันบันทึกลิงก์ซ้ำ</label></div></div>
          <?php endif; ?>
          <div class="col-md-4"><label class="form-label">วันที่จัดกิจกรรม</label><input type="date" class="form-control" name="event_date" value="<?= $v('event_date') ?>"></div>
          <div class="col-md-3"><label class="form-label">ปีการศึกษา</label>
            <?php $ySel = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('academic_year') : ($album['academic_year'] ?? (string)current_academic_year()); ?>
            <select class="form-select" name="academic_year"><option value="">-</option>
              <?php foreach (academic_years() as $y): ?><option<?= (string)$y === (string)$ySel ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-5"><label class="form-label">แผนก / ฝ่าย / งาน</label>
            <?php $dSel = (int)($_SERVER['REQUEST_METHOD'] === 'POST' ? post('department_id') : ($album['department_id'] ?? current_user()['department_id'] ?? 0)); ?>
            <select class="form-select" name="department_id"><option value="">— ไม่ระบุ —</option>
              <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= $dSel === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12"><label class="form-label">รายละเอียด</label><textarea class="form-control" name="description" rows="2" maxlength="2000"><?= $v('description') ?></textarea></div>
          <?php if ($staff): ?>
            <div class="col-12">
              <?php $st = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('status') : ($album['status'] ?? 'approved'); ?>
              <?php foreach (['approved' => 'เผยแพร่', 'pending' => 'รออนุมัติ', 'rejected' => 'ไม่อนุมัติ'] as $k => $lbl): ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="status" value="<?= $k ?>" id="st<?= $k ?>"<?= $st === $k ? ' checked' : '' ?>><label class="form-check-label" for="st<?= $k ?>"><?= $lbl ?></label></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-send me-1"></i><?= $staff ? 'บันทึก' : 'ส่งลิงก์' ?></button>
            <a class="btn btn-outline-secondary" href="<?= url(get('back') === 'admin' ? 'admin/photo-albums.php' : 'photo-albums.php') ?>">ยกเลิก</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
