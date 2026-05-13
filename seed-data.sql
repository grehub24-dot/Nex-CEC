-- ==========================================
-- COMPLETE CORRELATED SEED DATA: Nex CEC Basic School
-- Run AFTER migrate-all.sql
-- Idempotent: safe to run multiple times
-- ==========================================

-- ==========================================
-- 1. SYSTEM SETTINGS
-- ==========================================
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'school_name', 'Nex CEC Basic School' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'school_name');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'school_motto', 'Education for Excellence and Character' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'school_motto');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'school_address', '12 Education Ridge, Kumasi, Ghana' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'school_address');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'school_phone', '+233 32 277 0000' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'school_phone');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'school_email', 'info@necxec.edu.gh' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'school_email');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'current_term', '1' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'current_term');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'next_term_begins', '2026-01-06' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'next_term_begins');
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'school_logo', 'images/school-logo.png' WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'school_logo');

-- ==========================================
-- 2. CLASSES (13 classes)
-- ==========================================
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Creche', 'early_childhood', 0 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Creche');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Nursery 1', 'early_childhood', 1 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Nursery 1');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Nursery 2', 'early_childhood', 2 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Nursery 2');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'KG 1', 'early_childhood', 3 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'KG 1');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'KG 2', 'early_childhood', 4 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'KG 2');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Basic 1', 'primary', 4 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Basic 1');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Basic 2', 'primary', 5 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Basic 2');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Basic 3', 'primary', 6 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Basic 3');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Basic 4', 'primary', 7 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Basic 4');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Basic 5', 'primary', 8 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Basic 5');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'Basic 6', 'primary', 9 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'Basic 6');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'JHS 1', 'jhs', 10 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'JHS 1');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'JHS 2', 'jhs', 11 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'JHS 2');
INSERT INTO classes (name, level_group, sort_order)
SELECT 'JHS 3', 'jhs', 12 WHERE NOT EXISTS (SELECT 1 FROM classes WHERE name = 'JHS 3');

-- ==========================================
-- 3. TERMS (3 terms)
-- ==========================================
INSERT INTO terms (name, academic_year, start_date, end_date, is_active)
SELECT 'Term 1', '2025/2026', '2025-09-08', '2025-12-19', true
WHERE NOT EXISTS (SELECT 1 FROM terms WHERE name = 'Term 1' AND academic_year = '2025/2026');
INSERT INTO terms (name, academic_year, start_date, end_date, is_active)
SELECT 'Term 2', '2025/2026', '2026-01-06', '2026-04-10', false
WHERE NOT EXISTS (SELECT 1 FROM terms WHERE name = 'Term 2' AND academic_year = '2025/2026');
INSERT INTO terms (name, academic_year, start_date, end_date, is_active)
SELECT 'Term 3', '2025/2026', '2026-04-27', '2026-07-31', false
WHERE NOT EXISTS (SELECT 1 FROM terms WHERE name = 'Term 3' AND academic_year = '2025/2026');

-- ==========================================
-- 4. SUBJECTS (11 subjects)
-- ==========================================
INSERT INTO subjects (name, code, class_id)
SELECT 'English Language', 'ENG', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'English Language' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Mathematics', 'MATH', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Mathematics' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Integrated Science', 'SCI', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Integrated Science' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Social Studies', 'SST', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Social Studies' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'French', 'FRE', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'French' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Creative Arts & Design', 'CAD', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Creative Arts & Design' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Ghanaian Language (Twi)', 'GL', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Ghanaian Language (Twi)' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Computing', 'COMP', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Computing' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Physical Education', 'PE', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Physical Education' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Religious & Moral Education', 'RME', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Religious & Moral Education' AND (class_id IS NULL));
INSERT INTO subjects (name, code, class_id)
SELECT 'Career Technology', 'CT', NULL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Career Technology' AND (class_id IS NULL));

-- ==========================================
-- 5. USERS (admin, bursar)
-- ==========================================
-- Password: NexCEC2026!
-- hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (email, password, role, status, is_password_reset)
SELECT 'admin@necxec.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active', true
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@necxec.edu.gh');
INSERT INTO users (email, password, role, status, is_password_reset)
SELECT 'deputy@necxec.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', true
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'deputy@necxec.edu.gh');
INSERT INTO users (email, password, role, status, is_password_reset)
SELECT 'bursar@necxec.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'bursar', 'active', true
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'bursar@necxec.edu.gh');

-- ==========================================
-- 6. STAFF (19 members)
-- ==========================================
DO $$
DECLARE
    admin_uid INTEGER;
    deputy_uid INTEGER;
