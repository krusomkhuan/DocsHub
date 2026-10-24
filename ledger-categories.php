<?php
require_once __DIR__ . '/includes/bootstrap.php';
$dept = require_ledger_access();
$deptId = (int)$dept['id'];
$kinds = ledger_kinds();
$perms = ledger_perms($dept);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$perms['manage']) {
        http_response_code(403);
        exit('เฉพาะผู้รับผิดชอบบัญชีเท่านั้น');
    }
    $id = (int)post('id');
    if (post('action') === 'save') {
        $name = mb_substr(post('name'), 0, 150);
        $kind = post('kind');
        if ($name === '' || !isset($kinds[$kind])) {
            flash('danger', 'กรุณากรอกชื่อหมวดและเลือกประเภท');
        } elseif ($id) {
            db_exec('UPDATE ledger_categories SET name = ?, sort_order = ? WHERE id = ? AND department_id = ?', 'siii', [$name, (int)post('sort_order'), $id, $deptId]);
            flash('success', 'แก้ไขหมวดแล้ว');
        } else {
            db_exec('INSERT INTO ledger_categories (department_id, kind, name, sort_order) VALUES (?,?,?,?)', 'issi', [$deptId, $kind, $name, (int)post('sort_order')]);
            flash('success', 'เพิ่มหมวดแล้ว');
        }
    } elseif (post('action') === 'delete') {
        $used = (int)db_val('SELECT COUNT(*) FROM ledger_entries WHERE category_id = ? AND department_id = ?', 'ii', [$id, $deptId]);
        if ($used) {
            flash('danger', 'ลบไม่ได้ เนื่องจากมีรายการในหมวดนี้ ' . $used . ' รายการ');
        } else {
            db_exec('DELETE FROM ledger_categories WHERE id = ? AND department_id = ?', 'ii', [$id, $deptId]);
            flash('success', 'ลบหมวดแล้ว');
        }
    }
    redirect('ledger-categories.php');
}

$cats = ledger_categories($deptId);
$counts = array_column(db_all('SELECT category_id, COUNT(*) c FROM ledger_entries WHERE department_id = ? AND category_id IS NOT NULL GROUP BY category_id', 'i', [$deptId]), 'c', 'category_id');
$edit = null;
foreach ($cats as $c) {
    if ((int)$c['id'] === (int)get('edit')) $edit = $c;
}

$managers = ledger_managers($deptId);
$pageTitle = 'ตั้งค่าบัญชีแผนก';
$activeNav = 'ledger';
require __DIR__ . '/includes/header.php';
$ledgerTab = 'settings';
require __DIR__ . '/includes/ledger_nav.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('ledger.php') ?>">บัญชี<?= e($dept['name']) ?></a></li>
  <li class="breadcrumb-item active">ตั้งค่าบัญชีแผนก</li>
</ol></nav>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>ผู้รับผิดชอบบัญชี</div>
      <div class="card-body small">
        <?php if ($managers): ?>
          <?php foreach ($managers as $m): ?><div class="d-flex align-items-center gap-2 mb-2"><?= avatar_html($m, 28) ?><?= e($m['fullname']) ?></div><?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted">ยังไม่ได้กำหนด — ครูทุกคนในแผนกบันทึก แก้ไข และจัดการหมวดได้</div>
        <?php endif; ?>
        <div class="text-muted mt-2">ผู้ดูแลระบบเป็นผู้กำหนดผู้รับผิดชอบบัญชี</div>
        <hr>
        <div class="text-muted"><i class="bi bi-check-circle text-success me-1"></i>ครูทุกคนในแผนก<strong>บันทึก</strong>และ<strong>แก้ไข</strong>รายรับ-รายจ่ายได้ทุกรายการ (แก้ไขรายการเก่าที่บันทึกผิดได้ ระบบเก็บชื่อผู้แก้ไขล่าสุด) · ลบได้เฉพาะผู้บันทึกรายการและผู้รับผิดชอบบัญชี</div>
      </div>
    </div>
    <?php if ($perms['manage']): ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><?= $edit ? 'แก้ไขหมวด' : 'เพิ่มหมวด' ?></div>
      <form method="post" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">ประเภท</label>
          <select class="form-select" name="kind"<?= $edit ? ' disabled' : '' ?>>
            <?php foreach ($kinds as $k => $lbl): ?><option value="<?= $k ?>"<?= ($edit['kind'] ?? '') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
          </select>
          <?php if ($edit): ?><input type="hidden" name="kind" value="<?= e($edit['kind']) ?>"><?php endif; ?></div>
        <div class="mb-2"><label class="form-label">ชื่อหมวด</label><input class="form-control" name="name" required maxlength="150" value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">ลำดับ</label><input type="number" class="form-control" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></div>
        <button class="btn btn-primary">บันทึก</button>
        <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= url('ledger-categories.php') ?>">ยกเลิก</a><?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-lg-8">
    <?php foreach ($kinds as $k => $lbl): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold text-<?= $k === 'income' ? 'success' : 'danger' ?>"><?= $lbl ?></div>
        <ul class="list-group list-group-flush">
          <?php foreach (array_filter($cats, fn($c) => $c['kind'] === $k) as $c): ?>
            <li class="list-group-item d-flex align-items-center gap-2">
              <span class="me-auto"><?= e($c['name']) ?> <span class="badge text-bg-light"><?= (int)($counts[$c['id']] ?? 0) ?> รายการ</span></span>
              <?php if ($perms['manage']): ?>
              <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $c['id'] ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" data-confirm="ลบหมวด '<?= e($c['name']) ?>'?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"<?= !empty($counts[$c['id']]) ? ' disabled' : '' ?>><i class="bi bi-trash"></i></button></form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
