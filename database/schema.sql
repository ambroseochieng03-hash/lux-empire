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

-- LUX EMPIRE
-- Migration: driver identity type (National ID vs Driving License)
--
-- The driver form has ONE identity field that can be either a
-- National ID or a Driving License number — this column records
-- which one was actually submitted, since drivers.license_number
-- (unchanged name, for minimal disruption) now holds whichever
-- value was provided, encrypted, regardless of type.

ALTER TABLE drivers
    ADD COLUMN identity_type ENUM('national_id', 'license') NOT NULL DEFAULT 'national_id' AFTER license_number;

ALTER TABLE house_images
  ADD COLUMN status ENUM('ready','processing','failed') NOT NULL DEFAULT 'ready' AFTER image_path,
  ADD COLUMN staged_path VARCHAR(500) NULL AFTER status;

-- LUX EMPIRE
-- Migration: admin moderation columns + action-reason audit trail
--
-- Adds moderation state to users and houses (flagging, verification)
-- and a generic table for capturing the "reason" text behind
-- dangerous admin actions (permanent deletes), separate from the
-- free-text activity_logs table so reasons stay queryable per record.
--
-- Does NOT touch classes/House.php or any existing column.

ALTER TABLE users
    ADD COLUMN is_flagged TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN flag_reason VARCHAR(255) NULL AFTER is_flagged,
    ADD COLUMN verified_at TIMESTAMP NULL AFTER flag_reason,
    ADD COLUMN verified_by INT NULL AFTER verified_at,
    ADD CONSTRAINT fk_users_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE houses
    ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN is_flagged TINYINT(1) NOT NULL DEFAULT 0 AFTER is_hidden,
    ADD COLUMN flag_reason VARCHAR(255) NULL AFTER is_flagged,
    ADD COLUMN verified_at TIMESTAMP NULL AFTER flag_reason,
    ADD COLUMN verified_by INT NULL AFTER verified_at,
    ADD CONSTRAINT fk_houses_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE admin_action_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_table VARCHAR(50) NOT NULL,
    target_id INT NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;     

ALTER TABLE driver_locations ADD UNIQUE KEY uniq_driver_id (driver_id);
ALTER TABLE tenant_locations ADD UNIQUE KEY uniq_tenant_id (tenant_id);

