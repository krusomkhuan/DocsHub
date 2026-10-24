<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$where = '1=1';
$types = '';
$params = [];
if (get('q') !== '') {
    $where .= ' AND (l.action LIKE ? OR l.detail LIKE ? OR u.username LIKE ?)';
    $like = '%' . get('q') . '%';
    $types = 'sss';
    $params = [$like, $like, $like];
}
$p = paginate((int)db_val("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $where", $types, $params), 50);
$logs = db_all("SELECT l.*, u.username, u.fullname FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
                WHERE $where ORDER BY l.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);

$pageTitle = 'บันทึกการใช้งาน';
$activeMenu = 'logs';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card shadow-sm">
  <div class="card-header bg-white">
    <form class="d-flex gap-2" style="max-width:420px">
      <input class="form-control form-control-sm" name="q" placeholder="ค้นหา action / รายละเอียด / ผู้ใช้" value="<?= e(get('q')) ?>">
      <button class="btn btn-sm btn-primary">ค้นหา</button>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>รายละเอียด</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="small text-nowrap"><?= thai_date($l['created_at'], true, true) ?></td>
        <td class="small"><?= e($l['fullname'] ?? '-') ?></td>
        <td><code><?= e($l['action']) ?></code></td>
        <td class="small"><?= e($l['detail']) ?></td>
        <td class="small text-muted"><?= e($l['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="mt-3"><?= pagination_links($p) ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
