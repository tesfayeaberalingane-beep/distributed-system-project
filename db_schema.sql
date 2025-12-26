-- Database Name: distributed_jobs

-- 1. Create the Database
CREATE DATABASE IF NOT EXISTS `distributed_job`;
USE `distributed_job`;

-- 2. Create the users table
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- To store the hashed password
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create the jobs table
CREATE TABLE IF NOT EXISTS `jobs` (
    `job_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `status` ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `submit_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `start_time` DATETIME NULL,
    `end_time` DATETIME NULL,
    `result` TEXT,
    PRIMARY KEY (`job_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create an initial 'admin' user (password is 'adminpass')
-- IMPORTANT: Change this password after setup!
INSERT INTO `users` (`username`, `password`, `role`)
VALUES ('habte', '$2y$10$iI8v0c1K9S4l.kY9x4b9O.I4t7YwJ.Wk2wL0oQ0sZ7T4v9dC9', 'admin');
-- Hash for 'adminpass' is used above