-- ==========================================
-- COMPLETE DATABASE MIGRATION: Nex CEC School Management System
-- Run this ENTIRE script in Supabase SQL Editor
-- Covers ALL 4 phases: Fees, Staff Payroll, Report Cards, Attendance
-- Idempotent: safe to run multiple times
-- ==========================================

-- ==========================================
-- PHASE 1: SCHOOL FEES (Core)
-- ==========================================

-- 1. Recreate classes table with INTEGER id (drop legacy UUID version if exists)
DO $$
DECLARE
    col_type TEXT;
BEGIN
    SELECT data_type INTO col_type
    FROM information_schema.columns
    WHERE table_name = 'classes' AND column_name = 'id';

    IF col_type IS NOT NULL AND col_type != 'integer' THEN
        -- Drop legacy UUID table and all dependent constraints
        DROP TABLE IF EXISTS classes CASCADE;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS classes (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    level_group VARCHAR(20) NOT NULL DEFAULT 'basic',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Ensure unique constraint exists for ON CONFLICT
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'classes_name_key' AND conrelid = 'classes'::regclass) THEN
        ALTER TABLE classes ADD CONSTRAINT classes_name_key UNIQUE (name);
    END IF;
END $$;

-- Seed classes
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
ON CONFLICT (name) DO NOTHING;

-- 2. Add class_name column to students (if not exists)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'class_name') THEN
        ALTER TABLE students ADD COLUMN class_name VARCHAR(50);
    END IF;
END $$;

-- =====================================================
-- Basic School Student Schema (Guardian + Health Info)
-- =====================================================
DO $$
BEGIN
    -- Guardian Details (replaces legacy guardian_name/guardian_phone)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_name') THEN
        ALTER TABLE students ADD COLUMN guardian_name VARCHAR(255);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_email') THEN
        ALTER TABLE students ADD COLUMN guardian_email VARCHAR(255);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_relationship') THEN
        ALTER TABLE students ADD COLUMN guardian_relationship VARCHAR(50);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_phone_primary') THEN
        ALTER TABLE students ADD COLUMN guardian_phone_primary VARCHAR(20);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_phone_emergency') THEN
        ALTER TABLE students ADD COLUMN guardian_phone_emergency VARCHAR(20);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_occupation') THEN
        ALTER TABLE students ADD COLUMN guardian_occupation VARCHAR(100);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'guardian_address') THEN
        ALTER TABLE students ADD COLUMN guardian_address TEXT;
    END IF;

    -- Student Demographics
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'gender') THEN
        ALTER TABLE students ADD COLUMN gender VARCHAR(10);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'date_of_birth') THEN
        ALTER TABLE students ADD COLUMN date_of_birth DATE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'place_of_birth') THEN
        ALTER TABLE students ADD COLUMN place_of_birth VARCHAR(100);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'nationality') THEN
        ALTER TABLE students ADD COLUMN nationality VARCHAR(50) DEFAULT 'Ghanaian';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'hometown') THEN
        ALTER TABLE students ADD COLUMN hometown VARCHAR(100);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'address') THEN
        ALTER TABLE students ADD COLUMN address TEXT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'profile_picture') THEN
        ALTER TABLE students ADD COLUMN profile_picture TEXT;
    END IF;

    -- Health Information
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'health_insurance_id') THEN
        ALTER TABLE students ADD COLUMN health_insurance_id VARCHAR(50);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'blood_group') THEN
        ALTER TABLE students ADD COLUMN blood_group VARCHAR(5);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'genotype') THEN
        ALTER TABLE students ADD COLUMN genotype VARCHAR(10);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'medical_conditions') THEN
        ALTER TABLE students ADD COLUMN medical_conditions TEXT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'allergies') THEN
        ALTER TABLE students ADD COLUMN allergies TEXT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'special_needs') THEN
        ALTER TABLE students ADD COLUMN special_needs TEXT;
    END IF;

    -- Academic Background
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'previous_school') THEN
        ALTER TABLE students ADD COLUMN previous_school VARCHAR(255);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'previous_class') THEN
        ALTER TABLE students ADD COLUMN previous_class VARCHAR(50);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'admission_date') THEN
        ALTER TABLE students ADD COLUMN admission_date DATE DEFAULT CURRENT_DATE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'academic_year') THEN
        ALTER TABLE students ADD COLUMN academic_year VARCHAR(20);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'status') THEN
        ALTER TABLE students ADD COLUMN status VARCHAR(20) DEFAULT 'active';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'enrollment_type') THEN
        ALTER TABLE students ADD COLUMN enrollment_type VARCHAR(20) DEFAULT 'admin';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'enrollment_id') THEN
        ALTER TABLE students ADD COLUMN enrollment_id VARCHAR(20);
    END IF;
    -- Add UNIQUE constraint on enrollment_id (skip if already exists)
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'students_enrollment_id_key' AND conrelid = 'students'::regclass) THEN
        ALTER TABLE students ADD CONSTRAINT students_enrollment_id_key UNIQUE (enrollment_id);
    END IF;
    -- Ensure users.email has unique constraint (for ON CONFLICT)
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_email_key' AND conrelid = 'users'::regclass) THEN
        ALTER TABLE users ADD CONSTRAINT users_email_key UNIQUE (email);
    END IF;
    -- Ensure students.index_number has unique constraint
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'students_index_number_key' AND conrelid = 'students'::regclass) THEN
        ALTER TABLE students ADD CONSTRAINT students_index_number_key UNIQUE (index_number);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'payment_status') THEN
        ALTER TABLE students ADD COLUMN payment_status VARCHAR(20) DEFAULT 'unpaid';
    END IF;
