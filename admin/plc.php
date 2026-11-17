<?php
/** ตั้งค่าระบบ PLC: เปิดใช้รายแผนก หัวหน้าแผนก ผู้ลงนามระดับวิทยาลัย */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$users = db_all("SELECT u.id, u.fullname, d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.status = 'active' AND u.anonymized_at IS NULL ORDER BY u.fullname");
$userIds = array_map('intval', array_column($users, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (departments() as $d) {
        $on = !empty($_POST['enabled'][$d['id']]) ? 1 : 0;
        $head = (int)($_POST['head'][$d['id']] ?? 0);
        db_exec('UPDATE departments SET plc_enabled = ?, plc_head_user_id = ? WHERE id = ?', 'iii', [$on, in_array($head, $userIds, true) ? $head : null, $d['id']]);
    }
    foreach (['deputy', 'director'] as $k) {
        $uid = (int)post($k . '_user_id');
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', ['plc_' . $k . '_user_id', in_array($uid, $userIds, true) ? (string)$uid : '']);
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', ['plc_' . $k . '_name', mb_substr(trim(post($k . '_name')), 0, 150)]);
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', ['plc_' . $k . '_position', mb_substr(trim(post($k . '_position')), 0, 150)]);
    }
    db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', ['plc_committee_title', mb_substr(trim(post('committee_title')), 0, 255)]);
    log_activity('plc_settings');
    flash('success', 'บันทึกการตั้งค่าระบบ PLC แล้ว');
    redirect('admin/plc.php');
}

$userSelect = function (string $name, $selected) use ($users): string {
    $h = '<select class="form-select form-select-sm" name="' . e($name) . '"><option value="">— ไม่มีบัญชีในระบบ —</option>';
    foreach ($users as $u) $h .= '<option value="' . $u['id'] . '"' . ((string)$selected === (string)$u['id'] ? ' selected' : '') . '>' . e($u['fullname']) . ($u['dept_name'] ? ' · ' . e($u['dept_name']) : '') . '</option>';
    return $h . '</select>';
};
$stats = db_all('SELECT department_id, COUNT(*) AS n FROM plc_groups GROUP BY department_id');
$groupCount = array_column($stats, 'n', 'department_id');

$pageTitle = 'ระบบ PLC';
$activeMenu = 'plc';
require __DIR__ . '/../includes/admin_header.php';
?>
<form method="post">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">เปิดใช้ระบบ PLC รายแผนก และหัวหน้าแผนก (ผู้อนุมัติขั้นที่ 1)</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th style="width:70px" class="text-center">เปิดใช้</th><th>แผนกวิชา / ฝ่าย</th><th>หัวหน้าแผนก</th><th class="text-center">กลุ่ม</th></tr></thead>
            <tbody>
            <?php foreach (departments() as $d): ?>
              <tr>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="enabled[<?= $d['id'] ?>]" value="1"<?= $d['plc_enabled'] ? ' checked' : '' ?>></td>
                <td><?= e($d['name']) ?> <span class="text-muted small">(<?= e($d['code']) ?>)</span></td>
                <td style="min-width:220px"><?= $userSelect('head[' . $d['id'] . ']', $d['plc_head_user_id']) ?></td>
                <td class="text-center"><?= (int)($groupCount[$d['id']] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-white small text-muted">ครูในแผนกที่เปิดใช้จะเห็นเมนู PLC และจัดตั้งกลุ่มได้ · ปิดใช้ภายหลัง ข้อมูลเดิมยังอยู่ สมาชิกกลุ่มยังเข้าดูได้</div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">ผู้ลงนามระดับวิทยาลัย</div>
        <div class="card-body">
          <?php foreach (['deputy' => ['รองผู้อำนวยการฝ่ายวิชาการ (ผู้อนุมัติขั้นที่ 2)', 'รองผู้อำนวยการฝ่ายวิชาการ'], 'director' => ['ผู้อำนวยการ (ผู้อนุมัติขั้นสุดท้าย)', 'ผู้อำนวยการ']] as $k => [$label, $defPos]): ?>
            <div class="border rounded p-2 mb-3">
              <div class="fw-medium mb-2"><?= e($label) ?></div>
              <label class="form-label small mb-1">บัญชีผู้ใช้ (อนุมัติในระบบได้)</label>
              <?= $userSelect($k . '_user_id', setting('plc_' . $k . '_user_id')) ?>
              <label class="form-label small mb-1 mt-2">ชื่อที่แสดงในเอกสาร (ใช้เมื่อไม่มีบัญชี)</label>
              <input class="form-control form-control-sm" name="<?= $k ?>_name" maxlength="150" value="<?= e(setting('plc_' . $k . '_name')) ?>" placeholder="เช่น ว่าที่ ร้อยโท สันติสุข โมฬา">
              <label class="form-label small mb-1 mt-2">ตำแหน่ง</label>
              <input class="form-control form-control-sm" name="<?= $k ?>_position" maxlength="150" value="<?= e(setting('plc_' . $k . '_position', $defPos)) ?>">
            </div>
          <?php endforeach; ?>
          <label class="form-label small mb-1">"เรียน" ในแบบคำร้อง</label>
          <input class="form-control form-control-sm" name="committee_title" maxlength="255" value="<?= e(setting('plc_committee_title')) ?>">
          <div class="form-text">ผู้อนุมัติที่ไม่มีบัญชี ระบบข้ามขั้นนั้น (ลงนามในเอกสารกระดาษ) · ผู้อำนวยการแสดงตำแหน่งต่อท้ายด้วยชื่อวิทยาลัย</div>
        </div>
      </div>
      <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึกการตั้งค่า</button>
    </div>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
