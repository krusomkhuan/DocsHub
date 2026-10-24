<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'หน้าแรก';
$activeNav = 'home';
$noContainer = true;

// เงื่อนไขการมองเห็นเอกสาร
$visible = "d.status = 'approved'" . (is_logged_in() ? '' : ' AND d.is_public = 1');

$counts = [];
foreach (db_all("SELECT d.type_id, COUNT(*) c FROM documents d WHERE $visible GROUP BY d.type_id") as $r) {
    $counts[$r['type_id']] = (int)$r['c'];
}
$latest = db_all("SELECT d.*, t.name AS type_name, t.color, dp.name AS dept_name
                  FROM documents d JOIN doc_types t ON t.id = d.type_id
                  LEFT JOIN departments dp ON dp.id = d.department_id
                  WHERE $visible ORDER BY d.created_at DESC LIMIT 8");
$popular = db_all("SELECT d.id, d.title, d.file_ext, d.downloads FROM documents d
                   WHERE $visible AND d.downloads > 0 ORDER BY d.downloads DESC LIMIT 5");
$totalDocs = array_sum($counts);
$totalBooks = (int)db_val('SELECT COUNT(*) FROM book_numbers WHERE is_cancelled = 0');
$totalUsers = (int)db_val("SELECT COUNT(*) FROM users WHERE status = 'active'");

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container position-relative" style="z-index:1">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <h1 class="display-5 fw-semibold mb-2"><?= e(setting('site_name', 'Docs Hub')) ?></h1>
        <p class="fs-4 slogan mb-1">[<?= e(setting('site_slogan')) ?>]</p>
        <p class="mb-4 opacity-75"><?= e(setting('college_name')) ?> · คำสั่งราชการ เกียรติบัตร แผนการสอน โครงการสอน หนังสือเรียน และทะเบียนเลขหนังสือ</p>
        <form class="hero-search" action="<?= url('documents.php') ?>">
          <div class="input-group input-group-lg shadow">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
            <input class="form-control" name="q" placeholder="ค้นหาเอกสารทุกประเภท: ชื่อเรื่อง เลขที่ ชื่อบุคคล รหัสวิชา แผนก...">
            <select class="form-select border-0 flex-grow-0 w-auto d-none d-md-block" name="type" aria-label="ประเภทเอกสาร">
              <option value="">ทุกประเภท</option>
              <?php foreach (doc_types() as $t): ?><option value="<?= e($t['slug']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-warning px-4">ค้นหา</button>
          </div>
        </form>
      </div>
      <div class="col-lg-4 d-none d-lg-block">
        <div class="row g-2 text-center">
          <div class="col-4"><div class="bg-white bg-opacity-10 rounded-3 p-3"><div class="fs-3 fw-semibold"><?= number_format($totalDocs) ?></div><small>เอกสาร</small></div></div>
          <div class="col-4"><div class="bg-white bg-opacity-10 rounded-3 p-3"><div class="fs-3 fw-semibold"><?= number_format($totalBooks) ?></div><small>เลขหนังสือ</small></div></div>
          <div class="col-4"><div class="bg-white bg-opacity-10 rounded-3 p-3"><div class="fs-3 fw-semibold"><?= number_format($totalUsers) ?></div><small>สมาชิก</small></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container pb-5">
  <div class="overlap"><?= render_flash() ?></div>
  <div class="row g-3 overlap">
    <?php foreach (doc_types() as $t): ?>
      <div class="col-6 col-md-4 col-xl">
        <a class="text-decoration-none text-body" href="<?= url('documents.php?type=' . urlencode($t['slug'])) ?>">
          <div class="card type-card shadow-sm">
            <div class="card-body">
              <span class="type-icon bg-<?= e($t['color']) ?>-subtle text-<?= e($t['color']) ?>-emphasis mb-3"><i class="bi <?= e($t['icon']) ?>"></i></span>
              <h5 class="card-title mb-1"><?= e($t['name']) ?></h5>
              <div class="small text-muted mb-2"><?= e($t['description']) ?></div>
              <span class="badge rounded-pill text-bg-light"><?= number_format($counts[$t['id']] ?? 0) ?> รายการ</span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-4 col-xl">
      <a class="text-decoration-none text-body" href="<?= url(is_logged_in() ? 'book-numbers.php' : 'login.php') ?>">
        <div class="card type-card shadow-sm">
          <div class="card-body">
            <span class="type-icon bg-secondary-subtle text-secondary-emphasis mb-3"><i class="bi bi-123"></i></span>
            <h5 class="card-title mb-1">ออกเลขหนังสือ</h5>
            <div class="small text-muted mb-2">ออกเลขที่หนังสือราชการ แยกตามแผนกวิชา</div>
            <span class="badge rounded-pill text-bg-light">สำหรับสมาชิก</span>
          </div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <a class="text-decoration-none text-body" href="<?= url('photo-albums.php') ?>">
        <div class="card type-card shadow-sm">
          <div class="card-body">
            <span class="type-icon bg-warning-subtle text-warning-emphasis mb-3"><i class="bi bi-images"></i></span>
            <h5 class="card-title mb-1">คลังรูปภาพ</h5>
            <div class="small text-muted mb-2">สืบค้นอัลบั้มภาพกิจกรรมของวิทยาลัย</div>
            <span class="badge rounded-pill text-bg-light"><?= number_format((int)db_val("SELECT COUNT(*) FROM photo_albums WHERE status = 'approved'")) ?> อัลบั้ม</span>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="row g-4 mt-2">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>เอกสารล่าสุด</h5>
          <a href="<?= url('documents.php') ?>" class="small">ดูทั้งหมด</a>
        </div>
        <div class="list-group list-group-flush">
          <?php if (!$latest): ?>
            <div class="list-group-item text-center text-muted py-5">ยังไม่มีเอกสารในระบบ</div>
          <?php endif; ?>
          <?php foreach ($latest as $d): ?>
            <a href="<?= url('document.php?id=' . $d['id']) ?>" class="list-group-item list-group-item-action doc-item d-flex gap-3 py-3">
              <i class="bi <?= file_icon($d['file_ext']) ?> file-ic"></i>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-medium text-truncate"><?= e($d['title']) ?></div>
                <div class="small text-muted">
                  <span class="badge text-bg-<?= e($d['color']) ?> bg-opacity-75"><?= e($d['type_name']) ?></span>
                  <?= $d['dept_name'] ? e($d['dept_name']) . ' · ' : '' ?><?= thai_date($d['created_at']) ?>
                  <?php if (!$d['is_public']): ?><i class="bi bi-lock-fill ms-1" title="เฉพาะสมาชิก"></i><?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="bi bi-fire me-2 text-danger"></i>ดาวน์โหลดมากที่สุด</h5></div>
        <ol class="list-group list-group-flush list-group-numbered">
          <?php if (!$popular): ?><li class="list-group-item text-muted small">ยังไม่มีข้อมูล</li><?php endif; ?>
          <?php foreach ($popular as $p): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start">
              <a class="ms-2 me-auto text-truncate" href="<?= url('document.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a>
              <span class="badge text-bg-primary rounded-pill"><?= (int)$p['downloads'] ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php if (!is_logged_in()): ?>
        <div class="card shadow-sm bg-hub text-white">
          <div class="card-body">
            <h5>สมาชิก Docs Hub</h5>
            <p class="small opacity-75">ครูและบุคลากรสามารถสมัครสมาชิกเพื่ออัปโหลดเอกสาร ดูเอกสารภายใน และขอออกเลขหนังสือราชการของแผนกวิชา</p>
            <a href="<?= url('login.php') ?>" class="btn btn-light btn-sm">เข้าสู่ระบบ</a>
            <a href="<?= url('register.php') ?>" class="btn btn-outline-light btn-sm">สมัครสมาชิก</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
