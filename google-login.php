<?php
/** เริ่มเข้าสู่ระบบ / เชื่อมบัญชีด้วย Google  ?mode=login|link&next= */
require_once __DIR__ . '/includes/bootstrap.php';

if (!google_login_available()) {
    flash('warning', 'ระบบยังไม่เปิดให้เข้าสู่ระบบด้วย Google');
    redirect('login.php');
}
$mode = get('mode') === 'link' ? 'link' : 'login';
if ($mode === 'link') {
    require_login();
} elseif (is_logged_in()) {
    redirect('index.php');
}
header('Location: ' . google_auth_url($mode, get('next')));
exit;
