-- =============================================================
-- Migration: Create resource_assignments table
-- Allows teachers to assign curated resources to their classes
-- as homework/learning tasks with a due date.
-- =============================================================

CREATE TABLE IF NOT EXISTS resource_assignments (
    id SERIAL PRIMARY KEY,
    resource_id INT NOT NULL REFERENCES resource_links(id) ON DELETE CASCADE,
    teacher_id INT NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    class_id INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    subject_id INT REFERENCES subjects(id) ON DELETE SET NULL,
    instructions TEXT DEFAULT '',
    due_date DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index for fast lookups by teacher
CREATE INDEX IF NOT EXISTS idx_resource_assignments_teacher ON resource_assignments(teacher_id);

-- Index for fast lookups by class (student portal)
CREATE INDEX IF NOT EXISTS idx_resource_assignments_class ON resource_assignments(class_id);
