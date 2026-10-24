<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$types = pdpa_request_types();
$tab = in_array(get('tab'), ['requests', 'policy', 'settings', 'consents'], true) ? get('tab') : 'requests';
$policy = pdpa_current_policy();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'handle') {
        $req = db_row('SELECT * FROM pdpa_requests WHERE id = ?', 'i', [(int)post('id')]);
        $status = post('status');
        if ($req && in_array($status, ['pending', 'processing', 'done', 'rejected'], true)) {
            $msg = 'บันทึกการดำเนินการคำร้องแล้ว';
            if (post('anonymize') === '1' && $req['user_id'] && in_array($req['request_type'], ['erase', 'withdraw'], true)) {
                $target = db_row('SELECT id, role FROM users WHERE id = ?', 'i', [$req['user_id']]);
                if ((int)$req['user_id'] === (int)current_user()['id']) {
                    flash('danger', 'ไม่สามารถลบข้อมูลบัญชีของตนเองได้');
                    redirect('admin/pdpa.php?tab=requests');
                } elseif ($target && $target['role'] === 'admin') {
                    flash('danger', 'บัญชีนี้เป็นผู้ดูแลระบบ กรุณาเปลี่ยนสิทธิ์เป็นสมาชิกก่อนลบข้อมูล');
                    redirect('admin/pdpa.php?tab=requests');
                } elseif ($target) {
                    pdpa_anonymize_user((int)$req['user_id']);
                    db_exec("UPDATE pdpa_requests SET requester_name = ? WHERE user_id = ?", 'si', ['ผู้ใช้ที่ลบข้อมูลแล้ว #' . $req['user_id'], $req['user_id']]);
                    log_activity('pdpa_anonymize', 'บัญชี #' . $req['user_id']);
                    $msg .= ' และลบข้อมูลส่วนบุคคลของบัญชี #' . $req['user_id'] . ' แล้ว';
                    $status = 'done';
                }
            }
            db_exec('UPDATE pdpa_requests SET status = ?, response = ?, handled_by = ?, handled_at = IF(? IN (\'done\',\'rejected\'), NOW(), handled_at) WHERE id = ?', 'ssisi',
                [$status, mb_substr(post('response'), 0, 2000) ?: null, current_user()['id'], $status, $req['id']]);
            log_activity('pdpa_handle', 'คำร้อง #' . $req['id'] . ' → ' . $status);
            flash('success', $msg);
        }
        redirect('admin/pdpa.php?tab=requests');
    }

    if ($action === 'policy') {
        $title = mb_substr(post('title'), 0, 255) ?: 'นโยบายคุ้มครองข้อมูลส่วนบุคคล';
        $content = trim((string)($_POST['content'] ?? ''));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('effective_date')) ? post('effective_date') : date('Y-m-d');
        if (mb_strlen($content) < 50) {
            flash('danger', 'เนื้อหานโยบายสั้นเกินไป');
        } elseif (post('mode') === 'new') {
            $ver = (int)db_val('SELECT COALESCE(MAX(version), 0) + 1 FROM pdpa_policies');
            db_exec('INSERT INTO pdpa_policies (version, title, content, effective_date, created_by) VALUES (?,?,?,?,?)', 'isssi', [$ver, $title, $content, $date, current_user()['id']]);
            log_activity('pdpa_policy_new', 'ฉบับที่ ' . $ver);
            flash('success', 'เผยแพร่นโยบายฉบับที่ ' . $ver . ' แล้ว ผู้ใช้ทุกคนจะต้องยอมรับใหม่เมื่อเข้าใช้งานครั้งถัดไป');
        } elseif ($policy) {
            db_exec('UPDATE pdpa_policies SET title = ?, content = ?, effective_date = ? WHERE id = ?', 'sssi', [$title, $content, $date, $policy['id']]);
            log_activity('pdpa_policy_edit', 'แก้ไขถ้อยคำฉบับที่ ' . $policy['version']);
            flash('success', 'แก้ไขถ้อยคำในฉบับปัจจุบันแล้ว (ไม่ต้องขอความยินยอมใหม่)');
        }
        redirect('admin/pdpa.php?tab=policy');
    }

    if ($action === 'settings') {
        $email = post('pdpa_dpo_email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'รูปแบบอีเมลไม่ถูกต้อง');
            redirect('admin/pdpa.php?tab=settings');
        }
        $vals = [
            'pdpa_dpo_name'      => mb_substr(post('pdpa_dpo_name'), 0, 150),
            'pdpa_dpo_email'     => $email,
            'pdpa_dpo_phone'     => mb_substr(post('pdpa_dpo_phone'), 0, 50),
            'log_retention_days' => (string)max(30, (int)post('log_retention_days')),
        ];
        foreach ($vals as $k => $v) {
            db_exec('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', [$k, $v]);
        }
        log_activity('pdpa_settings');
        flash('success', 'บันทึกการตั้งค่า PDPA แล้ว');
        redirect('admin/pdpa.php?tab=settings');
    }
}

