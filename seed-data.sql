-- ==========================================
-- COMPLETE CORRELATED SEED DATA: Nex CEC Basic School
-- Run AFTER migrate-all.sql has been executed
-- Idempotent: safe to run multiple times
-- ==========================================

-- ==========================================
-- 1. SYSTEM SETTINGS
-- ==========================================
INSERT INTO system_settings (setting_key, setting_value) VALUES
('school_name', 'Nex CEC Basic School'),
('school_motto', 'Education for Excellence and Character'),
('school_address', '12 Education Ridge, Kumasi, Ghana'),
('school_phone', '+233 32 277 0000'),
('school_email', 'info@necxec.edu.gh'),
('current_term', '1'),
('next_term_begins', '2026-01-06'),
('school_logo', 'images/school-logo.png')
ON CONFLICT ON CONSTRAINT system_settings_setting_key_key DO NOTHING;

-- ==========================================
-- 2. CLASSES (from migrate-all, ensure they exist)
-- ==========================================
INSERT INTO classes (name, level_group, sort_order) VALUES
('Creche', 'early_childhood', 0),
('Nursery', 'early_childhood', 1),
('KG 1', 'early_childhood', 2),
('KG 2', 'early_childhood', 3),
('Basic 1', 'primary', 4),
('Basic 2', 'primary', 5),
('Basic 3', 'primary', 6),
('Basic 4', 'primary', 7),
('Basic 5', 'primary', 8),
('Basic 6', 'primary', 9),
('JHS 1', 'jhs', 10),
('JHS 2', 'jhs', 11),
('JHS 3', 'jhs', 12)
ON CONFLICT ON CONSTRAINT classes_name_key DO NOTHING;

-- ==========================================
-- 3. TERMS
-- ==========================================
INSERT INTO terms (name, academic_year, start_date, end_date, is_active) VALUES
('Term 1', '2025/2026', '2025-09-08', '2025-12-19', true),
('Term 2', '2025/2026', '2026-01-06', '2026-04-10', false),
('Term 3', '2025/2026', '2026-04-27', '2026-07-31', false)
ON CONFLICT ON CONSTRAINT terms_academic_year_name_key DO NOTHING;

-- ==========================================
-- 4. SUBJECTS
-- ==========================================
INSERT INTO subjects (name, code, class_id) VALUES
('English Language', 'ENG', NULL),
('Mathematics', 'MATH', NULL),
('Integrated Science', 'SCI', NULL),
('Social Studies', 'SST', NULL),
('French', 'FRE', NULL),
('Creative Arts & Design', 'CAD', NULL),
('Ghanaian Language (Twi)', 'GL', NULL),
('Computing', 'COMP', NULL),
('Physical Education', 'PE', NULL),
('Religious & Moral Education', 'RME', NULL),
('Career Technology', 'CT', NULL)
ON CONFLICT ON CONSTRAINT subjects_name_class_id_key DO NOTHING;

