-- =============================================================
-- Migration: Create resource_links table + seed curated resources
-- =============================================================
-- This adds a managed resource library so admins can curate
-- external teaching/learning resources (TLMs, worksheets, games,
-- videos) and serve them under nexcec.com URLs (iframe or proxy).
--
-- embed_type:
--   'iframe'   → site allows iframing (SAMEORIGIN or no header)
--   'proxy'    → site blocks iframes — PHP proxy fetches & rewrites
--   'redirect' → interactive content (games) — opens via interstitial
--
-- Source guide:
--   hand2mind       → iframe OK (no X-Frame-Options)
--   kiddoworksheets → iframe OK (no X-Frame-Options)
--   pbskids         → redirect only (DENY + strict CSP)
-- =============================================================

-- Create table
CREATE TABLE IF NOT EXISTS resource_links (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    source VARCHAR(50) DEFAULT '',           -- 'hand2mind', 'pbskids', 'kiddoworksheets', etc.
    category VARCHAR(100) DEFAULT '',        -- 'math', 'literacy', 'stem', 'ece', 'printables', 'sel', 'art'
    class_id INT DEFAULT NULL,              -- NULL = all classes
    subject_id INT DEFAULT NULL,            -- NULL = all subjects
    description TEXT DEFAULT '',
    embed_type VARCHAR(20) DEFAULT 'iframe', -- 'iframe', 'proxy', 'redirect'
    is_active BOOLEAN DEFAULT true,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================
-- Seed data — curated educational resources
-- =============================================================

-- == HAND2MIND (iframe OK, ECE through Basic 6) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Hand2Mind — Free Resources Library', 'https://www.hand2mind.com/free-resources', 'hand2mind', 'all', NULL, NULL, 'Browse hundreds of free downloadable lesson plans, activity mats, and worksheets organized by grade and subject.', 'iframe', 1),

('Daily Foundational Literacy Lessons', 'https://www.hand2mind.com/learn/daily-foundational-literacy-lessons', 'hand2mind', 'literacy', NULL, NULL, 'Ready-to-use daily literacy lessons covering phonemic awareness, phonics, fluency, vocabulary, and comprehension.', 'iframe', 2),

('Math Daily Lessons', 'https://www.hand2mind.com/learn/math-daily-lessons', 'hand2mind', 'math', NULL, NULL, '20-minute daily math lessons with hands-on activities aligned to curriculum standards.', 'iframe', 3),

('Hand2Mind — Math Manipulatives', 'https://www.hand2mind.com/subjects/math', 'hand2mind', 'math', NULL, NULL, 'Browse hands-on math resources: base ten blocks, counters, pattern blocks, fraction tiles, and more.', 'iframe', 4),

('Hand2Mind — Literacy Resources', 'https://www.hand2mind.com/subjects/literacy', 'hand2mind', 'literacy', NULL, NULL, 'Literacy tools: letter tiles, reading rods, sound boxes, decodable books, and phonics kits.', 'iframe', 5),

('Hand2Mind — STEM Activities', 'https://www.hand2mind.com/subjects/stem', 'hand2mind', 'stem', NULL, NULL, 'Science, technology, engineering, and math activity kits and lesson ideas for hands-on STEM learning.', 'iframe', 6),

('Hand2Mind — Social-Emotional Learning', 'https://www.hand2mind.com/subjects/sel', 'hand2mind', 'sel', NULL, NULL, 'SEL resources: mindfulness tools, emotion cards, calming kits, and classroom management solutions.', 'iframe', 7),

('Hand2Mind — Early Childhood', 'https://www.hand2mind.com/subjects/early-childhood', 'hand2mind', 'ece', NULL, NULL, 'PreK and early childhood resources: fine motor tools, sensory play, early math and literacy foundations.', 'iframe', 8),

('Hand2Mind — Intervention Resources', 'https://www.hand2mind.com/subjects/intervention', 'hand2mind', 'all', NULL, NULL, 'Targeted intervention resources for struggling learners: RTI tiers, special education, and differentiation tools.', 'iframe', 9);

-- == KIDDOWORKSHEETS (iframe OK, nursery through Basic 6) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('KiddoWorksheets — All Printable Worksheets', 'https://www.kiddoworksheets.com/printable-worksheets/', 'kiddoworksheets', 'printables', NULL, NULL, 'Huge library of free printable worksheets organized by subject and grade level.', 'iframe', 10),

('KiddoWorksheets — Math Worksheets', 'https://www.kiddoworksheets.com/math-worksheets/', 'kiddoworksheets', 'math', NULL, NULL, 'Printable math worksheets: addition, subtraction, multiplication, division, fractions, counting, and number patterns.', 'iframe', 11),

('KiddoWorksheets — Alphabet Tracing', 'https://www.kiddoworksheets.com/alphabet-tracing/', 'kiddoworksheets', 'literacy', NULL, NULL, 'Letter tracing worksheets for nursery and reception: uppercase and lowercase alphabet practice.', 'iframe', 12),

('KiddoWorksheets — Cursive Writing', 'https://www.kiddoworksheets.com/cursive-writing/', 'kiddoworksheets', 'literacy', NULL, NULL, 'Cursive handwriting practice sheets — ideal for Basic 3 and above.', 'iframe', 13),

('KiddoWorksheets — Shapes & Colors', 'https://www.kiddoworksheets.com/shapes/', 'kiddoworksheets', 'ece', NULL, NULL, 'Shape recognition, color matching, and pattern worksheets for early learners (Creche & Nursery).', 'iframe', 14),

('KiddoWorksheets — Flashcards', 'https://www.kiddoworksheets.com/flashcards/', 'kiddoworksheets', 'printables', NULL, NULL, 'Printable flashcards: numbers, letters, sight words, and vocabulary cards for classroom use.', 'iframe', 15),

('KiddoWorksheets — Number Tracing', 'https://www.kiddoworksheets.com/number-tracing/', 'kiddoworksheets', 'math', NULL, NULL, 'Number tracing worksheets 0-20 for nursery and reception math readiness.', 'iframe', 16),

('KiddoWorksheets — Missing Numbers', 'https://www.kiddoworksheets.com/missing-numbers/', 'kiddoworksheets', 'math', NULL, NULL, 'Fill-in-the-missing-number worksheets for Basic 1-2: number sequences and skip counting.', 'iframe', 17);

-- == PBS KIDS (redirect only — blocks iframes. Opens via interstitial.) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('PBS Kids — Math Games', 'https://pbskids.org/games/math/', 'pbskids', 'math', NULL, NULL, 'Fun math games featuring PBS characters: Peg + Cat, Odd Squad, Cyberchase and more. Ages 4-8.', 'redirect', 18),

('PBS Kids — Reading Games', 'https://pbskids.org/games/reading/', 'pbskids', 'literacy', NULL, NULL, 'Literacy games with Super Why!, WordGirl, Martha Speaks, and others. Build phonics and vocabulary skills.', 'redirect', 19),

('PBS Kids — Science Games', 'https://pbskids.org/games/science/', 'pbskids', 'stem', NULL, NULL, 'Science exploration games: The Cat in the Hat, Wild Kratts, Splash and Bubbles. Ages 3-8.', 'redirect', 20),

('PBS Kids — Social-Emotional Games', 'https://pbskids.org/games/social-emotional/', 'pbskids', 'sel', NULL, NULL, 'SEL games featuring Daniel Tiger, Arthur, and Clifford: feelings, friendship, and empathy.', 'redirect', 21),

('PBS Kids — Creative Games', 'https://pbskids.org/games/creative/', 'pbskids', 'art', NULL, NULL, 'Art, music, and creative expression games: Pinkalicious, Nature Cat, and Xavier Riddle.', 'redirect', 22),

('PBS Kids — Video Library', 'https://pbskids.org/video/', 'pbskids', 'all', NULL, NULL, 'Educational video clips and full episodes from all PBS Kids shows. Classroom-safe curated content.', 'redirect', 23),

('PBS Kids — Printables', 'https://pbskids.org/printables/', 'pbskids', 'printables', NULL, NULL, 'Coloring pages, activity sheets, and craft templates featuring PBS Kids characters.', 'redirect', 24),

('PBS Kids — Parents (At-Home Learning)', 'https://pbskids.org/parents/', 'pbskids', 'all', NULL, NULL, 'Activity ideas, parenting tips, and at-home learning resources organized by age and topic.', 'redirect', 25);
