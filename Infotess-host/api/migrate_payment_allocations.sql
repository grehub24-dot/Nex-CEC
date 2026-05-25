-- Migration: Create payment_allocations table
-- Enables tracking which fee items each payment covers.
-- One payment may have multiple allocations (Full Payment = all items, Partial Payment = selected items).
-- Run this in Supabase Dashboard SQL Editor before deploying code changes.

CREATE TABLE IF NOT EXISTS payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    fee_title VARCHAR(200) DEFAULT '',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

-- Index for quick lookup by payment
CREATE INDEX IF NOT EXISTS idx_pa_payment ON payment_allocations(payment_id);

-- Index for financial reporting (income by fee_type)
CREATE INDEX IF NOT EXISTS idx_pa_fee_type ON payment_allocations(fee_type);
