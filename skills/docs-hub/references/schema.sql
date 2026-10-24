-- Docs Hub database structure (no data)
-- APP_VERSION 1.23.3 · db_version 13 (DB_SCHEMA_LATEST 13)
-- สร้างด้วย tools/dump-schema.php

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_be` varchar(10) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_year` (`year_be`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
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

CREATE TABLE `book_number_starts` (
  `department_id` int(11) NOT NULL,
  `book_kind` enum('out','in','memo','order') NOT NULL,
  `year_be` smallint(6) NOT NULL,
  `start_no` int(11) NOT NULL,
  `set_by` int(11) DEFAULT NULL,
  `set_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`department_id`,`book_kind`,`year_be`),
  KEY `fk_bns_user` (`set_by`),
  CONSTRAINT `fk_bns_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bns_user` FOREIGN KEY (`set_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `book_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `book_kind` enum('out','in','memo','order') NOT NULL DEFAULT 'out',
  `year_be` smallint(6) NOT NULL,
  `running_no` int(11) NOT NULL,
  `full_no` varchar(100) NOT NULL,
  `book_date` date NOT NULL,
  `subject` varchar(255) NOT NULL,
  `to_whom` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_running` (`department_id`,`book_kind`,`year_be`,`running_no`),
  KEY `fk_bn_doc` (`document_id`),
  KEY `fk_bn_user` (`created_by`),
  CONSTRAINT `fk_bn_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_bn_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bn_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `has_trip_report` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'เปิดใช้รายงานการไปราชการ',
  PRIMARY KEY (`id`),
  KEY `fk_cat_type` (`type_id`),
  CONSTRAINT `fk_cat_type` FOREIGN KEY (`type_id`) REFERENCES `doc_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `ledger_member_can_record` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ครูทั่วไปในแผนกบันทึกรายการบัญชีได้',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'bi-file-earmark',
  `color` varchar(20) NOT NULL DEFAULT 'primary',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `has_subject` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'แสดงช่องรหัส/ชื่อวิชา',
  `has_semester` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'แสดงช่องภาคเรียน',
  `has_order_no` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ออกเลขคำสั่งอัตโนมัติได้',
  `owner_label` varchar(100) NOT NULL DEFAULT 'ผู้รับผิดชอบ',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
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

CREATE TABLE `ledger_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `kind` enum('income','expense') NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lcat_dept` (`department_id`,`kind`),
  CONSTRAINT `fk_lcat_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ledger_entries` (
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

CREATE TABLE `ledger_managers` (
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`department_id`,`user_id`),
  KEY `fk_lm_user` (`user_id`),
  CONSTRAINT `fk_lm_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pdpa_consents` (
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

CREATE TABLE `pdpa_policies` (
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

CREATE TABLE `pdpa_requests` (
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

CREATE TABLE `photo_albums` (
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

CREATE TABLE `settings` (
  `skey` varchar(50) NOT NULL,
  `svalue` text DEFAULT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trip_report_images` (
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

CREATE TABLE `trip_reports` (
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

CREATE TABLE `users` (
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_google_sub` (`google_sub`),
  KEY `fk_user_dept` (`department_id`),
  CONSTRAINT `fk_user_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

