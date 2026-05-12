<?php
// enroll.php — Student Enrollment Landing Page
// Load DB session handler FIRST, then start session
require_once __DIR__ . '/api/includes/functions.php';
require_once __DIR__ . '/api/includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

$school_name = $settings['school_name'] ?? 'Nex CEC Basic School';
$school_motto = $settings['school_motto'] ?? 'Education for Excellence';
$academic_year = date('Y') . '/' . (date('Y') + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .enroll-hero {
            background: linear-gradient(135deg, rgba(0,51,102,0.95), rgba(26,82,118,0.9));
            color: #fff;
            padding: 60px 20px 40px;
            text-align: center;
        }
        .enroll-hero h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .enroll-hero p { font-size: 1.1rem; opacity: 0.9; max-width: 600px; margin: 0 auto; }
        .enroll-hero .year-badge {
            display: inline-block;
            background: #ffcc00;
            color: #003366;
            padding: 6px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 15px;
        }
        .enroll-options {
            max-width: 800px;
            margin: -30px auto 40px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        .enroll-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            padding: 35px 30px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .enroll-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.18);
            border-color: #ffcc00;
        }
        .enroll-card .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }
        .enroll-card.download .icon-circle { background: #e3f2fd; color: #1a5276; }
        .enroll-card.online .icon-circle { background: #e8f5e9; color: #27ae60; }
        .enroll-card h3 { font-size: 1.3rem; color: #1a5276; margin-bottom: 10px; }
        .enroll-card p { color: #666; font-size: 0.95rem; line-height: 1.6; }
        .enroll-card .btn-enroll {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .enroll-card.download .btn-enroll { background: #1a5276; color: #fff; }
        .enroll-card.online .btn-enroll { background: #27ae60; color: #fff; }
        .enroll-steps {
            max-width: 800px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }
        .enroll-steps h3 { text-align: center; color: #1a5276; margin-bottom: 25px; font-size: 1.2rem; }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .step { text-align: center; padding: 15px; }
        .step .step-num {
            width: 40px; height: 40px;
            background: #1a5276; color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 10px;
            font-weight: 700;
        }
        .step p { font-size: 0.9rem; color: #555; }
        .back-link {
            display: inline-block;
            margin: 20px auto;
            text-align: center;
            color: #1a5276;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }

        /* Print form card styles */
        .print-card {
            display: none;
            max-width: 700px;
            margin: 20px auto;
            background: #fff;
            border: 2px solid #1a5276;
            padding: 30px;
        }
        @media print {
            body * { visibility: hidden; }
            .print-card, .print-card * { visibility: visible; }
            .print-card { position: absolute; top: 0; left: 0; width: 100%; border: 2px solid #000; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="images/school-logo.png" alt="Logo" style="width:40px;height:40px;border-radius:50%;" onerror="this.style.display='none'">
                <?php echo htmlspecialchars($school_name); ?>
            </a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="index.php#about">About</a>
                <a href="index.php#contact">Contact</a>
                <a href="login.php" class="btn-login">Login</a>
            </div>
            <button class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('active')">☰</button>
        </div>
    </nav>

    <!-- Enrollment Hero -->
    <section class="enroll-hero">
        <h1>Student Enrollment</h1>
        <p>Join <?php echo htmlspecialchars($school_name); ?> — <?php echo htmlspecialchars($school_motto); ?></p>
        <span class="year-badge">Academic Year: <?php echo $academic_year; ?></span>
    </section>

    <!-- Enrollment Options -->
    <div class="enroll-options">
        <!-- Option 1: Download/Print Form -->
        <div class="enroll-card download" onclick="showPrintCard()">
            <div class="icon-circle"><i class="fas fa-file-pdf"></i></div>
            <h3>Download / Print Form Card</h3>
            <p>Download a printable enrollment form. Fill it out manually and submit at the school office.</p>
            <span class="btn-enroll"><i class="fas fa-download"></i> Download Form</span>
        </div>

        <!-- Option 2: Fill Online -->
        <a href="enroll_form.php" class="enroll-card online" style="text-decoration:none; color:inherit;">
            <div class="icon-circle"><i class="fas fa-laptop"></i></div>
            <h3>Fill Enrollment Form Online</h3>
            <p>Complete the enrollment form online. You'll be directed to payment after submission.</p>
            <span class="btn-enroll"><i class="fas fa-arrow-right"></i> Start Online Enrollment</span>
        </a>
    </div>

    <!-- Steps -->
    <div class="enroll-steps">
        <h3>How It Works</h3>
        <div class="steps-grid">
            <div class="step">
                <div class="step-num">1</div>
                <p>Choose online or printable form</p>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <p>Fill in student & guardian details</p>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <p>Complete payment (MoMo / GhanaPay / Bank)</p>
            </div>
            <div class="step">
                <div class="step-num">4</div>
                <p>Receive confirmation & admission number</p>
            </div>
        </div>
    </div>

    <!-- Printable Form Card (hidden, shown for print) -->
    <div class="print-card" id="printCard">
        <div style="text-align:center; border-bottom:2px solid #1a5276; padding-bottom:15px; margin-bottom:20px;">
            <h2 style="color:#1a5276; margin:0;"><?php echo htmlspecialchars($school_name); ?></h2>
            <p style="margin:5px 0; color:#666;"><?php echo htmlspecialchars($school_motto); ?></p>
            <p style="font-size:0.85rem; color:#999;">Academic Year: <?php echo $academic_year; ?></p>
        </div>
        <h3 style="text-align:center; color:#1a5276; margin-bottom:20px;">Student Enrollment Form</h3>
        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
            <tr><td style="padding:8px; border:1px solid #ddd; width:180px;"><strong>Enrollment ID (if applying online):</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd; width:180px;"><strong>Student Full Name:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Date of Birth:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Gender:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Class Applying For:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Previous School:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Guardian Name:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Relationship:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Primary Phone:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Email:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Residence:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>Allergies/Conditions:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
            <tr><td style="padding:8px; border:1px solid #ddd;"><strong>NHIS Number:</strong></td><td style="padding:8px; border:1px solid #ddd; height:25px;"></td></tr>
        </table>
        <div style="margin-top:20px; display:flex; justify-content:space-between; font-size:0.85rem; color:#666;">
            <div>Signature: ___________________</div>
            <div>Date: ___________________</div>
        </div>
    </div>

    <div style="text-align:center; padding:0 20px 40px;">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>

    <script>
        function showPrintCard() {
            const card = document.getElementById('printCard');
            card.style.display = 'block';
            window.print();
            setTimeout(() => { card.style.display = 'none'; }, 1000);
        }
    </script>
</body>
</html>
