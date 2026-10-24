# กฎธุรกิจของแต่ละโมดูล

## เอกสาร (`documents`, `doc_types`, `categories`)
- ประเภทเริ่มต้น (slug): `orders` คำสั่งราชการ · `certificates` เกียรติบัตร · `lesson-plans` แผนการสอน · `course-plans` โครงการสอน · `textbooks` หนังสือเรียน · `regulation` (เพิ่มภายหลัง) — ผู้ดูแลเพิ่มประเภทได้ (`admin/doc-types.php`) กำหนด icon, สี, `has_subject`, `has_semester`, `has_order_no`, `owner_label`
- ลบประเภทได้เมื่อไม่มีเอกสาร · หมวดหมู่ย่อยต่อประเภท (`categories.type_id`) · หมวดที่ `has_trip_report = 1` เปิดรายงานไปราชการ (ค่าเริ่มต้น "คำสั่งไปราชการ")
- สถานะ `pending` (สมาชิกอัปโหลด) → `approved`/`rejected` (เจ้าหน้าที่) · staff อัปโหลด = approved ทันที · `is_public` 1 = ทุกคน, 0 = เฉพาะสมาชิก
- คำสั่งราชการติ๊ก "ออกเลขคำสั่งอัตโนมัติ" ได้ (ใช้ `issue_book_number` ประเภทคำสั่ง)
- ค้นหา (`document_filters`): แยกคำด้วยช่องว่าง ทุกคำต้องพบในอย่างน้อยหนึ่งช่อง: title, doc_no, owner_name, subject_code/name, description, academic_year, file_original, semester, ชื่อประเภท, ชื่อหมวด, ชื่อ/รหัสแผนก · escape `% _` · สูงสุด 8 คำ
- ดูตัวอย่างในหน้า: PDF (iframe), รูปภาพ, Word .docx (jszip@3.10.1 + docx-preview@0.3.3 ดึงไฟล์จาก `download.php?inline=1`) · inline ไม่นับยอดดาวน์โหลด

## ปีการศึกษา (`academic_years`)
- ผู้ดูแลเพิ่ม/แก้/ลบ และกำหนดปีปัจจุบัน (`is_current`) → ค่าเริ่มต้นในฟอร์ม (`current_academic_year()`)

## เลขหนังสือราชการ (`book_numbers`, `book_number_starts`)
- ประเภท (`book_kinds()`): หนังสือส่ง / หนังสือรับ / บันทึกข้อความ / คำสั่ง · รันใหม่ทุกปี พ.ศ. แยกแผนก+ประเภท
- รูปแบบจาก `setting('book_format')` เช่น `{code} {no}/{year}` → `ชฟ 001/2569`
- ออกเลขใน transaction + `SELECT ... FOR UPDATE` บนแถวแผนก กันเลขซ้ำ · เลขที่ยกเลิกไม่นำกลับมาใช้
- เลขเริ่มต้น (staff): เลขถัดไป = max(เลขเริ่มต้น, เลขล่าสุด + 1)
- สมาชิกออกเลขได้เฉพาะแผนกตน · staff ทุกแผนก ยกเลิก/คืนสถานะ ส่งออก CSV

## รายงานการไปราชการ (`trip_reports`, `trip_report_images`)
- ผูกกับเอกสารคำสั่งในหมวดที่เปิดใช้ · ฟิลด์: เรื่อง สถานที่ วันที่ไป-กลับ ผู้ไปราชการ วัตถุประสงค์ ผล ประโยชน์ ข้อเสนอแนะ ค่าใช้จ่าย ไฟล์แนบ
- ภาพสูงสุด 30 ภาพ JPG/PNG/WEBP ย่อด้านยาว 1600px + thumb, หมุนตาม EXIF, คำบรรยาย, เรียงลำดับ · Drive: ref `gdrive:MAIN|THUMB`
- พิมพ์ A4 พร้อมช่องลงชื่อ · สิทธิ์ `trip_can_view/edit` (ผู้เขียน + staff)

## คลังรูปภาพ (`photo_albums`)
- เก็บเฉพาะลิงก์ (http/https) ตรวจลิงก์ซ้ำ (`album_duplicate`) ระบุแหล่งที่มา (`album_source`) นับจำนวนครั้งที่เปิด
- สมาชิกส่ง → pending → staff อนุมัติ/ไม่อนุมัติ (เหตุผล) · admin นำเข้า .xlsx/.csv/.tsv/.txt (≤10 MB, ≤5,000 แถว) ตรวจหัวคอลัมน์อัตโนมัติ รองรับ HYPERLINK, วันที่ Excel/พ.ศ., CSV UTF-8/Windows-874 → ตัวอย่าง → ยืนยัน

