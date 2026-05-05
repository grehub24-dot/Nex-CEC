-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 24, 2026 at 01:08 PM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `infotess_sdms`
--
CREATE DATABASE IF NOT EXISTS `infotess_sdms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `infotess_sdms`;

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
CREATE TABLE IF NOT EXISTS `activities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `activity_date` datetime NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `registration_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

DROP TABLE IF EXISTS `alumni`;
CREATE TABLE IF NOT EXISTS `alumni` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `graduation_year` year NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `testimonial` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `alumni`
--

INSERT INTO `alumni` (`id`, `full_name`, `graduation_year`, `position`, `company`, `image_url`, `testimonial`, `created_at`) VALUES
(1, 'Kwame Ahin Adu Ezekiel', '2024', 'Alumni President', 'AAMUSTED Alumni Association', 'images/aamusted.jpg', 'Proud to support student innovation and leadership at INFOTESS.', '2026-04-24 12:48:32'),
(2, 'Koomson Thomas', '2025', 'Alumni President', 'Technology Education Community', 'images/infotess.png', 'Stay connected, keep building, and always mentor the next cohort.', '2026-04-24 12:48:32'),
(3, 'Ama Serwaa Boateng', '2023', 'Alumni Member', 'EdTech Practitioner', 'images/aamusted-logo.svg', 'INFOTESS helped shape my practical skills and confidence in tech.', '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_submissions`
--

DROP TABLE IF EXISTS `contact_submissions`;
CREATE TABLE IF NOT EXISTS `contact_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `response` text,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_info`
--

DROP TABLE IF EXISTS `department_info`;
CREATE TABLE IF NOT EXISTS `department_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL,
  `content` text,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `event_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `source_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `location`, `source_url`, `created_at`) VALUES
(1, '2026 Matriculation Ceremony', 'Matriculation ceremony for fresh Postgraduate and Undergraduate students admitted for the 2025/2026 Academic Year.', '2026-02-20 10:00:00', 'AAMUSTED, Kumasi & Mampong Campuses', 'https://aamusted.edu.gh/aamusted-organises-2025-2026-orientation-for-fresh-students/', '2026-04-24 12:48:32'),
(2, 'Matriculation Ceremony 2025 – Sandwich Session', 'Official matriculation for fresh students admitted to the Sandwich Session for the 2025 academic year.', '2025-12-15 09:00:00', 'Main Auditorium', 'https://aamusted.edu.gh/', '2026-04-24 12:48:32'),
(3, 'Medical Examinations for Fresh Students', 'Commencement of mandatory medical examinations for all newly admitted students for the academic year.', '2025-12-01 08:00:00', 'University Clinic', 'https://mampong.aamusted.edu.gh/2022/01/12/student-medical-examination-2022/', '2026-04-24 12:48:32'),
(4, 'First Congregation', 'Graduation ceremony for graduands of the university.', '2023-05-17 09:00:00', 'Ceremonial Grounds', 'https://aamusted.edu.gh/', '2026-04-24 12:48:32'),
(5, 'Graduate Seminar Series', 'A virtual seminar series organized for graduate students and researchers.', '2023-03-21 10:00:00', 'Virtual (Zoom)', 'https://aamusted.edu.gh/', '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `executives`
--

DROP TABLE IF EXISTS `executives`;
CREATE TABLE IF NOT EXISTS `executives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `position` varchar(100) NOT NULL,
  `bio` text,
  `image_url` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

DROP TABLE IF EXISTS `gallery`;
CREATE TABLE IF NOT EXISTS `gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `title`, `image_url`, `category`, `created_at`) VALUES
(1, 'USTED Change of Name', 'images/aamusted.jpg', 'University Update', '2026-04-24 12:48:32'),
(2, 'INFOTESS Spotlight', 'images/infotess.png', 'Society Activities', '2026-04-24 12:48:32'),
(3, 'Campus Moments', 'images/aamusted-logo.svg', 'Events & Community', '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `is_broadcast` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reads`
--

DROP TABLE IF EXISTS `message_reads`;
CREATE TABLE IF NOT EXISTS `message_reads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `message_id` int NOT NULL,
  `user_id` int NOT NULL,
  `read_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`message_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text,
  `source_url` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_url` (`source_url`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `content`, `source_url`, `image_url`, `published_at`, `created_at`) VALUES