BEGIN
    admin_uid := (SELECT id FROM users WHERE email = 'admin@necxec.edu.gh');
    deputy_uid := (SELECT id FROM users WHERE email = 'deputy@necxec.edu.gh');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT admin_uid, 'NXC-STF-001', 'Mr. Kwabena Asante', 'Headmaster', 'Administration', 'M.Ed Administration', '0244123456', 'kwabena.asante@necxec.edu.gh', '1975-03-15', 'Male', '15 Bantama High St, Kumasi', '2018-09-01', 'active', 'GCB Bank', '1234567890'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-001');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT deputy_uid, 'NXC-STF-002', 'Mrs. Ama Serwaa Mensah', 'Assistant Head', 'Administration', 'M.Ed Curriculum', '0245234567', 'ama.mensah@necxec.edu.gh', '1980-07-22', 'Female', '22 Ayeduase Rd, Kumasi', '2019-01-15', 'active', 'Ecobank', '2345678901'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-002');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-003', 'Ms. Efua Darko', 'Class Teacher', 'Early Childhood', 'Diploma in Early Childhood Education', '0246345678', 'efua.darko@necxec.edu.gh', '1990-11-05', 'Female', '8 Suame Magazine Rd, Kumasi', '2020-09-01', 'active', 'GCB Bank', '3456789012'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-003');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-004', 'Mr. Kofi Owusu', 'Class Teacher', 'Early Childhood', 'Certificate in Early Childhood', '0247456789', 'kofi.owusu@necxec.edu.gh', '1992-04-18', 'Male', '12 Tech Junction, Kumasi', '2021-01-10', 'active', 'Fidelity Bank', '4567890123'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-004');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-005', 'Mrs. Abena Gyamfi', 'Class Teacher', 'Early Childhood', 'Diploma in Basic Education', '0248567890', 'abena.gyamfi@necxec.edu.gh', '1988-09-30', 'Female', '5 Roman Hill, Kumasi', '2019-09-01', 'active', 'ADB Bank', '5678901234'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-005');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-006', 'Mr. Yaw Boateng', 'Class Teacher', 'Early Childhood', 'Diploma in Basic Education', '0249678901', 'yaw.boateng@necxec.edu.gh', '1985-02-14', 'Male', '20 Oforikrom, Kumasi', '2018-09-01', 'active', 'GCB Bank', '6789012345'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-006');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-007', 'Ms. Akosua Frimpong', 'Class Teacher', 'Primary', 'B.Ed Basic Education', '0240789012', 'akosua.frimpong@necxec.edu.gh', '1991-06-25', 'Female', '3 Asokwa Estate, Kumasi', '2020-09-01', 'active', 'CalBank', '7890123456'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-007');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-008', 'Mr. Emmanuel Tetteh', 'Class Teacher', 'Primary', 'B.Ed Basic Education', '0241890123', 'emmanuel.tetteh@necxec.edu.gh', '1987-12-08', 'Male', '17 Ahodwo Circle, Kumasi', '2019-09-01', 'active', 'Ecobank', '8901234567'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-008');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-009', 'Mrs. Grace Adjei', 'Class Teacher', 'Primary', 'Diploma in Basic Education', '0242901234', 'grace.adjei@necxec.edu.gh', '1983-01-20', 'Female', '9 Kentinkrono, Kumasi', '2017-09-01', 'active', 'GCB Bank', '9012345678'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-009');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-010', 'Mr. Daniel Kwarteng', 'Class Teacher', 'Primary', 'B.Ed Mathematics', '0243012345', 'daniel.kwarteng@necxec.edu.gh', '1986-08-11', 'Male', '14 Patasi, Kumasi', '2018-09-01', 'active', 'Fidelity Bank', '0123456789'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-010');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-011', 'Ms. Victoria Appiah', 'Class Teacher', 'Primary', 'B.Ed English', '0244123457', 'victoria.appiah@necxec.edu.gh', '1993-03-28', 'Female', '6 Danyame, Kumasi', '2021-09-01', 'active', 'ADB Bank', '1234509876'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-011');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-012', 'Mr. Samuel Osei', 'Class Teacher', 'Primary', 'B.Ed Science', '0245234568', 'samuel.osei@necxec.edu.gh', '1984-10-03', 'Male', '21 Bompata, Kumasi', '2017-09-01', 'active', 'GCB Bank', '2345610987'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-012');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-013', 'Mrs. Nana Yeboah', 'Class Teacher', 'JHS', 'B.Ed Mathematics', '0246345679', 'nana.yeboah@necxec.edu.gh', '1982-05-17', 'Female', '11 Amakom, Kumasi', '2016-09-01', 'active', 'CalBank', '3456721098'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-013');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-014', 'Mr. Isaac Mensah', 'Class Teacher', 'JHS', 'B.Ed English', '0247456780', 'isaac.mensah@necxec.edu.gh', '1989-07-09', 'Male', '7 Asafo, Kumasi', '2019-09-01', 'active', 'Ecobank', '4567832109'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-014');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-015', 'Mr. Frank Agyemang', 'Class Teacher', 'JHS', 'B.Ed Integrated Science', '0248567891', 'frank.agyemang@necxec.edu.gh', '1990-01-25', 'Male', '18 Tafo, Kumasi', '2020-09-01', 'active', 'Fidelity Bank', '5678943210'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-015');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-016', 'Mr. Bernard Anane', 'ICT Teacher', 'ICT', 'BSc Computer Science', '0249678902', 'bernard.anane@necxec.edu.gh', '1994-06-12', 'Male', '4 Kwame Nkrumah St, Kumasi', '2022-01-10', 'active', 'GCB Bank', '6789054321'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-016');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-017', 'Mrs. Beatrice Nkrumah', 'French Teacher', 'Languages', 'BA French', '0240789013', 'beatrice.nkrumah@necxec.edu.gh', '1991-09-04', 'Female', '13 Ejisu Rd, Kumasi', '2021-09-01', 'active', 'ADB Bank', '7890165432'
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-017');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-018', 'Mr. Joseph Tawiah', 'Security Officer', 'Operations', 'SHS Certificate', '0241890124', 'joseph.tawiah@necxec.edu.gh', '1978-04-30', 'Male', '25 Suame, Kumasi', '2015-03-01', 'active', NULL, NULL
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-018');

    INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, date_of_birth, gender, address, hire_date, status, bank_name, account_number)
    SELECT NULL, 'NXC-STF-019', 'Mrs. Comfort Agyei', 'Cleaner', 'Operations', 'JHS Certificate', '0242901235', 'comfort.agyei@necxec.edu.gh', '1985-11-19', 'Female', '10 Manhyia, Kumasi', '2016-06-01', 'active', NULL, NULL
    WHERE NOT EXISTS (SELECT 1 FROM staff WHERE staff_id = 'NXC-STF-019');
END $$;

-- ==========================================
-- 7. STAFF USER ACCOUNTS (auto-create for staff without accounts)
-- ==========================================
DO $$
DECLARE
    s RECORD;
    uid INTEGER;
BEGIN
    FOR s IN SELECT id, email FROM staff WHERE email IS NOT NULL AND email != '' AND user_id IS NULL LOOP
        INSERT INTO users (email, password, role, status, is_password_reset)
        SELECT s.email, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active', false
        WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = s.email);
        
        SELECT id INTO uid FROM users WHERE email = s.email;
        IF uid IS NOT NULL THEN
            UPDATE staff SET user_id = uid WHERE id = s.id AND user_id IS NULL;
        END IF;
    END LOOP;
END $$;

-- ==========================================
-- 8. SALARY STRUCTURES (per active staff)
-- ==========================================
DO $$
DECLARE
    s RECORD;
    base NUMERIC;
    existing_id INTEGER;
BEGIN
    FOR s IN SELECT id, position FROM staff WHERE status = 'active' LOOP
        SELECT id INTO existing_id FROM salary_structures WHERE staff_id = s.id;
        IF existing_id IS NULL THEN
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
            VALUES (s.id, base, base * 0.15, base * 0.10, base * 0.05, 13.50, 0);
        END IF;
    END LOOP;
END $$;

-- ==========================================
-- 9. PAYROLL (November 2025)
-- ==========================================
DO $$
DECLARE
    s RECORD;
    ss RECORD;
    gross NUMERIC;
    ssnit_amt NUMERIC;
    tax_amt NUMERIC;
    net NUMERIC;
    existing_id INTEGER;
