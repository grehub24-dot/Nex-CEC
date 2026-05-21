-- ============================================================
-- CLEANUP: Remove university remnants for basic school system
-- Run this AFTER fix-schema-mismatches.sql
-- ============================================================

-- 1. Drop dead columns from students (university artifacts, never used by PHP code)
ALTER TABLE IF EXISTS public.students DROP COLUMN IF EXISTS department;
ALTER TABLE IF EXISTS public.students DROP COLUMN IF EXISTS level;

-- 2. Fix fee_type default (university terminology → basic school)
ALTER TABLE IF EXISTS public.fee_structures 
    ALTER COLUMN fee_type SET DEFAULT 'school_fees';
