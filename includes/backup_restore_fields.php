<?php
/** ช่องเลือกการกู้คืน (ใช้ใน admin/backup.php) ตัวแปร $hasFiles = ไฟล์สำรองมีไฟล์เอกสารหรือไม่ */
$hasFiles = $hasFiles ?? true;
$uid = uniqid('r');
?>
<div class="col-auto">
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" name="restore_db" value="1" id="<?= $uid ?>db" checked>
    <label class="form-check-label small" for="<?= $uid ?>db">ฐานข้อมูล</label>
  </div>
  <?php if ($hasFiles): ?>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" name="restore_files" value="1" id="<?= $uid ?>f" checked>
    <label class="form-check-label small" for="<?= $uid ?>f">ไฟล์เอกสาร</label>
  </div>
  <?php endif; ?>
</div>
<div class="col-auto my-2">
  <input type="password" class="form-control form-control-sm" name="password" placeholder="ยืนยันรหัสผ่านของคุณ" required autocomplete="current-password">
</div>
<div class="col-auto">
  <button class="btn btn-sm btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>กู้คืน</button>
</div>
<?php unset($hasFiles); ?>
