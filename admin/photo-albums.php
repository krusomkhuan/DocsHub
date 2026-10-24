<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/album_import.php';
require_staff();

// แบบฟอร์มไฟล์ตัวอย่างสำหรับนำเข้า (ผู้ดูแลระบบ)
if (get('template') === 'csv' && is_admin()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="photo-albums-template.csv"');
    echo "\xEF\xBB\xBF" . "ลำดับ,ชื่ออัลบั้ม,ลิงก์,วันที่,ปีการศึกษา\n"
        . "1,ประกวดโครงงานวิทยาศาสตร์ ประจำปี 2567,https://photos.app.goo.gl/xxxxxxxx,15/08/2567,2567\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    // ---------- นำเข้าข้อมูลเก่า (เฉพาะผู้ดูแลระบบ): อัปโหลด/วาง → ตรวจสอบตัวอย่าง → ยืนยัน ----------
    if (in_array($action, ['import_preview', 'import_commit', 'import_cancel'], true)) {
        if (!is_admin()) {
            http_response_code(403);
            exit('การนำเข้าข้อมูลทำได้เฉพาะผู้ดูแลระบบ');
        }
        if ($action === 'import_cancel') {
            unset($_SESSION['album_import']);
            redirect('admin/photo-albums.php');
        }
        if ($action === 'import_preview') {
            try {
                $file = $_FILES['import_file'] ?? null;
                if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ (ไฟล์อาจใหญ่เกิน ' . ini_get('upload_max_filesize') . ')');
                    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
                    $rows = album_import_read_file($file['tmp_name'], $file['name']);
                    $source = basename($file['name']);
                } elseif (trim((string)($_POST['lines'] ?? '')) !== '') {
                    $rows = album_read_delimited((string)$_POST['lines'], 'txt');
                    $source = 'ข้อความที่วาง';
                } else {
                    throw new RuntimeException('กรุณาเลือกไฟล์ หรือวางรายการอัลบั้ม');
                }
                $map = album_import_map($rows);
                if (!$map['items']) throw new RuntimeException('ไม่พบข้อมูลในไฟล์');
                $_SESSION['album_import'] = $map + [
                    'source' => $source,
                    'year'   => in_array(post('academic_year'), array_map('strval', academic_years()), true) ? post('academic_year') : null,
                    'dept'   => isset(departments()[(int)post('department_id')]) ? (int)post('department_id') : null,
                ];
                redirect('admin/photo-albums.php?import=preview#import');
            } catch (Throwable $ex) {
                flash('danger', 'นำเข้าไม่ได้: ' . $ex->getMessage());
                redirect('admin/photo-albums.php#import');
            }
        }
        // ยืนยันนำเข้า
        $imp = $_SESSION['album_import'] ?? null;
        if (!$imp) redirect('admin/photo-albums.php');
        $status = post('status') === 'pending' ? 'pending' : 'approved';
        $uid = (int)current_user()['id'];
        $added = $skipped = 0;
        foreach ($imp['items'] as $it) {
            if ($it['state'] !== 'ok' || album_duplicate($it['url'])) { $skipped++; continue; }
            db_exec('INSERT INTO photo_albums (title, url, event_date, academic_year, department_id, status, submitted_by, approved_by) VALUES (?,?,?,?,?,?,?,?)', 'ssssisii',
                [$it['title'], $it['url'], $it['date'], $it['year'] ?? $imp['year'], $imp['dept'], $status, $uid, $status === 'approved' ? $uid : null]);
            $added++;
        }
        unset($_SESSION['album_import']);
        log_activity('album_import', 'นำเข้าจาก ' . $imp['source'] . ': ' . $added . ' รายการ');
        flash($added ? 'success' : 'warning', 'นำเข้าอัลบั้มจาก ' . $imp['source'] . ' แล้ว ' . number_format($added) . ' รายการ' . ($skipped ? ' · ข้าม ' . number_format($skipped) . ' รายการ (ซ้ำหรืออ่านไม่ได้)' : ''));
        redirect('admin/photo-albums.php');
    }

    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if ($ids && in_array($action, ['approved', 'rejected', 'pending', 'delete'], true)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $t = str_repeat('i', count($ids));
        if ($action === 'delete') {
            db_exec("DELETE FROM photo_albums WHERE id IN ($ph)", $t, $ids);
            flash('success', 'ลบ ' . count($ids) . ' รายการแล้ว');
        } else {
            $reason = $action === 'rejected' ? (mb_substr(post('reason'), 0, 255) ?: null) : null;
            db_exec("UPDATE photo_albums SET status = ?, reject_reason = ?, approved_by = ?, updated_at = NOW() WHERE id IN ($ph)", 'ssi' . $t,
                array_merge([$action, $reason, $action === 'approved' ? current_user()['id'] : null], $ids));
            flash('success', 'ปรับสถานะ ' . count($ids) . ' รายการแล้ว');
        }
        log_activity('album_' . $action, '#' . implode(',#', $ids));
    }
    redirect('admin/photo-albums.php?' . http_build_query($_GET));
}

