<?php
/**
 * ระบบบริหารจัดการ PLC (ชุมชนการเรียนรู้ทางวิชาชีพ) — โมดูลย่อยของ Docs Hub
 * กลุ่ม PLC ผูกกับแผนกวิชา (เปิดใช้รายแผนกที่ หลังบ้าน → ระบบ PLC) ใช้บัญชีผู้ใช้ของ Docs Hub
 * เอกสารที่พิมพ์ได้อยู่ใน includes/plc_docs.php
 */

/* ---------------- ค่าคงที่ / ตัวเลือก ---------------- */

function plc_committee_roles(): array
{
    return ['chair' => 'ประธานกรรมการ', 'vice_chair' => 'รองประธานกรรมการ', 'member' => 'กรรมการ',
            'secretary' => 'กรรมการและเลขานุการ', 'expert' => 'ผู้เชี่ยวชาญประจำกลุ่ม'];
}

function plc_meeting_kinds(): array
{
    return [
        'finding'  => 'ประชุมค้นหาปัญหา',
        'solution' => 'ประชุมหาแนวทางแก้ไขปัญหา',
        'general'  => 'ประชุม / กิจกรรม PLC',
        'teach'    => 'ลงมือปฏิบัติการสอน',
        'reflect'  => 'ประชุมสะท้อนผล',
        'summary'  => 'สรุป / สังเคราะห์การเรียนรู้',
    ];
}

function plc_status_badge(string $status): string
{
    [$c, $t] = ['draft' => ['secondary', 'ร่าง'], 'submitted' => ['warning', 'รออนุมัติ'], 'approved' => ['success', 'อนุมัติแล้ว']][$status] ?? ['secondary', $status];
    return '<span class="badge text-bg-' . $c . '">' . $t . '</span>';
}

/** ขั้นตอนเริ่มต้นของแผนปฏิบัติการ PLC (PLC-AP) ตามแบบของวิทยาลัย: [กิจกรรม, บทบาท, นาที] */
function plc_default_steps(): array
{
    $mt = "Model Teacher\nและสมาชิกกลุ่ม";
    return [
        ['ประชุมร่วมกันพิจารณาเลือกปัญหา เป้าหมายและร่วมกันหาแนวทางในการแก้ปัญหา', 'สมาชิกกลุ่ม', 60],
        ['จัดทำแผนปฏิบัติการชุมชนการเรียนรู้ทางวิชาชีพ (PLC)', $mt, 120],
        ['ออกแบบกิจกรรมการแก้ปัญหา (แผนการสอน/แบบฝึก/กิจกรรม)', $mt, 60],
        ['แลกเปลี่ยน เสนอแนะ สะท้อนแผนการจัดกิจกรรมหรือแผนการสอน', 'สมาชิกกลุ่ม', 60],
        ['สรุป สังเคราะห์ผลการวิพากษ์ สะท้อนคิด และปรับปรุงแผนการสอน/แบบฝึก/กิจกรรม', $mt, 120],
        ['ลงมือปฏิบัติการสอน/ฝึก/กิจกรรม', 'Model Teacher', 60],
        ['สังเกตการสอนในชั้นเรียน', 'สมาชิกกลุ่ม', 60],
        ['ประชุมสะท้อนผลการสังเกตการเรียน', 'สมาชิกกลุ่ม', 60],
        ['สรุปการสังเคราะห์การเรียนรู้จากสมาชิกในทีม จุดอ่อน จุดแข็ง และการดำเนินการ', $mt, 60],
        ['สรุปรูปแบบที่ปรับปรุงจากบทเรียนที่ได้ทดลองใช้', $mt, 60],
    ];
}

