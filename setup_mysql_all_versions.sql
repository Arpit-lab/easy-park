-- ===============================================
-- EasyPark Smart Recommendations SQL - MySQL 5.7+ Compatible
-- ===============================================

-- STEP 1: Check your MySQL version
SELECT VERSION() as mysql_version;

-- STEP 2: Check current table structure
DESCRIBE parking_spaces;

-- STEP 3: Add columns (ignore errors if they already exist)
-- These will error if column exists, but that's OK - just means it's already there

ALTER TABLE parking_spaces ADD COLUMN distance_from_entry FLOAT DEFAULT 0;

ALTER TABLE parking_spaces ADD COLUMN priority_level INT DEFAULT 1;

-- STEP 4: Update with realistic values
UPDATE parking_spaces 
SET distance_from_entry = 25 
WHERE distance_from_entry IS NULL OR distance_from_entry = 0;

UPDATE parking_spaces 
SET priority_level = 1 
WHERE priority_level IS NULL OR priority_level = 0;

-- STEP 5: Verify data
SELECT 
    id,
    space_number,
    location_name,
    distance_from_entry,
    priority_level,
    is_available,
    status
FROM parking_spaces
LIMIT 10;

-- STEP 6: Check locations exist
SELECT DISTINCT location_name 
FROM parking_spaces 
WHERE status = 'active'
ORDER BY location_name;

-- STEP 7: If no spaces exist, add test data
-- ONLY RUN THIS IF THE SELECT ABOVE SHOWS NO ROWS
INSERT INTO parking_spaces 
(space_number, category_id, location_name, latitude, longitude, address, price_per_hour, status, distance_from_entry, priority_level) 
VALUES 
('A001', 1, 'Parking Center', 27.717, 85.324, '123 Main Street', 50.00, 'active', 15, 1),
('A002', 1, 'Parking Center', 27.717, 85.324, '123 Main Street', 50.00, 'active', 20, 1),
('A003', 1, 'Parking Center', 27.717, 85.324, '123 Main Street', 50.00, 'active', 25, 2),
('B001', 2, 'Parking Basement', 27.717, 85.324, '456 East Avenue', 75.00, 'active', 35, 1),
('B002', 2, 'Parking Basement', 27.717, 85.324, '456 East Avenue', 75.00, 'active', 40, 2),
('C001', 1, 'Parking North', 27.720, 85.330, '789 North Road', 60.00, 'active', 10, 1);

-- STEP 8: Final verification
SELECT COUNT(*) as total_spaces FROM parking_spaces WHERE status = 'active';
SELECT COUNT(DISTINCT location_name) as total_locations FROM parking_spaces WHERE status = 'active';
