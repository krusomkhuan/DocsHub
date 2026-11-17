<?php
/** ลายมือชื่อของฉัน (ใช้ในเอกสาร PLC เมื่อกดลงนาม/อนุมัติ) และตำแหน่งที่แสดงใต้ชื่อ */
require_once __DIR__ . '/includes/bootstrap.php';
require_plc_access();

$u = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (post('action') === 'position') {
            $pos = mb_substr(trim(preg_replace('/\s+/u', ' ', post('position'))), 0, 100);
            db_exec('UPDATE users SET position = ? WHERE id = ?', 'si', [$pos ?: null, $u['id']]);
            flash('success', 'บันทึกตำแหน่งแล้ว');
        } elseif (post('action') === 'delete') {
            if ($u['signature'] && ($path = plc_signature_path($u['signature']))) @unlink($path);
            db_exec('UPDATE users SET signature = NULL WHERE id = ?', 'i', [$u['id']]);
            log_activity('plc_signature_delete');
            flash('success', 'ลบลายมือชื่อแล้ว (เอกสารที่ลงนามไปแล้วจะแสดงเป็นเส้นประ)');
        } else {
            plc_save_signature((int)$u['id'], $_FILES['file'] ?? null, (string)post('drawn'));
            log_activity('plc_signature_set');
            flash('success', 'บันทึกลายมือชื่อแล้ว');
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('plc-signature.php');
}

$pageTitle = 'ลายมือชื่อของฉัน';
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('plc.php') ?>">PLC</a></li>
  <li class="breadcrumb-item active">ลายมือชื่อของฉัน</li>
</ol></nav>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-pen me-1"></i>เซ็นชื่อบนหน้าจอ</div>
      <div class="card-body">
        <div class="border rounded bg-white mb-2" style="touch-action:none"><canvas id="sigPad" style="width:100%;height:200px;display:block;cursor:crosshair"></canvas></div>
        <form method="post" id="drawForm" class="d-flex gap-2">
          <?= csrf_field() ?><input type="hidden" name="drawn" id="drawn">
          <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save me-1"></i>บันทึกลายมือชื่อนี้</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" id="sigClear">ล้าง</button>
        </form>
        <div class="form-text">ใช้นิ้วหรือปากกาบนมือถือ/แท็บเล็ต หรือใช้เมาส์ · ระบบตัดขอบว่างและเก็บพื้นหลังโปร่งใสให้</div>
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-upload me-1"></i>หรืออัปโหลดภาพลายมือชื่อ</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
          <?= csrf_field() ?>
          <input type="file" class="form-control form-control-sm w-auto" name="file" accept="image/png,image/jpeg" required>
          <button class="btn btn-outline-primary btn-sm">อัปโหลด</button>
        </form>
        <div class="form-text">PNG พื้นหลังโปร่งใสจะสวยที่สุด (ไม่เกิน 2 MB) · เซ็นบนกระดาษขาวแล้วถ่ายภาพ/สแกนก็ได้</div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">ลายมือชื่อปัจจุบัน</div>
      <div class="card-body text-center">
        <?php if ($u['signature'] && plc_signature_path($u['signature'])): ?>
          <img src="<?= url('plc-file.php?sig=' . $u['id'] . '&v=' . substr(md5($u['signature']), 0, 8)) ?>" alt="ลายมือชื่อ" style="max-height:90px;max-width:100%">
          <form method="post" class="mt-3" data-confirm="ลบลายมือชื่อ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>ลบลายมือชื่อ</button></form>
        <?php else: ?>
          <div class="text-muted py-4">ยังไม่มีลายมือชื่อ</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">ตำแหน่ง (แสดงใต้ชื่อในเอกสาร)</div>
      <div class="card-body">
        <form method="post" class="d-flex gap-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="position">
          <input class="form-control form-control-sm" name="position" maxlength="100" value="<?= e($u['position'] ?? '') ?>" placeholder="เช่น ครู คศ.1 / ครูอัตราจ้าง">
          <button class="btn btn-outline-primary btn-sm text-nowrap">บันทึก</button>
        </form>
      </div>
    </div>
    <div class="alert alert-light border small">
      <i class="bi bi-shield-lock me-1"></i>ลายมือชื่อจะปรากฏในเอกสาร PLC <strong>เฉพาะเมื่อคุณกด "ลงนาม" หรือ "อนุมัติ" ในเอกสารนั้นเอง</strong> · เห็นได้เฉพาะสมาชิกกลุ่ม PLC เดียวกัน ผู้อนุมัติ และเจ้าหน้าที่ · ลบได้ทุกเมื่อ
    </div>
  </div>
</div>
<script>
(function () {
  var c = document.getElementById('sigPad'), ctx = c.getContext('2d'), drawing = false, dirty = false;
  function size() {
    var r = c.getBoundingClientRect(), d = window.devicePixelRatio || 1;
    c.width = r.width * d; c.height = r.height * d;
    ctx.setTransform(d, 0, 0, d, 0, 0);
    ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1f66';
    dirty = false;
  }
  function pos(e) { var r = c.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; }
  c.addEventListener('pointerdown', function (e) { drawing = true; c.setPointerCapture(e.pointerId); var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
  c.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); dirty = true; });
  ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) { c.addEventListener(t, function () { drawing = false; }); });
  document.getElementById('sigClear').onclick = function () { ctx.clearRect(0, 0, c.width, c.height); dirty = false; };
  // ตัดขอบว่างรอบลายเซ็นก่อนส่ง
  document.getElementById('drawForm').addEventListener('submit', function (e) {
    if (!dirty) { e.preventDefault(); alert('กรุณาเซ็นชื่อในกรอบก่อน'); return; }
    var d = ctx.getImageData(0, 0, c.width, c.height).data, x1 = c.width, y1 = c.height, x2 = 0, y2 = 0;
    for (var y = 0; y < c.height; y++) for (var x = 0; x < c.width; x++) {
      if (d[(y * c.width + x) * 4 + 3] > 0) { if (x < x1) x1 = x; if (x > x2) x2 = x; if (y < y1) y1 = y; if (y > y2) y2 = y; }
    }
    var pad = 6, w = x2 - x1 + pad * 2, h = y2 - y1 + pad * 2, out = document.createElement('canvas');
    out.width = w; out.height = h;
    out.getContext('2d').drawImage(c, x1 - pad, y1 - pad, w, h, 0, 0, w, h);
    document.getElementById('drawn').value = out.toDataURL('image/png');
  });
  window.addEventListener('resize', size);
  size();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
