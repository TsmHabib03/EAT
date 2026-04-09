-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 09, 2026 at 07:27 AM
-- Server version: 8.0.41
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `employee_tracker`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetEmployeeAttendance` (IN `p_employee_id` VARCHAR(20), IN `p_start_date` DATE, IN `p_end_date` DATE)   BEGIN
    SELECT
        ea.*,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.department_code,
        e.shift_code
    FROM employee_attendance ea
    INNER JOIN employees e ON ea.employee_id = e.employee_id
    WHERE ea.employee_id = p_employee_id
      AND ea.date BETWEEN p_start_date AND p_end_date
    ORDER BY ea.date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkEmployeeClockIn` (IN `p_employee_id` VARCHAR(20), IN `p_date` DATE, IN `p_time` TIME, IN `p_source` VARCHAR(10))   BEGIN
    DECLARE v_shift_code VARCHAR(50);
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*), shift_code
    INTO v_exists, v_shift_code
    FROM employees
    WHERE employee_id = p_employee_id
    LIMIT 1;

    IF v_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Employee not found';
    END IF;

    INSERT INTO employee_attendance (employee_id, date, time_in, shift_code, status, source)
    VALUES (p_employee_id, p_date, p_time, v_shift_code, 'clock_in', IFNULL(NULLIF(p_source, ''), 'qr'))
    ON DUPLICATE KEY UPDATE
        time_in = IF(time_in IS NULL, VALUES(time_in), time_in),
        shift_code = VALUES(shift_code),
        status = IF(time_out IS NULL, 'clock_in', 'clock_out'),
        updated_at = CURRENT_TIMESTAMP;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkEmployeeClockOut` (IN `p_employee_id` VARCHAR(20), IN `p_date` DATE, IN `p_time` TIME)   BEGIN
    DECLARE v_rows_affected INT DEFAULT 0;

    UPDATE employee_attendance
    SET
        time_out = p_time,
        status = 'clock_out',
        updated_at = CURRENT_TIMESTAMP
    WHERE employee_id = p_employee_id
      AND date = p_date
      AND time_in IS NOT NULL
      AND time_out IS NULL;

    SET v_rows_affected = ROW_COUNT();

    IF v_rows_affected = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No Clock In record found or Clock Out already recorded for today';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RegisterEmployee` (IN `p_employee_id` VARCHAR(20), IN `p_first_name` VARCHAR(50), IN `p_middle_name` VARCHAR(50), IN `p_last_name` VARCHAR(50), IN `p_gender` VARCHAR(10), IN `p_work_email` VARCHAR(100), IN `p_department_code` VARCHAR(50), IN `p_shift_code` VARCHAR(50), IN `p_work_mode` VARCHAR(10), IN `p_hire_date` DATE)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF p_employee_id NOT REGEXP '^[A-Za-z0-9_-]{3,20}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid employee ID format. Use 3-20 letters, numbers, underscore, or dash.';
    END IF;

    IF p_work_email IS NULL OR p_work_email = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Work email is required';
    END IF;

    INSERT INTO employees (
        employee_id,
        first_name,
        middle_name,
        last_name,
        gender,
        work_email,
        department_code,
        shift_code,
        work_mode,
        hire_date
    ) VALUES (
        p_employee_id,
        p_first_name,
        p_middle_name,
        p_last_name,
        p_gender,
        p_work_email,
        p_department_code,
        NULLIF(p_shift_code, ''),
        IFNULL(NULLIF(p_work_mode, ''), 'WFH'),
        p_hire_date
    );

    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin12', '$2y$10$cLdwbGeQ4R0j4HGnYLk2P.BTRSJQS5MXq8E3oHemdBGHGYwGaUSCa', 'jaudianhabib879@gmail.com', 'Habib Jaudian', 'admin', 1, '2026-04-09 05:19:15', '2026-04-08 09:09:31', '2026-04-09 05:19:15');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_corrections`
--

