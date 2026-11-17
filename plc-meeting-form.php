<?php
/** บันทึกการประชุม / กิจกรรม PLC (รวมแบบบันทึกการสะท้อนผล) พร้อมผู้เข้าร่วม — ใช้คำนวณชั่วโมง PLC */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$u = current_user();
$id = (int)get('id', post('id'));
$mt = $id ? db_row('SELECT * FROM plc_meetings WHERE id = ?', 'i', [$id]) : null;
$problem = null;
if ($mt) {
    $g = plc_group_get((int)$mt['group_id']);
} elseif ((int)get('problem', post('problem'))) {
    $problem = db_row('SELECT * FROM plc_problems WHERE id = ?', 'i', [(int)get('problem', post('problem'))]);
    $g = $problem ? plc_group_get((int)$problem['group_id']) : null;
} else {
    $g = plc_group_get((int)get('group', post('group')));
}
if (!$g || ($id && !$mt) || !plc_can_contribute($g)) {
    flash('danger', 'ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์บันทึกข้อมูลในกลุ่มนี้');
    redirect('plc.php');
}
$members = plc_members((int)$g['id']);
$problems = db_all('SELECT id, theme FROM plc_problems WHERE group_id = ? ORDER BY id', 'i', [$g['id']]);
$problemIds = array_map('intval', array_column($problems, 'id'));
$kinds = plc_meeting_kinds();
$back = $mt && $mt['problem_id'] ? 'plc-problem.php?id=' . $mt['problem_id'] : ($problem ? 'plc-problem.php?id=' . $problem['id'] : 'plc-group.php?id=' . $g['id']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'delete' && $mt) {
        db_exec("DELETE FROM plc_signatures WHERE entity = 'meeting' AND entity_id = ?", 'i', [$mt['id']]);
        db_exec('DELETE FROM plc_meetings WHERE id = ?', 'i', [$mt['id']]);
        log_activity('plc_meeting_delete', '#' . $mt['id'] . ' ' . $mt['topic']);
        flash('success', 'ลบบันทึกการประชุมแล้ว');
        redirect($back);
    }
    $f = [
        'problem_id'   => in_array((int)post('problem_id'), $problemIds, true) ? (int)post('problem_id') : null,
        'kind'         => isset($kinds[post('kind')]) ? post('kind') : 'general',
        'step_no'      => (int)post('step_no') ?: null,
        'meeting_date' => post('meeting_date'),
        'start_time'   => post('start_time'),
        'end_time'     => post('end_time'),
        'place'        => mb_substr(trim(post('place')), 0, 255),
        'topic'        => mb_substr(trim(post('topic')), 0, 255),
        'content'      => mb_substr(trim(post('content')), 0, 20000),
        'recorder_id'  => (int)post('recorder_id') ?: null,
    ];
    $details = [];
    if ($f['kind'] === 'reflect') {
        foreach (plc_reflect_fields() as $k => $label) $details[$k] = mb_substr(trim((string)($_POST['details'][$k] ?? '')), 0, 5000);
    }
    $att = array_values(array_intersect(array_map('intval', (array)($_POST['attendees'] ?? [])), array_map('intval', array_column($members, 'id'))));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['meeting_date'])) $errors[] = 'กรุณาระบุวันที่';
    if (!preg_match('/^\d{2}:\d{2}/', $f['start_time']) || !preg_match('/^\d{2}:\d{2}/', $f['end_time'])) $errors[] = 'กรุณาระบุเวลาเริ่มและเวลาสิ้นสุด';
    elseif (plc_minutes($f['start_time'], $f['end_time']) <= 0) $errors[] = 'เวลาสิ้นสุดต้องหลังเวลาเริ่ม';
    if ($f['topic'] === '') $errors[] = 'กรุณากรอกหัวข้อ/กิจกรรม';
    if (!$att) $errors[] = 'กรุณาเลือกผู้เข้าร่วมอย่างน้อย 1 คน';
    if (in_array($f['kind'], ['finding', 'solution', 'reflect'], true) && !$f['problem_id']) $errors[] = 'การประชุมประเภทนี้ต้องเลือกประเด็นปัญหา';
    $memberUsers = array_filter(array_map(fn($m) => (int)$m['user_id'], $members));
    if ($f['recorder_id'] && !in_array($f['recorder_id'], $memberUsers, true)) $f['recorder_id'] = null;

    if (!$errors) {
        $vals = [$f['problem_id'], $f['kind'], $f['step_no'], $f['meeting_date'], $f['start_time'], $f['end_time'], $f['place'] ?: null, $f['topic'], $f['content'] ?: null,
                 $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, $f['recorder_id']];
        if ($mt) {
            db_exec('UPDATE plc_meetings SET problem_id=?, kind=?, step_no=?, meeting_date=?, start_time=?, end_time=?, place=?, topic=?, content=?, details=?, recorder_id=?, updated_at=NOW() WHERE id=?',
                'isisssssssii', array_merge($vals, [$mt['id']]));
            $mid = (int)$mt['id'];
            plc_clear_signatures('meeting', $mid);
            db_exec('DELETE FROM plc_attendees WHERE meeting_id = ?', 'i', [$mid]);
        } else {
            $mid = db_exec('INSERT INTO plc_meetings (problem_id, kind, step_no, meeting_date, start_time, end_time, place, topic, content, details, recorder_id, group_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                'isisssssssiii', array_merge($vals, [$g['id'], $u['id']]));
        }
        foreach ($att as $a) db_exec('INSERT INTO plc_attendees (meeting_id, member_id) VALUES (?,?)', 'ii', [$mid, $a]);
        log_activity($mt ? 'plc_meeting_edit' : 'plc_meeting_add', '#' . $mid . ' ' . $f['topic']);
        flash('success', 'บันทึกการประชุมแล้ว' . ($f['recorder_id'] === (int)$u['id'] ? ' · อย่าลืมลงนามในฐานะผู้บันทึก' : ''));
        redirect('plc-doc.php?doc=meeting&id=' . $mid);
    }
}

