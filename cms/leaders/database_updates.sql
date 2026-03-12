-- Database Updates for Pastor Dashboard Logic

-- 1. People Table Updates
-- Ensure 'people' table has necessary columns for visitor pipeline
-- Note: Run these lines one by one if your DB doesn't support IF NOT EXISTS in ALTER

CREATE TABLE IF NOT EXISTS people (
    person_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add columns if they don't exist
ALTER TABLE people ADD COLUMN visit_type VARCHAR(50) DEFAULT 'First-Time Visitor';
ALTER TABLE people ADD COLUMN first_visit_date DATE;
ALTER TABLE people ADD COLUMN prayer_requests TEXT;
ALTER TABLE people ADD COLUMN follow_up_status VARCHAR(50) DEFAULT 'NEW'; -- NEW, ASSIGNED, CONTACTED, COMPLETE
ALTER TABLE people ADD COLUMN assigned_date DATETIME;
ALTER TABLE people ADD COLUMN last_contact_date DATETIME;

-- 2. Escalations Table
CREATE TABLE IF NOT EXISTS escalations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    followup_id INT,
    volunteer_id INT,
    escalation_date DATETIME,
    escalation_tier ENUM('Standard', 'Urgent', 'Emergency') DEFAULT 'Standard',
    escalation_reason VARCHAR(100), -- e.g., 'Financial Crisis', 'Mental Health'
    status ENUM('New', 'In Progress', 'Resolved') DEFAULT 'New',
    resolved_date DATETIME,
    outcome VARCHAR(100), -- e.g., 'Counseling Referral'
    crisis_protocol_followed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. VNPS Surveys Table
CREATE TABLE IF NOT EXISTS vnps_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT,
    vnps_score INT, -- 0-10
    quarter VARCHAR(10), -- e.g., 'Q1'
    year INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Notes Table (for Impact & Outcomes)
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50), -- 'Follow-Up', 'Person'
    entity_id INT,
    note_text TEXT,
    tags VARCHAR(255), -- Comma-separated tags
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT
);

-- 5. Volunteers Table Updates
-- Ensure 'level' column exists for Pipeline
ALTER TABLE volunteers ADD COLUMN level VARCHAR(50) DEFAULT 'Level 0';
ALTER TABLE volunteers ADD COLUMN completion_rate INT DEFAULT 0;
