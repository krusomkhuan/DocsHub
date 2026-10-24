# สถาปัตยกรรม Docs Hub

ระบบแบบ procedural: แต่ละหน้าคือไฟล์ PHP หนึ่งไฟล์ที่ `require_once includes/bootstrap.php` แล้วทำงานตามลำดับ **ตรวจสิทธิ์ → รับ POST (CSRF) → query → แสดงผล**

## การโหลดทุกหน้า (`includes/bootstrap.php`)

```
config.php            ค่าคงที่: APP_VERSION, DB_*, APP_ROOT, UPLOAD_DIR, ALLOWED_EXT, BASE_URL (คำนวณอัตโนมัติ)
installed.lock?       ไม่มี → ส่งไป install.php
new mysqli(...)       global $db, utf8mb4, MYSQLI_REPORT_STRICT (error = exception)
session_start()       ชื่อ DOCSHUBSESSID, HttpOnly, SameSite=Lax, path = BASE_URL/ (ไม่เริ่มใน CLI)
functions.php         ฟังก์ชันกลาง (db_*, e, url, csrf, flash, auth, upload, ค้นหา, เลขหนังสือ)
migrate.php           ปรับโครงสร้างฐานข้อมูลอัตโนมัติตาม settings.db_version
trip_reports.php ledger.php pdpa.php photo_albums.php google_auth.php gdrive.php
$CURRENT_USER         โหลดผู้ใช้จาก DB ทุกคำขอ (ถูกระงับ = ออกจากระบบทันที)
pdpa_enforce()        ยังไม่ยอมรับนโยบายฉบับล่าสุด → ส่งไป pdpa-consent.php
```

## หน้าบ้าน (รากโปรเจกต์)

| ไฟล์ | หน้าที่ |
|---|---|
| `index.php` | หน้าแรก: ค้นหา (เลือกประเภทได้) การ์ดประเภทเอกสาร เอกสารล่าสุด สถิติ |
| `documents.php` | รายการ/ค้นหาเอกสาร (ทุกประเภทหรือตาม `?type=slug`) กรองหมวด แผนก ปีการศึกษา |
| `document.php` | รายละเอียดเอกสาร ดูตัวอย่าง PDF/รูปภาพ/Word (.docx ผ่าน docx-preview) รายงานไปราชการที่ผูกอยู่ |
| `download.php` | ส่งไฟล์เอกสารหลังตรวจ `can_view_document` (`&inline=1` = ดูในหน้า ไม่นับดาวน์โหลด) |
| `upload.php`, `my-documents.php` | สมาชิกอัปโหลด (รออนุมัติ) / เอกสารของฉัน (ใช้ `includes/document_form.php`) |
| `book-numbers.php` | ออกเลขหนังสือ (ใช้ `includes/book_numbers_page.php` ร่วมกับหลังบ้าน) |
| `trip-reports.php`, `trip-report.php`, `trip-report-form.php`, `trip-image.php` | รายงานการไปราชการและภาพประกอบ |
| `photo-albums.php`, `photo-album-form.php` | คลังลิงก์อัลบั้มภาพ / ส่งลิงก์ |
| `ledger.php`, `ledger-entry.php`, `ledger-categories.php`, `ledger-file.php` | บัญชีรายรับ-รายจ่ายแผนก (แถบเมนูย่อย `includes/ledger_nav.php`) |
| `login.php`, `logout.php`, `register.php`, `profile.php`, `avatar.php` | บัญชีผู้ใช้ ภาพโปรไฟล์ |
| `google-login.php`, `google-callback.php`, `google-register.php`, `gdrive-connect.php` | OAuth กับ Google (เข้าสู่ระบบ / เชื่อมบัญชี / เชื่อม Drive) |
| `privacy.php`, `pdpa-consent.php`, `my-data.php` | PDPA |
| `manual.php` | คู่มือการใช้งาน (สารบัญ 15 หัวข้อ พิมพ์เป็น PDF ได้) |
| `install.php` | ติดตั้งครั้งแรก (สร้างตารางจาก `database/install.sql` — ไม่มีจึงใช้ `database/docshub.sql` — + ผู้ดูแล) ล็อกด้วย `includes/installed.lock` |

## หลังบ้าน (`admin/`)

`admin_header.php` กำหนดเมนูใน `$menu = [[key, file, icon, label, badgeCount, adminOnly], ...]` หน้าใช้ `$activeMenu = 'key'`

