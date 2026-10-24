<?php
require_once __DIR__ . '/includes/bootstrap.php';

$versions = db_all('SELECT version, effective_date FROM pdpa_policies ORDER BY version DESC');
$policy = get('v') !== ''
    ? db_row('SELECT * FROM pdpa_policies WHERE version = ?', 'i', [(int)get('v')])
    : pdpa_current_policy();
$current = pdpa_current_policy();

$pageTitle = 'นโยบายคุ้มครองข้อมูลส่วนบุคคล';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <?php if (!$policy): ?>
          <div class="alert alert-warning mb-0">ยังไม่มีนโยบายคุ้มครองข้อมูลส่วนบุคคล</div>
        <?php else: ?>
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
              <h3 class="mb-1"><i class="bi bi-shield-check me-2 text-primary"></i><?= e($policy['title']) ?></h3>
              <div class="text-muted small">ฉบับที่ <?= (int)$policy['version'] ?> · มีผลตั้งแต่ <?= thai_date($policy['effective_date'], false) ?>
                <?= (int)$policy['version'] !== (int)$current['version'] ? ' · <span class="text-danger">ฉบับเก่า</span> <a href="' . url('privacy.php') . '">ดูฉบับปัจจุบัน</a>' : '' ?></div>
            </div>
            <?php if (count($versions) > 1): ?>
              <form class="d-print-none"><select class="form-select form-select-sm" name="v" onchange="this.form.submit()">
                <?php foreach ($versions as $v): ?><option value="<?= (int)$v['version'] ?>"<?= (int)$v['version'] === (int)$policy['version'] ? ' selected' : '' ?>>ฉบับที่ <?= (int)$v['version'] ?> (<?= thai_date($v['effective_date']) ?>)</option><?php endforeach; ?>
              </select></form>
            <?php endif; ?>
          </div>
          <div class="pdpa-policy"><?= pdpa_render($policy['content']) ?></div>
          <?php if (is_logged_in()): ?>
            <hr><a class="btn btn-outline-primary btn-sm" href="<?= url('my-data.php') ?>"><i class="bi bi-person-lock me-1"></i>ข้อมูลส่วนบุคคลของฉัน / ใช้สิทธิ์เจ้าของข้อมูล</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
