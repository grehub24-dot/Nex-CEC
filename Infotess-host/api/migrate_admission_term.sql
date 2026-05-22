-- migration: add admission_term to students table
-- new student detection is now term-based, not year-based:
-- a student is "new" only in the term they were admitted
-- once the term advances, they become returning students
ALTER TABLE public.students ADD COLUMN IF NOT EXISTS admission_term character varying DEFAULT '1';

-- update description in existing records where admission_term is null (safety net)
UPDATE public.students SET admission_term = '1' WHERE admission_term IS NULL;
