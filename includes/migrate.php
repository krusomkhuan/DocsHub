<?php
/**
 * ปรับโครงสร้างฐานข้อมูลอัตโนมัติเมื่ออัปเดตเวอร์ชันระบบ
 * เก็บเวอร์ชันโครงสร้างไว้ที่ settings.db_version ทำงานเฉพาะครั้งแรกที่ยังไม่ได้ปรับ
 */
// เวอร์ชันโครงสร้างล่าสุด — เพิ่มทุกครั้งที่เพิ่มขั้นตอนด้านล่าง (ใช้ในหน้าตรวจสอบระบบ)
if (!defined('DB_SCHEMA_LATEST')) define('DB_SCHEMA_LATEST', 13);

$dbVersion = (int)(db_val("SELECT svalue FROM settings WHERE skey = 'db_version'") ?? 1);

// v2: ตัวเลือกช่องข้อมูลของแต่ละประเภทเอกสาร (รองรับการเพิ่มประเภทใหม่)
if ($dbVersion < 2) {
    $db->query("ALTER TABLE doc_types
        ADD COLUMN has_subject TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'แสดงช่องรหัส/ชื่อวิชา',
        ADD COLUMN has_semester TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'แสดงช่องภาคเรียน',
        ADD COLUMN has_order_no TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ออกเลขคำสั่งอัตโนมัติได้',
        ADD COLUMN owner_label VARCHAR(100) NOT NULL DEFAULT 'ผู้รับผิดชอบ'");
    $db->query("UPDATE doc_types SET has_order_no = 1 WHERE slug = 'orders'");
    $db->query("UPDATE doc_types SET owner_label = 'ผู้รับ' WHERE slug = 'certificates'");
    $db->query("UPDATE doc_types SET has_subject = 1, has_semester = 1, owner_label = 'ผู้สอน' WHERE slug IN ('lesson-plans', 'course-plans')");
    $db->query("UPDATE doc_types SET has_subject = 1, owner_label = 'ผู้แต่ง/ผู้เรียบเรียง' WHERE slug = 'textbooks'");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '2') ON DUPLICATE KEY UPDATE svalue = '2'");
}

// v3: รายงานการไปราชการ (แนบกับเอกสารในหมวดที่เปิดใช้ เช่น คำสั่งไปราชการ) พร้อมภาพประกอบ
if ($dbVersion < 3) {
    $db->query("ALTER TABLE categories ADD COLUMN has_trip_report TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'เปิดใช้รายงานการไปราชการ'");
    $db->query("UPDATE categories SET has_trip_report = 1 WHERE name = 'คำสั่งไปราชการ'");
    $db->query("CREATE TABLE IF NOT EXISTS trip_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,                 -- คำสั่งไปราชการที่อ้างอิง
        subject VARCHAR(255) NOT NULL,            -- เรื่อง
        travelers TEXT,                           -- รายชื่อผู้ไปราชการ
        place VARCHAR(255) NOT NULL,              -- สถานที่
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        purpose TEXT,                             -- วัตถุประสงค์
        result TEXT NOT NULL,                     -- ผลการไปราชการ / สาระสำคัญ
        benefit TEXT,                             -- ประโยชน์ที่ได้รับและการนำไปใช้
        suggestion TEXT,                          -- ปัญหา อุปสรรค ข้อเสนอแนะ
        expense DECIMAL(10,2) DEFAULT NULL,       -- ค่าใช้จ่าย
        file_name VARCHAR(255) DEFAULT NULL,      -- ไฟล์รายงานแนบ (ถ้ามี)
        file_original VARCHAR(255) DEFAULT NULL,
        file_size INT NOT NULL DEFAULT 0,
        file_ext VARCHAR(10) DEFAULT NULL,
        is_public TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_trip_doc (document_id),
        CONSTRAINT fk_trip_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        CONSTRAINT fk_trip_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS trip_report_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        caption VARCHAR(255) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        width INT NOT NULL DEFAULT 0,
        height INT NOT NULL DEFAULT 0,
        file_size INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_trip_img (report_id, sort_order),
        CONSTRAINT fk_trip_img FOREIGN KEY (report_id) REFERENCES trip_reports(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '3') ON DUPLICATE KEY UPDATE svalue = '3'");
}

// v4: ภาพโปรไฟล์สมาชิก และ URL ของระบบ/วิทยาลัย
if ($dbVersion < 4) {
    $db->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์ภาพโปรไฟล์ใน uploads/docs/avatars'");
    $db->query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('site_url', ''), ('college_url', '')");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '4') ON DUPLICATE KEY UPDATE svalue = '4'");
}

