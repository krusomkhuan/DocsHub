<?php
/**
 * เอกสาร PLC สำหรับพิมพ์ / บันทึก PDF / ดาวน์โหลด Word
 * ทุกฟังก์ชันคืน HTML ของเอกสาร (หนึ่งหรือหลายหน้า .plc-page) ใช้ร่วมกันทั้งหน้าพิมพ์และไฟล์ Word
 * ภาพ (ลายมือชื่อ ภาพประกอบ) ผ่าน plc_doc_img() ซึ่งเปลี่ยนที่มาของภาพตามโหมด (print / word)
 */

$PLC_DOC_MODE = 'print';
$PLC_DOC_IMAGES = [];   // โหมด word: [ชื่อไฟล์ใน MHTML => [path, mime]]

/** ที่มาของภาพ: โหมดพิมพ์ใช้ URL ของระบบ · โหมด Word ฝังไฟล์ใน MHTML */
function plc_doc_img(string $url, ?string $path, string $mime = 'image/png'): string
{
    global $PLC_DOC_MODE, $PLC_DOC_IMAGES;
    if ($PLC_DOC_MODE !== 'word') return $url;
    if (!$path || !is_file($path)) return '';
    $name = 'img' . (count($PLC_DOC_IMAGES) + 1) . ($mime === 'image/jpeg' ? '.jpg' : '.png');
    $PLC_DOC_IMAGES[$name] = [$path, $mime];
    return $name;
}

function plc_n($s): string
{
    return e(plc_thai_digits($s));
}

/** บล็อกลงชื่อ: แสดงลายมือชื่อเมื่อช่องนั้นลงนามในระบบแล้ว */
function plc_sign_block(?array $sig, string $name, string $position = '', string $prefix = 'ลงชื่อ', string $suffix = ''): string
{
    $img = '';
    if ($sig && $sig['signature'] && ($path = plc_signature_path($sig['signature']))) {
        $mime = str_ends_with($sig['signature'], '.jpg') ? 'image/jpeg' : 'image/png';
        $src = plc_doc_img(url('plc-file.php?sig=' . (int)$sig['user_id'] . '&v=' . substr(md5($sig['signature']), 0, 8)), $path, $mime);
        // กำหนดทั้งกว้างและสูง (Word ไม่ย่อภาพตามความสูงอย่างเดียว)
        $dim = @getimagesize($path);
        $h = 45;
        $w = $dim && $dim[1] ? (int)round($dim[0] * $h / $dim[1]) : 120;
        if ($w > 200) { $h = (int)round($h * 200 / $w); $w = 200; }
        if ($src) $img = '<img class="plc-sig" src="' . e($src) . '" width="' . $w . '" height="' . $h . '" style="width:' . $w . 'px;height:' . $h . 'px" alt="">';
    }
    $line = $img ?: '.............................................';
    $html = '<div class="plc-sign"><div>' . e($prefix) . ' ' . $line . ' ' . e($suffix) . '</div>';
    $html .= '<div>(' . e($name !== '' ? $name : '.............................................') . ')</div>';
    if ($position !== '') $html .= '<div>' . nl2br(plc_n($position)) . '</div>';
    if ($sig) $html .= '<div class="plc-sig-date">ลงนามในระบบ ' . plc_n(thai_date($sig['signed_at'], true, true)) . '</div>';
    return $html . '</div>';
}

/** แถวช่องลงชื่อ (ใช้ตารางเพื่อให้ Word จัดวางได้): 1 ช่อง = ชิดขวา · 2 ช่อง = ซ้าย-ขวา */
function plc_signs(string ...$blocks): string
{
    if (count($blocks) === 1) {
        return '<table class="plc-signs" align="right"><tr><td>' . $blocks[0] . '</td></tr></table><div style="clear:both"></div>';
    }
    return '<table class="plc-signs plc-signs-2"><tr><td>' . implode('</td><td>', $blocks) . '</td></tr></table>';
}

function plc_slot_block(array $slots, array $sigs, string $key, ?string $position = null, string $suffix = ''): string
{
    $s = $slots[$key];
    return plc_sign_block($sigs[$key] ?? null, (string)$s['name'], $position ?? (string)$s['position'], 'ลงชื่อ', $suffix);
}

/** ความเห็นผู้บังคับบัญชา (จากการอนุมัติในระบบ หรือเส้นประให้เขียน) */
function plc_opinion(array $sigs, string $key, string $label, string $default = 'เห็นควรอนุมัติ'): string
{
    $text = isset($sigs[$key]) ? ($sigs[$key]['comment'] ?: $default) : '';
    return '<p class="plc-opinion">' . e($label) . ' ' . ($text !== '' ? '<u>' . plc_n($text) . '</u>' : str_repeat('.', 90)) . '</p>';
}

function plc_page(string $body, string $class = ''): string
{
    global $PLC_DOC_MODE;
    static $n = 0;
    // Word ขึ้นหน้าใหม่ด้วย <br> แบบ page-break (ไม่สนใจ page-break ของ div)
    $break = $PLC_DOC_MODE === 'word' && $n++ > 0 ? '<br clear="all" style="page-break-before:always">' : '';
    return $break . '<div class="plc-page ' . $class . '">' . $body . '</div>';
}

