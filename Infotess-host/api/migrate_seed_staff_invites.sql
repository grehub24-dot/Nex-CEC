-- migrate_seed_staff_invites.sql
-- Seeds user accounts + updates staff records + generates invite tokens.
-- Run this in Supabase SQL Editor.
-- After running, copy the invite links and share with each staff member via SMS/WhatsApp.
--
-- NOTE: Requires pgcrypto extension (enabled by default in Supabase).
--       If you get "function gen_random_bytes not found", run:
--       CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE EXTENSION IF NOT EXISTS pgcrypto;

WITH
-- ============================================================
-- STEP 1: Upsert user accounts (inactive, placeholder password)
-- ============================================================
ins_users AS (
    INSERT INTO users (email, password, role, status)
    VALUES
        -- Teachers
        ('naomi_nursery1@school.edu.gh',     crypt('Welcome123', gen_salt('bf')), 'teacher', 'inactive'),
        ('rosemond_nursery2@school.edu.gh',  crypt('Welcome123', gen_salt('bf')), 'teacher', 'inactive'),
        ('diana_kg1@school.edu.gh',          crypt('Welcome123', gen_salt('bf')), 'teacher', 'inactive'),
        ('francisca_kg2@school.edu.gh',      crypt('Welcome123', gen_salt('bf')), 'teacher', 'inactive'),
        ('patricia_bs4@school.edu.gh',       crypt('Welcome123', gen_salt('bf')), 'teacher', 'inactive'),
        ('stephen_bs5@school.edu.gh',        crypt('Welcome123', gen_salt('bf')), 'teacher', 'inactive'),
        -- Non-Teaching
        ('joyce_kitchen@school.edu.gh',      crypt('Welcome123', gen_salt('bf')), 'staff',   'inactive'),
        ('martha_junitor@school.edu.gh',     crypt('Welcome123', gen_salt('bf')), 'staff',   'inactive')
    ON CONFLICT (email) DO UPDATE SET
        status = 'inactive'
    RETURNING id, email
),
-- Map emails to staff codes for the JOIN
user_map AS (
    SELECT id, email,
        CASE
            WHEN email LIKE '%naomi%'     THEN 'NK'
            WHEN email LIKE '%rosemond%'  THEN 'ROW'
            WHEN email LIKE '%diana%'     THEN 'DFB'
            WHEN email LIKE '%francisca%' THEN 'FA'
            WHEN email LIKE '%patricia%'  THEN 'PA'
            WHEN email LIKE '%stephen%'   THEN 'SA'
            WHEN email LIKE '%joyce%'     THEN 'JA'
            WHEN email LIKE '%martha%'    THEN 'MB'
        END AS staff_code
    FROM ins_users
),
-- ============================================================
-- STEP 2: Upsert staff records with full data from spreadsheet
-- ============================================================
ins_staff AS (
    INSERT INTO staff (user_id, staff_id, full_name, position, department, status, email, gender, phone, qualification, hire_date)
    SELECT
        u.id,
        s.staff_id,
        s.full_name,
        s.position,
        s.department,
        'inactive',
        u.email,
        s.gender,
        s.phone,
        s.qualification,
        s.hire_date::date
    FROM (VALUES
        -- Teachers
        ('NK',  'Naomi Kusi',          'Teacher',      'Nursery 1', 'Female', '0240200047', NULL,         '2023-09-25'),
        ('ROW', 'Rosemond O. Woahene', 'Teacher',      'Nursery 2', 'Female', '0595343218', NULL,         '2023-09-25'),
        ('DFB', 'Diana Fosu Birago',   'Teacher',      'KG 1',      'Female', '0244362724', 'JHS',        '2024-09-25'),
        ('FA',  'Francisca Atta',      'Teacher',      'KG 2',      'Female', '0553806395', NULL,         '2024-02-28'),
        ('PA',  'Patricia Asamoah',    'Teacher',      'BS 4',      'Female', '0205645853', NULL,         '2025-11-12'),
        ('SA',  'Stephen Amoah',       'Teacher',      'BS 5',      'Male',   '0558638326', NULL,         '2024-09-25'),
        -- Non-Teaching
        ('JA',  'Joyce Acheampong',    'Caterer',      'Kitchen',   'Female', '0244787977', 'JHS Cert.',  '2023-09-04'),
        ('MB',  'Martha Bafa',         'Cleaner',      'Junitor',   'Female', '0539180210', NULL,         '2025-11-19')
    ) AS s(staff_id, full_name, position, department, gender, phone, qualification, hire_date)
    JOIN user_map u ON u.staff_code = s.staff_id
    ON CONFLICT (staff_id) DO UPDATE SET
        user_id        = EXCLUDED.user_id,
        status         = 'inactive',
        phone          = EXCLUDED.phone,
        qualification  = EXCLUDED.qualification,
        hire_date      = EXCLUDED.hire_date
    RETURNING id, staff_id, full_name, user_id, phone, position, department
),
-- ============================================================
-- STEP 3: Generate invite tokens (48h expiry)
-- ============================================================
ins_invites AS (
    INSERT INTO staff_invites (staff_id, user_id, token, status, invited_by, expires_at, invited_at)
    SELECT
        s.id,
        s.user_id,
        encode(gen_random_bytes(32), 'hex'),
        'pending',
        NULL,  -- invited_by: NULL (don't know admin user_id)
        NOW() + INTERVAL '48 hours',
        NOW()
    FROM ins_staff s
    ON CONFLICT (token) DO NOTHING
    RETURNING id, staff_id, token, expires_at
)
-- ============================================================
-- STEP 4: Output invite links + staff info
-- ============================================================
SELECT
    s.full_name,
    s.staff_id    AS code,
    s.phone,
    s.position,
    s.department,
    i.token,
    CONCAT('https://nex-cec.vercel.app/staff/register.php?token=', i.token) AS invite_link,
    i.expires_at
FROM ins_invites i
JOIN ins_staff s ON s.id = i.staff_id
ORDER BY s.full_name;
