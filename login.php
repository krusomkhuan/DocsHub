<?php
require_once __DIR__ . '/includes/bootstrap.php';

$next = get('next', post('next'));
// อนุญาตเฉพาะ redirect ภายในระบบ
if ($next === '' || !str_starts_with($next, BASE_URL . '/') || str_starts_with($next, '//')) {
    $next = url('index.php');
}
if (is_logged_in()) redirect($next);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // หน่วงการเดารหัสผ่าน: ล็อก 5 นาทีหลังผิด 5 ครั้งใน session นี้
    $_SESSION['login_fail'] = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
    if ($_SESSION['login_fail']['n'] >= 5 && time() - $_SESSION['login_fail']['t'] < 300) {
        $error = 'เข้าสู่ระบบผิดหลายครั้ง กรุณารอ 5 นาทีแล้วลองใหม่';
    } else {
        $user = db_row('SELECT * FROM users WHERE username = ?', 's', [post('username')]);
        if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
            if ($user['status'] === 'pending') {
                $error = 'บัญชีของคุณอยู่ระหว่างรอผู้ดูแลระบบอนุมัติ';
            } elseif ($user['status'] === 'disabled') {
                $error = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
            } else {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$user['id'];
                unset($_SESSION['login_fail']);
                db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', 'i', [$user['id']]);
                $GLOBALS['CURRENT_USER'] = $user;
                log_activity('login', $user['username']);
                flash('success', 'ยินดีต้อนรับ ' . $user['fullname']);
                redirect($next);
            }
        } else {
            $_SESSION['login_fail'] = ['n' => $_SESSION['login_fail']['n'] + 1, 't' => time()];
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <span class="brand-logo mb-2" style="width:56px;height:56px;font-size:1.8rem"><i class="bi bi-folder2-open"></i></span>
          <h4 class="mb-0">เข้าสู่ระบบ</h4>
          <small class="text-muted"><?= e(setting('site_name')) ?> · <?= e(setting('site_slogan')) ?></small>
        </div>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="next" value="<?= e($next) ?>">
          <div class="mb-3">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input class="form-control" name="username" required autofocus value="<?= e(post('username')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">รหัสผ่าน</label>
            <input type="password" class="form-control" name="password" required>
          </div>
          <button class="btn btn-primary w-100">เข้าสู่ระบบ</button>
        </form>
        <?php if (google_login_available()): ?>
          <div class="d-flex align-items-center my-3"><hr class="flex-grow-1"><span class="px-2 small text-muted">หรือ</span><hr class="flex-grow-1"></div>
          <a class="btn btn-outline-dark w-100 d-flex align-items-center justify-content-center gap-2" href="<?= url('google-login.php' . '?next=' . urlencode($next)) ?>">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.6 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 2.9-2.2 5.4-4.7 7.1l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.5z"/><path fill="#FBBC05" d="M10.6 28.6c-.5-1.4-.8-2.9-.8-4.6s.3-3.2.8-4.6l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.2 0-11.5-4.1-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
            เข้าสู่ระบบด้วย Google
          </a>
          <?php if (google_allowed_domain() !== ''): ?><div class="text-center small text-muted mt-1">เฉพาะบัญชี @<?= e(google_allowed_domain()) ?></div><?php endif; ?>
        <?php endif; ?>
        <?php if (setting('allow_register', '1') === '1'): ?>
          <p class="text-center small mt-3 mb-0">ยังไม่มีบัญชี? <a href="<?= url('register.php') ?>">สมัครสมาชิก</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
