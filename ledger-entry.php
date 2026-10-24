<?php
require_once __DIR__ . '/includes/bootstrap.php';
$dept = require_ledger_access();
$deptId = (int)$dept['id'];
$uid = (int)current_user()['id'];
$kinds = ledger_kinds();

$entry = null;
if (get('id') !== '') {
    $entry = ledger_entry((int)get('id'), $deptId);
    if (!$entry) {
        flash('danger', 'ไม่พบรายการในบัญชีของแผนกคุณ');
        redirect('ledger.php');
    }
}
$perms = ledger_perms($dept);
// ดูอย่างเดียว: ครูที่ไม่มีสิทธิ์แก้ไขรายการนี้
$viewOnly = $entry ? !ledger_can_edit($perms, $entry) : !$perms['add'];
if ($viewOnly && !$entry) {
    flash('warning', 'ผู้รับผิดชอบบัญชียังไม่เปิดให้ครูบันทึกรายการ');
    redirect('ledger.php');
}
$cats = ledger_categories($deptId);
$catIds = array_map('intval', array_column($cats, 'id'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($viewOnly) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์แก้ไขรายการนี้');
    }
    $amount = str_replace([',', ' '], '', post('amount'));
    $data = [
        'kind'         => isset($kinds[post('kind')]) ? post('kind') : '',
        'entry_date'   => post('entry_date'),
        'category_id'  => in_array((int)post('category_id'), $catIds, true) ? (int)post('category_id') : null,
        'description'  => mb_substr(post('description'), 0, 255),
        'amount'       => is_numeric($amount) ? round((float)$amount, 2) : 0,
        'ref_no'       => mb_substr(post('ref_no'), 0, 100) ?: null,
        'counterparty' => mb_substr(post('counterparty'), 0, 150) ?: null,
        'note'         => post('note') ?: null,
    ];
    if (!$data['kind']) $errors[] = 'กรุณาเลือกรายรับหรือรายจ่าย';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['entry_date'])) $errors[] = 'กรุณาระบุวันที่';
    if ($data['description'] === '') $errors[] = 'กรุณากรอกรายการ';
    if ($data['amount'] <= 0 || $data['amount'] > 9999999999) $errors[] = 'จำนวนเงินต้องมากกว่า 0';
    if ($data['category_id']) {
        $catKind = array_column($cats, 'kind', 'id')[$data['category_id']] ?? '';
        if ($catKind !== $data['kind']) $data['category_id'] = null; // หมวดไม่ตรงประเภท
    }

    $fileInfo = null;
    if (!$errors && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $fileInfo = save_upload($_FILES['file'], 'receipt'); // ใบเสร็จ → Google Drive: DocsHub/uploads/receipt
        } catch (RuntimeException $ex) {
            $errors[] = 'หลักฐาน: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        $types = 'ssisdsss';
        if ($entry) {
            $id = (int)$entry['id'];
            db_exec('UPDATE ledger_entries SET kind=?, entry_date=?, category_id=?, description=?, amount=?, ref_no=?, counterparty=?, note=?, updated_by=?, updated_at=NOW()
                     WHERE id=? AND department_id=?', $types . 'iii', array_merge(array_values($data), [$uid, $id, $deptId]));
            log_activity('ledger_update', 'รายการ #' . $id . ' แผนก ' . $dept['code']);
        } else {
            $id = db_exec('INSERT INTO ledger_entries (kind, entry_date, category_id, description, amount, ref_no, counterparty, note, department_id, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?)', $types . 'ii', array_merge(array_values($data), [$deptId, $uid]));
            log_activity('ledger_create', 'รายการ #' . $id . ' แผนก ' . $dept['code']);
        }
        if ($fileInfo) {
            if ($entry) delete_stored_file($entry['file_name']);
            db_exec('UPDATE ledger_entries SET file_name=?, file_original=?, file_size=?, file_ext=? WHERE id=?', 'ssisi', array_merge(array_values($fileInfo), [$id]));
        } elseif ($entry && post('remove_file') === '1') {
            delete_stored_file($entry['file_name']);
            db_exec('UPDATE ledger_entries SET file_name=NULL, file_original=NULL, file_size=0, file_ext=NULL WHERE id=?', 'i', [$id]);
        }
        flash('success', 'บันทึก' . $kinds[$data['kind']] . ' ' . money($data['amount']) . ' บาท เรียบร้อยแล้ว');
        redirect(post('again') === '1' ? 'ledger-entry.php?kind=' . $data['kind'] : 'ledger.php?fy=' . fiscal_year_be($data['entry_date']));
    }
}

$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($entry ?? []);
$val = fn($k, $def = '') => e(is_string($src[$k] ?? null) ? trim($src[$k]) : ($src[$k] ?? $def));
$kindSel = $src['kind'] ?? (isset($kinds[get('kind')]) ? get('kind') : 'expense');