(1, 'AAMUSTED Honours Former Staff', 'AAMUSTED has recognised former staff for their years of dedicated service and contribution to the university community.', 'https://aamusted.edu.gh/', 'images/aamusted.jpg', '2026-03-05 10:00:00', '2026-04-24 12:48:32'),
(2, 'AAMUSTED Student Entrepreneurship Team Excels', 'Student innovators continue to represent AAMUSTED strongly in national innovation and entrepreneurship events.', 'https://aamusted.edu.gh/aamusted-student-entrepreneurship-team-qualifies-for-semis-of-mcdan-youth-challenge/', 'images/aamusted.jpg', '2026-02-04 10:00:00', '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'Welcome to INFOTESS Portal', 'Welcome to the INFOTESS student portal. Check your dashboard regularly for updates and announcements.', 0, '2026-04-24 12:48:32'),
(2, 1, 'Password Security Reminder', 'Use your temporary password to login and reset it immediately to keep your account secure.', 0, '2026-04-24 12:48:32'),
(3, 1, 'Dues Payment Reminder', 'Please review your dues status and complete pending payments before the deadline.', 0, '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `payment_method` enum('Cash','Mobile Money','Bank Transfer') NOT NULL,
  `payment_date` date NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `recorded_by` int NOT NULL COMMENT 'Admin User ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `student_id` (`student_id`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `amount`, `academic_year`, `semester`, `payment_method`, `payment_date`, `receipt_number`, `recorded_by`, `created_at`) VALUES
(1, 1, 80.00, '2026/2027', '1', 'Cash', '2026-04-24', 'INFO-2604-2953', 2, '2026-04-24 13:07:32');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `image_url` varchar(255) DEFAULT NULL,
  `project_date` date DEFAULT NULL,
  `status` enum('completed','ongoing','planned') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `description`, `image_url`, `project_date`, `status`, `created_at`) VALUES
(1, 'INFOTESS P.A System Donation Project', 'INFOTESS donated 15 P.A systems to IT and educational lecturers as part of a community impact initiative.', 'images/infotess.png', '2025-11-15', 'completed', '2026-04-24 12:48:32'),
(2, 'Student Tech Mentorship Series', 'Peer-led mentorship sessions helping students build practical software and data skills.', 'images/aamusted.jpg', '2026-01-20', 'ongoing', '2026-04-24 12:48:32'),
(3, 'Campus Smart Noticeboard Prototype', 'A prototype digital noticeboard project for centralized campus announcements.', 'images/aamusted-logo.svg', '2026-04-10', 'planned', '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
CREATE TABLE IF NOT EXISTS `receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_id` int NOT NULL,
  `receipt_file_path` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verification_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `receipts`
--

INSERT INTO `receipts` (`id`, `payment_id`, `receipt_file_path`, `generated_at`, `verification_hash`) VALUES
(1, 1, 'receipt_INFO-2604-2953.html', '2026-04-24 13:07:32', '454a8ea9d9820ff3eba9941f4de99263');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `index_number` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `level` varchar(20) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_picture` varchar(255) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `stream` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `index_number` (`index_number`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `index_number`, `full_name`, `department`, `level`, `phone_number`, `created_at`, `updated_at`, `profile_picture`, `class_name`, `stream`) VALUES
(1, 1, '05230100408', 'David Amankwaah', 'B.Ed. Information Technology', '100', '0536282694', '2026-04-24 12:06:42', '2026-04-24 12:06:42', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_resources`
--

DROP TABLE IF EXISTS `student_resources`;
CREATE TABLE IF NOT EXISTS `student_resources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `file_url` varchar(255) NOT NULL,
  `resource_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_resources`
--

INSERT INTO `student_resources` (`id`, `title`, `description`, `file_url`, `resource_type`, `created_at`) VALUES
(1, 'AAMUSTED Library Portal', 'Access the university library services, research databases, and digital collections.', 'https://aamusted.edu.gh/library/', 'Portal', '2026-04-24 12:48:32'),
(2, 'AAMUSTED Library E-Resources', 'Browse academic e-resources and research platforms curated for students.', 'https://aamusted.edu.gh/library/e-resources/', 'E-Resources', '2026-04-24 12:48:32'),
(3, 'AAMUSTED LMS', 'Open the Learning Management System for lecture content, assignments, and course activities.', 'https://lms.aamusted.edu.gh/', 'LMS', '2026-04-24 12:48:32'),
(4, 'Student LMS Quick Guide', 'Follow the official quick guide for navigating and using AAMUSTED LMS as a student.', 'https://itdirectorate.aamusted.edu.gh/index.php/a-quick-guide-for-student-lms/', 'Guide', '2026-04-24 12:48:32'),
(5, 'Provisional Fees Schedule (2025/2026)', 'Download and review the approved fee schedules for the 2025/2026 academic year.', 'https://aamusted.edu.gh/fees-schedule-for-2025-2026-academic-year/', 'Fees', '2026-04-24 12:48:32'),
(6, 'Academic Calendar', 'Track semester timelines, reopening dates, and key academic milestones.', 'https://aamusted.edu.gh/category/academic_calendar/', 'Calendar', '2026-04-24 12:48:32'),
(7, 'Admissions & Applications', 'Check application updates, admission requirements, and programme entry details.', 'https://aamusted.edu.gh/apply/', 'Admissions', '2026-04-24 12:48:32'),
(8, 'AAMUSTED Mail Access', 'Open official mail access information and related student communication tools.', 'https://aamusted.edu.gh/aamusted-mail/', 'Communication', '2026-04-24 12:48:32'),
(9, 'OSIS Password Reset Guide', 'Use the official AAMUSTED-Mampong guide for student OSIS password reset steps.', 'https://mampong.aamusted.edu.gh/guide-to-resetting-changing-your-osis-password/', 'OSIS', '2026-04-24 12:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','executive','admin','super_admin') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_password_reset` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`, `is_password_reset`) VALUES
(1, 'amanvid.da@gmail.com', '$2y$10$X6T3ArzbSCZ.uwkr8JCSp..YRM4j1vQISghQNPGYEgUVEg1Xu/ogu', 'student', 'active', '2026-04-24 12:06:42', '2026-04-24 12:55:17', 1),
(2, 'admin@infotess.org', '$2y$10$YRNoSKY.hpBVI8PGmwOMNOZAmYXoAIzsnr0Py0vqoHiERUihByEkq', 'admin', 'active', '2026-04-24 13:03:46', '2026-04-24 13:03:46', 0);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