BEGIN
    FOR s IN SELECT id FROM staff WHERE status = 'active' LOOP
        SELECT id INTO existing_id FROM payroll WHERE staff_id = s.id AND month = 11 AND year = 2025;
        IF existing_id IS NULL THEN
            SELECT basic_salary, housing_allowance, transport_allowance, other_allowances, ssnit_rate, tax_rate
            INTO ss FROM salary_structures WHERE staff_id = s.id;
            
            IF ss IS NOT NULL THEN
                gross := ss.basic_salary + ss.housing_allowance + ss.transport_allowance + ss.other_allowances;
                ssnit_amt := ss.basic_salary * (ss.ssnit_rate / 100);
                tax_amt := ss.basic_salary * ss.tax_rate / 100;
                net := gross - ssnit_amt - tax_amt;
                
                INSERT INTO payroll (staff_id, month, year, basic_salary, total_allowances, gross_pay, ssnit_deduction, tax_deduction, other_deductions, total_deductions, net_pay, status, pay_date)
                VALUES (s.id, 11, 2025, ss.basic_salary, ss.housing_allowance + ss.transport_allowance + ss.other_allowances, gross, ssnit_amt, tax_amt, 0, ssnit_amt + tax_amt, net, 'paid', '2025-11-28');
            END IF;
        END IF;
    END LOOP;
END $$;

-- ==========================================
-- 10. GRADE BOUNDARIES (Ghana Basic School)
-- ==========================================
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 90, 100, 1, 'Excellent' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 90 AND max_score = 100);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 80, 89, 2, 'Distinction' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 80 AND max_score = 89);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 70, 79, 3, 'Credit' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 70 AND max_score = 79);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 60, 69, 4, 'Pass' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 60 AND max_score = 69);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 55, 59, 5, 'Average' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 55 AND max_score = 59);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 50, 54, 6, 'Below Average' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 50 AND max_score = 54);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 45, 49, 7, 'Poor' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 45 AND max_score = 49);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 40, 44, 8, 'Very Poor' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 40 AND max_score = 44);
INSERT INTO grade_boundaries (min_score, max_score, grade, remark)
SELECT 0, 39, 9, 'Fail' WHERE NOT EXISTS (SELECT 1 FROM grade_boundaries WHERE min_score = 0 AND max_score = 39);

-- ==========================================
-- 11. STUDENT USERS (guardian portal accounts)
-- ==========================================
-- 39 guardian emails
DO $$
DECLARE
    emails TEXT[] := ARRAY[
        'guardian.kwame@parent.com','guardian.abena@parent.com','guardian.kofi@parent.com',
        'guardian.ama@parent.com','guardian.yaw@parent.com','guardian.efua@parent.com',
        'guardian.kweku@parent.com','guardian.akua@parent.com','guardian.osei@parent.com',
        'guardian.adwoa@parent.com','guardian.kwabena2@parent.com','guardian.nana@parent.com',
        'guardian.akosua2@parent.com','guardian.daniel2@parent.com','guardian.grace2@parent.com',
        'guardian.samuel2@parent.com','guardian.victoria2@parent.com','guardian.isaac2@parent.com',
        'guardian.nana2@parent.com','guardian.frank2@parent.com','guardian.emmanuel2@parent.com',
        'guardian.bernice@parent.com','guardian.richard@parent.com','guardian.prince@parent.com',
        'guardian.bridget@parent.com','guardian.felix@parent.com','guardian.stella@parent.com',
        'guardian.alfred@parent.com','guardian.mercy@parent.com','guardian.joseph2@parent.com',
        'guardian.ruth@parent.com','guardian.peter@parent.com','guardian.hannah@parent.com',
        'guardian.charles@parent.com','guardian.deborah@parent.com','guardian.eric@parent.com',
        'guardian.joyce@parent.com','guardian.david2@parent.com','guardian.linda@parent.com'
    ];
    em TEXT;
