# EasyPark Final Defense Preparation

## How the Program Works

EasyPark is a smart parking management system designed for parking facilities in Nepal. It works like this:

1. **User Registration & Login**: Users create accounts and log in using PHP sessions for security.

2. **Dashboard Overview**: After login, users see their dashboard showing available parking spaces, current bookings, and smart predictions about parking availability.

3. **Searching for Parking**: Users can search for parking spaces by location, vehicle type (car, bike, truck), or use GPS to find nearest spots. The system uses maps from OpenStreetMap (free, no API keys needed).

4. **Smart Recommendations**: The system intelligently suggests the best parking spaces based on distance from entry, priority level, and availability. It ranks spaces using a scoring formula to help users find optimal spots quickly.

5. **Booking Process**: When a user wants to book a space:
   - System checks if the space is available for the requested time
   - Prevents double-booking by checking for time conflicts
   - Calculates the cost based on hours and rates
   - Creates a booking record and marks the space as occupied

6. **Real-time Management**: Admins can manage spaces, users, vehicles, and monitor the system. They can check-in/check-out vehicles, view analytics, and handle anomaly alerts.

7. **Smart Features**:
   - **Demand Prediction**: Shows users if parking is likely to be crowded based on historical data
   - **Anomaly Detection**: Identifies suspicious activities like vehicles overstaying or wrong vehicle types in spaces
   - **Location Services**: Uses GPS and maps for finding and navigating to parking spots

The system uses a web-based interface with responsive design that works on phones and computers.

## What Problems It Solves

EasyPark solves several real-world parking problems:

1. **Manual Tracking Issues**: Traditional parking lots use paper tickets and manual counting. EasyPark automates this with digital records and real-time updates.

2. **Double-Booking Prevention**: Without the system, multiple people could book the same space unknowingly. The interval overlap detection prevents this.

3. **Finding Available Spaces**: Users waste time driving around looking for parking. EasyPark shows real-time availability and guides users to open spots.

4. **Poor Space Utilization**: Some spaces might be underused while others are crowded. Smart recommendations help balance the load.

5. **Revenue Loss**: Manual systems miss payments or have errors. EasyPark automatically calculates costs and tracks payments.

6. **Security Issues**: No way to track suspicious activities. Anomaly detection alerts admins to potential problems like overstaying vehicles.

7. **User Convenience**: No easy way to reserve spots in advance. Users can book ahead and get confirmations.

8. **Admin Management**: Hard to manage large parking facilities. Dashboard provides analytics and quick actions.

9. **Demand Planning**: No insight into busy hours. Prediction system helps plan staffing and pricing.

10. **Location Finding**: Hard to find parking locations. GPS integration and maps solve this.

## DSA Algorithms Used

### 1. Haversine Distance Algorithm
**What it does**: Calculates the actual straight-line distance between two points on Earth using their GPS coordinates.

**Simple explanation**: When you want to find parking spaces near you, the system needs to measure real-world distances, not just straight lines on a map. This algorithm accounts for the Earth's curved surface.

**When it triggers**: Every time a user searches for "nearest parking" or filters by distance (like "within 5km").

**Real scenario**: A user in Kathmandu wants parking within 2km. The system calculates distance from user location (27.717°, 85.324°) to each parking space and shows only those within 2km radius.

**How it works simply**: Uses trigonometry with Earth's radius (6371km) to compute accurate distances between latitude/longitude points.

### 2. Interval Overlap Detection Algorithm
**What it does**: Checks if two time periods conflict with each other to prevent double-booking.

**Simple explanation**: Before allowing a new booking, the system checks if anyone else has already booked the same space for overlapping times.

**When it triggers**: Every time someone tries to book a parking space - the system checks against all existing bookings for that space.

**Real scenario**: Space A is booked from 2 PM to 4 PM. User tries to book from 3 PM to 5 PM. System detects overlap (3 PM falls within 2-4 PM) and rejects the booking.

**How it works simply**: Compares start and end times: if new_booking_start < existing_booking_end AND new_booking_end > existing_booking_start, then there's a conflict.

### 3. Weighted Priority Scoring Algorithm
**What it does**: Ranks parking spaces by combining multiple factors (distance, priority, availability) into a single score.

