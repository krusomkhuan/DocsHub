<?php
/** เสนอ / แก้ไขประเด็นปัญหา: ปัญหา-สาเหตุ แนวทางแก้ไข วัตถุประสงค์ กิจกรรม การวัดผล ประโยชน์ และแผนปฏิบัติการ (PLC-AP) */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$u = current_user();
$id = (int)get('id', post('id'));
$p = $id ? db_row('SELECT * FROM plc_problems WHERE id = ?', 'i', [$id]) : null;
$g = plc_group_get($p ? (int)$p['group_id'] : (int)get('group', post('group')));
if (!$g || ($id && !$p) || !plc_can_contribute($g)) {
    flash('danger', 'ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์บันทึกข้อมูลในกลุ่มนี้');
    redirect('plc.php');
}
if ($p && $p['status'] !== 'draft') {
    flash('warning', 'ประเด็นปัญหานี้ส่งขออนุมัติแล้ว ต้องถอนกลับเป็นร่างก่อนจึงจะแก้ไขได้');
    redirect('plc-problem.php?id=' . $p['id']);
}
$teachers = array_filter(plc_members((int)$g['id']), fn($m) => $m['user_id'] && $m['committee_role'] !== 'expert');
$teacherIds = array_map(fn($m) => (int)$m['user_id'], $teachers);