BEGIN
    FOREACH em IN ARRAY emails LOOP
        IF NOT EXISTS (SELECT 1 FROM users WHERE email = em) THEN
            INSERT INTO users (email, password, role, status, is_password_reset)
            VALUES (em, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', true);
        END IF;
    END LOOP;
END $$;

-- ==========================================
-- 12. STUDENTS (39 students ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â 3 per class)
-- ==========================================
DO $$
DECLARE
    uid INTEGER;
BEGIN
    -- Creche (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.kwame@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Kwame Junior Asante', 'ENR-2025-A1B2C3', 'CEC-250908-001', 'Creche', 'Male', '2022-05-10', 'Kumasi', 'Ghanaian', '15 Bantama', 'Mr. Kwame Asante Sr.', 'guardian.kwame@parent.com', 'Father', '0244111222', '0244111333', 'Trader', '15 Bantama High St', 'NHIS-1234567', '', 'None', NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-001');

    SELECT id INTO uid FROM users WHERE email = 'guardian.abena@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Abena Grace Mensah', 'ENR-2025-D4E5F6', 'CEC-250908-002', 'Creche', 'Female', '2022-08-15', 'Kumasi', 'Ghanaian', '22 Ayeduase', 'Mrs. Abena Mensah', 'guardian.abena@parent.com', 'Mother', '0245222333', '0245222444', 'Nurse', '22 Ayeduase Rd', 'NHIS-2345678', '', 'None', NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-002');

    SELECT id INTO uid FROM users WHERE email = 'guardian.ama@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Ama Serwaa Darko', 'ENR-2025-J1K2L3', 'CEC-250908-003', 'Nursery 1', 'Female', '2021-03-12', 'Kumasi', 'Ghanaian', '5 Roman Hill', 'Mrs. Ama Darko', 'guardian.ama@parent.com', 'Mother', '0247444555', '0247444666', 'Teacher', '5 Roman Hill', 'NHIS-4567890', 'Mild asthma', 'None', NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-003');

    SELECT id INTO uid FROM users WHERE email = 'guardian.yaw@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Yaw Boateng Jnr.', 'ENR-2025-M4N5O6', 'CEC-250908-004', 'Nursery 1', 'Male', '2021-06-18', 'Kumasi', 'Ghanaian', '20 Oforikrom', 'Mr. Yaw Boateng Sr.', 'guardian.yaw@parent.com', 'Father', '0248555666', '0248555777', 'Farmer', '20 Oforikrom', 'NHIS-5678901', '', 'None', NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-004');

    SELECT id INTO uid FROM users WHERE email = 'guardian.efua@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Efua Adjei', 'ENR-2025-P7Q8R9', 'CEC-250908-005', 'Nursery 1', 'Female', '2021-09-25', 'Kumasi', 'Ghanaian', '3 Asokwa', 'Mrs. Efua Adjei', 'guardian.efua@parent.com', 'Mother', '0249666777', '0249666888', 'Hairdresser', '3 Asokwa Estate', NULL, 'None', NULL, NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-005');

    -- KG 1 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.kweku@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Kweku Tetteh', 'ENR-2025-S1T2U3', 'CEC-250908-006', 'KG 1', 'Male', '2020-01-14', 'Kumasi', 'Ghanaian', '17 Ahodwo', 'Mr. Kweku Tetteh', 'guardian.kweku@parent.com', 'Father', '0240777888', '0240777999', 'Banker', '17 Ahodwo Circle', 'NHIS-6789012', '', 'None', NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-006');

    SELECT id INTO uid FROM users WHERE email = 'guardian.akua@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Akua Frimpong', 'ENR-2025-V4W5X6', 'CEC-250908-007', 'KG 1', 'Female', '2020-04-22', 'Kumasi', 'Ghanaian', '9 Kentinkrono', 'Mrs. Akua Frimpong', 'guardian.akua@parent.com', 'Mother', '0241888999', '0241888000', 'Accountant', '9 Kentinkrono', 'NHIS-7890123', '', 'None', NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-007');

    SELECT id INTO uid FROM users WHERE email = 'guardian.osei@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Osei Kwarteng', 'ENR-2025-Y7Z8A0', 'CEC-250908-008', 'KG 1', 'Male', '2020-07-30', 'Kumasi', 'Ghanaian', '14 Patasi', 'Mr. Osei Kwarteng', 'guardian.osei@parent.com', 'Father', '0242999000', '0242999111', 'Driver', '14 Patasi', NULL, 'None', NULL, NULL, NULL, NULL, '2025-09-08', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-008');

    -- KG 2 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.adwoa@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Adwoa Mensah', 'ENR-2025-B1C2D3', 'CEC-250908-009', 'KG 2', 'Female', '2019-02-14', 'Kumasi', 'Ghanaian', '6 Danyame', 'Mrs. Adwoa Mensah', 'guardian.adwoa@parent.com', 'Mother', '0243000111', '0243000222', 'Pharmacist', '6 Danyame', 'NHIS-8901234', '', 'Dust allergy', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-009');

    SELECT id INTO uid FROM users WHERE email = 'guardian.kwabena2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Kwabena Osei II', 'ENR-2025-E4F5G6', 'CEC-250908-010', 'KG 2', 'Male', '2019-05-20', 'Kumasi', 'Ghanaian', '21 Bompata', 'Mr. Kwabena Osei', 'guardian.kwabena2@parent.com', 'Father', '0244111222', '0244111333', 'Engineer', '21 Bompata', 'NHIS-9012345', '', 'None', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-010');

    SELECT id INTO uid FROM users WHERE email = 'guardian.nana@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Nana Ama Frimpong', 'ENR-2025-H7I8J9', 'CEC-250908-011', 'KG 2', 'Female', '2019-08-11', 'Kumasi', 'Ghanaian', '11 Amakom', 'Mrs. Nana Frimpong', 'guardian.nana@parent.com', 'Mother', '0245222333', '0245222444', 'Civil Servant', '11 Amakom', 'NHIS-1234567', '', 'None', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-011');

    -- Basic 1 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.akosua2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Akosua Yeboah', 'ENR-2025-K1L2M3', 'CEC-250908-012', 'Basic 1', 'Female', '2018-03-15', 'Kumasi', 'Ghanaian', '7 Asafo', 'Mrs. Akosua Yeboah', 'guardian.akosua2@parent.com', 'Mother', '0246333444', '0246333555', 'Business Owner', '7 Asafo', 'NHIS-0123456', '', 'None', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-012');

    SELECT id INTO uid FROM users WHERE email = 'guardian.daniel2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Daniel Kofi Asante', 'ENR-2025-N4O5P6', 'CEC-250908-013', 'Basic 1', 'Male', '2018-06-22', 'Kumasi', 'Ghanaian', '18 Tafo', 'Mr. Daniel Asante', 'guardian.daniel2@parent.com', 'Father', '0247444555', '0247444666', 'Pastor', '18 Tafo', 'NHIS-1234560', '', 'None', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-013');

    SELECT id INTO uid FROM users WHERE email = 'guardian.grace2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Grace Adwoa Boateng', 'ENR-2025-Q7R8S9', 'CEC-250908-014', 'Basic 1', 'Female', '2018-09-30', 'Kumasi', 'Ghanaian', '25 Suame', 'Mrs. Grace Boateng', 'guardian.grace2@parent.com', 'Mother', '0248555666', '0248555777', 'Market Woman', '25 Suame', NULL, 'None', NULL, NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-014');

    -- Basic 2 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.samuel2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Samuel Owusu Tetteh', 'ENR-2025-T1U2V3', 'CEC-250908-015', 'Basic 2', 'Male', '2017-01-10', 'Kumasi', 'Ghanaian', '10 Manhyia', 'Mr. Samuel Tetteh', 'guardian.samuel2@parent.com', 'Father', '0249666777', '0249666888', 'Teacher', '10 Manhyia', 'NHIS-2345670', '', 'None', NULL, NULL, NULL, '2023-09-04', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-015');

    SELECT id INTO uid FROM users WHERE email = 'guardian.victoria2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Victoria Ama Gyamfi', 'ENR-2025-W4X5Y6', 'CEC-250908-016', 'Basic 2', 'Female', '2017-04-18', 'Kumasi', 'Ghanaian', '4 Kwame Nkrumah St', 'Mrs. Victoria Gyamfi', 'guardian.victoria2@parent.com', 'Mother', '0240777888', '0240777999', 'Fashion Designer', '4 Kwame Nkrumah St', 'NHIS-3456780', 'Sickle cell trait', 'None', NULL, NULL, NULL, '2023-09-04', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-016');

    SELECT id INTO uid FROM users WHERE email = 'guardian.isaac2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Isaac Mensah Jnr.', 'ENR-2025-Z7A8B9', 'CEC-250908-017', 'Basic 2', 'Male', '2017-07-25', 'Kumasi', 'Ghanaian', '13 Ejisu Rd', 'Mr. Isaac Mensah Sr.', 'guardian.isaac2@parent.com', 'Father', '0241888999', '0241888000', 'Electrician', '13 Ejisu Rd', NULL, 'None', NULL, NULL, NULL, NULL, '2023-09-04', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-017');

    -- Basic 3 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.nana2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Nana Kwame Frimpong', 'ENR-2025-C1D2E3', 'CEC-250908-018', 'Basic 3', 'Male', '2016-02-14', 'Kumasi', 'Ghanaian', '16 Bantama', 'Mr. Nana Frimpong', 'guardian.nana2@parent.com', 'Father', '0242999000', '0242999111', 'Police Officer', '16 Bantama', 'NHIS-4567890', '', 'None', NULL, NULL, NULL, '2022-09-05', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-018');

    SELECT id INTO uid FROM users WHERE email = 'guardian.frank2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Frank Agyemang Asante', 'ENR-2025-F4G5H6', 'CEC-250908-019', 'Basic 3', 'Male', '2016-05-22', 'Kumasi', 'Ghanaian', '19 Ayeduase', 'Mr. Frank Asante', 'guardian.frank2@parent.com', 'Father', '0243000111', '0243000222', 'Soldier', '19 Ayeduase', 'NHIS-5678900', '', 'None', NULL, NULL, NULL, '2022-09-05', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-019');

    SELECT id INTO uid FROM users WHERE email = 'guardian.emmanuel2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Emmanuel Tetteh II', 'ENR-2025-I7J8K9', 'CEC-250908-020', 'Basic 3', 'Male', '2016-08-30', 'Kumasi', 'Ghanaian', '23 Suame', 'Mr. Emmanuel Tetteh', 'guardian.emmanuel2@parent.com', 'Father', '0244111222', '0244111333', 'Carpenter', '23 Suame', NULL, 'None', NULL, NULL, NULL, NULL, '2022-09-05', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-020');

    -- Basic 4 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.bernice@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Bernice Adjei Mensah', 'ENR-2025-L1M2N3', 'CEC-250908-021', 'Basic 4', 'Female', '2015-01-14', 'Kumasi', 'Ghanaian', '12 Tech Junction', 'Mrs. Bernice Mensah', 'guardian.bernice@parent.com', 'Mother', '0245222333', '0245222444', 'Doctor', '12 Tech Junction', 'NHIS-6789010', '', 'Penicillin', NULL, NULL, NULL, '2021-09-06', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-021');

    SELECT id INTO uid FROM users WHERE email = 'guardian.richard@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Richard Osei Boateng', 'ENR-2025-O4P5Q6', 'CEC-250908-022', 'Basic 4', 'Male', '2015-04-18', 'Kumasi', 'Ghanaian', '28 Oforikrom', 'Mr. Richard Boateng', 'guardian.richard@parent.com', 'Father', '0246333444', '0246333555', 'Lawyer', '28 Oforikrom', 'NHIS-7890120', '', 'None', NULL, NULL, NULL, '2021-09-06', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-022');

    SELECT id INTO uid FROM users WHERE email = 'guardian.prince@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Prince Kofi Asante', 'ENR-2025-R7S8T9', 'CEC-250908-023', 'Basic 4', 'Male', '2015-07-25', 'Kumasi', 'Ghanaian', '30 Roman Hill', 'Mr. Prince Asante', 'guardian.prince@parent.com', 'Father', '0247444555', '0247444666', 'Plumber', '30 Roman Hill', NULL, 'None', NULL, NULL, NULL, NULL, '2021-09-06', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-023');

    -- Basic 5 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.bridget@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Bridget Ama Darko', 'ENR-2025-U1V2W3', 'CEC-250908-024', 'Basic 5', 'Female', '2014-02-14', 'Kumasi', 'Ghanaian', '33 Ahodwo', 'Mrs. Bridget Darko', 'guardian.bridget@parent.com', 'Mother', '0248555666', '0248555777', 'Journalist', '33 Ahodwo', 'NHIS-8901230', '', 'None', NULL, NULL, NULL, '2020-09-07', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-024');

    SELECT id INTO uid FROM users WHERE email = 'guardian.felix@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Felix Mensah Owusu', 'ENR-2025-X4Y5Z6', 'CEC-250908-025', 'Basic 5', 'Male', '2014-05-22', 'Kumasi', 'Ghanaian', '36 Kentinkrono', 'Mr. Felix Owusu', 'guardian.felix@parent.com', 'Father', '0249666777', '0249666888', 'Architect', '36 Kentinkrono', 'NHIS-9012340', '', 'None', NULL, NULL, NULL, '2020-09-07', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-025');

    SELECT id INTO uid FROM users WHERE email = 'guardian.stella@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Stella Adwoa Frimpong', 'ENR-2025-A0B1C2', 'CEC-250908-026', 'Basic 5', 'Female', '2014-08-30', 'Kumasi', 'Ghanaian', '39 Patasi', 'Mrs. Stella Frimpong', 'guardian.stella@parent.com', 'Mother', '0240777888', '0240777999', 'Pharmacist', '39 Patasi', NULL, 'None', NULL, NULL, NULL, NULL, '2020-09-07', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-026');

    -- Basic 6 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.alfred@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Alfred Kwarteng Asante', 'ENR-2025-D3E4F5', 'CEC-250908-027', 'Basic 6', 'Male', '2013-01-14', 'Kumasi', 'Ghanaian', '42 Bompata', 'Mr. Alfred Asante', 'guardian.alfred@parent.com', 'Father', '0241888999', '0241888000', 'Accountant', '42 Bompata', 'NHIS-0123450', '', 'None', NULL, NULL, NULL, '2019-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-027');

    SELECT id INTO uid FROM users WHERE email = 'guardian.mercy@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Mercy Yeboah Boateng', 'ENR-2025-G6H7I8', 'CEC-250908-028', 'Basic 6', 'Female', '2013-04-18', 'Kumasi', 'Ghanaian', '45 Danyame', 'Mrs. Mercy Boateng', 'guardian.mercy@parent.com', 'Mother', '0242999000', '0242999111', 'Entrepreneur', '45 Danyame', 'NHIS-1234500', '', 'None', NULL, NULL, NULL, '2019-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-028');

    SELECT id INTO uid FROM users WHERE email = 'guardian.joseph2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Joseph Tawiah Jnr.', 'ENR-2025-J9K0L1', 'CEC-250908-029', 'Basic 6', 'Male', '2013-07-25', 'Kumasi', 'Ghanaian', '48 Asafo', 'Mr. Joseph Tawiah', 'guardian.joseph2@parent.com', 'Father', '0243000111', '0243000222', 'Security Guard', '48 Asafo', NULL, 'None', NULL, NULL, NULL, NULL, '2019-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-029');

    -- JHS 1 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.ruth@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Ruth Ama Asante', 'ENR-2025-M2N3O4', 'CEC-250908-030', 'JHS 1', 'Female', '2012-01-14', 'Kumasi', 'Ghanaian', '51 Amakom', 'Mrs. Ruth Asante', 'guardian.ruth@parent.com', 'Mother', '0244111222', '0244111333', 'Teacher', '51 Amakom', 'NHIS-2345600', '', 'None', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-030');

    SELECT id INTO uid FROM users WHERE email = 'guardian.peter@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Peter Kwame Mensah', 'ENR-2025-P5Q6R7', 'CEC-250908-031', 'JHS 1', 'Male', '2012-04-18', 'Kumasi', 'Ghanaian', '54 Tafo', 'Mr. Peter Mensah', 'guardian.peter@parent.com', 'Father', '0245222333', '0245222444', 'Engineer', '54 Tafo', 'NHIS-3456700', '', 'None', NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-031');

    SELECT id INTO uid FROM users WHERE email = 'guardian.hannah@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Hannah Adjei Kwarteng', 'ENR-2025-S8T9U0', 'CEC-250908-032', 'JHS 1', 'Female', '2012-07-25', 'Kumasi', 'Ghanaian', '57 Suame', 'Mrs. Hannah Kwarteng', 'guardian.hannah@parent.com', 'Mother', '0246333444', '0246333555', 'Nurse', '57 Suame', NULL, 'None', NULL, NULL, NULL, NULL, '2024-09-02', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-032');

    -- JHS 2 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.charles@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Charles Osei Frimpong', 'ENR-2025-V1W2X3', 'CEC-250908-033', 'JHS 2', 'Male', '2011-02-14', 'Kumasi', 'Ghanaian', '60 Ejisu Rd', 'Mr. Charles Frimpong', 'guardian.charles@parent.com', 'Father', '0247444555', '0247444666', 'Businessman', '60 Ejisu Rd', 'NHIS-4567800', '', 'None', NULL, NULL, NULL, '2023-09-04', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-033');

    SELECT id INTO uid FROM users WHERE email = 'guardian.deborah@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Deborah Ama Boateng', 'ENR-2025-Y4Z5A6', 'CEC-250908-034', 'JHS 2', 'Female', '2011-05-22', 'Kumasi', 'Ghanaian', '63 Manhyia', 'Mrs. Deborah Boateng', 'guardian.deborah@parent.com', 'Mother', '0248555666', '0248555777', 'Civil Servant', '63 Manhyia', 'NHIS-5678900', '', 'None', NULL, NULL, NULL, '2023-09-04', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-034');

    SELECT id INTO uid FROM users WHERE email = 'guardian.eric@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Eric Mensah Tetteh', 'ENR-2025-B7C8D9', 'CEC-250908-035', 'JHS 2', 'Male', '2011-08-30', 'Kumasi', 'Ghanaian', '66 Bantama', 'Mr. Eric Tetteh', 'guardian.eric@parent.com', 'Father', '0249666777', '0249666888', 'Driver', '66 Bantama', NULL, 'None', NULL, NULL, NULL, NULL, '2023-09-04', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-035');

    -- JHS 3 (3)
    SELECT id INTO uid FROM users WHERE email = 'guardian.joyce@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Joyce Adwoa Asante', 'ENR-2025-E0F1G2', 'CEC-250908-036', 'JHS 3', 'Female', '2010-01-14', 'Kumasi', 'Ghanaian', '69 Ayeduase', 'Mrs. Joyce Asante', 'guardian.joyce@parent.com', 'Mother', '0240777888', '0240777999', 'Headteacher', '69 Ayeduase', 'NHIS-6789000', '', 'None', NULL, NULL, NULL, '2022-09-05', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-036');

    SELECT id INTO uid FROM users WHERE email = 'guardian.david2@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'David Kwame Osei', 'ENR-2025-H3I4J5', 'CEC-250908-037', 'JHS 3', 'Male', '2010-04-18', 'Kumasi', 'Ghanaian', '72 Oforikrom', 'Mr. David Osei', 'guardian.david2@parent.com', 'Father', '0241888999', '0241888000', 'Professor', '72 Oforikrom', 'NHIS-7890100', '', 'None', NULL, NULL, NULL, '2022-09-05', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-037');

    SELECT id INTO uid FROM users WHERE email = 'guardian.linda@parent.com';
    INSERT INTO students (user_id, full_name, enrollment_id, admission_number, class_name, gender, date_of_birth, place_of_birth, nationality, address, guardian_name, guardian_email, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address, health_insurance_id, medical_conditions, allergies, special_needs, previous_school, previous_class, admission_date, academic_year, enrollment_type, payment_status, status)
    SELECT uid, 'Linda Ama Gyamfi', 'ENR-2025-K6L7M8', 'CEC-250908-038', 'JHS 3', 'Female', '2010-07-25', 'Kumasi', 'Ghanaian', '75 Roman Hill', 'Mrs. Linda Gyamfi', 'guardian.linda@parent.com', 'Mother', '0242999000', '0242999111', 'Diplomat', '75 Roman Hill', 'NHIS-8901200', '', 'None', NULL, NULL, NULL, '2022-09-05', '2025/2026', 'New', 'Paid', 'Active'
    WHERE NOT EXISTS (SELECT 1 FROM students WHERE admission_number = 'CEC-250908-038');