/** หัวข้อแบบสังเกตการสอน (ให้คะแนน 1–5) */
function plc_observe_items(): array
{
    return [
        'ด้านครูผู้สอน' => [
            't1' => 'การนำเข้าสู่บทเรียนและชี้แจงวัตถุประสงค์การเรียนรู้',
            't2' => 'จัดกิจกรรมตามแผนการสอน/แนวทางแก้ปัญหาที่กลุ่มออกแบบไว้',
            't3' => 'การใช้สื่อ เทคโนโลยี และนวัตกรรมประกอบการสอน',
            't4' => 'การใช้คำถามกระตุ้นการคิดและการให้ข้อมูลย้อนกลับแก่ผู้เรียน',
            't5' => 'การบริหารเวลาและการจัดการชั้นเรียน',
            't6' => 'การวัดและประเมินผลระหว่างเรียน',
        ],
        'ด้านผู้เรียน' => [
            's1' => 'ผู้เรียนสนใจและตั้งใจเรียน',
            's2' => 'ผู้เรียนมีส่วนร่วมในกิจกรรม ถาม-ตอบ และแสดงความคิดเห็น',
            's3' => 'ผู้เรียนปฏิบัติงาน/ทำแบบฝึกได้ตามที่กำหนด',
            's4' => 'ผู้เรียนทำงานร่วมกับผู้อื่นและช่วยเหลือกัน',
            's5' => 'ผู้เรียนบรรลุจุดประสงค์การเรียนรู้ของคาบเรียน',
        ],
    ];
}

function plc_score_labels(): array
{
    return [5 => 'ดีมาก', 4 => 'ดี', 3 => 'ปานกลาง', 2 => 'พอใช้', 1 => 'ปรับปรุง'];
}

/** หัวข้อแบบบันทึกการสะท้อนผล (เก็บใน plc_meetings.details) */
function plc_reflect_fields(): array
{
    return [
        'result'      => 'ผลการจัดกิจกรรมการเรียนรู้ตามแผน',
        'goal'        => 'ผลการดำเนินงานเทียบกับเกณฑ์ความสำเร็จ',
        'strengths'   => 'จุดแข็ง / สิ่งที่ทำได้ดี',
        'weaknesses'  => 'จุดที่ควรพัฒนา / ปัญหาอุปสรรค',
        'lessons'     => 'สิ่งที่สมาชิกได้เรียนรู้ร่วมกัน',
        'improvement' => 'แนวทางการปรับปรุงและพัฒนาต่อ',
    ];
}

/* ---------------- ตัวเลขไทย / วันที่ ---------------- */

function plc_thai_digits($s): string
{
    return strtr((string)$s, ['0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔', '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙']);
}

/** วันที่แบบเอกสารราชการ เช่น ๒๘ ตุลาคม ๒๕๖๙ (หรือย่อ ๒๘ ต.ค. ๒๕๖๙) */
function plc_date(?string $date, bool $short = false): string
{
    return $date ? plc_thai_digits(thai_date($date, $short)) : '....................';
}

function plc_time(?string $t): string
{
    return $t ? plc_thai_digits(substr($t, 0, 5)) . ' น.' : '..........';
}

function plc_minutes(string $start, string $end): int
{
    return max(0, (int)round((strtotime('2000-01-01 ' . $end) - strtotime('2000-01-01 ' . $start)) / 60));
}

function plc_hours_text(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return ($h ? $h . ' ชม.' : '') . ($m ? ($h ? ' ' : '') . $m . ' นาที' : '') ?: '0 ชม.';
}

