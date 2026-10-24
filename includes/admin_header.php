<?php
/** ส่วนหัวระบบหลังบ้าน ใช้ $pageTitle และ $activeMenu */
$pageTitle = $pageTitle ?? 'ระบบหลังบ้าน';
$activeMenu = $activeMenu ?? '';
$pendingDocs = (int)db_val("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
$pendingPdpa = is_admin() ? (int)db_val("SELECT COUNT(*) FROM pdpa_requests WHERE status IN ('pending','processing')") : 0;
$pendingAlbums = (int)db_val("SELECT COUNT(*) FROM photo_albums WHERE status = 'pending'");
$pendingUsers = is_admin() ? (int)db_val("SELECT COUNT(*) FROM users WHERE status = 'pending'") : 0;
$menu = [
    ['dashboard', 'index.php', 'bi-speedometer2', 'แดชบอร์ด', 0, false],
    ['documents', 'documents.php', 'bi-files', 'จัดการเอกสาร', $pendingDocs, false],
    ['trip', 'trip-reports.php', 'bi-geo-alt', 'รายงานไปราชการ', 0, false],
    ['albums', 'photo-albums.php', 'bi-images', 'คลังรูปภาพ', $pendingAlbums, false],
    ['book', 'book-numbers.php', 'bi-123', 'ทะเบียนเลขหนังสือ', 0, false],
    ['doctypes', 'doc-types.php', 'bi-grid', 'ประเภทเอกสาร', 0, true],
    ['categories', 'categories.php', 'bi-tags', 'หมวดหมู่เอกสาร', 0, false],
    ['departments', 'departments.php', 'bi-building', 'แผนกวิชา / ฝ่าย', 0, true],
    ['ledgermgr', 'ledger-managers.php', 'bi-person-badge', 'ผู้รับผิดชอบบัญชีแผนก', 0, true],
    ['years', 'academic-years.php', 'bi-calendar3', 'ปีการศึกษา', 0, true],
    ['users', 'users.php', 'bi-people', 'สมาชิก', $pendingUsers, true],
    ['settings', 'settings.php', 'bi-gear', 'ตั้งค่าระบบ', 0, true],
    ['pdpa', 'pdpa.php', 'bi-shield-lock', 'PDPA ข้อมูลส่วนบุคคล', $pendingPdpa, true],
    ['syscheck', 'system-check.php', 'bi-heart-pulse', 'ตรวจสอบระบบ', 0, true],
    ['backup', 'backup.php', 'bi-cloud-arrow-down', 'สำรอง / กู้คืนข้อมูล', 0, true],
    ['logs', 'logs.php', 'bi-clock-history', 'บันทึกการใช้งาน', 0, true],
];
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | หลังบ้าน <?= e(setting('site_name')) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="d-lg-flex">
  <aside class="admin-sidebar p-3">
    <a href="<?= url('admin/index.php') ?>" class="d-flex align-items-center gap-2 text-white text-decoration-none mb-3">
      <span class="brand-logo"><i class="bi bi-folder2-open"></i></span>
      <span><span class="fw-semibold d-block" style="font-family:Prompt"><?= e(setting('site_name')) ?></span><small class="opacity-75">ระบบหลังบ้าน · v<?= e(APP_VERSION) ?></small></span>
    </a>
    <button class="btn btn-sm btn-outline-light d-lg-none w-100 mb-2" data-bs-toggle="collapse" data-bs-target="#adminMenu">เมนู</button>
    <nav class="collapse d-lg-block" id="adminMenu">
      <div class="nav flex-column gap-1">
        <?php foreach ($menu as [$key, $href, $icon, $label, $badge, $adminOnly]): if ($adminOnly && !is_admin()) continue; ?>
          <a class="nav-link d-flex align-items-center<?= $activeMenu === $key ? ' active' : '' ?>" href="<?= url('admin/' . $href) ?>">
            <i class="bi <?= $icon ?> me-2"></i><?= $label ?>
            <?php if ($badge): ?><span class="badge text-bg-warning ms-auto"><?= $badge ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <div class="nav-section">ทางลัด</div>
        <a class="nav-link" href="<?= url('manual.php#admin') ?>" target="_blank"><i class="bi bi-book me-2"></i>คู่มือการใช้งาน</a>
        <a class="nav-link" href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>หน้าบ้าน</a>
        <a class="nav-link" href="https://krusomkhuan.com/prompthub/public/" target="_blank" rel="noopener" title="ศูนย์รวมคำสั่ง AI (Prompt) ภาษาไทย"><i class="bi bi-stars me-2"></i>Prompt Hub</a>
        <a class="nav-link" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a>
      </div>
    </nav>
  </aside>
  <div class="flex-grow-1 min-vw-0" style="min-width:0">
    <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><?= e($pageTitle) ?></h5>
      <span class="small text-muted d-flex align-items-center gap-2"><?= avatar_html(current_user(), 30) ?><?= e(current_user()['fullname']) ?> (<?= e(role_label(current_user()['role'])) ?>)</span>
    </header>
    <main class="p-3 p-lg-4">
      <?= render_flash() ?>
