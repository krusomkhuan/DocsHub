<?php
/** ประเด็นปัญหา (วงจร PLC ของ Model Teacher): ข้อมูล แผนปฏิบัติการ การประชุม การสังเกตการสอน ภาพ เอกสาร และการอนุมัติ */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$p = db_row('SELECT p.*, u.fullname AS model_name FROM plc_problems p LEFT JOIN users u ON u.id = p.model_teacher_id WHERE p.id = ?', 'i', [(int)get('id', post('id'))]);
$g = $p ? plc_group_get((int)$p['group_id']) : null;
if (!$p || !$g || !plc_can_view_group($g)) {
    flash('danger', 'ไม่พบประเด็นปัญหา หรือคุณไม่มีสิทธิ์ดู');
    redirect('plc.php');
}
$canContribute = plc_can_contribute($g);
$self = 'plc-problem.php?id=' . $p['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (post('wf') !== '') {
            flash('success', plc_handle_workflow('problem', $p, $g, $canContribute));
        } elseif (post('action') === 'photos' && $canContribute) {
            $saved = 0;
            foreach (normalize_files($_FILES['photos'] ?? ['name' => [], 'error' => []]) as $file) {
                [$name] = trip_save_image($file, 'plc', 'plc', 'plc_');
                db_exec('INSERT INTO plc_photos (group_id, problem_id, file_name, caption, sort_order, created_by) VALUES (?,?,?,?,?,?)', 'iissii',
                    [$g['id'], $p['id'], $name, mb_substr(trim(post('caption')), 0, 255) ?: null, 99, current_user()['id']]);
                $saved++;
            }
            flash($saved ? 'success' : 'warning', $saved ? 'เพิ่มภาพแล้ว ' . $saved . ' ภาพ' : 'กรุณาเลือกภาพ');
        } elseif (post('action') === 'del_photo' && $canContribute) {
            $ph = db_row('SELECT * FROM plc_photos WHERE id = ? AND problem_id = ?', 'ii', [(int)post('photo_id'), $p['id']]);
            if ($ph) { plc_delete_photo($ph); flash('success', 'ลบภาพแล้ว'); }
        } elseif (post('action') === 'delete' && $canContribute) {
            if ($p['status'] !== 'draft' && !is_staff()) throw new RuntimeException('ต้องถอนกลับเป็นร่างก่อนจึงจะลบได้');
            plc_delete_photos_where('problem_id', (int)$p['id']);
            db_exec("DELETE s FROM plc_signatures s JOIN plc_observations o ON o.id = s.entity_id WHERE s.entity = 'observation' AND o.problem_id = ?", 'i', [$p['id']]);
            db_exec("DELETE FROM plc_signatures WHERE entity = 'problem' AND entity_id = ?", 'i', [$p['id']]);
            db_exec('DELETE FROM plc_problems WHERE id = ?', 'i', [$p['id']]); // การประชุมที่ผูกไว้ยังอยู่ในกลุ่ม (problem_id = NULL)
            log_activity('plc_problem_delete', '#' . $p['id'] . ' ' . $p['theme']);
            flash('success', 'ลบประเด็นปัญหาแล้ว');
            redirect('plc-group.php?id=' . $g['id']);
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect($self);
}

$issues = db_all('SELECT * FROM plc_issues WHERE problem_id = ? ORDER BY priority, id', 'i', [$p['id']]);
$steps = db_all('SELECT * FROM plc_plan_steps WHERE problem_id = ? ORDER BY step_no', 'i', [$p['id']]);
$meetings = db_all('SELECT m.*, (SELECT COUNT(*) FROM plc_attendees a WHERE a.meeting_id = m.id) AS n_att FROM plc_meetings m WHERE m.problem_id = ? ORDER BY m.meeting_date, m.start_time', 'i', [$p['id']]);
$doneSteps = array_map('intval', array_filter(array_column($meetings, 'step_no')));
$observations = db_all('SELECT o.*, u.fullname AS observer FROM plc_observations o LEFT JOIN users u ON u.id = o.observer_id WHERE o.problem_id = ? ORDER BY o.observe_date, o.id', 'i', [$p['id']]);
$photos = db_all('SELECT * FROM plc_photos WHERE problem_id = ? ORDER BY sort_order, id', 'i', [$p['id']]);
$kinds = plc_meeting_kinds();
$editable = $canContribute && $p['status'] === 'draft';