$where = '1=1';
$types = '';
$params = [];
if (in_array(get('status'), ['pending', 'approved', 'rejected'], true)) { $where .= ' AND a.status = ?'; $types .= 's'; $params[] = get('status'); }
if (get('q') !== '') {
    $where .= ' AND (a.title LIKE ? OR a.url LIKE ?)';
    $like = '%' . get('q') . '%';
    $types .= 'ss';
    array_push($params, $like, $like);
}
$p = paginate((int)db_val("SELECT COUNT(*) FROM photo_albums a WHERE $where", $types, $params), 50);
$rows = db_all("SELECT a.*, d.name AS dept_name, u.fullname AS submitter FROM photo_albums a
                LEFT JOIN departments d ON d.id = a.department_id LEFT JOIN users u ON u.id = a.submitted_by
                WHERE $where ORDER BY FIELD(a.status,'pending','approved','rejected'), COALESCE(a.event_date, DATE(a.created_at)) DESC, a.id DESC
                LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$counts = db_row("SELECT SUM(status='pending') p, SUM(status='approved') a, SUM(status='rejected') r, COALESCE(SUM(clicks),0) c FROM photo_albums");

$pageTitle = 'คลังรูปภาพ';
$activeMenu = 'albums';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3 mb-3">
  <?php foreach ([['รออนุมัติ', (int)$counts['p'], 'bi-hourglass-split', 'warning', 'pending'], ['เผยแพร่แล้ว', (int)$counts['a'], 'bi-images', 'success', 'approved'], ['ไม่อนุมัติ', (int)$counts['r'], 'bi-x-circle', 'secondary', 'rejected'], ['จำนวนครั้งที่เปิดดู', (int)$counts['c'], 'bi-eye', 'info', '']] as [$lbl, $n, $ic, $c, $st]): ?>
    <div class="col-6 col-xl-3"><a class="text-decoration-none text-body" href="?status=<?= $st ?>"><div class="card shadow-sm stat-card"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon bg-<?= $c ?>-subtle text-<?= $c ?>-emphasis"><i class="bi <?= $ic ?>"></i></div>
      <div><div class="fs-5 fw-semibold"><?= number_format($n) ?></div><div class="small text-muted"><?= $lbl ?></div></div>
    </div></div></a></div>
  <?php endforeach; ?>
</div>

<?php if (is_admin()): $imp = get('import') === 'preview' ? ($_SESSION['album_import'] ?? null) : null; ?>
<div class="card shadow-sm mb-3<?= $imp ? ' border-primary' : '' ?>" id="import">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><i class="bi bi-file-earmark-arrow-up me-1"></i>นำเข้าข้อมูลคลังรูปภาพเดิม <span class="badge text-bg-danger">ผู้ดูแลระบบ</span></span>
    <?php if (!$imp): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#importBox">เปิด/ปิด</button><?php endif; ?>
  </div>
  <?php if ($imp):
      $cnt = ['ok' => 0, 'dup' => 0, 'bad' => 0];
      foreach ($imp['items'] as $it) $cnt[$it['state']]++; ?>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
        <div><strong><?= e($imp['source']) ?></strong> · <?= number_format(count($imp['items'])) ?> แถว</div>
        <span class="badge text-bg-success">นำเข้าได้ <?= number_format($cnt['ok']) ?></span>
        <span class="badge text-bg-warning">ซ้ำ <?= number_format($cnt['dup']) ?></span>
        <span class="badge text-bg-secondary">อ่านไม่ได้ <?= number_format($cnt['bad']) ?></span>
      </div>
      <div class="small text-muted mb-2"><i class="bi bi-diagram-3 me-1"></i><?= e($imp['mapping']) ?><?= $imp['year'] ? ' · ปีการศึกษาเริ่มต้น ' . e($imp['year']) : '' ?><?= $imp['dept'] ? ' · ' . e(departments()[$imp['dept']]['name'] ?? '') : '' ?></div>
      <div class="table-responsive border rounded" style="max-height:420px;overflow:auto">
        <table class="table table-sm small mb-0">
          <thead class="table-light sticky-top"><tr><th>แถว</th><th>ชื่ออัลบั้ม</th><th>ลิงก์</th><th>วันที่</th><th>ปี</th><th>ผล</th></tr></thead>
          <tbody>
            <?php foreach ($imp['items'] as $it): ?>
              <tr class="<?= ['ok' => '', 'dup' => 'table-warning', 'bad' => 'table-secondary text-muted'][$it['state']] ?>">
                <td><?= (int)$it['line'] ?></td>
                <td><?= e($it['title']) ?></td>
                <td class="text-break" style="max-width:260px"><?= e($it['url']) ?></td>
                <td class="text-nowrap"><?= $it['date'] ? thai_date($it['date']) : '' ?></td>
                <td><?= e($it['year'] ?? $imp['year'] ?? '') ?></td>
                <td class="text-nowrap"><?= $it['state'] === 'ok' ? '<i class="bi bi-check-circle text-success"></i> นำเข้า' : e($it['note']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
        <form method="post" class="d-flex flex-wrap gap-2 align-items-center" data-confirm="นำเข้า <?= number_format($cnt['ok']) ?> รายการเข้าคลังรูปภาพ?">
          <?= csrf_field() ?><input type="hidden" name="action" value="import_commit">
          <select class="form-select form-select-sm w-auto" name="status"><option value="approved">เผยแพร่ทันที</option><option value="pending">ตั้งเป็นรออนุมัติ</option></select>
          <button class="btn btn-primary btn-sm"<?= $cnt['ok'] ? '' : ' disabled' ?>><i class="bi bi-check2-all me-1"></i>ยืนยันนำเข้า <?= number_format($cnt['ok']) ?> รายการ</button>
        </form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="import_cancel"><button class="btn btn-outline-secondary btn-sm">ยกเลิก / เลือกไฟล์ใหม่</button></form>
      </div>
    </div>
  <?php else: ?>
  <div class="collapse<?= get('import') !== '' ? ' show' : '' ?>" id="importBox">
    <form method="post" enctype="multipart/form-data" class="card-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="import_preview">
      <div class="row g-3">
        <div class="col-lg-6">
          <label class="form-label fw-medium">1) อัปโหลดไฟล์ข้อมูลเดิม</label>
          <input type="file" class="form-control" name="import_file" accept=".xlsx,.csv,.tsv,.txt">
          <div class="form-text">
            Excel (.xlsx), CSV, TSV หรือ TXT ไม่เกิน 10 MB · จาก Google Sheets ให้ใช้ ไฟล์ → ดาวน์โหลด → Microsoft Excel (.xlsx)<br>
            ระบบหาคอลัมน์ "ชื่ออัลบั้ม" และ "ลิงก์" จากหัวตารางให้อัตโนมัติ (รองรับลิงก์แบบ hyperlink และคอลัมน์วันที่/ปีการศึกษา ถ้ามี)
            · <a href="?template=csv">ดาวน์โหลดไฟล์ตัวอย่าง</a>
          </div>
        </div>
        <div class="col-lg-6">
          <label class="form-label fw-medium">หรือ 2) วางรายการ</label>
          <textarea class="form-control font-monospace small" name="lines" rows="4" placeholder="ประกวดโครงงานวิทยาศาสตร์ ประจำปี 2567	https://photos.app.goo.gl/xxxx&#10;กิจกรรมวันแม่แห่งชาติ 12 สิงหาคม | https://photos.app.goo.gl/yyyy"></textarea>
          <div class="form-text">คัดลอกคอลัมน์ชื่ออัลบั้มและลิงก์จาก Sheets/Excel มาวาง บรรทัดละ 1 อัลบั้ม</div>
        </div>
        <div class="col-md-4"><select class="form-select form-select-sm" name="academic_year"><option value="">ปีการศึกษาเริ่มต้น (ไม่ระบุ)</option>
          <?php foreach (academic_years() as $y): ?><option><?= e($y) ?></option><?php endforeach; ?></select>
          <div class="form-text">ใช้กับแถวที่ไม่มีปีการศึกษาในไฟล์</div></div>
        <div class="col-md-5"><select class="form-select form-select-sm" name="department_id"><option value="">แผนก / ฝ่าย / งาน (ไม่ระบุ)</option>
          <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"><?= e($dp['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-eye me-1"></i>อ่านไฟล์และแสดงตัวอย่าง</button></div>
      </div>
      <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>ระบบจะแสดงตัวอย่างให้ตรวจก่อน ยังไม่บันทึกจนกว่าจะกด "ยืนยันนำเข้า" · ลิงก์ที่มีในคลังแล้วหรือซ้ำในไฟล์จะถูกข้าม</div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2">
    <div class="col-md-6"><input class="form-control form-control-sm" name="q" placeholder="ค้นหาชื่ออัลบั้มหรือลิงก์" value="<?= e(get('q')) ?>"></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="status"><option value="">ทุกสถานะ</option>
      <?php foreach (['pending' => 'รออนุมัติ', 'approved' => 'เผยแพร่แล้ว', 'rejected' => 'ไม่อนุมัติ'] as $k => $lbl): ?><option value="<?= $k ?>"<?= get('status') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button></div>
    <div class="col-md-2"><a class="btn btn-sm btn-success w-100" href="<?= url('photo-album-form.php?back=admin') ?>"><i class="bi bi-plus-lg"></i> เพิ่ม</a></div>
  </form>
