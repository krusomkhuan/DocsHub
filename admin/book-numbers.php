<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

$selfUrl = url('admin/book-numbers.php');
ob_start();
require __DIR__ . '/../includes/book_numbers_page.php';
$body = ob_get_clean();

$pageTitle = 'ทะเบียนเลขหนังสือราชการ';
$activeMenu = 'book';
require __DIR__ . '/../includes/admin_header.php';
echo '<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>รูปแบบเลขปัจจุบัน: <code>' . e(setting('book_format')) . '</code>'
   . (is_admin() ? ' · <a href="' . url('admin/settings.php') . '">เปลี่ยนรูปแบบ</a>' : '') . ' · เลขรันใหม่ทุกปี พ.ศ. แยกตามแผนกและประเภทหนังสือ</div>';
echo $body;
require __DIR__ . '/../includes/admin_footer.php';
