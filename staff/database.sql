-- Sapphire Aesthetic & Luxury - Attendance & HR Management System Database Schema
-- Character set utf8mb4

SET FOREIGN_KEY_CHECKS = 0;

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
  `emp_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Office Employee Code (EMP001, etc.)',
  `name` VARCHAR(128) NOT NULL,
  `calling_name` VARCHAR(64) NULL,
  `email` VARCHAR(128) NULL,
  `phone` VARCHAR(32) NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Female',
  `department` VARCHAR(64) NOT NULL DEFAULT 'Sapphire',
  `designation` VARCHAR(64) NOT NULL DEFAULT 'Staff Member',
  `join_date` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `shift_start` TIME NOT NULL DEFAULT '09:00:00',
  `shift_end` TIME NOT NULL DEFAULT '18:00:00',
  `grace_period_mins` INT NOT NULL DEFAULT 15,
  `maternity_eligible` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if female employee is eligible for maternity leave',
  `role` ENUM('admin', 'manager', 'supervisor', 'employee') NOT NULL DEFAULT 'employee',
  `can_approve_leave` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permission to approve/reject leaves',
  `can_approve_manual_punch` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permission to authorize manual punches',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password for staff login',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
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
  `leave_type` ENUM('Annual', 'Casual', 'Medical', 'Maternity', 'Half Day', 'Short Leave') NOT NULL,
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
  `medical_total` DECIMAL(4,1) NOT NULL DEFAULT 14.0,
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

-- ============================================================
-- INITIAL SEED DATA
-- Default password for all initial accounts: "admin123" for admin, "123456" for staff.
-- Password hash for 'admin123': $2y$10$wKqC/Dcfh5o9k2nfeL.KqOhKk0p5l6aGkrhJdJsm51y9Q/O5O9K1O
-- Password hash for '123456': $2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK
-- Note: Our PHP login code also supports automatic upgrade from plaintext if initially set.
-- ============================================================

-- 1. Super Admin Account
INSERT INTO `employees` (`machine_user_id`, `emp_no`, `name`, `calling_name`, `email`, `phone`, `gender`, `department`, `designation`, `role`, `can_approve_leave`, `can_approve_manual_punch`, `password`, `status`)
VALUES (999, 'ADM001', 'System Administrator', 'Admin', 'admin@sapphireaesthetic.com', '0773114343', 'Male', 'Management', 'Administrator', 'admin', 1, 1, '$2y$10$wKqC/Dcfh5o9k2nfeL.KqOhKk0p5l6aGkrhJdJsm51y9Q/O5O9K1O', 'active');

-- 2. Seed 22 existing employees directly from the VFT Machine Report (Source/1_StandardReport.xlsx)
-- Maternity Leave starts as 0 (Disabled) for all employees by default. Admin activates it per employee on demand!
INSERT INTO `employees` (`machine_user_id`, `emp_no`, `name`, `calling_name`, `gender`, `department`, `designation`, `maternity_eligible`, `role`, `can_approve_leave`, `can_approve_manual_punch`, `password`) VALUES
(1, 'EMP001', 'Pavithra', 'Pavithra', 'Female', 'Sapphire', 'Senior Aesthetician', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(2, 'EMP002', 'Sonali', 'Sonali', 'Female', 'Sapphire', 'Senior Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(3, 'EMP003', 'Drindi', 'Drindi', 'Female', 'Sapphire', 'Consultant', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(4, 'EMP004', 'Chathuranga', 'Chathuranga', 'Male', 'Sapphire', 'Operations Staff', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(5, 'EMP005', 'Anne', 'Anne', 'Female', 'Sapphire', 'Customer Care Executive', 0, 'supervisor', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(6, 'EMP006', 'Nethmi', 'Nethmi', 'Female', 'Sapphire', 'Aesthetician', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(7, 'EMP007', 'Noshika', 'Noshika', 'Female', 'Sapphire', 'Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(8, 'EMP008', 'Drshadhun', 'Drshadhun', 'Male', 'Sapphire', 'Consultant Doctor', 0, 'manager', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(9, 'EMP009', 'Ranjani', 'Ranjani', 'Female', 'Sapphire', 'Front Desk Executive', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(10, 'EMP010', 'Hashan', 'Hashan', 'Male', 'Sapphire', 'IT & Systems Support', 0, 'supervisor', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(11, 'EMP011', 'Nirsali', 'Nirsali', 'Female', 'Sapphire', 'Senior Aesthetician', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(12, 'EMP012', 'pethmi', 'Pethmi', 'Female', 'Sapphire', 'Beauty Specialist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(13, 'EMP013', 'LAKSHANA', 'Lakshana', 'Female', 'Sapphire', 'Skin Care Specialist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(14, 'EMP014', 'DR,GAYANIKA', 'Dr. Gayanika', 'Female', 'Sapphire', 'Lead Medical Aesthetician', 0, 'manager', 1, 1, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(15, 'EMP015', 'Duminda', 'Duminda', 'Male', 'Sapphire', 'Inventory & Stores', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(16, 'EMP016', 'Amanda', 'Amanda', 'Female', 'Sapphire', 'Customer Relations', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(17, 'EMP017', 'MADUSANKA', 'Madusanka', 'Male', 'Sapphire', 'Maintenance Staff', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(18, 'EMP018', 'Buwana', 'Buwana', 'Male', 'Sapphire', 'Logistics Coordinator', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(19, 'EMP019', 'PIYUMI', 'Piyumi', 'Female', 'Sapphire', 'Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(20, 'EMP020', 'Gayathri', 'Gayathri', 'Female', 'Sapphire', 'Therapist', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(21, 'EMP021', 'Mihirani', 'Mihirani', 'Female', 'Sapphire', 'Salon Assistant', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK'),
(22, 'EMP022', 'KISHANI', 'Kishani', 'Female', 'Sapphire', 'Salon Assistant', 0, 'employee', 0, 0, '$2y$10$zYf7OQ7R97sE6mF6f02Z8e9D47zDq3A5q3j2g/xXjN/mBsm2w1PzK');

-- Initialize Leave Balances for 2026 for all employees
INSERT INTO `leave_balances` (`employee_id`, `year`, `annual_total`, `casual_total`, `medical_total`, `maternity_total`)
SELECT `id`, 2026, 14.0, 7.0, 14.0, (CASE WHEN `maternity_eligible` = 1 THEN 84.0 ELSE 0.0 END)
FROM `employees`;

SET FOREIGN_KEY_CHECKS = 1;