END $$;

-- ==========================================
-- 13. PAYMENTS (26 records ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â one per paid student)
-- ==========================================
DO $$
DECLARE
    s RECORD;
    method TEXT;
    rcpt TEXT;
    counter INT := 0;
BEGIN
    FOR s IN SELECT id, enrollment_id FROM students WHERE admission_number IS NOT NULL ORDER BY id LOOP
        counter := counter + 1;
        method := CASE counter % 3 WHEN 0 THEN 'MTN MoMo' WHEN 1 THEN 'Telecel Cash' ELSE 'Bank/Cash' END;
        rcpt := 'NXC-2509' || LPAD(counter::TEXT, 2, '0') || '-' ||
                CASE counter % 3 WHEN 0 THEN 'MO' WHEN 1 THEN 'TC' ELSE 'BC' END;
        
        INSERT INTO payments (student_id, amount, payment_method, payment_date, receipt_number, status, enrollment_id, academic_year)
        SELECT s.id, 745.00, method, '2025-09-10', rcpt, 'completed', s.enrollment_id, '2025/2026'
        WHERE NOT EXISTS (SELECT 1 FROM payments WHERE receipt_number = rcpt);
    END LOOP;
END $$;

-- ==========================================
-- 14. FEE STRUCTURES (per class)
-- ==========================================
DO $$
DECLARE
    c RECORD;
    base_fee NUMERIC;
