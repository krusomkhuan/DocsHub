<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$u = current_user();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fullname = post('fullname');
    $email    = post('email');
    $phone    = post('phone');
    if ($fullname === '') $errors[] = 'กรุณากรอกชื่อ-สกุล';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';

    $newPass = (string)($_POST['new_password'] ?? '');
    // ยกเลิกการเชื่อมบัญชี Google (ต้องมีรหัสผ่านของระบบก่อน ไม่อย่างนั้นจะเข้าสู่ระบบไม่ได้)
    if (post('action') === 'google_unlink') {
        if (!$u['has_password']) {
            flash('danger', 'กรุณาตั้งรหัสผ่านของระบบก่อน จึงยกเลิกการเชื่อม Google ได้');
        } else {
            db_exec('UPDATE users SET google_sub = NULL, google_email = NULL WHERE id = ?', 'i', [$u['id']]);
            log_activity('google_unlink', (string)$u['google_email']);
            flash('success', 'ยกเลิกการเชื่อมบัญชี Google แล้ว');
        }
        redirect('profile.php');
    }

    if ($newPass !== '') {
        // บัญชีที่สมัครด้วย Google ยังไม่มีรหัสผ่านของตนเอง จึงตั้งได้โดยไม่ต้องกรอกรหัสผ่านปัจจุบัน
        if ($u['has_password'] && !password_verify((string)($_POST['current_password'] ?? ''), $u['password_hash'])) $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        if (strlen($newPass) < 8) $errors[] = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
        if ($newPass !== ($_POST['new_password2'] ?? '')) $errors[] = 'รหัสผ่านใหม่ยืนยันไม่ตรงกัน';
    }

    // ภาพโปรไฟล์
    $newAvatar = null;
    if (!$errors && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $newAvatar = save_avatar($_FILES['avatar'], (int)$u['id']);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        if ($newAvatar || post('remove_avatar') === '1') {
            delete_stored_file($u['avatar']);
            db_exec('UPDATE users SET avatar = ? WHERE id = ?', 'si', [$newAvatar, $u['id']]);
        }
        db_exec('UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?', 'sssi', [$fullname, $email ?: null, $phone ?: null, $u['id']]);
        if ($newPass !== '') {
            db_exec('UPDATE users SET password_hash = ?, has_password = 1 WHERE id = ?', 'si', [password_hash($newPass, PASSWORD_DEFAULT), $u['id']]);
            log_activity('change_password');
        }
        flash('success', 'บันทึกข้อมูลเรียบร้อยแล้ว');
        redirect('profile.php');
    }
}

$pageTitle = 'ข้อมูลส่วนตัว';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3"><i class="bi bi-person-gear me-2"></i>ข้อมูลส่วนตัว</h4>
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="col-12 d-flex align-items-center gap-3">
            <span id="avatarPreview"><?= avatar_html($u, 96) ?></span>
            <div class="flex-grow-1">
              <label class="form-label mb-1">ภาพโปรไฟล์</label>
              <input type="file" class="form-control form-control-sm" name="avatar" accept="image/jpeg,image/png,image/webp"
                     onchange="if(this.files[0]){document.getElementById('avatarPreview').innerHTML='<img class=&quot;rounded-circle object-fit-cover&quot; style=&quot;width:96px;height:96px&quot; src=&quot;'+URL.createObjectURL(this.files[0])+'&quot;>'}">
              <div class="form-text">JPG / PNG / WEBP ไม่เกิน 10 MB · ระบบครอปเป็นภาพสี่เหลี่ยมจัตุรัสให้อัตโนมัติ</div>
              <?php if ($u['avatar']): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="rmav"><label class="form-check-label small" for="rmav">ลบภาพโปรไฟล์</label></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6"><label class="form-label">ชื่อผู้ใช้</label><input class="form-control" value="<?= e($u['username']) ?>" disabled></div>
          <div class="col-md-6"><label class="form-label">สิทธิ์ / แผนก</label><input class="form-control" value="<?= e(role_label($u['role']) . ' · ' . ($u['dept_name'] ?? '-')) ?>" disabled></div>
          <div class="col-12"><label class="form-label">ชื่อ-สกุล</label><input class="form-control" name="fullname" required value="<?= e($u['fullname']) ?>"></div>
          <div class="col-md-6"><label class="form-label">อีเมล</label><input type="email" class="form-control" name="email" value="<?= e($u['email']) ?>"></div>
          <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e($u['phone']) ?>"></div>
          <div class="col-12"><hr><h6><?= $u['has_password'] ? 'เปลี่ยนรหัสผ่าน' : 'ตั้งรหัสผ่านของระบบ' ?> <small class="text-muted">(เว้นว่างหากไม่ต้องการ<?= $u['has_password'] ? 'เปลี่ยน' : 'ตั้ง' ?>)</small></h6>
            <?php if (!$u['has_password']): ?><div class="small text-muted">บัญชีนี้สมัครด้วย Google ตั้งรหัสผ่านไว้เพื่อเข้าสู่ระบบด้วยชื่อผู้ใช้ <strong><?= e($u['username']) ?></strong> ได้ด้วย</div><?php endif; ?></div>
          <?php if ($u['has_password']): ?><div class="col-md-4"><label class="form-label">รหัสผ่านปัจจุบัน</label><input type="password" class="form-control" name="current_password"></div><?php endif; ?>
          <div class="col-md-4"><label class="form-label">รหัสผ่านใหม่</label><input type="password" class="form-control" name="new_password" minlength="8"></div>
          <div class="col-md-4"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input type="password" class="form-control" name="new_password2" minlength="8"></div>
          <div class="col-12"><button class="btn btn-primary">บันทึก</button></div>
        </form>
        <?php if (google_login_available() || $u['google_sub']): ?>
        <hr>
        <h6><i class="bi bi-google me-1 text-danger"></i>บัญชี Google</h6>
        <?php if ($u['google_sub']): ?>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-success"><i class="bi bi-link-45deg"></i> เชื่อมแล้ว</span> <span class="small"><?= e($u['google_email']) ?></span>
            <form method="post" class="ms-auto" data-confirm="ยกเลิกการเชื่อมบัญชี Google?"><?= csrf_field() ?><input type="hidden" name="action" value="google_unlink">
              <button class="btn btn-sm btn-outline-danger"<?= $u['has_password'] ? '' : ' disabled title="ตั้งรหัสผ่านของระบบก่อน"' ?>>ยกเลิกการเชื่อม</button></form>
          </div>
        <?php else: ?>
          <p class="small text-muted mb-2">เชื่อมบัญชี Google แล้วครั้งต่อไปกด "เข้าสู่ระบบด้วย Google" ได้โดยไม่ต้องพิมพ์รหัสผ่าน<?= google_allowed_domain() !== '' ? ' (เฉพาะบัญชี @' . e(google_allowed_domain()) . ')' : '' ?></p>
          <a class="btn btn-outline-dark btn-sm" href="<?= url('google-login.php?mode=link') ?>"><i class="bi bi-google me-1"></i>เชื่อมบัญชี Google</a>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
