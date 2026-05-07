<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../enroll_form.php');
    exit;
}

// Sanitize all POST inputs
$sanitized = [];
foreach ($_POST as $key => $value) {
    $sanitized[$key] = sanitize($value);
}

// Required fields validation
$required = ['full_name', 'gender', 'date_of_birth', 'class_name', 'guardian_name', 'guardian_relationship', 'guardian_phone_primary', 'guardian_email', 'address'];
$errors = [];

foreach ($required as $field) {
    if (empty($sanitized[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
    }
}

// Validate guardian email format
if (!empty($sanitized['guardian_email']) && !filter_var($sanitized['guardian_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid guardian email address';
}

// Validate date of birth
if (!empty($sanitized['date_of_birth']) && !strtotime($sanitized['date_of_birth'])) {
    $errors[] = 'Invalid date of birth format';
}

if (!empty($errors)) {
    $errorMsg = urlencode(implode(' | ', $errors));
    header('Location: ../enroll_form.php?error=' . $errorMsg);
    exit;
}

// Extract sanitized values
$full_name = $sanitized['full_name'];
$gender = $sanitized['gender'];
$date_of_birth = $sanitized['date_of_birth'];
$class_name = $sanitized['class_name'];
$place_of_birth = $sanitized['place_of_birth'] ?? null;
$nationality = $sanitized['nationality'] ?? null;
$previous_school = $sanitized['previous_school'] ?? null;
$previous_class = $sanitized['previous_class'] ?? null;
$health_insurance_id = $sanitized['health_insurance_id'] ?? null;
$allergies = $sanitized['allergies'] ?? null;
$guardian_name = $sanitized['guardian_name'];
$guardian_relationship = $sanitized['guardian_relationship'];
$guardian_phone_primary = $sanitized['guardian_phone_primary'];
$guardian_phone_emergency = $sanitized['guardian_phone_emergency'] ?? null;
$guardian_email = $sanitized['guardian_email'];
$guardian_occupation = $sanitized['guardian_occupation'] ?? null;
$address = $sanitized['address'];

try {
    $pdo->beginTransaction();

    // Generate admission number: CEC-YYMMDD-XXX
    $today = date('ymd');
    $prefix = "CEC-{$today}-%";
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE index_number LIKE :prefix');
    $stmt->execute([':prefix' => $prefix]);
    $todayCount = (int) $stmt->fetchColumn();
    $counter = str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);
    $admissionNumber = "CEC-{$today}-{$counter}";

    // Generate 6-character random password
    $plainPassword = bin2hex(random_bytes(3));
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    // Create user account
    $stmt = $pdo->prepare('INSERT INTO users (email, password, role) VALUES (:email, :password, :role)');
    $stmt->execute([
        ':email' => $guardian_email,
        ':password' => $hashedPassword,
        ':role' => 'student'
    ]);
    $userId = $pdo->lastInsertId();

    // Calculate academic year (e.g. 2026/2027)
    $currentYear = date('Y');
    $academicYear = "{$currentYear}/" . ($currentYear + 1);

    // Create student record
    $stmt = $pdo->prepare('INSERT INTO students (
        user_id, index_number, full_name, class_name, gender, date_of_birth, place_of_birth, nationality, address,
        guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency,
        guardian_occupation, health_insurance_id, allergies, previous_school, previous_class,
        admission_date, academic_year, status, enrollment_type
    ) VALUES (
        :user_id, :index_number, :full_name, :class_name, :gender, :date_of_birth, :place_of_birth, :nationality, :address,
        :guardian_name, :guardian_email, :guardian_relationship, :guardian_phone_primary, :guardian_phone_emergency,
        :guardian_occupation, :health_insurance_id, :allergies, :previous_school, :previous_class,
        :admission_date, :academic_year, :status, :enrollment_type
    )');
    $stmt->execute([
        ':user_id' => $userId,
        ':index_number' => $admissionNumber,
        ':full_name' => $full_name,
        ':class_name' => $class_name,
        ':gender' => $gender,
        ':date_of_birth' => $date_of_birth,
        ':place_of_birth' => $place_of_birth ?: null,
        ':nationality' => $nationality ?: null,
        ':address' => $address,
        ':guardian_name' => $guardian_name,
        ':guardian_email' => $guardian_email,
        ':guardian_relationship' => $guardian_relationship,
        ':guardian_phone_primary' => $guardian_phone_primary,
        ':guardian_phone_emergency' => $guardian_phone_emergency ?: null,
        ':guardian_occupation' => $guardian_occupation ?: null,
        ':health_insurance_id' => $health_insurance_id ?: null,
        ':allergies' => $allergies ?: null,
        ':previous_school' => $previous_school ?: null,
        ':previous_class' => $previous_class ?: null,
        ':admission_date' => date('Y-m-d'),
        ':academic_year' => $academicYear,
        ':status' => 'pending',
        ':enrollment_type' => 'self'
    ]);
    $studentId = $pdo->lastInsertId();

    // Store enrollment session data for payment page
    $_SESSION['enrollment'] = [
        'student_id' => $studentId,
        'admission_number' => $admissionNumber,
        'full_name' => $full_name,
        'class_name' => $class_name,
        'guardian_email' => $guardian_email,
        'guardian_phone' => $guardian_phone_primary,
        'amount' => 150.00,
        'academic_year' => $academicYear
    ];

    $pdo->commit();

    // Send confirmation email (non-blocking, errors ignored)
    $emailSubject = "Enrollment Confirmation - {$admissionNumber}";
    $emailBody = "Dear {$guardian_name},\n\n" .
                 "Thank you for submitting the enrollment form.\n" .
                 "Admission Number: {$admissionNumber}\n" .
                 "Student Portal Password: {$plainPassword}\n" .
                 "Please proceed to payment to complete enrollment.\n\n" .
                 "Regards,\nSchool Administration";
    @mail($guardian_email, $emailSubject, $emailBody);

    // Redirect to payment page
    header('Location: ../payment.php?enrollment=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $errorMsg = urlencode('Enrollment failed. Please try again or contact support.');
    header('Location: ../enroll_form.php?error=' . $errorMsg);
    exit;
}
