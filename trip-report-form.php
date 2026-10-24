<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$report = null;
if (get('id') !== '') {
    $report = trip_get((int)get('id'));
    if (!$report || !trip_can_edit($report)) {
        flash('danger', 'ไม่พบรายงาน หรือคุณไม่มีสิทธิ์แก้ไข');
        redirect('trip-reports.php');
    }
}

// คำสั่งที่อ้างอิง
$docId = $report ? (int)$report['document_id'] : (int)($_POST['document_id'] ?? get('doc'));
$doc = $docId ? db_row('SELECT * FROM documents WHERE id = ?', 'i', [$docId]) : null;
if ($doc && (!doc_allows_trip_report($doc) || !can_view_document($doc))) {
    $doc = null;
}

// รายการคำสั่งให้เลือก (กรณีเขียนรายงานใหม่)
$docChoices = [];
if (!$report && trip_category_ids()) {
    $ph = implode(',', array_fill(0, count(trip_category_ids()), '?'));
    $types = str_repeat('i', count(trip_category_ids()));
    $params = trip_category_ids();
    $vis = '1=1';
    if (!is_staff()) {
        $vis = "(d.status = 'approved' OR d.uploaded_by = ?)";
        $types .= 'i';
        $params[] = (int)current_user()['id'];
    }
    $docChoices = db_all("SELECT d.id, d.title, d.doc_no, d.doc_date FROM documents d
                          WHERE d.category_id IN ($ph) AND $vis ORDER BY COALESCE(d.doc_date, DATE(d.created_at)) DESC LIMIT 300", $types, $params);
}

$images = $report ? db_all('SELECT * FROM trip_report_images WHERE report_id = ? ORDER BY sort_order, id', 'i', [$report['id']]) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'subject'    => mb_substr(post('subject'), 0, 255),
        'travelers'  => post('travelers') ?: null,
        'place'      => mb_substr(post('place'), 0, 255),
        'start_date' => post('start_date'),
        'end_date'   => post('end_date') ?: post('start_date'),
        'purpose'    => post('purpose') ?: null,
        'result'     => post('result'),
        'benefit'    => post('benefit') ?: null,
        'suggestion' => post('suggestion') ?: null,
        'expense'    => post('expense') !== '' ? round((float)str_replace(',', '', post('expense')), 2) : null,
        'is_public'  => post('is_public') === '1' ? 1 : 0,
    ];
    if (!$doc) $errors[] = 'กรุณาเลือกคำสั่งไปราชการที่อ้างอิง';
    if ($data['subject'] === '') $errors[] = 'กรุณากรอกเรื่อง';
    if ($data['place'] === '') $errors[] = 'กรุณากรอกสถานที่';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['end_date'])) $errors[] = 'กรุณาระบุวันที่ไปราชการ';
    elseif ($data['end_date'] < $data['start_date']) $errors[] = 'วันที่กลับต้องไม่ก่อนวันที่ไป';
    if ($data['result'] === '') $errors[] = 'กรุณากรอกผลการไปราชการ';

    $newImages = isset($_FILES['images']) ? normalize_files($_FILES['images']) : [];
    $deleteIds = array_map('intval', array_keys(array_filter((array)($_POST['delete_image'] ?? []))));
    if (count($images) - count($deleteIds) + count($newImages) > TRIP_MAX_IMAGES) {
        $errors[] = 'แนบภาพได้ไม่เกิน ' . TRIP_MAX_IMAGES . ' ภาพต่อรายงาน';
    }

    $fileInfo = null;
    if (!$errors && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $fileInfo = save_upload($_FILES['file'], 'trip-reports'); // Drive: uploads/trips/reports
        } catch (RuntimeException $ex) {
            $errors[] = 'ไฟล์รายงาน: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        $types = 'sssssssssdi';
        if ($report) {
            $id = (int)$report['id'];
            db_exec('UPDATE trip_reports SET subject=?, travelers=?, place=?, start_date=?, end_date=?, purpose=?, result=?, benefit=?, suggestion=?, expense=?, is_public=?, updated_at=NOW() WHERE id=?',
                $types . 'i', array_merge(array_values($data), [$id]));
            log_activity('trip_update', '#' . $id . ' ' . $data['subject']);
        } else {
            $id = db_exec('INSERT INTO trip_reports (subject, travelers, place, start_date, end_date, purpose, result, benefit, suggestion, expense, is_public, document_id, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', $types . 'ii', array_merge(array_values($data), [$doc['id'], current_user()['id']]));
            log_activity('trip_create', '#' . $id . ' ' . $data['subject']);
        }

        // ไฟล์รายงานแนบ
        if ($fileInfo) {
            if ($report) delete_stored_file($report['file_name']);
            db_exec('UPDATE trip_reports SET file_name=?, file_original=?, file_size=?, file_ext=? WHERE id=?', 'ssisi', array_merge(array_values($fileInfo), [$id]));
        } elseif ($report && post('remove_file') === '1') {
            delete_stored_file($report['file_name']);
            db_exec('UPDATE trip_reports SET file_name=NULL, file_original=NULL, file_size=0, file_ext=NULL WHERE id=?', 'i', [$id]);
        }

        // ภาพเดิม: ลบ / แก้คำบรรยาย / เรียงลำดับ
        foreach ($images as $img) {
            if (in_array((int)$img['id'], $deleteIds, true)) {
                trip_delete_image_files($img['file_name']);
                db_exec('DELETE FROM trip_report_images WHERE id = ?', 'i', [$img['id']]);
            } else {
                db_exec('UPDATE trip_report_images SET caption = ?, sort_order = ? WHERE id = ?', 'sii',
                    [mb_substr(trim((string)($_POST['caption'][$img['id']] ?? '')), 0, 255) ?: null, (int)($_POST['sort'][$img['id']] ?? 0), $img['id']]);
            }
        }

        // ภาพใหม่
        $sort = (int)db_val('SELECT COALESCE(MAX(sort_order), 0) FROM trip_report_images WHERE report_id = ?', 'i', [$id]);
        $newCaptions = (array)($_POST['new_caption'] ?? []);
        $saved = 0;
        foreach ($newImages as $i => $f) {
            try {
                [$name, $w, $h, $size] = trip_save_image($f);
                db_exec('INSERT INTO trip_report_images (report_id, file_name, caption, sort_order, width, height, file_size) VALUES (?,?,?,?,?,?,?)', 'issiiii',
                    [$id, $name, mb_substr(trim((string)($newCaptions[$i] ?? '')), 0, 255) ?: null, ++$sort, $w, $h, $size]);
                $saved++;
            } catch (RuntimeException $ex) {
                flash('warning', $ex->getMessage());
            }
        }
        flash('success', 'บันทึกรายงานการไปราชการแล้ว' . ($saved ? " · เพิ่มภาพ $saved ภาพ" : ''));
        redirect('trip-report.php?id=' . $id);
    }
}

