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
--   hand2mind       → redirect only (X-Frame-Options: SAMEORIGIN — blocks external iframes)
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
--
-- Iframe policy reference (current as of May 2026):
--   khanacademy.org    → redirect (behind Fastly Shield — blocks iframes via DDoS challenge cookies)
--   blockly.games      → iframe OK (only object-src 'none' CSP)
--   scratch.mit.edu    → redirect only (SAMEORIGIN on main pages; /embed endpoint OK but needs specific project IDs)
--   kiddoworksheets    → iframe OK (no X-Frame-Options)
--   hand2mind          → redirect only (X-Frame-Options: SAMEORIGIN added mid-2026)
--   pbskids            → redirect only (DENY + strict CSP)
--   nasa.gov           → redirect only (SAMEORIGIN or DENY on all pages)
-- =============================================================

-- == HAND2MIND (redirect only — X-Frame-Options: SAMEORIGIN added mid-2026) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Hand2Mind — Free Resources Library', 'https://www.hand2mind.com/free-resources', 'hand2mind', 'all', NULL, NULL, 'Browse hundreds of free downloadable lesson plans, activity mats, and worksheets organized by grade and subject.', 'redirect', 1),

('Daily Foundational Literacy Lessons', 'https://www.hand2mind.com/learn/daily-foundational-literacy-lessons', 'hand2mind', 'literacy', NULL, NULL, 'Ready-to-use daily literacy lessons covering phonemic awareness, phonics, fluency, vocabulary, and comprehension.', 'redirect', 2),

('Math Daily Lessons', 'https://www.hand2mind.com/learn/math-daily-lessons', 'hand2mind', 'math', NULL, NULL, '20-minute daily math lessons with hands-on activities aligned to curriculum standards.', 'redirect', 3),

('Hand2Mind — Math Manipulatives', 'https://www.hand2mind.com/subjects/math', 'hand2mind', 'math', NULL, NULL, 'Browse hands-on math resources: base ten blocks, counters, pattern blocks, fraction tiles, and more.', 'redirect', 4),

('Hand2Mind — Literacy Resources', 'https://www.hand2mind.com/subjects/literacy', 'hand2mind', 'literacy', NULL, NULL, 'Literacy tools: letter tiles, reading rods, sound boxes, decodable books, and phonics kits.', 'redirect', 5),

('Hand2Mind — STEM Activities', 'https://www.hand2mind.com/subjects/stem', 'hand2mind', 'stem', NULL, NULL, 'Science, technology, engineering, and math activity kits and lesson ideas for hands-on STEM learning.', 'redirect', 6),

('Hand2Mind — Social-Emotional Learning', 'https://www.hand2mind.com/subjects/sel', 'hand2mind', 'sel', NULL, NULL, 'SEL resources: mindfulness tools, emotion cards, calming kits, and classroom management solutions.', 'redirect', 7),

('Hand2Mind — Early Childhood', 'https://www.hand2mind.com/subjects/early-childhood', 'hand2mind', 'ece', NULL, NULL, 'PreK and early childhood resources: fine motor tools, sensory play, early math and literacy foundations.', 'redirect', 8),

('Hand2Mind — Intervention Resources', 'https://www.hand2mind.com/subjects/intervention', 'hand2mind', 'all', NULL, NULL, 'Targeted intervention resources for struggling learners: RTI tiers, special education, and differentiation tools.', 'redirect', 9);

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

-- == PBS KIDS (redirect only — DENY + strict CSP. Opens via interstitial.) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('PBS Kids — Math Games', 'https://pbskids.org/games/math/', 'pbskids', 'math', NULL, NULL, 'Fun math games featuring PBS characters: Peg + Cat, Odd Squad, Cyberchase and more. Ages 4-8.', 'redirect', 18),

('PBS Kids — Reading Games', 'https://pbskids.org/games/reading/', 'pbskids', 'literacy', NULL, NULL, 'Literacy games with Super Why!, WordGirl, Martha Speaks, and others. Build phonics and vocabulary skills.', 'redirect', 19),

