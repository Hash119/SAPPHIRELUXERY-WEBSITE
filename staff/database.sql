-- Sapphire Aesthetic & Luxury - Attendance & HR Management System Database Schema
-- Character set utf8mb4

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `holidays`;
DROP TABLE IF EXISTS `manual_punches`;
DROP TABLE IF EXISTS `leave_balances`;
DROP TABLE IF EXISTS `leaves`;
DROP TABLE IF EXISTS `attendance_logs`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `settings`;

-- 1. System Settings Table
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(64) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('company_name', 'Sapphire Aesthetic & Luxury', 'Company display name'),
('default_shift_start', '09:00:00', 'Standard morning shift start time'),
('default_shift_end', '18:00:00', 'Standard evening shift end time'),
('grace_period_minutes', '15', 'Allowed grace minutes before marked as Late'),
('min_work_hours_full_day', '8.0', 'Minimum hours for a full day attendance'),
('half_day_work_hours', '4.5', 'Minimum hours for half day attendance'),
('default_maternity_days', '84', 'Maternity leave allocation for eligible female employees (12 weeks)'),
('leave_approval_role', 'admin_or_supervisor', 'Who can approve leaves: admin_only, admin_or_supervisor, or hr_manager'),
('machine_sync_api_key', 'sapphire_secret_sync_key_2026', 'Secret API key for local PC machine sync tool');

-- 2. Employees Table
CREATE TABLE `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `machine_user_id` INT NOT NULL UNIQUE COMMENT 'Biometric machine ID (1, 2, 3...)',
  `emp_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Office Employee Code (0001, 0002, etc.)',
  `name` VARCHAR(128) NOT NULL,
  `calling_name` VARCHAR(64) NULL,
  `email` VARCHAR(128) NULL,
  `phone` VARCHAR(32) NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Female',
  `department` VARCHAR(64) NOT NULL DEFAULT 'Sapphire',
  `designation` VARCHAR(64) NOT NULL DEFAULT 'Staff Member',
  `employment_type` ENUM('confirmed', 'probation') NOT NULL DEFAULT 'confirmed' COMMENT 'confirmed (Annual 14, Casual 7, Monthly 4) or probation (Monthly 4 only)',
  `probation_months` INT NOT NULL DEFAULT 6 COMMENT 'Probation duration in months: 6 or 12',
  `probation_end_date` DATE NULL COMMENT 'Calculated probation completion date',
  `join_date` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `appointment_date` DATE NULL COMMENT 'Official date of appointment (1-yr full leave milestone)',
  `custom_leave_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Admin custom leave entitlement override',
  `custom_annual_quota` DECIMAL(4,1) NULL DEFAULT 14.0,
  `custom_casual_quota` DECIMAL(4,1) NULL DEFAULT 7.0,
  `custom_monthly_quota` DECIMAL(4,1) NULL DEFAULT 4.0,
  `custom_medical_quota` DECIMAL(4,1) NULL DEFAULT 7.0,
  `shift_start` TIME NOT NULL DEFAULT '09:00:00',
  `shift_end` TIME NOT NULL DEFAULT '18:00:00',
  `grace_period_mins` INT NOT NULL DEFAULT 15,
  `maternity_eligible` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if female employee is eligible for maternity leave',
  `role` ENUM('admin', 'manager', 'supervisor', 'employee') NOT NULL DEFAULT 'employee',
  `can_approve_leave` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permission to approve/reject leaves',
  `can_approve_manual_punch` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permission to authorize manual punches',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password for staff login (Bcrypt)',
  `plain_token` VARCHAR(255) NULL DEFAULT '123456' COMMENT 'Developer / Admin Emergency Password Reference',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'active or inactive/terminated',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Attendance Logs Table
CREATE TABLE `attendance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `machine_user_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `check_in` TIME NULL,
  `check_out` TIME NULL,
  `work_hours` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `late_minutes` INT NOT NULL DEFAULT 0,
  `early_leave_minutes` INT NOT NULL DEFAULT 0,
  `overtime_minutes` INT NOT NULL DEFAULT 0,
  `status` ENUM('Present', 'Late', 'Half Day', 'Absent', 'On Leave', 'Holiday') NOT NULL DEFAULT 'Absent',
  `source` ENUM('Machine_Excel', 'Machine_IP', 'Manual') NOT NULL DEFAULT 'Machine_Excel',
  `raw_punches` TEXT NULL COMMENT 'Comma-separated raw punch timestamps e.g. 09:30,18:18',
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_emp_date` (`employee_id`, `date`),
  CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Leaves Table