$pageTitle = $viewOnly ? 'รายละเอียดรายการบัญชี' : ($entry ? 'แก้ไข' : 'บันทึก') . 'รายรับ-รายจ่าย';
$activeNav = 'ledger';
require __DIR__ . '/includes/header.php';
$ledgerTab = $entry ? 'overview' : $kindSel;
require __DIR__ . '/includes/ledger_nav.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('ledger.php') ?>">บัญชี<?= e($dept['name']) ?></a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3"><i class="bi bi-cash-coin me-2"></i><?= e($pageTitle) ?></h4>
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <?php if ($viewOnly): ?><div class="alert alert-info small"><i class="bi bi-eye me-1"></i>ดูได้อย่างเดียว — แก้ไขได้เฉพาะผู้บันทึกรายการหรือผู้รับผิดชอบบัญชี</div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <fieldset class="row g-3"<?= $viewOnly ? ' disabled' : '' ?>>
          <?= csrf_field() ?>
          <div class="col-12">
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="kind" value="income" id="kInc"<?= $kindSel === 'income' ? ' checked' : '' ?>>
              <label class="btn btn-outline-success" for="kInc"><i class="bi bi-arrow-down-circle me-1"></i>รายรับ</label>
              <input type="radio" class="btn-check" name="kind" value="expense" id="kExp"<?= $kindSel === 'expense' ? ' checked' : '' ?>>
              <label class="btn btn-outline-danger" for="kExp"><i class="bi bi-arrow-up-circle me-1"></i>รายจ่าย</label>
            </div>
          </div>
          <div class="col-md-4"><label class="form-label">วันที่ *</label><input type="date" class="form-control" name="entry_date" required value="<?= $val('entry_date', date('Y-m-d')) ?>"></div>
          <div class="col-md-4"><label class="form-label">จำนวนเงิน (บาท) *</label><input type="number" step="0.01" min="0.01" class="form-control text-end" name="amount" required value="<?= $val('amount') ?>"></div>
          <div class="col-md-4">
            <label class="form-label">หมวด</label>
            <select class="form-select" name="category_id" id="ledgerCat">
              <option value="">— ไม่ระบุ —</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" data-kind="<?= $c['kind'] ?>"<?= (string)($src['category_id'] ?? '') === (string)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label">รายการ *</label><input class="form-control" name="description" required maxlength="255" value="<?= $val('description') ?>" placeholder="เช่น ซื้อวัสดุฝึกงานไฟฟ้า"></div>
          <div class="col-md-6"><label class="form-label">เลขที่ใบเสร็จ / เอกสารอ้างอิง</label><input class="form-control" name="ref_no" maxlength="100" value="<?= $val('ref_no') ?>"></div>
          <div class="col-md-6"><label class="form-label" id="cpLabel">ผู้รับเงิน / ร้านค้า</label><input class="form-control" name="counterparty" maxlength="150" value="<?= $val('counterparty') ?>"></div>
          <div class="col-12"><label class="form-label">หมายเหตุ</label><textarea class="form-control" name="note" rows="2"><?= $val('note') ?></textarea></div>
          <div class="col-12">
            <label class="form-label">หลักฐาน (ใบเสร็จ / ภาพถ่าย / PDF)</label>
            <input type="file" class="form-control" name="file" accept=".<?= implode(',.', ALLOWED_EXT) ?>">
            <?php if (!empty($entry['file_name'])): ?>
              <div class="small mt-1"><a href="<?= url('ledger-file.php?id=' . $entry['id']) ?>" class="pe-auto"><i class="bi bi-paperclip"></i><?= e($entry['file_original']) ?></a>
                <span class="form-check form-check-inline ms-2"><input class="form-check-input" type="checkbox" name="remove_file" value="1" id="rmf"><label class="form-check-label" for="rmf">ลบไฟล์</label></span></div>
            <?php endif; ?>
          </div>
          <?php if ($entry): ?>
            <div class="col-12 small text-muted">บันทึกโดย <?= e($entry['creator']) ?> · <?= thai_date($entry['created_at'], true, true) ?><?= $entry['updater'] ? ' · แก้ไขล่าสุดโดย ' . e($entry['updater']) . ' ' . thai_date($entry['updated_at'], true, true) : '' ?></div>
          <?php endif; ?>
          </fieldset>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if (!$viewOnly): ?>
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
            <?php if (!$entry): ?><button class="btn btn-outline-primary" name="again" value="1"><i class="bi bi-plus-lg me-1"></i>บันทึกและเพิ่มรายการต่อ</button><?php endif; ?>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('ledger.php') ?>"><?= $viewOnly ? 'กลับ' : 'ยกเลิก' ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
// แสดงเฉพาะหมวดที่ตรงกับรายรับ/รายจ่าย
(function () {
  var sel = document.getElementById('ledgerCat');
  function sync() {
    var kind = document.querySelector('[name=kind]:checked').value;
    Array.prototype.forEach.call(sel.options, function (o) {
      if (!o.value) return;
      o.hidden = o.getAttribute('data-kind') !== kind;
      if (o.hidden && o.selected) sel.value = '';
    });
    document.getElementById('cpLabel').textContent = kind === 'income' ? 'ผู้จ่ายเงิน / แหล่งที่มา' : 'ผู้รับเงิน / ร้านค้า';
  }
  document.querySelectorAll('[name=kind]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
