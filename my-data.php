<?php
/** ศูนย์ข้อมูลส่วนบุคคลของฉัน: ดูข้อมูลที่ระบบเก็บ ดาวน์โหลด ยื่นคำร้องใช้สิทธิ์ และถอนความยินยอม */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$u = current_user();
$uid = (int)$u['id'];
$types = pdpa_request_types();

if (get('download') === 'json') {
    log_activity('pdpa_export', 'ดาวน์โหลดข้อมูลส่วนบุคคลของตนเอง');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="my-data-' . $u['username'] . '-' . date('Ymd') . '.json"');
    echo json_encode(pdpa_export_user($uid), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'request') {
        $type = post('request_type');
        $detail = mb_substr(post('detail'), 0, 2000);
        if (!isset($types[$type])) {
            flash('danger', 'กรุณาเลือกประเภทคำร้อง');
        } elseif (in_array($type, ['rectify', 'object', 'restrict', 'other'], true) && $detail === '') {
            flash('danger', 'กรุณาระบุรายละเอียดคำร้อง');
        } else {
            db_exec('INSERT INTO pdpa_requests (user_id, requester_name, request_type, detail) VALUES (?,?,?,?)', 'isss', [$uid, $u['fullname'], $type, $detail ?: null]);
            log_activity('pdpa_request', $types[$type]);
            flash('success', 'ส่งคำร้องแล้ว วิทยาลัยจะดำเนินการภายใน 30 วัน ติดตามสถานะได้ที่หน้านี้');
        }
        redirect('my-data.php');
    }
    if (post('action') === 'withdraw' && post('confirm') === '1') {
        pdpa_record_consent($uid, 'withdraw');
        db_exec("INSERT INTO pdpa_requests (user_id, requester_name, request_type, detail) VALUES (?,?,'withdraw',?)", 'iss',
            [$uid, $u['fullname'], 'ถอนความยินยอมผ่านระบบ' . (post('detail') !== '' ? ': ' . mb_substr(post('detail'), 0, 1000) : '')]);
        log_activity('pdpa_withdraw');
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'บันทึกการถอนความยินยอมแล้ว และส่งคำร้องถึงผู้ดูแลระบบ หากเข้าสู่ระบบอีกครั้ง ระบบจะขอความยินยอมใหม่');
        redirect('privacy.php');
    }
}

$consents = db_all('SELECT * FROM pdpa_consents WHERE user_id = ? ORDER BY id DESC', 'i', [$uid]);
$requests = db_all('SELECT * FROM pdpa_requests WHERE user_id = ? ORDER BY id DESC', 'i', [$uid]);
$stats = [
    'เอกสารที่อัปโหลด'       => (int)db_val('SELECT COUNT(*) FROM documents WHERE uploaded_by = ?', 'i', [$uid]),
    'รายงานการไปราชการ'      => (int)db_val('SELECT COUNT(*) FROM trip_reports WHERE created_by = ?', 'i', [$uid]),
    'เลขหนังสือที่ออก'        => (int)db_val('SELECT COUNT(*) FROM book_numbers WHERE created_by = ?', 'i', [$uid]),
    'รายการบัญชีที่บันทึก'     => (int)db_val('SELECT COUNT(*) FROM ledger_entries WHERE created_by = ?', 'i', [$uid]),
    'ประวัติการใช้งาน'         => (int)db_val('SELECT COUNT(*) FROM activity_logs WHERE user_id = ?', 'i', [$uid]),
];
$logs = db_all('SELECT action, ip, created_at FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 15', 'i', [$uid]);

