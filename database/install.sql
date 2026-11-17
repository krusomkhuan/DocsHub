-- Docs Hub install schema + default data
-- APP_VERSION 1.24.0 · db_version 16 (DB_SCHEMA_LATEST 16)
-- สร้างด้วย tools/dump-schema.php --install (ไม่มีผู้ใช้ ข้อมูลส่วนบุคคล หรือค่าลับ)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_be` varchar(10) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_year` (`year_be`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('1', '2568', NULL, NULL, '0');
INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('2', '2569', NULL, NULL, '1');
INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('3', '2570', NULL, NULL, '0');
INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('4', '2564', NULL, NULL, '0');
INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('5', '2565', NULL, NULL, '0');
INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('6', '2566', NULL, NULL, '0');
INSERT IGNORE INTO `academic_years` (`id`, `year_be`, `start_date`, `end_date`, `is_current`) VALUES ('7', '2567', NULL, NULL, '0');

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_log_time` (`created_at`),
  KEY `fk_log_user` (`user_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `has_trip_report` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'เปิดใช้รายงานการไปราชการ',
  PRIMARY KEY (`id`),
  KEY `fk_cat_type` (`type_id`),
  CONSTRAINT `fk_cat_type` FOREIGN KEY (`type_id`) REFERENCES `doc_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('1', '1', 'คำสั่งแต่งตั้งคณะกรรมการ', '1', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('2', '1', 'คำสั่งมอบหมายงาน', '2', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('3', '1', 'คำสั่งไปราชการ', '3', '1');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('4', '2', 'เกียรติบัตรครู/บุคลากร', '1', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('5', '2', 'เกียรติบัตรนักเรียน นักศึกษา', '2', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('6', '2', 'วุฒิบัตรการอบรม', '3', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('7', '3', 'ระดับ ปวช.', '1', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('8', '3', 'ระดับ ปวส.', '2', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('9', '3', 'หลักสูตรระยะสั้น', '3', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('10', '4', 'ระดับ ปวช.', '1', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('11', '4', 'ระดับ ปวส.', '2', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('12', '5', 'ตำรา/หนังสือเรียน', '2', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('13', '5', 'เอกสารประกอบการสอน', '3', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('16', '5', 'คู่มือการใช้งาน Claude', '1', '0');
INSERT IGNORE INTO `categories` (`id`, `type_id`, `name`, `sort_order`, `has_trip_report`) VALUES ('17', '2', 'ประกาศนียบัตร', '4', '0');

CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `ledger_member_can_record` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ครูทั่วไปในแผนกบันทึกรายการบัญชีได้',
  `plc_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'เปิดใช้ระบบ PLC',
  `plc_head_user_id` int(11) DEFAULT NULL COMMENT 'หัวหน้าแผนก (ผู้ลงนามเอกสาร PLC)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('1', 'บห', 'ฝ่ายบริหารทรัพยากร / งานสารบรรณ', '1', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('2', 'วก', 'ฝ่ายวิชาการ', '4', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('3', 'กผ', 'ฝ่ายยุทธศาสตร์และแผนงาน', '2', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('4', 'พน', 'ฝ่ายกิจการนักเรียน นักศึกษา', '3', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('5', 'ชย', 'แผนกวิชาช่างยนต์', '5', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('6', 'ชฟ', 'แผนกวิชาช่างไฟฟ้ากำลัง', '8', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('7', 'อล', 'แผนกวิชาช่างอิเล็กทรอนิกส์', '9', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('8', 'ชก', 'แผนกวิชาช่างกลโรงงาน', '6', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('9', 'ชช', 'แผนกวิชาช่างเชื่อมโลหะ', '7', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('10', 'บช', 'แผนกวิชาการบัญชี', '11', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('11', 'ทธ', 'แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล', '13', '1', '1', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('13', 'สม', 'แผนกวิชาสามัญสัมพันธ์', '18', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('14', 'กต', 'แผนกวิชาการตลาด', '12', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('15', 'รร', 'การโรงแรม', '14', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('16', 'กส', 'แผนกวิชาช่างก่อสร้าง-โยธา', '10', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('18', 'ทบ', 'ชมรม TO BE NUMBER ONE', '16', '1', '0', NULL);
INSERT IGNORE INTO `departments` (`id`, `code`, `name`, `sort_order`, `ledger_member_can_record`, `plc_enabled`, `plc_head_user_id`) VALUES ('19', 'ชร.ทธ', 'ชมรมวิชาชีพเทคโนโลยีธุรกิจดิจิทัล', '17', '1', '0', NULL);

CREATE TABLE IF NOT EXISTS `doc_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'bi-file-earmark',
  `color` varchar(20) NOT NULL DEFAULT 'primary',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `has_subject` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'แสดงช่องรหัส/ชื่อวิชา',
  `has_semester` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'แสดงช่องภาคเรียน',
  `owner_label` varchar(100) NOT NULL DEFAULT 'ผู้รับผิดชอบ',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `doc_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `sort_order`, `has_subject`, `has_semester`, `owner_label`) VALUES ('1', 'orders', 'คำสั่งราชการ', 'bi-file-earmark-ruled', 'primary', 'คำสั่งวิทยาลัย คำสั่งแต่งตั้ง และคำสั่งราชการต่าง ๆ', '1', '0', '0', 'ผู้รับผิดชอบ');
