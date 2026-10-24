<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$selfUrl = url('book-numbers.php');
// ประมวลผลก่อนแสดงส่วนหัว เพื่อให้ redirect / ส่งออก CSV ทำงานได้
ob_start();
require __DIR__ . '/includes/book_numbers_page.php';
$body = ob_get_clean();

$pageTitle = 'ออกเลขหนังสือราชการ';
$activeNav = 'book';
require __DIR__ . '/includes/header.php';
echo '<h4 class="mb-3"><i class="bi bi-123 me-2"></i>ออกเลขหนังสือราชการ <small class="text-muted fs-6">' . e(current_user()['dept_name'] ?? '') . '</small></h4>';
echo $body;
require __DIR__ . '/includes/footer.php';
