<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) redirect('index.php');
if (setting('allow_register', '1') !== '1') {
    flash('warning', 'ขณะนี้ปิดรับสมัครสมาชิก กรุณาติดต่อผู้ดูแลระบบ');
    redirect('login.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post('username');
    $fullname = post('fullname');
    $email    = post('email');
    $phone    = post('phone');
    $deptId   = (int)post('department_id');
    $password = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . ยาว 4-30 ตัวอักษร';
    elseif (db_val('SELECT id FROM users WHERE username = ?', 's', [$username])) $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
    if ($fullname === '') $errors[] = 'กรุณากรอกชื่อ-สกุล';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    if (!isset(departments()[$deptId])) $errors[] = 'กรุณาเลือกแผนกวิชา/ฝ่าย';
    if (strlen($password) < 8) $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    if ($password !== ($_POST['password2'] ?? '')) $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';
    if (post('pdpa_agree') !== '1') $errors[] = 'กรุณายอมรับนโยบายคุ้มครองข้อมูลส่วนบุคคล';

    if (!$errors) {
        $status = setting('auto_approve_member', '0') === '1' ? 'active' : 'pending';
        $newId = db_exec("INSERT INTO users (username, password_hash, fullname, email, phone, department_id, role, status)
                 VALUES (?,?,?,?,?,?,'member',?)", 'sssssis',
            [$username, password_hash($password, PASSWORD_DEFAULT), $fullname, $email ?: null, $phone ?: null, $deptId, $status]);
        pdpa_record_consent($newId, 'accept');
        log_activity('register', $username);
        flash('success', $status === 'active' ? 'สมัครสมาชิกสำเร็จ เข้าสู่ระบบได้ทันที' : 'สมัครสมาชิกสำเร็จ กรุณารอผู้ดูแลระบบอนุมัติบัญชี');
        redirect('login.php');
    }
}

$pageTitle = 'สมัครสมาชิก';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-1">สมัครสมาชิก</h4>
        <p class="text-muted small">สำหรับครูและบุคลากร <?= e(setting('college_name')) ?></p>
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-md-6"><label class="form-label">ชื่อผู้ใช้ *</label><input class="form-control" name="username" required value="<?= e(post('username')) ?>"></div>
          <div class="col-md-6"><label class="form-label">ชื่อ-สกุล *</label><input class="form-control" name="fullname" required value="<?= e(post('fullname')) ?>"></div>
          <div class="col-md-6"><label class="form-label">อีเมล</label><input type="email" class="form-control" name="email" value="<?= e(post('email')) ?>"></div>
          <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e(post('phone')) ?>"></div>
          <div class="col-12">
            <label class="form-label">แผนกวิชา / ฝ่าย *</label>
            <select class="form-select" name="department_id" required>
              <option value="">— เลือก —</option>
              <?php foreach (departments() as $dp): ?>
                <option value="<?= $dp['id'] ?>"<?= (int)post('department_id') === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">รหัสผ่าน * (อย่างน้อย 8 ตัว)</label><input type="password" class="form-control" name="password" required minlength="8"></div>
          <div class="col-md-6"><label class="form-label">ยืนยันรหัสผ่าน *</label><input type="password" class="form-control" name="password2" required minlength="8"></div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="pdpa_agree" value="1" id="pdpa" required<?= post('pdpa_agree') === '1' ? ' checked' : '' ?>>
              <label class="form-check-label small" for="pdpa">ข้าพเจ้าได้อ่านและยอมรับ <a href="<?= url('privacy.php') ?>" target="_blank">นโยบายคุ้มครองข้อมูลส่วนบุคคล</a> และยินยอมให้วิทยาลัยเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลตามนโยบายนี้</label>
            </div>
          </div>
          <div class="col-12"><button class="btn btn-primary w-100">สมัครสมาชิก</button></div>
        </form>
        <?php if (google_login_available() && setting('google_allow_register', '1') === '1'): ?>
          <div class="d-flex align-items-center my-3"><hr class="flex-grow-1"><span class="px-2 small text-muted">หรือ</span><hr class="flex-grow-1"></div>
          <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center gap-2" href="<?= url('google-login.php' . '') ?>">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.6 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 2.9-2.2 5.4-4.7 7.1l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.5z"/><path fill="#FBBC05" d="M10.6 28.6c-.5-1.4-.8-2.9-.8-4.6s.3-3.2.8-4.6l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.2 0-11.5-4.1-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
            สมัครด้วยบัญชี Google
          </a>
          <?php if (google_allowed_domain() !== ''): ?><div class="text-center small text-muted mt-1">เฉพาะบัญชี @<?= e(google_allowed_domain()) ?></div><?php endif; ?>
        <?php endif; ?>
        <p class="text-center small mt-3 mb-0">มีบัญชีแล้ว? <a href="<?= url('login.php') ?>">เข้าสู่ระบบ</a></p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
