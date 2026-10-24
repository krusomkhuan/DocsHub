<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/system_check.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'baseline') {
    csrf_check();
    if (!password_verify((string)($_POST['password'] ?? ''), current_user()['password_hash'])) {
        flash('danger', 'รหัสผ่านไม่ถูกต้อง');
    } else {
        $n = sc_save_integrity();
        log_activity('integrity_baseline', $n . ' ไฟล์ v' . APP_VERSION);
        flash('success', 'บันทึกค่าอ้างอิงไฟล์โปรแกรม ' . $n . ' ไฟล์แล้ว');
    }
    redirect('admin/system-check.php');
}

$started = microtime(true);
$results = run_system_checks();
$elapsed = round(microtime(true) - $started, 2);
$sum = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
foreach ($results as $items) foreach ($items as $it) $sum[$it['status']]++;
log_activity('system_check', "ผ่าน {$sum['ok']} เตือน {$sum['warn']} ไม่ผ่าน {$sum['fail']}");
$statusIcon = ['ok' => 'bi-check-circle-fill text-success', 'warn' => 'bi-exclamation-triangle-fill text-warning', 'fail' => 'bi-x-octagon-fill text-danger', 'info' => 'bi-info-circle text-secondary'];

$pageTitle = 'ตรวจสอบระบบ';
$activeMenu = 'syscheck';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <div class="fs-5 fw-semibold">
      <?php if ($sum['fail']): ?><span class="text-danger"><i class="bi bi-x-octagon-fill me-1"></i>พบปัญหาที่ต้องแก้ไข <?= $sum['fail'] ?> รายการ</span>
      <?php elseif ($sum['warn']): ?><span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle-fill me-1"></i>ระบบทำงานได้ มีข้อแนะนำ <?= $sum['warn'] ?> รายการ</span>
      <?php else: ?><span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>ระบบอยู่ในสภาพดี</span><?php endif; ?>
    </div>
    <div class="small text-muted">ตรวจเมื่อ <?= thai_date(date('Y-m-d H:i:s'), true, true) ?> · ใช้เวลา <?= $elapsed ?> วินาที ·
      <span class="text-success">ผ่าน <?= $sum['ok'] ?></span> · <span class="text-warning-emphasis">เตือน <?= $sum['warn'] ?></span> · <span class="text-danger">ไม่ผ่าน <?= $sum['fail'] ?></span></div>
  </div>
  <div class="d-flex gap-2 d-print-none">
    <a class="btn btn-primary btn-sm" href="<?= url('admin/system-check.php') ?>"><i class="bi bi-arrow-repeat me-1"></i>ตรวจอีกครั้ง</a>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์รายงาน</button>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <?php foreach ($results as $group => $items): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= e($group) ?></div>
        <ul class="list-group list-group-flush">
          <?php foreach ($items as $it): ?>
            <li class="list-group-item d-flex gap-2 align-items-start<?= $it['status'] === 'fail' ? ' list-group-item-danger' : '' ?>">
              <i class="bi <?= $statusIcon[$it['status']] ?> mt-1"></i>
              <div><div><?= e($it['label']) ?></div><?php if ($it['detail'] !== ''): ?><div class="small text-muted text-break"><?= e($it['detail']) ?></div><?php endif; ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="col-xl-4 d-print-none">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-fingerprint me-1"></i>ค่าอ้างอิงไฟล์โปรแกรม</div>
      <form method="post" class="card-body small" data-confirm="บันทึกสภาพไฟล์ปัจจุบันเป็นค่าอ้างอิง? ทำเฉพาะเมื่อมั่นใจว่าไฟล์โปรแกรมถูกต้อง">
        <?= csrf_field() ?><input type="hidden" name="action" value="baseline">
        <p>ระบบเก็บลายนิ้วมือ (SHA-256) ของไฟล์โปรแกรมทุกไฟล์ไว้ เพื่อตรวจว่ามีไฟล์ถูกแก้ไข ลบ หรือแอบเพิ่มเข้ามาหรือไม่</p>
        <p class="mb-2">บันทึกค่าอ้างอิงใหม่ <strong>หลังติดตั้งหรืออัปเดตระบบ</strong> เท่านั้น</p>
        <input type="password" class="form-control form-control-sm mb-2" name="password" placeholder="ยืนยันรหัสผ่านของคุณ" required autocomplete="current-password">
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-save me-1"></i>บันทึกค่าอ้างอิง</button>
      </form>
    </div>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-terminal me-1"></i>ตรวจจากบรรทัดคำสั่ง</div>
      <div class="card-body small">
        <p class="mb-1">ใช้ได้แม้หน้าเว็บมีปัญหา หรือตั้งเวลาให้ตรวจอัตโนมัติ:</p>
        <pre class="bg-light p-2 rounded mb-0"><code>C:\xampp\php\php.exe <?= e(str_replace('/', '\\', APP_ROOT)) ?>\tools\check.php</code></pre>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