-- ==========================================
-- 5. USERS — Admin & Bursar
-- ==========================================
-- Password for all seeded users: NexCEC2026!
-- hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (email, password, role, status, is_password_reset) VALUES
('admin@necxec.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active', true),
('deputy@necxec.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', true),
('bursar@necxec.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'bursar', 'active', true)
ON CONFLICT (email) DO NOTHING;

-- ==========================================
-- 6. STAFF — 15 members
-- ==========================================
INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
VALUES
-- Headmaster
((SELECT id FROM users WHERE email='admin@necxec.edu.gh'), 'NXC-STF-001', 'Mr. Kwabena Asante', 'Headmaster', 'Administration', 'M.Ed Administration', '0244123456', 'kwabena.asante@necxec.edu.gh', '1975-03-15', 'Male', '15 Bantama High St, Kumasi', '2018-09-01', 'active', 'GCB Bank', '1234567890'),
-- Assistant Head
((SELECT id FROM users WHERE email='deputy@necxec.edu.gh'), 'NXC-STF-002', 'Mrs. Ama Serwaa Mensah', 'Assistant Head', 'Administration', 'M.Ed Curriculum', '0245234567', 'ama.mensah@necxec.edu.gh', '1980-07-22', 'Female', '22 Ayeduase Rd, Kumasi', '2019-01-15', 'active', 'Ecobank', '2345678901'),
-- Class Teachers (13, one per class)
(NULL, 'NXC-STF-003', 'Ms. Efua Darko', 'Class Teacher', 'Early Childhood', 'Diploma in Early Childhood Education', '0246345678', 'efua.darko@necxec.edu.gh', '1990-11-05', 'Female', '8 Suame Magazine Rd, Kumasi', '2020-09-01', 'active', 'GCB Bank', '3456789012'),
(NULL, 'NXC-STF-004', 'Mr. Kofi Owusu', 'Class Teacher', 'Early Childhood', 'Certificate in Early Childhood', '0247456789', 'kofi.owusu@necxec.edu.gh', '1992-04-18', 'Male', '12 Tech Junction, Kumasi', '2021-01-10', 'active', 'Fidelity Bank', '4567890123'),
(NULL, 'NXC-STF-005', 'Mrs. Abena Gyamfi', 'Class Teacher', 'Early Childhood', 'Diploma in Basic Education', '0248567890', 'abena.gyamfi@necxec.edu.gh', '1988-09-30', 'Female', '5 Roman Hill, Kumasi', '2019-09-01', 'active', 'ADB Bank', '5678901234'),
(NULL, 'NXC-STF-006', 'Mr. Yaw Boateng', 'Class Teacher', 'Early Childhood', 'Diploma in Basic Education', '0249678901', 'yaw.boateng@necxec.edu.gh', '1985-02-14', 'Male', '20 Oforikrom, Kumasi', '2018-09-01', 'active', 'GCB Bank', '6789012345'),
(NULL, 'NXC-STF-007', 'Ms. Akosua Frimpong', 'Class Teacher', 'Primary', 'B.Ed Basic Education', '0240789012', 'akosua.frimpong@necxec.edu.gh', '1991-06-25', 'Female', '3 Asokwa Estate, Kumasi', '2020-09-01', 'active', 'CalBank', '7890123456'),
(NULL, 'NXC-STF-008', 'Mr. Emmanuel Tetteh', 'Class Teacher', 'Primary', 'B.Ed Basic Education', '0241890123', 'emmanuel.tetteh@necxec.edu.gh', '1987-12-08', 'Male', '17 Ahodwo Circle, Kumasi', '2019-09-01', 'active', 'Ecobank', '8901234567'),
(NULL, 'Mrs. Grace Adjei', 'Class Teacher', 'Primary', 'Diploma in Basic Education', '0242901234', 'grace.adjei@necxec.edu.gh', '1983-01-20', 'Female', '9 Kentinkrono, Kumasi', '2017-09-01', 'active', 'GCB Bank', '9012345678'),
(NULL, 'NXC-STF-010', 'Mr. Daniel Kwarteng', 'Class Teacher', 'Primary', 'B.Ed Mathematics', '0243012345', 'daniel.kwarteng@necxec.edu.gh', '1986-08-11', 'Male', '14 Patasi, Kumasi', '2018-09-01', 'active', 'Fidelity Bank', '0123456789'),
(NULL, 'NXC-STF-011', 'Ms. Victoria Appiah', 'Class Teacher', 'Primary', 'B.Ed English', '0244123457', 'victoria.appiah@necxec.edu.gh', '1993-03-28', 'Female', '6 Danyame, Kumasi', '2021-09-01', 'active', 'ADB Bank', '1234509876'),
(NULL, 'NXC-STF-012', 'Mr. Samuel Osei', 'Class Teacher', 'Primary', 'B.Ed Science', '0245234568', 'samuel.osei@necxec.edu.gh', '1984-10-03', 'Male', '21 Bompata, Kumasi', '2017-09-01', 'active', 'GCB Bank', '2345610987'),
(NULL, 'NXC-STF-013', 'Mrs. Nana Yeboah', 'Class Teacher', 'JHS', 'B.Ed Mathematics', '0246345679', 'nana.yeboah@necxec.edu.gh', '1982-05-17', 'Female', '11 Amakom, Kumasi', '2016-09-01', 'active', 'CalBank', '3456721098'),
(NULL, 'NXC-STF-014', 'Mr. Isaac Mensah', 'Class Teacher', 'JHS', 'B.Ed English', '0247456780', 'isaac.mensah@necxec.edu.gh', '1989-07-09', 'Male', '7 Asafo, Kumasi', '2019-09-01', 'active', 'Ecobank', '4567832109'),
(NULL, 'NXC-STF-015', 'Mr. Frank Agyemang', 'Class Teacher', 'JHS', 'B.Ed Integrated Science', '0248567891', 'frank.agyemang@necxec.edu.gh', '1990-01-25', 'Male', '18 Tafo, Kumasi', '2020-09-01', 'active', 'Fidelity Bank', '5678943210'),
-- Subject Specialists
(NULL, 'NXC-STF-016', 'Mr. Bernard Anane', 'ICT Teacher', 'ICT', 'BSc Computer Science', '0249678902', 'bernard.anane@necxec.edu.gh', '1994-06-12', 'Male', '4 Kwame Nkrumah St, Kumasi', '2022-01-10', 'active', 'GCB Bank', '6789054321'),
(NULL, 'NXC-STF-017', 'Mrs. Beatrice Nkrumah', 'French Teacher', 'Languages', 'BA French', '0240789013', 'beatrice.nkrumah@necxec.edu.gh', '1991-09-04', 'Female', '13 Ejisu Rd, Kumasi', '2021-09-01', 'active', 'ADB Bank', '7890165432'),
-- Support Staff
(NULL, 'NXC-STF-018', 'Mr. Joseph Tawiah', 'Security Officer', 'Operations', 'SHS Certificate', '0241890124', 'joseph.tawiah@necxec.edu.gh', '1978-04-30', 'Male', '25 Suame, Kumasi', '2015-03-01', 'active', NULL, NULL),
(NULL, 'NXC-STF-019', 'Mrs. Comfort Agyei', 'Cleaner', 'Operations', 'JHS Certificate', '0242901235', 'comfort.agyei@necxec.edu.gh', '1985-11-19', 'Female', '10 Manhyia, Kumasi', '2016-06-01', 'active', NULL, NULL)
ON CONFLICT (staff_id) DO NOTHING;

-- ==========================================
-- 7. STAFF USER ACCOUNTS
-- ==========================================
-- Create user accounts for staff who don't have one yet
DO $$
DECLARE
    s RECORD;
    uid INTEGER;
BEGIN
    FOR s IN SELECT id, email FROM staff WHERE email IS NOT NULL AND email != '' AND user_id IS NULL LOOP
        INSERT INTO users (email, password, role, status, is_password_reset)
        VALUES (s.email, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active', false)
        ON CONFLICT (email) DO NOTHING;
        
        IF FOUND OR EXISTS (SELECT 1 FROM users WHERE email = s.email) THEN
            uid := (SELECT id FROM users WHERE email = s.email);
            UPDATE staff SET user_id = uid WHERE id = s.id;
        END IF;
    END LOOP;
END $$;

-- ==========================================
-- 8. SALARY STRUCTURES
-- ==========================================
DO $$
DECLARE
    s RECORD;
    base NUMERIC;
BEGIN
    FOR s IN SELECT id, position FROM staff WHERE status = 'active' LOOP
        -- Set salary based on position
        IF s.position IN ('Headmaster', 'Assistant Head') THEN
            base := 4200;
        ELSIF s.position LIKE '%Teacher%' OR s.position LIKE '%Class Teacher%' THEN
            base := 2800;
        ELSIF s.position IN ('ICT Teacher', 'French Teacher') THEN
            base := 3000;
        ELSIF s.position IN ('Security Officer', 'Cleaner') THEN
            base := 1500;
        ELSE
            base := 2500;
        END IF;
        
        INSERT INTO salary_structures (staff_id, basic_salary, housing_allowance, transport_allowance, other_allowances, ssnit_rate, tax_rate)
        VALUES (s.id, base, base * 0.15, base * 0.10, base * 0.05, 13.50, 0)
        ON CONFLICT DO NOTHING;
    END LOOP;
END $$;

-- ==========================================
-- 9. PAYROLL RECORDS (November 2025)
-- ==========================================
DO $$
DECLARE
    s RECORD;
    ss RECORD;
    gross NUMERIC;
    ssnit NUMERIC;
    net NUMERIC;
BEGIN
    FOR s IN SELECT id FROM staff WHERE status = 'active' LOOP
        SELECT basic_salary, housing_allowance, transport_allowance, other_allowances, ssnit_rate, tax_rate
        INTO ss FROM salary_structures WHERE staff_id = s.id;
        
        IF ss IS NOT NULL THEN
            gross := ss.basic_salary + ss.housing_allowance + ss.transport_allowance + ss.other_allowances;
            ssnit := ss.basic_salary * (ss.ssnit_rate / 100);
            net := gross - ssnit - (ss.basic_salary * ss.tax_rate / 100);
            
            INSERT INTO payroll (staff_id, month, year, basic_salary, total_allowances, gross_pay, ssnit_deduction, tax_deduction, other_deductions, total_deductions, net_pay, status, pay_date)
            VALUES (s.id, 11, 2025, ss.basic_salary, ss.housing_allowance + ss.transport_allowance + ss.other_allowances, gross, ssnit, ss.basic_salary * ss.tax_rate / 100, 0, ssnit + ss.basic_salary * ss.tax_rate / 100, net, 'paid', '2025-11-28')
            ON CONFLICT DO NOTHING;
        END IF;
    END LOOP;
END $$;

-- ==========================================
-- 10. STUDENTS — 39 students (3 per class)
-- ==========================================
-- Guardian emails for student portal (password: NexCEC2026!)
-- Students with payment_status='paid' have index_number; others are NULL
INSERT INTO users (email, password, role, status, is_password_reset) VALUES
('guardian.kwame@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.abena@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.kofi@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.ama@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.yaw@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.efua@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.kweku@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.akua@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.osei@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.adwoa@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.kwabena2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.nana@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.akosua2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.daniel2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.grace2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.samuel2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.victoria2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.isaac2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.nana2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.frank2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.emmanuel2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.bernice@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.richard@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.prince@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.bridget@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.felix@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.stella@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.alfred@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.mercy@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.joseph2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.ruth@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.peter@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.hannah@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.charles@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.deborah@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.eric@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.joyce@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.david2@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true),
('guardian.linda@parent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true)
ON CONFLICT (email) DO NOTHING;

-- Now insert students with full correlated data
-- Using DO block to get user IDs dynamically
DO $$
DECLARE
    uid INTEGER;
BEGIN

-- Creche (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.kwame@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Kwame Junior Asante', 'ENR-2025-A1B2C3', 'CEC-250908-001', 'Creche', 'Male', '2022-05-10', 'Kumasi', 'Ghanaian', '15 Bantama', 'Mr. Kwame Asante Sr.', 'guardian.kwame@parent.com', 'Father', '0244111222', '0244111333', 'Trader', '15 Bantama High St', 'NHIS-1234567', '', 'None', '', '', '', '2025-09-08', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.abena@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Abena Grace Mensah', 'ENR-2025-D4E5F6', 'CEC-250908-002', 'Creche', 'Female', '2022-08-15', 'Kumasi', 'Ghanaian', '22 Ayeduase', 'Mrs. Abena Mensah', 'guardian.abena@parent.com', 'Mother', '0245222333', '0245222444', 'Nurse', '22 Ayeduase Rd', 'NHIS-2345678', '', 'None', '', '', '', '2025-09-08', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.kofi@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Kofi Owusu Jnr.', 'ENR-2025-G7H8I9', NULL, 'Creche', 'Male', '2022-11-20', 'Kumasi', 'Ghanaian', '8 Suame', 'Mr. Kofi Owusu Sr.', 'guardian.kofi@parent.com', 'Father', '0246333444', '0246333555', 'Mechanic', '8 Suame Magazine Rd', 'NHIS-3456789', '', 'Peanuts', '', '', '', '2025-09-08', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Nursery (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.ama@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Ama Serwaa Darko', 'ENR-2025-J1K2L3', 'CEC-250908-004', 'Nursery', 'Female', '2021-03-12', 'Kumasi', 'Ghanaian', '5 Roman Hill', 'Mrs. Ama Darko', 'guardian.ama@parent.com', 'Mother', '0247444555', '0247444666', 'Teacher', '5 Roman Hill', 'NHIS-4567890', 'Mild asthma', 'None', '', '', '', '2025-09-08', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.yaw@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Yaw Boateng Jnr.', 'ENR-2025-M4N5O6', 'CEC-250908-005', 'Nursery', 'Male', '2021-06-18', 'Kumasi', 'Ghanaian', '20 Oforikrom', 'Mr. Yaw Boateng Sr.', 'guardian.yaw@parent.com', 'Father', '0248555666', '0248555777', 'Farmer', '20 Oforikrom', 'NHIS-5678901', '', 'None', '', '', '', '2025-09-08', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.efua@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Efua Adjei', 'ENR-2025-P7Q8R9', NULL, 'Nursery', 'Female', '2021-09-25', 'Kumasi', 'Ghanaian', '3 Asokwa', 'Mrs. Efua Adjei', 'guardian.efua@parent.com', 'Mother', '0249666777', '0249666888', 'Hairdresser', '3 Asokwa Estate', '', '', 'None', '', '', '', '2025-09-08', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- KG 1 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.kweku@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Kweku Tetteh', 'ENR-2025-S1T2U3', 'CEC-250908-007', 'KG 1', 'Male', '2020-01-14', 'Kumasi', 'Ghanaian', '17 Ahodwo', 'Mr. Kweku Tetteh', 'guardian.kweku@parent.com', 'Father', '0240777888', '0240777999', 'Banker', '17 Ahodwo Circle', 'NHIS-6789012', '', 'None', '', 'Happy Kids KG', 'Nursery', '2025-09-08', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.akua@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Akua Frimpong', 'ENR-2025-V4W5X6', 'CEC-250908-008', 'KG 1', 'Female', '2020-04-22', 'Kumasi', 'Ghanaian', '9 Kentinkrono', 'Mrs. Akua Frimpong', 'guardian.akua@parent.com', 'Mother', '0241888999', '0241888000', 'Accountant', '9 Kentinkrono', 'NHIS-7890123', '', 'None', '', 'Happy Kids KG', 'Nursery', '2025-09-08', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.osei@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Osei Kwarteng', 'ENR-2025-Y7Z8A0', NULL, 'KG 1', 'Male', '2020-07-30', 'Kumasi', 'Ghanaian', '14 Patasi', 'Mr. Osei Kwarteng', 'guardian.osei@parent.com', 'Father', '0242999000', '0242999111', 'Driver', '14 Patasi', '', '', 'None', '', 'Sunshine Academy', 'Nursery', '2025-09-08', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- KG 2 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.adwoa@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Adwoa Mensah', 'ENR-2025-B1C2D3', 'CEC-250908-010', 'KG 2', 'Female', '2019-02-14', 'Kumasi', 'Ghanaian', '6 Danyame', 'Mrs. Adwoa Mensah', 'guardian.adwoa@parent.com', 'Mother', '0243000111', '0243000222', 'Pharmacist', '6 Danyame', 'NHIS-8901234', '', 'Dust allergy', '', 'Bright Stars KG', 'KG 1', '2024-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.kwabena2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Kwabena Osei II', 'ENR-2025-E4F5G6', 'CEC-250908-011', 'KG 2', 'Male', '2019-05-20', 'Kumasi', 'Ghanaian', '21 Bompata', 'Mr. Kwabena Osei', 'guardian.kwabena2@parent.com', 'Father', '0244111222', '0244111333', 'Engineer', '21 Bompata', 'NHIS-9012345', '', 'None', '', 'Bright Stars KG', 'KG 1', '2024-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.nana@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Nana Ama Frimpong', 'ENR-2025-H7I8J9', NULL, 'KG 2', 'Female', '2019-08-11', 'Kumasi', 'Ghanaian', '11 Amakom', 'Mrs. Nana Frimpong', 'guardian.nana@parent.com', 'Mother', '0245222333', '0245222444', 'Civil Servant', '11 Amakom', '', '', 'None', '', 'Little Angels KG', 'KG 1', '2024-09-02', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Basic 1 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.akosua2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Akosua Yeboah', 'ENR-2025-K1L2M3', 'CEC-250908-013', 'Basic 1', 'Female', '2018-03-15', 'Kumasi', 'Ghanaian', '7 Asafo', 'Mrs. Akosua Yeboah', 'guardian.akosua2@parent.com', 'Mother', '0246333444', '0246333555', 'Business Owner', '7 Asafo', 'NHIS-0123456', '', 'None', '', 'Nex CEC Basic School', 'KG 2', '2024-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.daniel2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Daniel Kofi Asante', 'ENR-2025-N4O5P6', 'CEC-250908-014', 'Basic 1', 'Male', '2018-06-22', 'Kumasi', 'Ghanaian', '18 Tafo', 'Mr. Daniel Asante', 'guardian.daniel2@parent.com', 'Father', '0247444555', '0247444666', 'Pastor', '18 Tafo', 'NHIS-1234560', '', 'None', '', 'Nex CEC Basic School', 'KG 2', '2024-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.grace2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Grace Adwoa Boateng', 'ENR-2025-Q7R8S9', NULL, 'Basic 1', 'Female', '2018-09-30', 'Kumasi', 'Ghanaian', '25 Suame', 'Mrs. Grace Boateng', 'guardian.grace2@parent.com', 'Mother', '0248555666', '0248555777', 'Market Woman', '25 Suame', '', '', 'None', '', 'Nex CEC Basic School', 'KG 2', '2024-09-02', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Basic 2 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.samuel2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Samuel Owusu Tetteh', 'ENR-2025-T1U2V3', 'CEC-250908-016', 'Basic 2', 'Male', '2017-01-10', 'Kumasi', 'Ghanaian', '10 Manhyia', 'Mr. Samuel Tetteh', 'guardian.samuel2@parent.com', 'Father', '0249666777', '0249666888', 'Teacher', '10 Manhyia', 'NHIS-2345670', '', 'None', '', 'Nex CEC Basic School', 'Basic 1', '2023-09-04', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.victoria2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Victoria Ama Gyamfi', 'ENR-2025-W4X5Y6', 'CEC-250908-017', 'Basic 2', 'Female', '2017-04-18', 'Kumasi', 'Ghanaian', '4 Kwame Nkrumah St', 'Mrs. Victoria Gyamfi', 'guardian.victoria2@parent.com', 'Mother', '0240777888', '0240777999', 'Fashion Designer', '4 Kwame Nkrumah St', 'NHIS-3456780', 'Sickle cell trait', 'None', '', 'Nex CEC Basic School', 'Basic 1', '2023-09-04', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.isaac2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Isaac Mensah Jnr.', 'ENR-2025-Z7A8B9', NULL, 'Basic 2', 'Male', '2017-07-25', 'Kumasi', 'Ghanaian', '13 Ejisu Rd', 'Mr. Isaac Mensah Sr.', 'guardian.isaac2@parent.com', 'Father', '0241888999', '0241888000', 'Electrician', '13 Ejisu Rd', '', '', 'None', '', 'Nex CEC Basic School', 'Basic 1', '2023-09-04', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Basic 3 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.nana2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Nana Kwame Frimpong', 'ENR-2025-C1D2E3', 'CEC-250908-019', 'Basic 3', 'Male', '2016-02-14', 'Kumasi', 'Ghanaian', '16 Bantama', 'Mr. Nana Frimpong', 'guardian.nana2@parent.com', 'Father', '0242999000', '0242999111', 'Police Officer', '16 Bantama', 'NHIS-4567890', '', 'None', '', 'Nex CEC Basic School', 'Basic 2', '2022-09-05', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.frank2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Frank Agyemang Asante', 'ENR-2025-F4G5H6', 'CEC-250908-020', 'Basic 3', 'Male', '2016-05-22', 'Kumasi', 'Ghanaian', '19 Ayeduase', 'Mr. Frank Asante', 'guardian.frank2@parent.com', 'Father', '0243000111', '0243000222', 'Soldier', '19 Ayeduase', 'NHIS-5678900', '', 'None', '', 'Nex CEC Basic School', 'Basic 2', '2022-09-05', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.emmanuel2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Emmanuel Tetteh II', 'ENR-2025-I7J8K9', NULL, 'Basic 3', 'Male', '2016-08-30', 'Kumasi', 'Ghanaian', '23 Suame', 'Mr. Emmanuel Tetteh', 'guardian.emmanuel2@parent.com', 'Father', '0244111222', '0244111333', 'Carpenter', '23 Suame', '', '', 'None', '', 'Nex CEC Basic School', 'Basic 2', '2022-09-05', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Basic 4 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.bernice@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Bernice Adjei Mensah', 'ENR-2025-L1M2N3', 'CEC-250908-022', 'Basic 4', 'Female', '2015-01-14', 'Kumasi', 'Ghanaian', '12 Tech Junction', 'Mrs. Bernice Mensah', 'guardian.bernice@parent.com', 'Mother', '0245222333', '0245222444', 'Doctor', '12 Tech Junction', 'NHIS-6789010', '', 'Penicillin', '', 'Nex CEC Basic School', 'Basic 3', '2021-09-06', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.richard@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Richard Osei Boateng', 'ENR-2025-O4P5Q6', 'CEC-250908-023', 'Basic 4', 'Male', '2015-04-18', 'Kumasi', 'Ghanaian', '28 Oforikrom', 'Mr. Richard Boateng', 'guardian.richard@parent.com', 'Father', '0246333444', '0246333555', 'Lawyer', '28 Oforikrom', 'NHIS-7890120', '', 'None', '', 'Nex CEC Basic School', 'Basic 3', '2021-09-06', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.prince@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Prince Kofi Asante', 'ENR-2025-R7S8T9', NULL, 'Basic 4', 'Male', '2015-07-25', 'Kumasi', 'Ghanaian', '30 Roman Hill', 'Mr. Prince Asante', 'guardian.prince@parent.com', 'Father', '0247444555', '0247444666', 'Plumber', '30 Roman Hill', '', '', 'None', '', 'Nex CEC Basic School', 'Basic 3', '2021-09-06', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Basic 5 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.bridget@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Bridget Ama Darko', 'ENR-2025-U1V2W3', 'CEC-250908-025', 'Basic 5', 'Female', '2014-02-14', 'Kumasi', 'Ghanaian', '33 Ahodwo', 'Mrs. Bridget Darko', 'guardian.bridget@parent.com', 'Mother', '0248555666', '0248555777', 'Journalist', '33 Ahodwo', 'NHIS-8901230', '', 'None', '', 'Nex CEC Basic School', 'Basic 4', '2020-09-07', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.felix@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Felix Mensah Owusu', 'ENR-2025-X4Y5Z6', 'CEC-250908-026', 'Basic 5', 'Male', '2014-05-22', 'Kumasi', 'Ghanaian', '36 Kentinkrono', 'Mr. Felix Owusu', 'guardian.felix@parent.com', 'Father', '0249666777', '0249666888', 'Architect', '36 Kentinkrono', 'NHIS-9012340', '', 'None', '', 'Nex CEC Basic School', 'Basic 4', '2020-09-07', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.stella@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Stella Adwoa Frimpong', 'ENR-2025-A0B1C2', NULL, 'Basic 5', 'Female', '2014-08-30', 'Kumasi', 'Ghanaian', '39 Patasi', 'Mrs. Stella Frimpong', 'guardian.stella@parent.com', 'Mother', '0240777888', '0240777999', 'Pharmacist', '39 Patasi', '', '', 'None', '', 'Nex CEC Basic School', 'Basic 4', '2020-09-07', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- Basic 6 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.alfred@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Alfred Kwarteng Asante', 'ENR-2025-D3E4F5', 'CEC-250908-028', 'Basic 6', 'Male', '2013-01-14', 'Kumasi', 'Ghanaian', '42 Bompata', 'Mr. Alfred Asante', 'guardian.alfred@parent.com', 'Father', '0241888999', '0241888000', 'Accountant', '42 Bompata', 'NHIS-0123450', '', 'None', '', 'Nex CEC Basic School', 'Basic 5', '2019-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.mercy@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Mercy Yeboah Boateng', 'ENR-2025-G6H7I8', 'CEC-250908-029', 'Basic 6', 'Female', '2013-04-18', 'Kumasi', 'Ghanaian', '45 Danyame', 'Mrs. Mercy Boateng', 'guardian.mercy@parent.com', 'Mother', '0242999000', '0242999111', 'Entrepreneur', '45 Danyame', 'NHIS-1234500', '', 'None', '', 'Nex CEC Basic School', 'Basic 5', '2019-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.joseph2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Joseph Tawiah Jnr.', 'ENR-2025-J9K0L1', NULL, 'Basic 6', 'Male', '2013-07-25', 'Kumasi', 'Ghanaian', '48 Asafo', 'Mr. Joseph Tawiah', 'guardian.joseph2@parent.com', 'Father', '0243000111', '0243000222', 'Security Guard', '48 Asafo', '', '', 'None', '', 'Nex CEC Basic School', 'Basic 5', '2019-09-02', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- JHS 1 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.ruth@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Ruth Ama Asante', 'ENR-2025-M2N3O4', 'CEC-250908-031', 'JHS 1', 'Female', '2012-01-14', 'Kumasi', 'Ghanaian', '51 Amakom', 'Mrs. Ruth Asante', 'guardian.ruth@parent.com', 'Mother', '0244111222', '0244111333', 'Teacher', '51 Amakom', 'NHIS-2345600', '', 'None', '', 'Nex CEC Basic School', 'Basic 6', '2024-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.peter@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Peter Kwame Mensah', 'ENR-2025-P5Q6R7', 'CEC-250908-032', 'JHS 1', 'Male', '2012-04-18', 'Kumasi', 'Ghanaian', '54 Tafo', 'Mr. Peter Mensah', 'guardian.peter@parent.com', 'Father', '0245222333', '0245222444', 'Engineer', '54 Tafo', 'NHIS-3456700', '', 'None', '', 'Nex CEC Basic School', 'Basic 6', '2024-09-02', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.hannah@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Hannah Adjei Kwarteng', 'ENR-2025-S8T9U0', NULL, 'JHS 1', 'Female', '2012-07-25', 'Kumasi', 'Ghanaian', '57 Suame', 'Mrs. Hannah Kwarteng', 'guardian.hannah@parent.com', 'Mother', '0246333444', '0246333555', 'Nurse', '57 Suame', '', '', 'None', '', 'Nex CEC Basic School', 'Basic 6', '2024-09-02', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- JHS 2 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.charles@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Charles Osei Frimpong', 'ENR-2025-V1W2X3', 'CEC-250908-034', 'JHS 2', 'Male', '2011-02-14', 'Kumasi', 'Ghanaian', '60 Ejisu Rd', 'Mr. Charles Frimpong', 'guardian.charles@parent.com', 'Father', '0247444555', '0247444666', 'Businessman', '60 Ejisu Rd', 'NHIS-4567800', '', 'None', '', 'Nex CEC Basic School', 'JHS 1', '2023-09-04', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.deborah@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Deborah Ama Boateng', 'ENR-2025-Y4Z5A6', 'CEC-250908-035', 'JHS 2', 'Female', '2011-05-22', 'Kumasi', 'Ghanaian', '63 Manhyia', 'Mrs. Deborah Boateng', 'guardian.deborah@parent.com', 'Mother', '0248555666', '0248555777', 'Civil Servant', '63 Manhyia', 'NHIS-5678900', '', 'None', '', 'Nex CEC Basic School', 'JHS 1', '2023-09-04', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.eric@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Eric Mensah Tetteh', 'ENR-2025-B7C8D9', NULL, 'JHS 2', 'Male', '2011-08-30', 'Kumasi', 'Ghanaian', '66 Bantama', 'Mr. Eric Tetteh', 'guardian.eric@parent.com', 'Father', '0249666777', '0249666888', 'Driver', '66 Bantama', '', '', 'None', '', 'Nex CEC Basic School', 'JHS 1', '2023-09-04', '2025/2026', 'online', 'unpaid', 'pending')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

-- JHS 3 (3 students)
SELECT id INTO uid FROM users WHERE email = 'guardian.joyce@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Joyce Adwoa Asante', 'ENR-2025-E0F1G2', 'CEC-250908-037', 'JHS 3', 'Female', '2010-01-14', 'Kumasi', 'Ghanaian', '69 Ayeduase', 'Mrs. Joyce Asante', 'guardian.joyce@parent.com', 'Mother', '0240777888', '0240777999', 'Headteacher', '69 Ayeduase', 'NHIS-6789000', '', 'None', '', 'Nex CEC Basic School', 'JHS 2', '2022-09-05', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.david2@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'David Kwame Osei', 'ENR-2025-H3I4J5', 'CEC-250908-038', 'JHS 3', 'Male', '2010-04-18', 'Kumasi', 'Ghanaian', '72 Oforikrom', 'Mr. David Osei', 'guardian.david2@parent.com', 'Father', '0241888999', '0241888000', 'Professor', '72 Oforikrom', 'NHIS-7890100', '', 'None', '', 'Nex CEC Basic School', 'JHS 2', '2022-09-05', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

SELECT id INTO uid FROM users WHERE email = 'guardian.linda@parent.com';
INSERT INTO students (user_id, full_name, enrollment_id, index_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
VALUES (uid, 'Linda Ama Gyamfi', 'ENR-2025-K6L7M8', 'CEC-250908-039', 'JHS 3', 'Female', '2010-07-25', 'Kumasi', 'Ghanaian', '75 Roman Hill', 'Mrs. Linda Gyamfi', 'guardian.linda@parent.com', 'Mother', '0242999000', '0242999111', 'Diplomat', '75 Roman Hill', 'NHIS-8901200', '', 'None', '', 'Nex CEC Basic School', 'JHS 2', '2022-09-05', '2025/2026', 'admin', 'paid', 'enrolled')
ON CONFLICT ON CONSTRAINT students_enrollment_id_key DO NOTHING;

END $$;

-- ==========================================
-- 11. PAYMENTS — 26 payment records (for paid students with index_number)
-- ==========================================
-- Generate payments for all students who have an index_number (paid students)
-- Uses DO block to generate unique receipt numbers per student
DO $$
DECLARE
    s RECORD;
    receipt TEXT;
    method TEXT;
    counter INTEGER := 0;
BEGIN
    FOR s IN SELECT id, enrollment_id, class_name FROM students WHERE index_number IS NOT NULL ORDER BY id LOOP
        counter := counter + 1;
        method := CASE counter % 3
            WHEN 0 THEN 'MTN MoMo'
            WHEN 1 THEN 'Telecel Cash'
            ELSE 'Bank/Cash'
        END;
        receipt := 'NXC-2509' || LPAD(counter::TEXT, 2, '0') || '-' || 
            CASE counter % 3
                WHEN 0 THEN 'MO'
                WHEN 1 THEN 'TC'
                ELSE 'BC'
            END;
        
        INSERT INTO payments (student_id, amount, payment_method, payment_date, receipt_number, status, enrollment_id, academic_year)
        VALUES (s.id, 745.00, method, '2025-09-10', receipt, 'completed', s.enrollment_id, '2025/2026')
        ON CONFLICT ON CONSTRAINT payments_receipt_number_key DO NOTHING;
    END LOOP;
END $$;

-- ==========================================
-- 12. FEE STRUCTURES — Correlated with classes
-- ==========================================
DO $$
DECLARE
    c RECORD;
    base_fee NUMERIC;
BEGIN
    FOR c IN SELECT id, name, level_group FROM classes ORDER BY sort_order LOOP
        -- Set base fee by level
        CASE c.level_group
            WHEN 'early_childhood' THEN base_fee := 350;
            WHEN 'primary' THEN base_fee := 500;
            WHEN 'jhs' THEN base_fee := 650;
            ELSE base_fee := 450;
        END CASE;
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        VALUES 
            (c.id, 'Tuition Fee', base_fee * 0.65, '2025/2026', 'Term 1', 'tuition', true),
            (c.id, 'PTA Dues', 50.00, '2025/2026', 'Term 1', 'pta', true),
            (c.id, 'Sports Fee', 30.00, '2025/2026', 'Term 1', 'sports', true),
            (c.id, 'Library Fee', 20.00, '2025/2026', 'Term 1', 'library', true),
            (c.id, 'ICT Fee', 40.00, '2025/2026', 'Term 1', 'ict', true),
            (c.id, 'Exam Fee', 25.00, '2025/2026', 'Term 1', 'exam', true),
            (c.id, 'Uniform', 80.00, '2025/2026', 'Term 1', 'uniform', false),
            (c.id, 'Textbooks', 50.00, '2025/2026', 'Term 1', 'books', false),
            (c.id, 'Feeding (EC only)', CASE WHEN c.level_group = 'early_childhood' THEN 100.00 ELSE 0 END, '2025/2026', 'Term 1', 'feeding', false),
            (c.id, 'Computer Lab', CASE WHEN c.level_group IN ('primary', 'jhs') THEN 35.00 ELSE 0 END, '2025/2026', 'Term 1', 'lab', false)
        ON CONFLICT DO NOTHING;
    END LOOP;
END $$;

-- ==========================================
-- 13. GRADE BOUNDARIES
-- ==========================================
INSERT INTO grade_boundaries (min_score, max_score, grade, remark) VALUES
(90, 100, 1, 'Excellent'),
(80, 89, 2, 'Distinction'),
(70, 79, 3, 'Credit'),
(60, 69, 4, 'Pass'),
(55, 59, 5, 'Average'),
(50, 54, 6, 'Below Average'),
(45, 49, 7, 'Poor'),
(40, 44, 8, 'Very Poor'),
(0, 39, 9, 'Fail')
ON CONFLICT ON CONSTRAINT grade_boundaries_min_score_max_score_key DO NOTHING;

-- ==========================================
-- 14. SBA SCORES — Term 1, Basic 1 students (core subjects)
-- ==========================================
-- For Basic 1: Akosua Yeboah and Daniel Kofi Asante
DO $$
DECLARE
    student_ids INTEGER[];
    subject_ids INTEGER[];
    sid INTEGER;
    subj_id INTEGER;
    ct NUMERIC;
    mt NUMERIC;
    et NUMERIC;
    exam NUMERIC;
    total NUMERIC;
    att TEXT[];
    int TEXT[];
    i INTEGER := 0;
BEGIN
    -- Get Basic 1 student IDs
    SELECT ARRAY(SELECT id FROM students WHERE class_name = 'Basic 1') INTO student_ids;
    -- Get core subject IDs (English, Math, Science, Social Studies, Ghanaian Language)
    SELECT ARRAY(SELECT id FROM subjects WHERE code IN ('ENG', 'MATH', 'SCI', 'SST', 'GL')) INTO subject_ids;
    
    att := ARRAY['1', '2', '3'];
    int := ARRAY['1', '2', '3'];
    
    FOREACH sid IN ARRAY student_ids LOOP
        FOREACH subj_id IN ARRAY subject_ids LOOP
            -- Generate realistic scores
            ct := (RANDOM() * 10 + 10)::NUMERIC(5,2);  -- 10-20 out of 30
            mt := (RANDOM() * 5 + 5)::NUMERIC(5,2);    -- 5-10 out of 30
            et := (RANDOM() * 5 + 30)::NUMERIC(5,2);   -- 30-35 out of 70
            exam := (RANDOM() * 30 + 50)::NUMERIC(5,2); -- 50-80 out of 100
            
            INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest)
            VALUES (sid, subj_id, 1, ct, mt, et, 0, att[1 + (i % 3)], int[1 + (i % 3)])
            ON CONFLICT ON CONSTRAINT sba_scores_student_subject_term_key DO NOTHING;
            
            INSERT INTO exam_scores (student_id, subject_id, term_id, exam_score)
            VALUES (sid, subj_id, 1, exam)
            ON CONFLICT ON CONSTRAINT sba_scores_student_subject_term_key DO NOTHING;
            
            i := i + 1;
        END LOOP;
    END LOOP;
END $$;

-- SBA scores for JHS 3 students (all 11 subjects)
DO $$
DECLARE
    student_ids INTEGER[];
    subject_ids INTEGER[];
    sid INTEGER;
    subj_id INTEGER;
    ct NUMERIC;
    mt NUMERIC;
    et NUMERIC;
    exam NUMERIC;
    att TEXT[];
    int TEXT[];
    i INTEGER := 0;
BEGIN
    SELECT ARRAY(SELECT id FROM students WHERE class_name = 'JHS 3') INTO student_ids;
    SELECT ARRAY(SELECT id FROM subjects) INTO subject_ids;
    
    att := ARRAY['1', '2', '3'];
    int := ARRAY['1', '2', '3'];
    
    FOREACH sid IN ARRAY student_ids LOOP
        FOREACH subj_id IN ARRAY subject_ids LOOP
            ct := (RANDOM() * 12 + 8)::NUMERIC(5,2);
            mt := (RANDOM() * 8 + 5)::NUMERIC(5,2);
            et := (RANDOM() * 20 + 35)::NUMERIC(5,2);
            exam := (RANDOM() * 35 + 50)::NUMERIC(5,2);
            
            INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest)
            VALUES (sid, subj_id, 1, ct, mt, et, (RANDOM() * 5 + 3)::NUMERIC(5,2), att[1 + (i % 3)], int[1 + (i % 3)])
            ON CONFLICT ON CONSTRAINT sba_scores_student_subject_term_key DO NOTHING;
            
            INSERT INTO exam_scores (student_id, subject_id, term_id, exam_score)
            VALUES (sid, subj_id, 1, exam)
            ON CONFLICT ON CONSTRAINT sba_scores_student_subject_term_key DO NOTHING;
            
            i := i + 1;
        END LOOP;
    END LOOP;
END $$;

-- ==========================================
-- 15. ATTENDANCE RECORDS — November 2025 (100+ records)
-- ==========================================
INSERT INTO student_attendance (student_id, attendance_date, status, reason)
SELECT s.id, d, 
    CASE WHEN RANDOM() < 0.8 THEN 'present'
         WHEN RANDOM() < 0.5 THEN 'late'
         ELSE 'absent'
    END,
    CASE WHEN RANDOM() < 0.5 THEN 'Sick'
         WHEN RANDOM() < 0.3 THEN 'Family emergency'
         ELSE NULL
    END
FROM students s
CROSS JOIN (
    SELECT '2025-11-03'::DATE AS d UNION ALL SELECT '2025-11-04' UNION ALL SELECT '2025-11-05' 
    UNION ALL SELECT '2025-11-06' UNION ALL SELECT '2025-11-07' UNION ALL SELECT '2025-11-10'
    UNION ALL SELECT '2025-11-11' UNION ALL SELECT '2025-11-12' UNION ALL SELECT '2025-11-13'
    UNION ALL SELECT '2025-11-14' UNION ALL SELECT '2025-11-17' UNION ALL SELECT '2025-11-18'
    UNION ALL SELECT '2025-11-19' UNION ALL SELECT '2025-11-20' UNION ALL SELECT '2025-11-21'
) dates
ON CONFLICT ON CONSTRAINT student_attendance_student_attendance_date_key DO NOTHING;

-- ==========================================
-- 16. REPORT CARDS — Basic 1 and JHS 3 students
-- ==========================================
DO $$
DECLARE
    sid INTEGER;
    class_total INTEGER;
    pos INTEGER;
    total_score NUMERIC;
BEGIN
    -- Basic 1 report cards
    class_total := (SELECT COUNT(*) FROM students WHERE class_name = 'Basic 1');
    pos := 0;
    FOR sid IN SELECT id FROM students WHERE class_name = 'Basic 1' ORDER BY full_name LOOP
        pos := pos + 1;
        INSERT INTO report_cards (student_id, term_id, class_position, total_students, class_teacher_remarks, class_teacher_name, headmaster_remarks, headmaster_name, next_term_begins)
        VALUES (sid, 1, pos, class_total, 
            CASE pos WHEN 1 THEN 'An outstanding start to the term.' WHEN 2 THEN 'Very good performance. Keep it up!' ELSE 'A promising start. More effort needed.' END,
            'Ms. Akosua Frimpong',
            CASE pos WHEN 1 THEN 'Excellent work! Maintain this standard.' ELSE 'Good effort. Keep working hard.' END,
            'Mr. Kwabena Asante',
            '2026-01-06')
        ON CONFLICT ON CONSTRAINT report_cards_student_term_key DO NOTHING;
    END LOOP;
    
    -- JHS 3 report cards
    class_total := (SELECT COUNT(*) FROM students WHERE class_name = 'JHS 3');
    pos := 0;
    FOR sid IN SELECT id FROM students WHERE class_name = 'JHS 3' ORDER BY full_name LOOP
        pos := pos + 1;
        INSERT INTO report_cards (student_id, term_id, class_position, total_students, class_teacher_remarks, class_teacher_name, headmaster_remarks, headmaster_name, next_term_begins)
        VALUES (sid, 1, pos, class_total,
            CASE pos WHEN 1 THEN 'Exceptional performance this term. Ready for BECE.' WHEN 2 THEN 'Strong academic showing. Keep the momentum.' ELSE 'Steady progress. Push harder for BECE prep.' END,
            'Mrs. Nana Yeboah',
            CASE pos WHEN 1 THEN 'BECE champion in the making!' ELSE 'Focus on BECE preparation. You can do better.' END,
            'Mr. Kwabena Asante',
            '2026-01-06')
        ON CONFLICT ON CONSTRAINT report_cards_student_term_key DO NOTHING;
    END LOOP;
END $$;

-- ==========================================
-- 17. MESSAGES — Sample broadcasts and direct messages
-- ==========================================
DO $$
DECLARE
    admin_uid INTEGER;
    student_uid INTEGER;
BEGIN
    admin_uid := (SELECT id FROM users WHERE email = 'admin@necxec.edu.gh');
    
    -- Broadcast: Term 1 reopening
    INSERT INTO messages (sender_id, title, content, is_broadcast, created_at)
    VALUES (admin_uid, 'Welcome Back — Term 1 2025/2026', 'Dear Parents and Guardians, we welcome you to the new academic year. Classes resume on September 8th, 2025. Please ensure all fees are paid before the end of the first week.', true, '2025-09-05')
    ON CONFLICT DO NOTHING;
    
    -- Broadcast: PTA Meeting
    INSERT INTO messages (sender_id, title, content, is_broadcast, created_at)
    VALUES (admin_uid, 'PTA Meeting Notice', 'Dear Parents, the first PTA meeting for 2025/2026 will be held on September 20th, 2025 at the school assembly hall. Attendance is mandatory.', true, '2025-09-15')
    ON CONFLICT DO NOTHING;
    
    -- Broadcast: Mid-term reminder
    INSERT INTO messages (sender_id, title, content, is_broadcast, created_at)
    VALUES (admin_uid, 'Mid-Term Examination Schedule', 'Dear Parents, mid-term examinations for Term 1 will commence on October 13th, 2025. Please ensure your wards are well prepared.', true, '2025-10-06')
    ON CONFLICT DO NOTHING;
    
    -- Direct message to a specific parent
    student_uid := (SELECT user_id FROM students WHERE enrollment_id = 'ENR-2025-A1B2C3');
    IF student_uid IS NOT NULL THEN
        INSERT INTO messages (sender_id, receiver_id, title, content, created_at)
        VALUES (admin_uid, student_uid, 'Fee Payment Reminder', 'Dear Parent, this is a gentle reminder that the school fees for Term 1 are due. Please make payment at your earliest convenience.', '2025-09-20')
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- ==========================================
-- 18. NOTIFICATIONS
-- ==========================================
DO $$
DECLARE
    admin_uid INTEGER;
    bursar_uid INTEGER;
BEGIN
    admin_uid := (SELECT id FROM users WHERE email = 'admin@necxec.edu.gh');
    bursar_uid := (SELECT id FROM users WHERE email = 'bursar@necxec.edu.gh');
    
    INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES
    (admin_uid, 'New Enrollment', 'A new student has enrolled online with ID ENR-2025-G7H8I9', false, '2025-09-08'),
    (admin_uid, 'Fee Structure Updated', 'Fee structures for 2025/2026 have been set', true, '2025-09-01'),
    (bursar_uid, 'Payment Received', 'New MTN MoMo payment of GHS 745.00 received', false, '2025-09-10'),
    (admin_uid, 'Attendance Alert', '3 students absent today', false, '2025-11-05'),
    (admin_uid, 'Report Cards Ready', 'Report cards for Term 1 are ready for review', true, '2025-12-15'),
    (bursar_uid, 'Payroll Ready', 'November 2025 payroll is ready for processing', true, '2025-11-25'),
    (admin_uid, 'Staff Attendance', '1 staff member was late today', false, '2025-11-10'),
    (bursar_uid, 'Unpaid Fees Alert', '13 students have unpaid fees for Term 1', false, '2025-09-15')
    ON CONFLICT DO NOTHING;
END $$;

-- ==========================================
-- VERIFICATION
-- ==========================================
SELECT 'users' AS tbl, COUNT(*) FROM users
UNION ALL SELECT 'staff', COUNT(*) FROM staff
UNION ALL SELECT 'students', COUNT(*) FROM students
UNION ALL SELECT 'classes', COUNT(*) FROM classes
UNION ALL SELECT 'subjects', COUNT(*) FROM subjects
UNION ALL SELECT 'terms', COUNT(*) FROM terms
UNION ALL SELECT 'salary_structures', COUNT(*) FROM salary_structures
UNION ALL SELECT 'payroll', COUNT(*) FROM payroll
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'sba_scores', COUNT(*) FROM sba_scores
UNION ALL SELECT 'exam_scores', COUNT(*) FROM exam_scores
UNION ALL SELECT 'student_attendance', COUNT(*) FROM student_attendance
UNION ALL SELECT 'report_cards', COUNT(*) FROM report_cards
UNION ALL SELECT 'messages', COUNT(*) FROM messages
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'system_settings', COUNT(*) FROM system_settings
UNION ALL SELECT 'fee_structures', COUNT(*) FROM fee_structures;
