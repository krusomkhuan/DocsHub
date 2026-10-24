<?php
/**
 * ฟอร์มเพิ่ม/แก้ไขเอกสาร ใช้ร่วมกันระหว่างหน้าบ้าน (upload.php) และหลังบ้าน (admin/document-form.php)
 */

/**
 * บันทึกข้อมูลจาก $_POST/$_FILES คืนค่า [id, errors]
 * สมาชิกทั่วไป: เอกสารใหม่/ที่แก้ไขจะมีสถานะรออนุมัติ
 */
function save_document_form(?array $doc): array
{
    $staff = is_staff();
    $errors = [];
    $typeId = (int)post('type_id');
    $type = doc_types()[$typeId] ?? null;
    $data = [
        'type_id'       => $typeId,
        'category_id'   => (int)post('category_id') ?: null,
        'department_id' => (int)post('department_id') ?: null,
        'title'         => mb_substr(post('title'), 0, 255),
        'doc_no'        => mb_substr(post('doc_no'), 0, 100) ?: null,
        'doc_date'      => post('doc_date') ?: null,
        'academic_year' => mb_substr(post('academic_year'), 0, 10) ?: null,
        'semester'      => mb_substr(post('semester'), 0, 10) ?: null,
        'subject_code'  => mb_substr(post('subject_code'), 0, 30) ?: null,
        'subject_name'  => mb_substr(post('subject_name'), 0, 150) ?: null,
        'owner_name'    => mb_substr(post('owner_name'), 0, 150) ?: null,
        'description'   => post('description') ?: null,
        'is_public'     => post('is_public') === '1' ? 1 : 0,
        'status'        => $staff ? (in_array(post('status'), ['pending', 'approved', 'rejected'], true) ? post('status') : 'approved') : 'pending',
    ];

    if (!$type) $errors[] = 'กรุณาเลือกประเภทเอกสาร';
    if ($data['title'] === '') $errors[] = 'กรุณากรอกชื่อเอกสาร';
    if ($data['category_id'] && !db_val('SELECT id FROM categories WHERE id = ? AND type_id = ?', 'ii', [$data['category_id'], $typeId])) {
        $data['category_id'] = null;
    }
    if ($data['department_id'] && !isset(departments()[$data['department_id']])) $data['department_id'] = null;
    if ($data['doc_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['doc_date'])) $errors[] = 'วันที่ไม่ถูกต้อง';

    $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
    if (!$doc && !$hasFile) $errors[] = 'กรุณาเลือกไฟล์เอกสาร';
    $wantNumber = $type && $type['has_order_no'] && post('auto_number') === '1' && !$data['doc_no'];
    if ($wantNumber && !$data['department_id']) $errors[] = 'กรุณาเลือกแผนกวิชา/ฝ่ายเพื่อออกเลขคำสั่ง';
    if ($errors) return [null, $errors];

    $fileInfo = null;
    if ($hasFile) {
        try {
            $fileInfo = save_upload($_FILES['file'], 'doc:' . $type['slug']); // Drive: โฟลเดอร์ตามประเภทเอกสาร
        } catch (RuntimeException $ex) {
            return [null, [$ex->getMessage()]];
        }
    }

    $cols = array_keys($data);
    $vals = array_values($data);
    $typesStr = 'iii' . str_repeat('s', 9) . 'is'; // ต้องตรงกับลำดับคีย์ใน $data
    if ($fileInfo) {
        $cols = array_merge($cols, array_keys($fileInfo));
        $vals = array_merge($vals, array_values($fileInfo));
        $typesStr .= 'ssis';
    }

    if ($doc) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
        db_exec("UPDATE documents SET $set, updated_at = NOW() WHERE id = ?", $typesStr . 'i', array_merge($vals, [$doc['id']]));
        if ($fileInfo) delete_stored_file($doc['file_name']);
        $id = (int)$doc['id'];
        log_activity('doc_update', '#' . $id . ' ' . $data['title']);
    } else {
        $cols[] = 'uploaded_by';
        $vals[] = current_user()['id'];
        $typesStr .= 'i';
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $id = db_exec('INSERT INTO documents (' . implode(',', $cols) . ") VALUES ($ph)", $typesStr, $vals);
        log_activity('doc_create', '#' . $id . ' ' . $data['title']);
    }

    // ออกเลขคำสั่งอัตโนมัติจากทะเบียนเลขหนังสือของแผนก
    if ($wantNumber) {
        $bn = issue_book_number($data['department_id'], 'order', $data['doc_date'] ?: date('Y-m-d'), $data['title'], '', 'ออกจากการอัปโหลดคำสั่ง', $id);
        db_exec('UPDATE documents SET doc_no = ? WHERE id = ?', 'si', [$bn['full_no'], $id]);
        flash('info', 'ออกเลขคำสั่ง: ' . $bn['full_no']);
    }
    return [$id, []];
}

function render_document_form(?array $doc, array $errors, string $cancelUrl): void
{
    $staff = is_staff();
    $v = fn($k, $def = '') => e($_SERVER['REQUEST_METHOD'] === 'POST' ? post($k, $def) : ($doc[$k] ?? $def));
    $sel = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($doc ?? []);
    $typeSel = (string)($sel['type_id'] ?? get('type'));
    $deptSel = (string)($sel['department_id'] ?? (current_user()['department_id'] ?? ''));
    $isPublic = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('is_public') === '1' : (bool)($doc['is_public'] ?? 1);
    $cats = array_map(fn($c) => ['id' => (int)$c['id'], 'type_id' => (int)$c['type_id'], 'name' => $c['name']], categories());
    ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-md-6">
        <label class="form-label">ประเภทเอกสาร *</label>
        <select class="form-select" name="type_id" required data-type-select>
          <option value="">— เลือก —</option>
          <?php foreach (doc_types() as $t): ?>
            <option value="<?= $t['id'] ?>" data-flags="<?= trim(($t['has_subject'] ? 'subject ' : '') . ($t['has_semester'] ? 'semester ' : '') . ($t['has_order_no'] ? 'order' : '')) ?>" data-owner-label="<?= e($t['owner_label']) ?>"<?= $typeSel === (string)$t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">หมวดหมู่</label>
        <select class="form-select" name="category_id" data-categories="<?= e(json_encode($cats, JSON_UNESCAPED_UNICODE)) ?>" data-selected="<?= e((string)($sel['category_id'] ?? '')) ?>"></select>
      </div>
      <div class="col-12">
        <label class="form-label">ชื่อเอกสาร / เรื่อง *</label>
        <input class="form-control" name="title" required maxlength="255" value="<?= $v('title') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">แผนกวิชา / ฝ่าย</label>
        <select class="form-select" name="department_id">
          <option value="">— ไม่ระบุ —</option>
          <?php foreach (departments() as $dp): ?>
            <option value="<?= $dp['id'] ?>"<?= $deptSel === (string)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">เลขที่เอกสาร</label>
        <input class="form-control" name="doc_no" maxlength="100" value="<?= $v('doc_no') ?>" placeholder="เช่น 123/2569">
      </div>
      <div class="col-md-3">
        <label class="form-label">ลงวันที่</label>
        <input type="date" class="form-control" name="doc_date" value="<?= $v('doc_date') ?>">
      </div>
      <?php if (!$doc): ?>
      <div class="col-12" data-show-for="order">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="auto_number" value="1" id="autoNo"<?= post('auto_number') === '1' ? ' checked' : '' ?>>
          <label class="form-check-label" for="autoNo">ออกเลขคำสั่งอัตโนมัติจากทะเบียนของแผนก (เมื่อไม่ได้กรอกเลขที่)</label>
        </div>
      </div>
      <?php endif; ?>
      <div class="col-md-3" data-show-for="subject">
        <label class="form-label">รหัสวิชา</label>
        <input class="form-control" name="subject_code" maxlength="30" value="<?= $v('subject_code') ?>">
      </div>
      <div class="col-md-5" data-show-for="subject">
        <label class="form-label">ชื่อวิชา</label>
        <input class="form-control" name="subject_name" maxlength="150" value="<?= $v('subject_name') ?>">
      </div>
      <div class="col-md-2" data-show-for="semester">
        <label class="form-label">ภาคเรียน</label>
        <select class="form-select" name="semester">
          <?php foreach (['', '1', '2', 'ฤดูร้อน'] as $s): ?>
            <option value="<?= e($s) ?>"<?= $v('semester') === e($s) ? ' selected' : '' ?>><?= $s === '' ? '-' : e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">ปีการศึกษา</label>
        <?php
          $yearSel = html_entity_decode($v('academic_year', (string)current_academic_year()), ENT_QUOTES);
          $yearOpts = academic_years();
          if ($yearSel !== '' && !in_array($yearSel, $yearOpts, true)) $yearOpts[] = $yearSel; // ปีเดิมของเอกสารที่ถูกลบออกจากรายการ
        ?>
        <select class="form-select" name="academic_year">
          <option value="">-</option>
          <?php foreach ($yearOpts as $y): ?>
            <option<?= $yearSel === (string)$y ? ' selected' : '' ?>><?= e($y) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label" data-owner-label>ผู้รับผิดชอบ</label>
        <input class="form-control" name="owner_name" maxlength="150" value="<?= $v('owner_name') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">ไฟล์เอกสาร <?= $doc ? '(เลือกใหม่หากต้องการแทนที่)' : '*' ?></label>
        <input type="file" class="form-control" name="file" <?= $doc ? '' : 'required' ?> accept=".<?= implode(',.', ALLOWED_EXT) ?>">
        <div class="form-text">
          <?= $doc ? 'ไฟล์ปัจจุบัน: ' . e($doc['file_original']) . ' · ' : '' ?>
          ไม่เกิน <?= e(setting('max_upload_mb', '20')) ?> MB · <?= implode(', ', ALLOWED_EXT) ?>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label">รายละเอียด</label>
        <textarea class="form-control" name="description" rows="3"><?= $v('description') ?></textarea>
      </div>
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="is_public" value="1" id="isPublic"<?= $isPublic ? ' checked' : '' ?>>
          <label class="form-check-label" for="isPublic">เผยแพร่สาธารณะ (ปิด = เฉพาะสมาชิกที่เข้าสู่ระบบ)</label>
        </div>
      </div>
      <?php if ($staff): ?>
      <div class="col-md-6">
        <label class="form-label me-2">สถานะ</label>
        <?php $st = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('status') : ($doc['status'] ?? 'approved'); ?>
        <?php foreach (['approved' => 'อนุมัติ', 'pending' => 'รออนุมัติ', 'rejected' => 'ไม่อนุมัติ'] as $k => $lbl): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" value="<?= $k ?>" id="st<?= $k ?>"<?= $st === $k ? ' checked' : '' ?>>
            <label class="form-check-label" for="st<?= $k ?>"><?= $lbl ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="col-12"><div class="alert alert-light border small mb-0"><i class="bi bi-info-circle me-1"></i>เอกสารจะแสดงในระบบหลังจากเจ้าหน้าที่ตรวจสอบและอนุมัติ</div></div>
      <?php endif; ?>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">ยกเลิก</a>
      </div>
    </form>
    <?php
}
