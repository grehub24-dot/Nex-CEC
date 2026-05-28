# Nex CEC — Claude Configuration

## Project Overview

School management system (PHP + MySQL/Supabase) for Nex Central Excel College. Handles students, staff, classes, subjects, billing, grades, and enrollment.

## Tech Stack

- **Backend**: PHP 8.x (LegacyPDO REST bridge in `Infotess-host/api/`)
- **Database**: Supabase PostgreSQL (via `Infotess-host/api/includes/db.php`)
- **Frontend**: PHP-generated HTML views, vanilla CSS/JS
- **Auth**: Supabase Auth + custom session handling

## Key Constraints

- `subjects`: master rows (teacher_id=NULL, class_id=NULL) + per-teacher-class assignment rows (INSERT new, never UPDATE masters)
- `class_teachers` is UNIQUE(staff_id) — one class per teacher max
- `getTeacherClassIds()` queries `subjects.teacher_id` first, falls back to `class_teachers.staff_id`
- Student login email unique per student; guardian_email is contact-only (siblings share)
- Staff members' own user accounts serve as `parent_user_id` in `parent_students` (dual-role) — no duplicate account created
- Staff Child Discount auto-apply works at both class billing and individual student billing save
- Real files live at `Infotess-host/api/`, not `api/`

## Relevant Files

- `Infotess-host/api/includes/functions.php` — core helpers
- `Infotess-host/api/includes/db.php` — LegacyPDO REST bridge
- `Infotess-host/api/index.php` — router
- `Infotess-host/api/admin_students.php` — add student
- `Infotess-host/api/admin_bulk_import.php` — bulk student import
- `Infotess-host/api/admin_edit_student.php` — edit student
- `Infotess-host/api/admin_staff.php` — add staff
- `Infotess-host/api/admin_edit_staff.php` — edit staff
- `Infotess-host/api/admin_role_permissions.php` — auto-assign teacher-class links
- `Infotess-host/api/admin_link_subjects.php` — bulk/manual subject-teacher-class linking
- `Infotess-host/api/admin_enrollments.php` — enrollment processing
- `Infotess-host/api/admin_class_billing.php` — class billing
- `Infotess-host/api/admin_student_billing.php` — individual student billing
- `Infotess-host/api/admin_migrate_parent_students.php` — parent_students backfill
- `Infotess-host/api/admin_settings.php` — discount settings
- `Infotess-host/api/staff_grades.php` — subject/student loading for teacher portal

## Agent skills

This project has Matt Pocock's skills installed at `.agents/skills/`.

- **Domain docs**: `docs/agents/domain.md`
- **Issue tracker**: `docs/agents/issue-tracker.md` (GitHub)
- **Triage labels**: `docs/agents/triage-labels.md`