## บัญชีรายรับ-รายจ่ายแผนก (`ledger_entries`, `ledger_categories`, `ledger_managers`)
- เห็นเฉพาะครูที่ `users.department_id` = แผนกนั้น (รวม admin ก็ต้องสังกัด) · เมนู dropdown "บัญชีแผนก" + แถบเมนูย่อย `includes/ledger_nav.php` · ไม่มีแผนก → เมนูแสดงคำอธิบาย
- `ledger_perms($dept)`:
  - ผู้รับผิดชอบบัญชี (admin แต่งตั้งใน `admin/ledger-managers.php`, ต้องสังกัดแผนก): add, edit_any, delete_any, manage (หมวด)
  - ครูทั่วไป: **add + edit_any** (แก้รายการที่บันทึกผิดได้ทุกรายการ ระบบเก็บ `updated_by`) · ลบได้เฉพาะรายการของตน
  - แผนกที่ไม่มีผู้รับผิดชอบ: ครูทุกคน manage หมวดได้ด้วย
- คอลัมน์ `departments.ledger_member_can_record` ยังอยู่ในฐานข้อมูลแต่**ไม่ได้ใช้แล้ว**
- ปีงบประมาณ 1 ต.ค.–30 ก.ย. (`fiscal_year_be`, `fiscal_year_range`) · สรุป: ยอดยกมา รายรับ รายจ่าย คงเหลือ รายเดือน (ยอดสะสม) แยกหมวด · กรอง/ค้นหา CSV พิมพ์
- แชร์ LINE: ลิงก์ `https://line.me/R/share?text=` ข้อความสรุปยอด + `abs_url('ledger.php?fy=..')` · ปุ่มคัดลอกข้อความ
- หลักฐาน: `save_upload($file, 'receipt')` → Drive `uploads/receipt` · ดาวน์โหลดผ่าน `ledger-file.php`

## ผู้ใช้ (`users`)
- สมัครเอง (เปิด/ปิด `allow_register`) เลือกแผนก ยอมรับ PDPA → pending (หรือ active ถ้า `auto_approve_member`)
- เข้าสู่ระบบด้วย Google (`google_sub`, `google_email`, `has_password`) ดู storage-google.md
- ภาพโปรไฟล์ ครอปสี่เหลี่ยม 320px เก็บ `uploads/docs/avatars` (ไม่ขึ้น Drive)

## PDPA (`pdpa_policies`, `pdpa_consents`, `pdpa_requests`)
- นโยบายมีเวอร์ชัน เผยแพร่ฉบับใหม่ → ทุกคนต้องยอมรับใหม่ · คำร้อง (`pdpa_request_types()`): access, rectify, erase, withdraw, object, restrict, portability, other ครบกำหนด 30 วัน · export JSON · ถอนความยินยอม = ออกจากระบบ + สร้างคำร้องลบ
- ตั้ง DPO และ `log_retention_days` (ลบ activity_logs อัตโนมัติวันละครั้ง)

## สำรอง/กู้คืน
- .zip มี `database.sql` + manifest (+ `uploads/` ถ้าแบบเต็ม) · เก็บ `backups/` · สำรองอัตโนมัติทุก N วันเมื่อเปิดแดชบอร์ด · เก็บล่าสุด N ไฟล์ (`backup_prune`)
- ส่งสำเนาขึ้น Drive (`storage_display_path('backups')`) ดัชนีใน `settings.backup_drive_index` (อ่านด้วย db_val ตรง) · ลบไฟล์สำรอง = ย้ายสำเนาใน Drive ไปถังขยะ
- กู้คืน: ต้องยืนยันรหัสผ่าน สำรอง `-prerestore` ก่อนเสมอ · `tools/restore.php` ใช้ได้แม้เว็บเสีย (ไม่ต้องโหลดโมดูล Drive)

## ตรวจสอบระบบ
- `run_system_checks()`: PHP/ส่วนขยาย/php.ini, DB + `db_version` เทียบ `DB_SCHEMA_LATEST`, โฟลเดอร์/ดิสก์/สำรอง, การเข้าถึงตรงจากเว็บ (ต้อง 403), สคริปต์แปลกปลอมใน uploads, ไฟล์หาย, Google Drive, ความถูกต้องของไฟล์ (SHA-256 กับ `database/integrity.json`, ข้ามโฟลเดอร์ uploads/backups/skills)
