---
name: docs-hub
description: Build, extend, fix, install or explain "Docs Hub" ([ศูนย์รวมงานจัดเก็บเอกสาร]) — a Thai document-archive web app for วิทยาลัยการอาชีพโพนทอง written in procedural PHP 8 + MySQLi + Bootstrap 5.3 for XAMPP, by ครูสมควร สากำ. Covers documents by type, book-number registry, trip reports with photos, photo-album links, department income/expense ledger, PDPA, Google login, Google Drive storage, backup/restore and system check. Use when the user asks to add a feature, change a page, write a migration, debug, review security, deploy to the server, or configure Google/Drive. ใช้เมื่อผู้ใช้ต้องการพัฒนา แก้ไข ติดตั้ง หรืออธิบายระบบ Docs Hub
---

# Docs Hub — คู่มือสำหรับผู้ช่วย AI ที่พัฒนาระบบนี้

Docs Hub คือเว็บศูนย์รวมเอกสารของวิทยาลัยการอาชีพโพนทอง (เวอร์ชัน 1.23.1, โครงสร้างฐานข้อมูล v13) ใช้งานจริงที่ `https://krusomkhuan.com/docshub/` ผู้เยี่ยมชมค้นหา/ดาวน์โหลดเอกสารสาธารณะ สมาชิก (ครู) อัปโหลดเอกสาร ออกเลขหนังสือ เขียนรายงานไปราชการ ส่งลิงก์อัลบั้มภาพ และใช้บัญชีรายรับ-รายจ่ายของแผนก เจ้าหน้าที่/ผู้ดูแลจัดการทุกอย่างในหลังบ้าน (`admin/`)

**เทคโนโลยี:** PHP 8.0 แบบ procedural (ไม่มี framework, ไม่มี Composer, ไม่มี class ของระบบ) · MariaDB 10.4 / MySQL ผ่าน **mysqli prepared statements** ด้วยฟังก์ชัน `db_query/db_all/db_row/db_val/db_exec` · Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 จาก jsdelivr · ฟอนต์ Prompt/Sarabun · XAMPP บน Windows (เครื่องพัฒนา) และโฮสต์ Apache (เซิร์ฟเวอร์จริง ผู้ใช้อัปโหลดไฟล์เองทีละไฟล์)

## ไฟล์ในสกิลนี้

| ไฟล์ | อ่านเมื่อ |
|---|---|
| `references/architecture.md` | ต้องรู้โครงสร้างไฟล์ การโหลดหน้า และหน้าที่ของแต่ละไฟล์ใน `includes/` |
| `references/conventions.md` | **ก่อนเขียนโค้ดทุกครั้ง** — รูปแบบหน้าเว็บ/หลังบ้าน ฟอร์ม migration พร้อมตัวอย่างจริง |
| `references/security.md` | งานที่แตะสิทธิ์ ฟอร์ม ไฟล์อัปโหลด การดาวน์โหลด หรือข้อมูลส่วนบุคคล |
| `references/features.md` | กฎธุรกิจของแต่ละโมดูล (เอกสาร เลขหนังสือ ไปราชการ คลังรูปภาพ บัญชีแผนก PDPA) |
| `references/storage-google.md` | Google Drive (โฟลเดอร์ตามประเภท, `gdrive:` refs), เข้าสู่ระบบด้วย Google, ปัญหา OAuth |
| `references/database.md` + `references/schema.sql` | ออกแบบตาราง เขียน query หรือ migration |
| `references/deployment.md` | ติดตั้ง อัปโหลดขึ้นเซิร์ฟเวอร์ สำรอง/กู้คืน ตรวจสอบระบบ เครื่องมือบรรทัดคำสั่ง |
| `references/testing.md` | วิธีทดสอบจริงก่อนส่งงาน และกฎการล้างข้อมูลทดสอบ |
| `assets/docs-hub-source.zip` | ซอร์สโค้ดทั้งระบบ (ไม่มีไฟล์ที่อัปโหลด ไฟล์สำรอง หรือรหัสผ่านจริง) — แตกไฟล์เพื่ออ่านโค้ดจริงแทนการเดา |