/** อ่านแถวจากฟอร์มตาราง: เก็บเฉพาะแถวที่มีข้อมูลในช่องหลัก */
function plc_post_rows(string $name, array $fields, string $required): array
{
    $out = [];
    foreach ((array)($_POST[$name] ?? []) as $r) {
        $row = [];
        foreach ($fields as $f => $max) $row[$f] = mb_substr(trim((string)($r[$f] ?? '')), 0, $max);
        if ($row[$required] !== '') $out[] = $row;
    }
    return $out;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $model = (int)post('model_teacher_id');
    $f = [
        'theme'         => mb_substr(trim(post('theme')), 0, 500),
        'subject_name'  => mb_substr(trim(post('subject_name')), 0, 255),
        'proposal_date' => post('proposal_date') ?: null,
        'memo_no'       => mb_substr(trim(post('memo_no')), 0, 100),
        'memo_date'     => post('memo_date') ?: null,
        'solutions'     => mb_substr(trim(post('solutions')), 0, 5000),
        'benefits'      => mb_substr(trim(post('benefits')), 0, 5000),
        'plan_start'    => post('plan_start') ?: null,
        'plan_end'      => post('plan_end') ?: null,
    ];
    $issues = plc_post_rows('issues', ['issue' => 2000, 'causes' => 3000, 'priority' => 3], 'issue');
    $acts = [];
    foreach ((array)($_POST['acts'] ?? []) as $r) {
        $t = mb_substr(trim((string)($r['teacher_activity'] ?? '')), 0, 3000);
        $s = mb_substr(trim((string)($r['student_activity'] ?? '')), 0, 3000);
        if ($t !== '' || $s !== '') $acts[] = ['teacher_activity' => $t, 'student_activity' => $s];
    }
    $measures = plc_post_rows('measures', ['objective' => 2000, 'tool' => 2000, 'criteria' => 2000], 'objective');
    $steps = plc_post_rows('steps', ['activity' => 500, 'role_text' => 255, 'plan_date' => 10, 'minutes' => 4], 'activity');

    if ($f['theme'] === '') $errors[] = 'กรุณากรอกประเด็นปัญหาที่จะพัฒนา';
    if (!in_array($model, $teacherIds, true)) $errors[] = 'กรุณาเลือกเจ้าของปัญหา (Model Teacher) จากสมาชิกกลุ่ม';
    foreach (['proposal_date', 'memo_date', 'plan_start', 'plan_end'] as $k) {
        if ($f[$k] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) $errors[] = 'วันที่ไม่ถูกต้อง';
    }
    if ($f['plan_start'] && $f['plan_end'] && $f['plan_end'] < $f['plan_start']) $errors[] = 'วันสิ้นสุดแผนต้องไม่ก่อนวันเริ่มแผน';
    $warnings = [];
    foreach ($steps as $i => $s) {
        if ($s['plan_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['plan_date'])) $errors[] = 'วันที่ในแผนปฏิบัติการขั้นที่ ' . ($i + 1) . ' ไม่ถูกต้อง';
        elseif ($s['plan_date'] !== '' && $f['plan_start'] && $f['plan_end'] && ($s['plan_date'] < $f['plan_start'] || $s['plan_date'] > $f['plan_end'])) {
            $warnings[] = 'ขั้นที่ ' . ($i + 1) . ' ลงวันที่ ' . thai_date($s['plan_date']) . ' อยู่นอกช่วงแผนปฏิบัติการ';
        }
    }


    if (!$errors) {
        $vals = [$model, $f['theme'], $f['subject_name'] ?: null, $f['proposal_date'], $f['memo_no'] ?: null, $f['memo_date'], $f['solutions'] ?: null, $f['benefits'] ?: null, $f['plan_start'], $f['plan_end']];
        if ($p) {
            db_exec('UPDATE plc_problems SET model_teacher_id=?, theme=?, subject_name=?, proposal_date=?, memo_no=?, memo_date=?, solutions=?, benefits=?, plan_start=?, plan_end=?, updated_at=NOW() WHERE id=?',
                'isssssssssi', array_merge($vals, [$p['id']]));
            $pid = (int)$p['id'];
            $hadSigs = (bool)plc_signatures('problem', $pid);
            plc_clear_signatures('problem', $pid);
            foreach (['plc_issues', 'plc_activities', 'plc_measures', 'plc_plan_steps'] as $t) db_exec("DELETE FROM $t WHERE problem_id = ?", 'i', [$pid]);
        } else {
            $pid = db_exec('INSERT INTO plc_problems (model_teacher_id, theme, subject_name, proposal_date, memo_no, memo_date, solutions, benefits, plan_start, plan_end, group_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                'isssssssssii', array_merge($vals, [$g['id'], $u['id']]));
        }
        foreach ($issues as $r) db_exec('INSERT INTO plc_issues (problem_id, issue, causes, priority) VALUES (?,?,?,?)', 'issi', [$pid, $r['issue'], $r['causes'] ?: null, max(1, (int)$r['priority'] ?: 1)]);
        foreach (array_values($acts) as $i => $r) db_exec('INSERT INTO plc_activities (problem_id, teacher_activity, student_activity, sort_order) VALUES (?,?,?,?)', 'issi', [$pid, $r['teacher_activity'] ?: null, $r['student_activity'] ?: null, $i]);
        foreach ($measures as $i => $r) db_exec('INSERT INTO plc_measures (problem_id, objective, tool, criteria, sort_order) VALUES (?,?,?,?,?)', 'isssi', [$pid, $r['objective'], $r['tool'] ?: null, $r['criteria'] ?: null, $i]);
        foreach ($steps as $i => $r) db_exec('INSERT INTO plc_plan_steps (problem_id, step_no, activity, role_text, plan_date, minutes) VALUES (?,?,?,?,?,?)', 'iisssi', [$pid, $i + 1, $r['activity'], $r['role_text'] ?: null, $r['plan_date'] ?: null, (int)$r['minutes'] ?: null]);
        log_activity($p ? 'plc_problem_edit' : 'plc_problem_add', '#' . $pid . ' ' . $f['theme']);
        flash('success', 'บันทึกประเด็นปัญหาแล้ว' . (!empty($hadSigs) ? ' (ลายมือชื่อเดิมถูกล้าง ต้องลงนามใหม่)' : ''));
        foreach ($warnings as $w) flash('warning', $w);
        redirect('plc-problem.php?id=' . $pid);
    }
}

// ค่าในฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = $_POST;
    $issues = array_values((array)($_POST['issues'] ?? []));
    $acts = array_values((array)($_POST['acts'] ?? []));
    $measures = array_values((array)($_POST['measures'] ?? []));
    $steps = array_values((array)($_POST['steps'] ?? []));
} elseif ($p) {
    $v = $p;
    $issues = db_all('SELECT * FROM plc_issues WHERE problem_id = ? ORDER BY id', 'i', [$p['id']]);
    $acts = db_all('SELECT * FROM plc_activities WHERE problem_id = ? ORDER BY sort_order, id', 'i', [$p['id']]);
    $measures = db_all('SELECT * FROM plc_measures WHERE problem_id = ? ORDER BY sort_order, id', 'i', [$p['id']]);
    $steps = db_all('SELECT * FROM plc_plan_steps WHERE problem_id = ? ORDER BY step_no', 'i', [$p['id']]);
} else {
    $v = ['model_teacher_id' => in_array((int)$u['id'], $teacherIds, true) ? $u['id'] : '', 'proposal_date' => date('Y-m-d')];
    $issues = [['priority' => 1], ['priority' => 2], ['priority' => 3]];
    $acts = [[], [], []];
    $measures = [[], [], []];
    $steps = array_map(fn($s) => ['activity' => $s[0], 'role_text' => $s[1], 'minutes' => $s[2]], plc_default_steps());
}

$ta = fn($name, $val, $rows = 2, $ph = '') => '<textarea class="form-control form-control-sm" name="' . $name . '" rows="' . $rows . '" placeholder="' . e($ph) . '">' . e($val ?? '') . '</textarea>';
$del = '<td class="text-end"><button class="btn btn-sm btn-outline-danger" data-del-row title="ลบแถว"><i class="bi bi-x-lg"></i></button></td>';
$rowIssue = fn($i, $r) => '<tr><td class="text-muted" data-row-no></td><td>' . $ta("issues[$i][issue]", $r['issue'] ?? '', 2, 'ปัญหาด้านการเรียนรู้ของผู้เรียน') . '</td><td>' . $ta("issues[$i][causes]", $r['causes'] ?? '', 3, "สาเหตุ (บรรทัดละข้อ)") . '</td>'
    . '<td><input type="number" class="form-control form-control-sm" name="issues[' . $i . '][priority]" min="1" max="20" value="' . e($r['priority'] ?? '') . '"></td>' . $del . '</tr>';
$rowAct = fn($i, $r) => '<tr><td class="text-muted" data-row-no></td><td>' . $ta("acts[$i][teacher_activity]", $r['teacher_activity'] ?? '', 2, 'ครูผู้สอน...') . '</td><td>' . $ta("acts[$i][student_activity]", $r['student_activity'] ?? '', 2, 'ผู้เรียน...') . '</td>' . $del . '</tr>';
$rowMeasure = fn($i, $r) => '<tr><td class="text-muted" data-row-no></td><td>' . $ta("measures[$i][objective]", $r['objective'] ?? '', 2, 'เพื่อ...') . '</td><td>' . $ta("measures[$i][tool]", $r['tool'] ?? '', 2) . '</td><td>' . $ta("measures[$i][criteria]", $r['criteria'] ?? '', 2) . '</td>' . $del . '</tr>';
$rowStep = fn($i, $r) => '<tr><td class="text-muted" data-row-no></td><td>' . $ta("steps[$i][activity]", $r['activity'] ?? '', 2) . '</td><td>' . $ta("steps[$i][role_text]", $r['role_text'] ?? '', 2) . '</td>'
    . '<td><input type="date" class="form-control form-control-sm" name="steps[' . $i . '][plan_date]" value="' . e($r['plan_date'] ?? '') . '"></td>'
    . '<td><input type="number" class="form-control form-control-sm" name="steps[' . $i . '][minutes]" min="0" max="9999" value="' . e($r['minutes'] ?? '') . '"></td>' . $del . '</tr>';

$pageTitle = $p ? 'แก้ไขประเด็นปัญหา' : 'เสนอประเด็นปัญหา';
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('plc.php') ?>">PLC</a></li>
  <li class="breadcrumb-item"><a href="<?= url('plc-group.php?id=' . $g['id']) ?>"><?= e(plc_group_title($g)) ?> <?= e(plc_term_text($g)) ?></a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>"><input type="hidden" name="group" value="<?= (int)$g['id'] ?>">

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">๑. แบบคำร้องขอเสนอประเด็นปัญหา</div>
    <div class="card-body row g-3">
      <div class="col-md-8"><label class="form-label">ประเด็นปัญหาที่จะพัฒนา *</label><textarea class="form-control" name="theme" rows="2" maxlength="500" required placeholder="เช่น การประยุกต์ใช้เทคโนโลยีปัญญาประดิษฐ์ในรายวิชาระบบจัดการฐานข้อมูล"><?= e($v['theme'] ?? '') ?></textarea></div>
      <div class="col-md-4">
        <label class="form-label">เจ้าของปัญหา (Model Teacher) *</label>
        <select class="form-select" name="model_teacher_id" required>
          <option value="">— เลือก —</option>
          <?php foreach ($teachers as $m): ?><option value="<?= $m['user_id'] ?>"<?= (string)($v['model_teacher_id'] ?? '') === (string)$m['user_id'] ? ' selected' : '' ?>><?= e($m['display_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5"><label class="form-label">รายวิชา</label><input class="form-control" name="subject_name" maxlength="255" value="<?= e($v['subject_name'] ?? '') ?>" placeholder="เช่น ระบบจัดการฐานข้อมูล"></div>
      <div class="col-md-3"><label class="form-label">วันที่เสนอ</label><input type="date" class="form-control" name="proposal_date" value="<?= e($v['proposal_date'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-semibold">๒. ปัญหา สาเหตุ และลำดับความสำคัญ (แบบบันทึกการค้นหาปัญหา)</span>
      <button class="btn btn-outline-primary btn-sm" data-add-row="issues"><i class="bi bi-plus-lg"></i> เพิ่ม</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead class="table-light"><tr><th style="width:36px">#</th><th>ปัญหา</th><th>สาเหตุของปัญหา (บรรทัดละข้อ)</th><th style="width:110px">ลำดับความสำคัญ</th><th style="width:50px"></th></tr></thead>
      <tbody data-rows="issues"><?php foreach ($issues as $i => $r) echo $rowIssue($i, $r); ?></tbody>
    </table></div>
    <div class="card-footer bg-white small text-muted">ปัญหาลำดับความสำคัญที่ 1 คือปัญหาที่กลุ่มเลือกนำมาแก้ไข (แสดงในแบบบันทึกแนวทางแก้ไขและแผนปฏิบัติการ)</div>
  </div>
  <template data-template="issues"><?= $rowIssue('__i__', []) ?></template>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">๓. แนวทางแก้ไขปัญหา</div>
    <div class="card-body"><textarea class="form-control" name="solutions" rows="4" placeholder="บรรทัดละข้อ เช่น&#10;ใช้ AI เป็นผู้ช่วยอธิบายคำสั่ง SQL วิเคราะห์ข้อผิดพลาด...&#10;ใช้ AI สร้างแบบฝึกหัดตามระดับความสามารถ..."><?= e($v['solutions'] ?? '') ?></textarea></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-semibold">๔. วัตถุประสงค์ เครื่องมือวัดและประเมินผล เกณฑ์ความสำเร็จ</span>
      <button class="btn btn-outline-primary btn-sm" data-add-row="measures"><i class="bi bi-plus-lg"></i> เพิ่ม</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead class="table-light"><tr><th style="width:36px">#</th><th>วัตถุประสงค์</th><th>เครื่องมือวัดและประเมินผล</th><th>เกณฑ์ความสำเร็จ</th><th style="width:50px"></th></tr></thead>
      <tbody data-rows="measures"><?php foreach ($measures as $i => $r) echo $rowMeasure($i, $r); ?></tbody>
    </table></div>
  </div>
  <template data-template="measures"><?= $rowMeasure('__i__', []) ?></template>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-semibold">๕. แนวทางการดำเนินงาน (กิจกรรมการเรียนการสอน / กิจกรรมผู้เรียน)</span>
      <button class="btn btn-outline-primary btn-sm" data-add-row="acts"><i class="bi bi-plus-lg"></i> เพิ่ม</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead class="table-light"><tr><th style="width:36px">#</th><th>กิจกรรมการเรียนการสอน</th><th>กิจกรรมผู้เรียน</th><th style="width:50px"></th></tr></thead>
      <tbody data-rows="acts"><?php foreach ($acts as $i => $r) echo $rowAct($i, $r); ?></tbody>
    </table></div>
  </div>
  <template data-template="acts"><?= $rowAct('__i__', []) ?></template>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">๖. ประโยชน์ที่ได้รับ</div>
    <div class="card-body"><textarea class="form-control" name="benefits" rows="3" placeholder="บรรทัดละข้อ"><?= e($v['benefits'] ?? '') ?></textarea></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-semibold">๗. แผนปฏิบัติการ (PLC-AP)</span>
      <button class="btn btn-outline-primary btn-sm" data-add-row="steps"><i class="bi bi-plus-lg"></i> เพิ่มขั้นตอน</button></div>
    <div class="card-body row g-3 pb-0">
      <div class="col-md-3"><label class="form-label small">เริ่มแผน</label><input type="date" class="form-control form-control-sm" name="plan_start" value="<?= e($v['plan_start'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">สิ้นสุดแผน</label><input type="date" class="form-control form-control-sm" name="plan_end" value="<?= e($v['plan_end'] ?? '') ?>"></div>
      <div class="col-md-6 small text-muted align-self-end">ระบบจะเตือนถ้าวันที่ของขั้นตอนอยู่นอกช่วงแผน</div>
    </div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead class="table-light"><tr><th style="width:36px">ขั้น</th><th>กิจกรรม</th><th style="width:190px">บทบาท</th><th style="width:150px">วันที่</th><th style="width:90px">นาที</th><th style="width:50px"></th></tr></thead>
      <tbody data-rows="steps"><?php foreach ($steps as $i => $r) echo $rowStep($i, $r); ?></tbody>
    </table></div>
  </div>
  <template data-template="steps"><?= $rowStep('__i__', []) ?></template>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">๘. บันทึกข้อความส่งแบบบันทึก</div>
    <div class="card-body row g-3">
      <div class="col-md-4"><label class="form-label">ที่</label><input class="form-control" name="memo_no" maxlength="100" value="<?= e($v['memo_no'] ?? '') ?>">
        <div class="form-text">กรอกเลขที่ที่ได้จากระบบ e-office</div></div>
      <div class="col-md-4"><label class="form-label">วันที่</label><input type="date" class="form-control" name="memo_date" value="<?= e($v['memo_date'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
    <a class="btn btn-outline-secondary" href="<?= url($p ? 'plc-problem.php?id=' . $p['id'] : 'plc-group.php?id=' . $g['id']) ?>">ยกเลิก</a>
  </div>
</form>
<script src="<?= url('assets/js/plc.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<script>document.querySelectorAll('[data-rows]').forEach(function (b) { b.querySelectorAll('[data-row-no]').forEach(function (el, i) { el.textContent = i + 1; }); });</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
