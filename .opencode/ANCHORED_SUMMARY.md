# Anchored Summary

## Active Session (2026-05-20)

### Problem
Vercel routing conflict — static site occupies `/admin/` path, so our admin panel's PHP backend (served separately on Supabase/Infotess hosting) loads files from the wrong location. This caused blank pages / 404 errors when `/admin/` urls were accessed.

### Fix Applied (Reverted)
- Commit `c21b3b2` tried to rename:
  - `Infotess-host/admin/` → `Infotess-host/manager/` (for PHP admin panel)
  - `Infotess-host/api/` → `Infotess-host/api_v2/` (for API endpoints)
  - Also changed all internal redirects and file references (`require_once`, `action=`, redirect urls)
- **This broke deployment** because Vercel serves the static site at `/admin/` path, and the PHP backend needs to serve directly at root or a different path.
- **Commit was reverted.** The Vercel static site remains at `/admin/` and the PHP backend now lives at `/admin-panel/` path on the server.

### Current State (admin_staff.php — 2026-05-20)
- Staff delete handler (`GET ?delete=N`) and bulk delete handler (`POST action=bulk_delete_staff`) now perform **complete FK cleanup before deleting**, using the actual schema revealed by the user:

**Tables cleaned by `staff_id`:**
- `salary_structures` → DELETE
- `deductions` → DELETE
- `payroll` → DELETE
- `staff_attendance` → DELETE
- `staff_invites` → DELETE
- `subjects` → SET teacher_id = NULL (added 2026-05-20)

**Tables cleaned by `user_id` (after staff row deleted):**
- `payments` → UPDATE recorded_by = fallback admin
- `student_attendance` → UPDATE recorded_by = fallback admin (added 2026-05-20)
- `messages` (sender + receiver) → DELETE
- `message_reads` → DELETE
- `notifications` → DELETE
- `audit_logs` → DELETE
- `executives` → DELETE (added 2026-05-20)
- `parent_students` → DELETE (added 2026-05-20)
- `staff_invites` → SET invited_by = NULL (added 2026-05-20)
- `users` → DELETE (final)

### Remaining Status
- 3 staff members previously failed to delete (user mentioned "only 3 staff failed")
- The 3 failures were likely caused by missing FK dependencies (executives, parent_students, student_attendance, staff_invites.invited_by, subjects.teacher_id)
- These are now fixed in the code
- The user needs to re-test the delete on the 3 failing staff members
- **Common causes for remaining failures**: if fallback admin not found (reassign falls back silently, not a blocker); or the `pdo->beginTransaction()` + `rollBack()` means partial failures are atomic and error message is logged