$v = fn($k, $def = '') => e($_SERVER['REQUEST_METHOD'] === 'POST' ? post($k, $def) : ($report[$k] ?? $def));
$pageTitle = $report ? 'แก้ไขรายงานการไปราชการ' : 'เขียนรายงานการไปราชการ';
$activeNav = 'trip';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('trip-reports.php') ?>">รายงานการไปราชการ</a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>

<div class="card shadow-sm">
  <div class="card-body p-4">
    <h4 class="mb-3"><i class="bi bi-geo-alt me-2"></i><?= e($pageTitle) ?></h4>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <?php if (!$report && !$docChoices && !$doc): ?>
      <div class="alert alert-warning">ยังไม่มีคำสั่งไปราชการในระบบ กรุณา<a href="<?= url('upload.php') ?>">อัปโหลดคำสั่งไปราชการ</a>ก่อน แล้วจึงเขียนรายงาน</div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="row g-3" id="tripForm">
      <?= csrf_field() ?>
      <div class="col-12">
        <label class="form-label">ตามคำสั่งไปราชการ *</label>
        <?php if ($report || ($doc && get('doc') !== '')): ?>
          <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <div class="form-control bg-light"><i class="bi bi-file-earmark-ruled me-1"></i><?= e($doc['title']) ?><?= $doc['doc_no'] ? ' · ที่ ' . e($doc['doc_no']) : '' ?><?= $doc['doc_date'] ? ' · ' . thai_date($doc['doc_date']) : '' ?></div>
        <?php else: ?>
          <select class="form-select" name="document_id" required>
            <option value="">— เลือกคำสั่ง —</option>
            <?php foreach ($docChoices as $c): ?>
              <option value="<?= $c['id'] ?>"<?= $docId === (int)$c['id'] ? ' selected' : '' ?>><?= e(($c['doc_no'] ? $c['doc_no'] . ' · ' : '') . $c['title']) ?><?= $c['doc_date'] ? ' (' . thai_date($c['doc_date']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
      <div class="col-md-8"><label class="form-label">เรื่อง *</label><input class="form-control" name="subject" required maxlength="255" value="<?= $v('subject', $doc['title'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">สถานที่ *</label><input class="form-control" name="place" required maxlength="255" value="<?= $v('place') ?>"></div>
      <div class="col-md-3"><label class="form-label">วันที่ไป *</label><input type="date" class="form-control" name="start_date" required value="<?= $v('start_date', $doc['doc_date'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">วันที่กลับ *</label><input type="date" class="form-control" name="end_date" required value="<?= $v('end_date', $doc['doc_date'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">ค่าใช้จ่าย (บาท)</label><input type="number" step="0.01" min="0" class="form-control" name="expense" value="<?= $v('expense') ?>"></div>
      <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
          <?php $pub = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('is_public') === '1' : (bool)($report['is_public'] ?? 1); ?>
          <input class="form-check-input" type="checkbox" name="is_public" value="1" id="isPub"<?= $pub ? ' checked' : '' ?>>
          <label class="form-check-label" for="isPub">เผยแพร่สาธารณะ</label>
        </div>
      </div>
      <div class="col-12"><label class="form-label">ผู้ไปราชการ</label><textarea class="form-control" name="travelers" rows="2" placeholder="รายชื่อ บรรทัดละ 1 คน"><?= $v('travelers', $report ? '' : current_user()['fullname']) ?></textarea></div>
      <div class="col-12"><label class="form-label">วัตถุประสงค์</label><textarea class="form-control" name="purpose" rows="2"><?= $v('purpose') ?></textarea></div>
      <div class="col-12"><label class="form-label">ผลการไปราชการ / สาระสำคัญ *</label><textarea class="form-control" name="result" rows="5" required><?= $v('result') ?></textarea></div>
      <div class="col-md-6"><label class="form-label">ประโยชน์ที่ได้รับและการนำไปใช้</label><textarea class="form-control" name="benefit" rows="3"><?= $v('benefit') ?></textarea></div>
      <div class="col-md-6"><label class="form-label">ปัญหา อุปสรรค / ข้อเสนอแนะ</label><textarea class="form-control" name="suggestion" rows="3"><?= $v('suggestion') ?></textarea></div>
      <div class="col-12">
        <label class="form-label">ไฟล์รายงานแนบ (ถ้ามี)</label>
        <input type="file" class="form-control" name="file" accept=".<?= implode(',.', ALLOWED_EXT) ?>">
        <?php if (!empty($report['file_name'])): ?>
          <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_file" value="1" id="rmf"><label class="form-check-label small" for="rmf">ลบไฟล์เดิม (<?= e($report['file_original']) ?>)</label></div>
        <?php endif; ?>
      </div>

      <div class="col-12"><hr class="my-1"><h5 class="mb-0"><i class="bi bi-images me-1"></i>ภาพประกอบ</h5><div class="small text-muted">สูงสุด <?= TRIP_MAX_IMAGES ?> ภาพ · JPG / PNG / WEBP · ระบบย่อขนาดภาพให้อัตโนมัติ</div></div>
      <?php if ($images): ?>
        <div class="col-12">
          <div class="row g-3">
            <?php foreach ($images as $img): ?>
              <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100">
                  <img src="<?= url('trip-image.php?id=' . $img['id'] . '&thumb=1') ?>" class="card-img-top" style="aspect-ratio:4/3;object-fit:cover" alt="">
                  <div class="card-body p-2">
                    <input class="form-control form-control-sm mb-1" name="caption[<?= $img['id'] ?>]" placeholder="คำบรรยายภาพ" maxlength="255" value="<?= e($img['caption']) ?>">
                    <div class="d-flex align-items-center gap-2">
                      <input type="number" class="form-control form-control-sm" style="width:70px" name="sort[<?= $img['id'] ?>]" value="<?= (int)$img['sort_order'] ?>" title="ลำดับ">
                      <div class="form-check small mb-0"><input class="form-check-input" type="checkbox" name="delete_image[<?= $img['id'] ?>]" value="1" id="di<?= $img['id'] ?>"><label class="form-check-label text-danger" for="di<?= $img['id'] ?>">ลบ</label></div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <div class="col-12">
        <input type="file" class="form-control" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-trip-images data-max="<?= TRIP_MAX_IMAGES - count($images) ?>">
        <div class="form-text" data-trip-status></div>
        <div class="row g-3 mt-1" data-trip-preview></div>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึกรายงาน</button>
        <a class="btn btn-outline-secondary" href="<?= url($report ? 'trip-report.php?id=' . $report['id'] : 'trip-reports.php') ?>">ยกเลิก</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
