-- Docs Hub: ลบเลข ทธ 002/2569 (บันทึกข้อความ ที่ถูกยกเลิกแล้ว)
-- วิธีใช้: phpMyAdmin > เลือกฐานข้อมูล docshub > แท็บ SQL > วางทั้งไฟล์ > Go

-- 1) ดูแถวที่จะลบ (ควรได้ 1 แถว: ขออนุญาตสอนแทนและดูแลแทน, is_cancelled = 1)
SELECT b.id, b.full_no, b.subject, b.book_date, b.is_cancelled
  FROM book_numbers b JOIN departments d ON d.id = b.department_id
 WHERE d.code = 'ทธ' AND b.book_kind = 'memo' AND b.year_be = 2569 AND b.running_no = 2;

-- 2) สำรองไว้ก่อนลบ
CREATE TABLE IF NOT EXISTS bak_book_numbers_deleted_20261114 LIKE book_numbers;
INSERT IGNORE INTO bak_book_numbers_deleted_20261114
SELECT b.* FROM book_numbers b JOIN departments d ON d.id = b.department_id
 WHERE d.code = 'ทธ' AND b.book_kind = 'memo' AND b.year_be = 2569 AND b.running_no = 2 AND b.is_cancelled = 1;

-- 3) ลบ (เฉพาะแถวที่ถูกยกเลิกแล้วเท่านั้น)
DELETE b FROM book_numbers b JOIN departments d ON d.id = b.department_id
 WHERE d.code = 'ทธ' AND b.book_kind = 'memo' AND b.year_be = 2569 AND b.running_no = 2 AND b.is_cancelled = 1;

-- 4) ตรวจผล: ควรเหลือ ทธ 001/2569 บันทึกข้อความ
SELECT b.full_no, b.subject, b.is_cancelled
  FROM book_numbers b JOIN departments d ON d.id = b.department_id
 WHERE d.code = 'ทธ' AND b.book_kind = 'memo' AND b.year_be = 2569 ORDER BY b.running_no;