function plc_memo_head(array $g, ?string $no, ?string $date, string $subject, string $to): string
{
    return '<div class="plc-memo-title">บันทึกข้อความ</div>'
        . '<table class="plc-memo"><tr><td colspan="2"><b>ส่วนราชการ</b> ' . e(plc_group_fullname($g)) . '</td></tr>'
        . '<tr><td><b>ที่</b> ' . plc_n($no ?: '..............................') . '</td><td><b>วันที่</b> ' . plc_date($date) . '</td></tr>'
        . '<tr><td colspan="2"><b>เรื่อง</b> ' . plc_n($subject) . '</td></tr></table>'
        . '<hr class="plc-memo-line"><p><b>เรียน</b> ' . e($to) . '</p>';
}

/** รายชื่อสมาชิกแบบคำร้อง/ประกาศ (๒.๑ นาย... ประธานกรรมการ) */
function plc_member_list(array $members, bool $experts): string
{
    $roles = plc_committee_roles();
    $rows = '';
    $i = 0;
    $prefix = $experts ? '๓.' : '๒.';
    foreach ($members as $m) {
        if (($m['committee_role'] === 'expert') !== $experts) continue;
        $i++;
        $role = $experts ? ($m['position_title'] ?: $roles['expert']) : $roles[$m['committee_role']];
        $rows .= '<tr><td class="plc-num">' . $prefix . plc_thai_digits($i) . '</td><td>' . e($m['display_name']) . '</td><td>' . e($role) . '</td></tr>';
    }
    return $rows ? '<table class="plc-list">' . $rows . '</table>' : '<p class="plc-indent">-</p>';
}

/** รายชื่อผู้เข้าร่วม (แบบบันทึก): ผู้เชี่ยวชาญ/ตำแหน่ง หรือ สมาชิกกลุ่ม PLC */
function plc_attendee_list(array $members): string
{
    $rows = '';
    foreach ($members as $m) {
        $role = $m['committee_role'] === 'expert'
            ? 'ผู้เชี่ยวชาญประจำกลุ่ม' . ($m['position_title'] ? ' / ' . $m['position_title'] : '')
            : 'สมาชิกกลุ่ม PLC';
        $rows .= '<tr><td>' . e($m['display_name']) . '</td><td>' . e($role) . '</td></tr>';
    }
    return '<table class="plc-list plc-indent">' . $rows . '</table>';
}

function plc_meeting_attendees(array $meeting, array $members): array
{
    $ids = array_map('intval', array_column(db_all('SELECT member_id FROM plc_attendees WHERE meeting_id = ?', 'i', [$meeting['id']]), 'member_id'));
    $list = array_values(array_filter($members, fn($m) => in_array((int)$m['id'], $ids, true)));
    usort($list, fn($a, $b) => ($b['committee_role'] === 'expert') <=> ($a['committee_role'] === 'expert')); // ผู้เชี่ยวชาญขึ้นก่อนตามแบบฟอร์ม
    return $list;
}

function plc_meeting_header(?array $mt, array $g, int $count): string
{
    $place = $mt['place'] ?? $g['meeting_place'] ?? '';
    return '<p>วันที่ ' . plc_date($mt['meeting_date'] ?? null) . ' สถานที่ ' . e($place ?: '..............................') . ' ' . e(setting('college_name')) . '</p>'
        . '<p>เวลาเริ่ม ' . plc_time($mt['start_time'] ?? null) . ' เวลาสิ้นสุด ' . plc_time($mt['end_time'] ?? null) . '</p>'
        . '<p>จำนวนผู้เข้าร่วม ' . plc_n($count) . ' คน</p>'
        . '<p><b>รายชื่อสมาชิกกลุ่ม PLC (Buddy Teacher)/ครูรุ่นพี่ (Senior Teacher)/ผู้อำนวยการ/รองผู้อำนวยการ/ผู้เชี่ยวชาญ (Expert) ปราชญ์ท้องถิ่น (ถ้ามี)</b></p>';
}

/* =========================== เอกสารของกลุ่ม =========================== */