$pageTitle = $p['theme'];
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('plc.php') ?>">PLC</a></li>
  <li class="breadcrumb-item"><a href="<?= url('plc-group.php?id=' . $g['id']) ?>"><?= e(plc_group_title($g)) ?> <?= e(plc_term_text($g)) ?></a></li>
  <li class="breadcrumb-item active text-truncate" style="max-width:320px">ประเด็นปัญหา</li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h5 class="mb-1"><?= e($p['theme']) ?> <?= plc_status_badge($p['status']) ?></h5>
    <div class="text-muted small">Model Teacher: <strong><?= e($p['model_name'] ?? '-') ?></strong><?= $p['subject_name'] ? ' · รายวิชา' . e($p['subject_name']) : '' ?>
      <?= $p['plan_start'] ? ' · แผน ' . thai_date($p['plan_start']) . ' – ' . thai_date($p['plan_end']) : '' ?></div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($editable): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('plc-problem-form.php?id=' . $p['id']) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</a><?php endif; ?>
    <?php if ($canContribute && ($p['status'] === 'draft' || is_staff())): ?>
      <form method="post" data-confirm="ลบประเด็นปัญหานี้พร้อมแบบสังเกตและภาพ? (บันทึกการประชุมยังอยู่ในกลุ่ม)">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-1"></i>แผนปฏิบัติการ (PLC-AP) และความคืบหน้า</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
          <thead class="table-light"><tr><th>ขั้น</th><th>กิจกรรม</th><th>บทบาท</th><th>กำหนด</th><th class="text-center">บันทึกแล้ว</th></tr></thead>
          <tbody>
          <?php foreach ($steps as $s): $done = in_array((int)$s['step_no'], $doneSteps, true); ?>
            <tr>
              <td><?= (int)$s['step_no'] ?></td>
              <td><?= e($s['activity']) ?></td>
              <td class="text-muted" style="white-space:pre-line"><?= e($s['role_text']) ?></td>
              <td class="text-nowrap"><?= $s['plan_date'] ? thai_date($s['plan_date']) : '-' ?><?= $s['minutes'] ? '<div class="text-muted">' . (int)$s['minutes'] . ' นาที</div>' : '' ?></td>
              <td class="text-center">
                <?php if ($done): ?><i class="bi bi-check-circle-fill text-success"></i>
                <?php elseif ($canContribute): ?><a class="btn btn-sm btn-outline-primary py-0" href="<?= url('plc-meeting-form.php?problem=' . $p['id'] . '&step=' . $s['step_no']) ?>">บันทึก</a>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$steps): ?><tr><td colspan="5" class="text-muted">ยังไม่มีแผนปฏิบัติการ</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-calendar-check me-1"></i>การประชุม / กิจกรรม / สะท้อนผล</span>
        <?php if ($canContribute): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('plc-meeting-form.php?problem=' . $p['id']) ?>"><i class="bi bi-plus-lg me-1"></i>บันทึก</a><?php endif; ?>
      </div>
      <div class="list-group list-group-flush small">
        <?php foreach ($meetings as $m): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <div><span class="badge text-bg-light border"><?= e($kinds[$m['kind']]) ?></span> <?= e($m['topic']) ?>
              <div class="text-muted"><?= thai_date($m['meeting_date']) ?> <?= substr($m['start_time'], 0, 5) ?>–<?= substr($m['end_time'], 0, 5) ?> · ผู้เข้าร่วม <?= (int)$m['n_att'] ?> คน<?= $m['step_no'] ? ' · ขั้นที่ ' . (int)$m['step_no'] : '' ?></div></div>
            <div class="text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('plc-doc.php?doc=meeting&id=' . $m['id']) ?>"><i class="bi bi-file-earmark-text"></i></a>
              <?php if ($canContribute): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('plc-meeting-form.php?id=' . $m['id']) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$meetings): ?><div class="list-group-item text-muted">ยังไม่มี · เริ่มจากบันทึก "ประชุมค้นหาปัญหา" และ "ประชุมหาแนวทางแก้ไขปัญหา" (ใช้ในแบบบันทึก)</div><?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-eye me-1"></i>การสังเกตการสอนในชั้นเรียน</span>
        <?php if ($canContribute): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('plc-observation-form.php?problem=' . $p['id']) ?>"><i class="bi bi-plus-lg me-1"></i>บันทึกแบบสังเกต</a><?php endif; ?>
      </div>
      <div class="list-group list-group-flush small">
        <?php foreach ($observations as $o):
            $sc = array_filter(array_map('intval', json_decode((string)$o['scores'], true) ?: []));
            $canEditObs = is_staff() || (int)$o['observer_id'] === (int)current_user()['id']; ?>
          <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <div><?= e($o['observer'] ?? '-') ?> <span class="text-muted">· <?= thai_date($o['observe_date']) ?> <?= substr($o['start_time'], 0, 5) ?>–<?= substr($o['end_time'], 0, 5) ?><?= $o['class_group'] ? ' · ' . e($o['class_group']) : '' ?></span>
              <?php if ($sc): ?><div class="text-muted">คะแนนเฉลี่ย <?= number_format(array_sum($sc) / count($sc), 2) ?></div><?php endif; ?></div>
            <div class="text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('plc-doc.php?doc=observation&id=' . $o['id']) ?>"><i class="bi bi-file-earmark-text"></i></a>
              <?php if ($canEditObs): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('plc-observation-form.php?id=' . $o['id']) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$observations): ?><div class="list-group-item text-muted">ยังไม่มี · สมาชิกแต่ละคนบันทึกแบบสังเกตของตนเอง (นับเป็นชั่วโมง PLC ของผู้สังเกต)</div><?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-images me-1"></i>ภาพประกอบ</div>
      <div class="card-body">
        <?php if ($photos): ?>
          <div class="row g-2 mb-3">
            <?php foreach ($photos as $ph): ?>
              <div class="col-6 col-md-3">
                <a href="<?= url('plc-file.php?photo=' . $ph['id']) ?>" target="_blank"><img class="img-fluid rounded border w-100" style="aspect-ratio:4/3;object-fit:cover" src="<?= url('plc-file.php?photo=' . $ph['id'] . '&thumb=1') ?>" alt="" loading="lazy"></a>
                <div class="small text-muted d-flex justify-content-between gap-1"><span class="text-truncate"><?= e($ph['caption'] ?? '') ?></span>
                  <?php if ($canContribute): ?><form method="post" data-confirm="ลบภาพนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="del_photo"><input type="hidden" name="photo_id" value="<?= $ph['id'] ?>"><button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button></form><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($canContribute): ?>
          <form method="post" enctype="multipart/form-data" class="row g-2">
            <?= csrf_field() ?><input type="hidden" name="action" value="photos">
            <div class="col-md-5"><input type="file" class="form-control form-control-sm" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
            <div class="col-md-5"><input class="form-control form-control-sm" name="caption" maxlength="255" placeholder="คำบรรยายภาพ เช่น สมาชิกกลุ่มร่วมประชุมเพื่อจัดทำแผนปฏิบัติการ"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">อัปโหลด</button></div>
          </form>
        <?php elseif (!$photos): ?><div class="text-muted small">ยังไม่มีภาพ</div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <?php $wfEntity = 'problem'; $wfRow = $p; $wfGroup = $g; $wfCanEdit = $canContribute; require __DIR__ . '/includes/plc_workflow.php'; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-printer me-1"></i>เอกสาร (พิมพ์ / PDF / Word)</div>
      <div class="list-group list-group-flush small">
        <?php foreach (['problem-proposal' => 'แบบคำร้องขอเสนอประเด็นปัญหา', 'problem-memo' => 'บันทึกข้อความส่งแบบบันทึก', 'problem-finding' => 'แบบบันทึกการค้นหาปัญหา',
                        'problem-solution' => 'แบบบันทึกแนวทางแก้ไขปัญหา', 'problem-plan' => 'แผนปฏิบัติการ (PLC-AP)', 'problem-photos' => 'รูปภาพประกอบ', 'problem-all' => 'ชุดเอกสารทั้งหมด (รวมแบบสังเกตและสะท้อนผล)'] as $doc => $label): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <a href="<?= url('plc-doc.php?doc=' . $doc . '&id=' . $p['id']) ?>"><?= e($label) ?></a>
            <a class="btn btn-sm btn-outline-secondary py-0" href="<?= url('plc-doc.php?doc=' . $doc . '&id=' . $p['id'] . '&format=word') ?>" title="ดาวน์โหลด Word"><i class="bi bi-file-earmark-word"></i></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($issues): ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ol me-1"></i>ปัญหาที่กลุ่มคัดเลือก</div>
      <ol class="list-group list-group-flush list-group-numbered small">
        <?php foreach ($issues as $it): ?><li class="list-group-item"><?= e($it['issue']) ?> <span class="badge text-bg-light border">ลำดับ <?= (int)$it['priority'] ?></span></li><?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
