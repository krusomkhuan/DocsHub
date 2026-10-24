<?php
/**
 * ทะเบียนออกเลขหนังสือราชการ แยกตามแผนกวิชา
 * ใช้ร่วมกัน: book-numbers.php (หน้าบ้าน) และ admin/book-numbers.php (หลังบ้าน)
 * สมาชิกทั่วไปออกเลข/ดูได้เฉพาะแผนกของตนเอง เจ้าหน้าที่ทำได้ทุกแผนก และยกเลิกเลข/ส่งออก CSV ได้
 * ต้องกำหนด $selfUrl ก่อน include
 */
$staff = is_staff();
$u = current_user();
$kinds = book_kinds();

$allowedDepts = $staff ? departments() : array_filter(departments(), fn($d) => (int)$d['id'] === (int)$u['department_id']);

$issued = null;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'issue') {
        $deptId  = (int)post('department_id');
        $kind    = post('book_kind');
        $date    = post('book_date') ?: date('Y-m-d');
        $subject = mb_substr(post('subject'), 0, 255);
        if (!isset($allowedDepts[$deptId])) $errors[] = 'คุณไม่มีสิทธิ์ออกเลขของแผนกนี้';
        if (!isset($kinds[$kind])) $errors[] = 'กรุณาเลือกประเภทหนังสือ';
        if ($subject === '') $errors[] = 'กรุณากรอกเรื่อง';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'วันที่ไม่ถูกต้อง';
        if (!$errors) {
            $issued = issue_book_number($deptId, $kind, $date, $subject, mb_substr(post('to_whom'), 0, 255), mb_substr(post('note'), 0, 255));
            $_SESSION['last_issued'] = $issued['id'];
            redirect($selfUrl . '?issued=' . $issued['id']);
        }
    } elseif ($action === 'cancel' && $staff) {
        $bn = db_row('SELECT * FROM book_numbers WHERE id = ?', 'i', [(int)post('id')]);
        if ($bn) {
            db_exec('UPDATE book_numbers SET is_cancelled = 1 - is_cancelled WHERE id = ?', 'i', [$bn['id']]);
            log_activity('book_cancel', $bn['full_no'] . ($bn['is_cancelled'] ? ' (คืนสถานะ)' : ' (ยกเลิก)'));
            flash('success', ($bn['is_cancelled'] ? 'คืนสถานะ ' : 'ยกเลิกเลข ') . $bn['full_no'] . ' แล้ว');
        }
        redirect($selfUrl . '?' . http_build_query(array_diff_key($_GET, ['issued' => 1])));
    } elseif ($action === 'set_start' && $staff) {
        // กำหนดเลขเริ่มต้น (เจ้าหน้าที่/ผู้ดูแลระบบ)
        $deptId = (int)post('department_id');
        $kind = post('book_kind');
        $yearBe = (int)post('year_be');
        $start = (int)post('start_no');
        if (!isset(departments()[$deptId]) || !isset($kinds[$kind]) || $yearBe < 2500 || $yearBe > 2700 || $start < 1 || $start > 99999) {
            flash('danger', 'ข้อมูลเลขเริ่มต้นไม่ถูกต้อง (เลขเริ่มต้นต้องอยู่ระหว่าง 1–99999)');
        } else {
            db_exec('INSERT INTO book_number_starts (department_id, book_kind, year_be, start_no, set_by) VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE start_no = VALUES(start_no), set_by = VALUES(set_by), set_at = NOW()', 'isiii', [$deptId, $kind, $yearBe, $start, $u['id']]);
            $next = book_next_number($deptId, $kind, $yearBe);
            $label = departments()[$deptId]['code'] . ' ' . $kinds[$kind] . ' ปี ' . $yearBe;
            log_activity('book_start', $label . ' เริ่มที่ ' . $start);
            flash($next > $start ? 'warning' : 'success', 'กำหนดเลขเริ่มต้น ' . $label . ' = ' . $start . ' แล้ว'
                . ($next > $start ? ' · แต่มีการออกเลขไปถึง ' . ($next - 1) . ' แล้ว เลขถัดไปจึงเป็น ' . $next . ' (ระบบไม่ออกเลขซ้ำ)' : ' · เลขถัดไปคือ ' . $next));
        }
        redirect($selfUrl . '?' . http_build_query(['year' => $yearBe] + array_diff_key($_GET, ['issued' => 1, 'year' => 1])) . '#starts');
    } elseif ($action === 'del_start' && $staff) {
        $deptId = (int)post('department_id');
        db_exec('DELETE FROM book_number_starts WHERE department_id = ? AND book_kind = ? AND year_be = ?', 'isi', [$deptId, post('book_kind'), (int)post('year_be')]);
        log_activity('book_start_delete', (departments()[$deptId]['code'] ?? '') . ' ' . post('book_kind') . ' ' . post('year_be'));
        flash('success', 'ยกเลิกการกำหนดเลขเริ่มต้นแล้ว (กลับไปนับต่อจากเลขล่าสุด หรือเริ่มที่ 1)');
        redirect($selfUrl . '?' . http_build_query(array_diff_key($_GET, ['issued' => 1])) . '#starts');
    }
}
if (get('issued') !== '' && (int)get('issued') === (int)($_SESSION['last_issued'] ?? 0)) {
    $issued = db_row('SELECT b.*, d.name AS dept_name FROM book_numbers b JOIN departments d ON d.id = b.department_id WHERE b.id = ?', 'i', [(int)get('issued')]);
}