function plc_doc_group_memo(array $g, array $members, array $slots, array $sigs): string
{
    $sched = $g['meeting_schedule'] ?: '....................';
    $body = plc_memo_head($g, $g['memo_no'], $g['memo_date'], 'ขออนุญาตจัดตั้งกลุ่มและประชุมการดำเนินการขับเคลื่อนกระบวนการ PLC ชุมชนการเรียนรู้ทางวิชาชีพ', 'ผู้อำนวยการ' . setting('college_name'));
    $body .= '<table class="plc-attach"><tr><td>สิ่งที่ส่งมาด้วย</td><td>๑. แบบคำร้องขอจัดตั้งกลุ่มชุมชนการเรียนรู้ทางวิชาชีพ (PLC)</td><td>จำนวน ๑ ฉบับ</td></tr>'
        . '<tr><td></td><td>๒. แบบคำร้องขอเสนอประเด็นปัญหา (PLC)</td><td>จำนวน ๑ ฉบับ</td></tr>'
        . '<tr><td></td><td>๓. ประกาศ' . e(setting('college_name')) . '</td><td>จำนวน ๑ ฉบับ</td></tr></table>';
    $body .= '<p class="plc-para">ตามที่' . e(setting('college_name')) . ' มีนโยบายขับเคลื่อนกระบวนการชุมชนการเรียนรู้ PLC (Professional Learning Community) ซึ่งเป็นกระบวนการเปลี่ยนแปลงโดยเรียนรู้จากการปฏิบัติงานของกลุ่มบุคคลที่มารวมตัวกันเพื่อทำงานร่วมกันและสนับสนุนซึ่งกันและกัน โดยมีวัตถุประสงค์เพื่อพัฒนาการเรียนรู้ของผู้เรียนร่วมกัน วางเป้าหมายการเรียนรู้ของผู้เรียน และตรวจสอบสะท้อนผลการปฏิบัติงานทั้งในส่วนบุคคลและผลที่เกิดขึ้นโดยรวม ผ่านกระบวนการแลกเปลี่ยนเรียนรู้ การวิพากษ์วิจารณ์ การทำงานร่วมกัน การร่วมมือรวมพลัง โดยมุ่งเน้นและส่งเสริมกระบวนการเรียนรู้อย่างเป็นองค์รวม</p>';
    $body .= '<p class="plc-para">ดังนั้น ' . e(plc_group_fullname($g)) . ' จึงขออนุญาตจัดตั้งกลุ่มการขับเคลื่อนกระบวนการชุมชนการเรียนรู้ PLC และขออนุญาตประชุมกลุ่ม ' . plc_n($sched) . '</p>';
    $body .= '<p class="plc-para">จึงเรียนมาเพื่อโปรดพิจารณา</p>';
    $same = $slots['chair']['user_id'] && $slots['chair']['user_id'] === $slots['head']['user_id'];
    $body .= ($same ? plc_signs(plc_slot_block($slots, $sigs, 'chair', 'ประธานกลุ่ม PLC ' . plc_group_title($g)))
        : plc_signs(plc_slot_block($slots, $sigs, 'chair', 'ประธานกลุ่ม PLC ' . plc_group_title($g)), plc_slot_block($slots, $sigs, 'head')));
    $body .= '<div class="plc-approve"><div>' . (isset($sigs['director']) ? '<u>' . e($sigs['director']['comment'] ?: 'อนุญาต') . '</u>' : '( ) อนุญาต &nbsp; ( ) ไม่อนุญาต') . '</div>'
        . plc_slot_block($slots, $sigs, 'director', $slots['director']['position'] . setting('college_name')) . '</div>';
    return plc_page($body);
}

function plc_doc_group_request(array $g, array $members, array $slots, array $sigs): string
{
    $college = setting('college_name');
    $chair = $slots['chair'];
    $body = '<div class="plc-title">แบบคำร้องขอจัดตั้งกลุ่มชุมชนการเรียนรู้ทางวิชาชีพ (PLC)<br>' . e($college) . '</div>';
    $body .= '<p class="text-end">วันที่ ' . plc_date($g['request_date']) . '</p>';
    $body .= '<p><b>เรื่อง</b> ขอจัดตั้งกลุ่มชุมชนการเรียนรู้ทางวิชาชีพ (PLC) ประจำภาคเรียนที่ ' . plc_n(plc_term_text($g)) . '</p>';
    $body .= '<p><b>เรียน</b> ' . e(setting('plc_committee_title')) . '</p>';
    $body .= '<p class="plc-para">ด้วยข้าพเจ้า ' . e($chair['name'] ?: '..............................') . ' ' . e($chair['position'] ?: '') . ' มีความประสงค์ขอจดทะเบียนจัดตั้งกลุ่มชุมชนการเรียนรู้วิชาชีพ (PLC) ประจำภาคเรียนที่ ' . plc_n(plc_term_text($g)) . ' รายละเอียดดังต่อไปนี้</p>';
    $body .= plc_group_details($g, $members);
    $body .= '<p class="plc-para">จึงเรียนมาเพื่อทราบและพิจารณา</p>';
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'chair', 'ประธานกลุ่ม PLC ' . plc_group_title($g)));
    $body .= plc_opinion($sigs, 'deputy', 'ความเห็น' . $slots['deputy']['position']);
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'deputy'));
    $body .= plc_opinion($sigs, 'director', 'ความเห็น' . setting('plc_committee_title'), 'อนุมัติ');
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'director', $slots['director']['position'] . $college));
    return plc_page($body);
}

function plc_group_details(array $g, array $members): string
{
    $field = $g['field_name'] ? ' สาขาวิชา' . $g['field_name'] : '';
    return '<p class="plc-indent">๑. กลุ่มชุมชนการเรียนรู้วิชาชีพ ' . e(plc_group_title($g) . $field) . ' ประจำภาคเรียนที่ ' . plc_n(plc_term_text($g)) . '</p>'
        . '<p class="plc-indent">๒. สมาชิกกลุ่มชุมชนการเรียนรู้วิชาชีพ ' . e(plc_group_title($g)) . '</p>' . plc_member_list($members, false)
        . '<p class="plc-indent">๓. ผู้เชี่ยวชาญประจำกลุ่ม</p>' . plc_member_list($members, true)
        . '<p class="plc-indent">๔. สถานที่ประชุมกลุ่ม ' . e($g['meeting_place'] ?: '-') . '</p>'
        . '<p class="plc-indent">๕. วันเวลาประชุมกลุ่ม ' . plc_n($g['meeting_schedule'] ?: '-') . '</p>';
}

