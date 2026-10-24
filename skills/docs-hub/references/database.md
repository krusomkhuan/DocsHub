# ฐานข้อมูล

- ฐานข้อมูล `docshub` (MariaDB 10.4, InnoDB, utf8mb4) · โครงสร้างล่าสุด **v13** (`settings.db_version`, `DB_SCHEMA_LATEST` ใน `includes/migrate.php`)
- โครงสร้างเต็มดู `schema.sql` (สร้างด้วย `tools/dump-schema.php`, ไม่มีข้อมูล)
- ติดตั้งใหม่: `install.php` รัน `database/install.sql` (ถ้าไม่มีใช้ `database/docshub.sql`) แล้วสร้างผู้ดูแล → เปิดเว็บครั้งแรก `migrate.php` ปรับจนถึงเวอร์ชันล่าสุด
- `database/install.sql` (อยู่ใน git และในชุดซอร์สของสกิล) สร้างด้วย `tools/dump-schema.php --install` (`skills/build_skill.py` สร้างใหม่ให้ทุกครั้ง) (โครงสร้าง + ข้อมูลตั้งต้น: ประเภทเอกสาร หมวด แผนก ปีการศึกษา นโยบาย PDPA และ settings ที่ไม่ลับ · ไม่มีผู้ใช้)

## ตาราง

| ตาราง | เก็บอะไร | คอลัมน์สำคัญ |
|---|---|---|
| `users` | สมาชิก | username, password_hash, fullname, email, phone, department_id, role (member/staff/admin), status (pending/active/disabled), avatar, google_sub, google_email, has_password, anonymized_at |
| `departments` | แผนกวิชา/ฝ่าย | code (ใช้ในเลขหนังสือ), name, sort_order, ledger_member_can_record (เลิกใช้) |
| `doc_types` | ประเภทเอกสาร | slug, name, icon, color, has_subject, has_semester, has_order_no, owner_label |
| `categories` | หมวดย่อยของประเภท | type_id, name, has_trip_report |
| `documents` | เอกสาร | type_id, category_id, department_id, title, doc_no, doc_date, academic_year, semester, subject_code/name, owner_name, description, file_name (path หรือ `gdrive:`), file_original, file_size, file_ext, is_public, status, downloads, views, uploaded_by |
| `academic_years` | ปีการศึกษา | year_be, start_date, end_date, is_current |
| `book_numbers` | ทะเบียนเลขหนังสือ | department_id, book_kind (out/in/memo/order), year_be, running_no, full_no, book_date, subject, to_whom, note, document_id, is_cancelled, created_by |
| `book_number_starts` | เลขเริ่มต้น | department_id, book_kind, year_be, start_no, set_by, set_at |
| `trip_reports`, `trip_report_images` | รายงานไปราชการ + ภาพ | document_id, subject, travelers, place, start_date, end_date, purpose, result, benefit, suggestion, expense, file_*, is_public, created_by / report_id, file_name, caption, sort_order, width, height |
| `photo_albums` | ลิงก์อัลบั้มภาพ | title, url, event_date, academic_year, department_id, description, status, reject_reason, clicks, submitted_by, approved_by |
| `ledger_entries` | รายการบัญชี | department_id, kind (income/expense), entry_date, category_id, description, amount, ref_no, counterparty, note, file_*, created_by, updated_by |
| `ledger_categories`, `ledger_managers` | หมวดบัญชีต่อแผนก / ผู้รับผิดชอบบัญชี | department_id, kind, name / department_id, user_id, assigned_by |
| `pdpa_policies`, `pdpa_consents`, `pdpa_requests` | PDPA | version, title, content, effective_date / user_id, policy_version, action, ip / user_id, requester_name, request_type, detail, status, response, handled_by, handled_at |
| `activity_logs` | บันทึกการใช้งาน | user_id, action, detail (≤255), ip, created_at |
| `settings` | ค่าตั้งค่า key-value | skey, svalue |

ตรวจชื่อคอลัมน์จริงใน `schema.sql` ทุกครั้งก่อนเขียน query (ตารางข้างบนเป็นสรุป)

## หลักการ
- ทุก query ผ่าน `db_*` + prepared statements · ตัวกรองแบบไดนามิกสร้าง `$where`, `$types`, `$params` แบบ `document_filters()`
- จำนวนเงิน `DECIMAL` แสดงด้วย `money()` · วันที่ `DATE`/`DATETIME` แสดงด้วย `thai_date()`
- ตารางใหม่ต้องเพิ่มใน migrate (ไม่ต้องแก้ไฟล์ .sql ด้วยมือ — รัน `py skills\build_skill.py` เพื่อสร้าง `database/install.sql` ใหม่) · ถ้าข้อมูลใหม่เป็นข้อมูลส่วนบุคคล → อัปเดต PDPA export/anonymize
- การทดสอบบนฐานข้อมูลจริง: ลบเฉพาะแถวที่สร้างจากการทดสอบ (ตั้งชื่อขึ้นต้น `ZZTEST`/`zzt_`) ห้าม TRUNCATE หรือลบข้อมูลจริง
