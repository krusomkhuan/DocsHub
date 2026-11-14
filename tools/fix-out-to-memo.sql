-- Docs Hub: เปลี่ยนประเภท "หนังสือส่ง" (out) ทั้งหมดเป็น "บันทึกข้อความ" (memo)
-- และลบเลข ทธ 091/2569 (หนังสือส่ง)
-- วิธีใช้: phpMyAdmin > เลือกฐานข้อมูล docshub > แท็บ SQL > วางทั้งไฟล์ > Go

-- 0) ตรวจก่อน: ต้องได้ 0 ทั้งสองค่า ถ้าไม่ใช่ 0 แปลว่าเลขจะชนกัน (คำสั่ง UPDATE ด้านล่างจะ error และไม่มีอะไรเปลี่ยน)
SELECT
  (SELECT COUNT(*) FROM book_numbers a JOIN book_numbers b
     ON a.department_id = b.department_id AND a.year_be = b.year_be AND a.running_no = b.running_no
    WHERE a.book_kind = 'out' AND b.book_kind = 'memo') AS number_collisions,
  (SELECT COUNT(*) FROM book_number_starts a JOIN book_number_starts b
     ON a.department_id = b.department_id AND a.year_be = b.year_be
    WHERE a.book_kind = 'out' AND b.book_kind = 'memo') AS start_collisions;

-- 1) สำรองแถวที่จะถูกแก้/ลบ (ถ้าต้องการย้อนคืน ใช้ข้อมูลจากสองตารางนี้)
CREATE TABLE IF NOT EXISTS bak_book_numbers_out_20261114 AS
  SELECT * FROM book_numbers WHERE book_kind = 'out';
CREATE TABLE IF NOT EXISTS bak_book_number_starts_out_20261114 AS
  SELECT * FROM book_number_starts WHERE book_kind = 'out';

START TRANSACTION;

-- 2) ลบ ทธ 091/2569 (หนังสือส่ง)
DELETE b FROM book_numbers b
  JOIN departments d ON d.id = b.department_id
 WHERE d.code = 'ทธ' AND b.book_kind = 'out' AND b.year_be = 2569 AND b.running_no = 91;

-- 3) เปลี่ยนประเภท หนังสือส่ง -> บันทึกข้อความ (รวมเลขเริ่มต้นที่ตั้งไว้ เพื่อให้เลขถัดไปต่อเนื่อง)
UPDATE book_numbers       SET book_kind = 'memo' WHERE book_kind = 'out';
UPDATE book_number_starts SET book_kind = 'memo' WHERE book_kind = 'out';

COMMIT;

-- 4) ตรวจผล: ต้องไม่มีแถว out เหลือ
SELECT book_kind, COUNT(*) AS n FROM book_numbers GROUP BY book_kind;
