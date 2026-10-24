<?php
/**
 * PDPA (พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562)
 * - นโยบายความเป็นส่วนตัวแบบมีเวอร์ชัน: ผู้ใช้ต้องยอมรับฉบับล่าสุดก่อนใช้งานระบบหลังเข้าสู่ระบบ
 * - บันทึกการให้/ถอนความยินยอม (pdpa_consents) พร้อมเวลา IP และเบราว์เซอร์
 * - คำร้องใช้สิทธิ์ของเจ้าของข้อมูล (pdpa_requests) ผู้ดูแลระบบดำเนินการภายใน 30 วัน
 * - ส่งออกข้อมูลของตนเอง (JSON) และทำให้ข้อมูลไม่สามารถระบุตัวตนได้ (anonymize) เมื่ออนุมัติคำขอลบ
 * - ลบประวัติการใช้งานที่เกินระยะเวลาเก็บรักษาอัตโนมัติ
 */

function pdpa_request_types(): array
{
    return [
        'access'      => 'ขอเข้าถึง/ขอสำเนาข้อมูล',
        'rectify'     => 'ขอแก้ไขข้อมูลให้ถูกต้อง',
        'erase'       => 'ขอลบหรือทำให้ไม่สามารถระบุตัวตนได้',
        'withdraw'    => 'ถอนความยินยอม',
        'object'      => 'คัดค้านการประมวลผล',
        'restrict'    => 'ขอให้ระงับการใช้ข้อมูล',
        'portability' => 'ขอรับข้อมูลในรูปแบบอิเล็กทรอนิกส์',
        'other'       => 'อื่น ๆ',
    ];
}

function pdpa_status_label(string $s): string
{
    $map = ['pending' => ['warning', 'รอดำเนินการ'], 'processing' => ['info', 'กำลังดำเนินการ'], 'done' => ['success', 'ดำเนินการแล้ว'], 'rejected' => ['secondary', 'ปฏิเสธ']];
    [$c, $t] = $map[$s] ?? ['secondary', $s];
    return '<span class="badge text-bg-' . $c . '">' . $t . '</span>';
}

function pdpa_current_policy(): ?array
{
    static $p = false;
    if ($p === false) {
        $p = db_row('SELECT * FROM pdpa_policies ORDER BY version DESC LIMIT 1');
    }
    return $p;
}

/** แปลงเนื้อหานโยบาย (ข้อความ + ## หัวข้อ + - รายการ) เป็น HTML พร้อมแทนค่าตัวแปร */
function pdpa_render(string $content): string
{
    $vars = [
        '{college_name}'       => setting('college_name'),
        '{site_name}'          => setting('site_name'),
        '{dpo_name}'           => setting('pdpa_dpo_name') ?: '(ยังไม่ได้ระบุ)',
        '{dpo_email}'          => setting('pdpa_dpo_email') ?: '(ยังไม่ได้ระบุ)',
        '{dpo_phone}'          => setting('pdpa_dpo_phone') ?: '(ยังไม่ได้ระบุ)',
        '{log_retention_days}' => setting('log_retention_days', '365'),
    ];
    $html = '';
    $inList = false;
    foreach (preg_split('/\R/', strtr($content, $vars)) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '- ')) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . e(substr($line, 2)) . '</li>';
            continue;
        }
        if ($inList) { $html .= '</ul>'; $inList = false; }
        if ($line === '') continue;
        $html .= str_starts_with($line, '## ') ? '<h5 class="mt-4">' . e(substr($line, 3)) . '</h5>' : '<p class="mb-2">' . e($line) . '</p>';
    }
    return $html . ($inList ? '</ul>' : '');
}

/** ความยินยอมล่าสุดของผู้ใช้ */
function pdpa_last_consent(int $uid): ?array
{
    return db_row('SELECT * FROM pdpa_consents WHERE user_id = ? ORDER BY id DESC LIMIT 1', 'i', [$uid]);
}

function pdpa_has_consented(int $uid): bool
{
    $p = pdpa_current_policy();
    if (!$p) return true;
    $c = pdpa_last_consent($uid);
    return $c && $c['action'] === 'accept' && (int)$c['policy_version'] >= (int)$p['version'];
}

