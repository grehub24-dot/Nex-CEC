-- ==========================================
-- SUPABASE SCHEMA: INFOTESS / NEX CEC
-- ==========================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. SCHOOLS
CREATE TABLE schools (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(100),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE schools ENABLE ROW LEVEL SECURITY;

-- 2. PROFILES (Linked to Supabase Auth)
CREATE TABLE profiles (
    id UUID REFERENCES auth.users ON DELETE CASCADE PRIMARY KEY,
    school_id UUID REFERENCES schools(id),
    full_name VARCHAR(255),
    role VARCHAR(20) CHECK (role IN ('super_admin', 'admin', 'bursar', 'teacher', 'parent')),
    phone VARCHAR(50),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;

-- 3. STUDENTS
CREATE TABLE students (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    school_id UUID REFERENCES schools(id) ON DELETE SET NULL,
    parent_id UUID REFERENCES profiles(id),
    index_number VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    class_name VARCHAR(50),
    level VARCHAR(20),
    stream VARCHAR(20),
    phone VARCHAR(50),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE students ENABLE ROW LEVEL SECURITY;

-- 4. CLASSES
CREATE TABLE classes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    school_id UUID REFERENCES schools(id) ON DELETE CASCADE,
    name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE classes ENABLE ROW LEVEL SECURITY;

-- 5. FEE STRUCTURES
CREATE TABLE fee_structures (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    school_id UUID REFERENCES schools(id) ON DELETE CASCADE,
    class_id UUID REFERENCES classes(id),
    title VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    academic_year VARCHAR(20),
    term VARCHAR(20),
    is_mandatory BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE fee_structures ENABLE ROW LEVEL SECURITY;

-- 6. PAYMENTS
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    student_id UUID REFERENCES students(id) ON DELETE CASCADE,
    fee_structure_id UUID REFERENCES fee_structures(id),
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    recorded_by UUID REFERENCES profiles(id),
    payment_date DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE payments ENABLE ROW LEVEL SECURITY;

-- ==========================================
-- SECURITY POLICIES (Basic Setup)
-- ==========================================

-- Allow authenticated users to read their own school data
CREATE POLICY "Users can view own school data" ON schools
    FOR SELECT USING (auth.uid() IN (SELECT id FROM profiles WHERE school_id = schools.id));

-- Allow admins to manage their school data
CREATE POLICY "Admins can manage school data" ON schools
    FOR ALL USING (
        auth.uid() IN (
            SELECT id FROM profiles WHERE role IN ('super_admin', 'admin', 'bursar')
        )
    );

-- Similar policies would be added for other tables in production.