$counts = db_row("SELECT SUM(status='pending') p, SUM(status='processing') i, SUM(status IN ('pending','processing') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) late FROM pdpa_requests");
$activeUsers = (int)db_val("SELECT COUNT(*) FROM users WHERE status = 'active'");
$accepted = (int)db_val("SELECT COUNT(*) FROM users u WHERE u.status = 'active' AND (SELECT c.action = 'accept' AND c.policy_version >= ? FROM pdpa_consents c WHERE c.user_id = u.id ORDER BY c.id DESC LIMIT 1)", 'i', [(int)($policy['version'] ?? 1)]);

$pageTitle = 'PDPA — คุ้มครองข้อมูลส่วนบุคคล';
$activeMenu = 'pdpa';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3 mb-3">
  <?php foreach ([['คำร้องรอดำเนินการ', (int)$counts['p'], 'bi-inbox', 'warning'], ['กำลังดำเนินการ', (int)$counts['i'], 'bi-hourglass-split', 'info'], ['เกิน 30 วัน', (int)$counts['late'], 'bi-alarm', (int)$counts['late'] ? 'danger' : 'secondary'], ['ยอมรับนโยบายฉบับปัจจุบัน', $accepted . ' / ' . $activeUsers, 'bi-people', 'success']] as [$lbl, $val, $ic, $c]): ?>
    <div class="col-6 col-xl-3"><div class="card shadow-sm stat-card"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon bg-<?= $c ?>-subtle text-<?= $c ?>-emphasis"><i class="bi <?= $ic ?>"></i></div>
      <div><div class="fs-5 fw-semibold"><?= e((string)$val) ?></div><div class="small text-muted"><?= $lbl ?></div></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach (['requests' => 'คำร้องใช้สิทธิ์', 'policy' => 'นโยบาย', 'settings' => 'เจ้าหน้าที่ DPO / การเก็บรักษา', 'consents' => 'บันทึกความยินยอม'] as $k => $lbl): ?>
    <li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="?tab=<?= $k ?>"><?= $lbl ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'requests'):
    $reqs = db_all("SELECT r.*, u.username, u.anonymized_at, h.fullname AS handler FROM pdpa_requests r LEFT JOIN users u ON u.id = r.user_id LEFT JOIN users h ON h.id = r.handled_by
                    ORDER BY FIELD(r.status,'pending','processing','done','rejected'), r.created_at DESC LIMIT 200"); ?>
  <?php if (!$reqs): ?><div class="card shadow-sm"><div class="card-body text-muted text-center py-5">ยังไม่มีคำร้อง</div></div><?php endif; ?>
  <?php foreach ($reqs as $r): $late = in_array($r['status'], ['pending', 'processing'], true) && strtotime($r['created_at']) < strtotime('-30 days'); ?>
    <div class="card shadow-sm mb-3<?= $late ? ' border-danger' : '' ?>">
      <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
        <span><strong>#<?= $r['id'] ?> <?= e($types[$r['request_type']]) ?></strong> — <?= e($r['requester_name']) ?><?= $r['username'] ? ' <span class="text-muted small">(' . e($r['username']) . ')</span>' : '' ?></span>
        <span class="small"><?= pdpa_status_label($r['status']) ?> · ยื่น <?= thai_date($r['created_at'], true, true) ?>
          <?php if (in_array($r['status'], ['pending', 'processing'], true)): ?> · <span class="<?= $late ? 'text-danger fw-semibold' : 'text-muted' ?>">ครบกำหนด <?= thai_date(date('Y-m-d', strtotime($r['created_at'] . ' +30 days'))) ?></span><?php endif; ?></span>
      </div>
      <div class="card-body">
        <?php if ($r['detail']): ?><p class="mb-2" style="white-space:pre-line"><?= e($r['detail']) ?></p><?php endif; ?>
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="handle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <div class="col-md-3"><label class="form-label small mb-0">สถานะ</label>
            <select class="form-select form-select-sm" name="status">
              <?php foreach (['pending' => 'รอดำเนินการ', 'processing' => 'กำลังดำเนินการ', 'done' => 'ดำเนินการแล้ว', 'rejected' => 'ปฏิเสธ'] as $k => $lbl): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-6"><label class="form-label small mb-0">ตอบกลับผู้ยื่นคำร้อง</label><input class="form-control form-control-sm" name="response" maxlength="2000" value="<?= e($r['response']) ?>"></div>
          <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">บันทึก</button></div>
          <?php if ($r['user_id'] && !$r['anonymized_at'] && in_array($r['request_type'], ['erase', 'withdraw'], true)): ?>
            <div class="col-12"><div class="form-check small text-danger">
              <input class="form-check-input" type="checkbox" name="anonymize" value="1" id="an<?= $r['id'] ?>" onchange="this.form.dataset.confirm = this.checked ? 'ลบข้อมูลส่วนบุคคลของบัญชีนี้ถาวร (ชื่อ อีเมล เบอร์โทร ภาพโปรไฟล์ IP) และระงับบัญชี? ย้อนกลับไม่ได้' : ''">
              <label class="form-check-label" for="an<?= $r['id'] ?>">ลบข้อมูลส่วนบุคคลของบัญชีนี้ (anonymize) และระงับบัญชี — เอกสารราชการยังคงอยู่</label>
            </div></div>
          <?php elseif ($r['anonymized_at']): ?>
            <div class="col-12 small text-muted"><i class="bi bi-check2-circle"></i> ลบข้อมูลส่วนบุคคลของบัญชีแล้วเมื่อ <?= thai_date($r['anonymized_at'], true, true) ?></div>
          <?php endif; ?>
          <?php if ($r['handler']): ?><div class="col-12 small text-muted">ผู้ดำเนินการ: <?= e($r['handler']) ?><?= $r['handled_at'] ? ' · ' . thai_date($r['handled_at'], true, true) : '' ?></div><?php endif; ?>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'policy'): ?>
  <div class="card shadow-sm">
    <form method="post" class="card-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="policy">
      <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>เนื้อหาเริ่มต้นเป็นแม่แบบ ควรให้ผู้รับผิดชอบด้านกฎหมายหรือเจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคลของวิทยาลัยตรวจทานก่อนใช้งานจริง</div>
      <div class="row g-2 mb-2">
        <div class="col-md-8"><label class="form-label">ชื่อนโยบาย</label><input class="form-control" name="title" value="<?= e($policy['title'] ?? 'นโยบายคุ้มครองข้อมูลส่วนบุคคล') ?>"></div>
        <div class="col-md-4"><label class="form-label">วันที่มีผล</label><input type="date" class="form-control" name="effective_date" value="<?= e($policy['effective_date'] ?? date('Y-m-d')) ?>"></div>
      </div>
      <label class="form-label">เนื้อหา <span class="text-muted small">(ปัจจุบัน: ฉบับที่ <?= (int)($policy['version'] ?? 0) ?>)</span></label>
      <textarea class="form-control font-monospace small" name="content" rows="22"><?= e($policy['content'] ?? '') ?></textarea>
      <div class="form-text mb-3">รูปแบบ: บรรทัดที่ขึ้นต้นด้วย <code>## </code> = หัวข้อ, <code>- </code> = รายการ · ตัวแปร: <code>{college_name}</code> <code>{site_name}</code> <code>{dpo_name}</code> <code>{dpo_email}</code> <code>{dpo_phone}</code> <code>{log_retention_days}</code></div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" name="mode" value="new" onclick="return confirm('เผยแพร่เป็นฉบับใหม่? ผู้ใช้ทุกคนต้องยอมรับใหม่')"><i class="bi bi-megaphone me-1"></i>เผยแพร่เป็นฉบับใหม่ (ขอความยินยอมใหม่)</button>
        <button class="btn btn-outline-secondary" name="mode" value="edit" onclick="return confirm('แก้ไขถ้อยคำในฉบับปัจจุบันโดยไม่ขอความยินยอมใหม่? ใช้เฉพาะการแก้คำผิดเล็กน้อย')">แก้คำผิดในฉบับปัจจุบัน</button>
        <a class="btn btn-link" href="<?= url('privacy.php') ?>" target="_blank">ดูหน้านโยบาย</a>
      </div>
    </form>
  </div>