CREATE TABLE idempotency_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(64) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
    response_code INT NULL,
    response_body MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_key_endpoint (idempotency_key, endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE truck_requests
  ADD COLUMN trip_type ENUM('instant', 'scheduled') NOT NULL DEFAULT 'instant' AFTER tenant_id,
  ADD COLUMN scheduled_at DATETIME NULL AFTER moving_date,
  ADD COLUMN items_description TEXT NULL AFTER notes,
  ADD COLUMN distance_km DECIMAL(8,2) NULL AFTER price,
  ADD COLUMN last_daily_reminder_sent_at DATE NULL,
  ADD COLUMN hour_reminder_sent_at TIMESTAMP NULL,
  ADD COLUMN tenant_reminder_sent_at TIMESTAMP NULL;

-- Landlord plan state lives on users (only meaningful for role='landlord',
-- same convention as national_id_encrypted being landlord-only in practice)
ALTER TABLE users
    ADD COLUMN plan_tier ENUM('free','pro') NOT NULL DEFAULT 'free' AFTER role,
    ADD COLUMN plan_expires_at DATETIME NULL AFTER plan_tier;

-- One table for every M-Pesa transaction, regardless of what it's for.
-- purpose + metadata tell you what it was paying for; nothing here is
-- payment-type-specific.
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    purpose ENUM('landlord_pro','booking_fee','driver_wallet_topup') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status ENUM('pending','completed','failed','timeout') NOT NULL DEFAULT 'pending',
    checkout_request_id VARCHAR(100) NOT NULL,
    merchant_request_id VARCHAR(100) NULL,
    mpesa_receipt VARCHAR(50) NULL,
    metadata JSON NULL,           -- e.g. {"booking_id": 42} or {"trip_id": 17}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_checkout_request (checkout_request_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Driver commission wallet — a ledger, not a mutable balance column,
-- so every deduction/top-up is auditable and race-safe.
CREATE TABLE wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    type ENUM('topup','commission','admin_adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,          -- negative for commission deductions
    reference_id INT NULL,                   -- payments.id or truck_requests.id
    balance_after DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings need to know the fee was actually paid before the landlord
-- ever sees/accepts the request.
ALTER TABLE bookings
    ADD COLUMN payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid' AFTER status,
    ADD COLUMN payment_id INT NULL AFTER payment_status,
    ADD CONSTRAINT fk_bookings_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL;

CREATE TABLE mpesa_c2b_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mpesa_receipt VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    transaction_time DATETIME NOT NULL,
    matched_payment_id INT NULL,
    status ENUM('unmatched','matched') NOT NULL DEFAULT 'unmatched',
    raw_payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_receipt (mpesa_receipt),
    FOREIGN KEY (matched_payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 

ALTER TABLE houses
    MODIFY COLUMN status ENUM('available','reserved','booked','rented') NOT NULL DEFAULT 'available';

ALTER TABLE houses
    ADD COLUMN reserved_by_booking_id INT NULL AFTER status,
    ADD CONSTRAINT fk_houses_reserved_booking FOREIGN KEY (reserved_by_booking_id) REFERENCES bookings(id) ON DELETE SET NULL;

ALTER TABLE payments
    ADD COLUMN user_submitted_receipt VARCHAR(20) NULL AFTER mpesa_receipt,
    ADD COLUMN needs_admin_review TINYINT(1) NOT NULL DEFAULT 0 AFTER user_submitted_receipt,
    ADD COLUMN refund_required TINYINT(1) NOT NULL DEFAULT 0 AFTER needs_admin_review,
    ADD COLUMN refund_resolved_at TIMESTAMP NULL AFTER refund_required,
    ADD COLUMN admin_notes TEXT NULL AFTER refund_resolved_at,
    ADD COLUMN resolved_by_admin_id INT NULL AFTER admin_notes,
    ADD CONSTRAINT fk_payments_resolved_by FOREIGN KEY (resolved_by_admin_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE payment_waivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('user','role') NOT NULL,
    user_id INT NULL,
    role ENUM('tenant','landlord','driver') NULL,
    reason TEXT NULL,
    granted_by INT NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE houses
    MODIFY COLUMN status ENUM('available','reserved','booked','unavailable','rented') NOT NULL DEFAULT 'available';

-- Preserve a readable trace of what was booked, even once the house row is gone.
ALTER TABLE bookings
    ADD COLUMN house_title_snapshot VARCHAR(150) NULL AFTER house_id;

UPDATE bookings b
JOIN houses h ON b.house_id = h.id
SET b.house_title_snapshot = h.title;

-- Find the FK's real name first (it wasn't given an explicit name
-- in your original schema, so MariaDB auto-generated one):
SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'house_truck_platform' AND TABLE_NAME = 'bookings'
AND COLUMN_NAME = 'house_id' AND REFERENCED_TABLE_NAME = 'houses';

-- ERROR
ALTER TABLE bookings DROP FOREIGN KEY bookings_ibfk_1;  -- use the actual name from above
ALTER TABLE bookings MODIFY COLUMN house_id INT NULL;
ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL;

USE house_truck_platform;

ALTER TABLE bookings
    DROP FOREIGN KEY `1`;

ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_house
    FOREIGN KEY (house_id)
    REFERENCES houses(id);

SHOW CREATE TABLE bookings\G  

-- ============================================================
-- HOUSES — every listing page filters is_hidden + status first,
-- then optionally price/house_type/keyword. This composite covers
-- the filter that's on EVERY query (runFilterQuery in House.php).
-- ============================================================
ALTER TABLE houses ADD INDEX idx_hidden_status (is_hidden, status);
ALTER TABLE houses ADD INDEX idx_price (price);
ALTER TABLE houses ADD INDEX idx_house_type (house_type);

-- Your keyword search uses LIKE '%word%' which a normal index CANNOT
-- use (leading wildcard). A FULLTEXT index lets MySQL actually use
-- an index for this instead of scanning every row. This requires
-- changing the query to MATCH(...) AGAINST(...) — see note below.
ALTER TABLE houses ADD FULLTEXT INDEX idx_fulltext_search (title, location, description);

-- ============================================================
-- BOOKINGS — landlord "pending work queue" and tenant "my bookings"
-- are your two hottest booking reads.
-- ============================================================
ALTER TABLE bookings ADD INDEX idx_landlord_status (landlord_id, status);
ALTER TABLE bookings ADD INDEX idx_tenant_house_id (tenant_id, house_id, id);
ALTER TABLE bookings ADD INDEX idx_tenant_date (tenant_id, booking_date);

-- ============================================================
-- TRUCK REQUESTS — driver's active trip lookup and tenant's active
-- trip lookup both filter by status set, every poll.
-- ============================================================
ALTER TABLE truck_requests ADD INDEX idx_driver_status (driver_id, status);
ALTER TABLE truck_requests ADD INDEX idx_tenant_status (tenant_id, status);

-- ============================================================
-- MESSAGES — every chat poll does WHERE conversation_id=? AND id>?
-- ORDER BY id ASC. This composite makes that an index-only range scan.
-- ============================================================
ALTER TABLE messages ADD INDEX idx_conv_id (conversation_id, id);

-- ============================================================
-- NOTIFICATIONS — polled by every user, every heartbeat. This is
-- one of your hottest tables at 8,500+ active users.
-- ============================================================
ALTER TABLE notifications ADD INDEX idx_user_read_created (user_id, is_read, created_at);

-- ============================================================
-- PAYMENTS — admin review queues and refund queues both filter
-- on flags + status, full table scan otherwise as payments grow.
-- ============================================================
ALTER TABLE payments ADD INDEX idx_review_status (needs_admin_review, status);
ALTER TABLE payments ADD INDEX idx_refund (refund_required, refund_resolved_at);

-- ============================================================
-- HOUSE IMAGES — fetch_houses.php filters status='ready' per house.
-- ============================================================
ALTER TABLE house_images ADD INDEX idx_house_status (house_id, status);

-- ============================================================
-- USERS — admin user list filters by role; role+status filters
-- (suspended/pending review) also common in admin dashboards.
-- ============================================================
ALTER TABLE users ADD INDEX idx_role (role);
ALTER TABLE users ADD INDEX idx_role_status (role, status);

ALTER TABLE houses
    ADD COLUMN has_parking TINYINT(1) NOT NULL DEFAULT 0 AFTER rating;