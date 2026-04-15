-- ==========================================
-- EasyPark Smart Recommendations - SQL Setup
-- ==========================================

-- 1. Add missing columns to parking_spaces if they don't exist
ALTER TABLE parking_spaces 
ADD COLUMN IF NOT EXISTS distance_from_entry FLOAT DEFAULT 0;

ALTER TABLE parking_spaces 
ADD COLUMN IF NOT EXISTS priority_level INT DEFAULT 1;

-- 2. Verify the columns now exist
DESC parking_spaces;

-- 3. Update with default values if they're empty
UPDATE parking_spaces SET distance_from_entry = 25 WHERE distance_from_entry <= 0 OR distance_from_entry IS NULL;
UPDATE parking_spaces SET priority_level = 1 WHERE priority_level IS NULL OR priority_level = 0;

-- 4. Update with more realistic values based on space number
UPDATE parking_spaces 
SET distance_from_entry = CASE 
    WHEN space_number LIKE 'A%' THEN 15
    WHEN space_number LIKE 'B%' THEN 25
    WHEN space_number LIKE 'C%' THEN 35
    WHEN space_number LIKE 'D%' THEN 45
    ELSE 20 + (id % 30)
END;

UPDATE parking_spaces 
SET priority_level = CASE 
    WHEN location_name LIKE '%Main%' THEN 2
    WHEN location_name LIKE '%Center%' THEN 3
    WHEN location_name LIKE '%Basement%' THEN 1
    ELSE ROUND(RAND() * 3)
END;

-- 5. Show current state of parking_spaces
SELECT id, space_number, location_name, distance_from_entry, priority_level, is_available, status
FROM parking_spaces
LIMIT 20;

-- 6. Count available spaces by location
SELECT 
    location_name,
    COUNT(*) as total_spaces,
    SUM(is_available = 1) as available_spaces,
    SUM(is_available = 0) as occupied_spaces,
    ROUND(AVG(distance_from_entry), 2) as avg_distance,
    ROUND(AVG(priority_level), 2) as avg_priority
FROM parking_spaces
WHERE status = 'active'
GROUP BY location_name;

-- 7. Show all unique locations
SELECT DISTINCT location_name 
FROM parking_spaces 
WHERE status = 'active'
ORDER BY location_name;

-- 8. Create parking_bookings table if it doesn't exist (for the API query to work)
CREATE TABLE IF NOT EXISTS parking_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(50) UNIQUE,
    space_id INT NOT NULL,
    vehicle_id INT,
    vehicle_number VARCHAR(50),
    user_id INT,
    check_in DATETIME,
    expected_check_out DATETIME,
    booking_status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    total_amount DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (space_id) REFERENCES parking_spaces(id) ON DELETE CASCADE
);

-- 9. Verify final state
SELECT 
    'parking_spaces' as table_name,
    COUNT(*) as total_records,
    SUM(is_available = 1) as available,
    SUM(is_available = 0) as occupied
FROM parking_spaces
WHERE status = 'active'
UNION ALL
SELECT 
    'parking_bookings',
    COUNT(*),
    NULL,
    NULL
FROM parking_bookings;
