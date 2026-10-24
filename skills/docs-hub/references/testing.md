# วิธีทดสอบ Docs Hub (ไม่มีชุดทดสอบอัตโนมัติ — ทดสอบจริงทุกครั้ง)

## ขั้นต่ำก่อนส่งงาน
1. **Lint ทุกไฟล์ที่แก้:** `C:\xampp\php\php.exe -l ไฟล์.php`
2. **เปิดหน้าจริง:** `curl -s http://localhost/docshub/หน้า.php` แล้วตรวจว่าไม่มี `Fatal`, `Warning:`, `Notice:` และสถานะ HTTP ถูกต้อง (หน้าที่ต้องเข้าสู่ระบบจะได้ 302)
3. **หน้าที่ต้องเข้าสู่ระบบ:** รันผ่าน PHP CLI โดยตั้ง session เอง (CLI ไม่บังคับ PDPA consent) เช่น

```php
<?php // as.php — php as.php <username> <page> "<query>"
$_SERVER += ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'localhost',
             'REQUEST_URI' => '/docshub/' . $argv[2], 'SCRIPT_NAME' => '/docshub/' . $argv[2]];
parse_str($argv[3] ?? '', $_GET);
session_start();
$m = new mysqli('localhost', 'root', '', 'docshub');
$_SESSION['uid'] = (int)$m->query("SELECT id FROM users WHERE username='" . $m->real_escape_string($argv[1]) . "'")->fetch_row()[0];
chdir('C:/xampp/htdocs/docshub');
require 'C:/xampp/htdocs/docshub/' . $argv[2];
```
   ทดสอบ POST: ตั้ง `$_SERVER['REQUEST_METHOD']='POST'`, `$_SESSION['csrf']='t'`, `$_POST=['csrf'=>'t', ...]` ใช้ `session_id()` เดิมแล้วรันอีกครั้งแบบ GET เพื่อดู flash/ผลลัพธ์
   หมายเหตุ: ใน CLI `BASE_URL` ว่าง ลิงก์จาก `abs_url()` จะไม่มี `/docshub` (ปกติบนเว็บ)
4. **สิทธิ์:** ทดสอบอย่างน้อย 2 บทบาทที่ต่างกัน (เช่น ครูแผนกเดียวกัน vs ครูอีกแผนก, เจ้าของ vs ไม่ใช่เจ้าของ) ว่าเห็น/แก้/ลบได้ตามกฎ
5. **UI:** เปิดในเบราว์เซอร์ทั้งจอกว้างและจอมือถือ ตรวจ console error
6. **Migration:** รันบนฐานข้อมูลที่เป็นเวอร์ชันก่อนหน้า และตรวจว่ารันซ้ำได้

## ข้อมูลทดสอบ
- ฐานข้อมูลเครื่องพัฒนาอาจมี**ข้อมูลจริง** → สร้างข้อมูลทดสอบด้วยชื่อที่จำได้ (`ZZTEST ...`, ผู้ใช้ `zzt_*`) และ**ลบเฉพาะแถวที่สร้าง** (รวม activity_logs ของผู้ใช้ทดสอบ) ห้าม TRUNCATE/ลบทั้งตาราง
- อย่าส่งข้อความภาษาไทยผ่าน `mysql` CLI (encoding เพี้ยน) — ใช้ PHP mysqli ที่ `set_charset('utf8mb4')`
- ห้ามแก้ไฟล์ด้วยสคริปต์ที่อาจเขียนผลว่าง/null ทับไฟล์ (ไม่มี git) — ตรวจผลก่อน `file_put_contents` หรือสำรองไฟล์ก่อน
- ทดสอบ Google/Drive จริงทำได้เฉพาะบนเซิร์ฟเวอร์ที่ตั้งค่า OAuth ไว้ → บอกผู้ใช้ตรง ๆ ว่ายังไม่ได้ทดสอบส่วนนี้ (ทดสอบตรรกะด้วยค่าปลอม/ฟังก์ชันจำลองได้)

## หลังทดสอบ
- `sc_save_integrity()` เพื่ออัปเดต `database/integrity.json`
- รายงานผล: ทดสอบอะไร ผลเป็นอย่างไร อะไรยังไม่ได้ทดสอบ + รายการไฟล์ที่ต้องอัปโหลด
