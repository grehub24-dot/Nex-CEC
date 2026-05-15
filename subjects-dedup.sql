-- ==========================================
-- SUBJECTS TABLE DEDUP + CONSTRAINT FIX
-- ==========================================
-- PROBLEM: UNIQUE(name, class_id) allows unlimited
-- duplicate rows when class_id IS NULL because
-- PostgreSQL treats NULL != NULL in unique constraints.
-- Running migrate-all.sql multiple times OR clicking
-- "Seed All Defaults" repeatedly in Subject Settings
-- creates N copies of the same subject.
--
-- This script:
--   1. Reports all duplicate subject groups
--   2. Remaps sba_scores to canonical (lowest ID) subject rows
--   3. Remaps exam_scores to canonical subject rows
--   4. Deletes all duplicate subject rows
--   5. Drops the broken UNIQUE(name, class_id) constraint
--   6. Creates proper partial unique indexes:
--      - UNIQUE(name) WHERE class_id IS NULL  (prevents NULL dupes)
--      - UNIQUE(name, class_id) WHERE class_id IS NOT NULL
-- ==========================================

DO $$
DECLARE
    dup_name TEXT;
    dup_count INT := 0;
    total_removed INT := 0;
BEGIN
    -- ==========================================
    -- STEP 1: Report duplicates
    -- ==========================================
    RAISE NOTICE '=== SUBJECT DEDUP ===';
    
    FOR dup_name IN 
        SELECT name FROM subjects 
        GROUP BY name, COALESCE(class_id::text, 'NULL') 
        HAVING COUNT(*) > 1
    LOOP
        dup_count := dup_count + 1;
    END LOOP;
    
    IF dup_count = 0 THEN
        RAISE NOTICE 'No duplicate subjects found. Skipping dedup.';
    ELSE
        RAISE NOTICE 'Found % subject name(s) with duplicates.', dup_count;
        
        -- Show duplicate details
        RAISE NOTICE 'Duplicate groups:';
        FOR dup_name IN 
            SELECT name || ' (class_id=' || COALESCE(class_id::text, 'NULL') || ') x' || COUNT(*)::text
            FROM subjects 
            GROUP BY name, COALESCE(class_id::text, 'NULL') 
            HAVING COUNT(*) > 1
            ORDER BY name
        LOOP
            RAISE NOTICE '  %', dup_name;
        END LOOP;
        
        -- ==========================================
        -- STEP 2: Remap sba_scores to canonical IDs
        -- ==========================================
        WITH duplicates AS (
            SELECT MIN(id) AS keep_id, name, COALESCE(class_id, 0) AS class_key
            FROM subjects
            GROUP BY name, COALESCE(class_id, 0)
            HAVING COUNT(*) > 1
        )
        UPDATE sba_scores
        SET subject_id = d.keep_id
        FROM duplicates d
        INNER JOIN subjects dup 
            ON dup.name = d.name 
            AND COALESCE(dup.class_id, 0) = d.class_key
            AND dup.id != d.keep_id
        WHERE sba_scores.subject_id = dup.id;
        
        GET DIAGNOSTICS dup_count = ROW_COUNT;
        RAISE NOTICE 'Remapped % sba_scores records to canonical subject IDs.', dup_count;
        
        -- ==========================================
        -- STEP 3: Remap exam_scores to canonical IDs
        -- ==========================================
        WITH duplicates AS (
            SELECT MIN(id) AS keep_id, name, COALESCE(class_id, 0) AS class_key
            FROM subjects
            GROUP BY name, COALESCE(class_id, 0)
            HAVING COUNT(*) > 1
        )
        UPDATE exam_scores
        SET subject_id = d.keep_id
        FROM duplicates d
        INNER JOIN subjects dup 
            ON dup.name = d.name 
            AND COALESCE(dup.class_id, 0) = d.class_key
            AND dup.id != d.keep_id
        WHERE exam_scores.subject_id = dup.id;
        
        GET DIAGNOSTICS dup_count = ROW_COUNT;
        RAISE NOTICE 'Remapped % exam_scores records to canonical subject IDs.', dup_count;
        
        -- ==========================================
        -- STEP 4: Remove duplicates with class_id=0 
        -- (the COALESCE in step 2/3 mapped NULL→0)
        -- but keep the actual NULL ones; we need to 
        -- delete by comparing with non-canonical IDs
        -- ==========================================
        DELETE FROM subjects
        WHERE id NOT IN (
            SELECT MIN(id)
            FROM subjects
            GROUP BY name, COALESCE(class_id, 0)
        );
        
        GET DIAGNOSTICS total_removed = ROW_COUNT;
        RAISE NOTICE 'Deleted % duplicate subject rows.', total_removed;
    END IF;
    
    -- ==========================================
    -- STEP 5: Fix the constraint
    -- ==========================================
    -- Drop the old constraint that allowed NULL duplicates
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'subjects_name_class_id_key') THEN
        ALTER TABLE subjects DROP CONSTRAINT subjects_name_class_id_key;
        RAISE NOTICE 'Dropped old UNIQUE(name, class_id) constraint.';
    END IF;
    
    -- Create partial unique index: no two subjects can share the same name
    -- when class_id IS NULL (the global/uncategorized subjects)
    DROP INDEX IF EXISTS subjects_name_unique_null_class_id;
    CREATE UNIQUE INDEX subjects_name_unique_null_class_id 
        ON subjects(name) WHERE class_id IS NULL;
    RAISE NOTICE 'Created UNIQUE INDEX subjects_name_unique_null_class_id ON subjects(name) WHERE class_id IS NULL';
    
    -- Create partial unique index: for class-specific subjects, the same
    -- name can appear once per class
    DROP INDEX IF EXISTS subjects_name_class_id_unique_not_null;
    CREATE UNIQUE INDEX subjects_name_class_id_unique_not_null 
        ON subjects(name, class_id) WHERE class_id IS NOT NULL;
    RAISE NOTICE 'Created UNIQUE INDEX subjects_name_class_id_unique_not_null ON subjects(name, class_id) WHERE class_id IS NOT NULL';
    
    -- ==========================================
    -- FINAL REPORT
    -- ==========================================
    RAISE NOTICE '=== DEDUP COMPLETE ===';
    RAISE NOTICE 'Remaining subjects: %', (SELECT COUNT(*) FROM subjects);
END $$;

-- Final verification
SELECT 'Final subject count: ' || COUNT(*)::text FROM subjects;