$pageTitle = 'ข้อมูลส่วนบุคคลของฉัน';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-person-lock me-2"></i>ข้อมูลส่วนบุคคลของฉัน</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?= url('privacy.php') ?>"><i class="bi bi-shield-check me-1"></i>นโยบายความเป็นส่วนตัว</a>
    <a class="btn btn-primary btn-sm" href="?download=json"><i class="bi bi-download me-1"></i>ดาวน์โหลดข้อมูลของฉัน (JSON)</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">ข้อมูลที่ระบบเก็บเกี่ยวกับคุณ</div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex align-items-center gap-3"><?= avatar_html($u, 48) ?><div><strong><?= e($u['fullname']) ?></strong><div class="text-muted"><?= e($u['username']) ?> · <?= e(role_label($u['role'])) ?> · <?= e($u['dept_name'] ?? 'ไม่ระบุแผนก') ?></div></div></li>
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">อีเมล</span><span><?= e($u['email'] ?: '-') ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">เบอร์โทร</span><span><?= e($u['phone'] ?: '-') ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">สมัครเมื่อ / เข้าใช้ล่าสุด</span><span><?= thai_date($u['created_at']) ?> / <?= thai_date($u['last_login'], true, true) ?></span></li>
        <?php foreach ($stats as $lbl => $n): ?>
          <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e($lbl) ?></span><span><?= number_format($n) ?></span></li>
        <?php endforeach; ?>
        <li class="list-group-item"><a href="<?= url('profile.php') ?>"><i class="bi bi-pencil me-1"></i>แก้ไขข้อมูลส่วนตัวด้วยตนเอง</a></li>
      </ul>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">ประวัติการให้ความยินยอม</div>
      <ul class="list-group list-group-flush small">
        <?php if (!$consents): ?><li class="list-group-item text-muted">ไม่มีข้อมูล</li><?php endif; ?>
        <?php foreach ($consents as $c): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span><?= $c['action'] === 'accept' ? '<i class="bi bi-check-circle text-success"></i> ยอมรับ' : '<i class="bi bi-x-circle text-danger"></i> ถอนความยินยอม' ?> นโยบายฉบับที่ <?= (int)$c['policy_version'] ?></span>
            <span class="text-muted"><?= thai_date($c['created_at'], true, true) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">ประวัติการใช้งานล่าสุดของคุณ</div>
      <ul class="list-group list-group-flush small">
        <?php if (!$logs): ?><li class="list-group-item text-muted">ไม่มีข้อมูล</li><?php endif; ?>
        <?php foreach ($logs as $l): ?>
          <li class="list-group-item d-flex justify-content-between"><span><code><?= e($l['action']) ?></code></span><span class="text-muted"><?= thai_date($l['created_at'], true, true) ?> · <?= e($l['ip']) ?></span></li>
        <?php endforeach; ?>
        <li class="list-group-item text-muted">เก็บไว้ <?= e(setting('log_retention_days', '365')) ?> วัน แล้วลบอัตโนมัติ · ดาวน์โหลด JSON เพื่อดูทั้งหมด</li>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-send me-1"></i>ยื่นคำร้องใช้สิทธิ์เจ้าของข้อมูล</div>
      <form method="post" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="request">
        <div class="mb-2"><label class="form-label">ประเภทคำร้อง</label>
          <select class="form-select" name="request_type" required>
            <option value="">— เลือก —</option>
            <?php foreach ($types as $k => $lbl): if ($k === 'withdraw') continue; ?><option value="<?= $k ?>"><?= e($lbl) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea class="form-control" name="detail" rows="3" maxlength="2000" placeholder="เช่น ข้อมูลที่ต้องการแก้ไข หรือเหตุผลของคำร้อง"></textarea></div>
        <button class="btn btn-primary btn-sm">ส่งคำร้อง</button>
        <div class="form-text">วิทยาลัยจะดำเนินการภายใน 30 วัน · เจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล: <?= e(setting('pdpa_dpo_name') ?: '-') ?> <?= setting('pdpa_dpo_email') ? '(' . e(setting('pdpa_dpo_email')) . ')' : '' ?></div>
      </form>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">คำร้องของฉัน</div>
      <ul class="list-group list-group-flush small">
        <?php if (!$requests): ?><li class="list-group-item text-muted">ยังไม่มีคำร้อง</li><?php endif; ?>
        <?php foreach ($requests as $r): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between"><strong><?= e($types[$r['request_type']]) ?></strong><?= pdpa_status_label($r['status']) ?></div>
            <div class="text-muted">ยื่นเมื่อ <?= thai_date($r['created_at'], true, true) ?><?= $r['handled_at'] ? ' · ดำเนินการ ' . thai_date($r['handled_at'], true, true) : '' ?></div>
            <?php if ($r['detail']): ?><div><?= e($r['detail']) ?></div><?php endif; ?>
            <?php if ($r['response']): ?><div class="mt-1 p-2 bg-light rounded"><i class="bi bi-reply me-1"></i><?= e($r['response']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card shadow-sm border-danger">
      <div class="card-header bg-danger-subtle fw-semibold">ถอนความยินยอม</div>
      <form method="post" class="card-body small" data-confirm="ยืนยันการถอนความยินยอม? คุณจะถูกออกจากระบบทันที">
        <?= csrf_field() ?><input type="hidden" name="action" value="withdraw">
        <p>การถอนความยินยอมจะทำให้คุณใช้งานระบบสำหรับสมาชิกไม่ได้ และระบบจะส่งคำร้องให้ผู้ดูแลพิจารณาลบข้อมูลส่วนบุคคลของคุณ เอกสารราชการที่เคยอัปโหลดจะยังคงเก็บไว้ตามระเบียบงานสารบรรณ แต่จะไม่ผูกกับชื่อของคุณ</p>
        <textarea class="form-control form-control-sm mb-2" name="detail" rows="2" maxlength="1000" placeholder="เหตุผล (ไม่บังคับ)"></textarea>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm" value="1" id="wc" required><label class="form-check-label" for="wc">ข้าพเจ้าเข้าใจผลของการถอนความยินยอม</label></div>
        <button class="btn btn-outline-danger btn-sm">ถอนความยินยอม</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
