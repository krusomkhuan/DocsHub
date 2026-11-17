<?php
/** จัดตั้ง / แก้ไขกลุ่ม PLC และสมาชิก */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$u = current_user();
$id = (int)get('id', post('id'));
$g = $id ? plc_group_get($id) : null;
if ($id && (!$g || !plc_can_manage_group($g))) {
    flash('danger', 'ไม่พบกลุ่ม PLC หรือคุณไม่มีสิทธิ์แก้ไข');
    redirect('plc.php');
}
if ($g && $g['status'] !== 'draft') {
    flash('warning', 'กลุ่มนี้ส่งขออนุมัติแล้ว ต้องถอนกลับเป็นร่างก่อนจึงจะแก้ไขได้');
    redirect('plc-group.php?id=' . $g['id']);
}

$depts = plc_departments();
if (!is_staff()) {
    $depts = array_filter($depts, fn($d) => (int)$d['id'] === (int)$u['department_id'] || ($g && (int)$d['id'] === (int)$g['department_id']));
}
if (!$depts) {
    flash('warning', 'แผนกของคุณยังไม่ได้เปิดใช้ระบบ PLC');
    redirect('plc.php');
}
$users = db_all("SELECT u.id, u.fullname, u.position, d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.status = 'active' AND u.anonymized_at IS NULL ORDER BY d.sort_order IS NULL, d.sort_order, u.fullname");
$userIds = array_map('intval', array_column($users, 'id'));
$roles = plc_committee_roles();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $deptId = (int)post('department_id');
    $f = [
        'name'             => mb_substr(trim(post('name')), 0, 255),
        'field_name'       => mb_substr(trim(post('field_name')), 0, 150),
        'academic_year'    => (int)post('academic_year'),
        'semester'         => (int)post('semester'),
        'meeting_place'    => mb_substr(trim(post('meeting_place')), 0, 255),
        'meeting_schedule' => mb_substr(trim(post('meeting_schedule')), 0, 255),
        'memo_no'          => mb_substr(trim(post('memo_no')), 0, 100),
        'memo_date'        => post('memo_date') ?: null,
        'request_date'     => post('request_date') ?: null,
        'announce_date'    => post('announce_date') ?: null,
    ];
    if (!isset($depts[$deptId])) $errors[] = 'กรุณาเลือกแผนกวิชาที่เปิดใช้ระบบ PLC';
    if ($f['academic_year'] < 2500 || $f['academic_year'] > 2700) $errors[] = 'ปีการศึกษาไม่ถูกต้อง';
    if (!in_array($f['semester'], [1, 2, 3], true)) $errors[] = 'ภาคเรียนไม่ถูกต้อง';
    foreach (['memo_date', 'request_date', 'announce_date'] as $k) {
        if ($f[$k] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) $errors[] = 'วันที่ไม่ถูกต้อง';
    }

    // สมาชิก
    $rows = [];
    $seen = [];
    foreach ((array)($_POST['members'] ?? []) as $r) {
        $uid = (int)($r['user_id'] ?? 0);
        $guest = mb_substr(trim((string)($r['guest_name'] ?? '')), 0, 150);
        if (!$uid && $guest === '') continue;
        if ($uid && !in_array($uid, $userIds, true)) continue;
        if ($uid && isset($seen[$uid])) { $errors[] = 'มีสมาชิกซ้ำกัน'; continue; }
        if ($uid) $seen[$uid] = true;
        $role = isset($roles[$r['role'] ?? '']) ? $r['role'] : 'member';
        $rows[] = ['id' => (int)($r['id'] ?? 0), 'user_id' => $uid ?: null, 'guest_name' => $uid ? null : $guest,
                   'position_title' => mb_substr(trim((string)($r['position_title'] ?? '')), 0, 150) ?: null, 'role' => $role];
    }
    $count = array_count_values(array_column($rows, 'role'));
    if (!$rows) $errors[] = 'กรุณาเพิ่มสมาชิกกลุ่มอย่างน้อย 1 คน';
    if (($count['chair'] ?? 0) !== 1) $errors[] = 'ต้องมีประธานกรรมการ 1 คน';
    if (($count['secretary'] ?? 0) > 1) $errors[] = 'กรรมการและเลขานุการมีได้ 1 คน';


    if (!$errors) {
        if ($g) {
            db_exec('UPDATE plc_groups SET department_id=?, name=?, field_name=?, academic_year=?, semester=?, meeting_place=?, meeting_schedule=?, memo_no=?, memo_date=?, request_date=?, announce_date=?, updated_at=NOW() WHERE id=?',
                'issiissssssi', [$deptId, $f['name'] ?: null, $f['field_name'] ?: null, $f['academic_year'], $f['semester'], $f['meeting_place'] ?: null, $f['meeting_schedule'] ?: null,
                                 $f['memo_no'] ?: null, $f['memo_date'], $f['request_date'], $f['announce_date'], $g['id']]);
            $gid = (int)$g['id'];
            $hadSigs = (bool)plc_signatures('group', $gid);
            plc_clear_signatures('group', $gid);
        } else {
            $gid = db_exec('INSERT INTO plc_groups (department_id, name, field_name, academic_year, semester, meeting_place, meeting_schedule, memo_no, memo_date, request_date, announce_date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                'issiissssssi', [$deptId, $f['name'] ?: null, $f['field_name'] ?: null, $f['academic_year'], $f['semester'], $f['meeting_place'] ?: null, $f['meeting_schedule'] ?: null,
                                 $f['memo_no'] ?: null, $f['memo_date'], $f['request_date'], $f['announce_date'], $u['id']]);
        }
        // สมาชิก: แก้แถวเดิม (เก็บประวัติการเข้าประชุม) เพิ่มแถวใหม่ ลบแถวที่เอาออก
        $keep = [];
        foreach ($rows as $i => $r) {
            $exists = $r['id'] ? db_val('SELECT id FROM plc_members WHERE id = ? AND group_id = ?', 'ii', [$r['id'], $gid]) : null;
            if ($exists) {
                db_exec('UPDATE plc_members SET user_id=?, guest_name=?, position_title=?, committee_role=?, sort_order=? WHERE id=?', 'isssii', [$r['user_id'], $r['guest_name'], $r['position_title'], $r['role'], $i, $r['id']]);
                $keep[] = (int)$r['id'];
            } else {
                $keep[] = db_exec('INSERT INTO plc_members (group_id, user_id, guest_name, position_title, committee_role, sort_order) VALUES (?,?,?,?,?,?)', 'iisssi', [$gid, $r['user_id'], $r['guest_name'], $r['position_title'], $r['role'], $i]);
            }
        }
        db_exec('DELETE FROM plc_members WHERE group_id = ? AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')', 'i', [$gid]);
        log_activity($g ? 'plc_group_edit' : 'plc_group_add', '#' . $gid . ' ' . ($f['name'] ?: $depts[$deptId]['name']) . ' ' . $f['semester'] . '/' . $f['academic_year']);
        flash('success', $g ? 'บันทึกข้อมูลกลุ่มแล้ว' . (!empty($hadSigs) ? ' (ลายมือชื่อเดิมถูกล้าง ต้องลงนามใหม่)' : '') : 'จัดตั้งกลุ่ม PLC แล้ว ขั้นต่อไป: เพิ่มประเด็นปัญหา และส่งขออนุมัติกลุ่ม');
        redirect('plc-group.php?id=' . $gid);
    }
}