function plc_doc_group_announce(array $g, array $members, array $slots, array $sigs): string
{
    $college = setting('college_name');
    $field = $g['field_name'] ? '<br>สาขาวิชา' . e($g['field_name']) : '';
    $body = '<div class="plc-title">ประกาศ' . e($college) . '<br>เรื่อง การจัดตั้งกลุ่มชุมชนการเรียนรู้วิชาชีพ (PLC)<br>กลุ่มชุมชนการเรียนรู้วิชาชีพ ' . e(plc_group_title($g)) . $field
        . '<br>ประจำภาคเรียนที่ ' . plc_n($g['semester']) . ' ปีการศึกษา ' . plc_n($g['academic_year']) . '</div><div class="plc-rule">-------------------------------------------------</div>';
    $body .= '<p class="plc-para">ด้วยคณะกรรมการขับเคลื่อนกระบวนการชุมชนการเรียนรู้ทางวิชาชีพ (PLC) ระดับสถานศึกษา ประจำปีการศึกษา ' . plc_n($g['academic_year']) . ' ได้รับจดทะเบียนการจัดตั้งกลุ่มชุมชนการเรียนรู้วิชาชีพไว้แล้ว ' . e($college) . ' จึงออกประกาศให้ทราบดังนี้</p>';
    $body .= plc_group_details($g, $members);
    $body .= '<p class="plc-para">จึงประกาศมาเพื่อทราบโดยทั่วกัน</p>';
    $body .= '<p class="plc-para">ประกาศ ณ วันที่ ' . plc_date($g['announce_date']) . '</p>';
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'director', $slots['director']['position'] . $college));
    return plc_page($body);
}

/* =========================== เอกสารของประเด็นปัญหา =========================== */

function plc_problem_data(array $p, array $g): array
{
    $members = plc_members((int)$g['id']);
    $meeting = function (string $kind) use ($p) {
        return db_row('SELECT * FROM plc_meetings WHERE problem_id = ? AND kind = ? ORDER BY meeting_date, id LIMIT 1', 'is', [$p['id'], $kind]);
    };
    $finding = $meeting('finding');
    $solution = $meeting('solution') ?: $finding;
    return [
        'members'  => $members,
        'issues'   => db_all('SELECT * FROM plc_issues WHERE problem_id = ? ORDER BY priority, id', 'i', [$p['id']]),
        'acts'     => db_all('SELECT * FROM plc_activities WHERE problem_id = ? ORDER BY sort_order, id', 'i', [$p['id']]),
        'measures' => db_all('SELECT * FROM plc_measures WHERE problem_id = ? ORDER BY sort_order, id', 'i', [$p['id']]),
        'steps'    => db_all('SELECT * FROM plc_plan_steps WHERE problem_id = ? ORDER BY step_no', 'i', [$p['id']]),
        'finding'  => $finding,
        'solution' => $solution,
        'model'    => plc_user_brief($p['model_teacher_id'] ? (int)$p['model_teacher_id'] : null),
    ];
}

function plc_selected_issue(array $d): string
{
    return $d['issues'][0]['issue'] ?? '';
}

function plc_doc_problem_proposal(array $p, array $g, array $d, array $slots, array $sigs): string
{
    $college = setting('college_name');
    $body = '<div class="plc-title">แบบคำร้องขอเสนอประเด็นปัญหา<br>กลุ่มชุมชนการเรียนรู้ทางวิชาชีพ (PLC)<br>' . e($college) . '</div>';
    $body .= '<p class="text-end">วันที่ ' . plc_date($p['proposal_date']) . '</p>';
    $body .= '<p><b>เรื่อง</b> ขอเสนอประเด็นปัญหากลุ่มชุมชนการเรียนรู้ทางวิชาชีพ (PLC) ประจำภาคเรียนที่ ' . plc_n(plc_term_text($g)) . '</p>';
    $body .= '<p><b>เรียน</b> ' . e(setting('plc_committee_title')) . '</p>';
    $body .= '<table width="100%" class="plc-kv"><tr><th>กลุ่มชุมชนการเรียนรู้วิชาชีพ</th><td>' . e(plc_group_title($g)) . '</td></tr>'
        . '<tr><th>ประเด็นปัญหาที่จะพัฒนา</th><td>' . plc_n($p['theme']) . '</td></tr>'
        . ($p['subject_name'] ? '<tr><th>รายวิชา</th><td>' . plc_n($p['subject_name']) . '</td></tr>' : '')
        . '<tr><th>เจ้าของปัญหา (Model Teacher)</th><td>' . e($d['model']['fullname'] ?? '-') . '</td></tr></table>';
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'model', $slots['model']['position'] ?: 'ครู' . $g['dept_name']), plc_slot_block($slots, $sigs, 'head'));
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'deputy'), plc_slot_block($slots, $sigs, 'director', $slots['director']['position'] . $college));
    return plc_page($body);
}

function plc_doc_problem_memo(array $p, array $g, array $d, array $slots, array $sigs): string
{
    $body = plc_memo_head($g, $p['memo_no'], $p['memo_date'], 'ขออนุญาตส่งแบบบันทึกการค้นหาปัญหา และแบบบันทึกแนวทางการแก้ไขปัญหา (PLC) กลุ่ม' . plc_group_title($g) . ' ประจำภาคเรียนที่ ' . plc_term_text($g), 'ผู้อำนวยการ' . setting('college_name'));
    $body .= '<p class="plc-para">ตามที่' . e(setting('college_name')) . ' ได้กำหนดให้ครูผู้สอนทุกแผนกวิชาจัดตั้งกลุ่มชุมชนแห่งการเรียนรู้ทางวิชาชีพ (PLC) ประจำภาคเรียนที่ ' . plc_n(plc_term_text($g)) . ' เพื่อแลกเปลี่ยนเรียนรู้ในการจัดการเรียนการสอน และแก้ไขปัญหาของการจัดการเรียนการสอนให้กับผู้เรียนอย่างมีคุณภาพ</p>';
    $body .= '<p class="plc-para">บัดนี้ กลุ่ม' . e(plc_group_title($g)) . ' ได้มีการกำหนดปัญหาและแนวทางแก้ไขปัญหาร่วมกันเป็นที่เรียบร้อยแล้ว ดังรายละเอียดเอกสารที่แนบมาพร้อมนี้</p>';
    $body .= '<p class="plc-para">จึงเรียนมาเพื่อโปรดทราบ</p>';
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'head', "ประธานกลุ่ม PLC " . plc_group_title($g) . "\n" . $slots['head']['position']));
    return plc_page($body);
}

