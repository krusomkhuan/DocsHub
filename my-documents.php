<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$uid = (int)current_user()['id'];

// ลบเอกสารของตนเองที่ยังไม่อนุมัติ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    $doc = db_row('SELECT * FROM documents WHERE id = ? AND uploaded_by = ?', 'ii', [(int)post('id'), $uid]);
    if ($doc && can_edit_document($doc)) {
        trip_delete_files_for_document((int)$doc['id']);
        db_exec('DELETE FROM documents WHERE id = ?', 'i', [$doc['id']]);
        delete_stored_file($doc['file_name']);
        log_activity('doc_delete', '#' . $doc['id'] . ' ' . $doc['title']);
        flash('success', 'ลบเอกสารเรียบร้อยแล้ว');
    }
    redirect('my-documents.php');
}

$where = 'd.uploaded_by = ?';
$types = 'i';
$params = [$uid];
document_filters(['q' => get('q'), 'status' => get('status')], $where, $types, $params);
$p = paginate((int)db_val("SELECT COUNT(*) FROM documents d WHERE $where", $types, $params), 20);
$docs = db_all("SELECT d.*, t.name AS type_name, t.color FROM documents d JOIN doc_types t ON t.id = d.type_id
                WHERE $where ORDER BY d.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);

$pageTitle = 'เอกสารของฉัน';
$activeNav = 'mydocs';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-folder-check me-2"></i>เอกสารของฉัน</h4>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2">
      <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
        <option value="">ทุกสถานะ</option>
        <?php foreach (['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'] as $k => $lbl): ?>
          <option value="<?= $k ?>"<?= get('status') === $k ? ' selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <input class="form-control form-control-sm" name="q" placeholder="ค้นหา" value="<?= e(get('q')) ?>">
    </form>
    <a class="btn btn-primary btn-sm text-nowrap" href="<?= url('upload.php') ?>"><i class="bi bi-plus-lg"></i> อัปโหลด</a>
  </div>
</div>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>เอกสาร</th><th>ประเภท</th><th>สถานะ</th><th>วันที่อัปโหลด</th><th class="text-end">จัดการ</th></tr></thead>
      <tbody>
        <?php if (!$docs): ?><tr><td colspan="5" class="text-center text-muted py-5">ยังไม่มีเอกสาร</td></tr><?php endif; ?>
        <?php foreach ($docs as $d): ?>
          <tr>
            <td><i class="bi <?= file_icon($d['file_ext']) ?> me-1"></i><a href="<?= url('document.php?id=' . $d['id']) ?>"><?= e($d['title']) ?></a></td>
            <td><span class="badge text-bg-<?= e($d['color']) ?>"><?= e($d['type_name']) ?></span></td>
            <td><?= status_badge($d['status']) ?></td>
            <td class="small"><?= thai_date($d['created_at'], true, true) ?></td>
            <td class="text-end text-nowrap">
              <?php if (can_edit_document($d)): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('upload.php?id=' . $d['id']) ?>"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" data-confirm="ยืนยันการลบเอกสารนี้?">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