BEGIN
    FOR c IN SELECT id, name, level_group FROM classes ORDER BY sort_order LOOP
        CASE c.level_group
            WHEN 'early_childhood' THEN base_fee := 350;
            WHEN 'primary' THEN base_fee := 500;
            WHEN 'jhs' THEN base_fee := 650;
            ELSE base_fee := 450;
        END CASE;
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'Tuition Fee', base_fee * 0.65, '2025/2026', 'Term 1', 'tuition', true
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'Tuition Fee' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'PTA Dues', 50.00, '2025/2026', 'Term 1', 'pta', true
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'PTA Dues' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'Sports Fee', 30.00, '2025/2026', 'Term 1', 'sports', true
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'Sports Fee' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'Library Fee', 20.00, '2025/2026', 'Term 1', 'library', true
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'Library Fee' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'ICT Fee', 40.00, '2025/2026', 'Term 1', 'ict', true
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'ICT Fee' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'Exam Fee', 25.00, '2025/2026', 'Term 1', 'exam', true
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'Exam Fee' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'Uniform', 80.00, '2025/2026', 'Term 1', 'uniform', false
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'Uniform' AND academic_year = '2025/2026');
        
        INSERT INTO fee_structures (class_id, title, amount, academic_year, term, fee_type, is_mandatory)
        SELECT c.id, 'Textbooks', 50.00, '2025/2026', 'Term 1', 'books', false
        WHERE NOT EXISTS (SELECT 1 FROM fee_structures WHERE class_id = c.id AND title = 'Textbooks' AND academic_year = '2025/2026');
    END LOOP;
