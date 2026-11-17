<?php
/**
 * การ์ดลงนาม/อนุมัติของเอกสาร PLC
 * ต้องกำหนด $wfEntity ('group'|'problem'|'meeting'|'observation'), $wfRow, $wfGroup, $wfCanEdit ก่อน include
 */
$wfSlots = plc_slots($wfEntity, $wfRow, $wfGroup);
$wfSigs = plc_signatures($wfEntity, (int)$wfRow['id']);
$wfMine = plc_signable_slots($wfEntity, $wfRow, $wfGroup);
$wfPending = plc_pending_slot($wfEntity, $wfRow, $wfGroup);
$wfHasStatus = isset($wfRow['status']);
$wfStatus = $wfRow['status'] ?? '';
$wfMeHasSig = !empty(current_user()['signature']);
?>
<div class="card shadow-sm mb-3 d-print-none" id="workflow">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><i class="bi bi-pen me-1"></i>การลงนาม<?= $wfHasStatus ? ' / อนุมัติ' : '' ?></span>
    <?= $wfHasStatus ? plc_status_badge($wfStatus) : '' ?>
  </div>
  <div class="card-body">
    <?php if (!empty($wfRow['reject_note'])): ?>
      <div class="alert alert-warning py-2 small"><i class="bi bi-arrow-return-left me-1"></i>ส่งกลับแก้ไข: <?= e($wfRow['reject_note']) ?></div>
    <?php endif; ?>
    <ul class="list-group list-group-flush small mb-2">
      <?php foreach ($wfSlots as $key => $s): $sg = $wfSigs[$key] ?? null; ?>
        <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-medium"><?= e($s['label']) ?></div>
            <div class="text-muted"><?= e($s['name'] ?: 'ยังไม่ได้กำหนด') ?><?= !$s['user_id'] && $s['name'] ? ' · ลงนามในเอกสารกระดาษ' : '' ?></div>
            <?php if ($sg && $sg['comment']): ?><div class="text-success">“<?= e($sg['comment']) ?>”</div><?php endif; ?>
          </div>
          <div class="text-end text-nowrap">
            <?php if ($sg): ?>
              <span class="text-success"><i class="bi bi-check-circle-fill"></i> <?= $s['type'] === 'approve' ? 'อนุมัติ' : 'ลงนาม' ?>แล้ว</span>
              <div class="text-muted" style="font-size:.75rem"><?= thai_date($sg['signed_at'], true, true) ?></div>
            <?php elseif ($key === $wfPending): ?>
              <span class="badge text-bg-warning">รอลงนาม</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($wfMine): ?>
      <?php if (!$wfMeHasSig): ?>
        <div class="alert alert-info py-2 small mb-2">คุณมีเอกสารรอลงนาม กรุณา<a href="<?= url('plc-signature.php') ?>">เพิ่มลายมือชื่อ</a>ก่อน</div>
      <?php else: ?>
        <form method="post" class="border rounded p-2 bg-light mb-2">
          <?= csrf_field() ?><input type="hidden" name="wf" value="sign">
          <?php $first = $wfSlots[$wfMine[0]]; ?>
          <div class="small mb-1">ลงนามในฐานะ:
            <select name="slot" class="form-select form-select-sm d-inline-block w-auto">
              <?php foreach ($wfMine as $k): ?><option value="<?= e($k) ?>"><?= e($wfSlots[$k]['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php if ($first['type'] === 'approve'): ?>
            <input class="form-control form-control-sm mb-2" name="comment" maxlength="500" placeholder="ความเห็น (เช่น เห็นควรอนุมัติ / อนุญาต) — ใช้เป็นเหตุผลเมื่อส่งกลับแก้ไขด้วย">
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm"><i class="bi bi-pen me-1"></i><?= $first['type'] === 'approve' ? 'อนุมัติและลงนาม' : 'ลงนาม' ?></button>
            <?php if ($first['type'] === 'approve'): ?>
              <button class="btn btn-outline-danger btn-sm" name="wf" value="reject" data-confirm-reject><i class="bi bi-arrow-return-left me-1"></i>ส่งกลับแก้ไข</button>
            <?php endif; ?>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($wfHasStatus): ?>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($wfCanEdit && $wfStatus === 'draft'): ?>
          <form method="post" data-confirm="ส่งขออนุมัติ? ระหว่างรออนุมัติจะแก้ไขข้อมูลไม่ได้">
            <?= csrf_field() ?><input type="hidden" name="wf" value="submit">
            <button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>ส่งขออนุมัติ</button>
          </form>
        <?php endif; ?>
        <?php if (($wfCanEdit && $wfStatus === 'submitted') || (is_staff() && $wfStatus !== 'draft')): ?>
          <form method="post" data-confirm="ถอนกลับเป็นร่างเพื่อแก้ไข? การอนุมัติที่มีอยู่จะถูกล้าง">
            <?= csrf_field() ?><input type="hidden" name="wf" value="withdraw">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>ถอนกลับเป็นร่าง</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="form-text">ร่าง → ส่งขออนุมัติ → หัวหน้าแผนก → รองผู้อำนวยการฝ่ายวิชาการ → ผู้อำนวยการ (ผู้ที่ไม่มีบัญชีในระบบ ลงนามในเอกสารกระดาษ)</div>
    <?php endif; ?>
  </div>
</div>
<script>
document.querySelectorAll('[data-confirm-reject]').forEach(function (b) {
  b.addEventListener('click', function (e) {
    var c = b.form.querySelector('[name=comment]');
    if (!c.value.trim()) { e.preventDefault(); c.focus(); c.placeholder = 'กรุณาระบุเหตุผลที่ส่งกลับแก้ไข'; c.classList.add('is-invalid'); }
  });
});
</script>
