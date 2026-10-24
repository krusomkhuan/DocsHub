# สกิล Docs Hub สำหรับ ChatGPT

สกิลนี้สอนให้ ChatGPT รู้จักระบบ Docs Hub ทั้งโครงสร้าง กฎความปลอดภัย กฎธุรกิจ ฐานข้อมูล Google Drive การทดสอบ และการอัปโหลดขึ้นเซิร์ฟเวอร์ พร้อมซอร์สโค้ดทั้งระบบ เพื่อให้ช่วย **เพิ่มฟีเจอร์ แก้บั๊ก ตรวจความปลอดภัย ติดตั้ง หรืออธิบายระบบ** ได้ตรงกับโค้ดจริง

| ไฟล์ | ใช้ทำอะไร |
|---|---|
| `docs-hub-skill.zip` | **ไฟล์สำหรับอัปโหลดเข้า ChatGPT** (ภายในคือโฟลเดอร์ `docs-hub/`) |
| `docs-hub/SKILL.md` | คำสั่งหลักของสกิล |
| `docs-hub/references/*` | เอกสารอ้างอิงที่ ChatGPT เปิดอ่านเมื่อจำเป็น (+ `schema.sql` โครงสร้างฐานข้อมูล) |
| `docs-hub/assets/docs-hub-source.zip` | ซอร์สโค้ดเวอร์ชันปัจจุบัน — ไม่มีไฟล์ที่อัปโหลด ไฟล์สำรอง ผู้ใช้ หรือค่าลับ (`database/docshub.sql` ในชุดนี้เป็นไฟล์ติดตั้งที่สะอาด) |
| `build_skill.py` | สร้าง zip ใหม่หลังแก้โค้ด |

> โฟลเดอร์ `skills/` ใช้บนเครื่องพัฒนาเท่านั้น **ไม่ต้องอัปโหลดขึ้นเซิร์ฟเวอร์** (มี `.htaccess` ปิดการเข้าถึงไว้แล้ว)

## วิธีที่ 1 — อัปโหลดเป็น Skill (ChatGPT Business / Enterprise / Edu)

1. เปิด ChatGPT → **Skills** → **Create** / **Upload from your computer** (ชื่อเมนูอาจต่างกันตามรุ่น)
2. เลือกไฟล์ `docs-hub-skill.zip`
3. ตรวจชื่อ `docs-hub` และคำอธิบาย แล้วกด **Install** (ระบบอาจสแกนไฟล์ก่อน)
4. ทดสอบ เช่น พิมพ์ “ใช้สกิล docs-hub ช่วยเพิ่มช่องหมายเหตุในรายงานไปราชการ”

เมนู Skills ขึ้นกับแผนบัญชีและการตั้งค่าของผู้ดูแล workspace ถ้าไม่พบเมนู ให้ใช้วิธีที่ 2

## วิธีที่ 2 — ใช้ใน Project (ChatGPT Plus / Pro / Free)

1. ChatGPT → **Projects** → **New project** ตั้งชื่อ “Docs Hub”
2. เปิด **Instructions** ของ Project แล้ววางเนื้อหาของ `docs-hub/SKILL.md` ตั้งแต่บรรทัด `# Docs Hub — ...` ลงไป (ไม่ต้องใส่ส่วน `---name/description---`)
3. **Add files**: อัปโหลดทุกไฟล์ใน `docs-hub/references/` และ `docs-hub/assets/docs-hub-source.zip`
4. แชตใหม่ใน Project นั้น ChatGPT จะใช้คำสั่งและไฟล์อ้างอิงอัตโนมัติ

## ตัวอย่างคำสั่ง

- “เพิ่มระบบแจ้งเตือนเมื่อเอกสารได้รับการอนุมัติ พร้อม migration v14 และบอกไฟล์ที่ต้องอัปโหลด”
- “ตรวจความปลอดภัยของ `ledger-file.php` ตามเช็กลิสต์ใน security.md”
- “อัปโหลดไฟล์แล้วขึ้น unauthorized_client ต้องแก้อย่างไร”
- “แตกไฟล์ซอร์สแล้วหาว่าหน้าไหนนับจำนวนดาวน์โหลด”

## อัปเดตสกิลหลังแก้ระบบ

เปิด MySQL ใน XAMPP แล้วรัน:

```
python skills\build_skill.py
```

แล้วอัปโหลด `docs-hub-skill.zip` ใหม่ (หรือแทนที่ไฟล์ใน Project) · ถ้ามีฟีเจอร์ใหม่ ให้อัปเดตเลขเวอร์ชันใน `docs-hub/SKILL.md` และ `references/features.md`

> สคริปต์จะหยุดถ้าพบข้อความที่ดูเหมือนคีย์ลับ/โทเคน/hash รหัสผ่าน หรือ `DB_PASS` ที่ไม่ว่างใน `includes/config.php` และจะไม่ใส่ `database/docshub.sql` ของเครื่อง (ใช้ไฟล์ที่สร้างจาก `tools/dump-schema.php --install` แทน)
