-- ============================================================
-- ORPHAN DETECTOR — find every FK violation across the schema
-- ============================================================
-- Run BEFORE fix-fk-cascade.sql. If this returns rows, those
-- records must be deleted before ON DELETE CASCADE can be added.
-- ============================================================

-- 1. REFERENCES students(id)
SELECT 'exam_scores' AS tbl, 'student_id' AS col, es.student_id::text AS orphan_id
FROM exam_scores es LEFT JOIN students s ON s.id = es.student_id WHERE s.id IS NULL
UNION ALL
SELECT 'sba_scores', 'student_id', sba.student_id::text
FROM sba_scores sba LEFT JOIN students s ON s.id = sba.student_id WHERE s.id IS NULL
UNION ALL
SELECT 'student_attendance', 'student_id', sa.student_id::text
FROM student_attendance sa LEFT JOIN students s ON s.id = sa.student_id WHERE s.id IS NULL
UNION ALL
SELECT 'payments', 'student_id', p.student_id::text
FROM payments p LEFT JOIN students s ON s.id = p.student_id WHERE s.id IS NULL
UNION ALL
SELECT 'report_cards', 'student_id', rc.student_id::text
FROM report_cards rc LEFT JOIN students s ON s.id = rc.student_id WHERE s.id IS NULL
UNION ALL
SELECT 'parent_students', 'student_id', ps.student_id::text
FROM parent_students ps LEFT JOIN students s ON s.id = ps.student_id WHERE s.id IS NULL
UNION ALL
SELECT 'attendance_summary', 'student_id', a.student_id::text
FROM attendance_summary a LEFT JOIN students s ON s.id = a.student_id WHERE s.id IS NULL

-- 2. REFERENCES subjects(id)
UNION ALL
SELECT 'exam_scores', 'subject_id', es.subject_id::text
FROM exam_scores es LEFT JOIN subjects s ON s.id = es.subject_id WHERE s.id IS NULL
UNION ALL
SELECT 'sba_scores', 'subject_id', sba.subject_id::text
FROM sba_scores sba LEFT JOIN subjects s ON s.id = sba.subject_id WHERE s.id IS NULL

-- 3. REFERENCES terms(id)
UNION ALL
SELECT 'exam_scores', 'term_id', es.term_id::text
FROM exam_scores es LEFT JOIN terms t ON t.id = es.term_id WHERE t.id IS NULL
UNION ALL
SELECT 'sba_scores', 'term_id', sba.term_id::text
FROM sba_scores sba LEFT JOIN terms t ON t.id = sba.term_id WHERE t.id IS NULL
UNION ALL
SELECT 'report_cards', 'term_id', rc.term_id::text
FROM report_cards rc LEFT JOIN terms t ON t.id = rc.term_id WHERE t.id IS NULL

-- 4. REFERENCES classes(id)
UNION ALL
SELECT 'fee_structures', 'class_id', fs.class_id::text
FROM fee_structures fs LEFT JOIN classes c ON c.id = fs.class_id WHERE c.id IS NULL
UNION ALL
SELECT 'subjects', 'class_id', s.class_id::text
FROM subjects s LEFT JOIN classes c ON c.id = s.class_id WHERE c.id IS NULL
UNION ALL
SELECT 'student_attendance', 'class_id', sa.class_id::text
FROM student_attendance sa LEFT JOIN classes c ON c.id = sa.class_id WHERE c.id IS NULL

-- 5. REFERENCES users(id)
UNION ALL
SELECT 'staff', 'user_id', s.user_id::text
FROM staff s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL
UNION ALL
SELECT 'students', 'user_id', s.user_id::text
FROM students s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL
UNION ALL
SELECT 'executives', 'user_id', e.user_id::text
FROM executives e LEFT JOIN users u ON u.id = e.user_id WHERE u.id IS NULL
UNION ALL
SELECT 'messages', 'sender_id', m.sender_id::text
FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.sender_id IS NOT NULL AND u.id IS NULL
UNION ALL
SELECT 'messages', 'receiver_id', m.receiver_id::text
FROM messages m LEFT JOIN users u ON u.id = m.receiver_id WHERE m.receiver_id IS NOT NULL AND u.id IS NULL

-- 6. REFERENCES staff(id)
UNION ALL
SELECT 'salary_structures', 'staff_id', ss.staff_id::text
FROM salary_structures ss LEFT JOIN staff s ON s.id = ss.staff_id WHERE s.id IS NULL
UNION ALL
SELECT 'deductions', 'staff_id', d.staff_id::text
FROM deductions d LEFT JOIN staff s ON s.id = d.staff_id WHERE s.id IS NULL
UNION ALL
SELECT 'payroll', 'staff_id', p.staff_id::text
FROM payroll p LEFT JOIN staff s ON s.id = p.staff_id WHERE s.id IS NULL
UNION ALL
SELECT 'staff_attendance', 'staff_id', sa.staff_id::text
FROM staff_attendance sa LEFT JOIN staff s ON s.id = sa.staff_id WHERE s.id IS NULL
UNION ALL
SELECT 'staff_invites', 'staff_id', si.staff_id::text
FROM staff_invites si LEFT JOIN staff s ON s.id = si.staff_id WHERE s.id IS NULL
UNION ALL
SELECT 'subjects', 'teacher_id', s.teacher_id::text
FROM subjects s LEFT JOIN staff st ON st.id = s.teacher_id WHERE s.teacher_id IS NOT NULL AND st.id IS NULL

-- 7. REFERENCES payroll(id)
UNION ALL
SELECT 'pay_slips', 'payroll_id', ps.payroll_id::text
FROM pay_slips ps LEFT JOIN payroll p ON p.id = ps.payroll_id WHERE p.id IS NULL

-- 8. REFERENCES payments(id)
UNION ALL
SELECT 'receipts', 'payment_id', r.payment_id::text
FROM receipts r LEFT JOIN payments p ON p.id = r.payment_id WHERE p.id IS NULL

ORDER BY tbl, col;