</div></div>

<form method="post" class="card shadow-sm" data-confirm="ยืนยันการดำเนินการกับรายการที่เลือก?">
  <?= csrf_field() ?>
  <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
    <span class="small text-muted me-auto">พบ <?= number_format($p['total']) ?> รายการ</span>
    <select class="form-select form-select-sm w-auto" name="action" required onchange="document.getElementById('reasonBox').classList.toggle('d-none', this.value !== 'rejected')">
      <option value="">— ดำเนินการกับที่เลือก —</option>
      <option value="approved">อนุมัติ / เผยแพร่</option><option value="rejected">ไม่อนุมัติ</option><option value="pending">ตั้งเป็นรออนุมัติ</option><option value="delete">ลบ</option>
    </select>
    <input class="form-control form-control-sm w-auto d-none" id="reasonBox" name="reason" maxlength="255" placeholder="เหตุผลที่ไม่อนุมัติ">
    <button class="btn btn-sm btn-outline-primary">ตกลง</button>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr>
      <th style="width:30px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.chk').forEach(c=>c.checked=this.checked)"></th>
      <th>อัลบั้ม</th><th>ลิงก์</th><th>สถานะ</th><th>ผู้ส่ง</th><th class="text-end"><i class="bi bi-eye"></i></th><th></th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">ยังไม่มีอัลบั้ม</td></tr><?php endif; ?>
    <?php foreach ($rows as $a): [$src, $icon] = album_source($a['url']); ?>
      <tr>
        <td><input type="checkbox" class="form-check-input chk" name="ids[]" value="<?= $a['id'] ?>"></td>
        <td><?= e($a['title']) ?><div class="small text-muted"><?= $a['event_date'] ? thai_date($a['event_date']) . ' · ' : '' ?><?= $a['academic_year'] ? 'ปี ' . e($a['academic_year']) . ' · ' : '' ?><?= e($a['dept_name']) ?></div></td>
        <td class="small text-break" style="max-width:280px"><a href="<?= url('photo-albums.php?go=' . $a['id']) ?>" target="_blank" rel="noopener"><i class="bi <?= $icon ?> me-1"></i><?= e($a['url']) ?></a></td>
        <td><?= status_badge($a['status']) ?><?= $a['status'] === 'rejected' && $a['reject_reason'] ? '<div class="small text-muted">' . e($a['reject_reason']) . '</div>' : '' ?></td>
        <td class="small"><?= e($a['submitter']) ?><div class="text-muted"><?= thai_date($a['created_at']) ?></div></td>
        <td class="text-end small"><?= number_format($a['clicks']) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('photo-album-form.php?id=' . $a['id'] . '&back=admin') ?>"><i class="bi bi-pencil"></i></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</form>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
