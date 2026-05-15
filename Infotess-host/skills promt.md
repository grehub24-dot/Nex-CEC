Now I have a thorough understanding. Here are the best safe, non-breaking skill prompts for each area of your project:
---
Database & Query Performance
opencode run "@postgres-best-practices
Review my Supabase queries for Nex CEC school management system. These are the patterns used:
1. Dashboard: fetches ALL rows from students, payments, staff, messages, attendance tables then filters/counts in PHP (see api/admin_dashboard.php)
2. Attendance: FETCH ALL students by class_name, filters in PHP (api/admin_attendance.php)
3. Payroll: FETCH ALL staff, checks each if payroll exists, filters active in PHP (api/admin_payroll.php)
4. Payments: SELECT * FROM payments WHERE student_id = ? AND academic_year = ? (api/student_dashboard.php)
Generate CREATE INDEX statements for Supabase that cover:
- payments: student_id + academic_year
- student_attendance: attendance_date, class_name + attendance_date
- payroll: staff_id + month + year
- students: class_name, status
- fee_structures: class_id + academic_year + term
Also check if I should enable pg_stat_statements. Do NOT suggest changing the PHP code — only database-level index and config improvements."
opencode run "@sql-optimization-patterns
My Nex CEC app on Supabase (PostgreSQL) fetches all rows and filters in PHP because the LegacyPDO bridge can't do SUM(), JOINs, or WHERE with IS NULL. Given this constraint:
1. Which Supabase views (CREATE VIEW) could I add to pre-aggregate dashboard stats (total revenue, student count by status, compliance rate)?
2. How can I use generated columns on the students table to avoid PHP-side status filtering?
3. Should I use Supabase Database Functions (plpgsql) to do the aggregation server-side?
Only suggest additive SQL changes (views, functions, generated columns) — no PHP code changes."
---
Security & Auth
opencode run "@security-audit
Audit my PHP school management app on Vercel with Supabase. Review these specific areas WITHOUT suggesting architecture changes:
1. Session handling: api/includes/SessionHandler.php stores sessions in a database table via Supabase REST bridge
2. Auth flow: api/login.php uses password_verify() with bcrypt hashes, role stored in $_SESSION['role']
3. Access control: api/includes/functions.php has requireAccess() function checked at top of each admin page
4. SQL injection: All queries go through LegacyPDO bridge which URL-encodes params to Supabase REST API
5. File uploads: Student profile pictures, receipt uploads
What are the actual risk vectors given the architecture (PHP → Supabase REST API, no direct DB connection)? Focus on session fixation, CSRF, and whether the role-based checks in each PHP file are sufficient."
---
Frontend & PWA
opencode run "@progressive-web-app
Add PWA support to my PHP school management system (Nex CEC) deployed on Vercel. The app has public pages (home, about, activities) and authenticated pages (admin dashboard, student dashboard, payments, attendance, grades, payroll, report cards).
Create:
1. A manifest.json with school name, icons, theme colors (#003366 primary, #ffcc00 secondary)
2. A service worker that caches the CSS, JS, and logo from /css/style.css, /js/main.js, /images/
3. An offline fallback page so authenticated users see a meaningful message when offline
4. Guide me where to link the manifest in the HTML <head>
The frontend is vanilla HTML+CSS+JS — NO React/Next.js. Keep this additive: only new files (manifest.json, sw.js), no changes to existing PHP logic."
opencode run "@ui-a11y
Review the admin and student dashboard pages in my PHP school management system. I have:
- Navigation with dropdowns (see dashboard.html for structure)
- Data tables for students, payments, attendance
- Forms for recording payments, attendance, grades
- Charts (Chart.js) in admin dashboard
Check: skip links, focus management after form submit, ARIA labels on navigation, table headers with <scope>, color contrast (primary #003366 on white), and form error announcements. Suggest ONLY HTML attribute additions and CSS changes — no PHP restructuring."
---
Code Quality (Safe)
opencode run "@error-handling-patterns
My PHP school management app on Vercel/Supabase has try/catch blocks that silently fail and return empty arrays (see admin_dashboard.php lines 76-79, student_dashboard.php lines 38-40, admin_fees.php lines 57-78). 
Without changing the architecture, suggest:
1. Better error logging that captures the Supabase REST API error messages
2. A helper function that wraps common fetch patterns (fetch settings, fetch students) with consistent error handling
3. User-facing error messages for database failures instead of silent empty states
Only additive changes to api/includes/functions.php — no page restructuring."
opencode run "@i18n-localization
My PHP school management system (Nex CEC) has hardcoded English strings throughout all pages. The system_settings table stores school name, motto, address.
List all the categories of hardcoded strings I should extract into the system_settings table:
1. UI labels (Dashboard, Students, Payments, etc.)
2. Form labels (Full Name, Amount, Payment Method, etc.)
3. Status messages (Payment recorded, Student added, etc.)
4. Error messages (Student not found, Invalid amount, etc.)
Give me the SQL INSERT statements for new system_settings keys and a PHP helper function to retrieve them. Do NOT change any existing page files — only additive functions in api/includes/functions.php and new settings rows."
---
Phase-Specific Improvements
opencode run "@database-design
I need to add new tables to my Supabase database for Phase 2 (Staff Payroll). My existing schema uses these naming conventions: lowercase, snake_case, bigint identity PKs, timestamptz for dates. 
Generate CREATE TABLE SQL for these NEW tables (additive only, won't touch existing):
1. staff_deductions_types — configurable deduction types (SSNIT, Tax, Loan, etc.) with rates
2. payroll_audit_log — track who generated payroll and when
Follow my existing conventions: bigint generated always as identity, timestamptz, text not varchar. Include proper indexes."
opencode run "@seo-audit
Review the public-facing PHP pages of my school management system (home.php, about.php, department.php, activities.php, events.php, contact.php, fees.php). Each page has mixed PHP+HTML output.
Generate a checklist of SEO improvements that are purely HTML/head changes:
1. Meta descriptions per page
2. Open Graph tags (og:title, og:description, og:image, og:url)
3. JSON-LD structured data for EducationalOrganization schema
4. Canonical URLs
Give me the exact HTML snippets to add to the <head> section. No PHP changes needed — these are additive HTML only."
---
Quick reference — which skill for what task:
Your task	Use skill
Slow dashboard queries	@postgres-best-practices
Add indexes	@sql-optimization-patterns
Review auth/sessions	@security-audit
Make installable on phones	@progressive-web-app
Accessibility fixes	@ui-a11y
Better error handling	@error-handling-patterns
Extract hardcoded text	@i18n-localization
New DB tables for payroll	@database-design
SEO meta tags	@seo-audit