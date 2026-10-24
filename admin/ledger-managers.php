<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $deptId = (int)post('department_id');
    $userId = (int)post('user_id');
    $dept = departments()[$deptId] ?? null;
    $user = db_row('SELECT id, fullname, department_id FROM users WHERE id = ?', 'i', [$userId]);
    if (!$dept || !$user) {
        flash('danger', 'ข้อมูลไม่ถูกต้อง');
    } elseif (post('action') === 'add') {
        if ((int)$user['department_id'] !== $deptId) {
            flash('danger', $user['fullname'] . ' ไม่ได้สังกัด' . $dept['name']);
        } else {
            db_exec('INSERT IGNORE INTO ledger_managers (department_id, user_id, assigned_by) VALUES (?,?,?)', 'iii', [$deptId, $userId, current_user()['id']]);
            log_activity('ledger_manager_add', $dept['code'] . ': ' . $user['fullname']);
            flash('success', 'แต่งตั้ง ' . $user['fullname'] . ' เป็นผู้รับผิดชอบบัญชี' . $dept['name'] . ' แล้ว');
        }
    } elseif (post('action') === 'remove') {
        db_exec('DELETE FROM ledger_managers WHERE department_id = ? AND user_id = ?', 'ii', [$deptId, $userId]);
        log_activity('ledger_manager_remove', $dept['code'] . ': ' . $user['fullname']);
        flash('success', 'ยกเลิกผู้รับผิดชอบบัญชี ' . $user['fullname'] . ' แล้ว');
    }
    redirect('admin/ledger-managers.php#d' . $deptId);
}

// ผู้รับผิดชอบบัญชีทั้งหมด (รวมรายที่ย้ายแผนก/ถูกระงับ เพื่อให้ยกเลิกได้)
$assigned = [];
foreach (db_all('SELECT m.*, u.fullname, u.avatar, u.status, u.department_id AS user_dept FROM ledger_managers m JOIN users u ON u.id = m.user_id ORDER BY u.fullname') as $m) {
    $assigned[$m['department_id']][] = $m;
}
$members = [];
foreach (db_all("SELECT id, fullname, department_id FROM users WHERE status = 'active' AND department_id IS NOT NULL ORDER BY fullname") as $u) {
    $members[$u['department_id']][] = $u;
}

$pageTitle = 'ผู้รับผิดชอบบัญชีแผนก';
$activeMenu = 'ledgermgr';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="alert alert-light border small">
  <i class="bi bi-info-circle me-1"></i><strong>ผู้รับผิดชอบบัญชีแผนก</strong> บันทึก แก้ไข และลบได้ทุกรายการในบัญชีของแผนก และจัดการหมวด · ครูทุกคนในแผนกบันทึกและแก้ไขรายการได้ (ลบได้เฉพาะรายการของตน)
  · ต้องเป็นครูที่สังกัดแผนกนั้น · แผนกที่ยังไม่มีผู้รับผิดชอบบัญชี ครูทุกคนในแผนกใช้งานได้
  <br><i class="bi bi-shield-lock me-1"></i>บัญชีแต่ละแผนกเห็นได้เฉพาะครูที่สังกัดแผนกนั้น ผู้ดูแลระบบกำหนดผู้รับผิดชอบได้แต่ไม่เห็นข้อมูลบัญชีของแผนกที่ตนไม่ได้สังกัด
</div>
<div class="row g-3">
  <?php foreach (departments() as $dp): $list = $assigned[$dp['id']] ?? []; $assignedIds = array_column($list, 'user_id'); ?>
    <div class="col-md-6 col-xl-4" id="d<?= $dp['id'] ?>">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white d-flex justify-content-between">
          <span class="fw-semibold"><?= e($dp['name']) ?></span>
        </div>
        <ul class="list-group list-group-flush">
          <?php if (!$list): ?><li class="list-group-item small text-muted">ยังไม่มีผู้รับผิดชอบบัญชี</li><?php endif; ?>
          <?php foreach ($list as $m): $stale = (int)$m['user_dept'] !== (int)$dp['id'] || $m['status'] !== 'active'; ?>
            <li class="list-group-item d-flex align-items-center gap-2">
              <?= avatar_html(['id' => $m['user_id'], 'avatar' => $m['avatar'], 'fullname' => $m['fullname']], 28) ?>
              <span class="me-auto small"><?= e($m['fullname']) ?>
                <?= $stale ? '<span class="badge text-bg-secondary" title="ย้ายแผนกหรือถูกระงับ จึงไม่มีสิทธิ์แล้ว">ไม่มีผล</span>' : '' ?></span>
              <form method="post" data-confirm="ยกเลิก <?= e($m['fullname']) ?> จากผู้รับผิดชอบบัญชี?">
                <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="department_id" value="<?= $dp['id'] ?>"><input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="ยกเลิก"><i class="bi bi-x-lg"></i></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="card-footer bg-white">
          <?php $choices = array_filter($members[$dp['id']] ?? [], fn($u) => !in_array($u['id'], $assignedIds)); ?>
          <?php if ($choices): ?>
            <form method="post" class="d-flex gap-2">
              <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="department_id" value="<?= $dp['id'] ?>">
              <select class="form-select form-select-sm" name="user_id" required>
                <option value="">— เลือกครูในแผนก —</option>
                <?php foreach ($choices as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['fullname']) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> แต่งตั้ง</button>
            </form>
          <?php else: ?>
            <div class="small text-muted"><?= empty($members[$dp['id']]) ? 'ยังไม่มีครูสังกัดแผนกนี้' : 'ครูทุกคนเป็นผู้รับผิดชอบบัญชีแล้ว' ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
