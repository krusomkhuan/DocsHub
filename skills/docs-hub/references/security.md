# ความปลอดภัย — เช็กลิสต์ก่อนส่งโค้ด

## บัญชีและเซสชัน
- รหัสผ่าน `password_hash()` / `password_verify()` อย่างน้อย 8 ตัวอักษร · เข้าสู่ระบบผิด 5 ครั้งใน session ล็อก 5 นาที · `session_regenerate_id(true)` หลังเข้าสู่ระบบ
- คุกกี้ `DOCSHUBSESSID` HttpOnly, SameSite=Lax, path = BASE_URL
- ผู้ใช้โหลดจาก DB ทุกคำขอ: `status` ต้อง `active` (pending/disabled = ออกจากระบบ)
- บทบาท: `member` < `staff` < `admin` · `is_staff()` รวม admin · ผู้ดูแลลืมรหัสผ่านใช้ `tools/reset-admin.php` (CLI) เท่านั้น ห้ามฝังรหัสผ่านในโค้ด

## คำขอ
- ทุก POST: `csrf_check()` (token ใน `$_SESSION['csrf']`, ฟอร์มใส่ `csrf_field()`) · GET ห้ามเปลี่ยนข้อมูล
- `redirect()` รับเฉพาะ path ภายใน/URL ที่ระบบสร้าง · OAuth ใช้ `google_safe_next()` กัน open redirect
- ค่า id จาก URL ต้อง `(int)` และตรวจว่าเป็นของผู้ใช้/แผนกนั้นจริง (เช่น `ledger_entry($id, $deptId)` ผูกแผนกใน WHERE)

## สิทธิ์ข้อมูล
- เอกสาร: `can_view_document()` = staff เห็นทุกฉบับ · เจ้าของเห็นของตน · คนอื่นเห็นเฉพาะ approved และ (public หรือเข้าสู่ระบบแล้ว) · `can_edit_document()` = staff หรือเจ้าของที่ยังไม่อนุมัติ
- บัญชีแผนก: แผนกจาก `current_user()['department_id']` เท่านั้น · admin/staff ที่ไม่ได้สังกัดแผนก **ห้ามเห็น** · บันทึก log ห้ามมีจำนวนเงิน/รายละเอียด
- ทุกสคริปต์ส่งไฟล์ (`download.php`, `trip-image.php`, `ledger-file.php`, `avatar.php`) ตรวจสิทธิ์ก่อนส่ง

## ไฟล์
- `uploads/`, `backups/`, `database/`, `tools/`, `includes/` ปิดการเข้าถึงจากเว็บด้วย `.htaccess` (`Require all denied`)
- อัปโหลด: ตรวจนามสกุลกับ `ALLOWED_EXT`, ขนาดตาม `setting('max_upload_mb')`, ชื่อไฟล์สุ่ม `YYYY/MM/Ymd_His_<hex>.ext` ห้ามใช้ชื่อจากผู้ใช้เป็น path · ภาพรายงาน/ภาพโปรไฟล์ประมวลผลใหม่ด้วย GD (ตัด metadata)
- ส่งไฟล์: `X-Content-Type-Options: nosniff`, `Content-Disposition` แบบ `filename*=UTF-8''` · inline เฉพาะ pdf/jpg/jpeg/png/docx
- หน้าตรวจสอบระบบหา `.php/.phtml/...` แปลกปลอมใน `uploads/` และเทียบ SHA-256 ของไฟล์โปรแกรมกับ `database/integrity.json`

## Google
- OAuth: Authorization Code + PKCE, `state` ใช้ครั้งเดียว, `nonce`, ตรวจ `iss/aud/exp` ของ ID token, อีเมลต้อง verified, จำกัดโดเมน (`google_allowed_domain`) ได้ · ไม่เชื่อมบัญชีเดิมจากอีเมลอัตโนมัติ
- `google_client_secret`, `gdrive_refresh_token`, `gdrive_access_token` อยู่ใน `settings` — หน้าตั้งค่าไม่แสดง secret ที่บันทึกแล้ว (เว้นว่าง = ใช้ค่าเดิม) ห้ามพิมพ์ค่าเหล่านี้ใน log หรือข้อความ error
- ไฟล์สำรองมี refresh token → เก็บใน `backups/` ที่ปิดเว็บ และ Drive แบบส่วนตัว

## PDPA
- ผู้ใช้ต้องยอมรับนโยบายฉบับล่าสุด (`pdpa_enforce`) · บันทึกความยินยอมพร้อมเวลา ฉบับ IP · ลบบัญชีแบบ anonymize (`pdpa_anonymize_user`) · ประวัติการใช้งานลบอัตโนมัติตาม `log_retention_days`
- ฟีเจอร์ใหม่ที่เก็บข้อมูลส่วนบุคคลเพิ่ม → อัปเดต `pdpa_export_user`, `pdpa_anonymize_user` และนโยบายใน `pdpa_default_policy.php`

## ข้อห้ามในชุดซอร์ส/คำตอบ
- ห้ามใส่ข้อมูลจริงจาก `database/docshub.sql` ของเครื่องผู้ใช้ (อาจเป็นไฟล์ export ที่มีผู้ใช้/hash/IP) — ใช้ `tools/dump-schema.php --install` สร้างไฟล์ติดตั้งที่สะอาด
