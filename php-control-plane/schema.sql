CREATE DATABASE IF NOT EXISTS railway_db;
USE railway_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'commuter') DEFAULT 'commuter'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS train_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    train_number VARCHAR(20) NOT NULL UNIQUE,
    origin_station VARCHAR(100) NOT NULL,
    destination_station VARCHAR(100) NOT NULL,
    base_duration_mins INT NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed baseline profiles (Password for admin accounts is: SecureAdmin123!)
INSERT IGNORE INTO users (id, username, password_hash, role) VALUES 
(1, 'dispatcher_alpha', '$2y$10$E2Wv9K7Hw/GZ.o8q7b9YPO21f8A3q6VzYbeKj5M/4Mshg2bW3e6hG', 'admin');

INSERT IGNORE INTO train_schedules (train_number, origin_station, destination_station, base_duration_mins) VALUES
('METRO-A-99', 'Central Terminal', 'North Outpost', 60),
('EXPRESS-B-02', 'Grand Junction', 'Coastal Bay', 85);