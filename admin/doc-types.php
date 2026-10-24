<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$icons = [
    'bi-file-earmark' => 'เอกสาร', 'bi-file-earmark-ruled' => 'คำสั่ง', 'bi-file-earmark-text' => 'ข้อความ',
    'bi-award' => 'รางวัล', 'bi-patch-check' => 'รับรอง', 'bi-journal-text' => 'สมุด', 'bi-journal-bookmark' => 'บันทึก',
    'bi-book' => 'หนังสือ', 'bi-diagram-3' => 'แผนผัง', 'bi-clipboard-check' => 'ตรวจสอบ', 'bi-calendar-event' => 'ปฏิทิน',
    'bi-megaphone' => 'ประกาศ', 'bi-envelope-paper' => 'จดหมาย', 'bi-briefcase' => 'งาน', 'bi-people' => 'บุคคล',
    'bi-mortarboard' => 'การศึกษา', 'bi-graph-up' => 'รายงาน', 'bi-cash-coin' => 'การเงิน', 'bi-image' => 'รูปภาพ',
    'bi-folder' => 'แฟ้ม', 'bi-archive' => 'จัดเก็บ', 'bi-trophy' => 'ถ้วยรางวัล', 'bi-tools' => 'เครื่องมือ', 'bi-shield-check' => 'ความปลอดภัย',
];
$colors = ['primary' => 'น้ำเงิน', 'success' => 'เขียว', 'warning' => 'เหลือง', 'danger' => 'แดง', 'info' => 'ฟ้า', 'secondary' => 'เทา', 'dark' => 'ดำ'];

