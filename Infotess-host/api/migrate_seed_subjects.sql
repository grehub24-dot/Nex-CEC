-- migrate_seed_subjects.sql
-- Seeds missing subjects and builds category mapping.
-- Merges with existing subjects already in the database.
-- Run this in Supabase SQL Editor.

-- ============================================================
-- STEP 1: Insert only subjects NOT already in the database
-- ============================================================
INSERT INTO subjects (name, code)
SELECT name, code FROM (VALUES
    ('Literacy',              'LIT'),
    ('Numeracy',              'NUM'),
    ('Colouring',             'COL'),
    ('Pre-writing',           'PRW'),
    ('OWOP',                  'OWOP'),
    ('History',               'HIST'),
    ('Asante-Twi',           'ATWI'),
    ('Science',               'SCI'),    -- separate from Integrated Science (id 3)
    ('Social Studies',        'SST'),
    ('French',                'FRE'),
    ('Ghanaian Language',     'GL'),
    ('Physical Education',    'PE'),
    ('Career Technology',     'CT')
) AS new(name, code)
WHERE NOT EXISTS (
    SELECT 1 FROM subjects WHERE subjects.name = new.name
);
-- Subjects already existing (will NOT be re-inserted):
--   English Language (id 1), Mathematics (2), Integrated Science (3),
--   Creative Arts (6), Computing (8), Religious and Moral Education (10),
--   Language & Literacy (165), Scribbling (199), etc.

-- ============================================================
-- STEP 2: Build & save category mapping to system_settings
-- ============================================================
WITH
-- Get all subject IDs by name (works for both existing and new)
subject_ids AS (
    SELECT id, name FROM subjects
    WHERE name IN (
        'Literacy', 'Numeracy', 'Colouring', 'Scribbling',
        'Pre-writing', 'Creative Arts', 'OWOP',
        'English Language', 'Mathematics', 'Science', 'Computing',
        'History', 'Religious and Moral Education', 'Asante-Twi',
        'Integrated Science', 'Social Studies', 'French',
        'Ghanaian Language', 'Physical Education', 'Career Technology',
        'Language & Literacy'
    )
),
mapping AS (
    SELECT 'creche' AS category,
        ARRAY(
            SELECT id FROM subject_ids
            WHERE name IN ('Literacy', 'Numeracy', 'Colouring', 'Scribbling')
            ORDER BY id
        ) AS subject_ids_array
    UNION ALL
    SELECT 'nursery',
        ARRAY(
            SELECT id FROM subject_ids
            WHERE name IN ('Literacy', 'Numeracy', 'Colouring', 'Pre-writing')
            ORDER BY id
        )
    UNION ALL
    SELECT 'kindergarten',
        ARRAY(
            SELECT id FROM subject_ids
            WHERE name IN ('Literacy', 'Numeracy', 'Creative Arts', 'Pre-writing', 'OWOP')
            ORDER BY id
        )
    UNION ALL
    SELECT 'primary',
        ARRAY(
            SELECT id FROM subject_ids
            WHERE name IN ('English Language', 'Mathematics', 'Science', 'Computing', 'History', 'Religious and Moral Education', 'Asante-Twi', 'Creative Arts')
            ORDER BY id
        )
    UNION ALL
    SELECT 'jhs',
        ARRAY(
            SELECT id FROM subject_ids
            WHERE name IN ('English Language', 'Mathematics', 'Integrated Science',
                'Social Studies', 'French', 'Ghanaian Language',
                'Physical Education', 'Career Technology', 'Computing',
                'Religious and Moral Education', 'Creative Arts',
                'History', 'Asante-Twi', 'Language & Literacy')
            ORDER BY id
        )
)
-- Save the mapping to system_settings (upsert — handles first-run and re-run)
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'subject_categories',
    (SELECT json_object_agg(
        m.category,
        (SELECT json_agg(sid) FROM unnest(m.subject_ids_array) AS sid)
     )::text
     FROM mapping m)
ON CONFLICT (setting_key) DO UPDATE SET
    setting_value = EXCLUDED.setting_value,
    updated_at = NOW();

-- Show what was saved
SELECT setting_value AS subject_categories_json FROM system_settings WHERE setting_key = 'subject_categories';
