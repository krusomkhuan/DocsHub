<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$fields = [
    'site_name'           => ['ชื่อระบบ', 'text'],
    'site_slogan'         => ['สโลแกน', 'text'],
    'college_name'        => ['ชื่อสถานศึกษา', 'text'],
    'site_url'            => ['URL ของระบบ Docs Hub', 'url'],
    'college_url'         => ['URL เว็บไซต์วิทยาลัย', 'url'],
    'book_prefix'         => ['รหัสหน่วยงาน (ใช้แทน {prefix})', 'text'],
    'book_format'         => ['รูปแบบเลขหนังสือ', 'text'],
    'max_upload_mb'       => ['ขนาดไฟล์อัปโหลดสูงสุด (MB)', 'number'],
    'allow_register'      => ['เปิดรับสมัครสมาชิก', 'bool'],
    'auto_approve_member' => ['อนุมัติสมาชิกใหม่อัตโนมัติ', 'bool'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // ---------- จัดไฟล์ที่อยู่ใน Drive แล้วเข้าโฟลเดอร์ตามประเภท ----------
    if (post('action') === 'drive_organize') {
        try {
            $res = storage_organize_drive();
            flash($res['failed'] ? 'warning' : 'success', 'จัดไฟล์ใน Google Drive เข้าโฟลเดอร์ตามประเภทแล้ว ' . number_format($res['moved']) . ' ไฟล์'
                . ($res['moved'] || $res['failed'] ? '' : ' (ทุกไฟล์อยู่ถูกโฟลเดอร์แล้ว)') . ($res['failed'] ? ' · ไม่สำเร็จ ' . count($res['failed']) . ': ' . implode(' · ', array_slice($res['failed'], 0, 3)) : ''));
        } catch (Throwable $ex) {
            flash('danger', 'จัดโฟลเดอร์ไม่ได้: ' . $ex->getMessage());
        }
        redirect('admin/settings.php#migrate');
    }
    // ---------- ภาพโปรไฟล์เก็บในเครื่องเสมอ: ดึงภาพที่เคยย้ายขึ้น Drive กลับมา ----------
    if (post('action') === 'avatars_local') {
        try {
            $res = storage_pull_avatars_from_drive();
            flash($res['failed'] ? 'warning' : 'success', 'ดึงภาพโปรไฟล์กลับมาเก็บที่ uploads/docs/avatars แล้ว ' . $res['moved'] . ' ภาพ' . ($res['failed'] ? ' · ไม่สำเร็จ ' . count($res['failed']) . ': ' . implode(' · ', array_slice($res['failed'], 0, 3)) : ''));
        } catch (Throwable $ex) {
            flash('danger', $ex->getMessage());
        }
        redirect('admin/settings.php#migrate');
    }
    // ---------- จัดเก็บไฟล์ใน Google Drive ----------
    if (in_array(post('action'), ['drive_save', 'drive_test', 'drive_disconnect'], true)) {
        if (post('action') === 'drive_disconnect') {
            foreach (['gdrive_refresh_token' => '', 'gdrive_access_token' => '', 'gdrive_token_exp' => '0', 'gdrive_account_email' => ''] as $k => $v) gdrive_save_setting($k, $v);
            log_activity('gdrive_disconnect');
            flash('warning', 'ยกเลิกการเชื่อมต่อ Google Drive แล้ว · ระหว่างนี้' . (setting('storage_driver') === 'gdrive' ? 'อัปโหลดไฟล์ไม่ได้ (ระบบเก็บใน Google Drive เท่านั้น)' : 'ไฟล์ใหม่จะเก็บในเครื่อง') . ' และไฟล์ที่อยู่ใน Drive จะดาวน์โหลดไม่ได้จนกว่าจะเชื่อมต่อบัญชีเดิมอีกครั้ง');
            redirect('admin/settings.php#drive');
        }
        $folderId = gdrive_parse_folder_id(post('gdrive_folder_id'));
        if (post('gdrive_folder_id') !== '' && $folderId === '') {
            flash('danger', 'Folder ID ไม่ถูกต้อง — คัดลอกรหัสหลัง /folders/ ในลิงก์ของโฟลเดอร์ หรือวางลิงก์โฟลเดอร์ทั้งลิงก์');
            redirect('admin/settings.php#drive');
        }
        $receiptPath = trim(preg_replace('#/+#', '/', str_replace('\\', '/', post('gdrive_receipt_path', 'uploads/receipt'))), '/ ');
        if ($receiptPath === '' || !preg_match('#^[^<>:"|?*]{1,200}$#u', $receiptPath)) {
            flash('danger', 'เส้นทางโฟลเดอร์ใบเสร็จไม่ถูกต้อง เช่น uploads/receipt');
            redirect('admin/settings.php#drive');
        }
        gdrive_save_setting('gdrive_receipt_path', $receiptPath);
        if (post('action') === 'drive_test') {
            // ทดสอบการเชื่อมต่อทีละขั้น แล้วแสดงผลเป็นรายการในการ์ด Drive
            if ($folderId !== '') gdrive_save_setting('gdrive_folder_id', $folderId);
            $_SESSION['drive_diag'] = gdrive_diagnose($folderId);
            foreach ($_SESSION['drive_diag'] as [, $ok]) if ($ok === false) $failed = true;
            if (empty($failed) && $folderId !== '') {
                foreach ($_SESSION['drive_diag'] as [$label, , $msg]) if ($label === 'โฟลเดอร์ปลายทาง' && preg_match('/"(.+)"/u', $msg, $m)) gdrive_save_setting('gdrive_folder_name', $m[1]);
            }
            log_activity('gdrive_test', empty($failed) ? 'ผ่านทุกขั้น' : 'ไม่ผ่าน');
            redirect('admin/settings.php#drive');
        }
        $enable = post('storage_driver') === 'gdrive';
        $folderName = '';
        if ($enable) {
            try {
                if (!gdrive_connected()) throw new RuntimeException('ยังไม่ได้เชื่อมต่อบัญชี Google Drive');
                if ($folderId === '') throw new RuntimeException('กรุณาระบุ Folder ID');
                gdrive_save_setting('gdrive_folder_id', $folderId);
                $info = gdrive_folder_info($folderId);
                $folderName = $info['name'];
            } catch (Throwable $ex) {
                if ($enable) gdrive_save_setting('storage_driver', 'local');
                flash('danger', 'Google Drive: ' . $ex->getMessage() . ($enable ? ' — จึงยังไม่เปิดใช้ (ไฟล์ใหม่เก็บในเครื่องตามเดิม)' : ''));
                redirect('admin/settings.php#drive');
            }
        }
        gdrive_save_setting('gdrive_folder_id', $folderId);
        if ($folderName !== '') gdrive_save_setting('gdrive_folder_name', $folderName);
        gdrive_save_setting('storage_driver', $enable ? 'gdrive' : 'local');
        log_activity('settings_storage', $enable ? 'เก็บไฟล์ใน Google Drive โฟลเดอร์ ' . $folderId : 'เก็บไฟล์ในเครื่อง');
        flash('success', $enable ? 'เปิดใช้แล้ว: ไฟล์ที่อัปโหลดใหม่จะเก็บในโฟลเดอร์ "' . $folderName . '" ของ Google Drive' : 'บันทึกแล้ว: ไฟล์ที่อัปโหลดใหม่เก็บในเครื่อง (uploads/docs)');
        redirect('admin/settings.php#drive');
    }
    // ---------- เข้าสู่ระบบด้วย Google ----------
    if (post('action') === 'google_test') {
        // ทดสอบค่าที่กรอกอยู่ในฟอร์ม (ยังไม่บันทึก) · Secret เว้นว่าง = ใช้ค่าที่บันทึกไว้
        $secret = trim((string)($_POST['google_client_secret'] ?? '')) ?: setting('google_client_secret');
        [$ok, $msg] = google_check_client(trim(post('google_client_id')), $secret);
        log_activity('google_test', $ok ? 'ผ่าน' : 'ไม่ผ่าน');
        flash($ok ? 'success' : 'danger', 'ทดสอบ Client ID / Secret: ' . $msg . ($ok ? ' · อย่าลืมกด "บันทึกการตั้งค่า Google" ถ้าแก้ไขค่า' : ''));
        redirect('admin/settings.php#google');
    }
    if (post('action') === 'google') {
        $enable = post('google_login_enabled') === '1';
        $clientId = trim(post('google_client_id'));
        $secret = trim((string)($_POST['google_client_secret'] ?? ''));
        $domain = strtolower(trim(post('google_allowed_domain'), " @"));
        if ($secret === '') $secret = setting('google_client_secret'); // เว้นว่าง = ใช้ค่าเดิม
        if (post('clear_secret') === '1') $secret = '';
        $err = null;
        if ($clientId !== '' && !preg_match('/^[\w.-]+\.apps\.googleusercontent\.com$/', $clientId)) $err = 'Client ID ต้องลงท้ายด้วย .apps.googleusercontent.com';
        elseif ($domain !== '' && !preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) $err = 'โดเมนอีเมลไม่ถูกต้อง เช่น pticec.ac.th';
        elseif ($enable && ($clientId === '' || $secret === '')) $err = 'เปิดใช้ไม่ได้: กรุณากรอก Client ID และ Client Secret ก่อน';
        if ($err) {
            flash('danger', $err);
            redirect('admin/settings.php#google');
        }
        foreach (['google_login_enabled' => $enable ? '1' : '0', 'google_client_id' => $clientId, 'google_client_secret' => $secret,
                  'google_allowed_domain' => $domain, 'google_allow_register' => post('google_allow_register') === '1' ? '1' : '0'] as $k => $v) {
            db_exec('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', [$k, $v]);
        }
        log_activity('settings_google', $enable ? 'เปิดเข้าสู่ระบบด้วย Google' : 'ปิดเข้าสู่ระบบด้วย Google');
        flash('success', 'บันทึกการตั้งค่าเข้าสู่ระบบด้วย Google แล้ว (' . ($enable ? 'เปิดใช้งาน' : 'ปิดใช้งาน') . ')');
        redirect('admin/settings.php#google');
    }
    foreach (['site_url' => 'URL ของระบบ', 'college_url' => 'URL เว็บไซต์วิทยาลัย'] as $k => $lbl) {
        $u = post($k);
        if ($u !== '' && (!filter_var($u, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $u))) {
            flash('danger', $lbl . ' ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://www.example.ac.th');
            redirect('admin/settings.php');
        }
        $_POST[$k] = rtrim($u, '/');
    }
    foreach ($fields as $key => [$label, $type]) {
        $val = $type === 'bool' ? (post($key) === '1' ? '1' : '0') : post($key);
        if ($type === 'number') $val = (string)max(1, (int)$val);
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', [$key, $val]);
    }
    log_activity('settings_update');
    flash('success', 'บันทึกการตั้งค่าแล้ว');
    redirect('admin/settings.php');
}

$pageTitle = 'ตั้งค่าระบบ';
$activeMenu = 'settings';
require __DIR__ . '/../includes/admin_header.php';
$phpMax = ini_get('upload_max_filesize') . ' / post_max_size ' . ini_get('post_max_size');
?>
<div class="card shadow-sm" style="max-width:760px">
  <form method="post" class="card-body">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => [$label, $type]): ?>
      <?php if ($type === 'bool'): ?>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" id="f_<?= $key ?>"<?= setting($key) === '1' ? ' checked' : '' ?>>
          <label class="form-check-label" for="f_<?= $key ?>"><?= e($label) ?></label>
        </div>
      <?php else: ?>
        <div class="mb-3">
          <label class="form-label"><?= e($label) ?></label>
          <input type="<?= $type ?>" class="form-control"<?= $type === 'url' ? ' placeholder="https://"' : '' ?> name="<?= $key ?>" value="<?= e(setting($key)) ?>">
          <?php if ($key === 'site_url'): ?>
            <div class="form-text">ใช้สร้างลิงก์เต็มสำหรับคัดลอก/แชร์เอกสาร และพิมพ์ท้ายรายงาน เว้นว่าง = ใช้ที่อยู่ที่เปิดอยู่ (ตอนนี้: <code><?= e(detected_base_url()) ?></code>)
              <button type="button" class="btn btn-link btn-sm p-0 align-baseline" onclick="document.querySelector('[name=site_url]').value='<?= e(detected_base_url()) ?>'">ใช้ค่านี้</button></div>
          <?php elseif ($key === 'college_url'): ?>
            <div class="form-text">แสดงเป็นลิงก์ชื่อวิทยาลัยที่ท้ายเว็บ</div>
          <?php elseif ($key === 'book_format'): ?>
            <div class="form-text">ตัวแปร: <code>{prefix}</code> รหัสหน่วยงาน, <code>{code}</code> รหัสแผนก, <code>{no}</code> เลขรัน 3 หลัก, <code>{year}</code> ปี พ.ศ. — เช่น <code>{code} {no}/{year}</code> → ชฟ 001/<?= current_year_be() ?> หรือ <code>{prefix}({code})/{no}</code></div>
          <?php elseif ($key === 'max_upload_mb'): ?>
            <div class="form-text">ค่าจาก php.ini ปัจจุบัน: upload_max_filesize <?= e($phpMax) ?> (ต้องตั้งให้ไม่น้อยกว่าค่านี้)</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn btn-primary">บันทึกการตั้งค่า</button>
  </form>
