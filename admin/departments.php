<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'save') {
        $code = mb_substr(post('code'), 0, 30);
        $name = mb_substr(post('name'), 0, 150);
        $sort = (int)post('sort_order');
        if ($code === '' || $name === '') {
            flash('danger', 'กรุณากรอกรหัสและชื่อแผนก');
        } elseif (db_val('SELECT id FROM departments WHERE code = ? AND id <> ?', 'si', [$code, $id])) {
            flash('danger', 'รหัส ' . $code . ' ถูกใช้แล้ว');
        } elseif ($id) {
            db_exec('UPDATE departments SET code = ?, name = ?, sort_order = ? WHERE id = ?', 'ssii', [$code, $name, $sort, $id]);
            flash('success', 'แก้ไขแผนกแล้ว');
        } else {
            db_exec('INSERT INTO departments (code, name, sort_order) VALUES (?,?,?)', 'ssi', [$code, $name, $sort]);
            flash('success', 'เพิ่มแผนกแล้ว');
        }
    } elseif (post('action') === 'delete') {
        if (db_val('SELECT COUNT(*) FROM book_numbers WHERE department_id = ?', 'i', [$id])) {
            flash('danger', 'ไม่สามารถลบได้ เนื่องจากแผนกนี้มีทะเบียนเลขหนังสือแล้ว');
        } elseif (db_val('SELECT COUNT(*) FROM ledger_entries WHERE department_id = ?', 'i', [$id])) {
            flash('danger', 'ไม่สามารถลบได้ เนื่องจากแผนกนี้มีข้อมูลบัญชีรายรับ-รายจ่าย');
        } else {
            db_exec('DELETE FROM departments WHERE id = ?', 'i', [$id]);
            flash('success', 'ลบแผนกแล้ว');
        }
    }
    redirect('admin/departments.php');
}

$rows = db_all('SELECT dp.*, (SELECT COUNT(*) FROM users u WHERE u.department_id = dp.id) AS users,
                       (SELECT COUNT(*) FROM documents d WHERE d.department_id = dp.id) AS docs,
                       (SELECT COUNT(*) FROM book_numbers b WHERE b.department_id = dp.id) AS books
                FROM departments dp ORDER BY dp.sort_order, dp.name');
$edit = get('edit') ? db_row('SELECT * FROM departments WHERE id = ?', 'i', [(int)get('edit')]) : null;

$pageTitle = 'แผนกวิชา / ฝ่าย';
$activeMenu = 'departments';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><?= $edit ? 'แก้ไขแผนก' : 'เพิ่มแผนก / ฝ่าย' ?></div>
      <form method="post" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">รหัสย่อ (ใช้ในเลขหนังสือ)</label><input class="form-control" name="code" required maxlength="30" value="<?= e($edit['code'] ?? '') ?>" placeholder="เช่น ชฟ"></div>
        <div class="mb-2"><label class="form-label">ชื่อแผนก / ฝ่าย</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">ลำดับ</label><input type="number" class="form-control" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></div>
        <button class="btn btn-primary">บันทึก</button>
        <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= url('admin/departments.php') ?>">ยกเลิก</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>รหัส</th><th>ชื่อ</th><th class="text-end">สมาชิก</th><th class="text-end">เอกสาร</th><th class="text-end">เลขหนังสือ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><span class="badge text-bg-secondary"><?= e($r['code']) ?></span></td>
            <td><?= e($r['name']) ?></td>
            <td class="text-end"><?= (int)$r['users'] ?></td>
            <td class="text-end"><?= (int)$r['docs'] ?></td>
            <td class="text-end"><?= (int)$r['books'] ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" data-confirm="ลบ '<?= e($r['name']) ?>'?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
