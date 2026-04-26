-- Database Migration for Role-Based Access Control and Project Folders
-- Run this SQL script to update the database structure

-- Add role column to users table
ALTER TABLE users ADD COLUMN role ENUM('admin', 'handler', 'visitor') DEFAULT 'visitor' AFTER passwordHash;

-- Add email column to users table
ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER fullName;

-- Create login attempts table (FIXED: added success column)
CREATE TABLE login_attempts (
    attemptId INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create password reset table
CREATE TABLE password_resets (
    resetId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expiry TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE
);

-- Create password reset attempts table
CREATE TABLE password_reset_attempts (
    attemptId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE
);

-- Create error logs table
CREATE TABLE error_logs (
    logId INT PRIMARY KEY AUTO_INCREMENT,
    error_message TEXT NOT NULL,
    query TEXT,
    params TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create user permissions table
CREATE TABLE user_permissions (
    permissionId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NOT NULL,
    permission_type ENUM('create_project', 'update_project', 'delete_project', 'manage_users', 'manage_groups', 'view_projects') NOT NULL,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (userId, permission_type)
);

-- Create folders table (replaces project_folders for simplicity)
CREATE TABLE folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(userId) ON DELETE CASCADE
);

-- Add folder_id column to websites table
ALTER TABLE websites ADD COLUMN folder_id INT NULL AFTER status;
ALTER TABLE websites ADD FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL;

-- Update existing users table to add email field for default users
UPDATE users SET email = CONCAT(username, '@example.com') WHERE email IS NULL;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, passwordHash, fullName, role) 
VALUES ('admin', '$2y$12$Fi3OAf7w0SeOoVM9v11BgeTwuVCbzmsnTPMqaYOGd0HKvq.VY8PfW', 'System Administrator', 'admin');

-- Insert default handler user (password: handler123)
INSERT INTO users (username, passwordHash, fullName, role) 
VALUES ('handler', '$2y$12$EMMWGgdib0yMBx4280x6ze9oCyn17xasgrP2gPjztrsCsqU7DMqXK', 'Project Handler', 'handler');

-- Insert default visitor user (password: visitor123)
INSERT INTO users (username, passwordHash, fullName, role) 
VALUES ('visitor', '$2y$12$RAgQ/fV3SmsGP94K0KP/NODu9vXhVNBU9lIad0WPphPqm4JoLgyd.', 'Project Visitor', 'visitor');

-- Grant default permissions - Admin gets all permissions
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'create_project' FROM users WHERE role = 'admin';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'update_project' FROM users WHERE role = 'admin';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'delete_project' FROM users WHERE role = 'admin';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'manage_users' FROM users WHERE role = 'admin';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'manage_groups' FROM users WHERE role = 'admin';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'view_projects' FROM users WHERE role = 'admin';

-- Handler gets project management permissions
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'create_project' FROM users WHERE role = 'handler';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'update_project' FROM users WHERE role = 'handler';
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'view_projects' FROM users WHERE role = 'handler';

-- Visitor gets only view permission
INSERT INTO user_permissions (userId, permission_type) 
SELECT userId, 'view_projects' FROM users WHERE role = 'visitor';

-- Create default folder
INSERT INTO folders (name, description, created_by) 
VALUES ('General Projects', 'Default folder for all projects', (SELECT userId FROM users WHERE role = 'admin'));

-- Update existing websites to map to default folder
UPDATE websites SET folder_id = (SELECT id FROM folders WHERE name = 'General Projects');