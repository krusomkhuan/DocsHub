<?php
/** ระบบ PLC: หน้ารวมกลุ่ม PLC งานที่รอลงนาม และชั่วโมงของฉัน */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$u = current_user();
$depts = plc_departments();
$fYear = (int)get('year') ?: current_academic_year();
$fSem = get('semester') === '' ? 0 : (int)get('semester');
$fDept = (int)get('dept');

$where = 'g.academic_year = ?';
$types = 'i';
$params = [$fYear];
if ($fSem) { $where .= ' AND g.semester = ?'; $types .= 'i'; $params[] = $fSem; }
if ($fDept) { $where .= ' AND g.department_id = ?'; $types .= 'i'; $params[] = $fDept; }
$groups = array_values(array_filter(
    db_all("SELECT g.*, d.name AS dept_name, d.code AS dept_code, d.plc_head_user_id,
                   (SELECT COUNT(*) FROM plc_members m WHERE m.group_id = g.id) AS n_members,
                   (SELECT COUNT(*) FROM plc_problems p WHERE p.group_id = g.id) AS n_problems,
                   (SELECT COUNT(*) FROM plc_meetings mt WHERE mt.group_id = g.id) AS n_meetings
            FROM plc_groups g JOIN departments d ON d.id = g.department_id
            WHERE $where ORDER BY d.sort_order, g.semester DESC, g.id DESC", $types, $params),
    'plc_can_view_group'
));

// งานที่รอฉันลงนาม (กลุ่ม/ประเด็นปัญหาที่ส่งขออนุมัติ + เอกสารที่ฉันเป็นผู้จัดทำแต่ยังไม่ลงนาม)
$todo = [];
foreach (db_all("SELECT g.*, d.name AS dept_name, d.code AS dept_code, d.plc_head_user_id FROM plc_groups g JOIN departments d ON d.id = g.department_id WHERE g.status <> 'approved' OR g.academic_year >= ?", 'i', [$fYear - 1]) as $g) {
    if (plc_signable_slots('group', $g, $g)) $todo[] = ['กลุ่ม PLC', plc_group_title($g) . ' ' . plc_term_text($g), 'plc-group.php?id=' . $g['id'], $g['status']];
    foreach (db_all("SELECT * FROM plc_problems WHERE group_id = ? AND status <> 'approved'", 'i', [$g['id']]) as $p) {
        if (plc_signable_slots('problem', $p, $g)) $todo[] = ['ประเด็นปัญหา', $p['theme'], 'plc-problem.php?id=' . $p['id'], $p['status']];
    }
    if ($u['id']) {
        foreach (db_all('SELECT * FROM plc_meetings WHERE group_id = ? AND recorder_id = ?', 'ii', [$g['id'], $u['id']]) as $mt) {
            if (plc_signable_slots('meeting', $mt, $g)) $todo[] = ['บันทึกการประชุม', $mt['topic'] . ' (' . thai_date($mt['meeting_date']) . ')', 'plc-doc.php?doc=meeting&id=' . $mt['id'], ''];
        }
        foreach (db_all('SELECT o.* FROM plc_observations o JOIN plc_problems p ON p.id = o.problem_id WHERE p.group_id = ? AND o.observer_id = ?', 'ii', [$g['id'], $u['id']]) as $o) {
            if (plc_signable_slots('observation', $o, $g)) $todo[] = ['แบบสังเกตการสอน', thai_date($o['observe_date']) . ' ' . $o['topic'], 'plc-doc.php?doc=observation&id=' . $o['id'], ''];
        }
    }
}

// ชั่วโมง PLC ของฉันในปีการศึกษาที่เลือก
$myHours = [];
foreach (db_all('SELECT g.*, d.name AS dept_name FROM plc_groups g JOIN departments d ON d.id = g.department_id JOIN plc_members m ON m.group_id = g.id
                 WHERE m.user_id = ? AND g.academic_year = ? ORDER BY g.semester', 'ii', [$u['id'], $fYear]) as $g) {
    foreach (plc_hours((int)$g['id']) as $h) {
        if ((int)$h['member']['user_id'] === (int)$u['id']) $myHours[] = [$g, $h];
    }
}

$canCreate = is_staff() || ($u['department_id'] && isset($depts[(int)$u['department_id']]));
$years = array_unique(array_merge([current_academic_year()], array_map('intval', academic_years()), array_map('intval', array_column(db_all('SELECT DISTINCT academic_year FROM plc_groups'), 'academic_year'))));
rsort($years);

$pageTitle = 'ชุมชนการเรียนรู้ทางวิชาชีพ (PLC)';
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>ชุมชนการเรียนรู้ทางวิชาชีพ (PLC)</h4>
    <div class="text-muted small">จัดตั้งกลุ่ม เสนอประเด็นปัญหา วางแผน สังเกตการสอน สะท้อนผล และพิมพ์เอกสาร PLC</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('plc-signature.php') ?>"><i class="bi bi-pen me-1"></i>ลายมือชื่อของฉัน<?= $u['signature'] ? '' : ' <span class="badge text-bg-warning">ยังไม่มี</span>' ?></a>
    <?php if ($canCreate): ?><a class="btn btn-primary btn-sm" href="<?= url('plc-group-form.php') ?>"><i class="bi bi-plus-lg me-1"></i>จัดตั้งกลุ่ม PLC</a><?php endif; ?>
  </div>
</div>

<?php if ($todo): ?>
<div class="card border-warning shadow-sm mb-3">
  <div class="card-header bg-warning-subtle fw-semibold"><i class="bi bi-bell me-1"></i>รอคุณลงนาม (<?= count($todo) ?>)</div>
  <div class="list-group list-group-flush">
    <?php foreach ($todo as [$kind, $title, $link, $st]): ?>
      <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= url($link) ?>#workflow">
        <span><span class="badge text-bg-light border me-2"><?= e($kind) ?></span><?= e($title) ?></span>
        <?= $st ? plc_status_badge($st) : '' ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<form class="row g-2 mb-3">
  <div class="col-auto"><select class="form-select form-select-sm" name="year"><?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= $y === $fYear ? ' selected' : '' ?>>ปีการศึกษา <?= $y ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><select class="form-select form-select-sm" name="semester"><option value="">ทุกภาคเรียน</option><?php foreach ([1, 2, 3] as $s): ?><option value="<?= $s ?>"<?= $s === $fSem ? ' selected' : '' ?>>ภาคเรียนที่ <?= $s ?></option><?php endforeach; ?></select></div>
  <?php if (count($depts) > 1 || is_staff()): ?>
  <div class="col-auto"><select class="form-select form-select-sm" name="dept"><option value="">ทุกแผนก</option><?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"<?= (int)$d['id'] === $fDept ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <div class="col-auto"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel"></i> กรอง</button></div>
</form>

<div class="row g-4">
  <div class="col-lg-8">
    <?php if (!$groups): ?>
      <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-people display-6 d-block mb-2"></i>ยังไม่มีกลุ่ม PLC ในปีการศึกษา <?= $fYear ?>
        <?php if ($canCreate): ?><div class="mt-3"><a class="btn btn-primary" href="<?= url('plc-group-form.php') ?>">จัดตั้งกลุ่ม PLC</a></div><?php endif; ?>
      </div></div>
    <?php endif; ?>
    <?php foreach ($groups as $g): ?>
      <a class="card shadow-sm mb-3 text-decoration-none text-body plc-card" href="<?= url('plc-group.php?id=' . $g['id']) ?>">
        <div class="card-body d-flex justify-content-between gap-3">
          <div>
            <div class="fw-semibold fs-5"><?= e(plc_group_title($g)) ?></div>
            <div class="text-muted small">ภาคเรียนที่ <?= e(plc_term_text($g)) ?><?= $g['field_name'] ? ' · สาขาวิชา' . e($g['field_name']) : '' ?><?= $g['meeting_schedule'] ? ' · ' . e($g['meeting_schedule']) : '' ?></div>
            <div class="small mt-2">
              <span class="me-3"><i class="bi bi-person me-1"></i><?= (int)$g['n_members'] ?> คน</span>
              <span class="me-3"><i class="bi bi-lightbulb me-1"></i><?= (int)$g['n_problems'] ?> ประเด็นปัญหา</span>
              <span><i class="bi bi-calendar-check me-1"></i><?= (int)$g['n_meetings'] ?> กิจกรรม</span>
            </div>
          </div>
          <div><?= plc_status_badge($g['status']) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>ชั่วโมง PLC ของฉัน (ปีการศึกษา <?= $fYear ?>)</div>
      <ul class="list-group list-group-flush">
        <?php $sum = 0; foreach ($myHours as [$g, $h]): $t = $h['meetings'] + $h['observe']; $sum += $t; ?>
          <li class="list-group-item d-flex justify-content-between small">
            <span><?= e(plc_group_title($g)) ?> <span class="text-muted">ภาคเรียน <?= (int)$g['semester'] ?></span></span>
            <span class="fw-medium"><?= e(plc_hours_text($t)) ?></span>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between fw-semibold"><span>รวม</span><span><?= e(plc_hours_text($sum)) ?></span></li>
      </ul>
      <div class="card-footer bg-white small text-muted">นับจากการประชุมที่บันทึกว่าเข้าร่วม และการสังเกตการสอนที่คุณเป็นผู้สังเกต</div>
    </div>
    <div class="card shadow-sm mt-3">
      <div class="card-body small">
        <div class="fw-semibold mb-2"><i class="bi bi-signpost-split me-1"></i>ขั้นตอนการทำ PLC</div>
        <ol class="mb-0 ps-3">
          <li>จัดตั้งกลุ่ม กำหนดสมาชิก ส่งขออนุมัติ</li>
          <li>สมาชิกแต่ละคนเสนอประเด็นปัญหา (ผลัดกันเป็น Model Teacher)</li>
          <li>บันทึกการประชุมค้นหาปัญหา / แนวทางแก้ไข / แผนปฏิบัติการ</li>
          <li>ลงมือสอน สมาชิกบันทึกแบบสังเกตการสอน</li>
          <li>ประชุมสะท้อนผล สรุปบทเรียน พิมพ์เอกสาร</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
