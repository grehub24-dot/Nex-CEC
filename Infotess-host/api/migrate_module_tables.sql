-- ============================================================
-- PostgreSQL Migration: Module Tables for Admin Content Management
-- ============================================================
-- Run this in your Supabase Dashboard SQL Editor:
--   https://supabase.com/dashboard/project/tbkinaglugagloinecle/sql/new
-- ============================================================

-- 1. Executives table (school leadership/management)
CREATE TABLE IF NOT EXISTS executives (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    bio TEXT,
    image_url TEXT,
    email VARCHAR(255),
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. Alumni table (former students)
CREATE TABLE IF NOT EXISTS alumni (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    graduation_year VARCHAR(10) NOT NULL,
    position VARCHAR(255),
    company VARCHAR(255),
    image_url TEXT,
    testimonial TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. Gallery table (photo gallery items)
CREATE TABLE IF NOT EXISTS gallery (
    id BIGSERIAL PRIMARY KEY,
    caption VARCHAR(255),
    image_url TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'School',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 4. Projects table (school projects/initiatives)
CREATE TABLE IF NOT EXISTS projects (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url TEXT,
    project_date DATE,
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('completed', 'ongoing', 'planned')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 5. Contact submissions table (contact form inquiries)
CREATE TABLE IF NOT EXISTS contact_submissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    response TEXT,
    responded_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 6. Activities table (school activities/events for public display)
CREATE TABLE IF NOT EXISTS activities (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    activity_date TIMESTAMPTZ NOT NULL,
    image_url TEXT,
    registration_link VARCHAR(255),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Enable Row Level Security (optional — tables are public reads)
ALTER TABLE executives ENABLE ROW LEVEL SECURITY;
ALTER TABLE alumni ENABLE ROW LEVEL SECURITY;
ALTER TABLE gallery ENABLE ROW LEVEL SECURITY;
ALTER TABLE projects ENABLE ROW LEVEL SECURITY;
ALTER TABLE contact_submissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE activities ENABLE ROW LEVEL SECURITY;

-- Allow public read access for frontend display
-- (Uses DO blocks because CREATE POLICY does not support IF NOT EXISTS)
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public select for executives') THEN
        CREATE POLICY "Public select for executives" ON executives FOR SELECT USING (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public select for alumni') THEN
        CREATE POLICY "Public select for alumni" ON alumni FOR SELECT USING (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public select for gallery') THEN
        CREATE POLICY "Public select for gallery" ON gallery FOR SELECT USING (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public select for projects') THEN
        CREATE POLICY "Public select for projects" ON projects FOR SELECT USING (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public select for activities') THEN
        CREATE POLICY "Public select for activities" ON activities FOR SELECT USING (true);
    END IF;
    -- Allow public insert into contact_submissions (for the contact form)
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public insert for contact_submissions') THEN
        CREATE POLICY "Public insert for contact_submissions" ON contact_submissions FOR INSERT WITH CHECK (true);
    END IF;
END $$;

-- Allow service_role full access (admin operations)
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Service role all for executives') THEN
        CREATE POLICY "Service role all for executives" ON executives TO service_role USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Service role all for alumni') THEN
        CREATE POLICY "Service role all for alumni" ON alumni TO service_role USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Service role all for gallery') THEN
        CREATE POLICY "Service role all for gallery" ON gallery TO service_role USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Service role all for projects') THEN
        CREATE POLICY "Service role all for projects" ON projects TO service_role USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Service role all for contact_submissions') THEN
        CREATE POLICY "Service role all for contact_submissions" ON contact_submissions TO service_role USING (true) WITH CHECK (true);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Service role all for activities') THEN
        CREATE POLICY "Service role all for activities" ON activities TO service_role USING (true) WITH CHECK (true);
    END IF;
END $$;
