-- ============================================================
-- Migration: Deduplicate payments by receipt_number + add UNIQUE
-- ============================================================
-- Run this in your Supabase Dashboard SQL Editor:
--   https://supabase.com/dashboard/project/tbkinaglugagloinecle/sql/new
-- ============================================================

-- Step 1: Remove duplicate receipt rows, keeping the oldest (lowest id)
DELETE FROM payments a
USING payments b
WHERE a.id > b.id
  AND a.receipt_number IS NOT NULL
  AND a.receipt_number = b.receipt_number;

-- Step 2: Add UNIQUE constraint to prevent future duplicates
ALTER TABLE payments
  ADD CONSTRAINT payments_receipt_number_key UNIQUE (receipt_number);
