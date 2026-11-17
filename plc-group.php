<?php
/** รายละเอียดกลุ่ม PLC: สมาชิก ชั่วโมง ประเด็นปัญหา การประชุม ภาพ เอกสาร และการอนุมัติ */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$g = plc_group_get((int)get('id', post('id')));
if (!$g || !plc_can_view_group($g)) {
    flash('danger', 'ไม่พบกลุ่ม PLC หรือคุณไม่มีสิทธิ์ดู');
    redirect('plc.php');
}
$canManage = plc_can_manage_group($g);
$canContribute = plc_can_contribute($g);
$self = 'plc-group.php?id=' . $g['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (post('wf') !== '') {
            flash('success', plc_handle_workflow('group', $g, $g, $canManage));
        } elseif (post('action') === 'photos' && $canContribute) {
            $saved = 0;
            foreach (normalize_files($_FILES['photos'] ?? ['name' => [], 'error' => []]) as $i => $file) {
                [$name] = trip_save_image($file, 'plc', 'plc', 'plc_');
                db_exec('INSERT INTO plc_photos (group_id, problem_id, file_name, caption, sort_order, created_by) VALUES (?,?,?,?,?,?)', 'iissii',
                    [$g['id'], (int)post('problem_id') ?: null, $name, mb_substr(trim(post('caption')), 0, 255) ?: null, 99, current_user()['id']]);
                $saved++;
            }
            flash($saved ? 'success' : 'warning', $saved ? 'เพิ่มภาพแล้ว ' . $saved . ' ภาพ' : 'กรุณาเลือกภาพ');
        } elseif (post('action') === 'del_photo' && $canContribute) {
            $ph = db_row('SELECT * FROM plc_photos WHERE id = ? AND group_id = ?', 'ii', [(int)post('photo_id'), $g['id']]);
            if ($ph) { plc_delete_photo($ph); flash('success', 'ลบภาพแล้ว'); }
        } elseif (post('action') === 'delete' && $canManage) {
            if ($g['status'] === 'approved' && !is_staff()) throw new RuntimeException('กลุ่มที่อนุมัติแล้ว ลบได้เฉพาะเจ้าหน้าที่');
            plc_delete_photos_where('group_id', (int)$g['id']);
            foreach (db_all('SELECT id FROM plc_problems WHERE group_id = ?', 'i', [$g['id']]) as $p) {
                db_exec("DELETE FROM plc_signatures WHERE entity = 'problem' AND entity_id = ?", 'i', [$p['id']]);
                db_exec("DELETE s FROM plc_signatures s JOIN plc_observations o ON o.id = s.entity_id WHERE s.entity = 'observation' AND o.problem_id = ?", 'i', [$p['id']]);
            }
            db_exec("DELETE s FROM plc_signatures s JOIN plc_meetings m ON m.id = s.entity_id WHERE s.entity = 'meeting' AND m.group_id = ?", 'i', [$g['id']]);
            db_exec("DELETE FROM plc_signatures WHERE entity = 'group' AND entity_id = ?", 'i', [$g['id']]);
            db_exec('DELETE FROM plc_groups WHERE id = ?', 'i', [$g['id']]);
            log_activity('plc_group_delete', '#' . $g['id'] . ' ' . plc_group_title($g) . ' ' . plc_term_text($g));
            flash('success', 'ลบกลุ่ม PLC แล้ว');
            redirect('plc.php');
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect($self);
}

