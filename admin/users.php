<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$me = (int)current_user()['id'];
$errors = [];
$edit = null;
if (get('edit') !== '') {
    $edit = get('edit') === 'new' ? [] : db_row('SELECT * FROM users WHERE id = ?', 'i', [(int)get('edit')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'quick' && $id && $id !== $me) {
        $status = post('status');
        if (in_array($status, ['active', 'disabled', 'pending'], true)) {
            db_exec('UPDATE users SET status = ? WHERE id = ?', 'si', [$status, $id]);
            log_activity('user_status', '#' . $id . ' ' . $status);
            flash('success', 'ปรับสถานะสมาชิกแล้ว');
        }
        redirect('admin/users.php?' . http_build_query($_GET));
    }

    if ($action === 'delete' && $id && $id !== $me) {
        delete_stored_file(db_val('SELECT avatar FROM users WHERE id = ?', 'i', [$id]));
        db_exec('DELETE FROM users WHERE id = ?', 'i', [$id]);
        log_activity('user_delete', '#' . $id);
        flash('success', 'ลบสมาชิกแล้ว (เอกสารและเลขหนังสือที่เคยสร้างยังคงอยู่)');
        redirect('admin/users.php');
    }

    if ($action === 'save') {
        $username = post('username');
        $data = [
            'fullname'      => mb_substr(post('fullname'), 0, 150),
            'email'         => post('email') ?: null,
            'phone'         => post('phone') ?: null,
            'department_id' => isset(departments()[(int)post('department_id')]) ? (int)post('department_id') : null,
            'role'          => in_array(post('role'), ['admin', 'staff', 'member'], true) ? post('role') : 'member',
            'status'        => in_array(post('status'), ['active', 'pending', 'disabled'], true) ? post('status') : 'active',
        ];
        if ($id === $me) {
            $data['role'] = 'admin';      // ป้องกันลดสิทธิ์ตนเอง
            $data['status'] = 'active';
        }
        $password = (string)($_POST['password'] ?? '');
        if ($data['fullname'] === '') $errors[] = 'กรุณากรอกชื่อ-สกุล';
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        if (!$id) {
            if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . ยาว 4-30 ตัวอักษร';
            elseif (db_val('SELECT id FROM users WHERE username = ?', 's', [$username])) $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
            if (strlen($password) < 8) $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errors[] = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
        }

        if (!$errors) {
            if ($id) {
                db_exec('UPDATE users SET fullname=?, email=?, phone=?, department_id=?, role=?, status=? WHERE id=?', 'sssissi', array_merge(array_values($data), [$id]));
                if ($password !== '') {
                    db_exec('UPDATE users SET password_hash = ?, has_password = 1 WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                log_activity('user_update', '#' . $id);
            } else {
                $id = db_exec('INSERT INTO users (fullname, email, phone, department_id, role, status, username, password_hash) VALUES (?,?,?,?,?,?,?,?)',
                    'sssissss', array_merge(array_values($data), [$username, password_hash($password, PASSWORD_DEFAULT)]));
                log_activity('user_create', $username);
            }
            // ภาพโปรไฟล์ (ผู้ดูแลอัปโหลดแทน/ลบภาพไม่เหมาะสมได้)
            $oldAvatar = db_val('SELECT avatar FROM users WHERE id = ?', 'i', [$id]);
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newAvatar = save_avatar($_FILES['avatar'], $id);
                    delete_stored_file($oldAvatar);
                    db_exec('UPDATE users SET avatar = ? WHERE id = ?', 'si', [$newAvatar, $id]);
                } catch (RuntimeException $ex) {
                    flash('warning', $ex->getMessage());
                }
            } elseif (post('remove_avatar') === '1' && $oldAvatar) {
                delete_stored_file($oldAvatar);
                db_exec('UPDATE users SET avatar = NULL WHERE id = ?', 'i', [$id]);
            }
            if (post('google_unlink') === '1') {
                db_exec('UPDATE users SET google_sub = NULL, google_email = NULL WHERE id = ?', 'i', [$id]);
                log_activity('google_unlink_admin', '#' . $id);
            }
            flash('success', 'บันทึกข้อมูลสมาชิกแล้ว');
            redirect('admin/users.php');
        }
        $edit = array_merge($edit ?? [], $data, ['id' => $id, 'username' => $username]);
    }
}

$where = '1=1';
$types = '';
$params = [];
if (in_array(get('status'), ['pending', 'active', 'disabled'], true)) { $where .= ' AND u.status = ?'; $types .= 's'; $params[] = get('status'); }
if (in_array(get('role'), ['admin', 'staff', 'member'], true)) { $where .= ' AND u.role = ?'; $types .= 's'; $params[] = get('role'); }
if (get('q') !== '') {
    $where .= ' AND (u.username LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)';
    $like = '%' . get('q') . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$p = paginate((int)db_val("SELECT COUNT(*) FROM users u WHERE $where", $types, $params), 25);
$users = db_all("SELECT u.*, d.name AS dept_name, (SELECT COUNT(*) FROM documents x WHERE x.uploaded_by = u.id) AS docs
                 FROM users u LEFT JOIN departments d ON d.id = u.department_id
                 WHERE $where ORDER BY FIELD(u.status,'pending','active','disabled'), u.created_at DESC
                 LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);

$pageTitle = 'จัดการสมาชิก';
$activeMenu = 'users';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($edit !== null): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold"><?= !empty($edit['id']) ? 'แก้ไขสมาชิก: ' . e($edit['username']) : 'เพิ่มสมาชิกใหม่' ?></div>
  <div class="card-body">
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="row g-3" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="col-md-4"><label class="form-label">ชื่อผู้ใช้</label><input class="form-control" name="username" value="<?= e($edit['username'] ?? '') ?>" <?= !empty($edit['id']) ? 'disabled' : 'required' ?>></div>
      <div class="col-md-4"><label class="form-label">ชื่อ-สกุล</label><input class="form-control" name="fullname" required value="<?= e($edit['fullname'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">รหัสผ่าน<?= !empty($edit['id']) ? ' (เว้นว่างหากไม่เปลี่ยน)' : '' ?></label><input type="password" class="form-control" name="password" minlength="8" <?= empty($edit['id']) ? 'required' : '' ?> autocomplete="new-password"></div>
      <div class="col-md-4"><label class="form-label">อีเมล</label><input type="email" class="form-control" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
      <div class="col-md-4 d-flex align-items-center gap-2">
        <?= avatar_html($edit + ['fullname' => '?', 'id' => 0], 56) ?>
        <div class="flex-grow-1"><label class="form-label mb-1">ภาพโปรไฟล์</label><input type="file" class="form-control form-control-sm" name="avatar" accept="image/jpeg,image/png,image/webp">
          <?php if (!empty($edit['avatar'])): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="rmav"><label class="form-check-label small" for="rmav">ลบภาพ</label></div><?php endif; ?>
          <?php if (!empty($edit['google_sub'])): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="google_unlink" value="1" id="gul"><label class="form-check-label small" for="gul">ยกเลิกการเชื่อม Google (<?= e($edit['google_email']) ?>)</label></div><?php endif; ?>
        </div>
      </div>
      <div class="col-md-4"><label class="form-label">แผนก / ฝ่าย</label>
        <select class="form-select" name="department_id"><option value="">— ไม่ระบุ —</option>
          <?php foreach (departments() as $dp): ?><option value="<?= $dp['id'] ?>"<?= (int)($edit['department_id'] ?? 0) === (int)$dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label">สิทธิ์</label>
        <select class="form-select" name="role"<?= (int)($edit['id'] ?? 0) === $me ? ' disabled' : '' ?>>
          <?php foreach (['member', 'staff', 'admin'] as $r): ?><option value="<?= $r ?>"<?= ($edit['role'] ?? 'member') === $r ? ' selected' : '' ?>><?= role_label($r) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">สมาชิก: อัปโหลด/ออกเลขของแผนกตน · เจ้าหน้าที่: จัดการเอกสาร/เลขหนังสือทุกแผนก · ผู้ดูแล: ทุกอย่าง</div></div>
      <div class="col-md-4"><label class="form-label">สถานะ</label>
        <select class="form-select" name="status"<?= (int)($edit['id'] ?? 0) === $me ? ' disabled' : '' ?>>
          <?php foreach (['active' => 'ใช้งาน', 'pending' => 'รออนุมัติ', 'disabled' => 'ระงับ'] as $k => $lbl): ?><option value="<?= $k ?>"<?= ($edit['status'] ?? 'active') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-12"><button class="btn btn-primary">บันทึก</button> <a class="btn btn-outline-secondary" href="<?= url('admin/users.php') ?>">ยกเลิก</a></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <form class="row g-2">
      <div class="col-md-4"><input class="form-control form-control-sm" name="q" placeholder="ค้นหาชื่อผู้ใช้ ชื่อ อีเมล" value="<?= e(get('q')) ?>"></div>
      <div class="col-md-2"><select class="form-select form-select-sm" name="status"><option value="">ทุกสถานะ</option>
        <?php foreach (['pending' => 'รออนุมัติ', 'active' => 'ใช้งาน', 'disabled' => 'ระงับ'] as $k => $lbl): ?><option value="<?= $k ?>"<?= get('status') === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select form-select-sm" name="role"><option value="">ทุกสิทธิ์</option>
        <?php foreach (['member', 'staff', 'admin'] as $r): ?><option value="<?= $r ?>"<?= get('role') === $r ? ' selected' : '' ?>><?= role_label($r) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">ค้นหา</button></div>
      <div class="col-md-2"><a class="btn btn-sm btn-success w-100" href="?edit=new"><i class="bi bi-person-plus"></i> เพิ่มสมาชิก</a></div>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>ชื่อ-สกุล</th><th>แผนก</th><th>สิทธิ์</th><th>สถานะ</th><th class="text-end">เอกสาร</th><th>เข้าใช้ล่าสุด</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $r): ?>
      <tr>
        <td><div class="d-flex align-items-center gap-2"><?= avatar_html($r, 36) ?><div><?= e($r['fullname']) ?><div class="small text-muted"><?= e($r['username']) ?><?= $r['email'] ? ' · ' . e($r['email']) : '' ?><?= $r['google_sub'] ? ' <i class="bi bi-google text-danger" title="เชื่อม Google: ' . e($r['google_email']) . '"></i>' : '' ?></div></div></div></td>
        <td class="small"><?= e($r['dept_name']) ?></td>
        <td class="small"><?= role_label($r['role']) ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td class="text-end"><?= (int)$r['docs'] ?></td>
        <td class="small"><?= thai_date($r['last_login'], true, true) ?></td>
        <td class="text-end text-nowrap">
          <?php if ((int)$r['id'] !== $me): ?>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="quick"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <?php if ($r['status'] !== 'active'): ?>
                <button class="btn btn-sm btn-success" name="status" value="active" title="อนุมัติ/เปิดใช้งาน"><i class="bi bi-check-lg"></i></button>
              <?php else: ?>
                <button class="btn btn-sm btn-outline-warning" name="status" value="disabled" title="ระงับ"><i class="bi bi-slash-circle"></i></button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
          <?php if ((int)$r['id'] !== $me): ?>
            <form method="post" class="d-inline" data-confirm="ลบสมาชิก <?= e($r['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