$errors = [];
$edit = null;
if (get('edit') !== '') {
    $edit = get('edit') === 'new' ? [] : db_row('SELECT * FROM doc_types WHERE id = ?', 'i', [(int)get('edit')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');

    if (post('action') === 'delete') {
        $t = db_row('SELECT * FROM doc_types WHERE id = ?', 'i', [$id]);
        $used = (int)db_val('SELECT COUNT(*) FROM documents WHERE type_id = ?', 'i', [$id]);
        if (!$t) {
            flash('danger', 'ไม่พบประเภทเอกสาร');
        } elseif ($used) {
            flash('danger', 'ลบ "' . $t['name'] . '" ไม่ได้ เนื่องจากมีเอกสาร ' . $used . ' รายการ กรุณาย้ายหรือลบเอกสารก่อน');
        } else {
            db_exec('DELETE FROM doc_types WHERE id = ?', 'i', [$id]); // หมวดหมู่ย่อยถูกลบตาม (CASCADE)
            log_activity('doctype_delete', $t['name']);
            flash('success', 'ลบประเภท "' . $t['name'] . '" แล้ว');
        }
        redirect('admin/doc-types.php');
    }

    if (post('action') === 'save') {
        $data = [
            'name'         => mb_substr(post('name'), 0, 100),
            'slug'         => strtolower(post('slug')),
            'icon'         => isset($icons[post('icon')]) ? post('icon') : 'bi-file-earmark',
            'color'        => isset($colors[post('color')]) ? post('color') : 'primary',
            'description'  => mb_substr(post('description'), 0, 255) ?: null,
            'sort_order'   => (int)post('sort_order'),
            'has_subject'  => post('has_subject') === '1' ? 1 : 0,
            'has_semester' => post('has_semester') === '1' ? 1 : 0,
            'has_order_no' => post('has_order_no') === '1' ? 1 : 0,
            'owner_label'  => mb_substr(post('owner_label'), 0, 100) ?: 'ผู้รับผิดชอบ',
        ];
        if ($data['slug'] === '') {
            $data['slug'] = $id ? (string)db_val('SELECT slug FROM doc_types WHERE id = ?', 'i', [$id]) : 'type-' . date('ymdHis');
        }
        if ($data['name'] === '') $errors[] = 'กรุณากรอกชื่อประเภทเอกสาร';
        if (!preg_match('/^[a-z0-9-]{2,50}$/', $data['slug'])) $errors[] = 'ชื่อใน URL ต้องเป็น a-z, 0-9 หรือ - ยาว 2-50 ตัวอักษร';
        elseif (db_val('SELECT id FROM doc_types WHERE slug = ? AND id <> ?', 'si', [$data['slug'], $id])) $errors[] = 'ชื่อใน URL นี้ถูกใช้แล้ว';

        if (!$errors) {
            $types = 'sssssiiiis';
            if ($id) {
                db_exec('UPDATE doc_types SET name=?, slug=?, icon=?, color=?, description=?, sort_order=?, has_subject=?, has_semester=?, has_order_no=?, owner_label=? WHERE id=?',
                    $types . 'i', array_merge(array_values($data), [$id]));
                log_activity('doctype_update', $data['name']);
            } else {
                $id = db_exec('INSERT INTO doc_types (name, slug, icon, color, description, sort_order, has_subject, has_semester, has_order_no, owner_label) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    $types, array_values($data));
                log_activity('doctype_create', $data['name']);
            }
            flash('success', 'บันทึกประเภท "' . $data['name'] . '" แล้ว · เพิ่มหมวดหมู่ย่อยได้ที่เมนูหมวดหมู่เอกสาร');
            redirect('admin/doc-types.php');
        }
        $edit = array_merge(['id' => $id], $data);
    }
}

$rows = db_all('SELECT t.*, (SELECT COUNT(*) FROM documents d WHERE d.type_id = t.id) AS docs,
                       (SELECT COUNT(*) FROM categories c WHERE c.type_id = t.id) AS cats
                FROM doc_types t ORDER BY t.sort_order, t.id');

$pageTitle = 'ประเภทเอกสาร';
$activeMenu = 'doctypes';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($edit !== null): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold"><?= !empty($edit['id']) ? 'แก้ไขประเภท: ' . e($edit['name']) : 'เพิ่มประเภทเอกสารใหม่' ?></div>
  <div class="card-body">
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="row g-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="col-md-5"><label class="form-label">ชื่อประเภทเอกสาร *</label><input class="form-control" name="name" required maxlength="100" value="<?= e($edit['name'] ?? '') ?>" placeholder="เช่น รายงานการประชุม"></div>
      <div class="col-md-4"><label class="form-label">ชื่อใน URL (ภาษาอังกฤษ)</label><input class="form-control" name="slug" maxlength="50" pattern="[a-z0-9\-]{2,50}" value="<?= e($edit['slug'] ?? '') ?>" placeholder="เช่น meeting-reports">
        <div class="form-text">a-z, 0-9, - · เว้นว่างให้ระบบตั้งให้</div></div>
      <div class="col-md-3"><label class="form-label">ลำดับการแสดง</label><input type="number" class="form-control" name="sort_order" value="<?= (int)($edit['sort_order'] ?? (count($rows) + 1)) ?>"></div>
      <div class="col-12"><label class="form-label">คำอธิบาย</label><input class="form-control" name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>"></div>
      <div class="col-md-6">
        <label class="form-label">ไอคอน</label>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($icons as $ic => $lbl): ?>
            <input type="radio" class="btn-check" name="icon" value="<?= $ic ?>" id="ic_<?= $ic ?>"<?= ($edit['icon'] ?? 'bi-file-earmark') === $ic ? ' checked' : '' ?>>
            <label class="btn btn-outline-secondary btn-sm" for="ic_<?= $ic ?>" title="<?= $lbl ?>"><i class="bi <?= $ic ?> fs-5"></i></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">สี</label>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($colors as $c => $lbl): ?>
            <input type="radio" class="btn-check" name="color" value="<?= $c ?>" id="co_<?= $c ?>"<?= ($edit['color'] ?? 'primary') === $c ? ' checked' : '' ?>>
            <label class="btn btn-outline-<?= $c ?> btn-sm" for="co_<?= $c ?>"><?= $lbl ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-12"><hr class="my-1"><h6 class="mb-0">ช่องข้อมูลในฟอร์มอัปโหลด</h6></div>
      <div class="col-md-4"><label class="form-label">ชื่อช่อง "บุคคล"</label><input class="form-control" name="owner_label" maxlength="100" value="<?= e($edit['owner_label'] ?? 'ผู้รับผิดชอบ') ?>">
        <div class="form-text">เช่น ผู้รับ, ผู้สอน, ผู้แต่ง, ผู้จัดทำ</div></div>
      <div class="col-md-8 d-flex flex-column justify-content-center gap-1">
        <?php foreach (['has_subject' => 'แสดงช่องรหัสวิชา / ชื่อวิชา', 'has_semester' => 'แสดงช่องภาคเรียน', 'has_order_no' => 'ให้ออกเลขคำสั่งอัตโนมัติจากทะเบียนเลขหนังสือได้'] as $k => $lbl): ?>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="<?= $k ?>" value="1" id="f_<?= $k ?>"<?= !empty($edit[$k]) ? ' checked' : '' ?>>
            <label class="form-check-label" for="f_<?= $k ?>"><?= $lbl ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="col-12"><button class="btn btn-primary">บันทึก</button> <a class="btn btn-outline-secondary" href="<?= url('admin/doc-types.php') ?>">ยกเลิก</a></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">ประเภทเอกสารทั้งหมด (<?= count($rows) ?>)</span>
    <a class="btn btn-sm btn-success" href="?edit=new"><i class="bi bi-plus-lg"></i> เพิ่มประเภทเอกสาร</a>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>ลำดับ</th><th>ประเภท</th><th>ช่องข้อมูลเพิ่มเติม</th><th class="text-end">หมวดหมู่</th><th class="text-end">เอกสาร</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><?= (int)$t['sort_order'] ?></td>
        <td>
          <span class="type-icon bg-<?= e($t['color']) ?>-subtle text-<?= e($t['color']) ?>-emphasis me-2" style="width:36px;height:36px;font-size:1.1rem;border-radius:10px"><i class="bi <?= e($t['icon']) ?>"></i></span>
          <strong><?= e($t['name']) ?></strong> <code class="small">/<?= e($t['slug']) ?></code>
          <?php if ($t['description']): ?><div class="small text-muted"><?= e($t['description']) ?></div><?php endif; ?>
        </td>
        <td class="small">
          <span class="badge text-bg-light"><?= e($t['owner_label']) ?></span>
          <?= $t['has_subject'] ? '<span class="badge text-bg-light">วิชา</span>' : '' ?>
          <?= $t['has_semester'] ? '<span class="badge text-bg-light">ภาคเรียน</span>' : '' ?>
          <?= $t['has_order_no'] ? '<span class="badge text-bg-light">ออกเลขคำสั่ง</span>' : '' ?>
        </td>
        <td class="text-end"><a href="<?= url('admin/categories.php?type=' . $t['id']) ?>"><?= (int)$t['cats'] ?></a></td>
        <td class="text-end"><?= (int)$t['docs'] ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $t['id'] ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="ลบประเภท '<?= e($t['name']) ?>' และหมวดหมู่ย่อยทั้งหมด?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"<?= $t['docs'] ? ' disabled title="มีเอกสารอยู่ ลบไม่ได้"' : '' ?>><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
