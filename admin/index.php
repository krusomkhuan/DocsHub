<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

// PDPA: ลบประวัติการใช้งานที่เกินระยะเวลาเก็บรักษา (วันละครั้ง)
pdpa_purge_logs();

// สำรองข้อมูลอัตโนมัติตามรอบที่ตั้งไว้ (ถ้าเปิดใช้)
require_once __DIR__ . '/../includes/backup.php';
try {
    if ($autoBackup = backup_auto_if_due()) {
        flash('info', 'ระบบสำรองข้อมูลอัตโนมัติแล้ว: ' . $autoBackup);
    }
} catch (Throwable $ex) {
    flash('danger', 'สำรองข้อมูลอัตโนมัติไม่สำเร็จ: ' . $ex->getMessage());
}

$byType = db_all("SELECT t.*, COUNT(d.id) AS total, SUM(d.status = 'pending') AS pending, COALESCE(SUM(d.downloads),0) AS downloads
                  FROM doc_types t LEFT JOIN documents d ON d.type_id = t.id GROUP BY t.id ORDER BY t.sort_order");
$stats = [
    ['เอกสารทั้งหมด', (int)db_val('SELECT COUNT(*) FROM documents'), 'bi-files', 'primary'],
    ['รออนุมัติ', (int)db_val("SELECT COUNT(*) FROM documents WHERE status = 'pending'"), 'bi-hourglass-split', 'warning'],
    ['เลขหนังสือปี ' . current_year_be(), (int)db_val('SELECT COUNT(*) FROM book_numbers WHERE year_be = ? AND is_cancelled = 0', 'i', [current_year_be()]), 'bi-123', 'success'],
    ['สมาชิก', (int)db_val("SELECT COUNT(*) FROM users WHERE status = 'active'"), 'bi-people', 'info'],
];
$pending = db_all("SELECT d.id, d.title, d.created_at, t.name AS type_name, u.fullname FROM documents d
                   JOIN doc_types t ON t.id = d.type_id LEFT JOIN users u ON u.id = d.uploaded_by
                   WHERE d.status = 'pending' ORDER BY d.created_at LIMIT 8");
$byDept = db_all('SELECT dp.name, COUNT(b.id) AS c FROM departments dp
                  LEFT JOIN book_numbers b ON b.department_id = dp.id AND b.year_be = ? AND b.is_cancelled = 0
                  GROUP BY dp.id ORDER BY c DESC, dp.sort_order LIMIT 8', 'i', [current_year_be()]);
$maxDept = max(1, ...array_map(fn($r) => (int)$r['c'], $byDept ?: [['c' => 1]]));

$pageTitle = 'แดชบอร์ด';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3 mb-4">
  <?php foreach ($stats as [$label, $val, $icon, $color]): ?>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm stat-card"><div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><i class="bi <?= $icon ?>"></i></div>
        <div><div class="fs-4 fw-semibold"><?= number_format($val) ?></div><div class="small text-muted"><?= e($label) ?></div></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">เอกสารแยกตามประเภท</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead class="table-light"><tr><th>ประเภท</th><th class="text-end">ทั้งหมด</th><th class="text-end">รออนุมัติ</th><th class="text-end">ดาวน์โหลด</th></tr></thead>
        <tbody>
        <?php foreach ($byType as $t): ?>
          <tr>
            <td><i class="bi <?= e($t['icon']) ?> text-<?= e($t['color']) ?> me-2"></i><a href="<?= url('admin/documents.php?type_id=' . $t['id']) ?>"><?= e($t['name']) ?></a></td>
            <td class="text-end"><?= number_format($t['total']) ?></td>
            <td class="text-end"><?= (int)$t['pending'] ? '<span class="badge text-bg-warning">' . (int)$t['pending'] . '</span>' : '0' ?></td>
            <td class="text-end"><?= number_format($t['downloads']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">เอกสารรออนุมัติ</span><a class="small" href="<?= url('admin/documents.php?status=pending') ?>">ดูทั้งหมด</a></div>
      <ul class="list-group list-group-flush">
        <?php if (!$pending): ?><li class="list-group-item text-muted small">ไม่มีเอกสารรออนุมัติ</li><?php endif; ?>
        <?php foreach ($pending as $d): ?>
          <li class="list-group-item d-flex justify-content-between gap-2">
            <div><a href="<?= url('admin/document-form.php?id=' . $d['id']) ?>"><?= e($d['title']) ?></a><div class="small text-muted"><?= e($d['type_name']) ?> · <?= e($d['fullname']) ?> · <?= thai_date($d['created_at'], true, true) ?></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">การออกเลขหนังสือ ปี <?= current_year_be() ?> (แยกตามแผนก)</div>
      <div class="card-body">
        <?php foreach ($byDept as $r): ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span><?= e($r['name']) ?></span><span class="fw-medium"><?= (int)$r['c'] ?></span></div>
            <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= round($r['c'] / $maxDept * 100) ?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
