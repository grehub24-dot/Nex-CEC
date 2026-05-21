-- ============================================================
-- Migration: Add transaction_reference column to payments table
-- ============================================================
-- Run this in your Supabase Dashboard SQL Editor:
--   https://supabase.com/dashboard/project/tbkinaglugagloinecle/sql/new
-- ============================================================

ALTER TABLE payments ADD COLUMN IF NOT EXISTS transaction_reference VARCHAR(255) DEFAULT NULL;
