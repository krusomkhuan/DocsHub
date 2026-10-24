# Google Drive และเข้าสู่ระบบด้วย Google

ใช้ **OAuth Client (Web application) ตัวเดียวกัน** ทั้งเข้าสู่ระบบและ Drive · ค่าเก็บใน `settings`: `google_client_id`, `google_client_secret`, `google_login_enabled`, `google_allowed_domain`, `google_allow_register`, `gdrive_refresh_token`, `gdrive_access_token`, `gdrive_token_exp`, `gdrive_account_email`, `gdrive_folder_id`, `gdrive_folder_name`, `gdrive_receipt_path`, `storage_driver` (`local`|`gdrive`), แคชโฟลเดอร์ `gdrive_dir_<md5>`, `backup_drive_index`

## Redirect URI
`abs_url('google-callback.php')` = `<site_url>/google-callback.php` (เซิร์ฟเวอร์จริง `https://krusomkhuan.com/docshub/google-callback.php`) ใช้ทั้ง login, link และ drive (`google_auth_url($mode)` mode = login|link|drive) · Drive ขอ scope `https://www.googleapis.com/auth/drive`, `access_type=offline`, `prompt=consent`

## ชั้นจัดเก็บไฟล์ (`includes/gdrive.php`)
- `save_upload()` เก็บไฟล์ชั่วคราวในเครื่อง → `storage_push($rel, $name, $category)`:
  - `storage_driver = gdrive` และเชื่อมต่อครบ → อัปโหลดไป `storage_category_folder($category)` ลบไฟล์ในเครื่อง คืน `gdrive:<fileId>` · **ล้มเหลว = throw RuntimeException** (โหมด Drive เท่านั้น ไม่มี fallback)
  - ไม่เปิด Drive → คืน path ในเครื่อง
- `storage_serve($ref, $mime, $disposition)` ส่งไฟล์ (stream จาก Drive ด้วย access token) · `storage_delete($ref)` ลบ/ย้ายไปถังขยะ · `storage_is_drive`, `storage_drive_ids` (ภาพไปราชการ `gdrive:MAIN|THUMB`)
- หมวด (`$category`): `doc:<slug>` เอกสารตามประเภท · `receipt` ใบเสร็จ · `trip-reports` ไปราชการ · `backups` ไฟล์สำรอง · `''` อื่น ๆ

## โฟลเดอร์ (`storage_category_path`)
| หมวด | เส้นทางใต้โฟลเดอร์ Folder ID |
|---|---|
| `doc:orders` | `uploads/commands` |
| `doc:lesson-plans`, `doc:course-plans` | `uploads/plans` |
| `doc:textbooks` | `uploads/books` |
| `doc:certificates` | `uploads/certs` |
| `receipt` | `setting('gdrive_receipt_path', 'uploads/receipt')` |
| `trip-reports` | `uploads/trips/reports` |
| `backups` | `uploads/backups` |
| อื่น ๆ | `uploads/docs` |

- กำหนดที่ค่าคงที่ `STORAGE_DOC_FOLDERS`, `STORAGE_OTHER_FOLDER`, `STORAGE_TRIP_FOLDER`, `STORAGE_BACKUP_FOLDER`
- **ถ้า Folder ID ชี้ที่โฟลเดอร์ชื่อ `uploads` เอง** (`gdrive_folder_name = uploads`) ระบบตัด `uploads/` ข้างหน้าออก → ไม่สร้าง `uploads/uploads` · แสดงผลด้วย `storage_display_path($cat)`
- `gdrive_ensure_path` หา/สร้างโฟลเดอร์ทีละชั้น ใช้ `$memo` ในคำขอ + `orderBy=createdTime` (ชื่อซ้ำใช้โฟลเดอร์เก่าสุด) กันสร้างซ้ำเพราะผลค้นหาของ Drive มีดีเลย์ · `storage_category_folder` จำ id ไว้ใน settings
- `storage_ensure_all_folders()` สร้างทุกโฟลเดอร์ · `storage_organize_drive()` ย้ายไฟล์ที่อยู่ผิดโฟลเดอร์ (รวมไฟล์สำรอง) · `storage_migrate_to_drive()` ย้ายไฟล์ในเครื่องขึ้น Drive (ใช้ผ่าน `tools/move-to-drive.php` เท่านั้น ไม่มีปุ่มในหน้าตั้งค่า)
- ภาพโปรไฟล์ไม่ขึ้น Drive (`storage_pull_avatars_from_drive` ดึงกลับ)

## การทดสอบ/วินิจฉัย (หลังบ้าน → ตั้งค่าระบบ)
- **ทดสอบ Client ID / Secret** (`google_check_client`): ส่ง refresh token ปลอมไป token endpoint → `invalid_grant` = คู่ ID/Secret ถูกต้อง · `invalid_client` = ผิด/ไม่ใช่คู่เดียวกัน
- **ทดสอบการเชื่อมต่อ** Drive (`gdrive_diagnose`) ทีละขั้น: Client → ขอ access token → โฟลเดอร์ (`gdrive_folder_info`: เป็นโฟลเดอร์, ไม่อยู่ถังขยะ, canAddChildren) → อัปโหลดไฟล์ทดสอบในโฟลเดอร์ใบเสร็จแล้วย้ายไปถังขยะ → โฟลเดอร์ย่อยทั้งหมด · แสดง ✓/✗ และปุ่ม "เชื่อมต่อ Google Drive ใหม่" เมื่อเป็นปัญหาสิทธิ์

## ข้อผิดพลาดที่พบบ่อย
| ข้อความ | สาเหตุ | วิธีแก้ |
|---|---|---|
| `unauthorized_client` ตอนอัปโหลด | refresh token ออกให้ Client ID ตัวเดิม (เปลี่ยน Client ID แล้ว) | กด "เชื่อมต่อ Google Drive" ใหม่ด้วยบัญชีเจ้าของโฟลเดอร์ |
| `invalid_grant` | สิทธิ์หมดอายุ/ถูกเพิกถอน (consent screen แบบ External+Testing หมดอายุ 7 วัน) | เชื่อมต่อใหม่ · ใช้ consent screen แบบ Internal |
| `invalid_client` | ID/Secret ผิดหรือคนละคู่ | วางทั้งสองค่าใหม่จาก client เดียวกัน แล้วกดทดสอบ |
| `redirect_uri_mismatch` | URI ใน Google Cloud ไม่ตรง (http/https, www, / ท้าย) หรือ `site_url` ผิด | ตั้ง URL ของระบบ แล้วคัดลอก URI จากหน้าตั้งค่า |
| 403 จาก Drive API | ยังไม่เปิด Google Drive API ในโปรเจกต์ / ไม่มีสิทธิ์แก้ไขโฟลเดอร์ | Enable API · แชร์สิทธิ์ Editor |
| path ซ้อน `uploads/uploads/...` | Folder ID = โฟลเดอร์ uploads (เวอร์ชันก่อน 1.22.1) | อัปเดตแล้วกด "จัดไฟล์ใน Drive เข้าโฟลเดอร์ตามประเภท" |

ลิงก์ Google Cloud Console: สร้างโปรเจกต์ `console.cloud.google.com/projectcreate` · consent `.../apis/credentials/consent` · สร้าง client `.../apis/credentials/oauthclient` · Drive API `.../apis/library/drive.googleapis.com`