| ไฟล์ | สิทธิ์ | หน้าที่ |
|---|---|---|
| `index.php` | staff | แดชบอร์ด + `backup_auto_if_due()` + `pdpa_purge_logs()` |
| `documents.php`, `document-form.php` | staff | จัดการ/อนุมัติเอกสารแบบหลายรายการ |
| `trip-reports.php`, `photo-albums.php`, `book-numbers.php`, `categories.php` | staff | ไปราชการ, อนุมัติลิงก์อัลบั้ม/นำเข้า (admin), ทะเบียนเลข, หมวดหมู่ |
| `doc-types.php`, `departments.php`, `ledger-managers.php`, `academic-years.php`, `users.php` | admin | ประเภทเอกสาร แผนก ผู้รับผิดชอบบัญชี ปีการศึกษา สมาชิก |
| `settings.php` | admin | ตั้งค่าทั่วไป, Google login (+ปุ่มทดสอบ Client), Google Drive (+ทดสอบการเชื่อมต่อทีละขั้น, จัดไฟล์เข้าโฟลเดอร์) |
| `pdpa.php`, `system-check.php`, `backup.php`, `logs.php` | admin | PDPA, ตรวจสอบระบบ, สำรอง/กู้คืน, บันทึกการใช้งาน |

## includes/ (ฟังก์ชันตามโมดูล)

| ไฟล์ | เนื้อหาหลัก |
|---|---|
| `functions.php` | `db_*`, `e`, `url`, `abs_url`, `redirect`, `setting`, `post/get`, CSRF, `flash`, `current_user/is_admin/is_staff/require_*`, `log_activity`, `thai_date`, ปีการศึกษา, `save_upload`, `doc_types/departments/categories`, `can_view/edit_document`, เลขหนังสือ (`book_next_number`, `issue_book_number`), `paginate`, `document_filters` (ค้นหาหลายคำทุกประเภท), `save_avatar` |
| `document_form.php` | ฟอร์มเอกสารที่ใช้ร่วมกันหน้าบ้าน/หลังบ้าน (`save_document_form`, `render_document_form`) |
| `trip_reports.php` | สิทธิ์รายงาน, ย่อ/หมุนภาพด้วย GD, เก็บภาพ main+thumb |
| `ledger.php` | `require_ledger_access`, `ledger_perms`, `ledger_can_edit/delete`, ปีงบประมาณ, `money()` |
| `pdpa.php`, `pdpa_default_policy.php` | นโยบาย ความยินยอม คำร้อง export/anonymize |
| `photo_albums.php`, `album_import.php` | ตรวจลิงก์อัลบั้ม, อ่าน xlsx/csv เพื่อนำเข้า |
| `google_auth.php` | OAuth (PKCE, state, nonce), ตรวจ ID token |
| `gdrive.php` | Drive API, ชั้นจัดเก็บไฟล์ (`storage_push/serve/delete`), โฟลเดอร์ตามประเภท, `google_check_client`, `gdrive_diagnose` |
| `backup.php` | สร้าง/อ่าน/กู้คืนไฟล์สำรอง .zip, ส่งสำเนาไป Drive |
| `system_check.php` | รายการตรวจสอบระบบ + ค่าอ้างอิง SHA-256 (`database/integrity.json`) |
| `header.php`/`footer.php`, `admin_header.php`/`admin_footer.php` | โครงหน้า + แถบเมนู (เมนูบัญชีแผนกเป็น dropdown) |

## ไฟล์อื่น

- `assets/css/style.css`, `assets/js/app.js` (ปุ่ม `data-copy`, confirm ฟอร์ม `data-confirm`, ฯลฯ)
- `database/install.sql` ไฟล์ติดตั้งที่สะอาด (สร้างด้วย `tools/dump-schema.php --install`), `database/docshub.sql` ไฟล์ของเครื่อง (ไม่อยู่ใน git อาจมีข้อมูลจริง), `database/integrity.json` ค่าอ้างอิงไฟล์
- `tools/` (CLI เท่านั้น, ปิดเว็บด้วย .htaccess): `check.php`, `backup.php`, `backup-task.bat`, `restore.php`, `move-to-drive.php`, `reset-admin.php`, `dump-schema.php`
- `uploads/` (ปิดเว็บ) `uploads/docs/YYYY/MM/...` ไฟล์ในเครื่อง, `uploads/docs/avatars/`, `uploads/docs/trips/` · `backups/` ไฟล์สำรอง (ปิดเว็บ)
