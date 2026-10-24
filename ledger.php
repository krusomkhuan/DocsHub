<?php
require_once __DIR__ . '/includes/bootstrap.php';
$dept = require_ledger_access();
$deptId = (int)$dept['id'];
$uid = (int)current_user()['id'];
$kinds = ledger_kinds();
$perms = ledger_perms($dept);

// ลบรายการ (ผู้รับผิดชอบบัญชี: ทุกรายการ, ครู: เฉพาะของตน)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    $en = ledger_entry((int)post('id'), $deptId);
    if ($en && ledger_can_delete($perms, $en)) {
        delete_stored_file($en['file_name']);
        db_exec('DELETE FROM ledger_entries WHERE id = ? AND department_id = ?', 'ii', [$en['id'], $deptId]);
        log_activity('ledger_delete', 'รายการ #' . $en['id'] . ' แผนก ' . $dept['code']);
        flash('success', 'ลบรายการแล้ว');
    } else {
        flash('danger', 'คุณไม่มีสิทธิ์ลบรายการนี้');
    }
    redirect('ledger.php?' . http_build_query($_GET));
}

$cats = ledger_categories($deptId);

$fy = (int)get('fy') ?: fiscal_year_be(date('Y-m-d'));
[$from, $to] = fiscal_year_range($fy);

