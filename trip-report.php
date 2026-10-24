<?php
require_once __DIR__ . '/includes/bootstrap.php';

$r = trip_get((int)get('id', post('id')));
if (!$r || !trip_can_view($r)) {
    if ($r && !is_logged_in()) require_login();
    http_response_code(404);
    $pageTitle = 'ไม่พบรายงาน';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">ไม่พบรายงานการไปราชการ หรือคุณไม่มีสิทธิ์ดู</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ลบรายงาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    if (trip_can_edit($r)) {
        trip_delete_report_files((int)$r['id']);
        db_exec('DELETE FROM trip_reports WHERE id = ?', 'i', [$r['id']]);
        log_activity('trip_delete', '#' . $r['id'] . ' ' . $r['subject']);
        flash('success', 'ลบรายงานการไปราชการแล้ว');
    }
    redirect('trip-reports.php');
}

// ดาวน์โหลดไฟล์รายงานแนบ
if (get('file') === '1' && $r['file_name']) {
    $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $r['file_original']);
    if (storage_serve($r['file_name'], 'application/octet-stream', 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($r['file_original']))) {
        exit;
    }
}

$images = db_all('SELECT * FROM trip_report_images WHERE report_id = ? ORDER BY sort_order, id', 'i', [$r['id']]);
$days = (int)((strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400) + 1;
$sections = [
    'วัตถุประสงค์'                    => $r['purpose'],
    'ผลการไปราชการ / สาระสำคัญ'       => $r['result'],
    'ประโยชน์ที่ได้รับและการนำไปใช้'    => $r['benefit'],
    'ปัญหา อุปสรรค / ข้อเสนอแนะ'      => $r['suggestion'],
];

$pageTitle = $r['subject'];
$activeNav = 'trip';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb" class="d-print-none"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('trip-reports.php') ?>">รายงานการไปราชการ</a></li>
  <li class="breadcrumb-item active text-truncate" style="max-width:320px"><?= e($r['subject']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3 d-print-none">
  <button class="btn btn-outline-secondary btn-sm" type="button" data-copy="<?= e(abs_url('trip-report.php?id=' . $r['id'])) ?>"><i class="bi bi-link-45deg me-1"></i>คัดลอกลิงก์</button>
  <button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์ / บันทึก PDF</button>
  <?php if (trip_can_edit($r)): ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('trip-report-form.php?id=' . $r['id']) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข / เพิ่มภาพ</a>
    <form method="post" data-confirm="ลบรายงานนี้พร้อมภาพประกอบทั้งหมด?">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
    </form>
  <?php endif; ?>
</div>

<article class="card shadow-sm trip-report">
  <div class="card-body p-4 p-lg-5">
    <div class="text-center mb-4">
      <h3 class="mb-1">รายงานการไปราชการ</h3>
      <div class="text-muted"><?= e(setting('college_name')) ?></div>
    </div>
    <table class="table table-borderless table-sm mb-4 trip-meta">
      <tr><th style="width:170px">เรื่อง</th><td><?= e($r['subject']) ?></td></tr>
      <tr><th>ตามคำสั่ง</th><td><a href="<?= url('document.php?id=' . $r['document_id']) ?>"><?= e($r['doc_title']) ?></a><?= $r['doc_no'] ? ' ที่ ' . e($r['doc_no']) : '' ?><?= $r['doc_date'] ? ' ลงวันที่ ' . thai_date($r['doc_date'], false) : '' ?></td></tr>
      <tr><th>สถานที่</th><td><?= e($r['place']) ?></td></tr>
      <tr><th>ระหว่างวันที่</th><td><?= thai_date_range($r['start_date'], $r['end_date']) ?> (<?= $days ?> วัน)</td></tr>
      <?php if ($r['travelers']): ?><tr><th>ผู้ไปราชการ</th><td style="white-space:pre-line"><?= e($r['travelers']) ?></td></tr><?php endif; ?>
      <?php if ($r['dept_name']): ?><tr><th>แผนกวิชา / ฝ่าย</th><td><?= e($r['dept_name']) ?></td></tr><?php endif; ?>
      <?php if ($r['expense'] !== null): ?><tr><th>ค่าใช้จ่าย</th><td><?= number_format((float)$r['expense'], 2) ?> บาท</td></tr><?php endif; ?>
    </table>

    <?php $n = 0; foreach ($sections as $title => $text): if (!$text) continue; ?>
      <h5 class="mt-4"><?= ++$n ?>. <?= e($title) ?></h5>
      <p style="white-space:pre-line;text-indent:2.5em"><?= e($text) ?></p>
    <?php endforeach; ?>

    <?php if ($images): ?>
      <h5 class="mt-4 trip-gallery-title"><?= ++$n ?>. ภาพประกอบ (<?= count($images) ?> ภาพ)</h5>
      <div class="row g-3 trip-gallery">
        <?php foreach ($images as $i => $img): ?>
          <figure class="col-6 col-md-4 mb-0">
            <a href="<?= url('trip-image.php?id=' . $img['id']) ?>" data-bs-toggle="modal" data-bs-target="#imgModal" data-index="<?= $i ?>">
              <img src="<?= url('trip-image.php?id=' . $img['id'] . '&thumb=1') ?>" class="img-fluid rounded border w-100" style="aspect-ratio:4/3;object-fit:cover" alt="<?= e($img['caption']) ?>" loading="lazy">
            </a>
            <?php if ($img['caption']): ?><figcaption class="small text-center text-muted mt-1"><?= e($img['caption']) ?></figcaption><?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="row mt-5 trip-sign">
      <div class="col-md-6 offset-md-6 text-center">
        <div>ลงชื่อ ........................................................ ผู้รายงาน</div>
        <div class="mt-2">( <?= e($r['reporter'] ?? '') ?> )</div>
        <div class="small text-muted mt-1">บันทึกเมื่อ <?= thai_date($r['created_at'], false) ?></div>
        <div class="small text-muted d-none d-print-block mt-2">ดูรายงานออนไลน์: <?= e(abs_url('trip-report.php?id=' . $r['id'])) ?></div>
      </div>
    </div>

    <?php if ($r['file_name']): ?>
      <div class="alert alert-light border mt-4 mb-0 d-print-none">
        <i class="bi <?= file_icon((string)$r['file_ext']) ?> me-1"></i>ไฟล์รายงานแนบ: <a href="?id=<?= $r['id'] ?>&file=1"><?= e($r['file_original']) ?></a> (<?= format_size((int)$r['file_size']) ?>)
      </div>
    <?php endif; ?>
  </div>
</article>

<?php if ($images): ?>
<div class="modal fade" id="imgModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0 py-2">
        <span class="small" id="imgCaption"></span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-2 position-relative">
        <img id="imgFull" class="img-fluid" style="max-height:78vh" alt="">
        <button class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-2" id="imgPrev"><i class="bi bi-chevron-left"></i></button>
        <button class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-2" id="imgNext"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="modal-footer border-0 py-2 justify-content-between">
        <span class="small" id="imgCounter"></span>
        <a class="btn btn-sm btn-outline-light" id="imgDownload"><i class="bi bi-download me-1"></i>ดาวน์โหลดภาพ</a>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var imgs = <?= json_encode(array_map(fn($im) => ['src' => url('trip-image.php?id=' . $im['id']), 'caption' => (string)$im['caption']], $images), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  var cur = 0;
  function show(i) {
    cur = (i + imgs.length) % imgs.length;
    document.getElementById('imgFull').src = imgs[cur].src;
    document.getElementById('imgCaption').textContent = imgs[cur].caption;
    document.getElementById('imgCounter').textContent = (cur + 1) + ' / ' + imgs.length;
    document.getElementById('imgDownload').href = imgs[cur].src + '&download=1';
  }
  document.querySelectorAll('[data-index]').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); show(+a.getAttribute('data-index')); });
  });
  document.getElementById('imgPrev').onclick = function () { show(cur - 1); };
  document.getElementById('imgNext').onclick = function () { show(cur + 1); };
  document.addEventListener('keydown', function (e) {
    if (!document.getElementById('imgModal').classList.contains('show')) return;
    if (e.key === 'ArrowLeft') show(cur - 1);
    if (e.key === 'ArrowRight') show(cur + 1);
  });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