// ค่าเริ่มต้นของฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = $_POST;
    $members = array_values((array)($_POST['members'] ?? []));
} elseif ($g) {
    $v = $g;
    $members = array_map(fn($m) => ['id' => $m['id'], 'user_id' => $m['user_id'], 'guest_name' => $m['guest_name'], 'position_title' => $m['position_title'], 'role' => $m['committee_role']], plc_members((int)$g['id']));
} else {
    $deptId = (int)($u['department_id'] && isset($depts[(int)$u['department_id']]) ? $u['department_id'] : array_key_first($depts));
    $v = ['department_id' => $deptId, 'academic_year' => current_academic_year(), 'semester' => (int)date('n') >= 11 || (int)date('n') <= 3 ? 2 : 1,
          'meeting_place' => 'ห้องพักครู' . ($depts[$deptId]['name'] ?? ''), 'meeting_schedule' => '', 'request_date' => date('Y-m-d'), 'memo_date' => date('Y-m-d')];
    // ครูในแผนกเป็นสมาชิกตั้งต้น ผู้สร้างเป็นประธาน (ปรับได้)
    $members = [['id' => 0, 'user_id' => $u['id'], 'guest_name' => '', 'position_title' => '', 'role' => 'chair']];
    foreach (db_all("SELECT id FROM users WHERE department_id = ? AND status = 'active' AND id <> ? ORDER BY fullname", 'ii', [$deptId, $u['id']]) as $r) {
        $members[] = ['id' => 0, 'user_id' => $r['id'], 'guest_name' => '', 'position_title' => '', 'role' => 'member'];
    }
    $members[] = ['id' => 0, 'user_id' => setting('plc_deputy_user_id') ?: '', 'guest_name' => setting('plc_deputy_user_id') ? '' : setting('plc_deputy_name'),
                  'position_title' => setting('plc_deputy_position'), 'role' => 'expert'];
}

