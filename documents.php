<?php
require_once __DIR__ . '/includes/bootstrap.php';

$type = get('type') !== '' ? doc_type_by_slug(get('type')) : null;
$f = [
    'type_id' => $type['id'] ?? null,
    'cat'     => get('cat'),
    'dept'    => get('dept'),
    'year'    => get('year'),
    'q'       => get('q'),
];

$where = "d.status = 'approved'" . (is_logged_in() ? '' : ' AND d.is_public = 1');
$types = '';
$params = [];
document_filters($f, $where, $types, $params);

$p = paginate((int)db_val("SELECT COUNT(*) FROM documents d WHERE $where", $types, $params), 15);
$docs = db_all("SELECT d.*, t.name AS type_name, t.color, c.name AS cat_name, dp.name AS dept_name
                FROM documents d JOIN doc_types t ON t.id = d.type_id
                LEFT JOIN categories c ON c.id = d.category_id
                LEFT JOIN departments dp ON dp.id = d.department_id
                WHERE $where ORDER BY COALESCE(d.doc_date, DATE(d.created_at)) DESC, d.id DESC
                LIMIT {$p['limit']} OFFSET {$p['offset']}", $types, $params);
$years = academic_years();

$pageTitle = $type['name'] ?? 'เอกสารทั้งหมด';
$activeNav = 'docs';
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">หน้าแรก</a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>

<div class="row g-4">
  <aside class="col-lg-3">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">ประเภทเอกสาร</div>
      <div class="list-group list-group-flush">
        <?php $qs = $f['q'] !== '' ? 'q=' . urlencode($f['q']) : ''; // คงคำค้นไว้เมื่อเปลี่ยนประเภท ?>
        <a href="<?= url('documents.php' . ($qs ? '?' . $qs : '')) ?>" class="list-group-item list-group-item-action<?= !$type ? ' active' : '' ?>"><i class="bi bi-collection me-2"></i>ทั้งหมด</a>
        <?php foreach (doc_types() as $t): ?>
          <a href="<?= url('documents.php?type=' . urlencode($t['slug']) . ($qs ? '&' . $qs : '')) ?>" class="list-group-item list-group-item-action<?= $type && $type['id'] == $t['id'] ? ' active' : '' ?>">
            <i class="bi <?= e($t['icon']) ?> me-2"></i><?= e($t['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <form class="card shadow-sm" method="get">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel me-1"></i>ตัวกรอง</div>
      <div class="card-body">
        <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type['slug']) ?>"><?php endif; ?>
        <div class="mb-2">
          <label class="form-label small">คำค้นหา</label>
          <input class="form-control form-control-sm" name="q" value="<?= e($f['q']) ?>">
        </div>
        <?php if ($type): ?>
        <div class="mb-2">
          <label class="form-label small">หมวดหมู่</label>
          <select class="form-select form-select-sm" name="cat">
            <option value="">ทั้งหมด</option>
            <?php foreach (categories((int)$type['id']) as $c): ?>
              <option value="<?= $c['id'] ?>"<?= $f['cat'] == $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="mb-2">
          <label class="form-label small">แผนกวิชา / ฝ่าย</label>
          <select class="form-select form-select-sm" name="dept">
            <option value="">ทั้งหมด</option>
            <?php foreach (departments() as $dp): ?>
              <option value="<?= $dp['id'] ?>"<?= $f['dept'] == $dp['id'] ? ' selected' : '' ?>><?= e($dp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">ปีการศึกษา</label>
          <select class="form-select form-select-sm" name="year">
            <option value="">ทั้งหมด</option>
            <?php foreach ($years as $y): ?>
              <option<?= $f['year'] === (string)$y ? ' selected' : '' ?>><?= e($y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm w-100">กรองข้อมูล</button>
      </div>
    </form>
  </aside>

  <section class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0"><?= e($pageTitle) ?> <small class="text-muted fs-6">(<?= number_format($p['total']) ?> รายการ)</small></h4>
      <?php if (is_logged_in()): ?>
        <a class="btn btn-primary btn-sm" href="<?= url('upload.php' . ($type ? '?type=' . $type['id'] : '')) ?>"><i class="bi bi-cloud-upload me-1"></i>อัปโหลด</a>
      <?php endif; ?>
    </div>
    <div class="card shadow-sm">
      <div class="list-group list-group-flush">
        <?php if (!$docs): ?>
          <div class="list-group-item text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ไม่พบเอกสาร</div>
        <?php endif; ?>
        <?php foreach ($docs as $d): ?>
          <div class="list-group-item doc-item d-flex gap-3 py-3">
            <i class="bi <?= file_icon($d['file_ext']) ?> file-ic"></i>
            <div class="flex-grow-1">
              <a class="fw-medium text-decoration-none" href="<?= url('document.php?id=' . $d['id']) ?>"><?= e($d['title']) ?></a>
              <?php if (!$d['is_public']): ?><i class="bi bi-lock-fill text-muted small" title="เฉพาะสมาชิก"></i><?php endif; ?>
              <div class="small text-muted mt-1">
                <span class="badge text-bg-<?= e($d['color']) ?>"><?= e($d['type_name']) ?></span>
                <?= $d['cat_name'] ? '<span class="badge text-bg-light">' . e($d['cat_name']) . '</span>' : '' ?>
                <?= $d['doc_no'] ? 'เลขที่ ' . e($d['doc_no']) . ' · ' : '' ?>
                <?= $d['subject_code'] ? e($d['subject_code'] . ' ' . $d['subject_name']) . ' · ' : '' ?>
                <?= $d['owner_name'] ? e($d['owner_name']) . ' · ' : '' ?>
                <?= $d['dept_name'] ? e($d['dept_name']) . ' · ' : '' ?>
                <?= thai_date($d['doc_date'] ?: $d['created_at']) ?>
              </div>
            </div>
            <div class="text-end small text-nowrap">
              <a class="btn btn-outline-primary btn-sm" href="<?= url('download.php?id=' . $d['id']) ?>"><i class="bi bi-download"></i></a>
              <div class="text-muted mt-1"><?= format_size((int)$d['file_size']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mt-3"><?= pagination_links($p) ?></div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
