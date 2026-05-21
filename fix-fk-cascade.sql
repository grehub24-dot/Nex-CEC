-- ============================================================
-- FIX FK CASCADE — Add ON DELETE CASCADE to all FKs
-- ============================================================
-- Run this in Supabase SQL Editor to fix ALL FK constraints
-- so you can delete data directly in Supabase without errors.
--
-- Strategy:
--   ON DELETE CASCADE on EVERY FK → deleting a parent record
--   automatically deletes ALL child records too.
-- ============================================================

-- ============================================================
-- 1. REFERENCES users(id)
-- ============================================================

-- staff.user_id — CASCADE (staff can't exist without user)
ALTER TABLE public.staff DROP CONSTRAINT IF EXISTS staff_user_id_fkey;
ALTER TABLE public.staff ADD CONSTRAINT staff_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- students.user_id — CASCADE
ALTER TABLE public.students DROP CONSTRAINT IF EXISTS students_user_id_fkey;
ALTER TABLE public.students ADD CONSTRAINT students_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- executives.user_id — CASCADE
ALTER TABLE public.executives DROP CONSTRAINT IF EXISTS executives_user_id_fkey;
ALTER TABLE public.executives ADD CONSTRAINT executives_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- messages.sender_id — CASCADE
ALTER TABLE public.messages DROP CONSTRAINT IF EXISTS messages_sender_id_fkey;
ALTER TABLE public.messages ADD CONSTRAINT messages_sender_id_fkey
  FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- messages.receiver_id — CASCADE
ALTER TABLE public.messages DROP CONSTRAINT IF EXISTS messages_receiver_id_fkey;
ALTER TABLE public.messages ADD CONSTRAINT messages_receiver_id_fkey
  FOREIGN KEY (receiver_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- notifications.user_id — CASCADE
ALTER TABLE public.notifications DROP CONSTRAINT IF EXISTS notifications_user_id_fkey;
ALTER TABLE public.notifications ADD CONSTRAINT notifications_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- staff_invites.user_id — CASCADE
ALTER TABLE public.staff_invites DROP CONSTRAINT IF EXISTS staff_invites_user_id_fkey;
ALTER TABLE public.staff_invites ADD CONSTRAINT staff_invites_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- staff_invites.invited_by — CASCADE
ALTER TABLE public.staff_invites DROP CONSTRAINT IF EXISTS staff_invites_invited_by_fkey;
ALTER TABLE public.staff_invites ADD CONSTRAINT staff_invites_invited_by_fkey
  FOREIGN KEY (invited_by) REFERENCES public.users(id) ON DELETE CASCADE;

-- student_attendance.recorded_by — CASCADE
ALTER TABLE public.student_attendance DROP CONSTRAINT IF EXISTS student_attendance_recorded_by_fkey;
ALTER TABLE public.student_attendance ADD CONSTRAINT student_attendance_recorded_by_fkey
  FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE CASCADE;

-- parent_students.parent_user_id — CASCADE (NOT NULL column)
ALTER TABLE public.parent_students DROP CONSTRAINT IF EXISTS parent_students_parent_user_id_fkey;
ALTER TABLE public.parent_students ADD CONSTRAINT parent_students_parent_user_id_fkey
  FOREIGN KEY (parent_user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- message_reads.user_id — CASCADE
ALTER TABLE public.message_reads DROP CONSTRAINT IF EXISTS message_reads_user_id_fkey;
ALTER TABLE public.message_reads ADD CONSTRAINT message_reads_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

-- payments.recorded_by — CASCADE
ALTER TABLE public.payments DROP CONSTRAINT IF EXISTS payments_recorded_by_fkey;
ALTER TABLE public.payments ADD CONSTRAINT payments_recorded_by_fkey
  FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE CASCADE;

-- ============================================================
-- 2. REFERENCES staff(id)
-- ============================================================

-- deductions.staff_id — CASCADE
ALTER TABLE public.deductions DROP CONSTRAINT IF EXISTS deductions_staff_id_fkey;
ALTER TABLE public.deductions ADD CONSTRAINT deductions_staff_id_fkey
  FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;

-- salary_structures.staff_id — CASCADE
ALTER TABLE public.salary_structures DROP CONSTRAINT IF EXISTS salary_structures_staff_id_fkey;
ALTER TABLE public.salary_structures ADD CONSTRAINT salary_structures_staff_id_fkey
  FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;

-- payroll.staff_id — CASCADE
ALTER TABLE public.payroll DROP CONSTRAINT IF EXISTS payroll_staff_id_fkey;
ALTER TABLE public.payroll ADD CONSTRAINT payroll_staff_id_fkey
  FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;

-- staff_attendance.staff_id — CASCADE
ALTER TABLE public.staff_attendance DROP CONSTRAINT IF EXISTS staff_attendance_staff_id_fkey;
ALTER TABLE public.staff_attendance ADD CONSTRAINT staff_attendance_staff_id_fkey
  FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;

-- staff_invites.staff_id — CASCADE
ALTER TABLE public.staff_invites DROP CONSTRAINT IF EXISTS staff_invites_staff_id_fkey;
ALTER TABLE public.staff_invites ADD CONSTRAINT staff_invites_staff_id_fkey
  FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;

-- subjects.teacher_id — CASCADE (deleting staff removes subject assignments)
ALTER TABLE public.subjects DROP CONSTRAINT IF EXISTS subjects_teacher_id_fkey;
ALTER TABLE public.subjects ADD CONSTRAINT subjects_teacher_id_fkey
  FOREIGN KEY (teacher_id) REFERENCES public.staff(id) ON DELETE CASCADE;

-- ============================================================
-- 3. REFERENCES students(id)
-- ============================================================

-- attendance_summary.student_id — CASCADE
ALTER TABLE public.attendance_summary DROP CONSTRAINT IF EXISTS attendance_summary_student_id_fkey;
ALTER TABLE public.attendance_summary ADD CONSTRAINT attendance_summary_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- exam_scores.student_id — CASCADE
ALTER TABLE public.exam_scores DROP CONSTRAINT IF EXISTS exam_scores_student_id_fkey;
ALTER TABLE public.exam_scores ADD CONSTRAINT exam_scores_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- sba_scores.student_id — CASCADE
ALTER TABLE public.sba_scores DROP CONSTRAINT IF EXISTS sba_scores_student_id_fkey;
ALTER TABLE public.sba_scores ADD CONSTRAINT sba_scores_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- student_attendance.student_id — CASCADE
ALTER TABLE public.student_attendance DROP CONSTRAINT IF EXISTS student_attendance_student_id_fkey;
ALTER TABLE public.student_attendance ADD CONSTRAINT student_attendance_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- parent_students.student_id — CASCADE
ALTER TABLE public.parent_students DROP CONSTRAINT IF EXISTS parent_students_student_id_fkey;
ALTER TABLE public.parent_students ADD CONSTRAINT parent_students_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- payments.student_id — CASCADE
ALTER TABLE public.payments DROP CONSTRAINT IF EXISTS payments_student_id_fkey;
ALTER TABLE public.payments ADD CONSTRAINT payments_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- report_cards.student_id — CASCADE
ALTER TABLE public.report_cards DROP CONSTRAINT IF EXISTS report_cards_student_id_fkey;
ALTER TABLE public.report_cards ADD CONSTRAINT report_cards_student_id_fkey
  FOREIGN KEY (student_id) REFERENCES public.students(id) ON DELETE CASCADE;

-- ============================================================
-- 4. REFERENCES classes(id)
-- ============================================================

-- fee_structures.class_id — CASCADE
ALTER TABLE public.fee_structures DROP CONSTRAINT IF EXISTS fee_structures_class_id_fkey;
ALTER TABLE public.fee_structures ADD CONSTRAINT fee_structures_class_id_fkey
  FOREIGN KEY (class_id) REFERENCES public.classes(id) ON DELETE CASCADE;

-- subjects.class_id — CASCADE
ALTER TABLE public.subjects DROP CONSTRAINT IF EXISTS subjects_class_id_fkey;
ALTER TABLE public.subjects ADD CONSTRAINT subjects_class_id_fkey
  FOREIGN KEY (class_id) REFERENCES public.classes(id) ON DELETE CASCADE;

-- student_attendance.class_id — CASCADE
ALTER TABLE public.student_attendance DROP CONSTRAINT IF EXISTS student_attendance_class_id_fkey;
ALTER TABLE public.student_attendance ADD CONSTRAINT student_attendance_class_id_fkey
  FOREIGN KEY (class_id) REFERENCES public.classes(id) ON DELETE CASCADE;

-- ============================================================
-- 5. REFERENCES subjects(id)
-- ============================================================

-- exam_scores.subject_id — CASCADE
ALTER TABLE public.exam_scores DROP CONSTRAINT IF EXISTS exam_scores_subject_id_fkey;
ALTER TABLE public.exam_scores ADD CONSTRAINT exam_scores_subject_id_fkey
  FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;

-- sba_scores.subject_id — CASCADE
ALTER TABLE public.sba_scores DROP CONSTRAINT IF EXISTS sba_scores_subject_id_fkey;
ALTER TABLE public.sba_scores ADD CONSTRAINT sba_scores_subject_id_fkey
  FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;

-- ============================================================
-- 6. REFERENCES terms(id)
-- ============================================================

-- exam_scores.term_id — CASCADE
ALTER TABLE public.exam_scores DROP CONSTRAINT IF EXISTS exam_scores_term_id_fkey;
ALTER TABLE public.exam_scores ADD CONSTRAINT exam_scores_term_id_fkey
  FOREIGN KEY (term_id) REFERENCES public.terms(id) ON DELETE CASCADE;

-- sba_scores.term_id — CASCADE
ALTER TABLE public.sba_scores DROP CONSTRAINT IF EXISTS sba_scores_term_id_fkey;
ALTER TABLE public.sba_scores ADD CONSTRAINT sba_scores_term_id_fkey
  FOREIGN KEY (term_id) REFERENCES public.terms(id) ON DELETE CASCADE;

-- report_cards.term_id — CASCADE
ALTER TABLE public.report_cards DROP CONSTRAINT IF EXISTS report_cards_term_id_fkey;
ALTER TABLE public.report_cards ADD CONSTRAINT report_cards_term_id_fkey
  FOREIGN KEY (term_id) REFERENCES public.terms(id) ON DELETE CASCADE;

-- ============================================================
-- 7. REFERENCES messages(id)
-- ============================================================

-- message_reads.message_id — CASCADE
ALTER TABLE public.message_reads DROP CONSTRAINT IF EXISTS message_reads_message_id_fkey;
ALTER TABLE public.message_reads ADD CONSTRAINT message_reads_message_id_fkey
  FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;

-- ============================================================
-- 8. REFERENCES payroll(id)
-- ============================================================

-- pay_slips.payroll_id — CASCADE
ALTER TABLE public.pay_slips DROP CONSTRAINT IF EXISTS pay_slips_payroll_id_fkey;
ALTER TABLE public.pay_slips ADD CONSTRAINT pay_slips_payroll_id_fkey
  FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE CASCADE;

-- ============================================================
-- 9. REFERENCES payments(id)
-- ============================================================

-- receipts.payment_id — CASCADE
ALTER TABLE public.receipts DROP CONSTRAINT IF EXISTS receipts_payment_id_fkey;
ALTER TABLE public.receipts ADD CONSTRAINT receipts_payment_id_fkey
  FOREIGN KEY (payment_id) REFERENCES public.payments(id) ON DELETE CASCADE;

-- ============================================================
-- DONE
-- ============================================================
-- Verify by checking all FKs now have ON DELETE:
SELECT conname, pg_get_constraintdef(oid)
FROM pg_constraint
WHERE contype = 'f' AND connamespace = 'public'::regnamespace
ORDER BY conname;
