<?php
/** ขอความยินยอม: แสดงเมื่อผู้ใช้ยังไม่ยอมรับนโยบายฉบับล่าสุด */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$uid = (int)current_user()['id'];
$next = get('next', post('next'));
if ($next === '' || !str_starts_with($next, BASE_URL . '/') || str_starts_with($next, '//')) {
    $next = url('index.php');
}
if (pdpa_has_consented($uid)) {
    redirect($next);
}
$policy = pdpa_current_policy();
$previous = pdpa_last_consent($uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('decision') === 'accept' && post('agree') === '1') {
        pdpa_record_consent($uid, 'accept');
        log_activity('pdpa_accept', 'นโยบายฉบับที่ ' . $policy['version']);
        flash('success', 'ขอบคุณที่ยอมรับนโยบายคุ้มครองข้อมูลส่วนบุคคล');
        redirect($next);
    }
    if (post('decision') === 'decline') {
        log_activity('pdpa_decline', 'นโยบายฉบับที่ ' . $policy['version']);
        $_SESSION = [];
        session_regenerate_id(true);
        flash('warning', 'คุณยังไม่ได้ยอมรับนโยบายคุ้มครองข้อมูลส่วนบุคคล จึงยังใช้งานระบบสำหรับสมาชิกไม่ได้ หากมีข้อสงสัยกรุณาติดต่อเจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล');
        redirect('privacy.php');
    }
    flash('danger', 'กรุณาทำเครื่องหมายยืนยันว่าได้อ่านและยอมรับนโยบายแล้ว');
    redirect('pdpa-consent.php?next=' . urlencode($next));
}

$pageTitle = 'ยืนยันนโยบายคุ้มครองข้อมูลส่วนบุคคล';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-1"><i class="bi bi-shield-check me-2 text-primary"></i><?= $previous ? 'นโยบายคุ้มครองข้อมูลส่วนบุคคลมีการปรับปรุง' : 'นโยบายคุ้มครองข้อมูลส่วนบุคคล' ?></h4>
        <p class="text-muted"><?= $previous && $previous['action'] === 'accept'
            ? 'วิทยาลัยปรับปรุงนโยบายเป็นฉบับที่ ' . (int)$policy['version'] . ' กรุณาอ่านและยืนยันอีกครั้งก่อนใช้งานต่อ'
            : 'กรุณาอ่านและยืนยันก่อนเริ่มใช้งานระบบ' ?></p>
        <div class="border rounded p-3 bg-light pdpa-policy" style="max-height:50vh;overflow:auto"><?= pdpa_render($policy['content']) ?></div>
        <form method="post" class="mt-3">
          <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="agree" value="1" id="agree" required>
            <label class="form-check-label" for="agree">ข้าพเจ้าได้อ่านและเข้าใจนโยบายคุ้มครองข้อมูลส่วนบุคคล ฉบับที่ <?= (int)$policy['version'] ?> และยินยอมให้วิทยาลัยเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลของข้าพเจ้าตามนโยบายนี้</label>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" name="decision" value="accept"><i class="bi bi-check2-circle me-1"></i>ยอมรับและใช้งานต่อ</button>
            <button class="btn btn-outline-secondary" name="decision" value="decline" formnovalidate>ไม่ยอมรับ (ออกจากระบบ)</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
