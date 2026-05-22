-- Migration: Create student_bill_items table
-- Enables per-student billing: staff selects which fee items apply to each student
-- Students admitted in the current academic_year (student.academic_year) are "new" → get Admission Fee
-- ALL students get Termly Fee. Other fees are optional (staff picks).
--
-- Run this in Supabase Dashboard SQL Editor before using the billing feature.

CREATE TABLE IF NOT EXISTS student_bill_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    fee_structure_id UUID REFERENCES fee_structures(id) ON DELETE SET NULL,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(10) NOT NULL,
    title VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    fee_type VARCHAR(50) DEFAULT 'General',
    is_optional BOOLEAN DEFAULT false,
    created_by INTEGER REFERENCES staff(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Index for quick lookups
CREATE INDEX IF NOT EXISTS idx_bill_items_lookup
    ON student_bill_items(student_id, academic_year, term);

-- Prevent duplicate fee_structure per student/year/term
CREATE UNIQUE INDEX IF NOT EXISTS idx_bill_items_unique
    ON student_bill_items(student_id, academic_year, term, fee_structure_id)
    WHERE fee_structure_id IS NOT NULL;