// ยอดยกมา / ยอดรวมปีงบประมาณ
$carried = (float)db_val("SELECT COALESCE(SUM(IF(kind='income', amount, -amount)), 0) FROM ledger_entries WHERE department_id = ? AND entry_date < ?", 'is', [$deptId, $from]);
$tot = db_row("SELECT COALESCE(SUM(IF(kind='income', amount, 0)), 0) AS inc, COALESCE(SUM(IF(kind='expense', amount, 0)), 0) AS exp, COUNT(*) AS n
               FROM ledger_entries WHERE department_id = ? AND entry_date BETWEEN ? AND ?", 'iss', [$deptId, $from, $to]);
$balance = $carried + $tot['inc'] - $tot['exp'];

// ข้อความสรุปสำหรับแชร์ไป LINE (ลิงก์เปิดได้เฉพาะครูในแผนกที่เข้าสู่ระบบ)
$shareText = "📊 บัญชีรายรับ-รายจ่าย {$dept['name']}\n"
    . "ปีงบประมาณ {$fy} (ข้อมูล ณ " . thai_date(date('Y-m-d')) . ")\n"
    . 'ยอดยกมา: ' . money($carried) . " บาท\n"
    . '🟢 รายรับ: ' . money((float)$tot['inc']) . " บาท\n"
    . '🔴 รายจ่าย: ' . money((float)$tot['exp']) . " บาท\n"
    . '💰 คงเหลือ: ' . money($balance) . " บาท\n"
    . 'ดูรายละเอียด (เฉพาะครูในแผนก): ' . abs_url('ledger.php?fy=' . $fy);

// รายเดือน (ต.ค. → ก.ย.)
$monthly = [];
foreach (db_all("SELECT YEAR(entry_date) y, MONTH(entry_date) m, SUM(IF(kind='income', amount, 0)) inc, SUM(IF(kind='expense', amount, 0)) exp
                 FROM ledger_entries WHERE department_id = ? AND entry_date BETWEEN ? AND ? GROUP BY y, m", 'iss', [$deptId, $from, $to]) as $r) {
    $monthly[$r['y'] . '-' . $r['m']] = $r;
}
$thMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

// แยกตามหมวด
$byCat = db_all("SELECT e.kind, COALESCE(c.name, 'ไม่ระบุหมวด') AS name, SUM(e.amount) AS total
                 FROM ledger_entries e LEFT JOIN ledger_categories c ON c.id = e.category_id
                 WHERE e.department_id = ? AND e.entry_date BETWEEN ? AND ? GROUP BY e.kind, e.category_id ORDER BY total DESC", 'iss', [$deptId, $from, $to]);

// รายการตามตัวกรอง
$where = 'e.department_id = ? AND e.entry_date BETWEEN ? AND ?';
$types = 'iss';
$params = [$deptId, $from, $to];
if (isset($kinds[get('kind')])) { $where .= ' AND e.kind = ?'; $types .= 's'; $params[] = get('kind'); }
if ((int)get('month')) { $where .= ' AND MONTH(e.entry_date) = ?'; $types .= 'i'; $params[] = (int)get('month'); }
if ((int)get('cat')) { $where .= ' AND e.category_id = ?'; $types .= 'i'; $params[] = (int)get('cat'); }
if (get('q') !== '') {
    $where .= ' AND (e.description LIKE ? OR e.ref_no LIKE ? OR e.counterparty LIKE ? OR e.note LIKE ?)';
    $like = '%' . get('q') . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
$sqlList = "SELECT e.*, c.name AS cat_name, u.fullname AS creator FROM ledger_entries e
            LEFT JOIN ledger_categories c ON c.id = e.category_id LEFT JOIN users u ON u.id = e.created_by
            WHERE $where ORDER BY e.entry_date DESC, e.id DESC";

if (get('export') === 'csv') {
    log_activity('ledger_export', 'แผนก ' . $dept['code'] . ' ปีงบ ' . $fy);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ledger-' . $dept['id'] . '-' . $fy . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['วันที่', 'ประเภท', 'หมวด', 'รายการ', 'รายรับ', 'รายจ่าย', 'เลขที่อ้างอิง', 'ผู้จ่าย/ผู้รับเงิน', 'หมายเหตุ', 'ผู้บันทึก']);
    foreach (db_all($sqlList, $types, $params) as $r) {
        fputcsv($out, [thai_date($r['entry_date']), $kinds[$r['kind']], $r['cat_name'], $r['description'],
            $r['kind'] === 'income' ? $r['amount'] : '', $r['kind'] === 'expense' ? $r['amount'] : '', $r['ref_no'], $r['counterparty'], $r['note'], $r['creator']]);
    }
    exit;
}

$sum = db_row("SELECT COALESCE(SUM(IF(kind='income', amount, 0)), 0) inc, COALESCE(SUM(IF(kind='expense', amount, 0)), 0) exp FROM ledger_entries e WHERE $where", $types, $params);
$p = paginate((int)db_val("SELECT COUNT(*) FROM ledger_entries e WHERE $where", $types, $params), 30);
$rows = db_all($sqlList . " LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$fys = array_map('intval', array_column(db_all("SELECT DISTINCT YEAR(entry_date) + 543 + (MONTH(entry_date) >= 10) AS fy FROM ledger_entries WHERE department_id = ? ORDER BY fy DESC", 'i', [$deptId]), 'fy'));
if (!in_array(fiscal_year_be(date('Y-m-d')), $fys, true)) array_unshift($fys, fiscal_year_be(date('Y-m-d')));
if (!in_array($fy, $fys, true)) $fys[] = $fy;
rsort($fys);
$members = db_all("SELECT fullname FROM users WHERE department_id = ? AND status = 'active' ORDER BY fullname", 'i', [$deptId]);

$pageTitle = 'บัญชีรายรับ-รายจ่าย ' . $dept['name'];
$activeNav = 'ledger';
require __DIR__ . '/includes/header.php';
$ledgerTab = 'overview';
require __DIR__ . '/includes/ledger_nav.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="bi bi-cash-coin me-2"></i>บัญชีรายรับ-รายจ่าย</h4>
    <div class="text-muted"><?= e($dept['name']) ?> · ปีงบประมาณ <?= $fy ?> (<?= thai_date($from) ?> – <?= thai_date($to) ?>)</div>
  </div>
  <div class="d-flex flex-wrap gap-2 d-print-none">
    <form class="d-flex gap-2">
      <select class="form-select form-select-sm" name="fy" onchange="this.form.submit()">
        <?php foreach ($fys as $y): ?><option value="<?= $y ?>"<?= $y === $fy ? ' selected' : '' ?>>ปีงบประมาณ <?= $y ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($perms['add']): ?>
    <a class="btn btn-success btn-sm text-nowrap" href="<?= url('ledger-entry.php?kind=income') ?>"><i class="bi bi-plus-lg"></i> รายรับ</a>
    <a class="btn btn-danger btn-sm text-nowrap" href="<?= url('ledger-entry.php?kind=expense') ?>"><i class="bi bi-dash-lg"></i> รายจ่าย</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('ledger-categories.php') ?>" title="<?= $perms['manage'] ? 'หมวดและการตั้งค่าบัญชี' : 'หมวดรายรับ-รายจ่าย / ผู้รับผิดชอบบัญชี' ?>"><i class="bi bi-gear"></i></a>
    <a class="btn btn-sm text-white text-nowrap" style="background:#06C755" href="https://line.me/R/share?text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener" title="แชร์สรุปยอดไป LINE"><i class="bi bi-line"></i> แชร์ LINE</a>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-copy="<?= e($shareText) ?>" title="คัดลอกข้อความสรุปยอด (นำไปวางในแชตได้)"><i class="bi bi-clipboard"></i></button>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="พิมพ์"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="alert alert-light border small d-print-none"><i class="bi bi-shield-lock me-1"></i>ข้อมูลนี้เห็นได้เฉพาะครูประจำ<?= e($dept['name']) ?> (<?= count($members) ?> คน: <?= e(implode(', ', array_column($members, 'fullname'))) ?>) เท่านั้น ครูแผนกอื่น เจ้าหน้าที่ และผู้ดูแลระบบที่ไม่ได้สังกัดแผนกนี้ไม่สามารถดูได้
  <?php $mgrs = ledger_managers($deptId); ?>
  <div class="mt-1"><i class="bi bi-person-badge me-1"></i>ผู้รับผิดชอบบัญชี: <?= $mgrs ? e(implode(', ', array_column($mgrs, 'fullname'))) : '<span class="text-muted">ยังไม่ได้กำหนด (ครูทุกคนบันทึกและแก้ไขได้)</span>' ?>
    <?php if ($perms['is_manager']): ?><span class="badge text-bg-primary ms-1">คุณเป็นผู้รับผิดชอบบัญชี</span><?php elseif ($perms['has_manager']): ?><span class="badge text-bg-light ms-1">คุณบันทึกและแก้ไขได้ทุกรายการ · ลบได้เฉพาะรายการของตน</span><?php endif; ?></div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([['ยอดยกมา', $carried, 'bi-box-arrow-in-right', 'secondary'], ['รายรับ', $tot['inc'], 'bi-arrow-down-circle', 'success'], ['รายจ่าย', $tot['exp'], 'bi-arrow-up-circle', 'danger'], ['คงเหลือ', $balance, 'bi-wallet2', $balance < 0 ? 'danger' : 'primary']] as [$lbl, $val, $ic, $c]): ?>
    <div class="col-6 col-lg-3">
      <div class="card shadow-sm stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-<?= $c ?>-subtle text-<?= $c ?>-emphasis"><i class="bi <?= $ic ?>"></i></div>
        <div><div class="fs-5 fw-semibold text-<?= $c ?>-emphasis"><?= money((float)$val) ?></div><div class="small text-muted"><?= $lbl ?> (บาท)</div></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">สรุปรายเดือน</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>เดือน</th><th class="text-end">รายรับ</th><th class="text-end">รายจ่าย</th><th class="text-end">คงเหลือสะสม</th></tr></thead>
        <tbody>
        <?php $run = $carried; for ($i = 0; $i < 12; $i++):
            $ts = strtotime($from . " +$i month");
            $m = $monthly[date('Y', $ts) . '-' . (int)date('n', $ts)] ?? ['inc' => 0, 'exp' => 0];
            $run += $m['inc'] - $m['exp'];
            if ($ts > time() && !$m['inc'] && !$m['exp']) continue; ?>
          <tr>
            <td><a href="?<?= e(http_build_query(['fy' => $fy, 'month' => (int)date('n', $ts)])) ?>#list"><?= $thMonths[(int)date('n', $ts)] ?> <?= (int)date('Y', $ts) + 543 ?></a></td>
            <td class="text-end text-success"><?= $m['inc'] ? money((float)$m['inc']) : '-' ?></td>
            <td class="text-end text-danger"><?= $m['exp'] ? money((float)$m['exp']) : '-' ?></td>
            <td class="text-end fw-medium<?= $run < 0 ? ' text-danger' : '' ?>"><?= money($run) ?></td>
          </tr>
        <?php endfor; ?>
        </tbody>
        <tfoot class="table-light fw-semibold"><tr><td>รวม</td><td class="text-end text-success"><?= money((float)$tot['inc']) ?></td><td class="text-end text-danger"><?= money((float)$tot['exp']) ?></td><td class="text-end"><?= money($balance) ?></td></tr></tfoot>
      </table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">แยกตามหมวด</div>
      <div class="card-body">
        <?php if (!$byCat): ?><div class="text-muted small">ยังไม่มีรายการในปีงบประมาณนี้</div><?php endif; ?>
        <?php foreach (['income' => (float)$tot['inc'], 'expense' => (float)$tot['exp']] as $k => $total): $items = array_filter($byCat, fn($c) => $c['kind'] === $k); if (!$items) continue; ?>
          <div class="small fw-semibold mb-1 text-<?= $k === 'income' ? 'success' : 'danger' ?>"><?= $kinds[$k] ?></div>
          <?php foreach ($items as $c): ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between small"><span><?= e($c['name']) ?></span><span><?= money((float)$c['total']) ?></span></div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-<?= $k === 'income' ? 'success' : 'danger' ?>" style="width:<?= $total > 0 ? round($c['total'] / $total * 100) : 0 ?>%"></div></div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm" id="list">
  <div class="card-header bg-white">
    <form class="row g-2 align-items-center d-print-none">
      <input type="hidden" name="fy" value="<?= $fy ?>">
      <div class="col-md-3"><input class="form-control form-control-sm" name="q" placeholder="ค้นหารายการ เลขที่ ผู้รับ/ผู้จ่าย" value="<?= e(get('q')) ?>"></div>
      <div class="col-md-2"><select class="form-select form-select-sm" name="kind"><option value="">รับและจ่าย</option>
        <?php foreach ($kinds as $k => $lbl): ?><option value="<?= $k ?>"<?= get('kind') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select form-select-sm" name="month"><option value="">ทุกเดือน</option>
        <?php foreach ([10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9] as $m): ?><option value="<?= $m ?>"<?= (int)get('month') === $m ? ' selected' : '' ?>><?= $thMonths[$m] ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><select class="form-select form-select-sm" name="cat"><option value="">ทุกหมวด</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"<?= (int)get('cat') === (int)$c['id'] ? ' selected' : '' ?>><?= e($kinds[$c['kind']] . ' · ' . $c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button></div>
      <div class="col-md-1"><a class="btn btn-sm btn-outline-success w-100" href="?<?= e(http_build_query(array_merge($_GET, ['fy' => $fy, 'export' => 'csv']))) ?>" title="ส่งออก CSV"><i class="bi bi-filetype-csv"></i></a></div>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover table-sm mb-0">
    <thead class="table-light"><tr><th>วันที่</th><th>รายการ</th><th>หมวด</th><th class="text-end">รายรับ</th><th class="text-end">รายจ่าย</th><th>ผู้บันทึก</th><th class="d-print-none"></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">ยังไม่มีรายการ</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="text-nowrap small"><?= thai_date($r['entry_date']) ?></td>
        <td><?= e($r['description']) ?>
          <div class="small text-muted"><?= $r['ref_no'] ? 'เลขที่ ' . e($r['ref_no']) : '' ?><?= $r['counterparty'] ? ($r['ref_no'] ? ' · ' : '') . e($r['counterparty']) : '' ?>
            <?php if ($r['file_name']): ?><a class="d-print-none" href="<?= url('ledger-file.php?id=' . $r['id']) ?>" title="<?= e($r['file_original']) ?>"><i class="bi bi-paperclip"></i>หลักฐาน</a><?php endif; ?></div></td>
        <td class="small"><?= e($r['cat_name']) ?></td>
        <td class="text-end text-success text-nowrap"><?= $r['kind'] === 'income' ? money((float)$r['amount']) : '' ?></td>
        <td class="text-end text-danger text-nowrap"><?= $r['kind'] === 'expense' ? money((float)$r['amount']) : '' ?></td>
        <td class="small"><?= e($r['creator']) ?></td>
        <td class="text-end text-nowrap d-print-none">
          <a class="btn btn-sm btn-outline-secondary" href="<?= url('ledger-entry.php?id=' . $r['id']) ?>" title="<?= ledger_can_edit($perms, $r) ? 'แก้ไข' : 'ดูรายละเอียด' ?>"><i class="bi <?= ledger_can_edit($perms, $r) ? 'bi-pencil' : 'bi-eye' ?>"></i></a>
          <?php if (ledger_can_delete($perms, $r)): ?>
            <form method="post" class="d-inline" data-confirm="ลบรายการ '<?= e($r['description']) ?>' จำนวน <?= money((float)$r['amount']) ?> บาท?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($rows): ?>
      <tfoot class="table-light fw-semibold"><tr><td colspan="3">รวมตามตัวกรอง (<?= number_format($p['total']) ?> รายการ)</td><td class="text-end text-success"><?= money((float)$sum['inc']) ?></td><td class="text-end text-danger"><?= money((float)$sum['exp']) ?></td><td colspan="2"></td></tr></tfoot>
    <?php endif; ?>
  </table></div>
</div>
<div class="mt-3 d-print-none"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