function plc_issue_table(array $issues): string
{
    if (!$issues) return '';
    $rows = '';
    $byOrder = $issues;
    usort($byOrder, fn($a, $b) => $a['id'] <=> $b['id']);
    foreach ($byOrder as $i => $it) {
        $causes = implode('<br>', array_map(fn($c, $n) => plc_n(($n + 1) . '. ' . $c), plc_lines($it['causes']), array_keys(plc_lines($it['causes']))));
        $rows .= '<tr><td>' . plc_n(($i + 1) . '. ' . $it['issue']) . '</td><td>' . $causes . '</td><td class="text-center">' . plc_n($it['priority']) . '</td></tr>';
    }
    return '<table border="1" cellspacing="0" cellpadding="4" width="100%" class="plc-table"><thead><tr><th>ปัญหา</th><th>สาเหตุของปัญหา</th><th style="width:16%">ลำดับความสำคัญ</th></tr></thead><tbody>' . $rows . '</tbody></table>';
}

function plc_doc_problem_finding(array $p, array $g, array $d, array $slots, array $sigs): string
{
    $att = $d['finding'] ? plc_meeting_attendees($d['finding'], $d['members']) : $d['members'];
    $body = '<div class="plc-title">แบบบันทึกการค้นหาปัญหา</div>';
    $body .= plc_meeting_header($d['finding'], $g, count($att)) . plc_attendee_list($att);
    $body .= '<p><b>สรุปปัญหาด้านการเรียนรู้ของผู้เรียนที่กลุ่มคัดเลือก</b></p><ol class="plc-ol">';
    foreach ($d['issues'] as $it) $body .= '<li>' . plc_n($it['issue']) . '</li>';
    $body .= '</ol>' . plc_issue_table($d['issues']);
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'recorder', trim(($slots['recorder']['position'] ?: '') . "\nผู้บันทึกกลุ่ม PLC")));
    return plc_page($body);
}

function plc_doc_problem_solution(array $p, array $g, array $d, array $slots, array $sigs): string
{
    $att = $d['solution'] ? plc_meeting_attendees($d['solution'], $d['members']) : $d['members'];
    $body = '<div class="plc-title">แบบบันทึกแนวทางแก้ไขปัญหา</div>';
    $body .= plc_meeting_header($d['solution'], $g, count($att)) . plc_attendee_list($att);
    $body .= '<p><b>ปัญหาด้านการเรียนรู้ของผู้เรียนที่กลุ่ม PLC คัดเลือก</b></p><p class="plc-indent">ชื่อปัญหา ' . plc_n(plc_selected_issue($d)) . '</p>';
    $list = fn(array $items) => $items ? '<ol class="plc-ol">' . implode('', array_map(fn($x) => '<li>' . plc_n($x) . '</li>', $items)) . '</ol>' : '<p class="plc-indent">-</p>';
    $body .= '<p><b>แนวทางแก้ปัญหา</b></p>' . $list(plc_lines($p['solutions']));
    $body .= '<p><b>วัตถุประสงค์</b></p>' . $list(array_column($d['measures'], 'objective'));
    if ($d['acts']) {
        $rows = '';
        foreach ($d['acts'] as $i => $a) {
            $rows .= '<tr><td>' . ($a['teacher_activity'] ? plc_n(($i + 1) . '. ' . $a['teacher_activity']) : '') . '</td><td>' . ($a['student_activity'] ? plc_n(($i + 1) . '. ' . $a['student_activity']) : '') . '</td></tr>';
        }
        $body .= '<p><b>แนวทางการดำเนินงาน</b></p><table border="1" cellspacing="0" cellpadding="4" width="100%" class="plc-table"><thead><tr><th>กิจกรรมการเรียนการสอน</th><th>กิจกรรมผู้เรียน</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }
    if ($d['measures']) {
        $rows = '';
        foreach ($d['measures'] as $i => $m) {
            $rows .= '<tr><td>' . plc_n(($i + 1) . '. ' . $m['objective']) . '</td><td>' . nl2br(plc_n($m['tool'])) . '</td><td>' . nl2br(plc_n($m['criteria'])) . '</td></tr>';
        }
        $body .= '<p><b>การวัดผลและการประเมินผล</b></p><table border="1" cellspacing="0" cellpadding="4" width="100%" class="plc-table"><thead><tr><th>วัตถุประสงค์</th><th>เครื่องมือวัดและประเมินผล</th><th>เกณฑ์ความสำเร็จ</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }
    $body .= '<p><b>ประโยชน์ที่ได้รับ</b></p>' . $list(plc_lines($p['benefits']));
    $body .= '<p><b>เจ้าของปัญหา (Model Teacher)</b> ' . e($d['model']['fullname'] ?? '-') . '</p>';
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'recorder', trim(($slots['recorder']['position'] ?: '') . "\nผู้บันทึกกลุ่ม PLC")));
    return plc_page($body);
}

