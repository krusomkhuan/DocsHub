<?php if (empty($noContainer)): ?></div><?php endif; ?>
</main>
<footer class="footer-hub mt-auto py-4">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small">
    <div>
      <strong><?= e(setting('site_name', 'Docs Hub')) ?></strong> — <?= e(setting('site_slogan')) ?><br>
      <?php if (setting('college_url') !== ''): ?>
        <a class="link-light" href="<?= e(setting('college_url')) ?>" target="_blank" rel="noopener"><?= e(setting('college_name')) ?> <i class="bi bi-box-arrow-up-right small"></i></a>
      <?php else: ?>
        <?= e(setting('college_name')) ?>
      <?php endif; ?>
    </div>
    <div class="text-md-end">
      <a class="link-light" href="<?= url('privacy.php') ?>"><i class="bi bi-shield-check me-1"></i>นโยบายคุ้มครองข้อมูลส่วนบุคคล</a><br>
      © <?= current_year_be() ?> <?= e(setting('college_name')) ?> · เวอร์ชัน <?= e(APP_VERSION) ?><br>
      พัฒนาระบบโดย <?= e(APP_DEVELOPER) ?> <?= e(APP_DEVELOPER_DEPT) ?>
    </div>
  </div>
</footer>
<div id="cookieNotice" class="position-fixed bottom-0 start-0 end-0 p-3 d-none d-print-none" style="z-index:1080">
  <div class="container"><div class="bg-dark text-white rounded-3 shadow p-3 d-flex flex-column flex-md-row align-items-md-center gap-2 small">
    <div class="flex-grow-1"><i class="bi bi-cookie me-1"></i>เว็บไซต์นี้ใช้เฉพาะคุกกี้ที่จำเป็นต่อการเข้าสู่ระบบและการทำงานของเว็บไซต์ ไม่มีคุกกี้เพื่อการโฆษณาหรือติดตามพฤติกรรม ดูรายละเอียดใน <a class="link-warning" href="<?= url('privacy.php') ?>">นโยบายคุ้มครองข้อมูลส่วนบุคคล</a></div>
    <button class="btn btn-warning btn-sm text-nowrap" id="cookieOk">รับทราบ</button>
  </div></div>
</div>
<script>
(function () {
  var box = document.getElementById('cookieNotice'), seen = false;
  try { seen = localStorage.getItem('docshub_cookie_notice') === '1'; } catch (e) {}
  if (!seen) box.classList.remove('d-none');
  document.getElementById('cookieOk').onclick = function () {
    try { localStorage.setItem('docshub_cookie_notice', '1'); } catch (e) {}
    box.classList.add('d-none');
  };
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