END $$;

-- 3. Update fee_structures columns if needed
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'fee_structures' AND column_name = 'fee_type') THEN
        ALTER TABLE fee_structures ADD COLUMN fee_type VARCHAR(50) NOT NULL DEFAULT 'tuition';
    END IF;
    -- Use INTEGER for class_id to match classes.id
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'fee_structures' AND column_name = 'class_id') THEN
        ALTER TABLE fee_structures ADD COLUMN class_id INTEGER REFERENCES classes(id);
    END IF;
END $$;

-- Seed fee types for basic school
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM fee_structures WHERE title = 'Tuition Fee') THEN
        INSERT INTO fee_structures (title, amount, academic_year, term, fee_type, is_mandatory) VALUES
        ('Tuition Fee', 500.00, '2025/2026', 'Term 1', 'tuition', true),
        ('PTA Dues', 50.00, '2025/2026', 'Term 1', 'pta', true),
        ('Sports Fee', 30.00, '2025/2026', 'Term 1', 'sports', true),
        ('Library Fee', 20.00, '2025/2026', 'Term 1', 'library', true),
        ('ICT Fee', 40.00, '2025/2026', 'Term 1', 'ict', true),
        ('Exam Fee', 25.00, '2025/2026', 'Term 1', 'exam', true),
        ('Uniform', 80.00, '2025/2026', 'Term 1', 'uniform', false);
    END IF;
END $$;

-- 4. Payments Table — recreate with correct columns for PHP bridge
-- Drop legacy UUID version if exists
DO $$
DECLARE
    col_type TEXT;
BEGIN
    SELECT data_type INTO col_type
    FROM information_schema.columns
    WHERE table_name = 'payments' AND column_name = 'id';

    IF col_type IS NOT NULL AND col_type != 'integer' THEN
        DROP TABLE IF EXISTS payments CASCADE;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    amount NUMERIC(10,2) NOT NULL DEFAULT 0,
    academic_year VARCHAR(20),
    semester VARCHAR(20),
    fee_type VARCHAR(50),
    payment_method VARCHAR(50),
    payment_date DATE DEFAULT CURRENT_DATE,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    recorded_by INTEGER REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'completed',
    enrollment_id VARCHAR(20),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 5. Messaging Tables
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER REFERENCES users(id),
    receiver_id INTEGER REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_broadcast BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    read_at TIMESTAMP WITH TIME ZONE
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS message_reads (
    id SERIAL PRIMARY KEY,
    message_id INTEGER REFERENCES messages(id),
    user_id INTEGER REFERENCES users(id),
    read_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(message_id, user_id)
);

