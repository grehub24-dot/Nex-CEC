-- ==========================================
-- DATABASE CONSTRAINT FIXES
-- ==========================================
-- 
-- Run this ENTIRE script in Supabase SQL Editor (click "Run").
-- It's safe to run multiple times (uses IF EXISTS checks).
-- ==========================================

-- ==========================================
-- 1. FIX subjects UNIQUE constraint
-- ==========================================
-- PROBLEM: UNIQUE(name, class_id) allows duplicate name when class_id IS NULL
-- because PostgreSQL treats multiple NULLs as NOT equal in unique constraints.
--
-- FIX: Drop old constraint, create partial unique indexes.
-- ==========================================
ALTER TABLE subjects DROP CONSTRAINT IF EXISTS subjects_name_class_id_key;

DROP INDEX IF EXISTS subjects_name_unique_null_class_id;
CREATE UNIQUE INDEX subjects_name_unique_null_class_id 
    ON subjects(name) WHERE class_id IS NULL;

DROP INDEX IF EXISTS subjects_name_class_id_unique_not_null;
CREATE UNIQUE INDEX subjects_name_class_id_unique_not_null 
    ON subjects(name, class_id) WHERE class_id IS NOT NULL;

-- ==========================================
-- 2. Add UNIQUE constraint on sba_scores
-- ==========================================
-- Prevents duplicate score entries for the same student/subject/term.
-- NOTE: PostgreSQL does NOT support "IF NOT EXISTS" for ADD CONSTRAINT,
-- so we use a DO block to check pg_constraint first.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sba_scores_student_subject_term_key' AND conrelid = 'sba_scores'::regclass) THEN
        ALTER TABLE sba_scores ADD CONSTRAINT sba_scores_student_subject_term_key UNIQUE (student_id, subject_id, term_id);
    END IF;
END $$;

-- ==========================================
-- 3. Add UNIQUE constraint on exam_scores
-- ==========================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'exam_scores_student_subject_term_key' AND conrelid = 'exam_scores'::regclass) THEN
        ALTER TABLE exam_scores ADD CONSTRAINT exam_scores_student_subject_term_key UNIQUE (student_id, subject_id, term_id);
    END IF;
END $$;

-- ==========================================
-- 4. Add UNIQUE constraint on report_cards
-- ==========================================
-- Ensures only one report card per student per term.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'report_cards_student_term_key' AND conrelid = 'report_cards'::regclass) THEN
        ALTER TABLE report_cards ADD CONSTRAINT report_cards_student_term_key UNIQUE (student_id, term_id);
    END IF;
END $$;

-- ==========================================
-- 5. Add UNIQUE constraint on student_attendance
-- ==========================================
-- Ensures one attendance record per student per day.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'student_attendance_student_attendance_date_key' AND conrelid = 'student_attendance'::regclass) THEN
        ALTER TABLE student_attendance ADD CONSTRAINT student_attendance_student_attendance_date_key UNIQUE (student_id, attendance_date);
    END IF;
END $$;

-- ==========================================
-- 6. Add UNIQUE constraint on staff_attendance
-- ==========================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_attendance_staff_attendance_date_key' AND conrelid = 'staff_attendance'::regclass) THEN
        ALTER TABLE staff_attendance ADD CONSTRAINT staff_attendance_staff_attendance_date_key UNIQUE (staff_id, attendance_date);
    END IF;
END $$;

-- ==========================================
-- 7. Add UNIQUE constraint on message_reads
-- ==========================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'message_reads_message_user_key' AND conrelid = 'message_reads'::regclass) THEN
        ALTER TABLE message_reads ADD CONSTRAINT message_reads_message_user_key UNIQUE (message_id, user_id);
    END IF;
END $$;

-- ==========================================
-- VERIFICATION QUERIES
-- ==========================================
SELECT '=== Constraint fixes applied ===' AS result;

-- Show subjects indexes
SELECT indexname, indexdef 
FROM pg_indexes 
WHERE tablename = 'subjects' 
  AND indexname LIKE 'subjects_name%';

-- Show all added constraints
SELECT conname AS constraint_name, conrelid::regclass AS table_name
FROM pg_constraint
WHERE conname IN (
    'sba_scores_student_subject_term_key',
    'exam_scores_student_subject_term_key',
    'report_cards_student_term_key',
    'student_attendance_student_attendance_date_key',
    'staff_attendance_staff_attendance_date_key',
    'message_reads_message_user_key'
)
ORDER BY conname;
