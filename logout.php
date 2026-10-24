<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    log_activity('logout', current_user()['username']);
}
$_SESSION = [];
session_regenerate_id(true);
flash('info', 'ออกจากระบบเรียบร้อยแล้ว');
redirect('login.php');
