<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $action = post('action');
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $t = str_repeat('i', count($ids));
        if (in_array($action, ['approved', 'rejected', 'pending'], true)) {
            db_exec("UPDATE documents SET status = ?, updated_at = NOW() WHERE id IN ($ph)", 's' . $t, array_merge([$action], $ids));
            log_activity('doc_status', $action . ' #' . implode(',#', $ids));
            flash('success', 'ปรับสถานะ ' . count($ids) . ' รายการแล้ว');
        } elseif ($action === 'public' || $action === 'private') {
            db_exec("UPDATE documents SET is_public = ? WHERE id IN ($ph)", 'i' . $t, array_merge([$action === 'public' ? 1 : 0], $ids));
            flash('success', 'ปรับการเผยแพร่ ' . count($ids) . ' รายการแล้ว');
        } elseif ($action === 'delete') {
            $files = db_all("SELECT id, title, file_name FROM documents WHERE id IN ($ph)", $t, $ids);
            foreach ($ids as $docId) {
                trip_delete_files_for_document($docId); // ภาพรายงานไปราชการที่ผูกกับเอกสาร
            }
            db_exec("DELETE FROM documents WHERE id IN ($ph)", $t, $ids);
            foreach ($files as $f) {
                delete_stored_file($f['file_name']);
                log_activity('doc_delete', '#' . $f['id'] . ' ' . $f['title']);
            }
            flash('success', 'ลบเอกสาร ' . count($files) . ' รายการแล้ว');
        }
    }
    redirect('admin/documents.php?' . http_build_query($_GET));
}

$f = ['type_id' => get('type_id'), 'dept' => get('dept'), 'status' => get('status'), 'q' => get('q'), 'year' => get('year')];
$where = '1=1';
$types = '';
$params = [];
document_filters($f, $where, $types, $params);
$p = paginate((int)db_val("SELECT COUNT(*) FROM documents d WHERE $where", $types, $params), 25);
$docs = db_all("SELECT d.*, t.name AS type_name, t.color, dp.name AS dept_name, u.fullname AS uploader
                FROM documents d JOIN doc_types t ON t.id = d.type_id
                LEFT JOIN departments dp ON dp.id = d.department_id LEFT JOIN users u ON u.id = d.uploaded_by
                WHERE $where ORDER BY d.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);

$pageTitle = 'จัดการเอกสาร';
$activeMenu = 'documents';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form class="row g-2">
      <div class="col-md-3"><input class="form-control form-control-sm" name="q" placeholder="ค้นหาชื่อ เลขที่ ผู้รับ รหัสวิชา" value="<?= e($f['q']) ?>"></div>
      <div class="col-md-2"><select class="form-select form-select-sm" name="type_id"><option value="">ทุกประเภท</option>
        <?php foreach (doc_types() as $t): ?><option value="<?= $t['id'] ?>"<?= $f['type_id'] == $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><select class="form-select form-select-sm" name="dept"><option value="">ทุกแผนก/ฝ่าย</option>
        <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= $f['dept'] == $dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select form-select-sm" name="status"><option value="">ทุกสถานะ</option>
        <?php foreach (['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'] as $k => $lbl): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> ค้นหา</button>
        <a class="btn btn-sm btn-success" href="<?= url('admin/document-form.php') ?>" title="เพิ่มเอกสาร"><i class="bi bi-plus-lg"></i></a></div>
    </form>
  </div>
</div>

<form method="post" class="card shadow-sm" id="bulkForm" data-confirm="ยืนยันการดำเนินการกับรายการที่เลือก?">
  <?= csrf_field() ?>
  <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
    <span class="small text-muted me-auto">พบ <?= number_format($p['total']) ?> รายการ</span>
    <select class="form-select form-select-sm w-auto" name="action" required>
      <option value="">— ดำเนินการกับที่เลือก —</option>
      <option value="approved">อนุมัติ</option><option value="rejected">ไม่อนุมัติ</option><option value="pending">ตั้งเป็นรออนุมัติ</option>
      <option value="public">เผยแพร่สาธารณะ</option><option value="private">เฉพาะสมาชิก</option>
      <option value="delete">ลบ</option>
    </select>
    <button class="btn btn-sm btn-outline-primary">ตกลง</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr>
        <th style="width:30px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.chk').forEach(c=>c.checked=this.checked)"></th>
        <th>เอกสาร</th><th>ประเภท</th><th>แผนก</th><th>สถานะ</th><th>ผู้อัปโหลด</th><th class="text-end"><i class="bi bi-download"></i></th><th></th>
      </tr></thead>
      <tbody>
      <?php if (!$docs): ?><tr><td colspan="8" class="text-center text-muted py-5">ไม่พบเอกสาร</td></tr><?php endif; ?>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><input type="checkbox" class="form-check-input chk" name="ids[]" value="<?= $d['id'] ?>"></td>
          <td>
            <i class="bi <?= file_icon($d['file_ext']) ?> me-1"></i><a href="<?= url('document.php?id=' . $d['id']) ?>" target="_blank"><?= e($d['title']) ?></a>
            <?= $d['is_public'] ? '' : '<i class="bi bi-lock-fill text-muted small" title="เฉพาะสมาชิก"></i>' ?>
            <div class="small text-muted"><?= $d['doc_no'] ? 'เลขที่ ' . e($d['doc_no']) . ' · ' : '' ?><?= thai_date($d['created_at']) ?> · <?= format_size((int)$d['file_size']) ?></div>
          </td>
          <td><span class="badge text-bg-<?= e($d['color']) ?>"><?= e($d['type_name']) ?></span></td>
          <td class="small"><?= e($d['dept_name']) ?></td>
          <td><?= status_badge($d['status']) ?></td>
          <td class="small"><?= e($d['uploader']) ?></td>
          <td class="text-end small"><?= number_format($d['downloads']) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/document-form.php?id=' . $d['id']) ?>"><i class="bi bi-pencil"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</form>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
