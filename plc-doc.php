<?php
/**
 * แสดง / พิมพ์ / บันทึก PDF / ดาวน์โหลด Word ของเอกสาร PLC
 * ?doc=group-memo|group-request|group-announce|group-all|hours &id=กลุ่ม
 * ?doc=problem-proposal|problem-memo|problem-finding|problem-solution|problem-plan|problem-photos|problem-all &id=ประเด็นปัญหา
 * ?doc=meeting|observation &id=  (หน้านี้รับการลงนามของผู้บันทึก/ผู้สังเกตด้วย)
 * &format=word = ไฟล์ .doc (MHTML ฝังลายมือชื่อและภาพ เปิดด้วย Microsoft Word)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/plc_docs.php';
require_plc_access();

$doc = (string)get('doc');
$id = (int)get('id');
$g = $p = $row = null;
$entity = null;

if (in_array($doc, ['group-memo', 'group-request', 'group-announce', 'group-all', 'hours'], true)) {
    $g = plc_group_get($id);
    $entity = 'group';
    $row = $g;
} elseif (str_starts_with($doc, 'problem-')) {
    $p = db_row('SELECT * FROM plc_problems WHERE id = ?', 'i', [$id]);
    $g = $p ? plc_group_get((int)$p['group_id']) : null;
    $entity = 'problem';
    $row = $p;
} elseif ($doc === 'meeting') {
    $row = db_row('SELECT * FROM plc_meetings WHERE id = ?', 'i', [$id]);
    $g = $row ? plc_group_get((int)$row['group_id']) : null;
    $p = $row && $row['problem_id'] ? db_row('SELECT * FROM plc_problems WHERE id = ?', 'i', [$row['problem_id']]) : null;
    $entity = 'meeting';
} elseif ($doc === 'observation') {
    $row = db_row('SELECT * FROM plc_observations WHERE id = ?', 'i', [$id]);
    $p = $row ? db_row('SELECT * FROM plc_problems WHERE id = ?', 'i', [$row['problem_id']]) : null;
    $g = $p ? plc_group_get((int)$p['group_id']) : null;
    $entity = 'observation';
}
if (!$g || !$row || !plc_can_view_group($g)) {
    flash('danger', 'ไม่พบเอกสาร หรือคุณไม่มีสิทธิ์ดู');
    redirect('plc.php');
}
$self = 'plc-doc.php?doc=' . urlencode($doc) . '&id=' . $id;

// ลงนามบันทึกการประชุม / แบบสังเกต (กลุ่มและประเด็นปัญหาลงนามที่หน้าของตน)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($entity, ['meeting', 'observation'], true)) {
    csrf_check();
    try {
        flash('success', plc_handle_workflow($entity, $row, $g, plc_can_contribute($g)));
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect($self);
}

/** สร้าง HTML ของเอกสารตามชนิด */
function plc_render_doc(string $doc, string $entity, array $g, ?array $p, array $row): array
{
    $members = plc_members((int)$g['id']);
    if ($entity === 'group') {
        $slots = plc_slots('group', $g, $g);
        $sigs = plc_signatures('group', (int)$g['id']);
        $parts = [
            'group-memo'     => fn() => plc_doc_group_memo($g, $members, $slots, $sigs),
            'group-request'  => fn() => plc_doc_group_request($g, $members, $slots, $sigs),
            'group-announce' => fn() => plc_doc_group_announce($g, $members, $slots, $sigs),
        ];
        if ($doc === 'hours') return ['สรุปชั่วโมง PLC', plc_doc_hours($g)];
        if ($doc === 'group-all') {
            $photos = db_all('SELECT * FROM plc_photos WHERE group_id = ? AND problem_id IS NULL ORDER BY sort_order, id', 'i', [$g['id']]);
            return ['ชุดเอกสารจัดตั้งกลุ่ม PLC', implode('', array_map(fn($f) => $f(), $parts)) . plc_doc_photos($g, null, $photos)];
        }
        $titles = ['group-memo' => 'บันทึกข้อความขออนุญาตจัดตั้งกลุ่ม PLC', 'group-request' => 'แบบคำร้องขอจัดตั้งกลุ่ม PLC', 'group-announce' => 'ประกาศการจัดตั้งกลุ่ม PLC'];
        return [$titles[$doc], $parts[$doc]()];
    }
    if ($entity === 'problem') {
        $slots = plc_slots('problem', $p, $g);
        $sigs = plc_signatures('problem', (int)$p['id']);
        $d = plc_problem_data($p, $g);
        $photos = db_all('SELECT * FROM plc_photos WHERE problem_id = ? ORDER BY sort_order, id', 'i', [$p['id']]);
        $parts = [
            'problem-proposal' => ['แบบคำร้องขอเสนอประเด็นปัญหา', fn() => plc_doc_problem_proposal($p, $g, $d, $slots, $sigs)],
            'problem-memo'     => ['บันทึกข้อความส่งแบบบันทึก PLC', fn() => plc_doc_problem_memo($p, $g, $d, $slots, $sigs)],
            'problem-finding'  => ['แบบบันทึกการค้นหาปัญหา', fn() => plc_doc_problem_finding($p, $g, $d, $slots, $sigs)],
            'problem-solution' => ['แบบบันทึกแนวทางแก้ไขปัญหา', fn() => plc_doc_problem_solution($p, $g, $d, $slots, $sigs)],
            'problem-plan'     => ['แผนปฏิบัติการ PLC-AP', fn() => plc_doc_problem_plan($p, $g, $d, $slots, $sigs)],
            'problem-photos'   => ['รูปภาพประกอบ PLC', fn() => plc_doc_photos($g, $p, $photos) ?: plc_page('<p>ยังไม่มีภาพประกอบ</p>')],
        ];
        if ($doc === 'problem-all') {
            $html = '';
            foreach (['problem-proposal', 'problem-memo', 'problem-finding', 'problem-solution', 'problem-plan'] as $k) $html .= $parts[$k][1]();
            foreach (db_all('SELECT * FROM plc_observations WHERE problem_id = ? ORDER BY observe_date, id', 'i', [$p['id']]) as $o) {
                $html .= plc_doc_observation($o, $g, $p, plc_slots('observation', $o, $g), plc_signatures('observation', (int)$o['id']));
            }
            foreach (db_all("SELECT * FROM plc_meetings WHERE problem_id = ? AND kind IN ('teach','reflect','summary') ORDER BY meeting_date, id", 'i', [$p['id']]) as $mt) {
                $html .= plc_doc_meeting($mt, $g, $p, plc_slots('meeting', $mt, $g), plc_signatures('meeting', (int)$mt['id']));
            }
            return ['ชุดเอกสาร PLC ' . $p['theme'], $html . plc_doc_photos($g, $p, $photos)];
        }
        if (!isset($parts[$doc])) return ['', ''];
        return [$parts[$doc][0], $parts[$doc][1]()];
    }
    if ($entity === 'meeting') {
        return [$row['kind'] === 'reflect' ? 'แบบบันทึกการสะท้อนผล' : 'บันทึกการประชุม PLC', plc_doc_meeting($row, $g, $p, plc_slots('meeting', $row, $g), plc_signatures('meeting', (int)$row['id']))];
    }
    return ['แบบสังเกตการสอน', plc_doc_observation($row, $g, $p, plc_slots('observation', $row, $g), plc_signatures('observation', (int)$row['id']))];
}