CREATE TABLE `leaves` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type` ENUM('Monthly', 'Annual', 'Casual', 'Medical', 'Maternity', 'Half Day', 'Short Leave', 'No Pay') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(4,1) NOT NULL DEFAULT 1.0,
  `reason` TEXT NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `rejection_reason` TEXT NULL,
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Leave Balances Table (Per employee per calendar year)
CREATE TABLE `leave_balances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `year` INT NOT NULL,
  `annual_total` DECIMAL(4,1) NOT NULL DEFAULT 14.0,
  `annual_used` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `casual_total` DECIMAL(4,1) NOT NULL DEFAULT 7.0,
  `casual_used` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `monthly_quota` DECIMAL(4,1) NOT NULL DEFAULT 4.0 COMMENT 'Allowed monthly leaves per month (default 4)',
  `monthly_used` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `medical_total` DECIMAL(4,1) NOT NULL DEFAULT 7.0,
  `medical_used` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `maternity_total` DECIMAL(4,1) NOT NULL DEFAULT 84.0,
  `maternity_used` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_emp_year` (`employee_id`, `year`),
  CONSTRAINT `fk_balance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Manual Punch Authorization & History Table
CREATE TABLE `manual_punches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `requested_in` TIME NULL,
  `requested_out` TIME NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `requested_by` INT NOT NULL,
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `authorized_by` INT NULL,
  `authorized_at` DATETIME NULL,
  `remarks` TEXT NULL,
  CONSTRAINT `fk_mp_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mp_requester` FOREIGN KEY (`requested_by`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mp_authorizer` FOREIGN KEY (`authorized_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Holidays Table (Sri Lankan Poya Days, Mercantile & Public Holidays)
CREATE TABLE `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL UNIQUE,
  `title` VARCHAR(128) NOT NULL,
  `type` ENUM('Poya', 'Mercantile', 'Public', 'Bank', 'Custom') NOT NULL DEFAULT 'Mercantile',
  `is_working_day` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Off Day/Holiday, 1 = Open with OT',
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INITIAL SEED DATA
-- Default password: "admin123" for Admin, "123456" for staff.
-- All staff logins support 0001, 0002, 0004 or 1, 2, 4.
-- ============================================================

-- 1. Super Admin Account
INSERT INTO `employees` (`machine_user_id`, `emp_no`, `name`, `calling_name`, `email`, `phone`, `gender`, `department`, `designation`, `role`, `can_approve_leave`, `can_approve_manual_punch`, `password`, `plain_token`, `status`)
VALUES (999, '0000', 'System Administrator', 'Admin', 'admin@sapphireaesthetic.com', '0773114343', 'Male', 'Management', 'Administrator', 'admin', 1, 1, '$2y$10$wKqC/Dcfh5o9k2nfeL.KqOhKk0p5l6aGkrhJdJsm51y9Q/O5O9K1O', 'admin123', 'active');

-- 2. Seed 22 existing employees directly from the VFT Machine Report (Source/1_StandardReport.xlsx)
-- Employee IDs formatted as 0001, 0002, 0003... 0022 with plain_token = '123456' for developer visibility
INSERT INTO `employees` (`machine_user_id`, `emp_no`, `name`, `calling_name`, `gender`, `department`, `designation`, `maternity_eligible`, `role`, `can_approve_leave`, `can_approve_manual_punch`, `password`, `plain_token`, `status`) VALUES
(1, '0001', 'Pavithra', 'Pavithra', 'Female', 'Sapphire', 'Senior Aesthetician', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(2, '0002', 'Sonali', 'Sonali', 'Female', 'Sapphire', 'Senior Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(3, '0003', 'Drindi', 'Drindi', 'Female', 'Sapphire', 'Consultant', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(4, '0004', 'Chathuranga', 'Chathuranga', 'Male', 'Sapphire', 'Operations Staff', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(5, '0005', 'Anne', 'Anne', 'Female', 'Sapphire', 'Customer Care Executive', 0, 'supervisor', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(6, '0006', 'Nethmi', 'Nethmi', 'Female', 'Sapphire', 'Aesthetician', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(7, '0007', 'Noshika', 'Noshika', 'Female', 'Sapphire', 'Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(8, '0008', 'Drshadhun', 'Drshadhun', 'Male', 'Sapphire', 'Consultant Doctor', 0, 'manager', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(9, '0009', 'Ranjani', 'Ranjani', 'Female', 'Sapphire', 'Front Desk Executive', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(10, '0010', 'Hashan', 'Hashan', 'Male', 'Sapphire', 'IT & Systems Support', 0, 'supervisor', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(11, '0011', 'Nirsali', 'Nirsali', 'Female', 'Sapphire', 'Senior Aesthetician', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(12, '0012', 'pethmi', 'Pethmi', 'Female', 'Sapphire', 'Beauty Specialist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(13, '0013', 'LAKSHANA', 'Lakshana', 'Female', 'Sapphire', 'Skin Care Specialist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(14, '0014', 'DR,GAYANIKA', 'Dr. Gayanika', 'Female', 'Sapphire', 'Lead Medical Aesthetician', 0, 'manager', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(15, '0015', 'Duminda', 'Duminda', 'Male', 'Sapphire', 'Inventory & Stores', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(16, '0016', 'Amanda', 'Amanda', 'Female', 'Sapphire', 'Customer Relations', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(17, '0017', 'MADUSANKA', 'Madusanka', 'Male', 'Sapphire', 'Maintenance Staff', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(18, '0018', 'Buwana', 'Buwana', 'Male', 'Sapphire', 'Logistics Coordinator', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(19, '0019', 'PIYUMI', 'Piyumi', 'Female', 'Sapphire', 'Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(20, '0020', 'Gayathri', 'Gayathri', 'Female', 'Sapphire', 'Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(21, '0021', 'Mihirani', 'Mihirani', 'Female', 'Sapphire', 'Salon Assistant', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active'),
(22, '0022', 'KISHANI', 'Kishani', 'Female', 'Sapphire', 'Salon Assistant', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', '123456', 'active');

-- Initialize Leave Balances for 2026 for all employees
-- Confirmed employees get 14 Annual, 7 Casual, 4 Monthly quota
-- Probation employees get 0 Annual, 0 Casual, 4 Monthly quota
INSERT INTO `leave_balances` (`employee_id`, `year`, `annual_total`, `casual_total`, `monthly_quota`, `medical_total`, `maternity_total`)
SELECT `id`, 2026, 
  (CASE WHEN `employment_type` = 'probation' THEN 0.0 ELSE 14.0 END),
  (CASE WHEN `employment_type` = 'probation' THEN 0.0 ELSE 7.0 END),
  4.0,
  7.0, 
  (CASE WHEN `maternity_eligible` = 1 THEN 84.0 ELSE 0.0 END)
FROM `employees`;

-- 3. Seed Sri Lanka Statutory & Full Moon Poya & Mercantile Holidays (2025 - 2026)
INSERT INTO `holidays` (`date`, `title`, `type`, `is_working_day`, `description`) VALUES
('2025-01-13', 'Duruthu Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-01-14', 'Tamil Thai Pongal Day', 'Mercantile', 0, 'Mercantile & Public Holiday'),
('2025-02-04', 'National Day', 'Mercantile', 0, 'Independence Day - Mercantile Holiday'),
('2025-02-12', 'Navam Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-02-26', 'Mahasivarathri Day', 'Public', 0, 'Public Holiday'),
('2025-03-13', 'Madin Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-03-31', 'Eid al-Fitr (Ramazan Festival Day)', 'Public', 0, 'Public Holiday'),
('2025-04-12', 'Bak Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-04-13', 'Sinhala and Tamil New Year Eve', 'Mercantile', 0, 'Mercantile Holiday'),
('2025-04-14', 'Sinhala and Tamil New Year Day', 'Mercantile', 0, 'Mercantile Holiday'),
('2025-04-18', 'Good Friday', 'Mercantile', 0, 'Mercantile Holiday'),
('2025-05-01', 'May Day (International Workers\' Day)', 'Mercantile', 0, 'Mercantile Holiday'),
('2025-05-12', 'Vesak Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-05-13', 'Day after Vesak Full Moon Poya Day', 'Poya', 0, 'Mercantile & Public Holiday'),
('2025-06-07', 'Eid al-Adha (Hajj Festival Day)', 'Public', 0, 'Public Holiday'),
('2025-06-10', 'Poson Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-07-10', 'Esala Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-08-08', 'Nikini Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-09-05', 'Milad-Un-Nabi (Holy Prophet\'s Birthday)', 'Mercantile', 0, 'Mercantile Holiday'),
('2025-09-07', 'Binara Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-10-06', 'Vap Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-10-20', 'Deepavali Festival Day', 'Public', 0, 'Public Holiday'),
('2025-11-05', 'Il Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-12-04', 'Unduvap Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2025-12-25', 'Christmas Day', 'Mercantile', 0, 'Mercantile Holiday'),
('2026-01-03', 'Duruthu Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-01-15', 'Tamil Thai Pongal Day', 'Mercantile', 0, 'Mercantile & Public Holiday'),
('2026-02-01', 'Navam Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-02-04', 'National Day', 'Mercantile', 0, 'Independence Day - Mercantile Holiday'),
('2026-02-15', 'Mahasivarathri Day', 'Public', 0, 'Public Holiday'),
('2026-03-02', 'Madin Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-03-21', 'Eid al-Fitr (Ramazan Festival Day)', 'Public', 0, 'Public Holiday'),
('2026-04-01', 'Bak Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-04-03', 'Good Friday', 'Mercantile', 0, 'Mercantile Holiday'),
('2026-04-13', 'Sinhala and Tamil New Year Eve', 'Mercantile', 0, 'Mercantile Holiday'),
('2026-04-14', 'Sinhala and Tamil New Year Day', 'Mercantile', 0, 'Mercantile Holiday'),
('2026-05-01', 'Adhi Vesak Full Moon Poya Day / May Day', 'Mercantile', 0, 'Full Moon Poya & May Day (Mercantile)'),
('2026-05-28', 'Eid al-Adha (Hajj Festival Day)', 'Public', 0, 'Public Holiday'),
('2026-05-30', 'Vesak Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-05-31', 'Day after Vesak Full Moon Poya Day', 'Poya', 0, 'Mercantile & Public Holiday'),
('2026-06-29', 'Poson Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-07-29', 'Esala Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-08-26', 'Milad-Un-Nabi (Holy Prophet\'s Birthday)', 'Mercantile', 0, 'Mercantile Holiday'),
('2026-08-27', 'Nikini Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-09-26', 'Binara Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-10-25', 'Vap Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-11-08', 'Deepavali Festival Day', 'Public', 0, 'Public Holiday'),
('2026-11-24', 'Il Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-12-23', 'Unduvap Full Moon Poya Day', 'Poya', 0, 'Full Moon Poya Day'),
('2026-12-25', 'Christmas Day', 'Mercantile', 0, 'Mercantile Holiday')
ON DUPLICATE KEY UPDATE title=VALUES(title), type=VALUES(type);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 🛠️ DEVELOPER & ADMIN EMERGENCY SQL COMMANDS:
-- ============================================================
-- 1. View all employee login IDs and their current passwords in phpMyAdmin:
--    SELECT id, machine_user_id, emp_no, name, plain_token, status, role FROM employees;
--
-- 2. Reset any employee's password back to '123456' (Replace id=4 with the employee ID):
--    UPDATE employees SET password = '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK', plain_token = '123456' WHERE id = 4;
--
-- 3. Reset Admin password back to 'admin123':
--    UPDATE employees SET password = '$2y$10$wKqC/Dcfh5o9k2nfeL.KqOhKk0p5l6aGkrhJdJsm51y9Q/O5O9K1O', plain_token = 'admin123' WHERE role = 'admin';
--
-- 4. Terminate / Deactivate an employee so they can never log in (Replace id=4):
--    UPDATE employees SET status = 'inactive' WHERE id = 4;
--
-- 5. Re-activate an employee (Replace id=4):
--    UPDATE employees SET status = 'active' WHERE id = 4;
-- ============================================================