</div>

<?php $gOn = setting('google_login_enabled') === '1'; $hasSecret = setting('google_client_secret') !== ''; ?>
<div class="card shadow-sm mt-4" style="max-width:760px" id="google">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><i class="bi bi-google me-1 text-danger"></i>เข้าสู่ระบบด้วย Google</span>
    <span class="badge text-bg-<?= google_login_available() ? 'success' : 'secondary' ?>"><?= google_login_available() ? 'เปิดใช้งานอยู่' : 'ปิดอยู่' ?></span>
  </div>
  <form method="post" class="card-body" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="google">
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="google_login_enabled" value="1" id="gOn"<?= $gOn ? ' checked' : '' ?>>
      <label class="form-check-label fw-medium" for="gOn">เปิดให้สมาชิกเข้าสู่ระบบด้วย Google</label>
    </div>
    <div class="mb-3">
      <label class="form-label">Client ID</label>
      <input class="form-control font-monospace small" name="google_client_id" value="<?= e(setting('google_client_id')) ?>" placeholder="xxxxxxxx.apps.googleusercontent.com">
    </div>
    <div class="mb-3">
      <label class="form-label">Client Secret</label>
      <input type="password" class="form-control font-monospace small" name="google_client_secret" placeholder="<?= $hasSecret ? '•••••••• (บันทึกไว้แล้ว เว้นว่างเพื่อใช้ค่าเดิม)' : 'GOCSPX-...' ?>" autocomplete="new-password">
      <?php if ($hasSecret): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_secret" value="1" id="clrSec"><label class="form-check-label small" for="clrSec">ลบ Client Secret ที่บันทึกไว้</label></div><?php endif; ?>
    </div>
    <div class="mb-3">
      <label class="form-label">จำกัดเฉพาะอีเมลโดเมน (ไม่บังคับ)</label>
      <div class="input-group"><span class="input-group-text">@</span><input class="form-control" name="google_allowed_domain" value="<?= e(setting('google_allowed_domain')) ?>" placeholder="เช่น pticec.ac.th"></div>
      <div class="form-text">ตั้งค่าแล้วจะรับเฉพาะบัญชี Google Workspace ของวิทยาลัย (เว้นว่าง = รับทุกบัญชี Gmail)</div>
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="google_allow_register" value="1" id="gReg"<?= setting('google_allow_register', '1') === '1' ? ' checked' : '' ?>>
      <label class="form-check-label" for="gReg">ให้ผู้ที่ยังไม่มีบัญชีสมัครสมาชิกด้วย Google ได้ <span class="text-muted small">(ต้องเลือกแผนกและยอมรับนโยบาย · อนุมัติตามการตั้งค่า "อนุมัติสมาชิกใหม่อัตโนมัติ")</span></label>
    </div>
    <div class="alert alert-light border small">
      <strong>วิธีขอ Client ID / Secret</strong>
      <ol class="mb-2 ps-3">
        <li>เปิด <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console → APIs &amp; Services → Credentials</a> (สร้างโปรเจกต์ก่อนถ้ายังไม่มี)</li>
        <li>ตั้งค่า <em>OAuth consent screen</em> (ประเภท Internal ถ้าใช้ Google Workspace ของวิทยาลัย หรือ External)</li>
        <li>กด <em>Create credentials → OAuth client ID</em> เลือกประเภท <strong>Web application</strong></li>
        <li>ช่อง <em>Authorized redirect URIs</em> ใส่ค่านี้ แล้วนำ Client ID / Secret มากรอกด้านบน</li>
      </ol>
      <div class="input-group input-group-sm">
        <input class="form-control font-monospace" value="<?= e(google_redirect_uri()) ?>" readonly>
        <button class="btn btn-outline-secondary" type="button" data-copy="<?= e(google_redirect_uri()) ?>"><i class="bi bi-clipboard"></i> คัดลอก</button>
      </div>
      <div class="mt-1 text-muted">Redirect URI สร้างจาก "URL ของระบบ" ด้านบน · Google รับเฉพาะ <code>https://</code> (ยกเว้น <code>http://localhost</code> สำหรับทดสอบ)</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-primary">บันทึกการตั้งค่า Google</button>
      <button class="btn btn-outline-primary" name="action" value="google_test"><i class="bi bi-plug me-1"></i>ทดสอบ Client ID / Secret</button>
    </div>
  </form>