// ไฟล์ Word: MHTML (HTML + ภาพฝังในไฟล์เดียว) นามสกุล .doc
if (get('format') === 'word') {
    $PLC_DOC_MODE = 'word';
    [$title, $html] = plc_render_doc($doc, $entity, $g, $p, $row);
    if ($html === '') redirect($self);
    $page = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
        . '<head><meta charset="utf-8"><title>' . e($title) . '</title>'
        . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->'
        . '<style>@page WordSection1 { size: 21cm 29.7cm; margin: 2cm 2cm 2cm 3cm; } div.WordSection1 { page: WordSection1; }'
        . plc_doc_css() . ' .plc-page { width: auto; min-height: 0; padding: 0; margin: 0; box-shadow: none; }'
        . ' body, p, td, th, div, li { font-family: "TH Sarabun New", "TH SarabunPSK"; font-size: 16pt; }'
        . ' p { margin: 0 0 2pt; } .plc-title { font-size: 18pt; } .plc-memo-title { font-size: 29pt; } .plc-sig-date, .plc-note, .plc-caption { font-size: 12pt; }'
        . ' .plc-memo-line { border-top: 1px solid #000; height: 1px; } .plc-signs { margin-top: 12pt; }</style></head>'
        . '<body><div class="WordSection1 plc-doc">' . $html . '</div></body></html>';
    $boundary = '----=_DocsHubPLC_' . bin2hex(random_bytes(8));
    $base = 'file:///C:/docshub-plc/';
    $out = "MIME-Version: 1.0\r\nContent-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $out .= "--$boundary\r\nContent-Location: {$base}document.htm\r\nContent-Transfer-Encoding: base64\r\nContent-Type: text/html; charset=\"utf-8\"\r\n\r\n";
    // ภาพอ้างอิงด้วยชื่อไฟล์ใน MHTML (img1.png ...) ให้ชี้ไปที่ Content-Location เดียวกัน
    foreach (array_keys($PLC_DOC_IMAGES) as $name) $page = str_replace('src="' . $name . '"', 'src="' . $base . $name . '"', $page);
    $out .= chunk_split(base64_encode($page), 76, "\r\n");
    foreach ($PLC_DOC_IMAGES as $name => [$path, $mime]) {
        $out .= "\r\n--$boundary\r\nContent-Location: {$base}{$name}\r\nContent-Transfer-Encoding: base64\r\nContent-Type: $mime\r\n\r\n";
        $out .= chunk_split(base64_encode((string)file_get_contents($path)), 76, "\r\n");
    }
    $out .= "\r\n--$boundary--\r\n";
    $file = preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', mb_substr($title, 0, 80)) . '.doc';
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="plc-' . $doc . '-' . $id . '.doc"; filename*=UTF-8\'\'' . rawurlencode($file));
    header('Content-Length: ' . strlen($out));
    log_activity('plc_word', $doc . ' #' . $id);
    echo $out;
    exit;
}