// v5: ปีการศึกษา (ผู้ดูแลเพิ่ม/กำหนดปีปัจจุบันได้)
if ($dbVersion < 5) {
    $db->query("CREATE TABLE IF NOT EXISTS academic_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year_be VARCHAR(10) NOT NULL,              -- ปีการศึกษา พ.ศ. เช่น 2569
        start_date DATE DEFAULT NULL,              -- วันเปิดภาคเรียน (ถ้ามี)
        end_date DATE DEFAULT NULL,
        is_current TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_year (year_be)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ปีเริ่มต้น: ปีการศึกษาปัจจุบันและปีก่อนหน้า รวมถึงปีที่มีในเอกสารเดิม
    $cur = (int)date('Y') + 543 - ((int)date('n') < 5 ? 1 : 0);
    $db->query("INSERT IGNORE INTO academic_years (year_be, is_current) VALUES ('" . ($cur - 1) . "', 0), ('$cur', 1)");
    $db->query("INSERT IGNORE INTO academic_years (year_be) SELECT DISTINCT academic_year FROM documents WHERE academic_year REGEXP '^[0-9]{4}$'");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '5') ON DUPLICATE KEY UPDATE svalue = '5'");
}

// v6: บัญชีรายรับ-รายจ่ายของแผนกวิชา (ดูได้เฉพาะครูในแผนกนั้น)
if ($dbVersion < 6) {
    $db->query("CREATE TABLE IF NOT EXISTS ledger_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        kind ENUM('income','expense') NOT NULL,
        name VARCHAR(150) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_lcat_dept (department_id, kind),
        CONSTRAINT fk_lcat_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ledger_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        kind ENUM('income','expense') NOT NULL,
        entry_date DATE NOT NULL,
        category_id INT DEFAULT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        ref_no VARCHAR(100) DEFAULT NULL,         -- เลขที่ใบเสร็จ / เอกสารอ้างอิง
        counterparty VARCHAR(150) DEFAULT NULL,   -- ผู้จ่ายเงิน / ผู้รับเงิน
        note TEXT,
        file_name VARCHAR(255) DEFAULT NULL,      -- หลักฐาน (ใบเสร็จ)
        file_original VARCHAR(255) DEFAULT NULL,
        file_size INT NOT NULL DEFAULT 0,
        file_ext VARCHAR(10) DEFAULT NULL,
        created_by INT DEFAULT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_ledger_dept_date (department_id, entry_date),
        CONSTRAINT fk_ledger_dept FOREIGN KEY (department_id) REFERENCES departments(id),
        CONSTRAINT fk_ledger_cat FOREIGN KEY (category_id) REFERENCES ledger_categories(id) ON DELETE SET NULL,
        CONSTRAINT fk_ledger_cuser FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_ledger_uuser FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '6') ON DUPLICATE KEY UPDATE svalue = '6'");
}

// v7: ผู้รับผิดชอบบัญชีรายรับ-รายจ่ายของแผนก และตัวเลือกให้ครูทั่วไปบันทึกรายการได้หรือไม่
if ($dbVersion < 7) {
    $db->query("CREATE TABLE IF NOT EXISTS ledger_managers (
        department_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_by INT DEFAULT NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (department_id, user_id),
        CONSTRAINT fk_lm_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        CONSTRAINT fk_lm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("ALTER TABLE departments ADD COLUMN ledger_member_can_record TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'ครูทั่วไปในแผนกบันทึกรายการบัญชีได้'");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '7') ON DUPLICATE KEY UPDATE svalue = '7'");
}

// v8: PDPA — นโยบายความเป็นส่วนตัว (มีเวอร์ชัน) การให้/ถอนความยินยอม และคำร้องใช้สิทธิ์ของเจ้าของข้อมูล
if ($dbVersion < 8) {
    $db->query("CREATE TABLE IF NOT EXISTS pdpa_policies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        version INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content MEDIUMTEXT NOT NULL,
        effective_date DATE NOT NULL,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_policy_version (version),
        CONSTRAINT fk_policy_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS pdpa_consents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        policy_version INT NOT NULL,
        action ENUM('accept','withdraw') NOT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_consent_user (user_id, id),
        CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS pdpa_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        requester_name VARCHAR(150) NOT NULL,
        request_type ENUM('access','rectify','erase','withdraw','object','restrict','portability','other') NOT NULL,
        detail TEXT,
        status ENUM('pending','processing','done','rejected') NOT NULL DEFAULT 'pending',
        response TEXT,
        handled_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        handled_at DATETIME DEFAULT NULL,
        KEY idx_req_status (status),
        CONSTRAINT fk_req_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_req_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("ALTER TABLE users ADD COLUMN anonymized_at DATETIME DEFAULT NULL COMMENT 'ลบข้อมูลส่วนบุคคลตามคำขอ PDPA แล้ว'");
    $db->query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('pdpa_dpo_name', ''), ('pdpa_dpo_email', ''), ('pdpa_dpo_phone', ''), ('log_retention_days', '365')");
    $stmt = $db->prepare("INSERT IGNORE INTO pdpa_policies (version, title, content, effective_date) VALUES (1, ?, ?, CURDATE())");
    $title = 'นโยบายคุ้มครองข้อมูลส่วนบุคคล';
    $content = require __DIR__ . '/pdpa_default_policy.php';
    $stmt->bind_param('ss', $title, $content);
    $stmt->execute();
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '8') ON DUPLICATE KEY UPDATE svalue = '8'");
}

