CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    role ENUM('rider', 'driver', 'admin') NOT NULL DEFAULT 'rider',
    avatar_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS driver_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    license_number VARCHAR(50) NOT NULL,
    license_expiry DATE NOT NULL,
    status ENUM('pending', 'approved', 'suspended') NOT NULL DEFAULT 'pending',
    is_available TINYINT(1) NOT NULL DEFAULT 0,
    current_lat DECIMAL(10, 7) NULL,
    current_lng DECIMAL(10, 7) NULL,
    rating_avg DECIMAL(3, 2) DEFAULT 0.00,
    total_rides INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT UNSIGNED NOT NULL,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year SMALLINT NOT NULL,
    color VARCHAR(30) NOT NULL,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type ENUM('economy', 'comfort', 'premium') NOT NULL DEFAULT 'economy',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    status ENUM('requested', 'accepted', 'arriving', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'requested',
    pickup_lat DECIMAL(10, 7) NOT NULL,
    pickup_lng DECIMAL(10, 7) NOT NULL,
    pickup_address VARCHAR(500) NOT NULL,
    dropoff_lat DECIMAL(10, 7) NOT NULL,
    dropoff_lng DECIMAL(10, 7) NOT NULL,
    dropoff_address VARCHAR(500) NOT NULL,
    vehicle_type ENUM('economy', 'comfort', 'premium') NOT NULL,
    fare DECIMAL(10, 2) NULL,
    distance_km DECIMAL(8, 2) NULL,
    duration_minutes INT UNSIGNED NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    arrived_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    cancelled_by ENUM('rider', 'driver') NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES users(id),
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ride_id INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    reviewee_id INT UNSIGNED NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (reviewee_id) REFERENCES users(id)
);

INSERT IGNORE INTO users (email, password_hash, first_name, last_name, phone, role)
VALUES ('admin@indrive.com', '$2y$12$/BwB/H.N0rE3xoLorsKhCeH9sq.7hEcPsuPFJ6xMFoE6UZn5j1UWy', 'Admin', 'User', '0000000000', 'admin');