/** แยกข้อความหลายบรรทัดเป็นรายการ (ตัดเลขข้อ/สัญลักษณ์นำหน้าออก) */
function plc_lines(?string $text): array
{
    $out = [];
    foreach (preg_split('/\R/u', (string)$text) as $line) {
        $line = trim(preg_replace('/^\s*(?:[-•*]|[0-9๐-๙]+[.)])\s*/u', '', $line));
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

/* ---------------- แผนก / สิทธิ์ ---------------- */

function plc_departments(): array
{
    return array_filter(departments(), fn($d) => !empty($d['plc_enabled']));
}

function plc_is_approver(?int $uid = null): bool
{
    $uid ??= (int)(current_user()['id'] ?? 0);
    if (!$uid) return false;
    if (in_array((string)$uid, [setting('plc_deputy_user_id'), setting('plc_director_user_id')], true)) return true;
    return (bool)db_val('SELECT id FROM departments WHERE plc_head_user_id = ? LIMIT 1', 'i', [$uid]);
}

/** ใช้งานโมดูล PLC ได้: แผนกของตนเปิดใช้ / เป็นสมาชิกกลุ่มใดกลุ่มหนึ่ง / เป็นผู้อนุมัติ / เจ้าหน้าที่ */
function plc_can_access(): bool
{
    $u = current_user();
    if (!$u) return false;
    if (is_staff() || plc_is_approver()) return true;
    if ($u['department_id'] && isset(plc_departments()[(int)$u['department_id']])) return true;
    return (bool)db_val('SELECT id FROM plc_members WHERE user_id = ? LIMIT 1', 'i', [$u['id']]);
}

function require_plc_access(): void
{
    require_login();
    if (!plc_can_access()) {
        flash('warning', 'แผนกของคุณยังไม่ได้เปิดใช้ระบบ PLC กรุณาติดต่อผู้ดูแลระบบ');
        redirect('index.php');
    }
}

function plc_group_get(int $id): ?array
{
    return db_row('SELECT g.*, d.name AS dept_name, d.code AS dept_code, d.plc_head_user_id
                   FROM plc_groups g JOIN departments d ON d.id = g.department_id WHERE g.id = ?', 'i', [$id]);
}

function plc_group_title(array $g): string
{
    return trim((string)$g['name']) !== '' ? $g['name'] : $g['dept_name'];
}

/** ชื่อกลุ่มเต็มที่ใช้ในเอกสาร เช่น แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล วิทยาลัยการอาชีพโพนทอง */
function plc_group_fullname(array $g): string
{
    return plc_group_title($g) . ' ' . setting('college_name');
}

function plc_term_text(array $g): string
{
    return $g['semester'] . '/' . $g['academic_year'];
}

/** สมาชิกกลุ่ม (ผู้เชี่ยวชาญอยู่ท้ายรายการ) พร้อมชื่อ ตำแหน่ง ลายมือชื่อ */
function plc_members(int $groupId): array
{
    return db_all("SELECT m.*, u.fullname, u.position, u.signature, u.department_id AS user_dept,
                          COALESCE(u.fullname, m.guest_name) AS display_name
                   FROM plc_members m LEFT JOIN users u ON u.id = m.user_id
                   WHERE m.group_id = ?
                   ORDER BY m.committee_role = 'expert', FIELD(m.committee_role, 'chair', 'vice_chair', 'member', 'secretary', 'expert'), m.sort_order, m.id", 'i', [$groupId]);
}

function plc_member_of(int $groupId, ?int $uid = null): ?array
{
    $uid ??= (int)(current_user()['id'] ?? 0);
    return $uid ? db_row('SELECT * FROM plc_members WHERE group_id = ? AND user_id = ?', 'ii', [$groupId, $uid]) : null;
}

function plc_member_with_role(int $groupId, string $role): ?array
{
    return db_row('SELECT m.*, u.fullname, u.position, COALESCE(u.fullname, m.guest_name) AS display_name
                   FROM plc_members m LEFT JOIN users u ON u.id = m.user_id
                   WHERE m.group_id = ? AND m.committee_role = ? ORDER BY m.sort_order, m.id LIMIT 1', 'is', [$groupId, $role]);
}

function plc_can_view_group(array $g): bool
{
    $u = current_user();
    if (!$u) return false;
    if (is_staff() || plc_is_approver() || plc_member_of((int)$g['id'])) return true;
    return (int)$u['department_id'] === (int)$g['department_id'];
}

/** แก้ข้อมูลกลุ่ม/สมาชิก: เจ้าหน้าที่ หัวหน้าแผนก ประธาน รองประธาน เลขานุการ */
function plc_can_manage_group(array $g): bool
{
    $u = current_user();
    if (!$u) return false;
    if (is_staff() || (int)$g['plc_head_user_id'] === (int)$u['id']) return true;
    $m = plc_member_of((int)$g['id']);
    return $m && in_array($m['committee_role'], ['chair', 'vice_chair', 'secretary'], true);
}

/** บันทึกข้อมูลในกลุ่ม (ประเด็นปัญหา การประชุม การสังเกต ภาพ): สมาชิกทุกคน เลขานุการ เจ้าหน้าที่ หัวหน้าแผนก */
function plc_can_contribute(array $g): bool
{
    return plc_can_manage_group($g) || (bool)plc_member_of((int)$g['id']);
}

/* ---------------- ผู้ลงนาม / การอนุมัติ ---------------- */

function plc_user_brief(?int $uid): ?array
{
    return $uid ? db_row('SELECT id, fullname, position, signature FROM users WHERE id = ?', 'i', [$uid]) : null;
}

/** ผู้ลงนามระดับวิทยาลัย: deputy / director จากการตั้งค่า */
function plc_college_signer(string $key): array
{
    $u = plc_user_brief((int)setting('plc_' . $key . '_user_id')) ;
    return [
        'user_id'  => $u ? (int)$u['id'] : null,
        'name'     => $u ? $u['fullname'] : setting('plc_' . $key . '_name'),
        'position' => setting('plc_' . $key . '_position', $key === 'director' ? 'ผู้อำนวยการ' : 'รองผู้อำนวยการฝ่ายวิชาการ'),
    ];
}

/**
 * ช่องลงนามของเอกสาร เรียงตามลำดับ
 * type: sign = ผู้จัดทำลงนามได้ทุกเมื่อ (ก่อนอนุมัติ) · approve = ผู้บังคับบัญชาอนุมัติตามลำดับหลังส่งขออนุมัติ
 * user_id ว่าง = ไม่มีบัญชีในระบบ (ลงนามในกระดาษ ข้ามขั้นนี้ในระบบ)
 */
function plc_slots(string $entity, array $row, array $g): array
{
    $head = plc_user_brief($g['plc_head_user_id'] ? (int)$g['plc_head_user_id'] : null);
    $headSlot = ['type' => 'approve', 'label' => 'หัวหน้าแผนกวิชา', 'user_id' => $head ? (int)$head['id'] : null,
                 'name' => $head['fullname'] ?? '', 'position' => 'หัวหน้า' . $g['dept_name']];
    $college = fn($key, $label) => ['type' => 'approve', 'label' => $label] + plc_college_signer($key);
    $member = function (string $role, string $label) use ($g) {
        $m = plc_member_with_role((int)$g['id'], $role);
        return ['type' => 'sign', 'label' => $label, 'user_id' => $m && $m['user_id'] ? (int)$m['user_id'] : null,
                'name' => $m['display_name'] ?? '', 'position' => $m['position'] ?? ''];
    };
    switch ($entity) {
        case 'group':
            return [
                'chair'    => $member('chair', 'ประธานกลุ่ม PLC'),
                'head'     => $headSlot,
                'deputy'   => $college('deputy', 'รองผู้อำนวยการฝ่ายวิชาการ'),
                'director' => $college('director', 'ผู้อำนวยการ'),
            ];
        case 'problem':
            $mt = plc_user_brief($row['model_teacher_id'] ? (int)$row['model_teacher_id'] : null);
            return [
                'model'    => ['type' => 'sign', 'label' => 'เจ้าของปัญหา (Model Teacher)', 'user_id' => $mt ? (int)$mt['id'] : null,
                               'name' => $mt['fullname'] ?? '', 'position' => $mt['position'] ?? ''],
                'recorder' => $member('secretary', 'ผู้บันทึกกลุ่ม PLC'),
                'head'     => $headSlot,
                'deputy'   => $college('deputy', 'รองผู้อำนวยการฝ่ายวิชาการ'),
                'director' => $college('director', 'ผู้อำนวยการ'),
            ];
        case 'meeting':
            $rec = plc_user_brief($row['recorder_id'] ? (int)$row['recorder_id'] : null);
            return ['recorder' => ['type' => 'sign', 'label' => 'ผู้บันทึก', 'user_id' => $rec ? (int)$rec['id'] : null,
                                   'name' => $rec['fullname'] ?? '', 'position' => $rec['position'] ?? '']];
        case 'observation':
            $ob = plc_user_brief($row['observer_id'] ? (int)$row['observer_id'] : null);
            return ['observer' => ['type' => 'sign', 'label' => 'ผู้สังเกตการสอน', 'user_id' => $ob ? (int)$ob['id'] : null,
                                   'name' => $ob['fullname'] ?? '', 'position' => $ob['position'] ?? '']];
    }
    return [];
}

/** ลายมือชื่อที่ลงไว้แล้ว [slot => แถว + ข้อมูลผู้ลงนาม] */
function plc_signatures(string $entity, int $id): array
{
    $out = [];
    foreach (db_all('SELECT s.*, u.fullname, u.signature FROM plc_signatures s LEFT JOIN users u ON u.id = s.user_id
                     WHERE s.entity = ? AND s.entity_id = ?', 'si', [$entity, $id]) as $r) {
        $out[$r['slot']] = $r;
    }
    return $out;
}

/** ช่องอนุมัติถัดไปที่รออยู่ (เฉพาะสถานะรออนุมัติ) */
function plc_pending_slot(string $entity, array $row, array $g): ?string
{
    if (($row['status'] ?? '') !== 'submitted') return null;
    $signed = plc_signatures($entity, (int)$row['id']);
    foreach (plc_slots($entity, $row, $g) as $key => $s) {
        if ($s['type'] === 'approve' && $s['user_id'] && !isset($signed[$key])) return $key;
    }
    return null;
}

/** ช่องที่ผู้ใช้ปัจจุบันลงนามได้ตอนนี้ */
function plc_signable_slots(string $entity, array $row, array $g): array
{
    $uid = (int)(current_user()['id'] ?? 0);
    if (!$uid) return [];
    $signed = plc_signatures($entity, (int)$row['id']);
    $status = $row['status'] ?? 'draft';
    $pending = plc_pending_slot($entity, $row, $g);
    $out = [];
    foreach (plc_slots($entity, $row, $g) as $key => $s) {
        if ($s['user_id'] !== $uid || isset($signed[$key])) continue;
        if ($s['type'] === 'sign' && $status !== 'approved') $out[] = $key;
        if ($s['type'] === 'approve' && $key === $pending) $out[] = $key;
    }
    return $out;
}

function plc_entity_table(string $entity): string
{
    return ['group' => 'plc_groups', 'problem' => 'plc_problems', 'meeting' => 'plc_meetings', 'observation' => 'plc_observations'][$entity];
}

/**
 * ลงนาม/อนุมัติช่อง $slot (ตรวจสิทธิ์แล้ว) ถ้าผู้ใช้เป็นผู้อนุมัติขั้นถัดไปด้วย จะลงนามต่อให้ทันที
 * เมื่อไม่มีขั้นอนุมัติเหลือ สถานะเป็น "อนุมัติแล้ว"
 */
function plc_sign(string $entity, array $row, array $g, string $slot, string $comment = ''): void
{
    $uid = (int)current_user()['id'];
    if (!in_array($slot, plc_signable_slots($entity, $row, $g), true)) {
        throw new RuntimeException('คุณไม่สามารถลงนามในช่องนี้ได้ในขณะนี้');
    }
    if (!current_user()['signature']) {
        throw new RuntimeException('กรุณาเพิ่มลายมือชื่อของคุณก่อน (เมนู PLC → ลายมือชื่อของฉัน)');
    }
    $comment = mb_substr(trim($comment), 0, 500);
    do {
        db_exec('INSERT INTO plc_signatures (entity, entity_id, slot, user_id, comment) VALUES (?,?,?,?,?)', 'sisis', [$entity, $row['id'], $slot, $uid, $comment ?: null]);
        $slots = plc_slots($entity, $row, $g);
        $next = plc_pending_slot($entity, $row, $g);
        $slot = ($slots[$slot]['type'] === 'approve' && $next && $slots[$next]['user_id'] === $uid) ? $next : null;
    } while ($slot);
    if (in_array($entity, ['group', 'problem'], true) && $row['status'] === 'submitted' && !plc_pending_slot($entity, $row, $g)) {
        db_exec('UPDATE ' . plc_entity_table($entity) . " SET status = 'approved' WHERE id = ?", 'i', [$row['id']]);
    }
}

/** ส่งขออนุมัติ: ถ้าไม่มีผู้อนุมัติที่มีบัญชีในระบบเลย ถือว่าอนุมัติแล้ว (ลงนามในกระดาษ) */
function plc_submit(string $entity, array $row, array $g): void
{
    db_exec('UPDATE ' . plc_entity_table($entity) . " SET status = 'submitted', reject_note = NULL WHERE id = ?", 'i', [$row['id']]);
    $row['status'] = 'submitted';
    if (!plc_pending_slot($entity, $row, $g)) {
        db_exec('UPDATE ' . plc_entity_table($entity) . " SET status = 'approved' WHERE id = ?", 'i', [$row['id']]);
    }
}

/** ส่งกลับแก้ไข / ถอนเรื่อง: กลับเป็นร่างและล้างการอนุมัติ (ลายมือชื่อผู้จัดทำยังอยู่) */
function plc_return_to_draft(string $entity, array $row, array $g, ?string $note = null): void
{
    $approve = array_keys(array_filter(plc_slots($entity, $row, $g), fn($s) => $s['type'] === 'approve'));
    foreach ($approve as $slot) {
        db_exec('DELETE FROM plc_signatures WHERE entity = ? AND entity_id = ? AND slot = ?', 'sis', [$entity, $row['id'], $slot]);
    }
    db_exec('UPDATE ' . plc_entity_table($entity) . " SET status = 'draft', reject_note = ? WHERE id = ?", 'si', [$note, $row['id']]);
}

/** แก้ไขเนื้อหาแล้ว: ล้างลายมือชื่อทั้งหมดของเอกสาร (ต้องลงนามใหม่) */
function plc_clear_signatures(string $entity, int $id): void
{
    db_exec('DELETE FROM plc_signatures WHERE entity = ? AND entity_id = ?', 'si', [$entity, $id]);
}

/** จัดการคำสั่งลงนาม/ส่ง/ส่งกลับ/ถอนจากฟอร์ม (ใช้ร่วมกันทุกหน้า) คืนข้อความผลลัพธ์ */
function plc_handle_workflow(string $entity, array $row, array $g, bool $canEdit): string
{
    $act = post('wf');
    $label = ['group' => 'กลุ่ม PLC', 'problem' => 'ประเด็นปัญหา', 'meeting' => 'บันทึกการประชุม', 'observation' => 'แบบสังเกตการสอน'][$entity];
    if ($act === 'sign') {
        plc_sign($entity, $row, $g, post('slot'), post('comment'));
        log_activity('plc_sign', $label . ' #' . $row['id'] . ' ' . post('slot'));
        return 'ลงนามแล้ว';
    }
    if ($act === 'submit' && $canEdit && ($row['status'] ?? '') === 'draft') {
        plc_submit($entity, $row, $g);
        log_activity('plc_submit', $label . ' #' . $row['id']);
        return 'ส่งขออนุมัติแล้ว';
    }
    if ($act === 'reject' && plc_pending_slot($entity, $row, $g) && in_array(plc_pending_slot($entity, $row, $g), plc_signable_slots($entity, $row, $g), true)) {
        $note = mb_substr(trim(post('comment')), 0, 500);
        if ($note === '') throw new RuntimeException('กรุณาระบุเหตุผลที่ส่งกลับแก้ไข');
        plc_return_to_draft($entity, $row, $g, $note . ' — ' . current_user()['fullname']);
        log_activity('plc_reject', $label . ' #' . $row['id'] . ' ' . $note);
        return 'ส่งกลับให้แก้ไขแล้ว';
    }
    if ($act === 'withdraw' && ($canEdit || is_staff()) && in_array($row['status'] ?? '', ['submitted', 'approved'], true)) {
        if ($row['status'] === 'approved' && !is_staff()) throw new RuntimeException('เอกสารที่อนุมัติแล้ว ถอนกลับได้เฉพาะเจ้าหน้าที่');
        plc_return_to_draft($entity, $row, $g);
        log_activity('plc_withdraw', $label . ' #' . $row['id']);
        return 'ถอนกลับเป็นร่างแล้ว แก้ไขได้';
    }
    throw new RuntimeException('ไม่สามารถดำเนินการได้');
}

/* ---------------- ลายมือชื่อ ---------------- */

/** บันทึกลายมือชื่อจากไฟล์ (PNG/JPG) หรือจากการเซ็นบนหน้าจอ (data URL PNG) — เก็บในเครื่องเสมอ */
function plc_save_signature(int $uid, ?array $file, string $dataUrl = ''): string
{
    if ($dataUrl !== '') {
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($dataUrl, $prefix)) throw new RuntimeException('ข้อมูลลายมือชื่อไม่ถูกต้อง');
        $bin = base64_decode(substr($dataUrl, strlen($prefix)), true);
        if ($bin === false || strlen($bin) > 1024 * 1024) throw new RuntimeException('ลายมือชื่อมีขนาดใหญ่เกินไป');
        $info = @getimagesizefromstring($bin);
        if (!$info || $info[2] !== IMAGETYPE_PNG) throw new RuntimeException('ลายมือชื่อต้องเป็นภาพ PNG');
        $ext = 'png';
    } else {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('กรุณาเลือกไฟล์ภาพลายมือชื่อ');
        if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('ไฟล์ลายมือชื่อต้องไม่เกิน 2 MB');
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) throw new RuntimeException('ลายมือชื่อต้องเป็นไฟล์ PNG หรือ JPG');
        $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $bin = file_get_contents($file['tmp_name']);
    }
    $dir = UPLOAD_DIR . '/signatures';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บลายมือชื่อได้');
    $name = 'signatures/u' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (file_put_contents(UPLOAD_DIR . '/' . $name, $bin) === false) throw new RuntimeException('บันทึกลายมือชื่อไม่สำเร็จ');
    $old = db_val('SELECT signature FROM users WHERE id = ?', 'i', [$uid]);
    db_exec('UPDATE users SET signature = ? WHERE id = ?', 'si', [$name, $uid]);
    if ($old && str_starts_with($old, 'signatures/')) @unlink(UPLOAD_DIR . '/' . $old);
    return $name;
}

function plc_signature_path(?string $sig): ?string
{
    if (!$sig || !str_starts_with($sig, 'signatures/') || str_contains($sig, '..')) return null;
    $p = UPLOAD_DIR . '/' . $sig;
    return is_file($p) ? $p : null;
}

/* ---------------- ชั่วโมง PLC ---------------- */

/**
 * ชั่วโมง PLC รายสมาชิกของกลุ่ม: เวลาประชุมที่เข้าร่วม + เวลาสังเกตการสอนที่ตนเป็นผู้สังเกต
 * คืนค่า [member_id => ['member' => ..., 'meetings' => นาที, 'observe' => นาที, 'count' => ครั้ง]]
 */
function plc_hours(int $groupId): array
{
    $out = [];
    foreach (plc_members($groupId) as $m) $out[$m['id']] = ['member' => $m, 'meetings' => 0, 'observe' => 0, 'count' => 0];
    foreach (db_all('SELECT a.member_id, mt.start_time, mt.end_time FROM plc_attendees a JOIN plc_meetings mt ON mt.id = a.meeting_id WHERE mt.group_id = ?', 'i', [$groupId]) as $r) {
        if (!isset($out[$r['member_id']])) continue;
        $out[$r['member_id']]['meetings'] += plc_minutes($r['start_time'], $r['end_time']);
        $out[$r['member_id']]['count']++;
    }
    foreach (db_all('SELECT o.observer_id, o.start_time, o.end_time FROM plc_observations o JOIN plc_problems p ON p.id = o.problem_id WHERE p.group_id = ?', 'i', [$groupId]) as $r) {
        foreach ($out as $id => $row) {
            if ($row['member']['user_id'] && (int)$row['member']['user_id'] === (int)$r['observer_id']) {
                $out[$id]['observe'] += plc_minutes($r['start_time'], $r['end_time']);
                $out[$id]['count']++;
            }
        }
    }
    return $out;
}

/* ---------------- ภาพประกอบ ---------------- */

function plc_delete_photo(array $photo): void
{
    trip_delete_image_files($photo['file_name']);
    db_exec('DELETE FROM plc_photos WHERE id = ?', 'i', [$photo['id']]);
}

/** ลบไฟล์ภาพทั้งหมดของกลุ่ม/ประเด็นปัญหา (เรียกก่อนลบแถว) */
function plc_delete_photos_where(string $col, int $id): void
{
    foreach (db_all("SELECT * FROM plc_photos WHERE $col = ?", 'i', [$id]) as $ph) plc_delete_photo($ph);
}