$memberRow = function ($i, array $m) use ($users, $roles): string {
    $opts = '<option value="">— บุคคลภายนอก (พิมพ์ชื่อ) —</option>';
    $dept = null;
    foreach ($users as $us) {
        if ($us['dept_name'] !== $dept) { $opts .= ($dept !== null ? '</optgroup>' : '') . '<optgroup label="' . e($us['dept_name'] ?: 'ไม่ระบุแผนก') . '">'; $dept = $us['dept_name']; }
        $opts .= '<option value="' . $us['id'] . '"' . ((string)($m['user_id'] ?? '') === (string)$us['id'] ? ' selected' : '') . '>' . e($us['fullname']) . '</option>';
    }
    $opts .= '</optgroup>';
    $roleOpts = '';
    foreach ($roles as $k => $l) $roleOpts .= '<option value="' . $k . '"' . (($m['role'] ?? '') === $k ? ' selected' : '') . '>' . $l . '</option>';
    return '<tr><td class="text-muted" data-row-no></td>'
        . '<td><input type="hidden" name="members[' . $i . '][id]" value="' . (int)($m['id'] ?? 0) . '">'
        . '<select class="form-select form-select-sm" name="members[' . $i . '][user_id]">' . $opts . '</select>'
        . '<input class="form-control form-control-sm mt-1" name="members[' . $i . '][guest_name]" placeholder="ชื่อบุคคลภายนอก (ถ้าไม่มีบัญชี)" value="' . e($m['guest_name'] ?? '') . '"></td>'
        . '<td><select class="form-select form-select-sm" name="members[' . $i . '][role]">' . $roleOpts . '</select></td>'
        . '<td><input class="form-control form-control-sm" name="members[' . $i . '][position_title]" placeholder="เช่น รองผู้อำนวยการฝ่ายวิชาการ" value="' . e($m['position_title'] ?? '') . '"></td>'
        . '<td><button class="btn btn-sm btn-outline-danger" data-del-row title="ลบ"><i class="bi bi-x-lg"></i></button></td></tr>';
};