</div>

<?php
$driveOn = storage_drive_enabled();
$fileCounts = db_row("SELECT
    (SELECT COUNT(*) FROM documents WHERE file_name LIKE 'gdrive:%') + (SELECT COUNT(*) FROM trip_report_images WHERE file_name LIKE 'gdrive:%')
  + (SELECT COUNT(*) FROM trip_reports WHERE file_name LIKE 'gdrive:%') + (SELECT COUNT(*) FROM ledger_entries WHERE file_name LIKE 'gdrive:%')
  + (SELECT COUNT(*) FROM users WHERE avatar LIKE 'gdrive:%') AS drive,
    (SELECT COUNT(*) FROM documents WHERE file_name NOT LIKE 'gdrive:%') + (SELECT COUNT(*) FROM trip_report_images WHERE file_name NOT LIKE 'gdrive:%')
  + (SELECT COUNT(*) FROM trip_reports WHERE file_name IS NOT NULL AND file_name NOT LIKE 'gdrive:%') + (SELECT COUNT(*) FROM ledger_entries WHERE file_name IS NOT NULL AND file_name NOT LIKE 'gdrive:%')
  + (SELECT COUNT(*) FROM users WHERE avatar IS NOT NULL AND avatar NOT LIKE 'gdrive:%') AS local");
?>
<div class="card shadow-sm mt-4" style="max-width:760px" id="drive">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><i class="bi bi-google me-1 text-success"></i>จัดเก็บไฟล์ใน Google Drive</span>
    <span class="badge text-bg-<?= $driveOn ? 'success' : 'secondary' ?>"><?= $driveOn ? 'เก็บใน Google Drive เท่านั้น' : (setting('storage_driver') === 'gdrive' ? 'เปิดอยู่ แต่ยังเชื่อมต่อไม่ครบ — อัปโหลดไม่ได้' : 'ไฟล์ใหม่เก็บในเครื่อง (uploads/docs)') ?></span>
  </div>
  <div class="card-body">
    <div class="small text-muted mb-3">
      ไฟล์ที่อัปโหลดเข้าระบบจะถูกส่งไปเก็บในโฟลเดอร์ย่อยตามประเภท ภายในโฟลเดอร์ตาม Folder ID — ระบบสร้างโฟลเดอร์ที่ยังไม่มีให้เอง และใช้โฟลเดอร์เดิมเสมอ ไม่สร้างซ้ำ
      (ถ้า Folder ID เป็นโฟลเดอร์ <code>uploads</code> เอง ระบบเก็บในโฟลเดอร์ย่อยของ uploads โดยตรง ไม่สร้าง uploads ซ้อน):
      <ul class="mb-1 mt-1"><li>คำสั่งราชการ → <code><?= e(storage_display_path('doc:orders')) ?></code></li><li>แผนการสอน / โครงการสอน → <code><?= e(storage_display_path('doc:lesson-plans')) ?></code></li>
      <li>หนังสือเรียน / ตำรา → <code><?= e(storage_display_path('doc:textbooks')) ?></code></li><li>เกียรติบัตร → <code><?= e(storage_display_path('doc:certificates')) ?></code></li>
      <li>ใบเสร็จ (หลักฐานบัญชีแผนก) → <code><?= e(storage_display_path('receipt')) ?></code></li>
      <li>ภาพประกอบและไฟล์แนบรายงานไปราชการ → <code><?= e(storage_display_path('trip-reports')) ?></code></li>
      <li>อื่น ๆ ทั้งหมด (ประเภทเอกสารอื่น ๆ) → <code><?= e(storage_display_path('')) ?></code></li>
      <li>ไฟล์สำรองข้อมูล (.zip) → <code><?= e(storage_display_path('backups')) ?></code> (เก็บสำเนาในเซิร์ฟเวอร์ด้วยเพื่อใช้กู้คืน)</li></ul>
      <strong>ภาพโปรไฟล์เก็บในเครื่องที่ uploads/docs/avatars เสมอ</strong>
      · ไฟล์ใน Drive เป็นส่วนตัว ผู้ใช้ดาวน์โหลดผ่านระบบซึ่งตรวจสิทธิ์ทุกครั้ง · ไฟล์เดิมใช้งานได้ตามปกติ
      <div class="mt-1">ตอนนี้: <strong><?= number_format((int)$fileCounts['drive']) ?></strong> ไฟล์อยู่ใน Google Drive · <strong><?= number_format((int)$fileCounts['local']) ?></strong> ไฟล์อยู่ในเครื่อง</div>
    </div>

    <h6 class="mb-2">1) บัญชี Google ที่ใช้เก็บไฟล์</h6>
    <?php if (gdrive_connected()): ?>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="badge text-bg-success"><i class="bi bi-link-45deg"></i> เชื่อมต่อแล้ว</span>
        <span><?= e(setting('gdrive_account_email')) ?></span>
        <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url('gdrive-connect.php') ?>">เชื่อมต่อใหม่ / เปลี่ยนบัญชี</a>
        <form method="post" data-confirm="ยกเลิกการเชื่อมต่อ Google Drive? ไฟล์ใหม่จะเก็บในเครื่อง และไฟล์ที่อยู่ใน Drive จะดาวน์โหลดไม่ได้จนกว่าจะเชื่อมต่อบัญชีเดิมอีกครั้ง">
          <?= csrf_field() ?><input type="hidden" name="action" value="drive_disconnect"><button class="btn btn-sm btn-outline-danger">ยกเลิกการเชื่อมต่อ</button></form>
      </div>
    <?php elseif (setting('google_client_id') === '' || !$hasSecret): ?>
      <div class="alert alert-warning small">กรอก <strong>Client ID</strong> และ <strong>Client Secret</strong> ในการ์ด "เข้าสู่ระบบด้วย Google" ด้านบนก่อน (ไม่จำเป็นต้องเปิดใช้การเข้าสู่ระบบด้วย Google)</div>
    <?php else: ?>
      <a class="btn btn-outline-dark btn-sm mb-3" href="<?= url('gdrive-connect.php') ?>"><i class="bi bi-google me-1"></i>เชื่อมต่อ Google Drive</a>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <h6 class="mb-2">2) โฟลเดอร์ปลายทาง</h6>
      <label class="form-label">Folder ID</label>
      <input class="form-control font-monospace small" name="gdrive_folder_id" value="<?= e(setting('gdrive_folder_id')) ?>" placeholder="เช่น 1AbCdEfGhIjKlMnOpQrStUvWxYz012345 หรือวางลิงก์โฟลเดอร์ทั้งลิงก์">
      <div class="form-text mb-3">
        เปิดโฟลเดอร์ใน Google Drive แล้วคัดลอกรหัสจากแถบที่อยู่: drive.google.com/drive/folders/<strong class="text-danger">รหัสนี้</strong>
        <?php if (setting('gdrive_folder_name') !== '' && setting('gdrive_folder_id') !== ''): ?> · โฟลเดอร์ที่ตรวจล่าสุด: <strong><?= e(setting('gdrive_folder_name')) ?></strong>
          <a href="https://drive.google.com/drive/folders/<?= e(rawurlencode(setting('gdrive_folder_id'))) ?>" target="_blank" rel="noopener">เปิดใน Drive</a><?php endif; ?>
      </div>
      <label class="form-label">โฟลเดอร์สำหรับใบเสร็จ (หลักฐานบัญชีแผนก)</label>
      <div class="input-group mb-1"><span class="input-group-text small"><?= e(setting('gdrive_folder_name') ?: 'DocsHub') ?> /</span>
        <input class="form-control font-monospace small" name="gdrive_receipt_path" value="<?= e(setting('gdrive_receipt_path', 'uploads/receipt')) ?>"></div>
      <div class="form-text mb-3">ใบเสร็จเก็บในโฟลเดอร์ย่อยนี้ ระบบสร้างให้อัตโนมัติถ้ายังไม่มี · ไฟล์อื่นเก็บตามรายการด้านบน</div>
      <h6 class="mb-2">3) เปิดใช้งาน</h6>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="storage_driver" value="gdrive" id="stDrive"<?= setting('storage_driver') === 'gdrive' ? ' checked' : '' ?><?= gdrive_connected() ? '' : ' disabled' ?>>
        <label class="form-check-label fw-medium" for="stDrive">เก็บไฟล์ที่อัปโหลดใน Google Drive เท่านั้น (ไม่เก็บในเครื่อง)</label>
        <div class="form-text">เปิด = ทุกไฟล์ส่งขึ้น Drive ทันที ถ้าส่งไม่สำเร็จผู้ใช้จะเห็นข้อความให้ลองใหม่ และไม่มีไฟล์ค้างในเครื่อง · ปิด = เก็บใน uploads/docs บนเซิร์ฟเวอร์ (ระบบตรวจการเข้าถึงโฟลเดอร์ก่อนเปิดใช้)</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" name="action" value="drive_save">บันทึกการตั้งค่า Drive</button>
        <button class="btn btn-outline-primary" name="action" value="drive_test"><i class="bi bi-plug me-1"></i>ทดสอบการเชื่อมต่อ</button>
      </div>
    </form>
    <?php if ($diag = $_SESSION['drive_diag'] ?? null): unset($_SESSION['drive_diag']); $allOk = !in_array(false, array_column($diag, 1), true); ?>
      <div class="alert alert-<?= $allOk ? 'success' : 'danger' ?> small mt-3 mb-0">
        <strong><i class="bi <?= $allOk ? 'bi-check-circle' : 'bi-x-octagon' ?> me-1"></i>ผลทดสอบการเชื่อมต่อ: <?= $allOk ? 'ผ่านทุกขั้น พร้อมใช้งาน' : 'ไม่ผ่าน — แก้ไขตามข้อที่มีเครื่องหมาย ✗ แล้วกดทดสอบอีกครั้ง' ?></strong>
        <ol class="mb-0 mt-2 ps-3">
          <?php foreach ($diag as [$label, $ok, $msg]): ?>
            <li><?= $ok ? '<span class="text-success">✓</span>' : '<span class="text-danger fw-bold">✗</span>' ?> <strong><?= e($label) ?></strong> — <?= e($msg) ?></li>
          <?php endforeach; ?>
        </ol>
        <?php if (!$allOk && preg_match('/unauthorized_client|invalid_grant|ยังไม่ได้เชื่อมต่อ/u', implode(' ', array_column($diag, 2)))): ?>
          <a class="btn btn-sm btn-dark mt-2" href="<?= url('gdrive-connect.php') ?>"><i class="bi bi-google me-1"></i>เชื่อมต่อ Google Drive ใหม่</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <hr id="migrate">
    <?php $inDrive = (int)db_val("SELECT (SELECT COUNT(*) FROM documents WHERE file_name LIKE 'gdrive:%') + (SELECT COUNT(*) FROM trip_reports WHERE file_name LIKE 'gdrive:%') + (SELECT COUNT(*) FROM trip_report_images WHERE file_name LIKE 'gdrive:%') + (SELECT COUNT(*) FROM ledger_entries WHERE file_name LIKE 'gdrive:%')"); ?>
    <form method="post" class="mt-2" data-confirm="จัดไฟล์ที่อยู่ใน Google Drive <?= $inDrive ?> ไฟล์ เข้าโฟลเดอร์ตามประเภท (commands / plans / books / certs / receipt / trips/reports / docs)?">
      <?= csrf_field() ?><input type="hidden" name="action" value="drive_organize">
      <button class="btn btn-sm btn-outline-primary"<?= gdrive_connected() && setting('gdrive_folder_id') !== '' && $inDrive ? '' : ' disabled' ?>><i class="bi bi-folder-symlink me-1"></i>จัดไฟล์ใน Drive เข้าโฟลเดอร์ตามประเภท (<?= number_format($inDrive) ?> ไฟล์)</button>
      <span class="small text-muted ms-1">ย้ายไฟล์ที่อยู่ใน docs หรือโฟลเดอร์หลักไปยังโฟลเดอร์ที่ถูกต้อง</span>
    </form>
    <?php $avatarsInDrive = (int)db_val("SELECT COUNT(*) FROM users WHERE avatar LIKE 'gdrive:%'"); ?>
    <?php if ($avatarsInDrive): ?>
      <form method="post" class="mt-2" data-confirm="ดึงภาพโปรไฟล์ <?= $avatarsInDrive ?> ภาพจาก Google Drive กลับมาเก็บที่ uploads/docs/avatars?">
        <?= csrf_field() ?><input type="hidden" name="action" value="avatars_local">
        <button class="btn btn-sm btn-outline-secondary"<?= gdrive_connected() ? '' : ' disabled' ?>><i class="bi bi-person-down me-1"></i>ดึงภาพโปรไฟล์ <?= $avatarsInDrive ?> ภาพกลับมาเก็บในเครื่อง</button>
        <span class="small text-muted ms-1">(ภาพโปรไฟล์ต้องเก็บที่ uploads/docs/avatars)</span>
      </form>
    <?php endif; ?>

    <div class="alert alert-light border small mt-3 mb-0">
      <strong>ข้อควรรู้</strong>
      <ul class="mb-0 ps-3">
        <li>ต้องเปิด <strong>Google Drive API</strong> ในโปรเจกต์เดียวกับ Client ID (Google Cloud Console → APIs &amp; Services → Library)</li>
        <li>ใช้ Redirect URI เดิมของการเข้าสู่ระบบด้วย Google ไม่ต้องเพิ่มใหม่</li>
        <li>OAuth consent screen แบบ <strong>Internal</strong> (บัญชี @วิทยาลัย) ใช้ได้ต่อเนื่อง · ถ้าเป็น External สถานะ Testing สิทธิ์จะหมดอายุทุก 7 วัน ต้องกดเชื่อมต่อใหม่</li>
        <li>ไฟล์ที่ลบในระบบจะถูกย้ายไป <strong>ถังขยะ</strong> ของ Drive (กู้คืนได้ใน 30 วัน) · อย่าลบหรือย้ายไฟล์ในโฟลเดอร์นี้ด้วยตนเอง</li>
        <li>ปุ่ม "สำรองแบบเต็ม" ในหลังบ้านรวมเฉพาะไฟล์ที่อยู่ในเครื่อง ไฟล์ใน Drive ให้สำรองด้วย Google Drive / Google Takeout</li>
      </ul>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
