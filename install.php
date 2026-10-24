<?php
/**
 * ตัวติดตั้งระบบ Docs Hub: สร้างฐานข้อมูล ตาราง ข้อมูลเริ่มต้น และบัญชีผู้ดูแลระบบ
 * เมื่อติดตั้งเสร็จจะสร้างไฟล์ includes/installed.lock และปิดหน้านี้อัตโนมัติ
 */
require_once __DIR__ . '/includes/config.php';

$lock = __DIR__ . '/includes/installed.lock';
$errors = [];
$done = false;

if (file_exists($lock)) {
    exit('ระบบติดตั้งเรียบร้อยแล้ว หากต้องการติดตั้งใหม่ให้ลบไฟล์ includes/installed.lock <a href="index.php">กลับหน้าแรก</a>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . ยาว 4-30 ตัวอักษร';
    if ($fullname === '') $errors[] = 'กรุณากรอกชื่อ-สกุลผู้ดูแลระบบ';
    if (strlen($password) < 8) $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    if ($password !== ($_POST['password2'] ?? '')) $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';

    if (!$errors) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
            $db->set_charset('utf8mb4');
            $db->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $db->select_db(DB_NAME);

            $sql = file_get_contents(__DIR__ . '/database/docshub.sql');
            $db->multi_query($sql);
            do {
                if ($r = $db->store_result()) $r->free();
            } while ($db->more_results() && $db->next_result());

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, fullname, role, status) VALUES (?,?,?,'admin','active')
                                  ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), fullname = VALUES(fullname), role='admin', status='active'");
            $stmt->bind_param('sss', $username, $hash, $fullname);
            $stmt->execute();

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
            file_put_contents($lock, date('c'));
            $done = true;
        } catch (mysqli_sql_exception $ex) {
            $errors[] = 'เกิดข้อผิดพลาดจากฐานข้อมูล: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ติดตั้งระบบ Docs Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-hub min-vh-100 d-flex align-items-center py-5">
<div class="container" style="max-width:560px">
  <div class="card shadow">
    <div class="card-body p-4">
      <h3 class="mb-1">ติดตั้งระบบ Docs Hub</h3>
      <p class="text-muted mb-1">ศูนย์รวมงานจัดเก็บเอกสาร · วิทยาลัยการอาชีพโพนทอง</p>
      <p class="small text-muted">เวอร์ชัน <?= htmlspecialchars(APP_VERSION) ?> · พัฒนาระบบโดย <?= htmlspecialchars(APP_DEVELOPER . ' ' . APP_DEVELOPER_DEPT) ?></p>
      <?php if ($done): ?>
        <div class="alert alert-success">ติดตั้งเรียบร้อยแล้ว! สร้างฐานข้อมูล <code><?= htmlspecialchars(DB_NAME) ?></code> และบัญชีผู้ดูแลระบบสำเร็จ</div>
        <a class="btn btn-primary w-100" href="login.php">ไปหน้าเข้าสู่ระบบ</a>
      <?php else: ?>
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($er) ?></div><?php endforeach; ?>
        <div class="alert alert-info small">
          ฐานข้อมูล: <code><?= htmlspecialchars(DB_USER . '@' . DB_HOST . ' / ' . DB_NAME) ?></code><br>
          แก้ไขค่าเชื่อมต่อได้ที่ <code>includes/config.php</code>
        </div>
        <form method="post">
          <h6 class="mt-3">บัญชีผู้ดูแลระบบ</h6>
          <div class="mb-2"><label class="form-label">ชื่อผู้ใช้</label><input class="form-control" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>"></div>
          <div class="mb-2"><label class="form-label">ชื่อ-สกุล</label><input class="form-control" name="fullname" required value="<?= htmlspecialchars($_POST['fullname'] ?? 'ผู้ดูแลระบบ') ?>"></div>
          <div class="mb-2"><label class="form-label">รหัสผ่าน (อย่างน้อย 8 ตัว)</label><input type="password" class="form-control" name="password" required minlength="8"></div>
          <div class="mb-3"><label class="form-label">ยืนยันรหัสผ่าน</label><input type="password" class="form-control" name="password2" required minlength="8"></div>
          <button class="btn btn-primary w-100">ติดตั้งระบบ</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
