-- =========================================================
-- Migration: 20250525_create_class_teachers_table
-- Purpose: Link a staff member (Class Teacher) directly to a class
--          so getTeacherClassIds() can find them.
-- Run in: Supabase Dashboard SQL Editor
-- =========================================================

BEGIN;

-- Create the table. Each Class Teacher can be assigned to
-- one primary class (UNIQUE on staff_id).
CREATE TABLE IF NOT EXISTS class_teachers (
    id         BIGSERIAL    PRIMARY KEY,
    staff_id   BIGINT       NOT NULL UNIQUE REFERENCES staff(id) ON DELETE CASCADE,
    class_id   BIGINT       NOT NULL       REFERENCES classes(id) ON DELETE CASCADE,
    assigned_at TIMESTAMPTZ DEFAULT NOW()
);

-- Index for fast lookups by class (e.g. find who the class teacher is)
CREATE INDEX IF NOT EXISTS idx_class_teachers_class ON class_teachers(class_id);

-- Index for staff → class lookups (the UNIQUE constraint already creates one,
-- but naming it explicitly helps with clarity)
CREATE INDEX IF NOT EXISTS idx_class_teachers_staff ON class_teachers(staff_id);

COMMIT;