// รายการทะเบียน
$fDept = (int)get('dept');
if (!$staff) $fDept = (int)$u['department_id'];
$fYear = (int)get('year') ?: current_year_be();
$where = 'b.year_be = ?';
$types = 'i';
$params = [$fYear];
if ($fDept) { $where .= ' AND b.department_id = ?'; $types .= 'i'; $params[] = $fDept; }
if (isset($kinds[get('kind')])) { $where .= ' AND b.book_kind = ?'; $types .= 's'; $params[] = get('kind'); }
if (get('q') !== '') {
    $where .= ' AND (b.full_no LIKE ? OR b.subject LIKE ? OR b.to_whom LIKE ?)';
    $like = '%' . get('q') . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$sqlList = "SELECT b.*, d.name AS dept_name, u.fullname AS creator FROM book_numbers b
            JOIN departments d ON d.id = b.department_id LEFT JOIN users u ON u.id = b.created_by
            WHERE $where ORDER BY b.year_be DESC, d.sort_order, b.book_kind, b.running_no DESC";

// ส่งออก CSV (เจ้าหน้าที่)
if ($staff && get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="book-numbers-' . $fYear . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['เลขที่', 'ประเภท', 'แผนก/ฝ่าย', 'ลงวันที่', 'เรื่อง', 'ถึง', 'หมายเหตุ', 'ผู้ออกเลข', 'สถานะ']);
    foreach (db_all($sqlList, $types, $params) as $r) {
        fputcsv($out, [$r['full_no'], $kinds[$r['book_kind']], $r['dept_name'], thai_date($r['book_date']), $r['subject'], $r['to_whom'], $r['note'], $r['creator'], $r['is_cancelled'] ? 'ยกเลิก' : '']);
    }
    exit;
}

$p = paginate((int)db_val("SELECT COUNT(*) FROM book_numbers b WHERE $where", $types, $params), 25);
$rows = db_all($sqlList . " LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$years = array_column(db_all('SELECT year_be FROM book_numbers UNION SELECT year_be FROM book_number_starts ORDER BY year_be DESC'), 'year_be');
if (!in_array(current_year_be(), $years)) array_unshift($years, current_year_be());

// เลขเริ่มต้นที่กำหนดไว้ของปีที่เลือก (แสดงให้เจ้าหน้าที่)
$starts = $staff ? db_all('SELECT s.*, d.name AS dept_name, d.code, u.fullname AS setter,
                                  (SELECT MAX(b.running_no) FROM book_numbers b WHERE b.department_id = s.department_id AND b.book_kind = s.book_kind AND b.year_be = s.year_be) AS last_no
                           FROM book_number_starts s JOIN departments d ON d.id = s.department_id LEFT JOIN users u ON u.id = s.set_by
                           WHERE s.year_be = ? ORDER BY d.sort_order, s.book_kind', 'i', [$fYear]) : [];
?>
<div class="row g-4">
  <div class="col-lg-4">
    <?php if ($issued): ?>
      <div class="card shadow-sm border-success border-2 mb-3">
        <div class="card-body text-center">
          <div class="text-success small"><i class="bi bi-check-circle-fill"></i> ออกเลขสำเร็จ</div>
          <div class="book-no-display text-success my-1"><?= e($issued['full_no']) ?></div>
          <div class="small text-muted"><?= e($kinds[$issued['book_kind']]) ?> · <?= e($issued['dept_name']) ?><br><?= thai_date($issued['book_date'], false) ?></div>
          <div class="small mt-2"><?= e($issued['subject']) ?></div>
          <button class="btn btn-outline-success btn-sm mt-2" type="button" onclick="navigator.clipboard.writeText('<?= e($issued['full_no']) ?>');this.textContent='คัดลอกแล้ว'"><i class="bi bi-clipboard"></i> คัดลอกเลข</button>
        </div>
      </div>
    <?php endif; ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-1"></i>ขอเลขหนังสือใหม่</div>
      <div class="card-body">
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
        <?php if (!$allowedDepts): ?>
          <div class="alert alert-warning small mb-0">บัญชีของคุณยังไม่ได้ระบุแผนกวิชา กรุณาติดต่อผู้ดูแลระบบ</div>
        <?php else: ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="issue">
          <div class="mb-2">
            <label class="form-label small">แผนกวิชา / ฝ่าย</label>
            <select class="form-select" name="department_id" required>
              <?php foreach ($allowedDepts as $dp): ?>
                <option value="<?= $dp['id'] ?>"<?= (int)post('department_id', $u['department_id']) === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?> (<?= e($dp['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">ประเภท</label>
              <select class="form-select" name="book_kind">
                <?php foreach ($kinds as $k => $lbl): ?><option value="<?= $k ?>"<?= post('book_kind') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small">ลงวันที่</label>
              <input type="date" class="form-control" name="book_date" value="<?= e(post('book_date', date('Y-m-d'))) ?>" required>
            </div>
          </div>
          <div class="mb-2"><label class="form-label small">เรื่อง *</label><input class="form-control" name="subject" required maxlength="255" value="<?= e(post('subject')) ?>"></div>
          <div class="mb-2"><label class="form-label small">เรียน / ถึง</label><input class="form-control" name="to_whom" maxlength="255" list="toWhomList" autocomplete="off" value="<?= e(post('to_whom')) ?>">
            <datalist id="toWhomList"><option value="ผู้อำนวยการ"><option value="ประธาน อวท."></datalist></div>
          <div class="mb-3"><label class="form-label small">หมายเหตุ</label><input class="form-control" name="note" maxlength="255" value="<?= e(post('note')) ?>"></div>
          <button class="btn btn-primary w-100"><i class="bi bi-123 me-1"></i>ออกเลข</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($staff): ?>
    <div class="card shadow-sm mt-3" id="starts">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-1"></i>กำหนดเลขเริ่มต้น</div>
      <div class="card-body">
        <p class="small text-muted mb-2">ใช้เมื่อเริ่มใช้ระบบกลางปี หรือต้องการต่อเลขจากทะเบียนเดิม เช่น ทะเบียนกระดาษใช้ถึงเลข 57 ให้ตั้งเริ่มที่ 58 · ระบบจะไม่ออกเลขย้อนหลังหรือซ้ำกับเลขที่ออกไปแล้ว</p>
        <form method="post" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="set_start">
          <div class="col-12">
            <select class="form-select form-select-sm" name="department_id" required>
              <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= $fDept === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?> (<?= e($dp['code']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <select class="form-select form-select-sm" name="book_kind">
              <?php foreach ($kinds as $k => $lbl): ?><option value="<?= $k ?>"><?= $lbl ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6"><input type="number" class="form-control form-control-sm" name="year_be" min="2500" max="2700" value="<?= $fYear ?>" title="ปี พ.ศ." required></div>
          <div class="col-6"><input type="number" class="form-control form-control-sm" name="start_no" min="1" max="99999" placeholder="เริ่มที่เลข" required></div>
          <div class="col-6"><button class="btn btn-outline-primary btn-sm w-100">บันทึก</button></div>
        </form>
        <?php if ($starts): ?>
          <div class="table-responsive mt-3"><table class="table table-sm small mb-0">
            <thead class="table-light"><tr><th>แผนก / ประเภท</th><th class="text-end">เริ่มที่</th><th class="text-end">ถัดไป</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($starts as $s): $next = max((int)$s['last_no'] + 1, (int)$s['start_no']); ?>
                <tr>
                  <td><?= e($s['code']) ?> · <?= e($kinds[$s['book_kind']]) ?><div class="text-muted">ปี <?= (int)$s['year_be'] ?> · <?= e($s['setter']) ?></div></td>
                  <td class="text-end"><?= (int)$s['start_no'] ?></td>
                  <td class="text-end fw-semibold<?= $next > (int)$s['start_no'] ? ' text-warning-emphasis' : ' text-success' ?>" title="<?= $s['last_no'] ? 'ออกเลขล่าสุดแล้วถึง ' . (int)$s['last_no'] : 'ยังไม่ได้ออกเลข' ?>"><?= $next ?></td>
                  <td class="text-end">
                    <form method="post" data-confirm="ยกเลิกเลขเริ่มต้นของ <?= e($s['code'] . ' ' . $kinds[$s['book_kind']]) ?> ปี <?= (int)$s['year_be'] ?>?">
                      <?= csrf_field() ?><input type="hidden" name="action" value="del_start"><input type="hidden" name="department_id" value="<?= $s['department_id'] ?>"><input type="hidden" name="book_kind" value="<?= e($s['book_kind']) ?>"><input type="hidden" name="year_be" value="<?= (int)$s['year_be'] ?>">
                      <button class="btn btn-sm btn-link text-danger p-0" title="ยกเลิก"><i class="bi bi-x-circle"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php else: ?>
          <div class="small text-muted mt-2">ปี <?= $fYear ?> ยังไม่ได้กำหนดเลขเริ่มต้น (ทุกแผนกเริ่มที่ 1)</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white">
        <form class="row g-2 align-items-center">
          <div class="col-auto fw-semibold me-auto"><i class="bi bi-list-ol me-1"></i>ทะเบียนเลขหนังสือ</div>
          <?php if ($staff): ?>
          <div class="col-auto">
            <select class="form-select form-select-sm" name="dept">
              <option value="">ทุกแผนก</option>
              <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= $fDept === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-auto">
            <select class="form-select form-select-sm" name="kind">
              <option value="">ทุกประเภท</option>
              <?php foreach ($kinds as $k => $lbl): ?><option value="<?= $k ?>"<?= get('kind') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <select class="form-select form-select-sm" name="year">
              <?php foreach ($years as $y): ?><option<?= (int)$y === $fYear ? ' selected' : '' ?>><?= (int)$y ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto"><input class="form-control form-control-sm" name="q" placeholder="ค้นหา" value="<?= e(get('q')) ?>"></div>
          <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button></div>
          <?php if ($staff): ?>
            <div class="col-auto"><a class="btn btn-sm btn-outline-success" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-filetype-csv"></i> CSV</a></div>
          <?php endif; ?>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>เลขที่</th><th>ลงวันที่</th><th>เรื่อง</th><th>แผนก</th><th>ผู้ออกเลข</th><?php if ($staff): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีการออกเลข</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
              <tr class="<?= $r['is_cancelled'] ? 'text-decoration-line-through text-muted' : '' ?>">
                <td class="text-nowrap fw-medium"><?= e($r['full_no']) ?><div class="small text-muted fw-normal"><?= e($kinds[$r['book_kind']]) ?></div></td>
                <td class="text-nowrap small"><?= thai_date($r['book_date']) ?></td>
                <td><?= e($r['subject']) ?><?= $r['to_whom'] ? '<div class="small text-muted">เรียน ' . e($r['to_whom']) . '</div>' : '' ?></td>
                <td class="small"><?= e($r['dept_name']) ?></td>
                <td class="small"><?= e($r['creator']) ?></td>
                <?php if ($staff): ?>
                <td class="text-end">
                  <form method="post" data-confirm="<?= $r['is_cancelled'] ? 'คืนสถานะเลขนี้?' : 'ยกเลิกเลขนี้? (เลขจะไม่ถูกนำกลับมาใช้ใหม่)' ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm btn-link p-0 <?= $r['is_cancelled'] ? 'text-success' : 'text-danger' ?>" title="<?= $r['is_cancelled'] ? 'คืนสถานะ' : 'ยกเลิกเลข' ?>"><i class="bi <?= $r['is_cancelled'] ? 'bi-arrow-counterclockwise' : 'bi-x-circle' ?>"></i></button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="mt-3"><?= pagination_links($p) ?></div>
  </div>
</div>