**Simple explanation**: Instead of just showing the closest space, the system considers what's most convenient for the user by scoring spaces on multiple criteria.

**When it triggers**: When showing search results or providing smart recommendations to users.

**Real scenario**: User searches for parking at "City Center". System ranks spaces:
- Space near entrance (25m away, priority 1) = score 30 (best)
- Space farther away (45m away, priority 2) = score 55
- Space very far (60m away, priority 1) = score 65 (worst)

**How it works simply**: Score = distance_from_entry + (priority_level × 5). Lower score means better recommendation.

### 4. Time-based Aggregation Algorithm
**What it does**: Groups historical booking data by hour and day to analyze patterns.

**Simple explanation**: Collects past 30 days of booking data and organizes it by time slots to understand when parking is busiest.

**When it triggers**: When generating demand predictions or showing hourly occupancy trends.

**Real scenario**: System analyzes that Monday mornings (8-10 AM) have 80% occupancy, while evenings (6-8 PM) have 60%. Uses this to predict tomorrow's demand.

**How it works simply**: SQL query groups bookings by HOUR(check_in) and DAYOFWEEK(check_in), then counts bookings in each time slot.

### 5. State Machine (Booking Status Management)
**What it does**: Manages the lifecycle of parking spaces and bookings using defined states and transitions.

**Simple explanation**: Ensures bookings and spaces follow proper rules - can't cancel completed bookings, can't book occupied spaces, etc.

**When it triggers**: On every booking action (create, cancel, check-in, check-out).

**Real scenario**: User tries to cancel a booking that's already completed. System prevents this because only "active" bookings can be cancelled.

**How it works simply**: Uses status flags (active/completed/cancelled for bookings, available/occupied for spaces) with validation rules.

## ML Logic Used

### 1. Demand Prediction Model
**What it does**: Predicts parking occupancy for the next hour based on historical patterns.

**How it functions**: Analyzes last 30 days of booking data, groups by hour and day of week, applies multipliers for weekends/holidays/peak hours.

**When it triggers**: 
- User views dashboard (shows current prediction)
- Admin checks analytics
- System runs hourly updates

**Real scenario**: At 2 PM on a Monday, system predicts 70% occupancy for 3 PM because historically Mondays at 3 PM average 65 bookings, and it's a peak hour (gets 1.3x multiplier).

**Simple working**: Historical average for this hour/day + adjustments for special conditions = predicted demand percentage.

### 2. Anomaly Detection System
**What it does**: Identifies suspicious parking activities using rule-based checks.

**How it functions**: Three types of checks:
- Overstay: Vehicle parked >2 hours past checkout time
- Unauthorized: Wrong vehicle type in space (bike in car space)
- Suspicious: Same vehicle books >3 times in 24 hours

**When it triggers**: 
- Admin manually checks anomalies
- Scheduled system scans
- Real-time alerts for critical issues

**Real scenario**: A car is supposed to check out at 4 PM but stays until 7 PM. System detects 3-hour overstay and creates a "medium" severity alert for admin to investigate.

**Simple working**: If conditions match rules (like time > expected + 2 hours), create alert with severity level.

### 3. Availability Prediction Model
**What it does**: Classifies current parking status and provides recommendations.

**How it functions**: Counts current active bookings vs total spaces, calculates occupancy percentage, classifies as Available/Moderate/Congested.

**When it triggers**: 
- User searches for parking
- Dashboard loads
- Real-time status updates

**Real scenario**: Parking has 100 spaces, 75 currently occupied. System calculates 75% occupancy, classifies as "Congested", recommends user wait or try alternative parking.

**Simple working**: (active_bookings / total_spaces) × 100 = occupancy%. Apply thresholds: <50% = Available, 50-80% = Moderate, >80% = Congested.

### 4. Smart Slot Recommendation Engine
**What it does**: Intelligently ranks parking spaces for optimal user experience.

**How it functions**: Filters available spaces, calculates scores based on distance and priority, sorts by score ascending (lower = better).

**When it triggers**: 
- User requests recommendations
- Search results display
- Booking suggestions

**Real scenario**: User needs parking at "Downtown Mall". System finds 5 available spaces, scores them, recommends the one 20m from entrance with high priority (score 25) over one 50m away with low priority (score 55).

