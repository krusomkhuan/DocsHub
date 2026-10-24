<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'save') {
        $year = post('year_be');
        $start = post('start_date') ?: null;
        $end = post('end_date') ?: null;
        if (!preg_match('/^25[0-9]{2}$/', $year) && !preg_match('/^26[0-9]{2}$/', $year)) {
            flash('danger', 'ปีการศึกษาต้องเป็นปี พ.ศ. 4 หลัก เช่น ' . current_academic_year());
        } elseif ($start && $end && $end < $start) {
            flash('danger', 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มต้น');
        } elseif (db_val('SELECT id FROM academic_years WHERE year_be = ? AND id <> ?', 'si', [$year, $id])) {
            flash('danger', 'มีปีการศึกษา ' . $year . ' อยู่แล้ว');
        } else {
            if ($id) {
                $old = db_val('SELECT year_be FROM academic_years WHERE id = ?', 'i', [$id]);
                db_exec('UPDATE academic_years SET year_be = ?, start_date = ?, end_date = ? WHERE id = ?', 'sssi', [$year, $start, $end, $id]);
                // เปลี่ยนเลขปี: ปรับเอกสารที่ใช้ปีเดิมให้ตรงกัน
                if ($old !== null && $old !== $year) {
                    db_exec('UPDATE documents SET academic_year = ? WHERE academic_year = ?', 'ss', [$year, $old]);
                }
            } else {
                $id = db_exec('INSERT INTO academic_years (year_be, start_date, end_date) VALUES (?,?,?)', 'sss', [$year, $start, $end]);
            }
            if (post('is_current') === '1') {
                db_exec('UPDATE academic_years SET is_current = (id = ?)', 'i', [$id]);
            }
            log_activity('year_save', $year);
            flash('success', 'บันทึกปีการศึกษา ' . $year . ' แล้ว');
        }
    } elseif ($action === 'current') {
        db_exec('UPDATE academic_years SET is_current = (id = ?)', 'i', [$id]);
        log_activity('year_current', (string)db_val('SELECT year_be FROM academic_years WHERE id = ?', 'i', [$id]));
        flash('success', 'ตั้งเป็นปีการศึกษาปัจจุบันแล้ว');
    } elseif ($action === 'delete') {
        $y = db_row('SELECT * FROM academic_years WHERE id = ?', 'i', [$id]);
        if ($y && $y['is_current']) {
            flash('danger', 'ลบปีการศึกษาปัจจุบันไม่ได้ กรุณาตั้งปีอื่นเป็นปีปัจจุบันก่อน');
        } elseif ($y) {
            db_exec('DELETE FROM academic_years WHERE id = ?', 'i', [$id]);
            log_activity('year_delete', $y['year_be']);
            flash('success', 'ลบปีการศึกษา ' . $y['year_be'] . ' แล้ว (เอกสารที่ระบุปีนี้ยังคงอยู่)');
        }
    }
    redirect('admin/academic-years.php');
}

$rows = db_all('SELECT y.*, (SELECT COUNT(*) FROM documents d WHERE d.academic_year = y.year_be) AS docs
                FROM academic_years y ORDER BY y.year_be DESC');
$edit = get('edit') ? db_row('SELECT * FROM academic_years WHERE id = ?', 'i', [(int)get('edit')]) : null;
$suggest = $rows ? (int)$rows[0]['year_be'] + 1 : current_academic_year();

$pageTitle = 'ปีการศึกษา';
$activeMenu = 'years';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><?= $edit ? 'แก้ไขปีการศึกษา ' . e($edit['year_be']) : 'เพิ่มปีการศึกษา' ?></div>
      <form method="post" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">ปีการศึกษา (พ.ศ.) *</label>
          <input type="number" class="form-control" name="year_be" required min="2500" max="2699" value="<?= e($edit['year_be'] ?? $suggest) ?>"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">วันเปิดภาคเรียน</label><input type="date" class="form-control form-control-sm" name="start_date" value="<?= e($edit['start_date'] ?? '') ?>"></div>
          <div class="col-6"><label class="form-label small">วันสิ้นปีการศึกษา</label><input type="date" class="form-control form-control-sm" name="end_date" value="<?= e($edit['end_date'] ?? '') ?>"></div>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="is_current" value="1" id="cur"<?= !empty($edit['is_current']) ? ' checked' : '' ?>>
          <label class="form-check-label" for="cur">ตั้งเป็นปีการศึกษาปัจจุบัน</label>
        </div>
        <button class="btn btn-primary">บันทึก</button>
        <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= url('admin/academic-years.php') ?>">ยกเลิก</a><?php endif; ?>
        <div class="form-text mt-2">ปีการศึกษาปัจจุบันจะถูกเลือกไว้ให้อัตโนมัติในฟอร์มอัปโหลดเอกสาร และใช้เป็นตัวกรองในหน้าค้นหาเอกสาร</div>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>ปีการศึกษา</th><th>ช่วงเวลา</th><th class="text-end">เอกสาร</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีปีการศึกษา</td></tr><?php endif; ?>
        <?php foreach ($rows as $y): ?>
          <tr class="<?= $y['is_current'] ? 'table-primary' : '' ?>">
            <td class="fw-medium"><?= e($y['year_be']) ?> <?= $y['is_current'] ? '<span class="badge text-bg-primary">ปีปัจจุบัน</span>' : '' ?></td>
            <td class="small"><?= $y['start_date'] ? thai_date($y['start_date']) . ' – ' . thai_date($y['end_date']) : '<span class="text-muted">-</span>' ?></td>
            <td class="text-end"><a href="<?= url('admin/documents.php?year=' . urlencode($y['year_be'])) ?>"><?= (int)$y['docs'] ?></a></td>
            <td class="text-end text-nowrap">
              <?php if (!$y['is_current']): ?>
                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="current"><input type="hidden" name="id" value="<?= $y['id'] ?>">
                  <button class="btn btn-sm btn-outline-primary" title="ตั้งเป็นปีปัจจุบัน"><i class="bi bi-star"></i></button></form>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $y['id'] ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" data-confirm="ลบปีการศึกษา <?= e($y['year_be']) ?>? (เอกสารที่ระบุปีนี้จะไม่ถูกลบ)"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $y['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"<?= $y['is_current'] ? ' disabled' : '' ?>><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