ถ้ามีเครื่องมือรันโค้ด (Code Interpreter) ให้แตก `assets/docs-hub-source.zip` แล้วเปิดไฟล์จริงก่อนแก้ไข อย่าเขียนโค้ดจากความจำเมื่อมีไฟล์ให้อ่าน

## กฎที่ห้ามละเมิด

1. **ฐานข้อมูล:** ใช้ `db_query/db_all/db_row/db_val/db_exec($sql, $types, $params)` เท่านั้น ทุกค่าจากผู้ใช้ผ่าน placeholder `?` **ห้ามใช้ PDO** ห้ามต่อสตริง SQL ด้วยค่าจากผู้ใช้ (ค่าตัวเลขที่ cast `(int)` แล้วหรือ whitelist เท่านั้นที่ต่อได้)
2. **ฟอร์ม:** ทุก POST มี `<?= csrf_field() ?>` และฝั่งรับเรียก `csrf_check()` ก่อนทำอะไร ห้ามเปลี่ยนข้อมูลผ่าน GET · หลังบันทึกใช้ `flash()` + `redirect()` (PRG)
3. **การแสดงผล:** ทุกค่าใน HTML ผ่าน `e()` ลิงก์ภายในใช้ `url('path')` ลิงก์เต็มใช้ `abs_url()`
4. **สิทธิ์:** หน้าสมาชิก `require_login()` · หลังบ้าน `require_staff()` หรือ `require_admin()` · ตรวจสิทธิ์ระดับรายการฝั่งเซิร์ฟเวอร์เสมอ (`can_view_document`, `can_edit_document`, `trip_can_*`, `album_can_edit`, `ledger_perms`)
5. **บัญชีแผนก:** ใช้แผนกจากบัญชีผู้ใช้ (`require_ledger_access()`) เท่านั้น **ห้ามรับรหัสแผนกจาก URL/ฟอร์ม** ผู้ดูแลระบบที่ไม่ได้สังกัดแผนกก็ห้ามเห็น
6. **ไฟล์:** อัปโหลดผ่าน `save_upload($file, $category)` (ตรวจนามสกุล `ALLOWED_EXT` ขนาด สุ่มชื่อ แล้ว `storage_push` ขึ้น Drive ตามหมวด) ดาวน์โหลดผ่านสคริปต์ที่ตรวจสิทธิ์ + `storage_serve()` ห้ามลิงก์ไปที่ `uploads/` ตรง ๆ · ภาพโปรไฟล์เก็บในเครื่องที่ `uploads/docs/avatars` เสมอ
7. **โครงสร้างฐานข้อมูล:** เปลี่ยนตาราง = เพิ่มขั้น `if ($dbVersion < N)` ใน `includes/migrate.php` และเพิ่ม `DB_SCHEMA_LATEST` ห้ามสั่งให้ผู้ใช้รัน SQL เอง (ระบบปรับอัตโนมัติเมื่อเปิดเว็บ)
8. **ภาษาและเวลา:** UI ภาษาไทยทั้งหมด · utf8mb4 · เวลา Asia/Bangkok แสดงด้วย `thai_date()` (พ.ศ.) · ปีงบประมาณ 1 ต.ค.–30 ก.ย. (`fiscal_year_be`)
9. **ความเข้ากันได้:** PHP 8.0 (ห้าม enum, readonly, `never`, first-class callable syntax ที่ต้อง 8.1) ไม่เพิ่ม Composer · ไลบรารี JS โหลดจาก cdn.jsdelivr.net ระบุเวอร์ชันแน่นอน
10. **ความลับ:** ห้ามใส่ Client secret, refresh token หรือรหัสผ่านจริงในโค้ด/คำตอบ ค่าเหล่านี้อยู่ในตาราง `settings` และตั้งผ่านหลังบ้าน
11. **ความจริงใจ:** ห้ามบอกว่าทดสอบผ่านถ้ายังไม่ได้รันจริง ห้ามส่งโค้ดจำลอง/TODO แทนฟังก์ชันหลัก บอกให้ชัดว่าอะไรยังไม่ได้ทดสอบ (เช่น การเชื่อมต่อ Google จริงบนเซิร์ฟเวอร์)

