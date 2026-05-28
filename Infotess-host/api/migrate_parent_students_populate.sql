-- ============================================================
-- MIGRATION: Populate parent_students for existing records
-- ============================================================
-- PROBLEM:
--   The parent_students table was only populated by
--   admin_enrollments.php::createParentAccount(). Manual student
--   registration (admin_students.php) and bulk import
--   (admin_bulk_import.php) created student login accounts but
--   never created parent_students links. This was fixed in code
--   (see commit b9611be), but existing students remain unlinked.
--
-- THIS SCRIPT:
--   Phase 1 — Dry-run preview (SELECT only, no writes)
--   Phase 2 — Execute INSERT for unmatched records
--
-- MATCH RULES:
--   1. guardian_email ↔ users.email  (any user role — parent, staff,
--      teacher, admin — supports dual-role accounts)
--   2. staff.phone ↔ students.guardian_phone_primary  (for staff
--      whose user email differs from what's stored as guardian_email)
-- ============================================================

------------------------------------------------------------------
-- PHASE 1: Preview — see what WOULD be linked
------------------------------------------------------------------

-- 1a) Email-based matches
SELECT
    'email_match' AS match_type,
    u.id      AS parent_user_id,
    u.email   AS parent_email,
    u.role    AS parent_role,
    s.id      AS student_id,
    s.full_name AS student_name,
    COALESCE(NULLIF(TRIM(s.guardian_relationship), ''), 'Guardian') AS relationship
FROM students s
JOIN users u ON TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps
    WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  )
ORDER BY u.email, s.full_name;

-- 1b) Phone-based matches (staff only — for when staff user email
--     differs from guardian_email on record)
SELECT
    'phone_match' AS match_type,
    u.id      AS parent_user_id,
    u.email   AS parent_email,
    u.role    AS parent_role,
    s.id      AS student_id,
    s.full_name AS student_name,
    COALESCE(NULLIF(TRIM(s.guardian_relationship), ''), 'Guardian') AS relationship
FROM students s
JOIN staff st ON TRIM(st.phone) = TRIM(s.guardian_phone_primary)
JOIN users u ON u.id = st.user_id
WHERE s.guardian_phone_primary IS NOT NULL AND TRIM(s.guardian_phone_primary) != ''
  AND (
    -- exclude students already linked via email match
    NOT EXISTS (
      SELECT 1 FROM parent_students ps
      WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
    )
  )
ORDER BY u.email, s.full_name;

-- 1c) Name-based matches (staff full_name ↔ student guardian_name
--     — lowest confidence, use as last resort)
SELECT
    'name_match' AS match_type,
    u.id      AS parent_user_id,
    u.email   AS parent_email,
    u.role    AS parent_role,
    s.id      AS student_id,
    s.full_name AS student_name,
    COALESCE(NULLIF(TRIM(s.guardian_relationship), ''), 'Guardian') AS relationship
FROM students s
JOIN staff st ON TRIM(LOWER(st.full_name)) = TRIM(LOWER(s.guardian_name))
JOIN users u ON u.id = st.user_id
WHERE s.guardian_name IS NOT NULL AND TRIM(s.guardian_name) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps
    WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  )
  AND NOT EXISTS (
    -- skip already matched by email or phone above
    SELECT 1 FROM users u2
    WHERE u2.id = u.id AND (
      u2.email = s.guardian_email
      OR u2.email IN (
        SELECT st2.email FROM staff st2 WHERE st2.user_id = u.id AND st2.phone = s.guardian_phone_primary
      )
    )
  )
ORDER BY u.email, s.full_name;

-- 1d) Summary counts for preview
SELECT 'email_match' AS match_source, COUNT(*) AS pending_links
FROM students s
JOIN users u ON TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  )
UNION ALL
SELECT 'phone_match' AS match_source, COUNT(*)
FROM students s
JOIN staff st ON TRIM(st.phone) = TRIM(s.guardian_phone_primary)
JOIN users u ON u.id = st.user_id
WHERE s.guardian_phone_primary IS NOT NULL AND TRIM(s.guardian_phone_primary) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  )
UNION ALL
SELECT 'name_match' AS match_source, COUNT(*)
FROM students s
JOIN staff st ON TRIM(LOWER(st.full_name)) = TRIM(LOWER(s.guardian_name))
JOIN users u ON u.id = st.user_id
WHERE s.guardian_name IS NOT NULL AND TRIM(s.guardian_name) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM users u2 WHERE u2.id = u.id AND (
      u2.email = s.guardian_email
      OR u2.email IN (
        SELECT st2.email FROM staff st2 WHERE st2.user_id = u.id AND st2.phone = s.guardian_phone_primary
      )
    )
  );

------------------------------------------------------------------
-- PHASE 2: Execute insert for email + phone matches
-- (name_match is lower confidence — included separately)
--
-- RUN THE INSERTS BELOW ONLY AFTER REVIEWING THE PREVIEW OUTPUT
------------------------------------------------------------------

BEGIN;

-- 2a) Email-based: guardian_email ↔ users.email (all roles)
INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary)
SELECT
    u.id,
    s.id,
    COALESCE(NULLIF(TRIM(s.guardian_relationship), ''), 'Guardian'),
    TRUE
FROM students s
JOIN users u ON TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps
    WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  );

-- 2b) Phone-based: staff.phone ↔ students.guardian_phone_primary
INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary)
SELECT
    u.id,
    s.id,
    COALESCE(NULLIF(TRIM(s.guardian_relationship), ''), 'Guardian'),
    TRUE
FROM students s
JOIN staff st ON TRIM(st.phone) = TRIM(s.guardian_phone_primary)
JOIN users u ON u.id = st.user_id
WHERE s.guardian_phone_primary IS NOT NULL AND TRIM(s.guardian_phone_primary) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps
    WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  );

-- 2c) Name-based: staff.full_name ↔ students.guardian_name
--     (lower confidence — comment out if too many false positives)
/*
INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary)
SELECT
    u.id,
    s.id,
    COALESCE(NULLIF(TRIM(s.guardian_relationship), ''), 'Guardian'),
    TRUE
FROM students s
JOIN staff st ON TRIM(LOWER(st.full_name)) = TRIM(LOWER(s.guardian_name))
JOIN users u ON u.id = st.user_id
WHERE s.guardian_name IS NOT NULL AND TRIM(s.guardian_name) != ''
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps
    WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM parent_students ps2
    JOIN users u2 ON u2.id = ps2.parent_user_id
    WHERE ps2.student_id = s.id
  );
*/

COMMIT;

-- ============================================================
-- VERIFICATION: Confirm results
-- ============================================================
SELECT '=== AFTER MIGRATION ===' AS info;

SELECT
    u.role,
    COUNT(*) AS link_count
FROM parent_students ps
JOIN users u ON u.id = ps.parent_user_id
GROUP BY u.role
ORDER BY link_count DESC;

SELECT COUNT(*) AS total_parent_students_links FROM parent_students;
