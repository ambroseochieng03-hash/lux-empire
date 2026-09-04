CREATE DATABASE IF NOT EXISTS house_truck_platform;
USE house_truck_platform;

-- USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('tenant','landlord','driver','admin') NOT NULL DEFAULT 'tenant',
    profile_image VARCHAR(255) DEFAULT NULL,
    national_id VARCHAR(50) DEFAULT NULL,
    status ENUM('active','suspended','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- HOUSES TABLE
CREATE TABLE houses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    location VARCHAR(255) NOT NULL,
    latitude DOUBLE,
    longitude DOUBLE,
    bedrooms INT DEFAULT 1,
    bathrooms INT DEFAULT 1,
    house_type VARCHAR(50),
    status ENUM('available','booked','rented') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
);

-- HOUSE IMAGES
CREATE TABLE house_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
);

-- BOOKINGS
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    tenant_id INT NOT NULL,
    landlord_id INT NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
);

-- DRIVER PROFILES
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_type VARCHAR(50),
    vehicle_plate VARCHAR(50),
    license_number VARCHAR(100),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TRUCK REQUESTS
CREATE TABLE truck_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    driver_id INT DEFAULT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    pickup_lat DOUBLE,
    pickup_lng DOUBLE,
    destination_lat DOUBLE,
    destination_lng DOUBLE,
    load_description TEXT,
    price DECIMAL(10,2),
    status ENUM('pending','accepted','in_transit','completed','cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- DRIVER LIVE LOCATION
CREATE TABLE driver_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PASSWORD RESETS
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ACTIVITY LOGS
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    activity TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,

    reporter_id INT NOT NULL,

    reported_user_id INT NULL,

    booking_id INT NULL,
    trip_id INT NULL,
    house_id INT NULL,

    report_type VARCHAR(100),

    message TEXT,

    status ENUM(
        'pending',
        'investigating',
        'resolved',
        'dismissed'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emergency_alerts (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    role VARCHAR(50),

    trip_id INT NULL,
    booking_id INT NULL,

    latitude VARCHAR(100) NULL,
    longitude VARCHAR(100) NULL,

    message TEXT NULL,

    status ENUM(
        'active',
        'responding',
        'resolved'
    ) DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trip_location_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    trip_id INT NOT NULL,

    user_id INT NOT NULL,

    role VARCHAR(50),

    latitude VARCHAR(100),

    longitude VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emergency_activity_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    emergency_id INT NOT NULL,

    activity TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trip_status_history (

    id INT AUTO_INCREMENT PRIMARY KEY,

    trip_id INT NOT NULL,

    status VARCHAR(100),

    changed_by INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tenant_locations (

    id INT AUTO_INCREMENT PRIMARY KEY,

    tenant_id INT NOT NULL,

    latitude VARCHAR(100),

    longitude VARCHAR(100),

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- Run against house_truck_platform

CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    other_user_id INT NOT NULL,
    other_role ENUM('landlord','driver') NOT NULL,
    house_id INT NULL,
    truck_request_id INT NULL,
    last_message_at TIMESTAMP NULL,
    tenant_last_read_at TIMESTAMP NULL,
    other_last_read_at TIMESTAMP NULL,
    ai_notice_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pair (tenant_id, other_user_id),
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (other_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL,
    FOREIGN KEY (truck_request_id) REFERENCES truck_requests(id) ON DELETE SET NULL
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NULL,
    sender_type ENUM('user','ai') NOT NULL DEFAULT 'user',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE chat_typing (
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE users ADD COLUMN last_seen_at TIMESTAMP NULL;

ALTER TABLE truck_requests
ADD updated_at TIMESTAMP
DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE truck_requests
ADD started_at TIMESTAMP NULL;

ALTER TABLE truck_requests
ADD completed_at TIMESTAMP NULL;

ALTER TABLE emergency_alerts 
MODIFY status ENUM('active','responding','resolved','dismissed') 
DEFAULT 'active';

ALTER TABLE password_resets
ADD used TINYINT(1) DEFAULT 0;

ALTER TABLE truck_requests
ADD COLUMN moving_date DATE NULL;

ALTER TABLE truck_requests
ADD COLUMN notes TEXT NULL;

ALTER TABLE houses
ADD rating INT DEFAULT 0;

ALTER TABLE truck_requests
MODIFY status ENUM('pending','accepted','arrived_at_pickup','in_transit','completed','cancelled')
DEFAULT 'pending';

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    rate_key VARCHAR(255) NOT NULL,

    attempts INT UNSIGNED NOT NULL DEFAULT 0,

    window_started_at DATETIME NOT NULL,

    blocked_until DATETIME DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_rate_key (rate_key),

    INDEX idx_blocked_until (blocked_until),

    INDEX idx_window_started_at (window_started_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bookings
  ADD COLUMN pending_lock VARCHAR(64)
    GENERATED ALWAYS AS (
      CASE WHEN status = 'pending'
           THEN CONCAT(tenant_id, '-', house_id)
           ELSE NULL END
    ) STORED;

ALTER TABLE bookings
  ADD UNIQUE KEY uniq_tenant_house_pending (pending_lock);  

ALTER TABLE houses
    ADD COLUMN booked_at TIMESTAMP NULL DEFAULT NULL AFTER status;  

-- LUX EMPIRE
-- Migration: application-level encryption columns + consent audit trail
--
-- 1) users.national_id_encrypted — NEW column, holds ciphertext for the
--    landlord registration flow's ID field. The existing plaintext
--    users.national_id column is left completely untouched (still used
--    by the old universal auth/register.php / tenant stopgap), so
--    nothing already reading that column is affected.
--
-- 2) drivers.license_number / drivers.vehicle_plate — widened to TEXT
--    to hold ciphertext (nonce + encrypted value, base64-encoded) in
--    place of the plaintext they held before. vehicle_type is NOT
--    encrypted (not sensitive) but widened slightly to fit a longer
--    free-text description.
--
-- 3) consent_records — audit trail of every accept/decline decision
--    made on the landlord/driver registration consent notice.
--    user_id is nullable because a "decline" happens before any
--    account exists.

ALTER TABLE users
    ADD COLUMN national_id_encrypted TEXT NULL AFTER national_id;

ALTER TABLE drivers
    MODIFY COLUMN license_number TEXT NULL,
    MODIFY COLUMN vehicle_plate TEXT NULL,
    MODIFY COLUMN vehicle_type VARCHAR(150) NULL;

CREATE TABLE consent_records (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NULL,

    role VARCHAR(20) NOT NULL,
    consent_type VARCHAR(50) NOT NULL DEFAULT 'data_processing',

    decision ENUM('accepted', 'declined') NOT NULL,

    ip_address VARCHAR(45) NULL,

    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;    


-- LUX EMPIRE
-- Migration: institutions table for "proximity to institution" filtering
--
-- houses.latitude/longitude already exist and are used to compute
-- distance via the Haversine formula at query time — no need to
-- pre-store distances, they'd go stale the moment either point moves.

CREATE TABLE institutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('university','college','training_institution','town','other') DEFAULT 'other',
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starter seed (Nairobi area) — coordinates are approximate campus
-- centers, replace/extend freely; this just makes the filter usable
-- immediately instead of shipping an empty dropdown.
INSERT INTO institutions (name, type, latitude, longitude) VALUES
('University of Nairobi (Main Campus)', 'university', -1.2795, 36.8172),
('Kenyatta University', 'university', -1.1794, 36.9337),
('Strathmore University', 'university', -1.3095, 36.8122),
('JKUAT (Juja)', 'university', -1.0936, 37.0138),
('USIU-Africa', 'university', -1.2194, 36.8790),
('Multimedia University of Kenya', 'university', -1.3773, 36.7476);