## ขั้นตอนเพิ่มฟีเจอร์ (ทำตามลำดับ)

1. **ฐานข้อมูล** — ตาราง/คอลัมน์ใหม่: เพิ่มขั้นถัดไปใน `includes/migrate.php` (ล่าสุด v13 → ใช้ v14) และเพิ่ม `DB_SCHEMA_LATEST` ค่าเริ่มต้นของ settings ใช้ `INSERT IGNORE`
2. **ฟังก์ชันกลาง** — กฎธุรกิจของโมดูลไว้ใน `includes/<โมดูล>.php` (โหลดใน `includes/bootstrap.php`)
3. **หน้าเว็บ** — หน้าบ้านที่ราก (`xxx.php` + `includes/header.php`/`footer.php`, ตั้ง `$pageTitle`, `$activeNav`) หรือหลังบ้านใน `admin/` (`admin_header.php`, `$activeMenu`, เพิ่มเมนูใน `$menu` ของ `admin_header.php`)
4. **สิทธิ์และบันทึก** — `log_activity('action_key', 'รายละเอียดสั้น')` ทุกการเปลี่ยนแปลงสำคัญ (ไม่บันทึกข้อมูลอ่อนไหว เช่น จำนวนเงิน)
5. **คู่มือ** — อัปเดต `manual.php` (หัวข้อที่เกี่ยวข้อง) และ `README.md`
6. **เวอร์ชัน** — ขยับ `APP_VERSION` ใน `includes/config.php` และบรรทัดเวอร์ชันใน README (ฟีเจอร์ = MINOR, แก้บั๊ก = PATCH)
7. **ทดสอบ** — ตาม `references/testing.md` แล้วบอกผลจริง
8. **ค่าอ้างอิงไฟล์** — แจ้งผู้ใช้ให้กด "บันทึกค่าอ้างอิง" ในหน้าตรวจสอบระบบหลังอัปโหลด (หรือรัน `sc_save_integrity()` บนเครื่องพัฒนา)

## รูปแบบคำตอบที่ผู้ใช้ต้องการ

- ตอบเป็นภาษาไทย ใช้ศัพท์เทคนิคภาษาอังกฤษได้ กระชับ เป็นขั้นตอน
- เมื่อแก้โค้ด บอกชื่อไฟล์เต็มและแสดงโค้ดทั้งฟังก์ชันหรือทั้งบล็อกที่แทนที่ ไม่ใช่ส่วนย่อยที่เดาตำแหน่งยาก
- **ทุกครั้งที่แก้ไข ให้จบด้วยรายการไฟล์ที่ต้องอัปโหลดขึ้นเซิร์ฟเวอร์** (ระบุไฟล์ใหม่ และเตือนว่า `includes/config.php` แก้เฉพาะบรรทัด `APP_VERSION` ห้ามเขียนทับ `uploads/`, `backups/`, `includes/installed.lock`)
- บอก "สิ่งที่ต้องทดสอบหลังอัปโหลด" และ "สิ่งที่ยังไม่ได้ทดสอบ" ตรงไปตรงมา
- ถ้าคำขอกำกวมจนเลือกผิดแล้วเสียงานมาก (ใครเห็นข้อมูล เก็บไฟล์ที่ไหน) ให้ถามก่อน ถ้าไม่มากให้เลือกค่าที่ปลอดภัยและบอกว่าเลือกอะไร
