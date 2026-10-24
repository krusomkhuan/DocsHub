<?php
/**
 * บัญชีรายรับ-รายจ่ายของแผนกวิชา
 * สิทธิ์: ดู/บันทึกได้เฉพาะผู้ใช้ที่สังกัดแผนกนั้น (users.department_id) เท่านั้น — ไม่มีข้อยกเว้น
 *         ผู้ดูแลระบบและเจ้าหน้าที่ไม่เห็นบัญชีของแผนกที่ตนไม่ได้สังกัด
 *         ผู้ดูแลระบบเป็นผู้แต่งตั้ง "ผู้รับผิดชอบบัญชี" ของแต่ละแผนก (admin/ledger-managers.php)
 * บันทึกการใช้งาน (activity_logs) เก็บเฉพาะเลขรายการ ไม่เก็บจำนวนเงิน/รายละเอียด เพื่อไม่ให้ผู้อื่นเห็นข้อมูลผ่านหน้า log
 */

function ledger_kinds(): array
{
    return ['income' => 'รายรับ', 'expense' => 'รายจ่าย'];
}

/** แผนกที่ผู้ใช้ปัจจุบันสังกัด (เข้าถึงบัญชีได้) หรือ null */
function ledger_department(): ?array
{
    $u = current_user();
    if (!$u || !$u['department_id']) return null;
    return departments()[(int)$u['department_id']] ?? null;
}

/** ต้องเข้าสู่ระบบและสังกัดแผนก คืนค่าแผนกของผู้ใช้เอง (ไม่รับค่าแผนกจาก URL) */
function require_ledger_access(): array
{
    require_login();
    $dept = ledger_department();
    if (!$dept) {
        http_response_code(403);
        $pageTitle = 'บัญชีแผนก';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-warning"><i class="bi bi-shield-lock me-1"></i>บัญชีรายรับ-รายจ่ายดูได้เฉพาะครูประจำแผนกวิชา บัญชีของคุณยังไม่ได้ระบุแผนกวิชา กรุณาติดต่อผู้ดูแลระบบ</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $dept;
}

/** ผู้รับผิดชอบบัญชีของแผนก (เฉพาะที่ยังสังกัดแผนกและบัญชีใช้งานอยู่) */
function ledger_managers(int $deptId): array
{
    return db_all("SELECT u.id, u.fullname, u.avatar FROM ledger_managers m JOIN users u ON u.id = m.user_id
                   WHERE m.department_id = ? AND u.department_id = m.department_id AND u.status = 'active' ORDER BY u.fullname", 'i', [$deptId]);
}

/**
 * สิทธิ์ในบัญชีแผนกของผู้ใช้ปัจจุบัน (เรียกหลัง require_ledger_access เท่านั้น)
 *  - ผู้รับผิดชอบบัญชี: บันทึก แก้ไข/ลบได้ทุกรายการ จัดการหมวดและตั้งค่า
 *  - แผนกที่ยังไม่มีผู้รับผิดชอบบัญชี: ครูทุกคนบันทึกได้ แก้ไขได้ทุกรายการ ลบได้เฉพาะของตน จัดการหมวดได้
 *  - แผนกที่มีผู้รับผิดชอบบัญชี: ครูทั่วไปดูได้ และบันทึก/แก้ไข/ลบเฉพาะรายการของตนเมื่อแผนกเปิดให้บันทึก
 */
function ledger_perms(array $dept): array
{
    $uid = (int)current_user()['id'];
    $managers = array_map('intval', array_column(ledger_managers((int)$dept['id']), 'id'));
    if (in_array($uid, $managers, true)) {
        return ['add' => true, 'edit_any' => true, 'delete_any' => true, 'own' => true, 'manage' => true, 'is_manager' => true, 'has_manager' => true];
    }
    if (!$managers) {
        return ['add' => true, 'edit_any' => true, 'delete_any' => false, 'own' => true, 'manage' => true, 'is_manager' => false, 'has_manager' => false];
    }
    // ครูทุกคนในแผนกบันทึกและแก้ไขรายการได้ทุกรายการ (แก้รายการที่บันทึกผิด) · ลบได้เฉพาะรายการของตน
    return ['add' => true, 'edit_any' => true, 'delete_any' => false, 'own' => true, 'manage' => false, 'is_manager' => false, 'has_manager' => true];
}

function ledger_can_edit(array $perms, array $entry): bool
{
    return $perms['edit_any'] || ($perms['own'] && (int)$entry['created_by'] === (int)current_user()['id']);
}

function ledger_can_delete(array $perms, array $entry): bool
{
    return $perms['delete_any'] || ($perms['own'] && (int)$entry['created_by'] === (int)current_user()['id']);
}

/** ดึงรายการบัญชีเฉพาะของแผนกที่ระบุ (กันการเข้าถึงข้ามแผนกด้วยการเปลี่ยน id ใน URL) */
function ledger_entry(int $id, int $deptId): ?array
{
    return db_row('SELECT e.*, c.name AS cat_name, cu.fullname AS creator, uu.fullname AS updater
                   FROM ledger_entries e LEFT JOIN ledger_categories c ON c.id = e.category_id
                   LEFT JOIN users cu ON cu.id = e.created_by LEFT JOIN users uu ON uu.id = e.updated_by
                   WHERE e.id = ? AND e.department_id = ?', 'ii', [$id, $deptId]);
}

/** หมวดรายรับ/รายจ่ายของแผนก สร้างหมวดเริ่มต้นให้เมื่อใช้งานครั้งแรก */
function ledger_categories(int $deptId): array
{
    $rows = db_all('SELECT * FROM ledger_categories WHERE department_id = ? ORDER BY kind, sort_order, name', 'i', [$deptId]);
    if (!$rows) {
        $defaults = [
            'income'  => ['เงินงบประมาณ', 'เงินรายได้สถานศึกษา', 'เงินบริจาค / สนับสนุน', 'รายได้จากการให้บริการ', 'รายรับอื่น ๆ'],
            'expense' => ['วัสดุฝึก', 'วัสดุสำนักงาน', 'ครุภัณฑ์', 'ค่าซ่อมบำรุง', 'ค่าเดินทาง / ไปราชการ', 'กิจกรรมนักเรียน', 'รายจ่ายอื่น ๆ'],
        ];
        foreach ($defaults as $kind => $names) {
            foreach ($names as $i => $name) {
                db_exec('INSERT INTO ledger_categories (department_id, kind, name, sort_order) VALUES (?,?,?,?)', 'issi', [$deptId, $kind, $name, $i + 1]);
            }
        }
        $rows = db_all('SELECT * FROM ledger_categories WHERE department_id = ? ORDER BY kind, sort_order, name', 'i', [$deptId]);
    }
    return $rows;
}

/** ปีงบประมาณ พ.ศ. ของวันที่ (1 ต.ค. – 30 ก.ย.) */
function fiscal_year_be(string $date): int
{
    $ts = strtotime($date);
    return (int)date('Y', $ts) + 543 + ((int)date('n', $ts) >= 10 ? 1 : 0);
}

/** ช่วงวันที่ของปีงบประมาณ [เริ่ม, สิ้นสุด] */
function fiscal_year_range(int $fyBe): array
{
    $y = $fyBe - 543;
    return [($y - 1) . '-10-01', $y . '-09-30'];
}

function money(float $n): string
{
    return number_format($n, 2);
}
