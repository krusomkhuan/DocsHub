<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $name = mb_substr(post('name'), 0, 150);
    $typeId = (int)post('type_id');
    $sort = (int)post('sort_order');
    $trip = post('has_trip_report') === '1' ? 1 : 0;
    if ($action === 'save') {
        if ($name === '' || !isset(doc_types()[$typeId])) {
            flash('danger', 'กรุณากรอกชื่อหมวดหมู่และเลือกประเภท');
        } elseif ((int)post('id')) {
            db_exec('UPDATE categories SET type_id = ?, name = ?, sort_order = ?, has_trip_report = ? WHERE id = ?', 'isiii', [$typeId, $name, $sort, $trip, (int)post('id')]);
            flash('success', 'แก้ไขหมวดหมู่แล้ว');
        } else {
            db_exec('INSERT INTO categories (type_id, name, sort_order, has_trip_report) VALUES (?,?,?,?)', 'isii', [$typeId, $name, $sort, $trip]);
            flash('success', 'เพิ่มหมวดหมู่แล้ว');
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM categories WHERE id = ?', 'i', [(int)post('id')]);
        flash('success', 'ลบหมวดหมู่แล้ว (เอกสารในหมวดนี้จะไม่ถูกลบ)');
    }
    redirect('admin/categories.php');
}

$counts = [];
foreach (db_all('SELECT category_id, COUNT(*) c FROM documents WHERE category_id IS NOT NULL GROUP BY category_id') as $r) {
    $counts[$r['category_id']] = (int)$r['c'];
}
$grouped = [];
foreach (categories() as $c) {
    $grouped[$c['type_id']][] = $c;
}
$edit = get('edit') ? db_row('SELECT * FROM categories WHERE id = ?', 'i', [(int)get('edit')]) : null;

$pageTitle = 'หมวดหมู่เอกสาร';
$activeMenu = 'categories';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><?= $edit ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' ?></div>
      <form method="post" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">ประเภทเอกสาร</label>
          <select class="form-select" name="type_id" required>
            <?php foreach (doc_types() as $t): ?><option value="<?= $t['id'] ?>"<?= ($edit['type_id'] ?? get('type')) == $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-2"><label class="form-label">ชื่อหมวดหมู่</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="has_trip_report" value="1" id="htr"<?= !empty($edit['has_trip_report']) ? ' checked' : '' ?>><label class="form-check-label" for="htr">เปิดใช้รายงานการไปราชการ (แนบภาพประกอบได้)</label></div>
        <div class="mb-3"><label class="form-label">ลำดับ</label><input type="number" class="form-control" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></div>
        <button class="btn btn-primary">บันทึก</button>
        <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= url('admin/categories.php') ?>">ยกเลิก</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <?php foreach (doc_types() as $t): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><i class="bi <?= e($t['icon']) ?> text-<?= e($t['color']) ?> me-2"></i><strong><?= e($t['name']) ?></strong></div>
        <ul class="list-group list-group-flush">
          <?php if (empty($grouped[$t['id']])): ?><li class="list-group-item small text-muted">ยังไม่มีหมวดหมู่</li><?php endif; ?>
          <?php foreach ($grouped[$t['id']] ?? [] as $c): ?>
            <li class="list-group-item d-flex align-items-center gap-2">
              <span class="me-auto"><?= e($c['name']) ?> <span class="badge text-bg-light"><?= $counts[$c['id']] ?? 0 ?> เอกสาร</span><?= $c['has_trip_report'] ? ' <span class="badge text-bg-info"><i class="bi bi-geo-alt"></i> รายงานไปราชการ</span>' : '' ?></span>
              <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $c['id'] ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" data-confirm="ลบหมวดหมู่ '<?= e($c['name']) ?>'?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
