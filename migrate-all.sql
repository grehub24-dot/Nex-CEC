-- ==========================================
-- COMPLETE DATABASE MIGRATION: Nex CEC School Management System
-- Run this ENTIRE script in Supabase SQL Editor
-- Idempotent: safe to run multiple times
-- ==========================================

-- ==========================================
-- IMPORTANT: Ensure admission_number + enrollment_id exist in students table FIRST.
-- Uses ADD COLUMN IF NOT EXISTS so it's safe to run even if already present.
-- Also DROP any legacy index_number column to avoid NOT NULL conflicts.
-- index_number is replaced by admission_number in the Basic School schema.
ALTER TABLE students DROP COLUMN IF EXISTS index_number CASCADE;
ALTER TABLE students ADD COLUMN IF NOT EXISTS admission_number VARCHAR(50);
ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_id VARCHAR(20);

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
('Nursery 1', 'early_childhood', 1),
('Nursery 2', 'early_childhood', 2),
('KG 1', 'early_childhood', 3),
('KG 2', 'early_childhood', 4),
('Basic 1', 'primary', 5),
('Basic 2', 'primary', 6),
('Basic 3', 'primary', 7),
('Basic 4', 'primary', 8),
('Basic 5', 'primary', 9),
('Basic 6', 'primary', 10),
('JHS 1', 'jhs', 11),
('JHS 2', 'jhs', 12),
('JHS 3', 'jhs', 13)
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
    -- Guardian Details
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
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'payment_status') THEN
        ALTER TABLE students ADD COLUMN payment_status VARCHAR(20) DEFAULT 'unpaid';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'students' AND column_name = 'enrollment_type') THEN
        ALTER TABLE students ADD COLUMN enrollment_type VARCHAR(20) DEFAULT 'online';
    END IF;
END $$;

-- ==========================================
-- CRITICAL: Force-add ALL missing unique constraints
-- These work even if table already exists (won't error on existing constraints)
-- ==========================================
DO $$
BEGIN
    -- students table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'students_admission_number_key' AND conrelid = 'students'::regclass) THEN
        DELETE FROM students a USING students b
            WHERE a.id > b.id AND a.admission_number IS NOT NULL AND a.admission_number = b.admission_number;
        ALTER TABLE students ADD CONSTRAINT students_admission_number_key UNIQUE (admission_number);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'students_enrollment_id_key' AND conrelid = 'students'::regclass) THEN
        ALTER TABLE students ADD CONSTRAINT students_enrollment_id_key UNIQUE (enrollment_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'students_user_id_key' AND conrelid = 'students'::regclass) THEN
        DELETE FROM students a USING students b
            WHERE a.id > b.id AND a.user_id IS NOT NULL AND a.user_id = b.user_id;
        ALTER TABLE students ADD CONSTRAINT students_user_id_key UNIQUE (user_id);
    END IF;
    
    -- users table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_email_key' AND conrelid = 'users'::regclass) THEN
        DELETE FROM users a USING users b
            WHERE a.id > b.id AND a.email IS NOT NULL AND a.email = b.email;
        ALTER TABLE users ADD CONSTRAINT users_email_key UNIQUE (email);
    END IF;
    
    -- payments: unique constraint already defined in CREATE TABLE above
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_staff_id_key' AND conrelid = 'staff'::regclass) THEN
        DELETE FROM staff a USING staff b
            WHERE a.id > b.id AND a.staff_id IS NOT NULL AND a.staff_id = b.staff_id;
        ALTER TABLE staff ADD CONSTRAINT staff_staff_id_key UNIQUE (staff_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_email_key' AND conrelid = 'staff'::regclass) THEN
        DELETE FROM staff a USING staff b
            WHERE a.id > b.id AND a.email IS NOT NULL AND a.email = b.email;
        ALTER TABLE staff ADD CONSTRAINT staff_email_key UNIQUE (email);
    END IF;
    
    -- system_settings table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'system_settings_setting_key_key' AND conrelid = 'system_settings'::regclass) THEN
        DELETE FROM system_settings a USING system_settings b
            WHERE a.id > b.id AND a.setting_key IS NOT NULL AND a.setting_key = b.setting_key;
        ALTER TABLE system_settings ADD CONSTRAINT system_settings_setting_key_key UNIQUE (setting_key);
    END IF;
    
    -- terms table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'terms_name_academic_year_key' AND conrelid = 'terms'::regclass) THEN
        -- Check for legacy constraint name
        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'terms_academic_year_name_key' AND conrelid = 'terms'::regclass) THEN
            -- Skip, legacy name exists; no action needed
        ELSE
            -- Fix duplicates in terms first (keep first occurrence by id)
            DELETE FROM terms a USING terms b
                WHERE a.id > b.id
                AND a.academic_year = b.academic_year
                AND a.name = b.name;
            -- Now safe to add constraint
            ALTER TABLE terms ADD CONSTRAINT terms_name_academic_year_key UNIQUE (name, academic_year);
        END IF;
    END IF;
    
    -- subjects table: ensure proper unique indexes exist
    -- (replaces the old UNIQUE(name, class_id) which allowed NULL duplicates)
    -- Drop the old flawed constraint if it still exists
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'subjects_name_class_id_key' AND conrelid = 'subjects'::regclass) THEN
        ALTER TABLE subjects DROP CONSTRAINT subjects_name_class_id_key;
    END IF;
    -- Create or verify partial unique indexes
    CREATE UNIQUE INDEX IF NOT EXISTS subjects_name_unique_null_class_id 
        ON subjects(name) WHERE class_id IS NULL;
    CREATE UNIQUE INDEX IF NOT EXISTS subjects_name_class_id_unique_not_null 
        ON subjects(name, class_id) WHERE class_id IS NOT NULL;
    
    -- sba_scores table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sba_scores_student_subject_term_key' AND conrelid = 'sba_scores'::regclass) THEN
        ALTER TABLE sba_scores ADD CONSTRAINT sba_scores_student_subject_term_key UNIQUE (student_id, subject_id, term_id);
    END IF;
    
    -- exam_scores table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'exam_scores_student_subject_term_key' AND conrelid = 'exam_scores'::regclass) THEN
        ALTER TABLE exam_scores ADD CONSTRAINT exam_scores_student_subject_term_key UNIQUE (student_id, subject_id, term_id);
    END IF;
    
    -- report_cards table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'report_cards_student_term_key' AND conrelid = 'report_cards'::regclass) THEN
        ALTER TABLE report_cards ADD CONSTRAINT report_cards_student_term_key UNIQUE (student_id, term_id);
    END IF;
    
    -- student_attendance table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'student_attendance_student_date_key' AND conrelid = 'student_attendance'::regclass) THEN
        -- Check for legacy constraint name
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'student_attendance_student_attendance_date_key' AND conrelid = 'student_attendance'::regclass) THEN
            ALTER TABLE student_attendance ADD CONSTRAINT student_attendance_student_date_key UNIQUE (student_id, attendance_date);
        END IF;
    END IF;
    
    -- staff_attendance table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_attendance_staff_date_key' AND conrelid = 'staff_attendance'::regclass) THEN
        -- Check for legacy constraint name
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_attendance_staff_attendance_date_key' AND conrelid = 'staff_attendance'::regclass) THEN
            ALTER TABLE staff_attendance ADD CONSTRAINT staff_attendance_staff_date_key UNIQUE (staff_id, attendance_date);
        END IF;
    END IF;
    
    -- message_reads table
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'message_reads_message_user_key' AND conrelid = 'message_reads'::regclass) THEN
        ALTER TABLE message_reads ADD CONSTRAINT message_reads_message_user_key UNIQUE (message_id, user_id);
    END IF;