<?php elseif ($tab === 'settings'): ?>
  <div class="card shadow-sm" style="max-width:720px">
    <form method="post" class="card-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h6>เจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล (DPO)</h6>
      <div class="row g-2 mb-3">
        <div class="col-md-5"><label class="form-label small">ชื่อ-สกุล / ตำแหน่ง</label><input class="form-control" name="pdpa_dpo_name" value="<?= e(setting('pdpa_dpo_name')) ?>"></div>
        <div class="col-md-4"><label class="form-label small">อีเมล</label><input type="email" class="form-control" name="pdpa_dpo_email" value="<?= e(setting('pdpa_dpo_email')) ?>"></div>
        <div class="col-md-3"><label class="form-label small">โทรศัพท์</label><input class="form-control" name="pdpa_dpo_phone" value="<?= e(setting('pdpa_dpo_phone')) ?>"></div>
      </div>
      <h6>ระยะเวลาเก็บรักษาประวัติการใช้งาน</h6>
      <div class="input-group mb-1" style="max-width:240px"><input type="number" min="30" class="form-control" name="log_retention_days" value="<?= (int)setting('log_retention_days', '365') ?>"><span class="input-group-text">วัน</span></div>
      <div class="form-text mb-3">ประวัติที่เก่ากว่านี้จะถูกลบอัตโนมัติวันละครั้ง (เมื่อเปิดแดชบอร์ดหลังบ้าน) ขั้นต่ำ 30 วัน</div>
      <button class="btn btn-primary">บันทึก</button>
    </form>
  </div>

