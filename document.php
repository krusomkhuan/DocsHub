<?php
require_once __DIR__ . '/includes/bootstrap.php';

$d = db_row('SELECT d.*, t.name AS type_name, t.slug AS type_slug, t.color, t.owner_label, c.name AS cat_name,
                    dp.name AS dept_name, u.fullname AS uploader
             FROM documents d JOIN doc_types t ON t.id = d.type_id
             LEFT JOIN categories c ON c.id = d.category_id
             LEFT JOIN departments dp ON dp.id = d.department_id
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.id = ?', 'i', [(int)get('id')]);

if (!$d) {
    http_response_code(404);
    $pageTitle = 'ไม่พบเอกสาร';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">ไม่พบเอกสารที่ต้องการ</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
if (!can_view_document($d)) {
    if (!is_logged_in()) require_login();
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์ดูเอกสารนี้');
}

db_exec('UPDATE documents SET views = views + 1 WHERE id = ?', 'i', [$d['id']]);
$books = db_all('SELECT full_no, subject FROM book_numbers WHERE document_id = ?', 'i', [$d['id']]);

// รายงานการไปราชการของคำสั่งนี้
$allowTrip = doc_allows_trip_report($d);
$trips = [];
if ($allowTrip) {
    foreach (db_all('SELECT r.*, u.fullname AS reporter, (SELECT COUNT(*) FROM trip_report_images i WHERE i.report_id = r.id) AS img_count
                     FROM trip_reports r LEFT JOIN users u ON u.id = r.created_by WHERE r.document_id = ? ORDER BY r.start_date', 'i', [$d['id']]) as $t) {
        $t += ['doc_status' => $d['status'], 'doc_public' => $d['is_public'], 'doc_owner' => $d['uploaded_by']];
        if (trip_can_view($t)) $trips[] = $t;
    }
}

$pageTitle = $d['title'];
$activeNav = 'docs';
require __DIR__ . '/includes/header.php';

$ownerLabel = $d['owner_label'] ?: 'ผู้รับผิดชอบ';
$fields = [
    'ประเภท'        => e($d['type_name']),
    'หมวดหมู่'       => e($d['cat_name']),
    'เลขที่'         => e($d['doc_no']),
    'ลงวันที่'        => $d['doc_date'] ? thai_date($d['doc_date'], false) : '',
    'แผนกวิชา/ฝ่าย'  => e($d['dept_name']),
    'รหัส/ชื่อวิชา'   => e(trim($d['subject_code'] . ' ' . $d['subject_name'])),
    'ปีการศึกษา'     => e(trim(($d['semester'] ? $d['semester'] . '/' : '') . $d['academic_year'])),
    $ownerLabel     => e($d['owner_name']),
    'อัปโหลดโดย'     => e($d['uploader']) . ' · ' . thai_date($d['created_at'], true, true),
];
$isPreviewable = in_array($d['file_ext'], ['pdf', 'jpg', 'jpeg', 'png'], true);
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">หน้าแรก</a></li>
  <li class="breadcrumb-item"><a href="<?= url('documents.php?type=' . urlencode($d['type_slug'])) ?>"><?= e($d['type_name']) ?></a></li>
  <li class="breadcrumb-item active text-truncate" style="max-width:300px"><?= e($d['title']) ?></li>
</ol></nav>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex gap-3 align-items-start mb-3">
          <i class="bi <?= file_icon($d['file_ext']) ?>" style="font-size:3rem;line-height:1"></i>
          <div>
            <h3 class="mb-1"><?= e($d['title']) ?></h3>
            <span class="badge text-bg-<?= e($d['color']) ?>"><?= e($d['type_name']) ?></span>
            <?= $d['is_public'] ? '<span class="badge text-bg-light"><i class="bi bi-globe"></i> สาธารณะ</span>' : '<span class="badge text-bg-dark"><i class="bi bi-lock"></i> เฉพาะสมาชิก</span>' ?>
            <?= $d['status'] !== 'approved' ? status_badge($d['status']) : '' ?>
          </div>
        </div>
        <?php if ($d['description']): ?><p class="text-secondary" style="white-space:pre-line"><?= e($d['description']) ?></p><?php endif; ?>
        <?php if ($isPreviewable): ?>
          <div class="ratio ratio-4x3 border rounded overflow-hidden bg-light">
            <?php if ($d['file_ext'] === 'pdf'): ?>
              <iframe src="<?= url('download.php?id=' . $d['id'] . '&inline=1') ?>" title="ตัวอย่างเอกสาร"></iframe>
            <?php else: ?>
              <img src="<?= url('download.php?id=' . $d['id'] . '&inline=1') ?>" alt="<?= e($d['title']) ?>" style="object-fit:contain">
            <?php endif; ?>
          </div>
        <?php elseif ($d['file_ext'] === 'docx'): ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted"><i class="bi bi-eye me-1"></i>ตัวอย่างเอกสาร Word (การจัดหน้าอาจต่างจากต้นฉบับเล็กน้อย)</span>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="docxFull"><i class="bi bi-arrows-fullscreen me-1"></i>เต็มจอ</button>
          </div>
          <div id="docxView" class="docx-view border rounded bg-light" data-src="<?= url('download.php?id=' . $d['id'] . '&inline=1') ?>">
            <div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2"></div>กำลังโหลดตัวอย่างเอกสาร...</div>
          </div>
          <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
          <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.3/dist/docx-preview.min.js"></script>
          <script>
          (function () {
            var box = document.getElementById('docxView');
            var fail = function () {
              box.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-file-earmark-word fs-1 d-block mb-2"></i>แสดงตัวอย่างไม่ได้ กรุณากดปุ่ม ดาวน์โหลด เพื่อเปิดไฟล์</div>';
            };
            if (!window.docx || !window.JSZip) { fail(); return; }
            fetch(box.dataset.src, { credentials: 'same-origin' })
              .then(function (r) { if (!r.ok) throw new Error(r.status); return r.blob(); })
              .then(function (blob) {
                box.innerHTML = '';
                return docx.renderAsync(blob, box, null, { inWrapper: true, breakPages: true, ignoreLastRenderedPageBreak: true, experimental: true });
              })
              .catch(fail);
            document.getElementById('docxFull').addEventListener('click', function () {
              if (box.requestFullscreen) box.requestFullscreen(); else box.classList.toggle('docx-view-max');
            });
          })();
          </script>
        <?php elseif ($d['file_ext'] === 'doc'): ?>
          <div class="alert alert-light border small mb-0"><i class="bi bi-info-circle me-1"></i>ไฟล์ Word แบบเก่า (.doc) แสดงตัวอย่างออนไลน์ไม่ได้ กรุณาดาวน์โหลด · แนะนำให้บันทึกเป็น .docx หรือ PDF ก่อนอัปโหลด เพื่อให้ดูออนไลน์ได้</div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($allowTrip): ?>
    <div class="card shadow-sm mt-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-geo-alt me-1"></i>รายงานการไปราชการ (<?= count($trips) ?>)</span>
        <?php if (is_logged_in()): ?>
          <a class="btn btn-sm btn-primary" href="<?= url('trip-report-form.php?doc=' . $d['id']) ?>"><i class="bi bi-plus-lg me-1"></i>เขียนรายงาน</a>
        <?php endif; ?>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$trips): ?><div class="list-group-item small text-muted">ยังไม่มีรายงานการไปราชการสำหรับคำสั่งนี้</div><?php endif; ?>
        <?php foreach ($trips as $t): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between gap-2" href="<?= url('trip-report.php?id=' . $t['id']) ?>">
            <span><?= e($t['subject']) ?><div class="small text-muted"><?= e($t['place']) ?> · <?= thai_date_range($t['start_date'], $t['end_date']) ?> · <?= e($t['reporter']) ?></div></span>
            <span class="small text-muted text-nowrap"><i class="bi bi-images"></i> <?= (int)$t['img_count'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <a class="btn btn-primary w-100 mb-2" href="<?= url('download.php?id=' . $d['id']) ?>"><i class="bi bi-download me-1"></i>ดาวน์โหลด (<?= format_size((int)$d['file_size']) ?>)</a>
        <button class="btn btn-outline-secondary btn-sm w-100 mb-2" type="button" data-copy="<?= e(abs_url('document.php?id=' . $d['id'])) ?>"><i class="bi bi-link-45deg me-1"></i>คัดลอกลิงก์เอกสาร</button>
        <div class="small text-muted text-center"><?= e($d['file_original']) ?></div>
        <div class="d-flex justify-content-around small text-muted mt-2">
          <span><i class="bi bi-eye"></i> <?= number_format($d['views'] + 1) ?></span>
          <span><i class="bi bi-download"></i> <?= number_format($d['downloads']) ?></span>
        </div>
        <?php if (can_edit_document($d)): ?>
          <a class="btn btn-outline-secondary btn-sm w-100 mt-3" href="<?= url(is_staff() ? 'admin/document-form.php?id=' . $d['id'] : 'upload.php?id=' . $d['id']) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">รายละเอียด</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($fields as $label => $val): if ($val === '' || $val === null) continue; ?>
          <li class="list-group-item d-flex justify-content-between gap-3"><span class="text-muted text-nowrap"><?= e($label) ?></span><span class="text-end"><?= $val ?></span></li>
        <?php endforeach; ?>
        <?php foreach ($books as $b): ?>
          <li class="list-group-item d-flex justify-content-between gap-3"><span class="text-muted">เลขหนังสือ</span><span class="text-end"><?= e($b['full_no']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
