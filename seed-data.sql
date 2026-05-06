-- ==========================================
-- MIGRATION + SEED DATA: INFOTESS SDMS
-- Run this ENTIRE script in Supabase SQL Editor
-- ==========================================

-- 1. Ensure extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 2. Create missing tables
CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(id),
    amount NUMERIC(10,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'manual',
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    payment_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    academic_year VARCHAR(20) NOT NULL DEFAULT '2025/2026',
    semester VARCHAR(20) NOT NULL DEFAULT '1',
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    reference VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

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

CREATE TABLE IF NOT EXISTS system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

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

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS message_reads (
    id SERIAL PRIMARY KEY,
    message_id INTEGER REFERENCES messages(id),
    user_id INTEGER REFERENCES users(id),
    read_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(message_id, user_id)
);

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

-- 3. Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('current_academic_year', '2025/2026', 'Current academic year'),
('annual_dues_amount', '100.00', 'Annual membership dues amount in GHS')
ON CONFLICT (setting_key) DO NOTHING;

-- 4. Insert fee structures
INSERT INTO fee_structures (level, amount, academic_year, semester, fee_type, description) VALUES
('100', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues'),
('200', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues'),
('300', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues'),
('400', 100.00, '2025/2026', 'All', 'dues', 'Annual membership dues')
ON CONFLICT DO NOTHING;

-- 5. Insert test payments (for existing students)
INSERT INTO payments (student_id, amount, payment_method, receipt_number, academic_year, semester, status, payment_date) VALUES
(1, 100.00, 'Cash', 'RCP-2026-001', '2025/2026', 'All', 'completed', '2026-05-01'),
(1, 50.00, 'Mobile Money', 'RCP-2026-002', '2025/2026', 'All', 'completed', '2026-05-01'),
(2, 100.00, 'Cash', 'RCP-2026-003', '2025/2026', 'All', 'completed', '2026-05-02'),
(4, 75.00, 'Mobile Money', 'RCP-2026-004', '2025/2026', '1', 'completed', '2026-05-03'),
(4, 30.00, 'Cash', 'RCP-2026-005', '2025/2026', '2', 'completed', '2026-05-03')
ON CONFLICT (receipt_number) DO NOTHING;

-- 6. Add a Bursar user
INSERT INTO users (email, password, role, status, is_password_reset)
VALUES ('bursar@infotess.org', '$2y$10$YRNoSKY.hpBVI8PGmwOMNOZAmYXoAIzsnr0Py0vqoHiERUihByEkq', 'bursar', 'active', true)
ON CONFLICT (email) DO NOTHING;

-- ==========================================
-- VERIFICATION QUERIES
-- ==========================================
SELECT 'students' AS tbl, COUNT(*) FROM students
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'fee_structures', COUNT(*) FROM fee_structures
UNION ALL SELECT 'system_settings', COUNT(*) FROM system_settings
UNION ALL SELECT 'messages', COUNT(*) FROM messages
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications;