INSERT IGNORE INTO `doc_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `sort_order`, `has_subject`, `has_semester`, `owner_label`) VALUES ('2', 'certificates', 'เกียรติบัตร', 'bi-award', 'warning', 'เกียรติบัตร วุฒิบัตร และประกาศนียบัตร', '2', '0', '0', 'ผู้รับ');
INSERT IGNORE INTO `doc_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `sort_order`, `has_subject`, `has_semester`, `owner_label`) VALUES ('3', 'lesson-plans', 'แผนการสอน', 'bi-journal-text', 'success', 'แผนการจัดการเรียนรู้รายวิชา', '3', '1', '1', 'ผู้สอน');
INSERT IGNORE INTO `doc_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `sort_order`, `has_subject`, `has_semester`, `owner_label`) VALUES ('4', 'course-plans', 'โครงการสอน', 'bi-diagram-3', 'info', 'โครงการสอนรายวิชา / กำหนดการสอน', '4', '1', '1', 'ผู้สอน');
INSERT IGNORE INTO `doc_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `sort_order`, `has_subject`, `has_semester`, `owner_label`) VALUES ('5', 'textbooks', 'ตำรา/หนังสือเรียน', 'bi-book', 'danger', 'หนังสือเรียน เอกสารประกอบการสอน และตำรา', '5', '1', '0', 'ผู้แต่ง/ผู้เรียบเรียง');
INSERT IGNORE INTO `doc_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `sort_order`, `has_subject`, `has_semester`, `owner_label`) VALUES ('7', 'regulation', 'ระเบียบราชการต่างๆ', 'bi-file-earmark', 'primary', 'เก็บข้อมูลระเบียบที่เกี่ยวข้องกับงานราชการ', '6', '0', '0', 'ผู้รับผิดชอบ');

CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `doc_no` varchar(100) DEFAULT NULL,
  `doc_date` date DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `subject_code` varchar(30) DEFAULT NULL,
  `subject_name` varchar(150) DEFAULT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_original` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `file_ext` varchar(10) NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `downloads` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_doc_type` (`type_id`),
  KEY `idx_doc_status` (`status`,`is_public`),
  KEY `fk_doc_cat` (`category_id`),
  KEY `fk_doc_dept` (`department_id`),
  KEY `fk_doc_user` (`uploaded_by`),
  CONSTRAINT `fk_doc_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_type` FOREIGN KEY (`type_id`) REFERENCES `doc_types` (`id`),
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ledger_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `kind` enum('income','expense') NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lcat_dept` (`department_id`,`kind`),
  CONSTRAINT `fk_lcat_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ledger_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `kind` enum('income','expense') NOT NULL,
  `entry_date` date NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `counterparty` varchar(150) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_original` varchar(255) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `file_ext` varchar(10) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ledger_dept_date` (`department_id`,`entry_date`),
  KEY `fk_ledger_cat` (`category_id`),
  KEY `fk_ledger_cuser` (`created_by`),
  KEY `fk_ledger_uuser` (`updated_by`),
  CONSTRAINT `fk_ledger_cat` FOREIGN KEY (`category_id`) REFERENCES `ledger_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_cuser` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_ledger_uuser` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ledger_managers` (
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`department_id`,`user_id`),
  KEY `fk_lm_user` (`user_id`),
  CONSTRAINT `fk_lm_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pdpa_consents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `policy_version` int(11) NOT NULL,
  `action` enum('accept','withdraw') NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consent_user` (`user_id`,`id`),
  CONSTRAINT `fk_consent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pdpa_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `effective_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_policy_version` (`version`),
  KEY `fk_policy_user` (`created_by`),
  CONSTRAINT `fk_policy_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pdpa_policies` (`id`, `version`, `title`, `content`, `effective_date`, `created_by`, `created_at`) VALUES ('1', '1', 'นโยบายคุ้มครองข้อมูลส่วนบุคคล', '{college_name} (\"วิทยาลัย\") ในฐานะผู้ควบคุมข้อมูลส่วนบุคคล ตระหนักถึงความสำคัญของการคุ้มครองข้อมูลส่วนบุคคลตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 จึงจัดทำนโยบายนี้เพื่อแจ้งให้ผู้ใช้งานระบบ {site_name} ทราบถึงการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคล\n\n## 1. ข้อมูลส่วนบุคคลที่เก็บรวบรวม\n- ข้อมูลบัญชีผู้ใช้: ชื่อผู้ใช้ ชื่อ-สกุล แผนกวิชา/ฝ่าย บทบาทในระบบ และรหัสผ่าน (จัดเก็บในรูปแบบเข้ารหัสทางเดียว ไม่มีผู้ใดเห็นรหัสผ่านจริง)\n- ข้อมูลติดต่อ: อีเมล และหมายเลขโทรศัพท์ (ถ้าระบุ)\n- ภาพโปรไฟล์ (ถ้าอัปโหลด)\n- ข้อมูลในเอกสารที่อัปโหลด เช่น ชื่อผู้รับเกียรติบัตร ผู้สอน ผู้ไปราชการ และภาพประกอบรายงานการไปราชการ\n- ข้อมูลบัญชีรายรับ-รายจ่ายของแผนกวิชา ซึ่งอาจมีชื่อผู้รับเงินหรือผู้จ่ายเงิน\n- ข้อมูลการใช้งานระบบ: วันเวลาเข้าสู่ระบบ ประวัติการทำรายการ และหมายเลข IP\n- คุกกี้ที่จำเป็นต่อการทำงานของระบบ (คุกกี้เซสชันสำหรับการเข้าสู่ระบบ) ระบบไม่ใช้คุกกี้เพื่อการโฆษณาหรือติดตามพฤติกรรม\n\n## 2. วัตถุประสงค์ในการใช้ข้อมูล\n- เพื่อยืนยันตัวตนและให้บริการจัดเก็บ ค้นหา และเผยแพร่เอกสารของวิทยาลัย\n- เพื่อออกเลขหนังสือราชการ จัดทำรายงานการไปราชการ และบัญชีรายรับ-รายจ่ายของแผนกวิชา\n- เพื่อติดต่อประสานงานภายในวิทยาลัย\n- เพื่อรักษาความปลอดภัยของระบบ ตรวจสอบย้อนหลัง และป้องกันการใช้งานโดยมิชอบ\n\n## 3. ฐานทางกฎหมาย\n- การปฏิบัติหน้าที่ในการดำเนินภารกิจเพื่อประโยชน์สาธารณะ และการใช้อำนาจรัฐของสถานศึกษา\n- การปฏิบัติตามกฎหมายและระเบียบงานสารบรรณ\n- ประโยชน์โดยชอบด้วยกฎหมายในการรักษาความปลอดภัยของระบบ\n- ความยินยอมของท่าน สำหรับข้อมูลที่ท่านเลือกให้เพิ่มเติม เช่น ภาพโปรไฟล์ อีเมล และเบอร์โทรศัพท์\n\n## 4. การเปิดเผยข้อมูล\n- ข้อมูลบัญชีผู้ใช้เปิดเผยเฉพาะผู้ดูแลระบบและเจ้าหน้าที่ที่ได้รับมอบหมาย\n- เอกสารที่ตั้งค่าเป็น \"สาธารณะ\" จะเปิดเผยต่อบุคคลทั่วไป ผู้อัปโหลดมีหน้าที่ตรวจสอบว่าเอกสารไม่มีข้อมูลส่วนบุคคลเกินความจำเป็น\n- บัญชีรายรับ-รายจ่ายของแผนกวิชาเปิดเผยเฉพาะครูที่สังกัดแผนกวิชานั้นเท่านั้น\n- วิทยาลัยไม่ขายหรือส่งต่อข้อมูลส่วนบุคคลให้บุคคลภายนอก เว้นแต่กฎหมายกำหนด\n\n## 5. ระยะเวลาการเก็บรักษา\n- ข้อมูลบัญชีผู้ใช้: ตลอดระยะเวลาที่ท่านเป็นบุคลากรของวิทยาลัย หรือจนกว่าจะมีคำขอลบข้อมูลที่ได้รับอนุมัติ\n- ประวัติการใช้งานระบบ: {log_retention_days} วัน แล้วลบอัตโนมัติ\n- เอกสารราชการ: ตามระเบียบสำนักนายกรัฐมนตรีว่าด้วยงานสารบรรณ\n\n## 6. สิทธิของเจ้าของข้อมูลส่วนบุคคล\nท่านมีสิทธิขอเข้าถึงและขอรับสำเนาข้อมูล ขอให้แก้ไขข้อมูลให้ถูกต้อง ขอให้ลบหรือทำให้ข้อมูลไม่สามารถระบุตัวตนได้ ขอให้ระงับการใช้ คัดค้านการประมวลผล ขอรับข้อมูลในรูปแบบที่อ่านได้ด้วยเครื่อง และถอนความยินยอม ท่านใช้สิทธิได้ที่เมนู \"ข้อมูลส่วนบุคคลของฉัน\" หลังเข้าสู่ระบบ หรือติดต่อเจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล วิทยาลัยจะดำเนินการภายใน 30 วันนับแต่ได้รับคำขอ และท่านมีสิทธิร้องเรียนต่อสำนักงานคณะกรรมการคุ้มครองข้อมูลส่วนบุคคล\n\n## 7. การรักษาความปลอดภัย\nวิทยาลัยใช้มาตรการรักษาความปลอดภัยที่เหมาะสม เช่น การเข้ารหัสรหัสผ่าน การจำกัดสิทธิ์การเข้าถึงตามบทบาท การป้องกันการเข้าถึงไฟล์โดยตรง การบันทึกประวัติการใช้งาน และการสำรองข้อมูล\n\n## 8. ช่องทางการติดต่อ\nเจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล: {dpo_name}\nอีเมล: {dpo_email}\nโทรศัพท์: {dpo_phone}\n{college_name}', '2026-10-19', NULL, '2026-10-19 06:28:38');

