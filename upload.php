<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/document_form.php';
require_login();

$doc = null;
if (get('id') !== '') {
    $doc = db_row('SELECT * FROM documents WHERE id = ?', 'i', [(int)get('id')]);
    if (!$doc || !can_edit_document($doc)) {
        flash('danger', 'ไม่สามารถแก้ไขเอกสารนี้ได้ (เอกสารที่อนุมัติแล้วต้องให้เจ้าหน้าที่แก้ไข)');
        redirect('my-documents.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$id, $errors] = save_document_form($doc);
    if ($id) {
        flash('success', is_staff() ? 'บันทึกเอกสารเรียบร้อยแล้ว' : 'บันทึกเอกสารแล้ว รอเจ้าหน้าที่อนุมัติ');
        redirect(is_staff() ? 'document.php?id=' . $id : 'my-documents.php');
    }
}

$pageTitle = $doc ? 'แก้ไขเอกสาร' : 'อัปโหลดเอกสาร';
$activeNav = 'mydocs';
require __DIR__ . '/includes/header.php';
?>
<div class="card shadow-sm">
  <div class="card-body p-4">
    <h4 class="mb-3"><i class="bi bi-cloud-upload me-2"></i><?= e($pageTitle) ?></h4>
    <?php render_document_form($doc, $errors, url('my-documents.php')); ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
