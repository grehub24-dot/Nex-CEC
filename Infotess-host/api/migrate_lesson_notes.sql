-- ============================================================
-- migrate_lesson_notes.sql
--
-- Creates the lesson_notes system — rich lesson content
-- authored by admin/teachers, viewed by students.
--
-- Modes:
--   class_id = NULL   → "All Classes" — filtered by student's class
--   class_id = <id>   → "Assigned" — only that class sees it
-- ============================================================

-- Lesson notes table
CREATE TABLE IF NOT EXISTS lesson_notes (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    key_concepts TEXT NOT NULL DEFAULT '',
    subject_id INTEGER REFERENCES subjects(id) ON DELETE SET NULL,
    class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE,
    created_by INTEGER NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Pivot: attach existing resource_links to lesson notes
CREATE TABLE IF NOT EXISTS lesson_note_resources (
    id SERIAL PRIMARY KEY,
    lesson_note_id INTEGER NOT NULL REFERENCES lesson_notes(id) ON DELETE CASCADE,
    resource_link_id INTEGER NOT NULL REFERENCES resource_links(id) ON DELETE CASCADE,
    label VARCHAR(255) DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    UNIQUE(lesson_note_id, resource_link_id)
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_lesson_notes_class ON lesson_notes(class_id);
CREATE INDEX IF NOT EXISTS idx_lesson_notes_subject ON lesson_notes(subject_id);
CREATE INDEX IF NOT EXISTS idx_lesson_notes_active ON lesson_notes(is_active);
CREATE INDEX IF NOT EXISTS idx_lesson_notes_created_by ON lesson_notes(created_by);
CREATE INDEX IF NOT EXISTS idx_lnr_lesson ON lesson_note_resources(lesson_note_id);
