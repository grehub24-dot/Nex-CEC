-- ==========================================
-- Performance Indexes for Nex CEC
-- Run this in Supabase SQL Editor after migrate-all.sql
-- ==========================================

-- 1. payments: speed up student payment history lookups
CREATE INDEX IF NOT EXISTS idx_payments_student_id ON payments(student_id);

-- 2. payments: speed up receipt lookups and verification
CREATE INDEX IF NOT EXISTS idx_payments_receipt_number ON payments(receipt_number);

-- 3. students: speed up class-based filtering (grades, attendance)
CREATE INDEX IF NOT EXISTS idx_students_class_name ON students(class_name);

-- 4. students: speed up status-based filtering (active/pending/rejected)
CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);

-- 5. sba_scores: speed up score lookups by student, subject, and term
CREATE INDEX IF NOT EXISTS idx_sba_scores_student_id ON sba_scores(student_id);
CREATE INDEX IF NOT EXISTS idx_sba_scores_term_id ON sba_scores(term_id);

-- 6. exam_scores: speed up exam score lookups by student, subject, and term
CREATE INDEX IF NOT EXISTS idx_exam_scores_student_id ON exam_scores(student_id);
CREATE INDEX IF NOT EXISTS idx_exam_scores_term_id ON exam_scores(term_id);
