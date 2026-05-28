-- =============================================================
-- Migration v2: Add Khan Academy, Scratch, Blockly, NASA resources
-- =============================================================
-- Run this after migrate_resource_links.sql to add new platform
-- resources to an existing resource_links table.
--
-- New sources added:
--   khanacademy   → iframe OK (no restrictions)
--   scratch       → redirect only (SAMEORIGIN on main pages)
--   blockly       → iframe OK (no restrictions)
--   nasa          → redirect only (SAMEORIGIN or DENY)
-- =============================================================

-- == KHAN ACADEMY (iframe OK) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Khan Academy — Math (K-12)', 'https://www.khanacademy.org/math', 'khanacademy', 'math', NULL, NULL, 'Complete K-12 math curriculum: arithmetic, algebra, geometry, trigonometry, calculus, and statistics. Video lessons + practice exercises.', 'iframe', 26),

('Khan Academy — Science & Engineering', 'https://www.khanacademy.org/science', 'khanacademy', 'stem', NULL, NULL, 'Physics, chemistry, biology, earth science, and engineering. NGSS-aligned video lessons with interactive exercises.', 'iframe', 27),

('Khan Academy — Reading & Grammar', 'https://www.khanacademy.org/ela', 'khanacademy', 'literacy', NULL, NULL, 'Reading comprehension, vocabulary, grammar, and writing skills for all grade levels. Novel study guides included.', 'iframe', 28),

('Khan Academy — Computing (Coding)', 'https://www.khanacademy.org/computing', 'khanacademy', 'coding', NULL, NULL, 'Learn JavaScript, HTML/CSS, and SQL through interactive coding challenges and projects. Ideal for JHS ICT.', 'iframe', 29),

('Khan Academy — Early Math (Ages 2-8)', 'https://www.khanacademy.org/math/early-math', 'khanacademy', 'math', NULL, NULL, 'Counting, shapes, addition, subtraction, place value, and measurement for early learners (Creche through Basic 2).', 'iframe', 30),

('Khan Academy — Arts & Humanities', 'https://www.khanacademy.org/humanities', 'khanacademy', 'art', NULL, NULL, 'Art history, music, world history, civics, and literature. Rich video content with primary source explorations.', 'iframe', 31),

('Khan Academy — Test Prep', 'https://www.khanacademy.org/test-prep', 'khanacademy', 'all', NULL, NULL, 'SAT, LSAT, and other standardized test preparation with personalized practice plans and progress tracking.', 'iframe', 32);

-- == SCRATCH (redirect only — SAMEORIGIN on main pages) ==
INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order) VALUES
('Scratch — Explore Projects', 'https://scratch.mit.edu/explore', 'scratch', 'coding', NULL, NULL, 'Browse millions of community-created games, animations, and stories. Filter by subject and grade level.', 'redirect', 33),

('Scratch — Create (Project Editor)', 'https://scratch.mit.edu/projects/editor/', 'scratch', 'coding', NULL, NULL, 'The Scratch block-based coding editor. Create games, animations, and interactive stories — no account needed to start.', 'redirect', 34),

('Scratch — About & Tutorials', 'https://scratch.mit.edu/about', 'scratch', 'coding', NULL, NULL, 'Getting-started tutorials, educator guides, and creative learning resources from the MIT Media Lab.', 'redirect', 35),

('ScratchJr — Coding for Ages 5-7', 'https://www.scratchjr.org/', 'scratch', 'coding', NULL, NULL, 'Introductory programming for young children (ages 5-7). Create interactive stories and games with a tablet-friendly interface.', 'redirect', 36);

-- == BLOCKLY GAMES (iframe OK) ==
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
