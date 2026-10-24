<?php
/** ส่วนหัวหน้าบ้าน ใช้ตัวแปร $pageTitle และ $activeNav */
$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';
$u = current_user();
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($pageTitle ? $pageTitle . ' | ' : '') . setting('site_name', 'Docs Hub')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-hub sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('index.php') ?>">
      <span class="brand-logo"><i class="bi bi-folder2-open"></i></span>
      <span>
        <span class="fw-semibold"><?= e(setting('site_name', 'Docs Hub')) ?></span>
        <small class="d-block brand-sub"><?= e(setting('college_name')) ?></small>
      </span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link<?= $activeNav === 'home' ? ' active' : '' ?>" href="<?= url('index.php') ?>">หน้าแรก</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle<?= $activeNav === 'docs' ? ' active' : '' ?>" href="#" data-bs-toggle="dropdown">หมวดเอกสาร</a>
          <ul class="dropdown-menu">
            <?php foreach (doc_types() as $t): ?>
              <li><a class="dropdown-item" href="<?= url('documents.php?type=' . urlencode($t['slug'])) ?>"><i class="bi <?= e($t['icon']) ?> me-2 text-<?= e($t['color']) ?>"></i><?= e($t['name']) ?></a></li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('documents.php') ?>"><i class="bi bi-collection me-2"></i>เอกสารทั้งหมด</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link<?= $activeNav === 'trip' ? ' active' : '' ?>" href="<?= url('trip-reports.php') ?>">รายงานไปราชการ</a></li>
        <li class="nav-item"><a class="nav-link<?= $activeNav === 'albums' ? ' active' : '' ?>" href="<?= url('photo-albums.php') ?>">คลังรูปภาพ</a></li>
        <?php if ($u): ?>
          <li class="nav-item"><a class="nav-link<?= $activeNav === 'book' ? ' active' : '' ?>" href="<?= url('book-numbers.php') ?>">ออกเลขหนังสือ</a></li>
          <li class="nav-item"><a class="nav-link<?= $activeNav === 'mydocs' ? ' active' : '' ?>" href="<?= url('my-documents.php') ?>">เอกสารของฉัน</a></li>
          <?php if ($ledgerDept = ledger_department()): $lp = ledger_perms($ledgerDept); ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle<?= $activeNav === 'ledger' ? ' active' : '' ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-cash-coin me-1"></i>บัญชีแผนก</a>
              <ul class="dropdown-menu">
                <li><span class="dropdown-item-text small text-muted"><?= e($ledgerDept['name']) ?></span></li>
                <li><a class="dropdown-item" href="<?= url('ledger.php') ?>"><i class="bi bi-speedometer me-2"></i>ภาพรวมและรายการ</a></li>
                <?php if ($lp['add']): ?>
                  <li><a class="dropdown-item" href="<?= url('ledger-entry.php?kind=income') ?>"><i class="bi bi-plus-circle me-2 text-success"></i>บันทึกรายรับ</a></li>
                  <li><a class="dropdown-item" href="<?= url('ledger-entry.php?kind=expense') ?>"><i class="bi bi-dash-circle me-2 text-danger"></i>บันทึกรายจ่าย</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('ledger.php?kind=income') ?>"><i class="bi bi-arrow-down-circle me-2"></i>รายการรายรับ</a></li>
                <li><a class="dropdown-item" href="<?= url('ledger.php?kind=expense') ?>"><i class="bi bi-arrow-up-circle me-2"></i>รายการรายจ่าย</a></li>
                <li><a class="dropdown-item" href="<?= url('ledger.php?export=csv') ?>"><i class="bi bi-filetype-csv me-2"></i>ส่งออก CSV (ปีงบประมาณนี้)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('ledger-categories.php') ?>"><i class="bi bi-gear me-2"></i><?= $lp['manage'] ? 'หมวดและการตั้งค่าบัญชี' : 'หมวดบัญชี / ผู้รับผิดชอบบัญชี' ?></a></li>
              </ul>
            </li>
          <?php else: // ยังไม่ระบุแผนก: แสดงเมนูพร้อมคำอธิบาย (บัญชีแผนกเห็นได้เฉพาะครูที่สังกัดแผนก) ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle<?= $activeNav === 'ledger' ? ' active' : '' ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-cash-coin me-1"></i>บัญชีแผนก</a>
              <div class="dropdown-menu p-3 small text-muted" style="min-width:280px">
                <i class="bi bi-info-circle me-1"></i>บัญชีของคุณยังไม่ได้ระบุ<strong>แผนกวิชา</strong> จึงยังใช้บัญชีแผนกไม่ได้
                (บัญชีแผนกเห็นได้เฉพาะครูที่สังกัดแผนกนั้น)
                <?php if (is_admin()): ?>
                  <a class="d-block mt-2" href="<?= url('admin/users.php') ?>"><i class="bi bi-people me-1"></i>กำหนดแผนกที่ หลังบ้าน → สมาชิก</a>
                <?php else: ?>
                  <div class="mt-2">ติดต่อผู้ดูแลระบบเพื่อกำหนดแผนกให้</div>
                <?php endif; ?>
              </div>
            </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link<?= $activeNav === 'manual' ? ' active' : '' ?>" href="<?= url('manual.php') ?>" title="คู่มือการใช้งาน"><i class="bi bi-question-circle"></i><span class="d-lg-none ms-1">คู่มือการใช้งาน</span></a></li>
        <?php if ($u): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown"><?= avatar_html($u, 28) ?><?= e($u['fullname']) ?></a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><span class="dropdown-item-text small text-muted"><?= e(role_label($u['role'])) ?><?= $u['dept_name'] ? ' · ' . e($u['dept_name']) : '' ?></span></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('upload.php') ?>"><i class="bi bi-cloud-upload me-2"></i>อัปโหลดเอกสาร</a></li>
              <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person-gear me-2"></i>ข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="<?= url('my-data.php') ?>"><i class="bi bi-person-lock me-2"></i>ข้อมูลส่วนบุคคลของฉัน (PDPA)</a></li>
              <?php if (is_staff()): ?>
                <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>ระบบหลังบ้าน</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a></li>
          <?php if (setting('allow_register', '1') === '1'): ?>
            <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2 mt-1" href="<?= url('register.php') ?>">สมัครสมาชิก</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="flex-grow-1">
<?php if (empty($noContainer)): ?><div class="container py-4"><?= render_flash() ?><?php endif; ?>