function plc_doc_problem_plan(array $p, array $g, array $d, array $slots, array $sigs): string
{
    $range = $p['plan_start'] ? ' (' . plc_date($p['plan_start'], true) . ' - ' . plc_date($p['plan_end'], true) . ')' : '';
    $body = '<div class="plc-title">แผนปฏิบัติการชุมชนการเรียนรู้ทางวิชาชีพ<br>(Professional Learning Community Action Plan : PLC-AP)</div><div class="plc-rule">-------------------------------------------------</div>';
    $body .= '<p><b>ชื่อกลุ่ม</b> ' . e(plc_group_title($g)) . ' <b>สังกัด</b> ' . e(setting('college_name')) . '</p>';
    $body .= '<p><b>เจ้าของปัญหา (Model Teacher)</b> ' . e($d['model']['fullname'] ?? '-') . '</p>';
    $body .= '<p><b>ชื่อสถานศึกษา</b> ' . e(setting('college_name')) . '</p>';
    $body .= '<p><b>รายชื่อสมาชิกกลุ่ม PLC (Buddy Teacher)/ครูรุ่นพี่ (Senior Teacher)/ผู้อำนวยการ/รองผู้อำนวยการ/ผู้เชี่ยวชาญ (Expert) ปราชญ์ท้องถิ่น (ถ้ามี)</b></p>';
    $members = $d['members'];
    usort($members, fn($a, $b) => ($b['committee_role'] === 'expert') <=> ($a['committee_role'] === 'expert'));
    $body .= plc_attendee_list($members);
    $body .= '<p><b>ประเด็นปัญหาที่เลือกนำมาเป็นเป้าหมาย</b> ' . plc_n(plc_selected_issue($d) ?: $p['theme']) . '</p>';
    $rows = '';
    foreach ($d['steps'] as $s) {
        $rows .= '<tr><td class="text-center">' . plc_n($s['step_no']) . '</td><td>' . plc_n($s['activity']) . '</td><td class="text-center">' . nl2br(e($s['role_text'])) . '</td>'
            . '<td class="text-center">' . ($s['plan_date'] ? plc_date($s['plan_date'], true) : '') . ($s['minutes'] ? '<br>(ประมาณ ' . plc_n($s['minutes']) . ' นาที)' : '') . '</td></tr>';
    }
    $body .= '<p><b>แผนปฏิบัติการ' . $range . '</b></p><table border="1" cellspacing="0" cellpadding="4" width="100%" class="plc-table"><thead><tr><th style="width:8%">ลำดับ</th><th>กิจกรรม</th><th style="width:22%">บทบาท</th><th style="width:22%">วัน เดือน ปี</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    $body .= '<p class="plc-note"><b>หมายเหตุ</b> ดำเนินการปรับปรุง วนซ้ำ ปฏิบัติจนสำเร็จตามวัตถุประสงค์ จึงนำออกเผยแพร่ โดยขั้นตอนตามกระบวนการและระยะเวลาดังกล่าว สามารถยืดหยุ่นได้ตามบริบท และคำนึงถึงการร่วมมือ การแลกเปลี่ยนเรียนรู้ การสังเกต และการสะท้อนผล</p>';
    return plc_page($body);
}

function plc_doc_photos(array $g, ?array $p, array $photos): string
{
    if (!$photos) return '';
    $body = '<div class="plc-title">รูปภาพประกอบการประชุม การดำเนินการขับเคลื่อนกระบวนการ PLC<br>ชุมชนการเรียนรู้ทางวิชาชีพ ' . e(plc_group_title($g)) . '</div>';
    $body .= '<table width="100%" class="plc-photos">';
    foreach (array_chunk($photos, 2) as $pair) {
        $body .= '<tr>';
        foreach ($pair as $ph) {
            $path = storage_is_drive($ph['file_name']) ? null : UPLOAD_DIR . '/' . trip_main_name($ph['file_name']);
            $src = plc_doc_img(url('plc-file.php?photo=' . (int)$ph['id']), $path, 'image/jpeg');
            $no = array_search($ph, $photos, true) + 1;
            $body .= '<td>' . ($src ? '<img src="' . e($src) . '" width="300" alt="">' : '<div class="text-muted small">(ภาพเก็บใน Google Drive ดูได้ในระบบ)</div>')
                . '<div class="plc-caption">ภาพที่ ' . plc_n($no) . ' ' . plc_n($ph['caption'] ?? '') . '</div></td>';
        }
        if (count($pair) === 1) $body .= '<td></td>';
        $body .= '</tr>';
    }
    return plc_page($body . '</table>');
}

/* =========================== บันทึกการประชุม / สะท้อนผล / สังเกตการสอน =========================== */

