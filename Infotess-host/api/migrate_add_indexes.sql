-- Migration: Add performance indexes for billing/fee queries
-- Issue 2 from code review: students.class_name is used in WHERE clauses
-- across admin_fees_debt.php, admin_class_billing.php, admin_student_billing.php
-- Without an index, these become sequential scans as student count grows.

CREATE INDEX IF NOT EXISTS idx_students_class_name ON students(class_name);

-- Additional useful indexes for billing queries
CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);
CREATE INDEX IF NOT EXISTS idx_student_bill_items_student_year_term ON student_bill_items(student_id, academic_year, term);
CREATE INDEX IF NOT EXISTS idx_payments_student_year_term ON payments(student_id, academic_year, term);
CREATE INDEX IF NOT EXISTS idx_fee_structures_year_term ON fee_structures(academic_year, term);
CREATE INDEX IF NOT EXISTS idx_fee_exemptions_student_year ON fee_exemptions(student_id, academic_year);
