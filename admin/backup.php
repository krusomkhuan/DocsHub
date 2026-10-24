<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/backup.php';
require_admin();

// ดาวน์โหลดไฟล์สำรอง
if (get('download') !== '') {
    try {
        $path = backup_path(get('download'));
    } catch (RuntimeException $ex) {
        $path = '';
    }
    if (!$path || !is_file($path)) {
        http_response_code(404);
        exit('ไม่พบไฟล์สำรอง');
    }
    log_activity('backup_download', get('download'));
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'create') {
            $name = backup_create(post('type') === 'full');
            $pruned = backup_prune((int)setting('backup_keep', '10'));
            log_activity('backup_create', $name);
            flash('success', 'สร้างไฟล์สำรอง ' . $name . ' เรียบร้อยแล้ว' . ($pruned ? " (ลบไฟล์เก่า $pruned ไฟล์)" : ''));
            if ($dr = $GLOBALS['BACKUP_DRIVE_RESULT'] ?? null) flash($dr['ok'] ? 'info' : 'warning', $dr['msg']);
        } elseif ($action === 'delete') {
            $path = backup_path(post('name'));
            if (is_file($path)) unlink($path);
            backup_drive_forget(post('name'), true); // ลบสำเนาใน Google Drive ด้วย (ย้ายไปถังขยะ)
            log_activity('backup_delete', post('name'));
            flash('success', 'ลบไฟล์สำรองแล้ว');
        } elseif ($action === 'settings') {
            foreach (['auto_backup_days' => 0, 'backup_keep' => 1] as $k => $min) {
                db_exec('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', 'ss', [$k, (string)max($min, (int)post($k))]);
            }
            db_exec("INSERT INTO settings (skey, svalue) VALUES ('auto_backup_full', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", 's', [post('auto_backup_full') === '1' ? '1' : '0']);
            flash('success', 'บันทึกการตั้งค่าการสำรองข้อมูลแล้ว');
        } elseif ($action === 'restore') {
            if (!password_verify((string)($_POST['password'] ?? ''), current_user()['password_hash'])) {
                throw new RuntimeException('รหัสผ่านไม่ถูกต้อง ยกเลิกการกู้คืน');
            }
            $restoreDb = post('restore_db') === '1';
            $restoreFiles = post('restore_files') === '1';
            if (!$restoreDb && !$restoreFiles) {
                throw new RuntimeException('กรุณาเลือกสิ่งที่ต้องการกู้คืนอย่างน้อย 1 อย่าง');
            }
            // ใช้ไฟล์ที่อัปโหลดมา หรือไฟล์ที่มีอยู่ในเซิร์ฟเวอร์
            if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                $m = backup_read_manifest($_FILES['backup_file']['tmp_name']);
                if (!$m) throw new RuntimeException('ไฟล์ที่อัปโหลดไม่ใช่ไฟล์สำรองของ Docs Hub');
                backup_ensure_dir();
                $name = 'docshub-backup-' . date('Ymd-His') . '-' . ($m['type'] === 'full' ? 'full' : 'db') . '-uploaded.zip';
                move_uploaded_file($_FILES['backup_file']['tmp_name'], BACKUP_DIR . '/' . $name);
            } elseif (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('อัปโหลดไฟล์สำรองไม่สำเร็จ (ไฟล์อาจใหญ่เกิน ' . ini_get('upload_max_filesize') . ') ให้คัดลอกไฟล์ไปไว้ในโฟลเดอร์ backups แล้วเลือกจากรายการแทน');
            } else {
                $name = post('name');
            }
            $path = backup_path($name);
            if (!is_file($path)) throw new RuntimeException('ไม่พบไฟล์สำรอง');

            $actor = current_user()['username'];
            $r = backup_restore($path, $restoreDb, $restoreFiles);
            $msg = 'กู้คืนจาก ' . $name . ' สำเร็จ'
                 . ($restoreDb ? ' · ฐานข้อมูล ' . number_format($r['statements']) . ' คำสั่ง' : '')
                 . ($restoreFiles ? ' · ไฟล์เอกสาร ' . number_format($r['files']) . ' ไฟล์' : '')
                 . ' · สำรองข้อมูลก่อนกู้คืนไว้ที่ ' . $r['safety'];
            if ($restoreDb) {
                // ข้อมูลสมาชิกอาจเปลี่ยน ให้เข้าสู่ระบบใหม่
                $_SESSION = [];
                session_regenerate_id(true);
                db_exec("INSERT INTO activity_logs (action, detail, ip) VALUES ('backup_restore', ?, ?)", 'ss', [mb_substr($actor . ': ' . $name, 0, 255), client_ip()]);
                flash('success', $msg . ' — กรุณาเข้าสู่ระบบอีกครั้ง');
                redirect('login.php');
            }
            log_activity('backup_restore', $name . ' (files)');
            flash('success', $msg);
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('admin/backup.php');
}