function plc_doc_meeting(array $mt, array $g, ?array $p, array $slots, array $sigs): string
{
    $members = plc_members((int)$g['id']);
    $att = plc_meeting_attendees($mt, $members);
    $reflect = $mt['kind'] === 'reflect';
    $title = $reflect ? 'แบบบันทึกการสะท้อนผล' : 'แบบบันทึกกิจกรรมชุมชนการเรียนรู้ทางวิชาชีพ (PLC)';
    $body = '<div class="plc-title">' . $title . '<br>กลุ่ม' . e(plc_group_title($g)) . ' ภาคเรียนที่ ' . plc_n(plc_term_text($g)) . '</div>';
    $body .= '<p><b>กิจกรรม</b> ' . plc_n($mt['topic']) . ($mt['step_no'] ? ' (ขั้นที่ ' . plc_n($mt['step_no']) . ' ของแผนปฏิบัติการ)' : '') . '</p>';
    if ($p) $body .= '<p><b>ประเด็นปัญหา</b> ' . plc_n($p['theme']) . '</p>';
    $body .= plc_meeting_header($mt, $g, count($att)) . plc_attendee_list($att);
    if ($reflect) {
        $det = json_decode((string)$mt['details'], true) ?: [];
        $n = 0;
        foreach (plc_reflect_fields() as $k => $label) {
            $body .= '<p><b>' . plc_n(++$n) . '. ' . e($label) . '</b></p><div class="plc-text">' . nl2br(plc_n($det[$k] ?? '-')) . '</div>';
        }
        if (trim((string)$mt['content']) !== '') $body .= '<p><b>' . plc_n(++$n) . '. ข้อมูลเพิ่มเติม</b></p><div class="plc-text">' . nl2br(plc_n($mt['content'])) . '</div>';
    } else {
        $body .= '<p><b>สาระสำคัญ / ผลการประชุม</b></p><div class="plc-text">' . nl2br(plc_n($mt['content'] ?: '-')) . '</div>';
    }
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'recorder', trim(($slots['recorder']['position'] ?: '') . "\nผู้บันทึก")));
    return plc_page($body);
}

function plc_doc_observation(array $o, array $g, array $p, array $slots, array $sigs): string
{
    $scores = json_decode((string)$o['scores'], true) ?: [];
    $model = plc_user_brief($p['model_teacher_id'] ? (int)$p['model_teacher_id'] : null);
    $body = '<div class="plc-title">แบบสังเกตการสอนในชั้นเรียน<br>ชุมชนการเรียนรู้ทางวิชาชีพ (PLC) ' . e(plc_group_title($g)) . '</div>';
    $body .= '<table width="100%" class="plc-kv"><tr><th>ครูผู้สอน (Model Teacher)</th><td>' . e($model['fullname'] ?? '-') . '</td></tr>'
        . '<tr><th>รายวิชา</th><td>' . plc_n($p['subject_name'] ?: '-') . '</td></tr>'
        . '<tr><th>หน่วย / เรื่องที่สอน</th><td>' . plc_n($o['topic'] ?: '-') . '</td></tr>'
        . '<tr><th>ระดับชั้น / กลุ่มเรียน</th><td>' . plc_n($o['class_group'] ?: '-') . '</td></tr>'
        . '<tr><th>วันที่สังเกต</th><td>' . plc_date($o['observe_date']) . ' เวลา ' . plc_time($o['start_time']) . ' - ' . plc_time($o['end_time']) . '</td></tr>'
        . '<tr><th>จำนวนผู้เรียน</th><td>ทั้งหมด ' . plc_n($o['students_total'] ?? '-') . ' คน มาเรียน ' . plc_n($o['students_present'] ?? '-') . ' คน</td></tr>'
        . '<tr><th>ประเด็นปัญหา</th><td>' . plc_n($p['theme']) . '</td></tr></table>';
    $body .= '<p><b>ตอนที่ ๑ ผลการสังเกต</b> (๕ = ดีมาก ๔ = ดี ๓ = ปานกลาง ๒ = พอใช้ ๑ = ปรับปรุง)</p>';
    $body .= '<table border="1" cellspacing="0" cellpadding="4" width="100%" class="plc-table"><thead><tr><th>รายการสังเกต</th>' . implode('', array_map(fn($n) => '<th width="7%" style="width:7%;text-align:center">' . plc_n($n) . '</th>', array_keys(plc_score_labels()))) . '</tr></thead><tbody>';
    $i = 0;
    $sum = 0;
    $cnt = 0;
    foreach (plc_observe_items() as $section => $items) {
        $body .= '<tr><td colspan="6"><b>' . e($section) . '</b></td></tr>';
        foreach ($items as $k => $label) {
            $v = (int)($scores[$k] ?? 0);
            if ($v) { $sum += $v; $cnt++; }
            $body .= '<tr><td>' . plc_n(++$i) . '. ' . e($label) . '</td>';
            foreach (array_keys(plc_score_labels()) as $n) $body .= '<td style="text-align:center">' . ($v === $n ? '✓' : '') . '</td>';
            $body .= '</tr>';
        }
    }
    $body .= '</tbody></table>';
    if ($cnt) $body .= '<p class="text-end">คะแนนเฉลี่ย ' . plc_n(number_format($sum / $cnt, 2)) . ' (' . e(plc_score_labels()[(int)round($sum / $cnt)] ?? '') . ')</p>';
    $body .= '<p><b>ตอนที่ ๒ บันทึกการสังเกต</b></p>';
    foreach (['student_behavior' => 'พฤติกรรมการเรียนรู้ของผู้เรียนที่พบ', 'strengths' => 'จุดเด่นของการจัดการเรียนรู้', 'improvements' => 'สิ่งที่ควรปรับปรุง / พัฒนา', 'suggestions' => 'ข้อเสนอแนะ'] as $k => $label) {
        $body .= '<p class="plc-indent"><b>' . e($label) . '</b></p><div class="plc-text">' . nl2br(plc_n($o[$k] ?: '-')) . '</div>';
    }
    $body .= plc_signs(plc_slot_block($slots, $sigs, 'observer', trim(($slots['observer']['position'] ?: '') . "\nผู้สังเกตการสอน")));
    return plc_page($body);
}

