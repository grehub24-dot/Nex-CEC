-- Find ALL FK constraint names referencing students(id)
SELECT
    conname AS constraint_name,
    conrelid::regclass::text AS table_name,
    pg_get_constraintdef(oid) AS constraint_def
FROM pg_constraint
WHERE contype = 'f'
  AND confrelid = 'students'::regclass
ORDER BY conname;
