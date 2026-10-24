<?php
require_once __DIR__ . '/includes/bootstrap.php';

// เปิดอัลบั้ม: นับจำนวนครั้งแล้วพาไปยังลิงก์ (เฉพาะอัลบั้มที่อนุมัติแล้ว หรือของตนเอง/เจ้าหน้าที่)
if (get('go') !== '') {
    $a = db_row('SELECT * FROM photo_albums WHERE id = ?', 'i', [(int)get('go')]);
    if (!$a || !album_valid_url($a['url']) || ($a['status'] !== 'approved' && !album_can_edit($a))) {
        http_response_code(404);
        exit('ไม่พบอัลบั้ม');
    }
    if ($a['status'] === 'approved') {
        db_exec('UPDATE photo_albums SET clicks = clicks + 1 WHERE id = ?', 'i', [$a['id']]);
    }
    header('Location: ' . $a['url'], true, 302);
    exit;
}

// ลบลิงก์ของตนเองที่ยังไม่อนุมัติ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    require_login();
    $a = db_row('SELECT * FROM photo_albums WHERE id = ?', 'i', [(int)post('id')]);
    if ($a && album_can_edit($a)) {
        db_exec('DELETE FROM photo_albums WHERE id = ?', 'i', [$a['id']]);
        log_activity('album_delete', '#' . $a['id'] . ' ' . $a['title']);
        flash('success', 'ลบลิงก์อัลบั้มแล้ว');
    }
    redirect('photo-albums.php');
}

$q = get('q');
$where = "a.status = 'approved'";
$types = '';
$params = [];
if ($q !== '') {
    $where .= ' AND (a.title LIKE ? OR a.description LIKE ? OR a.url LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if (get('year') !== '') { $where .= ' AND a.academic_year = ?'; $types .= 's'; $params[] = get('year'); }
if ((int)get('dept')) { $where .= ' AND a.department_id = ?'; $types .= 'i'; $params[] = (int)get('dept'); }

$p = paginate((int)db_val("SELECT COUNT(*) FROM photo_albums a WHERE $where", $types, $params), 50);
$rows = db_all("SELECT a.*, d.name AS dept_name FROM photo_albums a LEFT JOIN departments d ON d.id = a.department_id
                WHERE $where ORDER BY COALESCE(a.event_date, DATE(a.created_at)) DESC, a.id DESC
                LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$mine = is_logged_in() ? db_all("SELECT * FROM photo_albums WHERE submitted_by = ? AND status <> 'approved' ORDER BY id DESC", 'i', [current_user()['id']]) : [];
$total = (int)db_val("SELECT COUNT(*) FROM photo_albums WHERE status = 'approved'");

$pageTitle = 'คลังรูปภาพ';
$activeNav = 'albums';
require __DIR__ . '/includes/header.php';
?>
<div class="album-head mb-4">
  <div class="album-banner"><i class="bi bi-images me-2"></i>ระบบสืบค้นข้อมูลคลังเก็บรูปภาพ <?= e(setting('college_name')) ?></div>
</div>

<div class="row justify-content-center mb-3">
  <div class="col-lg-9">
    <form class="card shadow-sm album-search">
      <div class="card-body">
        <label class="form-label text-info-emphasis fw-medium" for="q">ป้อนชื่ออัลบั้ม || เช่น โครงการอบรมคุณธรรม จริยธรรม <?= current_year_be() ?></label>
        <div class="input-group">
          <input class="form-control form-control-lg border-0 border-bottom rounded-0" id="q" name="q" placeholder="ป้อนคำที่ต้องการค้นหา" value="<?= e($q) ?>" autofocus>
          <button class="btn btn-warning px-4"><i class="bi bi-search"></i> ค้นหา</button>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-sm-5"><select class="form-select form-select-sm" name="year" onchange="this.form.submit()"><option value="">ทุกปีการศึกษา</option>
            <?php foreach (academic_years() as $y): ?><option<?= get('year') === (string)$y ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></div>
          <div class="col-sm-7"><select class="form-select form-select-sm" name="dept" onchange="this.form.submit()"><option value="">ทุกแผนก / ฝ่าย</option>
            <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= (int)get('dept') === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
  <div class="small text-muted"><?= $q !== '' || get('year') !== '' || get('dept') !== '' ? 'พบ ' . number_format($p['total']) . ' อัลบั้ม จากทั้งหมด ' . number_format($total) : 'ทั้งหมด ' . number_format($total) . ' อัลบั้ม' ?>
    <?= $q !== '' || get('year') !== '' || get('dept') !== '' ? ' · <a href="' . url('photo-albums.php') . '">ล้างการค้นหา</a>' : '' ?></div>
  <?php if (is_logged_in()): ?>
    <a class="btn btn-primary btn-sm" href="<?= url('photo-album-form.php') ?>"><i class="bi bi-link-45deg me-1"></i>ส่งลิงก์อัลบั้ม</a>
  <?php else: ?>
    <a class="btn btn-outline-primary btn-sm" href="<?= url('login.php?next=' . urlencode(url('photo-album-form.php'))) ?>"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบเพื่อส่งลิงก์</a>
  <?php endif; ?>
</div>

<div class="table-responsive shadow-sm rounded-3 mb-3">
  <table class="table album-table mb-0">
    <thead><tr><th style="width:60px"></th><th>ป้อนชื่ออัลบั้ม || เช่น โครงการอบรมคุณธรรม จริยธรรม <?= current_year_be() ?></th><th>ป้อนลิงก์ || เช่น https://photos.app.goo.gl/...</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="3" class="text-center text-muted py-5 bg-white"><i class="bi bi-images fs-1 d-block mb-2"></i><?= $q !== '' ? 'ไม่พบอัลบั้มที่ตรงกับ "' . e($q) . '"' : 'ยังไม่มีอัลบั้มในคลัง' ?></td></tr><?php endif; ?>
      <?php foreach ($rows as $i => $a): [$src, $icon] = album_source($a['url']); ?>
        <tr>
          <td><?= $p['offset'] + $i + 1 ?>.</td>
          <td>
            <?= e($a['title']) ?>
            <?php if ($a['event_date'] || $a['dept_name']): ?><div class="small opacity-75"><?= $a['event_date'] ? thai_date($a['event_date']) : '' ?><?= $a['event_date'] && $a['dept_name'] ? ' · ' : '' ?><?= e($a['dept_name']) ?></div><?php endif; ?>
          </td>
          <td class="text-break">
            <a href="<?= url('photo-albums.php?go=' . $a['id']) ?>" target="_blank" rel="noopener" title="<?= e($src) ?>"><?= e($a['url']) ?></a>
            <span class="small opacity-75 ms-1 text-nowrap"><i class="bi <?= $icon ?>"></i><?= $a['clicks'] ? ' · เปิด ' . number_format($a['clicks']) . ' ครั้ง' : '' ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pagination_links($p) ?>

<?php if ($mine): ?>
  <div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-split me-1"></i>ลิงก์ที่ฉันส่ง (ยังไม่เผยแพร่)</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($mine as $a): ?>
        <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
          <div class="me-auto"><?= e($a['title']) ?> <?= status_badge($a['status']) ?>
            <div class="small text-muted text-break"><?= e($a['url']) ?></div>
            <?php if ($a['status'] === 'rejected' && $a['reject_reason']): ?><div class="small text-danger">เหตุผล: <?= e($a['reject_reason']) ?></div><?php endif; ?></div>
          <a class="btn btn-sm btn-outline-secondary" href="<?= url('photo-album-form.php?id=' . $a['id']) ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" data-confirm="ลบลิงก์ '<?= e($a['title']) ?>'?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