function plc_doc_hours(array $g): string
{
    $rows = '';
    $i = 0;
    foreach (plc_hours((int)$g['id']) as $h) {
        $m = $h['member'];
        $total = $h['meetings'] + $h['observe'];
        $rows .= '<tr><td class="text-center">' . plc_n(++$i) . '</td><td>' . e($m['display_name']) . '</td><td>' . e(plc_committee_roles()[$m['committee_role']]) . '</td>'
            . '<td class="text-center">' . plc_n($h['count']) . '</td><td class="text-center">' . plc_n(plc_hours_text($total)) . '</td></tr>';
    }
    $body = '<div class="plc-title">สรุปชั่วโมงการเข้าร่วมกิจกรรมชุมชนการเรียนรู้ทางวิชาชีพ (PLC)<br>กลุ่ม' . e(plc_group_title($g)) . ' ภาคเรียนที่ ' . plc_n(plc_term_text($g)) . '<br>' . e(setting('college_name')) . '</div>';
    $body .= '<table border="1" cellspacing="0" cellpadding="4" width="100%" class="plc-table"><thead><tr><th style="width:8%">ที่</th><th>ชื่อ - สกุล</th><th>บทบาทในกลุ่ม</th><th>จำนวนครั้ง</th><th>รวมเวลา</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    $body .= '<p class="plc-note">นับจากเวลาประชุม/กิจกรรมที่บันทึกการเข้าร่วม และเวลาสังเกตการสอนในชั้นเรียน ข้อมูล ณ วันที่ ' . plc_date(date('Y-m-d')) . '</p>';
    $head = plc_slots('group', $g, $g)['head'];
    $body .= plc_signs(plc_sign_block(null, (string)$head['name'], (string)$head['position'], 'ลงชื่อ', 'ผู้รับรอง'));
    return plc_page($body);
}

/** CSS ของเอกสาร (ใช้ทั้งหน้าพิมพ์และไฟล์ Word) */
function plc_doc_css(): string
{
    return <<<CSS
.plc-doc { font-family: 'TH Sarabun New', 'TH SarabunPSK', 'Sarabun', sans-serif; font-size: 16pt; line-height: 1.35; color: #000; }
.plc-doc p { margin: 0 0 4pt; }
.plc-page { background: #fff; width: 21cm; min-height: 29.7cm; margin: 0 auto 1.5rem; padding: 2cm 2cm 2cm 3cm; box-shadow: 0 2px 10px rgba(0,0,0,.12); }
.plc-title { text-align: center; font-weight: bold; font-size: 18pt; margin-bottom: 10pt; }
.plc-memo-title { text-align: center; font-weight: bold; font-size: 29pt; margin-bottom: 6pt; }
.plc-memo { width: 100%; border-collapse: collapse; }
.plc-memo td { padding: 2pt 0; vertical-align: top; }
.plc-memo-line { border: 0; border-top: 1px solid #000; margin: 2pt 0 6pt; }
.plc-attach td { padding: 0 6pt 0 0; vertical-align: top; }
.plc-para { text-indent: 2.5cm; }
.plc-indent { margin-left: 1.25cm !important; }
.plc-list { margin-left: 1.25cm; border-collapse: collapse; }
.plc-list td { padding: 0 14pt 0 0; vertical-align: top; }
.plc-num { width: 1.2cm; }
.plc-ol { margin: 0 0 4pt 1.25cm; padding-left: 1cm; }
.plc-table { width: 100%; border-collapse: collapse; margin: 4pt 0 8pt; }
.plc-table th, .plc-table td { border: 1px solid #000; padding: 3pt 5pt; vertical-align: top; }
.plc-table th { text-align: center; font-weight: bold; }
.plc-kv { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
.plc-kv th { width: 38%; text-align: left; font-weight: bold; vertical-align: top; padding: 1pt 6pt 1pt 0; }
.plc-kv td { vertical-align: top; padding: 1pt 0; }
.plc-text { margin: 0 0 6pt 1.25cm; }
.plc-signs { border-collapse: collapse; margin: 18pt 0 10pt auto; }
.plc-signs td { vertical-align: top; padding: 0 6pt; }
.plc-signs-2 { width: 100%; }
.plc-signs-2 td { width: 50%; }
.plc-sign { text-align: center; min-width: 7cm; }
.plc-sign img.plc-sig { height: 45px; vertical-align: bottom; }
.plc-sig-date { font-size: 11pt; color: #555; }
.plc-approve { margin-top: 14pt; text-align: center; margin-left: 50%; }
.plc-opinion { margin-top: 12pt !important; }
.plc-rule { text-align: center; margin-bottom: 8pt; }
.plc-note { font-size: 14pt; margin-top: 8pt !important; }
.plc-photos { width: 100%; border-collapse: collapse; }
.plc-photos td { width: 50%; text-align: center; vertical-align: top; padding: 6pt; }
.plc-photos img { max-width: 100%; height: auto; }
.plc-caption { font-size: 14pt; margin-top: 3pt; }
.text-end { text-align: right; }
.text-center { text-align: center; }
CSS;
}
