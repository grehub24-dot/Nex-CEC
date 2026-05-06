-- ==========================================
-- MIGRATION: Create Missing Tables for INFOTESS SDMS
-- Run this ENTIRE script in Supabase SQL Editor
-- ==========================================
-- NOTE: payments, system_settings, students, users already exist — skipped here.
-- ==========================================

-- 1. Fee Structures
CREATE TABLE IF NOT EXISTS fee_structures (
    id SERIAL PRIMARY KEY,
    level VARCHAR(10) NOT NULL DEFAULT '100',
    department VARCHAR(100),
    amount NUMERIC(10,2) NOT NULL DEFAULT 0,
    academic_year VARCHAR(20) NOT NULL DEFAULT '2025/2026',
    semester VARCHAR(20) NOT NULL DEFAULT '1',
    fee_type VARCHAR(50) NOT NULL DEFAULT 'dues',
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 2. Messages (Admin → Student communication)
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER REFERENCES users(id),
    receiver_id INTEGER REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_broadcast BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    read_at TIMESTAMP WITH TIME ZONE
);

-- 3. Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 4. Message Read Receipts
CREATE TABLE IF NOT EXISTS message_reads (
    id SERIAL PRIMARY KEY,
    message_id INTEGER REFERENCES messages(id),
    user_id INTEGER REFERENCES users(id),
    read_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(message_id, user_id)
);

-- 5. Executives
CREATE TABLE IF NOT EXISTS executives (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    position VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    academic_year VARCHAR(20),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- SEED DATA
-- ==========================================

-- Fee structures for each level
INSERT INTO fee_structures (level, amount, academic_year, semester, fee_type, description) VALUES
('100', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues'),
('200', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues'),
('300', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues'),
('400', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues')
ON CONFLICT DO NOTHING;

-- Bursar user (for recording payments)
INSERT INTO users (email, password, role, status, is_password_reset)
VALUES ('bursar@infotess.org', '$2y$10$YRNoSKY.hpBVI8PGmwOMNOZAmYXoAIzsnr0Py0vqoHiERUihByEkq', 'bursar', 'active', true)
ON CONFLICT (email) DO NOTHING;

-- Test payments (only if receipt_number doesn't conflict)
-- Uses the actual payments schema: student_id, amount, academic_year, semester, payment_method, payment_date, receipt_number, recorded_by
INSERT INTO payments (student_id, amount, academic_year, semester, payment_method, payment_date, receipt_number, recorded_by) VALUES
(1, 100.00, '2025/2026', 'All', 'Cash', '2026-05-01', 'RCP-2026-001', 2),
(1, 50.00,  '2025/2026', 'All', 'Mobile Money', '2026-05-01', 'RCP-2026-003', 2),
(2, 100.00, '2025/2026', 'All', 'Cash', '2026-05-02', 'RCP-2026-004', 2),
(4, 75.00,  '2025/2026', '1', 'Mobile Money', '2026-05-03', 'RCP-2026-005', 2),
(4, 30.00,  '2025/2026', '2', 'Cash', '2026-05-03', 'RCP-2026-008', 2)
ON CONFLICT (receipt_number) DO NOTHING;

-- ==========================================
-- VERIFICATION
-- ==========================================
SELECT 'students' AS tbl, COUNT(*) FROM students
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'fee_structures', COUNT(*) FROM fee_structures
UNION ALL SELECT 'system_settings', COUNT(*) FROM system_settings
UNION ALL SELECT 'messages', COUNT(*) FROM messages
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications;