('PBS Kids — Science Games', 'https://pbskids.org/games/science/', 'pbskids', 'stem', NULL, NULL, 'Science exploration games: The Cat in the Hat, Wild Kratts, Splash and Bubbles. Ages 3-8.', 'redirect', 20),

('PBS Kids — Social-Emotional Games', 'https://pbskids.org/games/social-emotional/', 'pbskids', 'sel', NULL, NULL, 'SEL games featuring Daniel Tiger, Arthur, and Clifford: feelings, friendship, and empathy.', 'redirect', 21),

('PBS Kids — Creative Games', 'https://pbskids.org/games/creative/', 'pbskids', 'art', NULL, NULL, 'Art, music, and creative expression games: Pinkalicious, Nature Cat, and Xavier Riddle.', 'redirect', 22),

('PBS Kids — Video Library', 'https://pbskids.org/video/', 'pbskids', 'all', NULL, NULL, 'Educational video clips and full episodes from all PBS Kids shows. Classroom-safe curated content.', 'redirect', 23),

('PBS Kids — Printables', 'https://pbskids.org/printables/', 'pbskids', 'printables', NULL, NULL, 'Coloring pages, activity sheets, and craft templates featuring PBS Kids characters.', 'redirect', 24),

('PBS Kids — Parents (At-Home Learning)', 'https://pbskids.org/parents/', 'pbskids', 'all', NULL, NULL, 'Activity ideas, parenting tips, and at-home learning resources organized by age and topic.', 'redirect', 25);

-- == KHAN ACADEMY (redirect — Fastly Shield blocks iframes with DDoS challenge cookies) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Khan Academy — Math (K-12)', 'https://www.khanacademy.org/math', 'khanacademy', 'math', NULL, NULL, 'Complete K-12 math curriculum: arithmetic, algebra, geometry, trigonometry, calculus, and statistics. Video lessons + practice exercises.', 'redirect', 26),

('Khan Academy — Science & Engineering', 'https://www.khanacademy.org/science', 'khanacademy', 'stem', NULL, NULL, 'Physics, chemistry, biology, earth science, and engineering. NGSS-aligned video lessons with interactive exercises.', 'redirect', 27),

('Khan Academy — Reading & Grammar', 'https://www.khanacademy.org/ela', 'khanacademy', 'literacy', NULL, NULL, 'Reading comprehension, vocabulary, grammar, and writing skills for all grade levels. Novel study guides included.', 'redirect', 28),

('Khan Academy — Computing (Coding)', 'https://www.khanacademy.org/computing', 'khanacademy', 'coding', NULL, NULL, 'Learn JavaScript, HTML/CSS, and SQL through interactive coding challenges and projects. Ideal for JHS ICT.', 'redirect', 29),

('Khan Academy — Early Math (Ages 2-8)', 'https://www.khanacademy.org/math/early-math', 'khanacademy', 'math', NULL, NULL, 'Counting, shapes, addition, subtraction, place value, and measurement for early learners (Creche through Basic 2).', 'redirect', 30),

('Khan Academy — Arts & Humanities', 'https://www.khanacademy.org/humanities', 'khanacademy', 'art', NULL, NULL, 'Art history, music, world history, civics, and literature. Rich video content with primary source explorations.', 'redirect', 31),

('Khan Academy — Test Prep', 'https://www.khanacademy.org/test-prep', 'khanacademy', 'all', NULL, NULL, 'SAT, LSAT, and other standardized test preparation with personalized practice plans and progress tracking.', 'redirect', 32);

-- == SCRATCH (redirect only — SAMEORIGIN on main pages; use /projects/X/embed for iframe with specific projects) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Scratch — Explore Projects', 'https://scratch.mit.edu/explore', 'scratch', 'coding', NULL, NULL, 'Browse millions of community-created games, animations, and stories. Filter by subject and grade level.', 'redirect', 33),

