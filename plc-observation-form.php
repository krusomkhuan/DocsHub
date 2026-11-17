<?php
/** แบบสังเกตการสอนในชั้นเรียน (ผู้สังเกตแต่ละคนบันทึกแบบของตนเอง) */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$u = current_user();
$id = (int)get('id', post('id'));
$o = $id ? db_row('SELECT * FROM plc_observations WHERE id = ?', 'i', [$id]) : null;
$p = db_row('SELECT * FROM plc_problems WHERE id = ?', 'i', [$o ? (int)$o['problem_id'] : (int)get('problem', post('problem'))]);
$g = $p ? plc_group_get((int)$p['group_id']) : null;
if (!$p || !$g || ($id && !$o) || !plc_can_contribute($g) || ($o && !is_staff() && (int)$o['observer_id'] !== (int)$u['id'])) {
    flash('danger', 'ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์แก้ไขแบบสังเกตนี้');
    redirect('plc.php');
}
$observers = array_filter(plc_members((int)$g['id']), fn($m) => $m['user_id']);
$observerIds = array_map(fn($m) => (int)$m['user_id'], $observers);
$items = plc_observe_items();
$labels = plc_score_labels();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'delete' && $o) {
        db_exec("DELETE FROM plc_signatures WHERE entity = 'observation' AND entity_id = ?", 'i', [$o['id']]);
        db_exec('DELETE FROM plc_observations WHERE id = ?', 'i', [$o['id']]);
        log_activity('plc_observation_delete', '#' . $o['id']);
        flash('success', 'ลบแบบสังเกตแล้ว');
        redirect('plc-problem.php?id=' . $p['id']);
    }
    $observer = is_staff() ? (int)post('observer_id') : (int)$u['id'];
    if (!in_array($observer, $observerIds, true)) $errors[] = 'ผู้สังเกตต้องเป็นสมาชิกกลุ่ม';
    $scores = [];
    foreach ($items as $section) {
        foreach ($section as $k => $label) {
            $s = (int)($_POST['scores'][$k] ?? 0);
            if ($s >= 1 && $s <= 5) $scores[$k] = $s;
        }
    }
    $f = [
        'observe_date'     => post('observe_date'),
        'start_time'       => post('start_time'),
        'end_time'         => post('end_time'),
        'class_group'      => mb_substr(trim(post('class_group')), 0, 150),
        'topic'            => mb_substr(trim(post('topic')), 0, 255),
        'students_total'   => post('students_total') === '' ? null : max(0, (int)post('students_total')),
        'students_present' => post('students_present') === '' ? null : max(0, (int)post('students_present')),
    ];
    $texts = [];
    foreach (['student_behavior', 'strengths', 'improvements', 'suggestions'] as $k) $texts[$k] = mb_substr(trim(post($k)), 0, 5000) ?: null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['observe_date'])) $errors[] = 'กรุณาระบุวันที่สังเกต';
    if (!preg_match('/^\d{2}:\d{2}/', $f['start_time']) || !preg_match('/^\d{2}:\d{2}/', $f['end_time'])) $errors[] = 'กรุณาระบุเวลา';
    elseif (plc_minutes($f['start_time'], $f['end_time']) <= 0) $errors[] = 'เวลาสิ้นสุดต้องหลังเวลาเริ่ม';
    if ($f['students_total'] !== null && $f['students_present'] !== null && $f['students_present'] > $f['students_total']) $errors[] = 'จำนวนผู้มาเรียนมากกว่าจำนวนทั้งหมด';

    if (!$errors) {
        $vals = [$observer, $f['observe_date'], $f['start_time'], $f['end_time'], $f['class_group'] ?: null, $f['topic'] ?: null, $f['students_total'], $f['students_present'],
                 json_encode($scores), $texts['student_behavior'], $texts['strengths'], $texts['improvements'], $texts['suggestions']];
        if ($o) {
            db_exec('UPDATE plc_observations SET observer_id=?, observe_date=?, start_time=?, end_time=?, class_group=?, topic=?, students_total=?, students_present=?, scores=?, student_behavior=?, strengths=?, improvements=?, suggestions=?, updated_at=NOW() WHERE id=?',
                'isssssiisssssi', array_merge($vals, [$o['id']]));
            $oid = (int)$o['id'];
            plc_clear_signatures('observation', $oid);
        } else {
            $oid = db_exec('INSERT INTO plc_observations (observer_id, observe_date, start_time, end_time, class_group, topic, students_total, students_present, scores, student_behavior, strengths, improvements, suggestions, problem_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                'isssssiisssssi', array_merge($vals, [$p['id']]));
        }
        log_activity($o ? 'plc_observation_edit' : 'plc_observation_add', '#' . $oid . ' ' . $p['theme']);
        flash('success', 'บันทึกแบบสังเกตการสอนแล้ว · กรุณาลงนามในฐานะผู้สังเกต');
        redirect('plc-doc.php?doc=observation&id=' . $oid);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = $_POST;
    $sc = (array)($_POST['scores'] ?? []);
} elseif ($o) {
    $v = $o;
    $sc = json_decode((string)$o['scores'], true) ?: [];
} else {
    $teach = db_row("SELECT * FROM plc_meetings WHERE problem_id = ? AND kind = 'teach' ORDER BY meeting_date DESC LIMIT 1", 'i', [$p['id']]);
    $v = ['observer_id' => $u['id'], 'observe_date' => $teach['meeting_date'] ?? date('Y-m-d'), 'start_time' => $teach['start_time'] ?? '', 'end_time' => $teach['end_time'] ?? '', 'topic' => ''];
    $sc = [];
}

$pageTitle = $o ? 'แก้ไขแบบสังเกตการสอน' : 'แบบสังเกตการสอนในชั้นเรียน';
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('plc.php') ?>">PLC</a></li>
  <li class="breadcrumb-item"><a href="<?= url('plc-group.php?id=' . $g['id']) ?>"><?= e(plc_group_title($g)) ?></a></li>
  <li class="breadcrumb-item"><a href="<?= url('plc-problem.php?id=' . $p['id']) ?>">ประเด็นปัญหา</a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($o['id'] ?? 0) ?>"><input type="hidden" name="problem" value="<?= (int)$p['id'] ?>">
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><span class="fw-semibold">ข้อมูลการสังเกต</span> <span class="text-muted small">· <?= e($p['theme']) ?></span></div>
    <div class="card-body row g-3">
      <div class="col-md-4">
        <label class="form-label">ผู้สังเกต</label>
        <?php if (is_staff()): ?>
          <select class="form-select" name="observer_id"><?php foreach ($observers as $m): ?><option value="<?= $m['user_id'] ?>"<?= (string)($v['observer_id'] ?? '') === (string)$m['user_id'] ? ' selected' : '' ?>><?= e($m['display_name']) ?></option><?php endforeach; ?></select>
        <?php else: ?>
          <input class="form-control" value="<?= e($u['fullname']) ?>" disabled>
        <?php endif; ?>
      </div>
      <div class="col-md-4"><label class="form-label">วันที่สังเกต *</label><input type="date" class="form-control" name="observe_date" required value="<?= e($v['observe_date'] ?? '') ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">เริ่ม *</label><input type="time" class="form-control" name="start_time" required value="<?= e(substr((string)($v['start_time'] ?? ''), 0, 5)) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">สิ้นสุด *</label><input type="time" class="form-control" name="end_time" required value="<?= e(substr((string)($v['end_time'] ?? ''), 0, 5)) ?>"></div>
      <div class="col-md-5"><label class="form-label">หน่วย / เรื่องที่สอน</label><input class="form-control" name="topic" maxlength="255" value="<?= e($v['topic'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">ระดับชั้น / กลุ่มเรียน</label><input class="form-control" name="class_group" maxlength="150" value="<?= e($v['class_group'] ?? '') ?>" placeholder="เช่น ปวส.1/1"></div>
      <div class="col-6 col-md-2"><label class="form-label">ผู้เรียนทั้งหมด</label><input type="number" min="0" class="form-control" name="students_total" value="<?= e($v['students_total'] ?? '') ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">มาเรียน</label><input type="number" min="0" class="form-control" name="students_present" value="<?= e($v['students_present'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">ตอนที่ 1 ผลการสังเกต</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>รายการสังเกต</th><?php foreach ($labels as $n => $l): ?><th class="text-center" style="width:72px"><?= $n ?><div class="fw-normal small"><?= e($l) ?></div></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php $i = 0; foreach ($items as $section => $list): ?>
          <tr class="table-light"><td colspan="6" class="fw-semibold small"><?= e($section) ?></td></tr>
          <?php foreach ($list as $k => $label): ?>
            <tr><td class="small"><?= ++$i ?>. <?= e($label) ?></td>
              <?php foreach (array_keys($labels) as $n): ?><td class="text-center"><input class="form-check-input" type="radio" name="scores[<?= $k ?>]" value="<?= $n ?>"<?= (int)($sc[$k] ?? 0) === $n ? ' checked' : '' ?> aria-label="<?= $n ?>"></td><?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">ตอนที่ 2 บันทึกการสังเกต</div>
    <div class="card-body">
      <?php foreach (['student_behavior' => 'พฤติกรรมการเรียนรู้ของผู้เรียนที่พบ', 'strengths' => 'จุดเด่นของการจัดการเรียนรู้', 'improvements' => 'สิ่งที่ควรปรับปรุง / พัฒนา', 'suggestions' => 'ข้อเสนอแนะ'] as $k => $label): ?>
        <div class="mb-2"><label class="form-label small mb-1"><?= e($label) ?></label><textarea class="form-control" name="<?= $k ?>" rows="3"><?= e($v[$k] ?? '') ?></textarea></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
    <a class="btn btn-outline-secondary" href="<?= url('plc-problem.php?id=' . $p['id']) ?>">ยกเลิก</a>
  </div>
</form>
<?php if ($o): ?>
  <form method="post" class="mt-3" data-confirm="ลบแบบสังเกตนี้?">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="action" value="delete">
    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>ลบแบบสังเกตนี้</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