CREATE TABLE IF NOT EXISTS `pdpa_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `requester_name` varchar(150) NOT NULL,
  `request_type` enum('access','rectify','erase','withdraw','object','restrict','portability','other') NOT NULL,
  `detail` text DEFAULT NULL,
  `status` enum('pending','processing','done','rejected') NOT NULL DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `handled_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `handled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_req_status` (`status`),
  KEY `fk_req_user` (`user_id`),
  KEY `fk_req_handler` (`handled_by`),
  CONSTRAINT `fk_req_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_req_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photo_albums` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `event_date` date DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `submitted_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_album_status` (`status`,`event_date`),
  KEY `fk_album_dept` (`department_id`),
  KEY `fk_album_user` (`submitted_by`),
  KEY `fk_album_approver` (`approved_by`),
  CONSTRAINT `fk_album_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_album_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_album_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `teacher_activity` text DEFAULT NULL,
  `student_activity` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_plca_problem` (`problem_id`),
  CONSTRAINT `fk_plca_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_attendees` (
  `meeting_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  PRIMARY KEY (`meeting_id`,`member_id`),
  KEY `fk_plcat_member` (`member_id`),
  CONSTRAINT `fk_plcat_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `plc_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plcat_member` FOREIGN KEY (`member_id`) REFERENCES `plc_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `field_name` varchar(150) DEFAULT NULL,
  `academic_year` smallint(6) NOT NULL,
  `semester` tinyint(4) NOT NULL DEFAULT 1,
  `meeting_place` varchar(255) DEFAULT NULL,
  `meeting_schedule` varchar(255) DEFAULT NULL,
  `memo_no` varchar(100) DEFAULT NULL,
  `memo_date` date DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `announce_date` date DEFAULT NULL,
  `status` enum('draft','submitted','approved') NOT NULL DEFAULT 'draft',
  `reject_note` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plc_group_term` (`academic_year`,`semester`),
  KEY `fk_plcg_dept` (`department_id`),
  KEY `fk_plcg_user` (`created_by`),
  CONSTRAINT `fk_plcg_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_plcg_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `issue` text NOT NULL,
  `causes` text DEFAULT NULL,
  `priority` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_plci_problem` (`problem_id`),
  CONSTRAINT `fk_plci_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_measures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `objective` text NOT NULL,
  `tool` text DEFAULT NULL,
  `criteria` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_plcme_problem` (`problem_id`),
  CONSTRAINT `fk_plcme_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `problem_id` int(11) DEFAULT NULL,
  `kind` enum('finding','solution','general','teach','reflect','summary') NOT NULL DEFAULT 'general',
  `step_no` tinyint(4) DEFAULT NULL,
  `meeting_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `place` varchar(255) DEFAULT NULL,
  `topic` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `recorder_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plc_meeting_date` (`group_id`,`meeting_date`),
  KEY `fk_plcmt_problem` (`problem_id`),
  KEY `fk_plcmt_recorder` (`recorder_id`),
  KEY `fk_plcmt_user` (`created_by`),
  CONSTRAINT `fk_plcmt_group` FOREIGN KEY (`group_id`) REFERENCES `plc_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plcmt_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plcmt_recorder` FOREIGN KEY (`recorder_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plcmt_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_name` varchar(150) DEFAULT NULL,
  `position_title` varchar(150) DEFAULT NULL,
  `committee_role` enum('chair','vice_chair','member','secretary','expert') NOT NULL DEFAULT 'member',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plc_member` (`group_id`,`user_id`),
  KEY `fk_plcm_user` (`user_id`),
  CONSTRAINT `fk_plcm_group` FOREIGN KEY (`group_id`) REFERENCES `plc_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plcm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_observations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `observer_id` int(11) DEFAULT NULL,
  `observe_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `class_group` varchar(150) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `students_total` smallint(6) DEFAULT NULL,
  `students_present` smallint(6) DEFAULT NULL,
  `scores` text DEFAULT NULL,
  `student_behavior` text DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `improvements` text DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_plco_problem` (`problem_id`),
  KEY `fk_plco_observer` (`observer_id`),
  CONSTRAINT `fk_plco_observer` FOREIGN KEY (`observer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plco_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `problem_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_plcph_group` (`group_id`),
  KEY `fk_plcph_problem` (`problem_id`),
  KEY `fk_plcph_user` (`created_by`),
  CONSTRAINT `fk_plcph_group` FOREIGN KEY (`group_id`) REFERENCES `plc_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plcph_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plcph_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_plan_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `problem_id` int(11) NOT NULL,
  `step_no` tinyint(4) NOT NULL,
  `activity` varchar(500) NOT NULL,
  `role_text` varchar(255) DEFAULT NULL,
  `plan_date` date DEFAULT NULL,
  `minutes` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_plcs_problem` (`problem_id`),
  CONSTRAINT `fk_plcs_problem` FOREIGN KEY (`problem_id`) REFERENCES `plc_problems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_problems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `model_teacher_id` int(11) DEFAULT NULL,
  `theme` varchar(500) NOT NULL,
  `subject_name` varchar(255) DEFAULT NULL,
  `proposal_date` date DEFAULT NULL,
  `memo_no` varchar(100) DEFAULT NULL,
  `memo_date` date DEFAULT NULL,
  `solutions` text DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `plan_start` date DEFAULT NULL,
  `plan_end` date DEFAULT NULL,
  `status` enum('draft','submitted','approved') NOT NULL DEFAULT 'draft',
  `reject_note` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_plcp_group` (`group_id`),
  KEY `fk_plcp_model` (`model_teacher_id`),
  KEY `fk_plcp_user` (`created_by`),
  CONSTRAINT `fk_plcp_group` FOREIGN KEY (`group_id`) REFERENCES `plc_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plcp_model` FOREIGN KEY (`model_teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plcp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plc_signatures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity` enum('group','problem','meeting','observation') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `slot` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `signed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plc_sig` (`entity`,`entity_id`,`slot`),
  KEY `fk_plcsig_user` (`user_id`),
  CONSTRAINT `fk_plcsig_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `skey` varchar(50) NOT NULL,
  `svalue` text DEFAULT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('storage_driver', 'local');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('google_login_enabled', '0');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('allow_register', '1');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('auto_approve_member', '1');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('college_name', 'ระบบสารสนเทศ');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('db_version', '16');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('google_allow_register', '0');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('log_retention_days', '365');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('max_upload_mb', '50');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('plc_committee_title', 'ประธานคณะกรรมการดำเนินงานขับเคลื่อนชุมชนการเรียนรู้ทางวิชาชีพ (PLC)');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('plc_deputy_position', 'รองผู้อำนวยการฝ่ายวิชาการ');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('plc_director_position', 'ผู้อำนวยการ');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('site_name', 'Docs Hub');
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('site_slogan', 'ศูนย์รวมงานจัดเก็บเอกสาร');

CREATE TABLE IF NOT EXISTS `traffic_daily` (
  `visit_date` date NOT NULL,
  `visits` bigint(20) unsigned NOT NULL DEFAULT 0,
  `pageviews` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `traffic_sessions` (
  `visitor_hash` char(64) NOT NULL,
  `visit_date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `last_seen` datetime NOT NULL,
  PRIMARY KEY (`visitor_hash`,`visit_date`),
  KEY `idx_traffic_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `trip_report_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `width` int(11) NOT NULL DEFAULT 0,
  `height` int(11) NOT NULL DEFAULT 0,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trip_img` (`report_id`,`sort_order`),
  CONSTRAINT `fk_trip_img` FOREIGN KEY (`report_id`) REFERENCES `trip_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trip_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `travelers` text DEFAULT NULL,
  `place` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `purpose` text DEFAULT NULL,
  `result` text NOT NULL,
  `benefit` text DEFAULT NULL,
  `suggestion` text DEFAULT NULL,
  `expense` decimal(10,2) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_original` varchar(255) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `file_ext` varchar(10) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_trip_doc` (`document_id`),
  KEY `fk_trip_user` (`created_by`),
  CONSTRAINT `fk_trip_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trip_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` enum('admin','staff','member') NOT NULL DEFAULT 'member',
  `status` enum('pending','active','disabled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ภาพโปรไฟล์ใน uploads/docs/avatars',
  `anonymized_at` datetime DEFAULT NULL COMMENT 'ลบข้อมูลส่วนบุคคลตามคำขอ PDPA แล้ว',
  `google_sub` varchar(64) DEFAULT NULL COMMENT 'รหัสบัญชี Google (sub) ที่เชื่อมไว้',
  `google_email` varchar(150) DEFAULT NULL,
  `has_password` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = สมัครด้วย Google ยังไม่ได้ตั้งรหัสผ่านเอง',
  `position` varchar(100) DEFAULT NULL COMMENT 'ตำแหน่ง/วิทยฐานะ เช่น ครู คศ.1',
  `signature` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ลายมือชื่อ (uploads/docs/signatures)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_google_sub` (`google_sub`),
  KEY `fk_user_dept` (`department_id`),
  CONSTRAINT `fk_user_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