$pageTitle = $g ? 'แก้ไขกลุ่ม PLC' : 'จัดตั้งกลุ่ม PLC';
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('plc.php') ?>">PLC</a></li>
  <?php if ($g): ?><li class="breadcrumb-item"><a href="<?= url('plc-group.php?id=' . $g['id']) ?>"><?= e(plc_group_title($g)) ?></a></li><?php endif; ?>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($g['id'] ?? 0) ?>">
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">ข้อมูลกลุ่ม</div>
    <div class="card-body row g-3">
      <div class="col-md-6">
        <label class="form-label">แผนกวิชา *</label>
        <select class="form-select" name="department_id" required>
          <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"<?= (int)($v['department_id'] ?? 0) === (int)$d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">ชื่อกลุ่ม</label>
        <input class="form-control" name="name" maxlength="255" value="<?= e($v['name'] ?? '') ?>" placeholder="เว้นว่าง = ใช้ชื่อแผนกวิชา">
        <div class="form-text">ในเอกสารจะแสดงเป็น "ชื่อกลุ่ม + <?= e(setting('college_name')) ?>" · ใช้เมื่อแผนกมีหลายกลุ่ม</div>
      </div>
      <div class="col-md-4"><label class="form-label">สาขาวิชา</label><input class="form-control" name="field_name" maxlength="150" value="<?= e($v['field_name'] ?? '') ?>" placeholder="เช่น คอมพิวเตอร์ธุรกิจ"></div>
      <div class="col-6 col-md-2"><label class="form-label">ภาคเรียน *</label><select class="form-select" name="semester"><?php foreach ([1, 2, 3] as $s): ?><option<?= (int)($v['semester'] ?? 1) === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label">ปีการศึกษา *</label><input type="number" class="form-control" name="academic_year" min="2500" max="2700" value="<?= (int)($v['academic_year'] ?? current_academic_year()) ?>" required></div>
      <div class="col-md-4"><label class="form-label">วันเวลาประชุมกลุ่ม</label><input class="form-control" name="meeting_schedule" maxlength="255" value="<?= e($v['meeting_schedule'] ?? '') ?>" placeholder="เช่น วันพุธ เวลา 18.00-20.00 น."></div>
      <div class="col-md-6"><label class="form-label">สถานที่ประชุมกลุ่ม</label><input class="form-control" name="meeting_place" maxlength="255" value="<?= e($v['meeting_place'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold">สมาชิกกลุ่ม</span>
      <button class="btn btn-outline-primary btn-sm" data-add-row="members"><i class="bi bi-plus-lg me-1"></i>เพิ่มสมาชิก</button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th style="width:40px">#</th><th>ชื่อ</th><th style="width:200px">บทบาทในกลุ่ม</th><th>ตำแหน่ง (แสดงในเอกสาร)</th><th style="width:50px"></th></tr></thead>
        <tbody data-rows="members">
          <?php foreach ($members as $i => $m) echo $memberRow($i, $m); ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white small text-muted">ต้องมีประธานกรรมการ 1 คน · กรรมการและเลขานุการเป็นผู้บันทึกกลุ่ม · ผู้เชี่ยวชาญที่ไม่มีบัญชี เลือก "บุคคลภายนอก" แล้วพิมพ์ชื่อ · สมาชิกทุกคนที่มีบัญชีบันทึกข้อมูลในกลุ่มได้</div>
  </div>
  <template data-template="members"><?= $memberRow('__i__', ['role' => 'member']) ?></template>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">เอกสารจัดตั้งกลุ่ม</div>
    <div class="card-body row g-3">
      <div class="col-md-4">
        <label class="form-label">ที่ (เลขบันทึกข้อความขออนุญาตจัดตั้งกลุ่ม)</label>
        <input class="form-control" name="memo_no" maxlength="100" value="<?= e($v['memo_no'] ?? '') ?>" placeholder="เช่น ทธ 093/2569">
        <div class="form-text">กรอกเลขที่ที่ได้จากระบบ e-office</div>
      </div>
      <div class="col-md-4"><label class="form-label">วันที่บันทึกข้อความ</label><input type="date" class="form-control" name="memo_date" value="<?= e($v['memo_date'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">วันที่แบบคำร้องขอจัดตั้งกลุ่ม</label><input type="date" class="form-control" name="request_date" value="<?= e($v['request_date'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">วันที่ประกาศวิทยาลัย</label><input type="date" class="form-control" name="announce_date" value="<?= e($v['announce_date'] ?? '') ?>"></div>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
    <a class="btn btn-outline-secondary" href="<?= url($g ? 'plc-group.php?id=' . $g['id'] : 'plc.php') ?>">ยกเลิก</a>
  </div>
</form>
<script src="<?= url('assets/js/plc.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<script>document.querySelectorAll('[data-rows]').forEach(function (b) { b.querySelectorAll('[data-row-no]').forEach(function (el, i) { el.textContent = i + 1; }); });</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
