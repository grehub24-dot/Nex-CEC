-- Migration: Add fee_exemptions table for tracking exempted students
-- Run this in Supabase Dashboard SQL Editor

CREATE TABLE IF NOT EXISTS fee_exemptions (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(10),
    reason TEXT DEFAULT '',
    exempted_by INTEGER REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(student_id, academic_year, COALESCE(term, '_all_'))
);

-- Index for faster lookups
CREATE INDEX IF NOT EXISTS idx_fee_exemptions_student ON fee_exemptions(student_id);
CREATE INDEX IF NOT EXISTS idx_fee_exemptions_year_term ON fee_exemptions(academic_year, term);
