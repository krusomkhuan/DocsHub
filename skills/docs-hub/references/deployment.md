# ติดตั้ง อัปเดต และดูแลระบบ

## เครื่องพัฒนา (XAMPP, Windows)
1. วางโฟลเดอร์ที่ `C:\xampp\htdocs\docshub` เปิด Apache + MySQL
2. ตรวจ `includes/config.php` (DB_HOST/USER/PASS/NAME/PORT) — ค่าเริ่มต้น root / ไม่มีรหัสผ่าน / `docshub`
3. เปิด `http://localhost/docshub/` → `install.php` ตั้งผู้ดูแล → สร้าง `includes/installed.lock`
4. ไฟล์ใหญ่: แก้ `upload_max_filesize`, `post_max_size` ใน `C:\xampp\php\php.ini` · ต้องมีส่วนขยาย mysqli, gd, zip, curl, fileinfo, mbstring
5. PHP CLI: `C:\xampp\php\php.exe` (บน Git Bash ใช้ `MSYS_NO_PATHCONV=1` และ path แบบ `C:/...`)

## เซิร์ฟเวอร์จริง
- `https://krusomkhuan.com/docshub/` · ผู้ดูแลอัปโหลดไฟล์ที่แก้เองทีละไฟล์ (ไม่มี git/CI)
- **ห้ามเขียนทับ:** `uploads/`, `backups/`, `includes/installed.lock` · `includes/config.php` ให้แก้เฉพาะบรรทัด `APP_VERSION` (ค่าฐานข้อมูลบนเซิร์ฟเวอร์ต่างจากเครื่องพัฒนา)
- **ไม่ต้องอัปโหลด:** `skills/` (สกิล ChatGPT สำหรับนักพัฒนา)
- หลังอัปโหลด: เปิดเว็บ 1 ครั้ง (migrate อัตโนมัติ) → หลังบ้าน → ตรวจสอบระบบ → **บันทึกค่าอ้างอิง**
- ไฟล์ CSS/JS เปลี่ยน → แนะนำผู้ใช้กด Ctrl+F5
- ตรวจจากภายนอกได้โดยไม่ต้องเข้าสู่ระบบ: เวอร์ชันแสดงท้ายหน้าแรก, `install.php` ต้องขึ้น "ระบบติดตั้งเรียบร้อยแล้ว", `includes/`, `uploads/`, `backups/`, `database/`, `tools/` ต้องตอบ 403

## ตั้งค่าในหลังบ้าน (ตั้งค่าระบบ)
- ทั่วไป: ชื่อระบบ สโลแกน ชื่อวิทยาลัย **URL ของระบบ** (`site_url` ใช้สร้าง redirect URI และลิงก์แชร์) URL วิทยาลัย รูปแบบเลขหนังสือ ขนาดไฟล์ การรับสมัคร
- เข้าสู่ระบบด้วย Google + ปุ่มทดสอบ Client · จัดเก็บไฟล์ใน Google Drive + ทดสอบการเชื่อมต่อ (ดู storage-google.md)

## สำรองและกู้คืน
- หลังบ้าน → สำรอง / กู้คืนข้อมูล (เต็ม/เฉพาะฐานข้อมูล, ดาวน์โหลด, อัตโนมัติทุก N วัน, เก็บล่าสุด N ไฟล์, สำเนาขึ้น Drive)
- Task Scheduler: `tools\backup-task.bat` (บันทึกผล `backups\backup-task.log`)
- เว็บเสีย: `C:\xampp\php\php.exe C:\xampp\htdocs\docshub\tools\restore.php [ไฟล์.zip] [--db-only|--files-only] [--yes]`

## เครื่องมือบรรทัดคำสั่ง (`tools/`, CLI เท่านั้น)
| คำสั่ง | ใช้ทำอะไร |
|---|---|
| `tools\check.php` | ตรวจสอบระบบ (exit 1 เมื่อมีรายการไม่ผ่าน) |
| `tools\backup.php [--full]` | สำรองข้อมูล (+ส่งสำเนาไป Drive) |
| `tools\restore.php` | แสดงรายการ/กู้คืนไฟล์สำรอง |
| `tools\move-to-drive.php` | ดึงภาพโปรไฟล์กลับ → จัดไฟล์ใน Drive → ย้ายไฟล์ในเครื่องขึ้น Drive |
| `tools\reset-admin.php` | ตั้งรหัสผ่านผู้ดูแลใหม่ / สร้างผู้ดูแลเพิ่ม |
| `tools\dump-schema.php [--install]` | พิมพ์โครงสร้างฐานข้อมูล / ไฟล์ติดตั้งที่สะอาด (ใช้สร้างสกิล) |

## ค่าอ้างอิงไฟล์ (integrity)
- `database/integrity.json` เก็บ SHA-256 ของไฟล์ .php/.js/.css/.sql/.htaccess/.bat (ข้าม uploads, backups, skills)
- บนเครื่องพัฒนาหลังแก้ไข: `php -r "require 'includes/bootstrap.php'; require 'includes/system_check.php'; sc_save_integrity();"` แล้วอัปโหลด `database/integrity.json` ไปพร้อมไฟล์อื่น
