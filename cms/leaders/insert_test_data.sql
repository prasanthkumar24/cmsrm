SET FOREIGN_KEY_CHECKS = 0;

-- 1. Truncate Tables
TRUNCATE TABLE check_ins;
TRUNCATE TABLE escalations;
TRUNCATE TABLE followup_master;
TRUNCATE TABLE notes;
TRUNCATE TABLE people;
TRUNCATE TABLE vnps_surveys;
TRUNCATE TABLE volunteers;
TRUNCATE TABLE users;
TRUNCATE TABLE capacity_bands;

-- 2. Insert Capacity Bands
INSERT INTO capacity_bands (band_name, min_per_week, max_per_week, description) VALUES
('Consistent', 5, 10, 'High capacity, regular volunteer'),
('Balanced', 3, 5, 'Medium capacity, steady engagement'),
('Limited', 1, 2, 'Low capacity, occasional helper'),
('Pause', 0, 0, 'Currently on break');

-- 3. Insert Users (Team Leads & Pastor)
INSERT INTO users (username, password, role, full_name, mobile_number) VALUES
('pastor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pastor', 'Pastor John', '555-0101'), -- password: password
('tl_sarah', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Team Lead', 'Sarah Connor', '555-0102'),
('tl_mike', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Team Lead', 'Mike Ross', '555-0103');

-- 4. Insert Volunteers
-- Sarah's Team
INSERT INTO volunteers (volunteer_name, capacity_band, max_capacity, is_active, team_lead_id, mobile_number, email, status, start_date, completion_rate, vnps_score) VALUES
('Alice Smith', 'Consistent', 8, 'Yes', 2, '555-0201', 'alice@example.com', 'Active', DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 95.00, 9),
('Bob Jones', 'Balanced', 4, 'Yes', 2, '555-0202', 'bob@example.com', 'Active', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 80.00, 8),
('Charlie Brown', 'Limited', 2, 'Yes', 2, '555-0203', 'charlie@example.com', 'Active', DATE_SUB(CURDATE(), INTERVAL 1 YEAR), 100.00, 10);

-- Mike's Team
INSERT INTO volunteers (volunteer_name, capacity_band, max_capacity, is_active, team_lead_id, mobile_number, email, status, start_date, completion_rate, vnps_score) VALUES
('David Wilson', 'Consistent', 10, 'Yes', 3, '555-0301', 'david@example.com', 'Active', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 60.00, 6), -- Low performance
('Eve Miller', 'Balanced', 5, 'Yes', 3, '555-0302', 'eve@example.com', 'Active', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 90.00, 9),
('Frank White', 'Limited', 1, 'Yes', 3, '555-0303', 'frank@example.com', 'Active', DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 85.00, 7);

-- 5. Insert People (Visitors)
INSERT INTO people (person_name, mobile_number, is_assigned, visit_type, first_visit_date, follow_up_status, created_at) VALUES
('John Doe', '555-1001', 'Yes', 'First-Time Visitor', CURDATE(), 'ASSIGNED', NOW()),
('Jane Smith', '555-1002', 'Yes', 'First-Time Visitor', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'ASSIGNED', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Sam Wilson', '555-1003', 'Yes', 'First-Time Visitor', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'ASSIGNED', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('Lisa Ray', '555-1004', 'No', 'First-Time Visitor', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'NEW', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Tom Hanks', '555-1005', 'Yes', 'Second-Time Visitor', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'IN_PROGRESS', DATE_SUB(NOW(), INTERVAL 10 DAY));

-- 6. Insert Followup Master (Assignments)
-- Assignments for this month
INSERT INTO followup_master (volunteer_id, person_id, person_name, assigned_volunteer, date_assigned, status, updated_at, attempt_count, response_type) VALUES
-- Alice (High performer)
(1, 1, 'John Doe', 'Alice Smith', CURDATE(), 'Active', NOW(), 0, ''),
(1, 2, 'Jane Smith', 'Alice Smith', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Archive', NOW(), 1, 'Normal - Connected'),

-- Bob (Average)
(2, 3, 'Sam Wilson', 'Bob Jones', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Active', DATE_SUB(NOW(), INTERVAL 1 DAY), 2, 'Left Voicemail'),

-- David (Low performer - Overdue/Escalated)
(4, 5, 'Tom Hanks', 'David Wilson', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Escalated', NOW(), 3, 'No response');

-- Assignments for previous month (for trends)
INSERT INTO followup_master (volunteer_id, person_id, person_name, assigned_volunteer, date_assigned, status, updated_at, attempt_count, response_type) VALUES
(1, 4, 'Lisa Ray', 'Alice Smith', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'Archive', DATE_SUB(NOW(), INTERVAL 28 DAY), 1, 'Normal - Connected');


-- 7. Insert Escalations
INSERT INTO escalations (followup_id, volunteer_id, escalation_date, escalation_tier, escalation_reason, status, crisis_protocol_followed) VALUES
(4, 4, NOW(), 'Standard', 'No response after 3 attempts', 'New', 0);

-- 8. Insert vNPS Surveys
INSERT INTO vnps_surveys (survey_id, volunteer_id, survey_date, quarter, year, vnps_score, vnps_category, sentiment) VALUES
('S001', '1', CURDATE(), CONCAT('Q', CEIL(MONTH(CURDATE())/3)), YEAR(CURDATE()), 9, 'Promoter', 'Positive'),
('S002', '2', CURDATE(), CONCAT('Q', CEIL(MONTH(CURDATE())/3)), YEAR(CURDATE()), 8, 'Passive', 'Neutral'),
('S003', '4', CURDATE(), CONCAT('Q', CEIL(MONTH(CURDATE())/3)), YEAR(CURDATE()), 6, 'Detractor', 'Negative');

-- 9. Insert Check-ins
INSERT INTO check_ins (check_in_id, volunteer_id, team_lead_id, check_in_date, emotional_tone, next_check_in_date) VALUES
('C001', 1, 2, DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 'Positive', DATE_ADD(CURDATE(), INTERVAL 3 WEEK)),
('C002', 4, 3, DATE_SUB(CURDATE(), INTERVAL 2 WEEK), 'Concerned', DATE_ADD(CURDATE(), INTERVAL 1 WEEK));

SET FOREIGN_KEY_CHECKS = 1;