[$title, $html] = plc_render_doc($doc, $entity, $g, $p, $row);
if ($html === '') redirect('plc.php');
$back = $entity === 'group' ? 'plc-group.php?id=' . $g['id'] : ($p ? 'plc-problem.php?id=' . $p['id'] : 'plc-group.php?id=' . $g['id']);
$canEdit = $entity === 'meeting' ? plc_can_contribute($g) : ($entity === 'observation' ? (is_staff() || (int)$row['observer_id'] === (int)current_user()['id']) : false);
$missingSig = array_filter(plc_slots($entity, $row, $g), fn($s) => $s['user_id'] && empty(plc_user_brief($s['user_id'])['signature']));

$pageTitle = $title;
$activeNav = 'plc';
require __DIR__ . '/includes/header.php';
?>
<style><?= plc_doc_css() ?>
.plc-viewer { background: #e5e7eb; border-radius: .5rem; padding: 1.5rem .5rem; overflow-x: auto; }
@media print {
  @page { size: A4; margin: 0; }
  body { background: #fff !important; }
  .plc-viewer { background: none; padding: 0; overflow: visible; }
  .plc-page { box-shadow: none; margin: 0; page-break-after: always; break-after: page; min-height: 0; }
  .plc-page:last-child { page-break-after: auto; break-after: auto; }
  .plc-sig-date { display: none; }
}
@media (max-width: 991.98px) { .plc-page { zoom: .55; } }
</style>
<div class="d-print-none">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('plc.php') ?>">PLC</a></li>
    <li class="breadcrumb-item"><a href="<?= url('plc-group.php?id=' . $g['id']) ?>"><?= e(plc_group_title($g)) ?> <?= e(plc_term_text($g)) ?></a></li>
    <?php if ($p): ?><li class="breadcrumb-item"><a href="<?= url('plc-problem.php?id=' . $p['id']) ?>">ประเด็นปัญหา</a></li><?php endif; ?>
    <li class="breadcrumb-item active"><?= e($title) ?></li>
  </ol></nav>
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= e($title) ?></h5>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?= url($back) ?>"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
      <?php if ($canEdit): ?><a class="btn btn-outline-primary btn-sm" href="<?= url(($entity === 'meeting' ? 'plc-meeting-form.php?id=' : 'plc-observation-form.php?id=') . $row['id']) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</a><?php endif; ?>
      <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์ / บันทึก PDF</button>
      <a class="btn btn-outline-primary btn-sm" href="<?= url($self . '&format=word') ?>"><i class="bi bi-file-earmark-word me-1"></i>ดาวน์โหลด Word</a>
    </div>
  </div>
  <?php if ($missingSig): ?>
    <div class="alert alert-light border small py-2"><i class="bi bi-info-circle me-1"></i>ผู้ลงนามที่ยังไม่ได้เพิ่มลายมือชื่อในระบบ: <?= e(implode(', ', array_map(fn($s) => $s['name'], $missingSig))) ?> (ช่องลงชื่อจะเป็นเส้นประให้เซ็นบนกระดาษ)</div>
  <?php endif; ?>
  <div class="small text-muted mb-2">พิมพ์เป็น PDF: กด "พิมพ์ / บันทึก PDF" แล้วเลือกเครื่องพิมพ์ "Save as PDF" · ขนาด A4 · ปิดตัวเลือก "หัวกระดาษและท้ายกระดาษ" · ไฟล์ Word ใช้ฟอนต์ TH Sarabun New</div>
</div>
<?php if (in_array($entity, ['meeting', 'observation'], true)): ?>
  <div class="row"><div class="col-lg-5"><?php $wfEntity = $entity; $wfRow = $row; $wfGroup = $g; $wfCanEdit = $canEdit; require __DIR__ . '/includes/plc_workflow.php'; ?></div></div>
<?php endif; ?>
<div class="plc-viewer"><div class="plc-doc"><?= $html ?></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