$members = plc_members((int)$g['id']);
$hours = plc_hours((int)$g['id']);
$problems = db_all('SELECT p.*, u.fullname AS model_name,
                           (SELECT COUNT(*) FROM plc_observations o WHERE o.problem_id = p.id) AS n_obs,
                           (SELECT COUNT(*) FROM plc_meetings m WHERE m.problem_id = p.id) AS n_meet
                    FROM plc_problems p LEFT JOIN users u ON u.id = p.model_teacher_id WHERE p.group_id = ? ORDER BY p.id', 'i', [$g['id']]);
$meetings = db_all('SELECT m.*, p.theme, (SELECT COUNT(*) FROM plc_attendees a WHERE a.meeting_id = m.id) AS n_att
                    FROM plc_meetings m LEFT JOIN plc_problems p ON p.id = m.problem_id WHERE m.group_id = ? ORDER BY m.meeting_date DESC, m.start_time DESC', 'i', [$g['id']]);
$photos = db_all('SELECT * FROM plc_photos WHERE group_id = ? AND problem_id IS NULL ORDER BY sort_order, id', 'i', [$g['id']]);
$roles = plc_committee_roles();
$kinds = plc_meeting_kinds();

$pageTitle = plc_group_title($g) . ' ' . plc_term_text($g);
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('plc.php?year=' . $g['academic_year']) ?>">PLC</a></li>
  <li class="breadcrumb-item active"><?= e(plc_group_title($g)) ?> ภาคเรียนที่ <?= e(plc_term_text($g)) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="mb-1"><?= e(plc_group_title($g)) ?> <?= plc_status_badge($g['status']) ?></h4>
    <div class="text-muted small">ภาคเรียนที่ <?= e(plc_term_text($g)) ?><?= $g['field_name'] ? ' · สาขาวิชา' . e($g['field_name']) : '' ?>
      <?= $g['meeting_schedule'] ? ' · <i class="bi bi-clock"></i> ' . e($g['meeting_schedule']) : '' ?><?= $g['meeting_place'] ? ' · <i class="bi bi-geo-alt"></i> ' . e($g['meeting_place']) : '' ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canManage && $g['status'] === 'draft'): ?>
      <a class="btn btn-outline-primary btn-sm" href="<?= url('plc-group-form.php?id=' . $g['id']) ?>"><i class="bi bi-pencil me-1"></i>แก้ไขกลุ่ม/สมาชิก</a>
    <?php endif; ?>
    <?php if ($canManage): ?>
      <form method="post" data-confirm="ลบกลุ่ม PLC นี้พร้อมประเด็นปัญหา การประชุม แบบสังเกต และภาพทั้งหมด? ย้อนกลับไม่ได้">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- ประเด็นปัญหา -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-lightbulb me-1"></i>ประเด็นปัญหา (Model Teacher)</span>
        <?php if ($canContribute): ?><a class="btn btn-primary btn-sm" href="<?= url('plc-problem-form.php?group=' . $g['id']) ?>"><i class="bi bi-plus-lg me-1"></i>เสนอประเด็นปัญหา</a><?php endif; ?>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($problems as $p): ?>
          <a class="list-group-item list-group-item-action" href="<?= url('plc-problem.php?id=' . $p['id']) ?>">
            <div class="d-flex justify-content-between gap-2">
              <div class="fw-medium"><?= e($p['theme']) ?></div><div><?= plc_status_badge($p['status']) ?></div>
            </div>
            <div class="small text-muted">Model Teacher: <?= e($p['model_name'] ?? '-') ?><?= $p['subject_name'] ? ' · ' . e($p['subject_name']) : '' ?> · <?= (int)$p['n_meet'] ?> กิจกรรม · <?= (int)$p['n_obs'] ?> แบบสังเกต</div>
          </a>
        <?php endforeach; ?>
        <?php if (!$problems): ?><div class="list-group-item text-muted small">ยังไม่มีประเด็นปัญหา · สมาชิกแต่ละคนผลัดกันเป็นเจ้าของปัญหา (Model Teacher) ได้ กลุ่มหนึ่งมีได้หลายประเด็น</div><?php endif; ?>
      </div>
    </div>

    <!-- การประชุม -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-calendar-check me-1"></i>การประชุม / กิจกรรม PLC</span>
        <?php if ($canContribute): ?><a class="btn btn-outline-primary btn-sm" href="<?= url('plc-meeting-form.php?group=' . $g['id']) ?>"><i class="bi bi-plus-lg me-1"></i>บันทึกการประชุม</a><?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
          <thead class="table-light"><tr><th>วันที่</th><th>กิจกรรม</th><th class="text-center">เวลา</th><th class="text-center">ผู้เข้าร่วม</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($meetings as $m): ?>
            <tr>
              <td class="text-nowrap"><?= thai_date($m['meeting_date']) ?></td>
              <td><span class="badge text-bg-light border"><?= e($kinds[$m['kind']]) ?></span> <?= e($m['topic']) ?><?= $m['theme'] ? '<div class="text-muted">' . e(mb_strimwidth($m['theme'], 0, 70, '…')) . '</div>' : '' ?></td>
              <td class="text-center text-nowrap"><?= substr($m['start_time'], 0, 5) ?>–<?= substr($m['end_time'], 0, 5) ?><div class="text-muted"><?= e(plc_hours_text(plc_minutes($m['start_time'], $m['end_time']))) ?></div></td>
              <td class="text-center"><?= (int)$m['n_att'] ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('plc-doc.php?doc=meeting&id=' . $m['id']) ?>" title="ดู/พิมพ์"><i class="bi bi-file-earmark-text"></i></a>
                <?php if ($canContribute): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('plc-meeting-form.php?id=' . $m['id']) ?>" title="แก้ไข"><i class="bi bi-pencil"></i></a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$meetings): ?><tr><td colspan="5" class="text-muted">ยังไม่มีการบันทึกการประชุม · ชั่วโมง PLC นับจากการประชุมที่ติ๊กผู้เข้าร่วม</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ภาพประกอบระดับกลุ่ม -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-images me-1"></i>ภาพประกอบการประชุมกลุ่ม</div>
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
            <div class="col-md-5"><input class="form-control form-control-sm" name="caption" maxlength="255" placeholder="คำบรรยายภาพ เช่น สมาชิกกลุ่มร่วมประชุมจัดตั้งกลุ่ม"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">อัปโหลด</button></div>
          </form>
        <?php elseif (!$photos): ?><div class="text-muted small">ยังไม่มีภาพ</div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <?php $wfEntity = 'group'; $wfRow = $g; $wfGroup = $g; $wfCanEdit = $canManage; require __DIR__ . '/includes/plc_workflow.php'; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-printer me-1"></i>เอกสารของกลุ่ม</div>
      <div class="list-group list-group-flush small">
        <?php foreach (['group-memo' => 'บันทึกข้อความขออนุญาตจัดตั้งกลุ่ม', 'group-request' => 'แบบคำร้องขอจัดตั้งกลุ่ม', 'group-announce' => 'ประกาศวิทยาลัย', 'group-all' => 'ชุดเอกสารจัดตั้งกลุ่ม (3 ฉบับ)', 'hours' => 'สรุปชั่วโมง PLC ของสมาชิก'] as $doc => $label): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <a href="<?= url('plc-doc.php?doc=' . $doc . '&id=' . $g['id']) ?>"><?= e($label) ?></a>
            <a class="btn btn-sm btn-outline-secondary py-0" href="<?= url('plc-doc.php?doc=' . $doc . '&id=' . $g['id'] . '&format=word') ?>" title="ดาวน์โหลด Word"><i class="bi bi-file-earmark-word"></i></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i>สมาชิกและชั่วโมง PLC</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($hours as $h): $m = $h['member']; ?>
          <li class="list-group-item d-flex justify-content-between gap-2">
            <div>
              <div class="fw-medium"><?= e($m['display_name'] ?: '(ไม่พบผู้ใช้)') ?><?= $m['user_id'] && !$m['signature'] ? ' <i class="bi bi-pen text-warning" title="ยังไม่ได้เพิ่มลายมือชื่อ"></i>' : '' ?></div>
              <div class="text-muted"><?= e($roles[$m['committee_role']]) ?><?= $m['position_title'] ? ' · ' . e($m['position_title']) : '' ?></div>
            </div>
            <div class="text-end text-nowrap"><?= e(plc_hours_text($h['meetings'] + $h['observe'])) ?><div class="text-muted"><?= (int)$h['count'] ?> ครั้ง</div></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
