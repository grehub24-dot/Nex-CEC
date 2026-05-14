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

    // 6. Create News table (for updates)
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
        ['School Reopening – First Term', 'School reopens for the first term of the academic year. All students are expected to report in full uniform.', date('Y-m-d', strtotime('first monday of september')) . ' 07:30:00', 'School Campus'],
        ['Open Day / Parent-Teacher Conference', 'Parents and guardians are invited to meet with teachers to discuss student progress and performance.', date('Y-m-d', strtotime('last friday of november')) . ' 09:00:00', 'School Hall'],
        ['Christmas Break', 'School closes for the Christmas holiday. Students resume next term.', date('Y-12-20') . ' 12:00:00', 'School Campus'],
        ['Second Term Begins', 'School reopens for the second term.', date('Y-01-10') . ' 07:30:00', 'School Campus'],
        ['BECE Preparation Clinic', 'Intensive revision classes for JHS 3 students preparing for the BECE.', date('Y-04-01') . ' 08:00:00', 'JHS Block'],
        ['End of Year Speech & Prize-Giving Day', 'Annual speech day and prize-giving ceremony to celebrate student achievements.', date('Y-07-15') . ' 09:00:00', 'School Auditorium']
    ];
    $insertEvent = $pdo->prepare("INSERT INTO events (title, description, event_date, location, source_url) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = ?)");
    foreach ($eventRows as $r) {
        $insertEvent->execute([$r[0], $r[1], $r[2], $r[3], '', $r[0]]);
    }

    $galleryRows = [
        ['School Campus', '', 'School Life'],
        ['Classroom Activities', '', 'Academics'],
        ['Sports & Culture', '', 'Events & Community']
    ];
    $insertGallery = $pdo->prepare("INSERT INTO gallery (title, image_url, category) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title = ?)");
    foreach ($galleryRows as $r) {
        $insertGallery->execute([$r[0], $r[1], $r[2], $r[0]]);
    }

    $newsRows = [
        ['School Honours Best Performing Students', 'The school has recognised outstanding students for their academic excellence and good conduct this term.', '', '', date('Y-m-d H:i:s')],
        ['Inter-House Sports Competition Held', 'Students participated actively in the annual inter-house sports competition showcasing talent and teamwork.', '', '', date('Y-m-d H:i:s')]
    ];
    $insertNews = $pdo->prepare("INSERT INTO news (title, content, source_url, image_url, published_at) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM news WHERE source_url = ?)");
    foreach ($newsRows as $r) {
        $insertNews->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[2]]);
    }

    $projectRows = [
        ['School Garden Project', 'Students learn about agriculture and environmental stewardship through the school garden initiative.', '', date('Y-m-d'), 'ongoing'],
        ['Reading Challenge Programme', 'A school-wide reading initiative to improve literacy and cultivate a love for reading.', '', date('Y-m-d'), 'ongoing'],
        ['STEM Club Launch', 'A new after-school club introducing students to science, technology, engineering, and mathematics.', '', date('Y-m-d'), 'planned']
    ];
    $insertProject = $pdo->prepare("INSERT INTO projects (title, description, image_url, project_date, status) SELECT ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM projects WHERE title = ?)");
    foreach ($projectRows as $r) {
        $insertProject->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[0]]);
    }

    $alumniRows = [
        ['Former Student – Class of 2024', '2024', 'Alumni Representative', 'Nex CEC Alumni Association', '', 'Proud to have started my educational journey at this great school.'],
        ['Former Student – Class of 2023', '2023', 'Alumni Member', 'Community Leader', '', 'The school laid a strong foundation for my secondary education.']
    ];
    $insertAlumni = $pdo->prepare("INSERT INTO alumni (full_name, graduation_year, position, company, image_url, testimonial) SELECT ?, ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM alumni WHERE full_name = ?)");
    foreach ($alumniRows as $r) {
        $insertAlumni->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[0]]);
    }

    $resourceRows = [
        ['School Calendar', 'View the academic calendar with term dates, holidays, and key events.', '', 'Calendar'],
        ['Uniform Guidelines', 'Download the school uniform policy and dress code requirements.', '', 'Guidelines'],
        ['Fee Schedule', 'Review the approved fee structure for the current academic year.', '', 'Fees'],
        ['PTA Information', 'Information about the Parent-Teacher Association and how to get involved.', '', 'Community']
    ];
    $insertResource = $pdo->prepare("INSERT INTO student_resources (title, description, file_url, resource_type) SELECT ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM student_resources WHERE title = ?)");
    foreach ($resourceRows as $r) {
        $insertResource->execute([$r[0], $r[1], $r[2], $r[3], $r[0]]);
    }

    $notificationRows = [
        ['Welcome to Nex CEC Portal', 'Welcome to the Nex CEC school portal. Check your dashboard regularly for updates and announcements.'],
        ['Password Security Reminder', 'Use your temporary password to login and reset it immediately to keep your account secure.'],
        ['Fee Payment Reminder', 'Please review your fee status and complete pending payments before the deadline.']
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
