-- Run this in phpMyAdmin (http://localhost/phpmyadmin) or MySQL CLI
-- Creates database and users table for login system

CREATE DATABASE IF NOT EXISTS property_stock;
USE property_stock;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default user: admin / password (change after first login!)
-- Password is hashed with PHP password_hash()
INSERT INTO users (username, password, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com')
ON DUPLICATE KEY UPDATE username = username;

-- The hash above is for the password "password". To add users with your own password,
-- run: php create_user.php
