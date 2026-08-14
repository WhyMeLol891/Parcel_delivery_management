-- --------------------------------------------------------
-- Database Creation
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS parcel_db 
  DEFAULT CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE parcel_db;

-- --------------------------------------------------------
-- 1. Users Table (Admins, Staff, Riders)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('admin', 'staff', 'rider') NOT NULL DEFAULT 'staff',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 2. Riders Table (Extended Profile for Users with role='rider')
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    vehicle_type VARCHAR(50) NOT NULL, -- e.g. Motorcycle, Van, Bicycle
    vehicle_plate VARCHAR(20) NOT NULL,
    license_number VARCHAR(50) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 3. Parcels Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS parcels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(30) NOT NULL UNIQUE,
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_address TEXT NOT NULL,
    status ENUM('pending', 'assigned', 'picked_up', 'in_transit', 'delivered', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    assigned_rider_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_rider_id) REFERENCES riders(id) ON DELETE SET NULL,
    INDEX idx_tracking (tracking_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 4. Parcel Status History Table (Audit Trail)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS parcel_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    updated_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 5. Rider Locations Table (Real-Time GPS Tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS rider_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    INDEX idx_rider_loc (rider_id)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 6. Delivery Photos Table (Photo POD Metadata)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS delivery_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    file_size_kb INT NULL,
    remarks TEXT NULL,
    uploaded_by_rider_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_rider_id) REFERENCES riders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 7. Activity Logs Table (System & Security Auditing)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 1. Insert Sample Admin, Staff, and Rider users (Passwords are 'password123' using PHP password_hash)
INSERT INTO users (id, full_name, email, password_hash, phone, role) VALUES
(1, 'Admin User', 'admin@system.com', '$2y$10$4.a5hXbO8vO8x8M6Gz.5u.I1qE6w1Ie6d3XJ4sW3rX2yZ1a1b1c1d', '+100000000', 'admin'),
(2, 'Dave Rider', 'rider@system.com', '$2y$10$4.a5hXbO8vO8x8M6Gz.5u.I1qE6w1Ie6d3XJ4sW3rX2yZ1a1b1c1d', '+155501923', 'rider');

-- 2. Link Rider profile to user_id 2
INSERT INTO riders (id, user_id, vehicle_type, vehicle_plate, license_number) VALUES
(1, 2, 'Motorcycle', 'AB-9821-NY', 'DL-88203102');

-- 3. Insert Sample Parcel assigned to Rider 1
INSERT INTO parcels (id, tracking_number, sender_name, sender_phone, recipient_name, recipient_phone, recipient_address, status, assigned_rider_id) VALUES
(1, 'TRK-2026-9802', 'Wayne Enterprises', '+15550100', 'Bruce Wayne', '+15550199', '1007 Mountain Drive, Gotham City', 'assigned', 1);

-- 4. Initial Parcel Log
INSERT INTO parcel_status_history (parcel_id, status, notes, updated_by_user_id) VALUES
(1, 'assigned', 'Assigned to Dave Rider', 1);