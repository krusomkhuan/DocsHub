<?php
/** สมัครสมาชิกด้วยบัญชี Google (ขั้นตอนสุดท้าย: เลือกแผนกและยอมรับนโยบาย) */
require_once __DIR__ . '/includes/bootstrap.php';

$g = $_SESSION['google_pending'] ?? null;
if (!$g || time() - $g['created'] > 900 || !google_login_available()) {
    unset($_SESSION['google_pending']);
    flash('warning', 'หมดเวลาสมัครสมาชิกด้วย Google กรุณากด "เข้าสู่ระบบด้วย Google" อีกครั้ง');
    redirect('login.php');
}
if (is_logged_in()) redirect('index.php');

$errors = [];
$username = post('username', google_suggest_username($g['email']));
$fullname = post('fullname', $g['name']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'cancel') {
        unset($_SESSION['google_pending']);
        redirect('login.php');
    }
    $deptId = (int)post('department_id');
    $phone = mb_substr(post('phone'), 0, 30);
    if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . ยาว 4-30 ตัวอักษร';
    elseif (db_val('SELECT id FROM users WHERE username = ?', 's', [$username])) $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
    if ($fullname === '') $errors[] = 'กรุณากรอกชื่อ-สกุล';
    if (!isset(departments()[$deptId])) $errors[] = 'กรุณาเลือกแผนกวิชา/ฝ่าย';
    if (post('pdpa_agree') !== '1') $errors[] = 'กรุณายอมรับนโยบายคุ้มครองข้อมูลส่วนบุคคล';
    if (db_val('SELECT id FROM users WHERE google_sub = ?', 's', [$g['sub']])) $errors[] = 'บัญชี Google นี้สมัครไว้แล้ว กรุณาเข้าสู่ระบบด้วย Google';

    if (!$errors) {
        $status = setting('auto_approve_member', '0') === '1' ? 'active' : 'pending';
        // รหัสผ่านสุ่มที่ไม่มีใครรู้ (has_password = 0) ผู้ใช้ตั้งรหัสผ่านเองภายหลังได้ที่หน้าข้อมูลส่วนตัว
        $id = db_exec("INSERT INTO users (username, password_hash, has_password, fullname, email, phone, department_id, role, status, google_sub, google_email)
                       VALUES (?,?,0,?,?,?,?,'member',?,?,?)", 'sssssisss',
            [$username, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), mb_substr($fullname, 0, 150), $g['email'], $phone ?: null, $deptId, $status, $g['sub'], $g['email']]);
        pdpa_record_consent($id, 'accept');
        log_activity('register_google', $username . ' (' . $g['email'] . ')');
        unset($_SESSION['google_pending']);
        if ($status === 'active') {
            google_sign_in(db_row('SELECT * FROM users WHERE id = ?', 'i', [$id]));
            flash('success', 'สมัครสมาชิกด้วย Google สำเร็จ ยินดีต้อนรับ ' . $fullname);
            redirect(google_safe_next($g['next']));
        }
        flash('success', 'สมัครสมาชิกด้วย Google สำเร็จ กรุณารอผู้ดูแลระบบอนุมัติบัญชี แล้วกด "เข้าสู่ระบบด้วย Google" ได้เลย');
        redirect('login.php');
    }
}

$pageTitle = 'สมัครสมาชิกด้วย Google';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-1"><i class="bi bi-google me-2 text-danger"></i>สมัครสมาชิกด้วย Google</h4>
        <p class="text-muted small">บัญชี Google: <strong><?= e($g['email']) ?></strong> · กรอกข้อมูลเพิ่มเติมเพื่อสร้างบัญชี <?= e(setting('site_name')) ?></p>
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-md-6"><label class="form-label">ชื่อผู้ใช้ *</label><input class="form-control" name="username" required value="<?= e($username) ?>"></div>
          <div class="col-md-6"><label class="form-label">ชื่อ-สกุล *</label><input class="form-control" name="fullname" required value="<?= e($fullname) ?>"></div>
          <div class="col-md-7">
            <label class="form-label">แผนกวิชา / ฝ่าย *</label>
            <select class="form-select" name="department_id" required>
              <option value="">— เลือก —</option>
              <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= (int)post('department_id') === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e(post('phone')) ?>"></div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="pdpa_agree" value="1" id="pdpa" required<?= post('pdpa_agree') === '1' ? ' checked' : '' ?>>
              <label class="form-check-label small" for="pdpa">ข้าพเจ้าได้อ่านและยอมรับ <a href="<?= url('privacy.php') ?>" target="_blank">นโยบายคุ้มครองข้อมูลส่วนบุคคล</a> และยินยอมให้วิทยาลัยใช้ชื่อและอีเมลจากบัญชี Google ตามนโยบายนี้</label>
            </div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1"><i class="bi bi-person-plus me-1"></i>สร้างบัญชี</button>
            <button class="btn btn-outline-secondary" name="action" value="cancel" formnovalidate>ยกเลิก</button>
          </div>
          <div class="col-12 small text-muted"><?= setting('auto_approve_member', '0') === '1' ? 'เข้าใช้งานได้ทันทีหลังสร้างบัญชี' : 'บัญชีใหม่ต้องรอผู้ดูแลระบบอนุมัติก่อนใช้งาน' ?> · ระบบไม่เก็บรหัสผ่าน Google ของคุณ</div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
