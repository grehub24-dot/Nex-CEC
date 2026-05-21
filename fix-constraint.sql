-- ==========================================
-- DATABASE CONSTRAINT FIXES
-- ==========================================
-- 
-- Run this ENTIRE script in Supabase SQL Editor (click "Run").
-- It's safe to run multiple times (uses IF EXISTS checks).
-- ==========================================

-- ==========================================
-- 0. FIX subjects.teacher_id ON DELETE behavior
-- ==========================================
-- PROBLEM: Deleting a staff member fails if they are assigned as teacher
-- to any subject, because subjects_teacher_id_fkey has no ON DELETE
-- behavior (defaults to NO ACTION).
--
-- FIX: Drop and recreate the FK with ON DELETE SET NULL so the database
-- automatically nullifies teacher_id when the referenced staff is deleted.
-- ==========================================
DO $fix_subject_fk$
BEGIN
    -- Drop the existing FK constraint if it exists
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'subjects_teacher_id_fkey' AND conrelid = 'subjects'::regclass) THEN
        ALTER TABLE subjects DROP CONSTRAINT subjects_teacher_id_fkey;
    END IF;

    -- Check if the constraint already has ON DELETE SET NULL (skip if so)
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'subjects_teacher_id_fkey' 
          AND conrelid = 'subjects'::regclass
          AND confdeltype = 'n'
    ) THEN
        ALTER TABLE subjects ADD CONSTRAINT subjects_teacher_id_fkey 
            FOREIGN KEY (teacher_id) REFERENCES staff(id) ON DELETE SET NULL;
    END IF;
END $fix_subject_fk$;

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
-- 2-7. Add UNIQUE constraints on remaining tables
-- ==========================================
-- NOTE: PostgreSQL does NOT support "IF NOT EXISTS" for ADD CONSTRAINT,
-- so we use a single DO block with named dollar-quoting to safely
-- check pg_constraint before adding each constraint.
-- ==========================================
DO $fix$
BEGIN
    -- sba_scores: prevents duplicate score entries for same student/subject/term
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'sba_scores_student_subject_term_key' AND conrelid = 'sba_scores'::regclass) THEN
        ALTER TABLE sba_scores ADD CONSTRAINT sba_scores_student_subject_term_key UNIQUE (student_id, subject_id, term_id);
    END IF;

    -- exam_scores
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'exam_scores_student_subject_term_key' AND conrelid = 'exam_scores'::regclass) THEN
        ALTER TABLE exam_scores ADD CONSTRAINT exam_scores_student_subject_term_key UNIQUE (student_id, subject_id, term_id);
    END IF;

    -- report_cards: only one report card per student per term
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'report_cards_student_term_key' AND conrelid = 'report_cards'::regclass) THEN
        ALTER TABLE report_cards ADD CONSTRAINT report_cards_student_term_key UNIQUE (student_id, term_id);
    END IF;

    -- student_attendance: one attendance record per student per day
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'student_attendance_student_attendance_date_key' AND conrelid = 'student_attendance'::regclass) THEN
        ALTER TABLE student_attendance ADD CONSTRAINT student_attendance_student_attendance_date_key UNIQUE (student_id, attendance_date);
    END IF;

    -- staff_attendance
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'staff_attendance_staff_attendance_date_key' AND conrelid = 'staff_attendance'::regclass) THEN
        ALTER TABLE staff_attendance ADD CONSTRAINT staff_attendance_staff_attendance_date_key UNIQUE (staff_id, attendance_date);
    END IF;

    -- message_reads
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'message_reads_message_user_key' AND conrelid = 'message_reads'::regclass) THEN
        ALTER TABLE message_reads ADD CONSTRAINT message_reads_message_user_key UNIQUE (message_id, user_id);
    END IF;
END $fix$;

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