END $$;

-- ==========================================
-- 15. SBA SCORES (Basic 1 + JHS 3 students)
-- ==========================================
DO $$
DECLARE
    student_ids INT[];
    subject_ids INT[];
    sid INT;
    subj_id INT;
    ct NUMERIC;
    mt NUMERIC;
    et NUMERIC;
    exam NUMERIC;
    att TEXT[];
    intt TEXT[];
    i INT := 0;
    term1_id INT;
BEGIN
    term1_id := (SELECT id FROM terms WHERE name = 'Term 1' AND academic_year = '2025/2026');
    IF term1_id IS NULL THEN RETURN; END IF;

    -- Basic 1 students (English, Math, Science, Social, GL)
    SELECT ARRAY(SELECT id FROM students WHERE class_name = 'Basic 1') INTO student_ids;
    SELECT ARRAY(SELECT id FROM subjects WHERE code IN ('ENG','MATH','SCI','SST','GL')) INTO subject_ids;
    att := ARRAY['1','2','3'];
    intt := ARRAY['1','2','3'];

    FOREACH sid IN ARRAY student_ids LOOP
        FOREACH subj_id IN ARRAY subject_ids LOOP
            ct := (RANDOM() * 10 + 10)::NUMERIC(5,2);
            mt := (RANDOM() * 5 + 5)::NUMERIC(5,2);
            et := (RANDOM() * 5 + 30)::NUMERIC(5,2);
            exam := (RANDOM() * 30 + 50)::NUMERIC(5,2);
            i := i + 1;

            INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest)
            SELECT sid, subj_id, term1_id, ct, mt, et, 0, att[1 + (i % 3)], intt[1 + (i % 3)]
            WHERE NOT EXISTS (SELECT 1 FROM sba_scores WHERE student_id = sid AND subject_id = subj_id AND term_id = term1_id);

            INSERT INTO exam_scores (student_id, subject_id, term_id, exam_score)
            SELECT sid, subj_id, term1_id, exam
            WHERE NOT EXISTS (SELECT 1 FROM exam_scores WHERE student_id = sid AND subject_id = subj_id AND term_id = term1_id);
        END LOOP;
    END LOOP;

    -- JHS 3 students (all 11 subjects)
    SELECT ARRAY(SELECT id FROM students WHERE class_name = 'JHS 3') INTO student_ids;
    SELECT ARRAY(SELECT id FROM subjects) INTO subject_ids;

    FOREACH sid IN ARRAY student_ids LOOP
        FOREACH subj_id IN ARRAY subject_ids LOOP
            ct := (RANDOM() * 12 + 8)::NUMERIC(5,2);
            mt := (RANDOM() * 8 + 5)::NUMERIC(5,2);
            et := (RANDOM() * 20 + 35)::NUMERIC(5,2);
            exam := (RANDOM() * 35 + 50)::NUMERIC(5,2);
            i := i + 1;

            INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest)
            SELECT sid, subj_id, term1_id, ct, mt, et, (RANDOM() * 5 + 3)::NUMERIC(5,2), att[1 + (i % 3)], intt[1 + (i % 3)]
            WHERE NOT EXISTS (SELECT 1 FROM sba_scores WHERE student_id = sid AND subject_id = subj_id AND term_id = term1_id);

            INSERT INTO exam_scores (student_id, subject_id, term_id, exam_score)
            SELECT sid, subj_id, term1_id, exam
            WHERE NOT EXISTS (SELECT 1 FROM exam_scores WHERE student_id = sid AND subject_id = subj_id AND term_id = term1_id);
        END LOOP;
    END LOOP;
END $$;

-- ==========================================
-- 16. ATTENDANCE (15 days x 39 students = 585 records)
-- ==========================================
DO $$
DECLARE
    s RECORD;
    d DATE;
    dates DATE[] := ARRAY[
        '2025-11-03','2025-11-04','2025-11-05','2025-11-06','2025-11-07',
        '2025-11-10','2025-11-11','2025-11-12','2025-11-13','2025-11-14',
        '2025-11-17','2025-11-18','2025-11-19','2025-11-20','2025-11-21'
    ];
    r INT;
    cid INT;
BEGIN
    FOR s IN SELECT id, class_name FROM students LOOP
        cid := (SELECT id FROM classes WHERE name = s.class_name LIMIT 1);
        FOREACH d IN ARRAY dates LOOP
            r := (RANDOM() * 10)::INT;
            INSERT INTO student_attendance (student_id, class_id, attendance_date, status, reason)
            SELECT s.id, cid, d,
                CASE WHEN r < 8 THEN 'present' WHEN r < 9 THEN 'late' ELSE 'absent' END,
                CASE WHEN r >= 9 THEN 'Sick' ELSE NULL END
            WHERE NOT EXISTS (SELECT 1 FROM student_attendance WHERE student_id = s.id AND attendance_date = d);
        END LOOP;
    END LOOP;
END $$;

-- ==========================================
-- 17. REPORT CARDS (Basic 1 + JHS 3)
-- ==========================================
DO $$
DECLARE
    sid INT;
    cnt INT;
    pos INT := 0;
    term1_id INT;