function pdpa_record_consent(int $uid, string $action): void
{
    db_exec('INSERT INTO pdpa_consents (user_id, policy_version, action, ip, user_agent) VALUES (?,?,?,?,?)', 'iisss',
        [$uid, (int)(pdpa_current_policy()['version'] ?? 1), $action, client_ip(), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

/** ผู้ใช้ที่ยังไม่ยอมรับนโยบายฉบับล่าสุด ต้องยอมรับก่อนใช้งานหน้าอื่น */
function pdpa_enforce(): void
{
    if (PHP_SAPI === 'cli' || !is_logged_in()) return;
    $allowed = ['pdpa-consent.php', 'privacy.php', 'logout.php', 'avatar.php'];
    if (in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $allowed, true)) return;
    if (!pdpa_has_consented((int)current_user()['id'])) {
        redirect('pdpa-consent.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

/** ลบประวัติการใช้งานที่เก่ากว่าระยะเวลาเก็บรักษา (เรียกวันละครั้งจากแดชบอร์ด) */
function pdpa_purge_logs(): int
{
    $days = (int)setting('log_retention_days', '365');
    if ($days < 30 || setting('log_purged_on') === date('Y-m-d')) return 0;
    db_exec("INSERT INTO settings (skey, svalue) VALUES ('log_purged_on', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", 's', [date('Y-m-d')]);
    global $db;
    db_query('DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', 'i', [$days]);
    return $db->affected_rows;
}

/** รวบรวมข้อมูลส่วนบุคคลทั้งหมดของผู้ใช้ (สิทธิขอเข้าถึง/ขอรับข้อมูล) */
function pdpa_export_user(int $uid): array
{
    $u = db_row('SELECT u.id, u.username, u.fullname, u.email, u.phone, u.role, u.status, u.created_at, u.last_login, d.name AS department
                 FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ?', 'i', [$uid]);
    return [
        'exported_at'      => date('c'),
        'controller'       => setting('college_name'),
        'system'           => setting('site_name') . ' ' . APP_VERSION,
        'account'          => $u,
        'google_account'   => db_val('SELECT google_email FROM users WHERE id = ?', 'i', [$uid]),
        'has_avatar'       => (bool)db_val('SELECT avatar IS NOT NULL FROM users WHERE id = ?', 'i', [$uid]),
        'consents'         => db_all('SELECT policy_version, action, ip, created_at FROM pdpa_consents WHERE user_id = ? ORDER BY id', 'i', [$uid]),
        'requests'         => db_all('SELECT request_type, detail, status, response, created_at, handled_at FROM pdpa_requests WHERE user_id = ? ORDER BY id', 'i', [$uid]),
        'documents'        => db_all('SELECT id, title, doc_no, created_at FROM documents WHERE uploaded_by = ? ORDER BY id', 'i', [$uid]),
        'trip_reports'     => db_all('SELECT id, subject, place, start_date, end_date, created_at FROM trip_reports WHERE created_by = ? ORDER BY id', 'i', [$uid]),
        'book_numbers'     => db_all('SELECT full_no, subject, book_date FROM book_numbers WHERE created_by = ? ORDER BY id', 'i', [$uid]),
        'ledger_entries'   => (int)db_val('SELECT COUNT(*) FROM ledger_entries WHERE created_by = ?', 'i', [$uid]),
        'activity_logs'    => db_all('SELECT action, detail, ip, created_at FROM activity_logs WHERE user_id = ? ORDER BY id', 'i', [$uid]),
    ];
}

/**
 * ทำให้ข้อมูลบัญชีไม่สามารถระบุตัวตนได้ (ตามคำขอลบที่อนุมัติ)
 * เอกสารราชการที่ผู้ใช้เคยอัปโหลดยังคงอยู่ตามระเบียบงานสารบรรณ แต่ไม่ผูกกับตัวตนอีกต่อไป
 */
function pdpa_anonymize_user(int $uid): void
{
    $u = db_row('SELECT * FROM users WHERE id = ?', 'i', [$uid]);
    if (!$u) return;
    delete_stored_file($u['avatar']);
    db_exec("UPDATE users SET username = ?, fullname = ?, email = NULL, phone = NULL, avatar = NULL, google_sub = NULL, google_email = NULL, status = 'disabled',
             password_hash = ?, anonymized_at = NOW() WHERE id = ?", 'sssi',
        ['deleted_' . $uid, 'ผู้ใช้ที่ลบข้อมูลแล้ว #' . $uid, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $uid]);
    db_exec('UPDATE activity_logs SET ip = NULL WHERE user_id = ?', 'i', [$uid]);
    db_exec('UPDATE pdpa_consents SET ip = NULL, user_agent = NULL WHERE user_id = ?', 'i', [$uid]);
    db_exec('DELETE FROM ledger_managers WHERE user_id = ?', 'i', [$uid]);
}
