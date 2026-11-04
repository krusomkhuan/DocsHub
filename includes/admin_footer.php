    </main>
    <footer class="px-4 pb-4 small text-muted">
      <?php require __DIR__ . '/traffic_widget.php'; ?>
      <?= e(setting('site_name')) ?> เวอร์ชัน <?= e(APP_VERSION) ?> · พัฒนาระบบโดย <?= e(APP_DEVELOPER) ?> <?= e(APP_DEVELOPER_DEPT) ?>
    </footer>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script src="<?= url('assets/js/traffic.js') ?>"></script>
</body>
</html>