// ค่าในฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = $_POST;
    $v['details'] = (array)($_POST['details'] ?? []);
    $checked = array_map('intval', (array)($_POST['attendees'] ?? []));
} elseif ($mt) {
    $v = $mt;
    $v['details'] = json_decode((string)$mt['details'], true) ?: [];
    $checked = array_map('intval', array_column(db_all('SELECT member_id FROM plc_attendees WHERE meeting_id = ?', 'i', [$mt['id']]), 'member_id'));
} else {
    $step = $problem && (int)get('step') ? db_row('SELECT * FROM plc_plan_steps WHERE problem_id = ? AND step_no = ?', 'ii', [$problem['id'], (int)get('step')]) : null;
    $last = db_row('SELECT start_time, end_time, place FROM plc_meetings WHERE group_id = ? ORDER BY id DESC LIMIT 1', 'i', [$g['id']]);
    $kindByStep = [1 => 'finding', 6 => 'teach', 8 => 'reflect', 9 => 'summary', 10 => 'summary'];
    $secretary = plc_member_with_role((int)$g['id'], 'secretary');
    $start = $last['start_time'] ?? '';
    $v = [
        'problem_id' => $problem['id'] ?? '', 'kind' => $step ? ($kindByStep[(int)$step['step_no']] ?? 'general') : 'general', 'step_no' => $step['step_no'] ?? '',
        'meeting_date' => $step['plan_date'] ?? date('Y-m-d'), 'start_time' => $start,
        'end_time' => $start && $step && $step['minutes'] ? date('H:i', strtotime('2000-01-01 ' . $start) + 60 * (int)$step['minutes']) : ($last['end_time'] ?? ''),
        'place' => $last['place'] ?? $g['meeting_place'], 'topic' => $step['activity'] ?? '', 'content' => '', 'details' => [],
        'recorder_id' => $secretary['user_id'] ?? $u['id'],
    ];
    $checked = array_map(fn($m) => (int)$m['id'], $members); // ค่าเริ่มต้น: ทุกคนเข้าร่วม (เอาออกได้)
}
$steps = [];
foreach ($problems as $pr) {
    foreach (db_all('SELECT step_no, activity FROM plc_plan_steps WHERE problem_id = ? ORDER BY step_no', 'i', [$pr['id']]) as $s) $steps[$pr['id']][] = $s;
}