('Scratch — Create (Project Editor)', 'https://scratch.mit.edu/projects/editor/', 'scratch', 'coding', NULL, NULL, 'The Scratch block-based coding editor. Create games, animations, and interactive stories — no account needed to start.', 'redirect', 34),

('Scratch — About & Tutorials', 'https://scratch.mit.edu/about', 'scratch', 'coding', NULL, NULL, 'Getting-started tutorials, educator guides, and creative learning resources from the MIT Media Lab.', 'redirect', 35),

('ScratchJr — Coding for Ages 5-7', 'https://www.scratchjr.org/', 'scratch', 'coding', NULL, NULL, 'Introductory programming for young children (ages 5-7). Create interactive stories and games with a tablet-friendly interface.', 'redirect', 36);

-- == BLOCKLY GAMES (iframe OK — no X-Frame-Options, minimal CSP) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Blockly Games — Maze', 'https://blockly.games/maze', 'blockly', 'coding', NULL, NULL, 'Navigate a maze using block-based code. Teaches sequence, loops, and conditionals — perfect for Basic 1-3.', 'iframe', 37),

('Blockly Games — Bird', 'https://blockly.games/bird', 'blockly', 'coding', NULL, NULL, 'Help a bird navigate obstacles using conditional logic. Introduces if/else and boolean thinking. Basic 1-4.', 'iframe', 38),

('Blockly Games — Turtle', 'https://blockly.games/turtle', 'blockly', 'coding', NULL, NULL, 'Draw geometric patterns using loops and procedures. Great for Basic 2-5 math + coding integration.', 'iframe', 39),

('Blockly Games — Movie', 'https://blockly.games/movie', 'blockly', 'coding', NULL, NULL, 'Create simple animations by sequencing commands. Introduces events and parallel execution. Basic 3-6.', 'iframe', 40),

('Blockly Games — Music', 'https://blockly.games/music', 'blockly', 'coding', NULL, NULL, 'Compose melodies using block code. Combines music theory with programming concepts. Basic 3-6.', 'iframe', 41),

('Blockly Games — Pond Tutor', 'https://blockly.games/pond-tutor', 'blockly', 'coding', NULL, NULL, 'Advanced puzzle: program a duck to navigate a pond. Introduces JavaScript syntax. Basic 4+ and JHS.', 'iframe', 42),

('Blockly Games — Pond', 'https://blockly.games/pond', 'blockly', 'coding', NULL, NULL, 'Free-form tank battle coding game. Write JavaScript to defeat opponent ducks. JHS level.', 'iframe', 43);

-- == NASA (redirect only — SAMEORIGIN or DENY on all pages) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('NASA — Kids'' Club', 'https://www.nasa.gov/learning-resources/nasa-kids-club/', 'nasa', 'stem', NULL, NULL, 'NASA''s official kids portal: games, activities, and information about space, rockets, planets, and astronauts. Ages 5-12.', 'redirect', 44),

('NASA — Space Place', 'https://spaceplace.nasa.gov/', 'nasa', 'stem', NULL, NULL, 'Explore Earth, the Sun, solar system, and universe with interactive games, crafts, and science experiments. Ages 8-14.', 'redirect', 45),

('NASA — Climate Kids', 'https://climatekids.nasa.gov/', 'nasa', 'stem', NULL, NULL, 'Learn about climate change, weather, and Earth science through games, projects, and NASA data visualizations. Ages 8-14.', 'redirect', 46),

('NASA — Solar System Exploration', 'https://solarsystem.nasa.gov/', 'nasa', 'stem', NULL, NULL, 'Interactive guide to our solar system: planets, moons, asteroids, and comets with real NASA mission data and images.', 'redirect', 47),

('NASA — Image of the Day', 'https://www.nasa.gov/image-of-the-day/', 'nasa', 'art', NULL, NULL, 'Stunning daily astronomy and Earth imagery from NASA''s satellites, telescopes, and space missions.', 'redirect', 48);