<?php else:
    $rows = db_all('SELECT c.*, u.fullname, u.username FROM pdpa_consents c JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 300');
    $pendingUsers = db_all("SELECT u.fullname, u.username FROM users u WHERE u.status = 'active'
                            AND NOT COALESCE((SELECT c.action = 'accept' AND c.policy_version >= ? FROM pdpa_consents c WHERE c.user_id = u.id ORDER BY c.id DESC LIMIT 1), 0) ORDER BY u.fullname", 'i', [(int)($policy['version'] ?? 1)]); ?>
  <?php if ($pendingUsers): ?>
    <div class="alert alert-light border small"><strong>ยังไม่ยอมรับฉบับปัจจุบัน (<?= count($pendingUsers) ?>):</strong> <?= e(implode(', ', array_map(fn($p) => $p['fullname'], $pendingUsers))) ?> — จะถูกขอความยินยอมเมื่อเข้าสู่ระบบครั้งถัดไป</div>
  <?php endif; ?>
  <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>ฉบับ</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
    <?php foreach ($rows as $c): ?>
      <tr><td class="small text-nowrap"><?= thai_date($c['created_at'], true, true) ?></td><td class="small"><?= e($c['fullname']) ?></td>
        <td><?= $c['action'] === 'accept' ? '<span class="badge text-bg-success">ยอมรับ</span>' : '<span class="badge text-bg-danger">ถอน</span>' ?></td>
        <td><?= (int)$c['policy_version'] ?></td><td class="small text-muted"><?= e($c['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