$pageTitle = $mt ? 'แก้ไขบันทึกการประชุม' : 'บันทึกการประชุม / กิจกรรม PLC';
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
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($mt['id'] ?? 0) ?>"><input type="hidden" name="group" value="<?= (int)$g['id'] ?>">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card shadow-sm mb-3"><div class="card-body row g-3">
        <div class="col-md-6">
          <label class="form-label">ประเภท</label>
          <select class="form-select" name="kind" id="kindSel"><?php foreach ($kinds as $k => $l): ?><option value="<?= $k ?>"<?= ($v['kind'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-md-6">
          <label class="form-label">ประเด็นปัญหา</label>
          <select class="form-select" name="problem_id" id="problemSel"><option value="">— กิจกรรมของกลุ่ม (ไม่ผูกประเด็นปัญหา) —</option>
            <?php foreach ($problems as $pr): ?><option value="<?= $pr['id'] ?>"<?= (string)($v['problem_id'] ?? '') === (string)$pr['id'] ? ' selected' : '' ?>><?= e(mb_strimwidth($pr['theme'], 0, 80, '…')) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">ขั้นตอนในแผนปฏิบัติการ</label>
          <select class="form-select" name="step_no" id="stepSel" data-selected="<?= e($v['step_no'] ?? '') ?>"></select>
        </div>
        <div class="col-md-12"><label class="form-label">หัวข้อ / กิจกรรม *</label><input class="form-control" name="topic" id="topicInp" maxlength="255" required value="<?= e($v['topic'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">วันที่ *</label><input type="date" class="form-control" name="meeting_date" required value="<?= e($v['meeting_date'] ?? '') ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label">เวลาเริ่ม *</label><input type="time" class="form-control" name="start_time" required value="<?= e(substr((string)($v['start_time'] ?? ''), 0, 5)) ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label">เวลาสิ้นสุด *</label><input type="time" class="form-control" name="end_time" required value="<?= e(substr((string)($v['end_time'] ?? ''), 0, 5)) ?>"></div>
        <div class="col-md-12"><label class="form-label">สถานที่</label><input class="form-control" name="place" maxlength="255" value="<?= e($v['place'] ?? '') ?>"></div>
      </div></div>

      <div class="card shadow-sm mb-3" id="reflectBox">
        <div class="card-header bg-white fw-semibold">แบบบันทึกการสะท้อนผล</div>
        <div class="card-body">
          <?php foreach (plc_reflect_fields() as $k => $label): ?>
            <div class="mb-2"><label class="form-label small mb-1"><?= e($label) ?></label><textarea class="form-control" name="details[<?= $k ?>]" rows="3"><?= e($v['details'][$k] ?? '') ?></textarea></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card shadow-sm mb-3"><div class="card-body">
        <label class="form-label" id="contentLabel">สาระสำคัญ / ผลการประชุม</label>
        <textarea class="form-control" name="content" rows="8"><?= e($v['content'] ?? '') ?></textarea>
      </div></div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-semibold">ผู้เข้าร่วม *</span>
          <span class="small"><a href="#" id="attAll">ทุกคน</a> · <a href="#" id="attNone">ล้าง</a></span></div>
        <div class="card-body">
          <?php foreach ($members as $m): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="attendees[]" value="<?= $m['id'] ?>" id="att<?= $m['id'] ?>"<?= in_array((int)$m['id'], $checked, true) ? ' checked' : '' ?>>
              <label class="form-check-label" for="att<?= $m['id'] ?>"><?= e($m['display_name']) ?> <span class="text-muted small"><?= e(plc_committee_roles()[$m['committee_role']]) ?></span></label></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card shadow-sm mb-3"><div class="card-body">
        <label class="form-label">ผู้บันทึก (ลงนามท้ายแบบบันทึก)</label>
        <select class="form-select" name="recorder_id"><option value="">— ไม่ระบุ —</option>
          <?php foreach ($members as $m): if (!$m['user_id']) continue; ?><option value="<?= $m['user_id'] ?>"<?= (string)($v['recorder_id'] ?? '') === (string)$m['user_id'] ? ' selected' : '' ?>><?= e($m['display_name']) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">แก้ไขบันทึกแล้ว ลายมือชื่อเดิมจะถูกล้าง ต้องลงนามใหม่</div>
      </div></div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
        <a class="btn btn-outline-secondary" href="<?= url($back) ?>">ยกเลิก</a>
      </div>
    </div>
  </div>
</form>
<?php if ($mt): ?>
  <form method="post" class="mt-3" data-confirm="ลบบันทึกการประชุมนี้? ชั่วโมง PLC ของผู้เข้าร่วมจะลดลง">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $mt['id'] ?>"><input type="hidden" name="action" value="delete">
    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>ลบบันทึกนี้</button>
  </form>
<?php endif; ?>
<script>
(function () {
  var steps = <?= json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  var kind = document.getElementById('kindSel'), prob = document.getElementById('problemSel'), step = document.getElementById('stepSel'), topic = document.getElementById('topicInp');
  function fillSteps() {
    var sel = step.getAttribute('data-selected');
    step.innerHTML = '<option value="">— ไม่ระบุ —</option>';
    (steps[prob.value] || []).forEach(function (s) {
      var o = document.createElement('option');
      o.value = s.step_no; o.textContent = 'ขั้นที่ ' + s.step_no + ' ' + s.activity;
      if (String(s.step_no) === sel) o.selected = true;
      step.appendChild(o);
    });
  }
  function toggle() {
    var r = kind.value === 'reflect';
    document.getElementById('reflectBox').style.display = r ? '' : 'none';
    document.getElementById('contentLabel').textContent = r ? 'ข้อมูลเพิ่มเติม (ถ้ามี)' : 'สาระสำคัญ / ผลการประชุม';
  }
  prob.addEventListener('change', function () { step.setAttribute('data-selected', ''); fillSteps(); });
  step.addEventListener('change', function () {
    step.setAttribute('data-selected', step.value);
    var s = (steps[prob.value] || []).find(function (x) { return String(x.step_no) === step.value; });
    if (s && !topic.value.trim()) topic.value = s.activity;
  });
  kind.addEventListener('change', toggle);
  document.getElementById('attAll').onclick = function (e) { e.preventDefault(); document.querySelectorAll('[name="attendees[]"]').forEach(function (c) { c.checked = true; }); };
  document.getElementById('attNone').onclick = function (e) { e.preventDefault(); document.querySelectorAll('[name="attendees[]"]').forEach(function (c) { c.checked = false; }); };
  fillSteps(); toggle();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