// v9: แก้ชื่อช่อง "บุคคล" ที่บันทึกภาษาไทยผิดรหัส (กลายเป็น ????) ให้กลับเป็นค่าเริ่มต้น
if ($dbVersion < 9) {
    $db->query("UPDATE doc_types SET owner_label = 'ผู้รับผิดชอบ' WHERE owner_label LIKE '%?%'");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '9') ON DUPLICATE KEY UPDATE svalue = '9'");
}

// v10: กำหนดเลขเริ่มต้นของเลขหนังสือ แยกตามแผนก/ประเภท/ปี พ.ศ. (เช่น ย้ายจากทะเบียนกระดาษกลางปี)
if ($dbVersion < 10) {
    $db->query("CREATE TABLE IF NOT EXISTS book_number_starts (
        department_id INT NOT NULL,
        book_kind ENUM('out','in','memo','order') NOT NULL,
        year_be SMALLINT NOT NULL,
        start_no INT NOT NULL,
        set_by INT DEFAULT NULL,
        set_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (department_id, book_kind, year_be),
        CONSTRAINT fk_bns_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        CONSTRAINT fk_bns_user FOREIGN KEY (set_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '10') ON DUPLICATE KEY UPDATE svalue = '10'");
}

// v11: คลังรูปภาพ — เก็บลิงก์อัลบั้มภาพกิจกรรม (Google Photos ฯลฯ) สมาชิกส่งลิงก์ เจ้าหน้าที่อนุมัติ
if ($dbVersion < 11) {
    $db->query("CREATE TABLE IF NOT EXISTS photo_albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,              -- ชื่ออัลบั้ม / กิจกรรม
        url VARCHAR(500) NOT NULL,                -- ลิงก์อัลบั้ม
        event_date DATE DEFAULT NULL,             -- วันที่จัดกิจกรรม
        academic_year VARCHAR(10) DEFAULT NULL,
        department_id INT DEFAULT NULL,
        description TEXT,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reject_reason VARCHAR(255) DEFAULT NULL,
        clicks INT NOT NULL DEFAULT 0,
        submitted_by INT DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_album_status (status, event_date),
        CONSTRAINT fk_album_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        CONSTRAINT fk_album_user FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_album_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '11') ON DUPLICATE KEY UPDATE svalue = '11'");
}

// v12: เข้าสู่ระบบด้วย Google (เปิด/ปิดได้ที่ตั้งค่าระบบ)
if ($dbVersion < 12) {
    $db->query("ALTER TABLE users
        ADD COLUMN google_sub VARCHAR(64) DEFAULT NULL COMMENT 'รหัสบัญชี Google (sub) ที่เชื่อมไว้',
        ADD COLUMN google_email VARCHAR(150) DEFAULT NULL,
        ADD COLUMN has_password TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = สมัครด้วย Google ยังไม่ได้ตั้งรหัสผ่านเอง',
        ADD UNIQUE KEY uq_google_sub (google_sub)");
    $db->query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('google_login_enabled', '0'), ('google_client_id', ''), ('google_client_secret', ''),
                ('google_allowed_domain', ''), ('google_allow_register', '1')");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '12') ON DUPLICATE KEY UPDATE svalue = '12'");
}

// v13: จัดเก็บไฟล์ใน Google Drive (ค่าเริ่มต้น: เก็บในเครื่องเหมือนเดิม)
if ($dbVersion < 13) {
    $db->query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('storage_driver', 'local'), ('gdrive_folder_id', ''), ('gdrive_folder_name', ''),
                ('gdrive_refresh_token', ''), ('gdrive_account_email', ''), ('gdrive_access_token', ''), ('gdrive_token_exp', '0')");
    $db->query("INSERT INTO settings (skey, svalue) VALUES ('db_version', '13') ON DUPLICATE KEY UPDATE svalue = '13'");
}
