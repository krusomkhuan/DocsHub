# รูปแบบโค้ดของ Docs Hub (ทำตามนี้ทุกครั้ง)

เขียนให้เหมือนโค้ดรอบข้าง: procedural, คอมเมนต์ภาษาไทยสั้น ๆ เหนือบล็อกที่ไม่ชัดเจน, ชื่อฟังก์ชัน snake_case นำหน้าด้วยชื่อโมดูล (`ledger_*`, `trip_*`, `album_*`, `pdpa_*`, `storage_*`, `gdrive_*`, `google_*`, `backup_*`, `sc_*`)

## ฐานข้อมูล

```php
$row  = db_row('SELECT * FROM documents WHERE id = ?', 'i', [$id]);           // แถวเดียวหรือ null
$rows = db_all('SELECT id, name FROM departments ORDER BY sort_order, id');    // array ของแถว
$n    = (int)db_val('SELECT COUNT(*) FROM users WHERE status = ?', 's', ['pending']);
db_exec('UPDATE departments SET name = ? WHERE id = ?', 'si', [$name, $id]);
$newId = (int)db_val('SELECT LAST_INSERT_ID()');
```
- types: `i` int, `d` float, `s` string · จำนวน `?` = ความยาว types = จำนวน params
- ค่าตั้งค่า: `setting('key', 'ค่าเริ่มต้น')` (แคชในคำขอ) · เขียน: `INSERT ... ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)` (ดู `gdrive_save_setting`) ถ้าต้องอ่านค่าที่เพิ่งเขียนในคำขอเดียวกัน ใช้ `db_val` ตรงแทน `setting()`
- งานที่ต้องกันชนกัน (เลขหนังสือ) ใช้ `$db->begin_transaction()` + `SELECT ... FOR UPDATE`

## หน้าบ้าน (ไฟล์ที่ราก)

```php
<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();                       // หรือไม่ต้อง ถ้าเป็นหน้าสาธารณะ

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = mb_substr(post('name'), 0, 150);      // post()/get() trim ค่าให้แล้ว
    if ($name === '') {
        flash('danger', 'กรุณากรอกชื่อ');
    } else {
        db_exec('INSERT INTO ... VALUES (?)', 's', [$name]);
        log_activity('xxx_add', $name);
        flash('success', 'บันทึกแล้ว');
    }
    redirect('this-page.php');            // PRG เสมอ
}

$items = db_all('SELECT ...');
$pageTitle = 'ชื่อหน้า';
$activeNav = 'docs';                      // home|docs|trip|albums|book|mydocs|ledger|manual
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">หน้าแรก</a></li>
  <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
</ol></nav>
<div class="card shadow-sm"><div class="card-body">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <input class="form-control" name="name" value="<?= e($x['name'] ?? '') ?>">
    <button class="btn btn-primary">บันทึก</button>
  </form>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- หลาย action ในหน้าเดียว: `<input type="hidden" name="action" value="...">` หรือ `<button name="action" value="...">` แล้วแยกด้วย `if (post('action') === '...')`
- ยืนยันก่อนลบ: `<form method="post" data-confirm="ลบรายการนี้?">` (จัดการใน `assets/js/app.js`)
- ปุ่มคัดลอก: `<button type="button" data-copy="ข้อความ">` · แสดงข้อความแจ้งจาก `flash()` อัตโนมัติใน header

## หลังบ้าน (`admin/xxx.php`)

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();                          // หรือ require_staff()
// ... POST เหมือนหน้าบ้าน ...
$pageTitle = 'ชื่อหน้า';
$activeMenu = 'key';                      // ตรงกับ key ใน $menu ของ includes/admin_header.php
require __DIR__ . '/../includes/admin_header.php';
?>
<h4 class="mb-3"><?= e($pageTitle) ?></h4>
...
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
```
เพิ่มเมนูใหม่: เพิ่มแถวใน `$menu` ของ `includes/admin_header.php` `['key', 'file.php', 'bi-icon', 'ชื่อเมนู', จำนวนแจ้งเตือน, adminOnly]`

## Migration (`includes/migrate.php`)

```php
// v14: คำอธิบายสั้นว่าเพิ่มอะไร
if ($dbVersion < 14) {
    $db->query("ALTER TABLE xxx ADD COLUMN yyy TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'คำอธิบาย'");
    $db->query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('new_key', 'default')");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '14') ON DUPLICATE KEY UPDATE svalue = '14'");
}
```
และเปลี่ยน `define('DB_SCHEMA_LATEST', 14)` ด้านบนไฟล์ · ตารางใหม่ใช้ `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4` · ทำให้รันซ้ำได้ปลอดภัย (`IF NOT EXISTS`, `INSERT IGNORE`)

## ไฟล์อัปโหลด

```php
try {
    $info = save_upload($_FILES['file'], 'doc:' . $type['slug']);   // หรือ 'receipt', 'trip-reports', ''
    // $info = ['file_name' => 'YYYY/MM/xxx.pdf' หรือ 'gdrive:<id>', 'file_original', 'file_size', 'file_ext']
} catch (RuntimeException $ex) {
    flash('danger', $ex->getMessage());
}
```
- ลบไฟล์: `delete_stored_file($row['file_name'])` (ในเครื่องหรือย้ายไปถังขยะ Drive)
- ส่งไฟล์ให้ผู้ใช้: ตรวจสิทธิ์ก่อน แล้ว `storage_serve($ref, storage_mime($original), $disposition)`

## UI

- Bootstrap 5 การ์ด `card shadow-sm`, ไอคอน `bi bi-*`, ป้ายสถานะ `status_badge()`, วันที่ `thai_date($d, true, $withTime)`, เงิน `money()`, ขนาดไฟล์ `format_size()`
- รองรับมือถือ (`table-responsive`, `col-lg-*`) และพิมพ์ (`d-print-none` กับปุ่ม/ตัวกรอง)
- ข้อความ UI ไทยทั้งหมด สุภาพ สั้น บอกวิธีแก้เมื่อผิดพลาด

## เวอร์ชันและเอกสาร

- `includes/config.php` → `APP_VERSION` · `README.md` บรรทัด `- **เวอร์ชัน:** x.y.z`
- คู่มือผู้ใช้ `manual.php` (สารบัญ `$toc`, ป้ายบทบาท `$role('สมาชิก', ...)`, ลิงก์ `$link('file.php', 'ข้อความ')`)