BEGIN
    term1_id := (SELECT id FROM terms WHERE name = 'Term 1' AND academic_year = '2025/2026');
    IF term1_id IS NULL THEN RETURN; END IF;

    -- Basic 1 report cards
    cnt := (SELECT COUNT(*) FROM students WHERE class_name = 'Basic 1');
    pos := 0;
    FOR sid IN SELECT id FROM students WHERE class_name = 'Basic 1' ORDER BY full_name LOOP
        pos := pos + 1;
        INSERT INTO report_cards (student_id, term_id, class_position, total_students, class_teacher_remarks, class_teacher_name, headmaster_remarks, headmaster_name, next_term_begins)
        SELECT sid, term1_id, pos, cnt,
            CASE pos WHEN 1 THEN 'An outstanding start to the term.' WHEN 2 THEN 'Very good performance. Keep it up!' ELSE 'A promising start. More effort needed.' END,
            'Ms. Akosua Frimpong',
            CASE pos WHEN 1 THEN 'Excellent work! Maintain this standard.' ELSE 'Good effort. Keep working hard.' END,
            'Mr. Kwabena Asante',
            '2026-01-06'
        WHERE NOT EXISTS (SELECT 1 FROM report_cards WHERE student_id = sid AND term_id = term1_id);
    END LOOP;

    -- JHS 3 report cards
    cnt := (SELECT COUNT(*) FROM students WHERE class_name = 'JHS 3');
    pos := 0;
    FOR sid IN SELECT id FROM students WHERE class_name = 'JHS 3' ORDER BY full_name LOOP
        pos := pos + 1;
        INSERT INTO report_cards (student_id, term_id, class_position, total_students, class_teacher_remarks, class_teacher_name, headmaster_remarks, headmaster_name, next_term_begins)
        SELECT sid, term1_id, pos, cnt,
            CASE pos WHEN 1 THEN 'Exceptional performance this term. Ready for BECE.' WHEN 2 THEN 'Strong academic showing. Keep the momentum.' ELSE 'Steady progress. Push harder for BECE prep.' END,
            'Mrs. Nana Yeboah',
            CASE pos WHEN 1 THEN 'BECE champion in the making!' ELSE 'Focus on BECE preparation.' END,
            'Mr. Kwabena Asante',
            '2026-01-06'
        WHERE NOT EXISTS (SELECT 1 FROM report_cards WHERE student_id = sid AND term_id = term1_id);
    END LOOP;
END $$;

-- ==========================================
-- 18. MESSAGES (admin broadcasts + direct)
-- ==========================================
DO $$
DECLARE
    admin_uid INT;
    student_uid INT;
BEGIN
    admin_uid := (SELECT id FROM users WHERE email = 'admin@necxec.edu.gh');
    IF admin_uid IS NULL THEN RETURN; END IF;

    INSERT INTO messages (sender_id, title, content, is_broadcast, created_at)
    SELECT admin_uid, 'Welcome Back ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Term 1 2025/2026', 'Dear Parents and Guardians, we welcome you to the new academic year. Classes resume on September 8th, 2025. Please ensure all fees are paid before the end of the first week.', true, '2025-09-05'
    WHERE NOT EXISTS (SELECT 1 FROM messages WHERE title = 'Welcome Back ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Term 1 2025/2026');

    INSERT INTO messages (sender_id, title, content, is_broadcast, created_at)
    SELECT admin_uid, 'PTA Meeting Notice', 'Dear Parents, the first PTA meeting for 2025/2026 will be held on September 20th, 2025 at the school assembly hall. Attendance is mandatory.', true, '2025-09-15'
    WHERE NOT EXISTS (SELECT 1 FROM messages WHERE title = 'PTA Meeting Notice');

    INSERT INTO messages (sender_id, title, content, is_broadcast, created_at)
    SELECT admin_uid, 'Mid-Term Examination Schedule', 'Dear Parents, mid-term examinations for Term 1 will commence on October 13th, 2025. Please ensure your wards are well prepared.', true, '2025-10-06'
    WHERE NOT EXISTS (SELECT 1 FROM messages WHERE title = 'Mid-Term Examination Schedule');

    student_uid := (SELECT user_id FROM students WHERE enrollment_id = 'ENR-2025-A1B2C3');
    IF student_uid IS NOT NULL THEN
        INSERT INTO messages (sender_id, receiver_id, title, content, created_at)
        SELECT admin_uid, student_uid, 'Fee Payment Reminder', 'Dear Parent, this is a gentle reminder that school fees for Term 1 are due. Please make payment at your earliest convenience.', '2025-09-20'
        WHERE NOT EXISTS (SELECT 1 FROM messages WHERE sender_id = admin_uid AND receiver_id = student_uid AND title = 'Fee Payment Reminder');
    END IF;
END $$;

-- ==========================================
-- 19. NOTIFICATIONS
-- ==========================================
DO $$
DECLARE
    admin_uid INT;
    bursar_uid INT;
BEGIN
    admin_uid := (SELECT id FROM users WHERE email = 'admin@necxec.edu.gh');
    bursar_uid := (SELECT id FROM users WHERE email = 'bursar@necxec.edu.gh');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT admin_uid, 'New Enrollment', 'A new student has enrolled online with ID ENR-2025-G7H8I9', false, '2025-09-08'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'New Enrollment' AND created_at = '2025-09-08');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT admin_uid, 'Fee Structure Updated', 'Fee structures for 2025/2026 have been set', true, '2025-09-01'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Fee Structure Updated');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT bursar_uid, 'Payment Received', 'New MTN MoMo payment of GHS 745.00 received', false, '2025-09-10'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Payment Received');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT admin_uid, 'Attendance Alert', '3 students absent today', false, '2025-11-05'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Attendance Alert');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT admin_uid, 'Report Cards Ready', 'Report cards for Term 1 are ready for review', true, '2025-12-15'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Report Cards Ready');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT bursar_uid, 'Payroll Ready', 'November 2025 payroll is ready for processing', true, '2025-11-25'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Payroll Ready');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT admin_uid, 'Staff Attendance', '1 staff member was late today', false, '2025-11-10'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Staff Attendance');

    INSERT INTO notifications (user_id, title, message, is_read, created_at)
    SELECT bursar_uid, 'Unpaid Fees Alert', '13 students have unpaid fees for Term 1', false, '2025-09-15'
    WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE title = 'Unpaid Fees Alert');
END $$;

-- ==========================================
-- VERIFICATION
-- ==========================================
SELECT 'users' AS tbl, COUNT(*) AS cnt FROM users
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
