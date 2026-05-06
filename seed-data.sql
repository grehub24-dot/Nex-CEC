-- ==========================================
-- SEED DATA: INFOTESS SDMS
-- Run this in Supabase SQL Editor
-- ==========================================

-- 1. Ensure extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 2. Create 5 test students
INSERT INTO students (user_id, index_number, full_name, department, level, stream, phone_number)
VALUES
    (NULL, 'UST/2024/0001', 'Kwame Asante', 'IT Education', 'Level 200', 'A', '0241234567'),
    (NULL, 'UST/2024/0002', 'Ama Mensah', 'IT Education', 'Level 200', 'A', '0241234568'),
    (NULL, 'UST/2024/0003', 'Kofi Boateng', 'IT Education', 'Level 100', 'B', '0241234569'),
    (NULL, 'UST/2024/0004', 'Abena Osei', 'IT Education', 'Level 300', 'A', '0241234570'),
    (NULL, 'UST/2024/0005', 'Yaw Frimpong', 'IT Education', 'Level 100', 'B', '0241234571');

-- 3. Create fee structures for 2025/2026
INSERT INTO fee_structures (school_id, class_id, title, amount, academic_year, term, is_mandatory)
VALUES
    (NULL, NULL, 'Annual Dues', 100.00, '2025/2026', 'All', true),
    (NULL, NULL, 'Registration Fee', 50.00, '2025/2026', 'All', true),
    (NULL, NULL, 'Exam Fee', 75.00, '2025/2026', 'Semester 1', true),
    (NULL, NULL, 'Lab Fee', 30.00, '2025/2026', 'Semester 2', false);

-- 4. Record test payments (for 3 out of 5 students = partial compliance)
INSERT INTO payments (student_id, fee_structure_id, amount, academic_year, semester, payment_method, payment_date, receipt_number, recorded_by)
VALUES
    (1, NULL, 100.00, '2025/2026', 'All', 'Cash', '2026-05-01', 'RCP-2026-001', 2),
    (1, NULL, 50.00,  '2025/2026', 'All', 'Mobile Money', '2026-05-01', 'RCP-2026-002', 2),
    (2, NULL, 100.00, '2025/2026', 'All', 'Cash', '2026-05-02', 'RCP-2026-003', 2),
    (4, NULL, 75.00,  '2025/2026', 'Semester 1', 'Mobile Money', '2026-05-03', 'RCP-2026-004', 2),
    (4, NULL, 30.00,  '2025/2026', 'Semester 2', 'Cash', '2026-05-03', 'RCP-2026-005', 2);

-- 5. Add a Bursar user (for recording payments)
INSERT INTO users (email, password, role, status, is_password_reset)
VALUES ('bursar@infotess.org', '$2y$10$YRNoSKY.hpBVI8PGmwOMNOZAmYXoAIzsnr0Py0vqoHiERUihByEkq', 'bursar', 'active', true);

-- ==========================================
-- VERIFICATION QUERIES
-- ==========================================

-- Should show 5 students
SELECT COUNT(*) AS total_students FROM students;

-- Should show 5 payments totaling GHS 355.00
SELECT COUNT(*) AS total_payments, SUM(amount) AS total_revenue FROM payments;

-- Should show 2 compliant students (student 1 and 2 paid annual dues >= 100)
SELECT COUNT(DISTINCT p.student_id) AS compliant_students
FROM payments p
GROUP BY p.student_id
HAVING SUM(p.amount) >= 100;

-- Should show 1 payment today (if today is 2026-05-06, shows 0)
SELECT COUNT(*) AS payments_today FROM payments WHERE payment_date = CURRENT_DATE;