**Simple working**: For each space: score = distance_from_entry + (priority_level × 5). Sort by score, return top results.

## Database Details

### Database Used: MySQL 5.7+
**Why MySQL instead of others?**

MySQL was chosen because:
- **Free and Open Source**: No licensing costs, widely available
- **Web Application Standard**: Perfect for PHP-based web apps like EasyPark
- **ACID Compliance**: Ensures data consistency for bookings and payments
- **Relational Structure**: Handles complex relationships between users, vehicles, spaces, bookings
- **Performance**: Fast queries with proper indexing for real-time operations
- **Scalability**: Can handle thousands of concurrent users with proper setup
- **Ecosystem**: Excellent PHP integration, phpMyAdmin for management
- **Reliability**: Proven track record in production environments

**Why not NoSQL (MongoDB)?** EasyPark needs complex joins and transactions (booking conflicts, referential integrity), which relational databases handle better.

**Why not PostgreSQL?** MySQL is simpler to set up and has better PHP support out-of-the-box.

### Number of Tables: 9

The exact tables are:

1. **users** - Stores user accounts, login credentials, roles (admin/user)
2. **vehicle_categories** - Defines vehicle types (Car, Bike, Truck) with pricing
3. **vehicles** - User's registered vehicles with details
4. **parking_spaces** - All parking spaces with location, pricing, status
5. **parking_bookings** - Active and historical bookings
6. **parking_transactions** - Payment records linked to bookings
7. **activity_logs** - Audit trail of user actions
8. **anomaly_alerts** - Suspicious activity alerts
9. **system_settings** - System configuration settings

**How the database functions**:
- Uses InnoDB engine for transactions and foreign keys
- Prepared statements prevent SQL injection
- Indexes on critical columns (user_id, space_id, booking_status, check_in)
- Singleton connection pattern for efficient database access
- ACID properties ensure booking integrity

## Why Agile Methodology Was Used

Agile methodology was used because:

**Iterative Development**: EasyPark evolved through multiple iterations. Started with basic booking, then added smart features, then ML predictions. Each iteration delivered working software.

**Adaptability to Changes**: Requirements changed during development (added GPS features, anomaly detection). Agile allowed incorporating these changes without major rewrites.

**User Feedback Integration**: Could show working prototypes to potential users and incorporate their feedback quickly.

**Risk Management**: Small iterations reduced risk - if something didn't work, only that feature was affected, not the whole system.

**Solo Development Suitability**: As a single developer project, Agile's flexible structure worked better than rigid waterfall planning.

**Continuous Improvement**: Each sprint focused on improving specific aspects (UI, performance, features).

**Deliverable Focus**: Emphasized working software over extensive documentation, which was perfect for a demo-able project.

## Tools Used

### Backend Development
- **PHP 7.4+**: Server-side logic, session management, database operations
- **MySQL 5.7+**: Database management with phpMyAdmin interface
- **MAMP/XAMPP**: Local development server (Apache + MySQL + PHP)

### Frontend Development
- **HTML5/CSS3**: Page structure and styling
- **Bootstrap 5.1.3**: Responsive framework for mobile/desktop compatibility
- **JavaScript/jQuery 3.6.0**: Interactive features, AJAX calls
- **Chart.js**: Data visualization for analytics and predictions
- **DataTables**: Dynamic tables with sorting, filtering, pagination

### Mapping & Location
- **Leaflet**: Open-source JavaScript library for interactive maps
- **OpenStreetMap**: Free worldwide map tiles (no API keys/costs)
- **Nominatim**: Free geocoding service for address-to-coordinates conversion

### Development Tools
- **VS Code**: Code editor with PHP, HTML, CSS, JavaScript support
- **Git**: Version control for code management
- **Browser DevTools**: Debugging and testing

### Database Tools
- **phpMyAdmin**: Web-based MySQL administration
- **MySQL Workbench**: Database design and query testing

### Design & Documentation
- **Draw.io/Figma**: System architecture diagrams
- **Markdown**: Documentation writing
- **GitHub**: Code repository and README hosting

All tools were chosen for being free/open-source, widely supported, and suitable for web development.