END $$;

-- 3. Update fee_structures columns if needed
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'fee_structures' AND column_name = 'fee_type') THEN
        ALTER TABLE fee_structures ADD COLUMN fee_type VARCHAR(50) NOT NULL DEFAULT 'school_fees';
    END IF;
    -- Drop any legacy UUID class_id and replace with INTEGER to match classes.id
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'fee_structures' AND column_name = 'class_id') THEN
        ALTER TABLE fee_structures DROP COLUMN IF EXISTS class_id;
    END IF;
    ALTER TABLE fee_structures ADD COLUMN class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE;
END $$;

-- Fee structures are seeded per-class in seed-data.sql (section 14).
-- This block just ensures the columns exist — handled above.

-- Rename legacy semester column to term (idempotent)
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'payments' AND column_name = 'semester'
    ) THEN
        ALTER TABLE payments RENAME COLUMN semester TO term;
    END IF;
END $$;

-- 4. Payments Table — drop and recreate to ensure correct schema + unique constraint
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
    term VARCHAR(20),
    fee_type VARCHAR(50),
    payment_method VARCHAR(50),
    payment_date DATE DEFAULT CURRENT_DATE,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    recorded_by INTEGER REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'completed',
    enrollment_id VARCHAR(20),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 5. Messaging Tables
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_broadcast BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    read_at TIMESTAMP WITH TIME ZONE
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS message_reads (
    id SERIAL PRIMARY KEY,
    message_id INTEGER REFERENCES messages(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    read_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(message_id, user_id)
);

-- 5. Executives Table
CREATE TABLE IF NOT EXISTS executives (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
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
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
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
    staff_id INTEGER REFERENCES staff(id) ON DELETE CASCADE,
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
    staff_id INTEGER REFERENCES staff(id) ON DELETE CASCADE,
    deduction_type VARCHAR(50) NOT NULL,
    amount NUMERIC(10,2) NOT NULL,
    description TEXT,
    is_recurring BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS payroll (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER REFERENCES staff(id) ON DELETE CASCADE,
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
    payroll_id INTEGER REFERENCES payroll(id) ON DELETE CASCADE,
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
ON CONFLICT ON CONSTRAINT terms_academic_year_name_key DO NOTHING;

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
    class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE,
    teacher_id INTEGER REFERENCES staff(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS subjects_name_unique_null_class_id 
    ON subjects(name) WHERE class_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS subjects_name_class_id_unique_not_null 
    ON subjects(name, class_id) WHERE class_id IS NOT NULL;

-- Seed basic school subjects (NULL class_id = global/uncategorized)
INSERT INTO subjects (name, code, class_id) VALUES
('English Language', 'ENG', NULL),
('Mathematics', 'MATH', NULL),
('Science', 'SCI', NULL),
('Social Studies', 'SST', NULL),
('French', 'FRE', NULL),
('Creative Arts', 'CA', NULL),
('Ghanaian Language', 'GL', NULL),
('Computing', 'COMP', NULL),
('Physical Education', 'PE', NULL),
('Religious and Moral Education', 'RME', NULL),
('Career Technology', 'CT', NULL)
ON CONFLICT (name) WHERE class_id IS NULL DO NOTHING;

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
    subject_id INTEGER REFERENCES subjects(id) ON DELETE CASCADE,
    term_id INTEGER REFERENCES terms(id) ON DELETE CASCADE,
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
    subject_id INTEGER REFERENCES subjects(id) ON DELETE CASCADE,
    term_id INTEGER REFERENCES terms(id) ON DELETE CASCADE,
    exam_score NUMERIC(5,2) DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, subject_id, term_id)
);

CREATE TABLE IF NOT EXISTS report_cards (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    term_id INTEGER REFERENCES terms(id) ON DELETE CASCADE,
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
    class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE,
    attendance_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'present',
    reason TEXT,
    recorded_by INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS staff_attendance (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER REFERENCES staff(id) ON DELETE CASCADE,
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
('school_motto', 'Education for Excellence and Character'),
('school_address', '12 Education Ridge, Kumasi, Ghana'),
('school_phone', '+233 32 277 0000'),
('school_email', 'info@necxec.edu.gh'),
('current_term', '1'),
('next_term_begins', '2026-01-06'),
('school_logo', 'images/school-logo.png')
ON CONFLICT ON CONSTRAINT system_settings_setting_key_key DO NOTHING;

-- ==========================================
-- PHASE 5: PARENT-STUDENT RELATIONSHIP
-- ==========================================

-- Create parent_students junction table for one-to-many parent-to-student
CREATE TABLE IF NOT EXISTS parent_students (
    id SERIAL PRIMARY KEY,
    parent_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    relationship VARCHAR(50),
    is_primary BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(parent_user_id, student_id)
);

-- Drop the UNIQUE constraint on students.user_id to allow one parent to have multiple children
-- We use a DO block to make it idempotent
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'students_user_id_key' AND conrelid = 'students'::regclass) THEN
        ALTER TABLE students DROP CONSTRAINT students_user_id_key;
    END IF;
END $$;

-- Create an index on students.user_id (non-unique) for fast lookups
CREATE INDEX IF NOT EXISTS idx_students_user_id ON students(user_id);

-- Create an index on parent_students for fast queries
CREATE INDEX IF NOT EXISTS idx_parent_students_parent ON parent_students(parent_user_id);
CREATE INDEX IF NOT EXISTS idx_parent_students_student ON parent_students(student_id);

-- ==========================================
-- PHASE 6: DATABASE-BACKED SESSIONS
-- ==========================================

-- Sessions table for database-backed PHP sessions (Vercel-compatible)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT NOT NULL DEFAULT '',
    last_accessed TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Auto-cleanup expired sessions (runs hourly)
CREATE INDEX IF NOT EXISTS idx_sessions_last_accessed ON sessions(last_accessed);

-- ==========================================
-- PHASE 7: CONTENT & RESOURCE TABLES
-- ==========================================

-- Events table (public-facing events page)
CREATE TABLE IF NOT EXISTS events (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date TIMESTAMP WITH TIME ZONE NOT NULL,
    location VARCHAR(255),
    source_url TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- News table (public-facing news/updates page)
CREATE TABLE IF NOT EXISTS news (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    source_url TEXT UNIQUE,
    image_url TEXT,
    published_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Resources table (public-facing learning resources page)
CREATE TABLE IF NOT EXISTS resources (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_url TEXT NOT NULL,
    category VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Student resources table (dashboard/internal resources)
CREATE TABLE IF NOT EXISTS student_resources (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_url TEXT NOT NULL,
    resource_type VARCHAR(50),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Receipts table (payment receipts with verification)
CREATE TABLE IF NOT EXISTS receipts (
    id SERIAL PRIMARY KEY,
    payment_id INTEGER NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
    receipt_file_path TEXT,
    verification_hash VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Indexes for content tables
CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_receipts_payment_id ON receipts(payment_id);

-- ==========================================
-- VERIFICATION
-- ==========================================
SELECT 'students' AS tbl, COUNT(*) FROM students
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'classes', COUNT(*) FROM classes
UNION ALL SELECT 'staff', COUNT(*) FROM staff
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'receipts', COUNT(*) FROM receipts
UNION ALL SELECT 'fee_structures', COUNT(*) FROM fee_structures
UNION ALL SELECT 'system_settings', COUNT(*) FROM system_settings
UNION ALL SELECT 'terms', COUNT(*) FROM terms
UNION ALL SELECT 'subjects', COUNT(*) FROM subjects
UNION ALL SELECT 'messages', COUNT(*) FROM messages
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'parent_students', COUNT(*) FROM parent_students
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'news', COUNT(*) FROM news
UNION ALL SELECT 'resources', COUNT(*) FROM resources
UNION ALL SELECT 'student_resources', COUNT(*) FROM student_resources
UNION ALL SELECT 'sessions', COUNT(*) FROM sessions;

-- ==========================================
-- PHASE 6: Staff Self-Registration (Invite System)
-- ==========================================

-- Add columns to staff table for profile/document uploads
ALTER TABLE staff ADD COLUMN IF NOT EXISTS profile_picture TEXT;
ALTER TABLE staff ADD COLUMN IF NOT EXISTS cv_path TEXT;
ALTER TABLE staff ADD COLUMN IF NOT EXISTS documents TEXT; -- JSON array of uploaded doc URLs

-- Create staff_invites table for invitation tokens
CREATE TABLE IF NOT EXISTS staff_invites (
    id SERIAL PRIMARY KEY,
    staff_id INTEGER NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) UNIQUE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    invited_by INTEGER REFERENCES users(id) ON DELETE CASCADE,
    invited_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    accepted_at TIMESTAMP WITH TIME ZONE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE
);

-- Index for fast token lookups
CREATE INDEX IF NOT EXISTS idx_staff_invites_token ON staff_invites(token);
CREATE INDEX IF NOT EXISTS idx_staff_invites_staff_id ON staff_invites(staff_id);

-- ============================================================
-- FIX SCHEMA-CODE MISMATCHES (idempotent)
-- ============================================================

-- 1. Add 'semester' alias column for payments (code uses it)
ALTER TABLE IF EXISTS public.payments ADD COLUMN IF NOT EXISTS semester character varying;
UPDATE public.payments SET semester = term WHERE term IS NOT NULL;

-- 2. Add 'caption' alias column for gallery (code uses it)
ALTER TABLE IF EXISTS public.gallery ADD COLUMN IF NOT EXISTS caption character varying;

-- 3. Create missing enrollment_inquiries table
CREATE TABLE IF NOT EXISTS public.enrollment_inquiries (
    id integer NOT NULL GENERATED BY DEFAULT AS IDENTITY,
    parent_name character varying NOT NULL,
    email character varying NOT NULL,
    phone character varying,
    child_name character varying,
    class_applying character varying,
    message text,
    created_at timestamp with time zone DEFAULT now(),
    CONSTRAINT enrollment_inquiries_pkey PRIMARY KEY (id)
);

-- 4. Add profile_picture to users (code references it)
ALTER TABLE IF EXISTS public.users ADD COLUMN IF NOT EXISTS profile_picture text;

-- 5. Safer UNIQUE constraints (optional, PG <14 compatible)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'subjects_name_class_key') THEN
        ALTER TABLE public.subjects ADD CONSTRAINT subjects_name_class_key UNIQUE (name, class_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'payroll_staff_month_year_key') THEN
        ALTER TABLE public.payroll ADD CONSTRAINT payroll_staff_month_year_key UNIQUE (staff_id, month, year);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_attendance_staff_date_key') THEN
        ALTER TABLE public.staff_attendance ADD CONSTRAINT staff_attendance_staff_date_key UNIQUE (staff_id, attendance_date);
    END IF;
END $$;

-- ============================================================
-- CLEANUP: Remove university remnants for basic school system
-- ============================================================

-- 6. Drop dead columns from students (university artifacts, never used by PHP code)
ALTER TABLE IF EXISTS public.students DROP COLUMN IF EXISTS department;
ALTER TABLE IF EXISTS public.students DROP COLUMN IF EXISTS level;

-- 7. Fix fee_type default (university 'tuition' → basic school 'school_fees')
ALTER TABLE IF EXISTS public.fee_structures 
    ALTER COLUMN fee_type SET DEFAULT 'school_fees';
