<?php
require_once 'includes/db.php';

try {
    // 1. Update Students table to include profile_picture if not exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM students LIKE 'profile_picture'");
    if ($checkColumn->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
    }

    // 2. Create Alumni table
    $pdo->exec("CREATE TABLE IF NOT EXISTS alumni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        graduation_year YEAR NOT NULL,
        position VARCHAR(255),
        company VARCHAR(255),
        image_url VARCHAR(255),
        testimonial TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Create Projects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image_url VARCHAR(255),
        project_date DATE,
        status ENUM('completed', 'ongoing', 'planned') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Create Gallery table
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        image_url VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Create Student Resources table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_url VARCHAR(255) NOT NULL,
        resource_type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Create News table (for scraping results)
    $pdo->exec("CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        source_url VARCHAR(255) UNIQUE,
        image_url VARCHAR(255),
        published_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. Create Messages table (for in-app messaging)
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT, -- NULL for broadcast
        title VARCHAR(255),
        content TEXT NOT NULL,
        is_broadcast TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 8. Create Contact Submissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255),
        message TEXT NOT NULL,
        response TEXT,
        responded_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $checkEventsSource = $pdo->query("SHOW COLUMNS FROM events LIKE 'source_url'");
    if ($checkEventsSource->rowCount() == 0) {
        $pdo->exec("ALTER TABLE events ADD COLUMN source_url VARCHAR(255) NULL AFTER location");
    }

    $eventRows = [
        ['2026 Matriculation Ceremony', 'Matriculation ceremony for fresh Postgraduate and Undergraduate students admitted for the 2025/2026 Academic Year.', '2026-02-20 10:00:00', 'AAMUSTED, Kumasi & Mampong Campuses', 'https://aamusted.edu.gh/aamusted-organises-2025-2026-orientation-for-fresh-students/'],
        ['Matriculation Ceremony 2025 – Sandwich Session', 'Official matriculation for fresh students admitted to the Sandwich Session for the 2025 academic year.', '2025-12-15 09:00:00', 'Main Auditorium', 'https://aamusted.edu.gh/'],
        ['Medical Examinations for Fresh Students', 'Commencement of mandatory medical examinations for all newly admitted students for the academic year.', '2025-12-01 08:00:00', 'University Clinic', 'https://mampong.aamusted.edu.gh/2022/01/12/student-medical-examination-2022/'],
        ['First Congregation', 'Graduation ceremony for graduands of the university.', '2023-05-17 09:00:00', 'Ceremonial Grounds', 'https://aamusted.edu.gh/'],
        ['Graduate Seminar Series', 'A virtual seminar series organized for graduate students and researchers.', '2023-03-21 10:00:00', 'Virtual (Zoom)', 'https://aamusted.edu.gh/']
    ];
    $insertEvent = $pdo->prepare("INSERT INTO events (title, description, event_date, location, source_url) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = ?)");
    foreach ($eventRows as $r) {
        $insertEvent->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[0]]);
    }

    $galleryRows = [
        ['USTED Change of Name', 'images/aamusted.jpg', 'University Update'],
        ['INFOTESS Spotlight', 'images/infotess.png', 'Society Activities'],
        ['Campus Moments', 'images/aamusted-logo.svg', 'Events & Community']
    ];
    $insertGallery = $pdo->prepare("INSERT INTO gallery (title, image_url, category) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title = ?)");
    foreach ($galleryRows as $r) {
        $insertGallery->execute([$r[0], $r[1], $r[2], $r[0]]);
    }

    $newsRows = [
        ['AAMUSTED Honours Former Staff', 'AAMUSTED has recognised former staff for their years of dedicated service and contribution to the university community.', 'https://aamusted.edu.gh/', 'images/aamusted.jpg', '2026-03-05 10:00:00'],
        ['AAMUSTED Student Entrepreneurship Team Excels', 'Student innovators continue to represent AAMUSTED strongly in national innovation and entrepreneurship events.', 'https://aamusted.edu.gh/aamusted-student-entrepreneurship-team-qualifies-for-semis-of-mcdan-youth-challenge/', 'images/aamusted.jpg', '2026-02-04 10:00:00']
    ];
    $insertNews = $pdo->prepare("INSERT INTO news (title, content, source_url, image_url, published_at) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM news WHERE source_url = ?)");
    foreach ($newsRows as $r) {
        $insertNews->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[2]]);
    }

    $projectRows = [
        ['INFOTESS P.A System Donation Project', 'INFOTESS donated 15 P.A systems to IT and educational lecturers as part of a community impact initiative.', 'images/infotess.png', '2025-11-15', 'completed'],
        ['Student Tech Mentorship Series', 'Peer-led mentorship sessions helping students build practical software and data skills.', 'images/aamusted.jpg', '2026-01-20', 'ongoing'],
        ['Campus Smart Noticeboard Prototype', 'A prototype digital noticeboard project for centralized campus announcements.', 'images/aamusted-logo.svg', '2026-04-10', 'planned']
    ];
    $insertProject = $pdo->prepare("INSERT INTO projects (title, description, image_url, project_date, status) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM projects WHERE title = ?)");
    foreach ($projectRows as $r) {
        $insertProject->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[0]]);
    }

    $alumniRows = [
        ['Kwame Ahin Adu Ezekiel', '2024', 'Alumni President', 'AAMUSTED Alumni Association', 'images/aamusted.jpg', 'Proud to support student innovation and leadership at INFOTESS.'],
        ['Koomson Thomas', '2025', 'Alumni President', 'Technology Education Community', 'images/infotess.png', 'Stay connected, keep building, and always mentor the next cohort.'],
        ['Ama Serwaa Boateng', '2023', 'Alumni Member', 'EdTech Practitioner', 'images/aamusted-logo.svg', 'INFOTESS helped shape my practical skills and confidence in tech.']
    ];
    $insertAlumni = $pdo->prepare("INSERT INTO alumni (full_name, graduation_year, position, company, image_url, testimonial) SELECT ?, ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM alumni WHERE full_name = ?)");
    foreach ($alumniRows as $r) {
        $insertAlumni->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[0]]);
    }

    $resourceRows = [
        ['AAMUSTED Library Portal', 'Access the university library services, research databases, and digital collections.', 'https://aamusted.edu.gh/library/', 'Portal'],
        ['AAMUSTED Library E-Resources', 'Browse academic e-resources and research platforms curated for students.', 'https://aamusted.edu.gh/library/e-resources/', 'E-Resources'],
        ['AAMUSTED LMS', 'Open the Learning Management System for lecture content, assignments, and course activities.', 'https://lms.aamusted.edu.gh/', 'LMS'],
        ['Student LMS Quick Guide', 'Follow the official quick guide for navigating and using AAMUSTED LMS as a student.', 'https://itdirectorate.aamusted.edu.gh/index.php/a-quick-guide-for-student-lms/', 'Guide'],
        ['Provisional Fees Schedule (2025/2026)', 'Download and review the approved fee schedules for the 2025/2026 academic year.', 'https://aamusted.edu.gh/fees-schedule-for-2025-2026-academic-year/', 'Fees'],
        ['Academic Calendar', 'Track semester timelines, reopening dates, and key academic milestones.', 'https://aamusted.edu.gh/category/academic_calendar/', 'Calendar'],
        ['Admissions & Applications', 'Check application updates, admission requirements, and programme entry details.', 'https://aamusted.edu.gh/apply/', 'Admissions'],
        ['AAMUSTED Mail Access', 'Open official mail access information and related student communication tools.', 'https://aamusted.edu.gh/aamusted-mail/', 'Communication'],
        ['OSIS Password Reset Guide', 'Use the official AAMUSTED-Mampong guide for student OSIS password reset steps.', 'https://mampong.aamusted.edu.gh/guide-to-resetting-changing-your-osis-password/', 'OSIS']
    ];
    $insertResource = $pdo->prepare("INSERT INTO student_resources (title, description, file_url, resource_type) SELECT ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM student_resources WHERE title = ?)");
    foreach ($resourceRows as $r) {
        $insertResource->execute([$r[0], $r[1], $r[2], $r[3], $r[0]]);
    }

    $notificationRows = [
        ['Welcome to INFOTESS Portal', 'Welcome to the INFOTESS student portal. Check your dashboard regularly for updates and announcements.'],
        ['Password Security Reminder', 'Use your temporary password to login and reset it immediately to keep your account secure.'],
        ['Dues Payment Reminder', 'Please review your dues status and complete pending payments before the deadline.']
    ];
    $studentUsers = $pdo->query("SELECT id FROM users WHERE role = 'student'")->fetchAll();
    $insertNotification = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) SELECT ?, ?, ?, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = ? AND title = ?)");
    foreach ($studentUsers as $u) {
        foreach ($notificationRows as $n) {
            $insertNotification->execute([(int)$u['id'], $n[0], $n[1], (int)$u['id'], $n[0]]);
        }
    }

    echo "Database schema updated successfully!";
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>
