<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/document_form.php';
require_staff();

$doc = null;
if (get('id') !== '') {
    $doc = db_row('SELECT * FROM documents WHERE id = ?', 'i', [(int)get('id')]);
    if (!$doc) {
        flash('danger', 'ไม่พบเอกสาร');
        redirect('admin/documents.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$id, $errors] = save_document_form($doc);
    if ($id) {
        flash('success', 'บันทึกเอกสารเรียบร้อยแล้ว');
        redirect('admin/documents.php');
    }
}

$pageTitle = $doc ? 'แก้ไขเอกสาร' : 'เพิ่มเอกสาร';
$activeMenu = 'documents';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card shadow-sm">
  <div class="card-body p-4">
    <?php if ($doc): ?>
      <div class="alert alert-light border small">
        อัปโหลดเมื่อ <?= thai_date($doc['created_at'], true, true) ?> · ดู <?= number_format($doc['views']) ?> ครั้ง · ดาวน์โหลด <?= number_format($doc['downloads']) ?> ครั้ง ·
        <a href="<?= url('download.php?id=' . $doc['id']) ?>">ดาวน์โหลดไฟล์ปัจจุบัน</a>
      </div>
    <?php endif; ?>
    <?php render_document_form($doc, $errors, url('admin/documents.php')); ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
