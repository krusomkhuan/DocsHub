<?php
require_once __DIR__ . '/includes/bootstrap.php';

$types = '';
$params = [];
$where = trip_visible_sql($types, $params);
if (get('mine') === '1' && is_logged_in()) {
    $where .= ' AND r.created_by = ?';
    $types .= 'i';
    $params[] = (int)current_user()['id'];
}
if (get('dept') !== '') {
    $where .= ' AND d.department_id = ?';
    $types .= 'i';
    $params[] = (int)get('dept');
}
if (get('year') !== '') {
    $where .= ' AND YEAR(r.start_date) = ?';
    $types .= 'i';
    $params[] = (int)get('year') - 543;
}
if (get('q') !== '') {
    $where .= ' AND (r.subject LIKE ? OR r.place LIKE ? OR r.travelers LIKE ? OR d.doc_no LIKE ?)';
    $like = '%' . get('q') . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$from = 'FROM trip_reports r JOIN documents d ON d.id = r.document_id';
$p = paginate((int)db_val("SELECT COUNT(*) $from WHERE $where", $types, $params), 12);
$rows = db_all("SELECT r.*, d.doc_no, dp.name AS dept_name, u.fullname AS reporter,
                       (SELECT COUNT(*) FROM trip_report_images i WHERE i.report_id = r.id) AS img_count,
                       (SELECT i.id FROM trip_report_images i WHERE i.report_id = r.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover_id
                $from LEFT JOIN departments dp ON dp.id = d.department_id LEFT JOIN users u ON u.id = r.created_by
                WHERE $where ORDER BY r.start_date DESC, r.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$years = array_map(fn($y) => (int)$y + 543, array_column(db_all('SELECT DISTINCT YEAR(start_date) y FROM trip_reports ORDER BY y DESC'), 'y'));

$pageTitle = 'รายงานการไปราชการ';
$activeNav = 'trip';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-geo-alt me-2"></i>รายงานการไปราชการ <small class="text-muted fs-6">(<?= number_format($p['total']) ?> รายงาน)</small></h4>
  <?php if (is_logged_in()): ?>
    <a class="btn btn-primary btn-sm" href="<?= url('trip-report-form.php') ?>"><i class="bi bi-plus-lg me-1"></i>เขียนรายงานการไปราชการ</a>
  <?php endif; ?>
</div>

<form class="card shadow-sm mb-3"><div class="card-body row g-2">
  <div class="col-md-4"><input class="form-control form-control-sm" name="q" placeholder="ค้นหาเรื่อง สถานที่ ผู้ไปราชการ เลขที่คำสั่ง" value="<?= e(get('q')) ?>"></div>
  <div class="col-md-3"><select class="form-select form-select-sm" name="dept"><option value="">ทุกแผนก/ฝ่าย</option>
    <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= get('dept') == $dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="year"><option value="">ทุกปี</option>
    <?php foreach ($years as $y): ?><option<?= (int)get('year') === $y ? ' selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
  <?php if (is_logged_in()): ?>
  <div class="col-md-2 d-flex align-items-center"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine"<?= get('mine') === '1' ? ' checked' : '' ?>><label class="form-check-label small" for="mine">เฉพาะของฉัน</label></div></div>
  <?php endif; ?>
  <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button></div>
</div></form>

<?php if (!$rows): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ยังไม่มีรายงานการไปราชการ</div></div>
<?php endif; ?>
<div class="row g-3">
  <?php foreach ($rows as $r): ?>
    <div class="col-md-6 col-lg-4">
      <a class="card shadow-sm h-100 text-decoration-none text-body type-card" href="<?= url('trip-report.php?id=' . $r['id']) ?>">
        <?php if ($r['cover_id']): ?>
          <img src="<?= url('trip-image.php?id=' . $r['cover_id'] . '&thumb=1') ?>" class="card-img-top" style="aspect-ratio:16/9;object-fit:cover" alt="" loading="lazy">
        <?php else: ?>
          <div class="card-img-top bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-center" style="aspect-ratio:16/9"><i class="bi bi-geo-alt" style="font-size:3rem"></i></div>
        <?php endif; ?>
        <div class="card-body">
          <h6 class="card-title mb-1"><?= e($r['subject']) ?></h6>
          <div class="small text-muted">
            <div><i class="bi bi-pin-map me-1"></i><?= e($r['place']) ?></div>
            <div><i class="bi bi-calendar3 me-1"></i><?= thai_date_range($r['start_date'], $r['end_date']) ?></div>
            <div><i class="bi bi-person me-1"></i><?= e($r['reporter']) ?><?= $r['dept_name'] ? ' · ' . e($r['dept_name']) : '' ?></div>
          </div>
        </div>
        <div class="card-footer bg-white small text-muted d-flex justify-content-between">
          <span><?= $r['doc_no'] ? 'คำสั่งที่ ' . e($r['doc_no']) : '' ?></span>
          <span><i class="bi bi-images"></i> <?= (int)$r['img_count'] ?><?= $r['is_public'] ? '' : ' <i class="bi bi-lock-fill" title="เฉพาะสมาชิก"></i>' ?></span>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
