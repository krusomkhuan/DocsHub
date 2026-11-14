-- Docs Hub: ปรับเลขลำดับ (running_no) ให้ตรงกับเลขที่ที่แสดง (full_no) เพื่อให้ทะเบียนเรียงถูกต้อง
-- ใช้กับรูปแบบเลข "{code} {no}/{year}" เช่น "ทธ 091/2569"
-- วิธีใช้: phpMyAdmin > ฐานข้อมูล docshub > แท็บ SQL > วางทั้งไฟล์ > Go

-- 1) แถวที่เลขไม่ตรงกัน (ควรเห็น ทธ 091/2569 และ 092/2569)
SELECT id, full_no, running_no AS old_running_no,
       CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(full_no, '/', 1), ' ', -1) AS UNSIGNED) AS new_running_no
  FROM book_numbers
 WHERE full_no REGEXP ' [0-9]+/[0-9]{4}$'
   AND running_no <> CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(full_no, '/', 1), ' ', -1) AS UNSIGNED);

-- 2) สำรองแถวเหล่านั้นไว้ก่อน
CREATE TABLE IF NOT EXISTS bak_book_numbers_running_20261114 LIKE book_numbers;
INSERT IGNORE INTO bak_book_numbers_running_20261114
SELECT * FROM book_numbers
 WHERE full_no REGEXP ' [0-9]+/[0-9]{4}$'
   AND running_no <> CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(full_no, '/', 1), ' ', -1) AS UNSIGNED);

-- 3) แก้ไข (ถ้าเลขชนกับแถวอื่น จะ error "Duplicate entry" และไม่มีอะไรเปลี่ยน)
UPDATE book_numbers
   SET running_no = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(full_no, '/', 1), ' ', -1) AS UNSIGNED)
 WHERE full_no REGEXP ' [0-9]+/[0-9]{4}$'
   AND running_no <> CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(full_no, '/', 1), ' ', -1) AS UNSIGNED);

-- 4) ตรวจผล: ทธ ปี 2569 เรียงจากมากไปน้อย
SELECT b.full_no, b.running_no, b.book_date, b.is_cancelled
  FROM book_numbers b JOIN departments d ON d.id = b.department_id
 WHERE d.code = 'ทธ' AND b.year_be = 2569
 ORDER BY b.book_kind, b.running_no DESC;