$backups = backup_list();
$totalSize = array_sum(array_column($backups, 'size'));
$uploadsSize = 0;
$uploadsCount = 0;
if (is_dir(UPLOAD_DIR)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile()) { $uploadsSize += $f->getSize(); $uploadsCount++; }
    }
}
$freeSpace = @disk_free_space(APP_ROOT);
$latest = $backups[0]['time'] ?? null;

$pageTitle = 'สำรองและกู้คืนข้อมูล';
$activeMenu = 'backup';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if (!$latest || time() - $latest > 7 * 86400): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= $latest ? 'ไม่ได้สำรองข้อมูลมาแล้ว ' . floor((time() - $latest) / 86400) . ' วัน' : 'ยังไม่เคยสำรองข้อมูล' ?> แนะนำให้สำรองแบบเต็มและดาวน์โหลดเก็บไว้นอกเครื่องเซิร์ฟเวอร์</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-4">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-cloud-arrow-down me-1"></i>สร้างไฟล์สำรอง</div>
      <div class="card-body">
        <ul class="small text-muted ps-3">
          <li>ฐานข้อมูล: เอกสาร สมาชิก เลขหนังสือ หมวดหมู่ ตั้งค่า</li>
          <li>ไฟล์เอกสาร: <?= number_format($uploadsCount) ?> ไฟล์ · <?= format_size($uploadsSize) ?></li>
          <?php if (storage_drive_enabled() || setting('gdrive_refresh_token') !== ''): ?>
            <li class="text-warning-emphasis">ไฟล์ที่เก็บใน Google Drive ไม่รวมในไฟล์สำรอง (สำรองด้วย Google Drive / Google Takeout) · ไฟล์สำรองมีค่าการเชื่อมต่อ Drive จึงควรเก็บไว้ในที่ปลอดภัย</li>
          <?php endif; ?>
          <li>พื้นที่ว่างบนดิสก์: <?= $freeSpace ? format_size((int)$freeSpace) : '-' ?></li>
        </ul>
        <form method="post" class="d-grid gap-2" data-confirm="เริ่มสร้างไฟล์สำรอง? (อาจใช้เวลาสักครู่หากมีไฟล์จำนวนมาก)">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <button class="btn btn-primary" name="type" value="full"><i class="bi bi-archive me-1"></i>สำรองแบบเต็ม (ฐานข้อมูล + ไฟล์เอกสาร)</button>
          <button class="btn btn-outline-primary" name="type" value="db"><i class="bi bi-database me-1"></i>สำรองเฉพาะฐานข้อมูล</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-gear me-1"></i>สำรองอัตโนมัติ</div>
      <form method="post" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="settings">
        <div class="mb-2">
          <label class="form-label small">สำรองอัตโนมัติทุก (วัน) · 0 = ปิด</label>
          <input type="number" min="0" class="form-control" name="auto_backup_days" value="<?= (int)setting('auto_backup_days', '0') ?>">
          <div class="form-text">ทำงานเมื่อผู้ดูแล/เจ้าหน้าที่เปิดหน้าแดชบอร์ดหลังบ้าน หรือตั้ง Task Scheduler ตามคำแนะนำด้านล่าง</div>
        </div>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" name="auto_backup_full" value="1" id="abf"<?= setting('auto_backup_full', '0') === '1' ? ' checked' : '' ?>>
          <label class="form-check-label small" for="abf">สำรองอัตโนมัติแบบเต็ม (รวมไฟล์เอกสาร)</label>
        </div>
        <div class="mb-3">
          <label class="form-label small">เก็บไฟล์สำรองล่าสุดไว้ (ไฟล์)</label>
          <input type="number" min="1" class="form-control" name="backup_keep" value="<?= (int)setting('backup_keep', '10') ?>">
        </div>
        <button class="btn btn-outline-secondary btn-sm">บันทึก</button>
      </form>
    </div>

    <div class="card shadow-sm border-danger">
      <div class="card-header bg-danger-subtle fw-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i>กู้คืนจากไฟล์ที่อยู่ในเครื่องอื่น</div>
      <form method="post" enctype="multipart/form-data" class="card-body" data-confirm="ข้อมูลปัจจุบันจะถูกแทนที่ด้วยข้อมูลในไฟล์สำรอง ยืนยันการกู้คืน?">
        <?= csrf_field() ?><input type="hidden" name="action" value="restore">
        <input type="file" class="form-control form-control-sm mb-2" name="backup_file" accept=".zip" required>
        <div class="form-text mb-2">ไฟล์ .zip ที่ดาวน์โหลดไว้ ไม่เกิน <?= e(ini_get('upload_max_filesize')) ?> · ไฟล์ใหญ่กว่านี้ให้คัดลอกไปไว้ที่โฟลเดอร์ <code>backups</code> แล้วกู้คืนจากรายการ</div>
        <?php include __DIR__ . '/../includes/backup_restore_fields.php'; ?>
      </form>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between">
        <span class="fw-semibold"><i class="bi bi-hdd-stack me-1"></i>ไฟล์สำรองในเซิร์ฟเวอร์ (<?= count($backups) ?>)
          <?php $driveIdx = backup_drive_index(); ?>
          <?php if (backup_drive_available()): ?><span class="small fw-normal text-success ms-2"><i class="bi bi-google"></i> ส่งสำเนาไปที่ Google Drive: <?= e(storage_display_path('backups')) ?> อัตโนมัติ</span>
          <?php else: ?><span class="small fw-normal text-muted ms-2">ยังไม่ได้เชื่อมต่อ Google Drive (ไฟล์สำรองอยู่ในเซิร์ฟเวอร์เท่านั้น)</span><?php endif; ?></span>
        <span class="small text-muted">รวม <?= format_size((int)$totalSize) ?></span>
      </div>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>วันที่สำรอง</th><th>ประเภท</th><th class="text-end">ขนาด</th><th>รายละเอียด</th><th class="text-end">จัดการ</th></tr></thead>
        <tbody>
        <?php if (!$backups): ?><tr><td colspan="5" class="text-center text-muted py-5">ยังไม่มีไฟล์สำรอง</td></tr><?php endif; ?>
        <?php foreach ($backups as $i => $b): $m = $b['manifest']; ?>
          <tr>
            <td class="text-nowrap"><?= thai_date(date('Y-m-d H:i:s', $b['time']), true, true) ?><div class="small text-muted"><?= e($b['name']) ?></div></td>
            <td>
              <?= str_contains($b['name'], '-full') ? '<span class="badge text-bg-primary">เต็ม</span>' : '<span class="badge text-bg-secondary">ฐานข้อมูล</span>' ?>
              <?= isset($driveIdx[$b['name']]) ? '<span class="badge text-bg-success" title="มีสำเนาใน Google Drive: ' . e(storage_display_path('backups')) . '"><i class="bi bi-google"></i> Drive</span>' : '' ?>
              <?php foreach (['auto' => 'อัตโนมัติ', 'prerestore' => 'ก่อนกู้คืน', 'cli' => 'ตั้งเวลา', 'uploaded' => 'อัปโหลด'] as $tag => $lbl): ?>
                <?= str_contains($b['name'], '-' . $tag) ? '<span class="badge text-bg-light">' . $lbl . '</span>' : '' ?>
              <?php endforeach; ?>
            </td>
            <td class="text-end text-nowrap small"><?= format_size((int)$b['size']) ?></td>
            <td class="small">
              <?php if ($m): ?>
                v<?= e($m['version']) ?> · เอกสาร <?= number_format($m['tables']['documents'] ?? 0) ?> · สมาชิก <?= number_format($m['tables']['users'] ?? 0) ?>
                · เลขหนังสือ <?= number_format($m['tables']['book_numbers'] ?? 0) ?><?= $m['type'] === 'full' ? ' · ไฟล์ ' . number_format($m['files']) : '' ?>
                <div class="text-muted">โดย <?= e($m['created_by']) ?></div>
              <?php else: ?>
                <span class="text-danger">ไฟล์เสียหายหรือไม่ใช่ไฟล์สำรอง</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-success" href="?download=<?= urlencode($b['name']) ?>" title="ดาวน์โหลด"><i class="bi bi-download"></i></a>
              <?php if ($m): ?>
                <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#rs<?= $i ?>" title="กู้คืน"><i class="bi bi-arrow-counterclockwise"></i></button>
              <?php endif; ?>
              <form method="post" class="d-inline" data-confirm="ลบไฟล์สำรอง <?= e($b['name']) ?>?<?= isset($driveIdx[$b['name']]) ? ' (สำเนาใน Google Drive จะถูกย้ายไปถังขยะด้วย)' : '' ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($b['name']) ?>">
                <button class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php if ($m): ?>
          <tr class="collapse" id="rs<?= $i ?>"><td colspan="5" class="bg-danger-subtle">
            <form method="post" class="row g-2 align-items-end" data-confirm="ข้อมูลปัจจุบันจะถูกแทนที่ด้วยข้อมูลจาก <?= e($b['name']) ?> ยืนยันการกู้คืน?">
              <?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="name" value="<?= e($b['name']) ?>">
              <?php $hasFiles = $m['type'] === 'full'; include __DIR__ . '/../includes/backup_restore_fields.php'; ?>
            </form>
          </td></tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <div class="card shadow-sm mt-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-life-preserver me-1"></i>แนวทางเมื่อเว็บมีปัญหา</div>
      <div class="card-body small">
        <ol class="mb-3">
          <li><strong>เข้าหลังบ้านได้:</strong> เลือกไฟล์สำรองล่าสุดที่ข้อมูลยังถูกต้อง แล้วกดปุ่ม <i class="bi bi-arrow-counterclockwise"></i> กู้คืน ระบบจะสำรองสถานะปัจจุบัน (ก่อนกู้คืน) ไว้ให้อัตโนมัติ</li>
          <li><strong>ติดตั้งระบบใหม่/ย้ายเครื่อง:</strong> คัดลอกโฟลเดอร์ระบบ → เปิด <code>install.php</code> ติดตั้ง → เข้าหลังบ้าน → กู้คืนจากไฟล์ .zip ที่ดาวน์โหลดเก็บไว้</li>
          <li><strong>เข้าเว็บไม่ได้เลย:</strong> ใช้คำสั่งบรรทัดคำสั่งด้านล่างกู้คืนได้โดยไม่ต้องผ่านหน้าเว็บ</li>
        </ol>
        <div class="mb-1">สำรองข้อมูลจากบรรทัดคำสั่ง / ตั้งเวลาด้วย Windows Task Scheduler:</div>
        <pre class="bg-light p-2 rounded mb-2"><code>C:\xampp\php\php.exe <?= e(str_replace('/', '\\', APP_ROOT)) ?>\tools\backup.php --full</code></pre>
        <div class="mb-1">กู้คืนจากบรรทัดคำสั่ง (กรณีเข้าเว็บไม่ได้):</div>
        <pre class="bg-light p-2 rounded mb-0"><code>C:\xampp\php\php.exe <?= e(str_replace('/', '\\', APP_ROOT)) ?>\tools\restore.php backups\ชื่อไฟล์.zip</code></pre>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