CREATE TABLE `attendance_corrections` (
  `id` int NOT NULL,
  `employee_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attendance_date` date NOT NULL,
  `requested_time_in` time DEFAULT NULL,
  `requested_time_out` time DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `reviewer_admin_id` int DEFAULT NULL,
  `review_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee attendance correction workflow';

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `department_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Company departments for employee attendance';

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `employee_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Company employee identifier',
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female','M','F') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male',
  `work_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_mode` enum('WFH','Hybrid','Onsite') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'WFH',
  `is_active` tinyint(1) DEFAULT '1',
  `hire_date` date DEFAULT NULL,
  `qr_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee master records';

-- --------------------------------------------------------

--
-- Table structure for table `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `id` int NOT NULL,
  `employee_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `break_out` time DEFAULT NULL,
  `break_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `shift_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('clock_in','clock_out','incomplete','corrected','absent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'clock_in',
  `late_minutes` int DEFAULT '0',
  `work_mode` enum('WFH','Hybrid','Onsite') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'WFH',
  `source` enum('qr','web','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'qr',
  `email_sent` tinyint(1) DEFAULT '0',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily employee clock in and out records';

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int NOT NULL,
  `shift_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `grace_minutes` int DEFAULT '15',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee shift definitions';

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_employee_daily_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_employee_daily_summary` (
`date` date
,`department_code` varchar(50)
,`late_count` decimal(23,0)
,`needs_time_out` decimal(23,0)
,`shift_code` varchar(50)
,`total_records` bigint
,`with_time_in` decimal(23,0)
,`with_time_out` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_employee_roster`
-- (See below for the actual view)
--
CREATE TABLE `v_employee_roster` (
`department_code` varchar(50)
,`employee_id` varchar(20)
,`full_name` varchar(104)
,`gender` enum('Male','Female','M','F')
,`id` int
,`last_attendance_date` date
,`shift_code` varchar(50)
,`work_email` varchar(100)
,`work_mode` enum('WFH','Hybrid','Onsite')
);

-- --------------------------------------------------------

--
-- Structure for view `v_employee_daily_summary`
--
DROP TABLE IF EXISTS `v_employee_daily_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_employee_daily_summary`  AS SELECT `ea`.`date` AS `date`, `e`.`department_code` AS `department_code`, `ea`.`shift_code` AS `shift_code`, count(0) AS `total_records`, sum((case when (`ea`.`time_in` is not null) then 1 else 0 end)) AS `with_time_in`, sum((case when (`ea`.`time_out` is not null) then 1 else 0 end)) AS `with_time_out`, sum((case when ((`ea`.`time_in` is not null) and (`ea`.`time_out` is null)) then 1 else 0 end)) AS `needs_time_out`, sum((case when (`ea`.`late_minutes` > 0) then 1 else 0 end)) AS `late_count` FROM (`employee_attendance` `ea` join `employees` `e` on((`e`.`employee_id` = `ea`.`employee_id`))) GROUP BY `ea`.`date`, `e`.`department_code`, `ea`.`shift_code` ORDER BY `ea`.`date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_employee_roster`
--
DROP TABLE IF EXISTS `v_employee_roster`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_employee_roster`  AS SELECT `e`.`id` AS `id`, `e`.`employee_id` AS `employee_id`, concat(`e`.`first_name`,' ',coalesce(concat(left(`e`.`middle_name`,1),'. '),''),`e`.`last_name`) AS `full_name`, `e`.`department_code` AS `department_code`, `e`.`shift_code` AS `shift_code`, `e`.`work_email` AS `work_email`, `e`.`gender` AS `gender`, `e`.`work_mode` AS `work_mode`, (select max(`ea`.`date`) from `employee_attendance` `ea` where (`ea`.`employee_id` = `e`.`employee_id`)) AS `last_attendance_date` FROM `employees` AS `e` ORDER BY `e`.`department_code` ASC, `e`.`shift_code` ASC, `e`.`last_name` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance_corrections`
--
ALTER TABLE `attendance_corrections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_correction_employee_date` (`employee_id`,`attendance_date`),
  ADD KEY `idx_correction_status` (`status`),
  ADD KEY `idx_correction_reviewer` (`reviewer_admin_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_department_code` (`department_code`),
  ADD KEY `idx_department_active` (`is_active`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_employee_id` (`employee_id`),
  ADD UNIQUE KEY `uniq_employee_email` (`work_email`),
  ADD KEY `idx_employee_department` (`department_code`),
  ADD KEY `idx_employee_shift` (`shift_code`),
  ADD KEY `idx_employee_active` (`is_active`);

--
-- Indexes for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_daily_employee_attendance` (`employee_id`,`date`),
  ADD KEY `idx_employee_date` (`employee_id`,`date`),
  ADD KEY `idx_attendance_shift` (`shift_code`),
  ADD KEY `idx_attendance_status` (`status`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_shift_code` (`shift_code`),
  ADD KEY `idx_shift_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_corrections`
--
ALTER TABLE `attendance_corrections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_corrections`
--
ALTER TABLE `attendance_corrections`
  ADD CONSTRAINT `attendance_corrections_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_department_fk` FOREIGN KEY (`department_code`) REFERENCES `departments` (`department_code`) ON UPDATE CASCADE,
  ADD CONSTRAINT `employees_shift_fk` FOREIGN KEY (`shift_code`) REFERENCES `shifts` (`shift_code`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD CONSTRAINT `employee_attendance_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `employee_attendance_shift_fk` FOREIGN KEY (`shift_code`) REFERENCES `shifts` (`shift_code`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
