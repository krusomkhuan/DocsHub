<?php
/** เริ่มเชื่อมต่อ Google Drive สำหรับจัดเก็บไฟล์ (ผู้ดูแลระบบ) */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

if (setting('google_client_id') === '' || setting('google_client_secret') === '') {
    flash('warning', 'กรุณากรอก Client ID และ Client Secret ในการ์ด "เข้าสู่ระบบด้วย Google" ก่อน (ไม่จำเป็นต้องเปิดใช้การเข้าสู่ระบบ)');
    redirect('admin/settings.php#google');
}
header('Location: ' . google_auth_url('drive'));
exit;