-- 5. Executives Table
CREATE TABLE IF NOT EXISTS executives (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    position VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    academic_year VARCHAR(20),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- PHASE 2: STAFF PAYROLL
-- ==========================================

-- Recreate staff table with INTEGER id if legacy UUID version exists
DO $$
DECLARE
    col_type TEXT;
BEGIN
    SELECT data_type INTO col_type
    FROM information_schema.columns
    WHERE table_name = 'staff' AND column_name = 'id';

    IF col_type IS NOT NULL AND col_type != 'integer' THEN
        DROP TABLE IF EXISTS staff CASCADE;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS staff (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    staff_id VARCHAR(50) UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    qualification VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(255),
    date_of_birth DATE,
    gender VARCHAR(10),
    address TEXT,
    hire_date DATE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS salary_structures (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER REFERENCES staff(id),
    basic_salary NUMERIC(10,2) NOT NULL DEFAULT 0,
    housing_allowance NUMERIC(10,2) DEFAULT 0,
    transport_allowance NUMERIC(10,2) DEFAULT 0,
    other_allowances NUMERIC(10,2) DEFAULT 0,
    ssnit_rate NUMERIC(5,2) DEFAULT 13.50,
    tax_rate NUMERIC(5,2) DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS deductions (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER REFERENCES staff(id),
    deduction_type VARCHAR(50) NOT NULL,
    amount NUMERIC(10,2) NOT NULL,
    description TEXT,
    is_recurring BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS payroll (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER REFERENCES staff(id),
    month INTEGER NOT NULL,
    year INTEGER NOT NULL,
    basic_salary NUMERIC(10,2) DEFAULT 0,
    total_allowances NUMERIC(10,2) DEFAULT 0,
    gross_pay NUMERIC(10,2) DEFAULT 0,
    ssnit_deduction NUMERIC(10,2) DEFAULT 0,
    tax_deduction NUMERIC(10,2) DEFAULT 0,
    other_deductions NUMERIC(10,2) DEFAULT 0,
    total_deductions NUMERIC(10,2) DEFAULT 0,
    net_pay NUMERIC(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    pay_date DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS pay_slips (
    id SERIAL PRIMARY KEY,
    payroll_id INTEGER REFERENCES payroll(id),
    pdf_path TEXT,
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- PHASE 3: REPORT CARDS / SBA
-- ==========================================

-- Recreate terms table with INTEGER id if legacy UUID version exists
DO $$
DECLARE
    col_type TEXT;
BEGIN
    SELECT data_type INTO col_type
    FROM information_schema.columns
    WHERE table_name = 'terms' AND column_name = 'id';

    IF col_type IS NOT NULL AND col_type != 'integer' THEN
        DROP TABLE IF EXISTS terms CASCADE;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS terms (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Seed terms
INSERT INTO terms (name, academic_year, is_active) VALUES
('Term 1', '2025/2026', true),
('Term 2', '2025/2026', false),
('Term 3', '2025/2026', false)
ON CONFLICT DO NOTHING;

-- Recreate subjects table with INTEGER id & class_id if legacy UUID version exists
DO $$
DECLARE
    col_type TEXT;
BEGIN
    SELECT data_type INTO col_type
    FROM information_schema.columns
    WHERE table_name = 'subjects' AND column_name = 'id';

    IF col_type IS NOT NULL AND col_type != 'integer' THEN
        DROP TABLE IF EXISTS subjects CASCADE;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS subjects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    class_id INTEGER REFERENCES classes(id),
    teacher_id INTEGER REFERENCES staff(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(name, class_id)
);

-- Seed basic school subjects
INSERT INTO subjects (name, code, class_id) VALUES
('English Language', 'ENG', NULL),
('Mathematics', 'MATH', NULL),
('Integrated Science', 'SCI', NULL),
('Social Studies', 'SST', NULL),
('French', 'FRE', NULL),
('Creative Arts', 'CA', NULL),
('Ghanaian Language', 'GL', NULL),
('Computing', 'COMP', NULL),
('Physical Education', 'PE', NULL),
('Religious and Moral Education', 'RME', NULL),
('Career Technology', 'CT', NULL)
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS grade_boundaries (
    id SERIAL PRIMARY KEY,
    min_score INTEGER NOT NULL,
    max_score INTEGER NOT NULL,
    grade INTEGER,
    remark VARCHAR(50),
    UNIQUE(min_score, max_score)
);

-- Seed Ghana Basic School grading system
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
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS sba_scores (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    subject_id INTEGER REFERENCES subjects(id),
    term_id INTEGER REFERENCES terms(id),
    class_test NUMERIC(5,2) DEFAULT 0,
    mid_term NUMERIC(5,2) DEFAULT 0,
    end_term NUMERIC(5,2) DEFAULT 0,
    project NUMERIC(5,2) DEFAULT 0,
    attitude VARCHAR(10),
    interest VARCHAR(10),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, subject_id, term_id)
);

CREATE TABLE IF NOT EXISTS exam_scores (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    subject_id INTEGER REFERENCES subjects(id),
    term_id INTEGER REFERENCES terms(id),
    exam_score NUMERIC(5,2) DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, subject_id, term_id)
);

CREATE TABLE IF NOT EXISTS report_cards (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    term_id INTEGER REFERENCES terms(id),
    class_position INTEGER,
    total_students INTEGER,
    class_teacher_remarks TEXT,
    class_teacher_name VARCHAR(255),
    headmaster_remarks TEXT,
    headmaster_name VARCHAR(255),
    next_term_begins DATE,
    pdf_path TEXT,
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, term_id)
);

-- ==========================================
-- PHASE 4: ATTENDANCE
-- ==========================================

-- Recreate student_attendance if legacy UUID version exists
DO $$
DECLARE
    col_type TEXT;
BEGIN
    SELECT data_type INTO col_type
    FROM information_schema.columns
    WHERE table_name = 'student_attendance' AND column_name = 'id';

    IF col_type IS NOT NULL AND col_type != 'integer' THEN
        DROP TABLE IF EXISTS student_attendance CASCADE;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS student_attendance (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    class_id INTEGER REFERENCES classes(id),
    attendance_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'present',
    reason TEXT,
    recorded_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS staff_attendance (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER REFERENCES staff(id),
    attendance_date DATE NOT NULL,
    check_in TIMESTAMP WITH TIME ZONE,
    check_out TIMESTAMP WITH TIME ZONE,
    status VARCHAR(20) NOT NULL DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(staff_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS attendance_summary (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    month INTEGER NOT NULL,
    year INTEGER NOT NULL,
    total_school_days INTEGER DEFAULT 0,
    present INTEGER DEFAULT 0,
    absent INTEGER DEFAULT 0,
    late INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, month, year)
);

-- Ensure system_settings exists with unique constraint on setting_key
CREATE TABLE IF NOT EXISTS system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Add school branding settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('school_name', 'Nex CEC Basic School'),
('school_motto', 'Education for Excellence'),
('school_address', 'Kumasi, Ghana'),
('school_phone', '+233 123 456 789'),
('school_email', 'info@necxec.edu.gh'),
('current_term', '1'),
('next_term_begins', '2026-01-06')
ON CONFLICT (setting_key) DO NOTHING;

-- ==========================================
-- VERIFICATION
-- ==========================================
SELECT 'students' AS tbl, COUNT(*) FROM students
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'classes', COUNT(*) FROM classes
UNION ALL SELECT 'staff', COUNT(*) FROM staff
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'fee_structures', COUNT(*) FROM fee_structures
UNION ALL SELECT 'system_settings', COUNT(*) FROM system_settings
UNION ALL SELECT 'terms', COUNT(*) FROM terms
UNION ALL SELECT 'subjects', COUNT(*) FROM subjects
UNION ALL SELECT 'messages', COUNT(*) FROM messages
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications;
