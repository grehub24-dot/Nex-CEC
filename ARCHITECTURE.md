# Nex CEC — Basic School Management System
## Architecture & Roadmap

---

## System Overview

A complete school management system built in 4 phases:

| Phase | Module | Status | Description |
|-------|--------|--------|-------------|
| 1 | **School Fees** | ⬜ In Progress | Student enrollment, fee types, term payments, receipts |
| 2 | **Staff Payroll** | ⬜ Planned | Staff management, salary structures, pay slips, deductions |
| 3 | **Report Cards / SBA** | ⬜ Planned | Subjects, grading, SBA scores, report card generation |
| 4 | **Attendance** | ⬜ Planned | Daily attendance for students & staff, reports |

---

## Database Schema

### Phase 1: School Fees (MVP)

```sql
-- Core tables (EXISTING, need refactoring)
users               -- id, email, password, role (admin/student/staff), status, created_at
students            -- id, user_id, index_number, full_name, class/grade, programme, phone, parent info
payments            -- id, student_id, fee_structure_id, amount, term, academic_year, receipt_number, method
fee_structures      -- id, fee_type, class_id, amount, term, academic_year, is_mandatory
system_settings     -- id, setting_key, setting_value

-- NEW tables to create
classes             -- id, name (e.g., "Basic 1", "JHS 3"), level_group, capacity
guardians           -- id, student_id, name, phone, email, relationship, address
```

### Phase 2: Staff Payroll

```sql
staff               -- id, user_id, staff_id, full_name, position, department, phone, qualification, hire_date, status
salary_structures   -- id, staff_id, basic_salary, housing_allowance, transport_allowance, other_allowances
payroll             -- id, staff_id, month, year, gross_pay, deductions, net_pay, status, pay_date
deductions          -- id, staff_id, type (SSNIT, tax, loan, other), amount, description
pay_slips           -- id, payroll_id, generated_at, pdf_path
```

### Phase 3: Report Cards / SBA

```sql
subjects            -- id, name, code, class_id, teacher_id
terms               -- id, name (Term 1, 2, 3), academic_year, start_date, end_date
sba_scores          -- id, student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude
exam_scores         -- id, student_id, subject_id, term_id, exam_score
report_cards        -- id, student_id, term_id, class_position, total_students, class_teacher_remarks, headmaster_remarks
grade_boundaries    -- id, min_score, max_score, grade, remark (1=Excellent, 9=Very Poor)
```

### Phase 4: Attendance

```sql
student_attendance  -- id, student_id, class_id, date, status (present/absent/late), reason
staff_attendance    -- id, staff_id, date, check_in, check_out, status, notes
attendance_summary  -- id, student_id, month, year, total_days, present, absent, late
```

---

## File Structure

```
Infotess-host/
├── api/
│   ├── index.php                 # Central router
│   ├── includes/
│   │   ├── db.php                # Supabase bridge
│   │   ├── functions.php         # Helper functions
│   │   ├── header.php            # Public header
│   │   ├── footer.php            # Public footer
│   │   ├── Mailer.php            # Email
│   │   └── SMSHelper.php         # SMS
│   ├── lib/
│   │   └── Supabase.php          # REST client
│   ├── PHPMailer/                # Email library
│   │
│   ├── home.php                  # Landing page
│   ├── login.php
│   ├── register.php              # Student enrollment
│   ├── forgot-password.php
│   ├── logout.php
│   │
│   │── ADMIN PAGES
│   │   ├── admin_dashboard.php
│   │   ├── admin_students.php       # Student CRUD
│   │   ├── admin_edit_student.php
│   │   ├── admin_fees.php           # Fee structure management (NEW)
│   │   ├── admin_payments.php       # Record payments
│   │   ├── admin_verify.php         # Receipt verification
│   │   ├── admin_reports.php        # Financial reports
│   │   ├── admin_settings.php       # System settings
│   │   ├── admin_users.php          # User management
│   │   ├── admin_messaging.php      # Notifications
│   │   ├── admin_inbox.php
│   │   ├── admin_staff.php          # Staff CRUD (Phase 2)
│   │   ├── admin_payroll.php        # Payroll (Phase 2)
│   │   ├── admin_attendance.php     # Attendance management (Phase 4)
│   │   ├── admin_grades.php         # SBA/Grades entry (Phase 3)
│   │   └── admin_report_cards.php   # Generate report cards (Phase 3)
│   │
│   │── STUDENT PORTAL
│   │   ├── student_dashboard.php
│   │   ├── student_profile.php
│   │   ├── student_history.php      # Payment history
│   │   ├── student_fees.php         # View fee structure (NEW)
│   │   ├── student_report_card.php  # View grades (Phase 3)
│   │   └── student_messages.php
│   │
│   │── STAFF PORTAL (Phase 2-4)
│   │   ├── staff_dashboard.php
│   │   ├── staff_profile.php
│   │   ├── staff_payslip.php
│   │   ├── staff_attendance.php
│   │   └── staff_grades.php         # Enter SBA scores
```

---

## Phase 1: Immediate Actions

### 1. Database Migration
- Create `classes` table (Basic 1-6, JHS 1-3, etc.)
- Add `guardian` fields to `students` (parent_name, parent_phone)
- Ensure `fee_structures` uses correct schema
- Create missing `messages`, `notifications`, `message_reads` tables

### 2. Code Refactoring
- Replace all "INFOTESS" branding → School name (configurable)
- Replace "INFOTESS" references in emails, UI, headers
- Update student registration form:
  - Remove: university programme/class/stream fields
  - Add: Class (Basic 1-9), Guardian name, Guardian phone
- Update payment form:
  - Fee types: Tuition, PTA, Sports, Library, ICT, Uniform, Exam
  - Term-based payments (Term 1, 2, 3)
- Update admin dashboard labels

### 3. Fee Structure Update
- Current: `fee_structures` has UUIDs, title, amount, term
- Need: fee_type dropdown, class mapping, mandatory flag

---

## Phase 2: Staff Payroll (After Phase 1 stable)

### Features
- Staff registration (teachers, admin, support staff)
- Salary structure per position
- Monthly payroll generation
- Auto-calculate: Gross, SSNIT (13.5%), Tax, Net
- Pay slip PDF generation
- Staff portal to view payslips

### Key Tables
- `staff`, `salary_structures`, `payroll`, `deductions`, `pay_slips`

---

## Phase 3: Report Cards / SBA

### Features
- Subject management per class
- SBA score entry (Class Test, Mid-term, End-term, Project)
- Auto-calculate totals and grades
- Class position ranking
- Report card PDF generation
- Parent view (student portal)

### Grading System (Ghana Basic School)
```
Score  | Grade | Remark
1-39   | 9     | Very Poor
40-49  | 8     | Poor
50-54  | 7     | Below Average
55-59  | 6     | Average
60-69  | 5     | Pass
70-79  | 4     | Credit
80-89  | 3     | Distinction
90-100 | 1-2   | Excellent
```

---

## Phase 4: Attendance

### Features
- Daily student attendance (mark present/absent/late)
- Staff check-in/check-out
- Monthly attendance summaries
- Absence alerts to parents
- Attendance reports

---

## Branding Configuration

All branding stored in `system_settings`:
```
school_name          → Default school name
school_motto         → School motto
school_logo          → Logo path
school_address       → Physical address
school_phone         → Contact phone
school_email         → Contact email
academic_year        → 2025/2026
current_term         → 1, 2, or 3
```
