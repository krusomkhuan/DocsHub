<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    $r = trip_get((int)post('id'));
    if ($r) {
        trip_delete_report_files((int)$r['id']);
        db_exec('DELETE FROM trip_reports WHERE id = ?', 'i', [$r['id']]);
        log_activity('trip_delete', '#' . $r['id'] . ' ' . $r['subject']);
        flash('success', 'ลบรายงานแล้ว');
    }
    redirect('admin/trip-reports.php?' . http_build_query($_GET));
}

$where = '1=1';
$types = '';
$params = [];
if (get('dept') !== '') { $where .= ' AND d.department_id = ?'; $types .= 'i'; $params[] = (int)get('dept'); }
if (get('q') !== '') {
    $where .= ' AND (r.subject LIKE ? OR r.place LIKE ? OR r.travelers LIKE ? OR d.doc_no LIKE ?)';
    $like = '%' . get('q') . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
$from = 'FROM trip_reports r JOIN documents d ON d.id = r.document_id';
$p = paginate((int)db_val("SELECT COUNT(*) $from WHERE $where", $types, $params), 25);
$rows = db_all("SELECT r.*, d.doc_no, d.title AS doc_title, dp.name AS dept_name, u.fullname AS reporter,
                       (SELECT COUNT(*) FROM trip_report_images i WHERE i.report_id = r.id) AS img_count,
                       (SELECT COALESCE(SUM(i.file_size),0) FROM trip_report_images i WHERE i.report_id = r.id) AS img_size
                $from LEFT JOIN departments dp ON dp.id = d.department_id LEFT JOIN users u ON u.id = r.created_by
                WHERE $where ORDER BY r.start_date DESC, r.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$pendingOrders = trip_category_ids() ? (int)db_val('SELECT COUNT(*) FROM documents d WHERE d.category_id IN (' . implode(',', trip_category_ids()) . ')
                 AND NOT EXISTS (SELECT 1 FROM trip_reports r WHERE r.document_id = d.id)') : 0;

$pageTitle = 'รายงานการไปราชการ';
$activeMenu = 'trip';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($pendingOrders): ?>
  <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>มีคำสั่งไปราชการ <?= $pendingOrders ?> ฉบับที่ยังไม่มีรายงานการไปราชการ</div>
<?php endif; ?>
<div class="card shadow-sm">
  <div class="card-header bg-white">
    <form class="row g-2">
      <div class="col-md-5"><input class="form-control form-control-sm" name="q" placeholder="ค้นหาเรื่อง สถานที่ ผู้ไปราชการ เลขที่คำสั่ง" value="<?= e(get('q')) ?>"></div>
      <div class="col-md-3"><select class="form-select form-select-sm" name="dept"><option value="">ทุกแผนก/ฝ่าย</option>
        <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= get('dept') == $dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">ค้นหา</button></div>
      <div class="col-md-2"><a class="btn btn-sm btn-success w-100" href="<?= url('trip-report-form.php') ?>"><i class="bi bi-plus-lg"></i> เขียนรายงาน</a></div>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>เรื่อง</th><th>วันที่</th><th>ตามคำสั่ง</th><th>ผู้รายงาน</th><th class="text-end">ภาพ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">ยังไม่มีรายงาน</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= url('trip-report.php?id=' . $r['id']) ?>" target="_blank"><?= e($r['subject']) ?></a><?= $r['is_public'] ? '' : ' <i class="bi bi-lock-fill text-muted small"></i>' ?><div class="small text-muted"><?= e($r['place']) ?></div></td>
        <td class="small text-nowrap"><?= thai_date($r['start_date']) ?><?= $r['end_date'] !== $r['start_date'] ? '<br>– ' . thai_date($r['end_date']) : '' ?></td>
        <td class="small"><a href="<?= url('admin/document-form.php?id=' . $r['document_id']) ?>"><?= e($r['doc_no'] ?: $r['doc_title']) ?></a></td>
        <td class="small"><?= e($r['reporter']) ?><div class="text-muted"><?= e($r['dept_name']) ?></div></td>
        <td class="text-end small"><?= (int)$r['img_count'] ?><div class="text-muted"><?= format_size((int)$r['img_size']) ?></div></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="<?= url('trip-report-form.php?id=' . $r['id']) ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="ลบรายงานนี้พร้อมภาพประกอบทั้งหมด?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
