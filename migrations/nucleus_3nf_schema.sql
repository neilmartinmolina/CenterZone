-- Nucleus 3NF database redesign
-- Creates the new database and normalized tables for subjects, projects,
-- project status, project membership, files, comments, notifications, and logs.

CREATE DATABASE IF NOT EXISTS nucleus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nucleus;

CREATE TABLE IF NOT EXISTS roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name ENUM('admin', 'handler', 'visitor') NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    userId INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    fullName VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL UNIQUE,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT PRIMARY KEY AUTO_INCREMENT,
    subject_code VARCHAR(50) NOT NULL UNIQUE,
    subject_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(userId) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS subject_members (
    subject_member_id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NOT NULL,
    userId INT NOT NULL,
    access_level ENUM('manager', 'contributor', 'viewer') NOT NULL DEFAULT 'manager',
    added_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(userId) ON DELETE SET NULL,
    UNIQUE KEY unique_subject_member (subject_id, userId)
);

CREATE TABLE IF NOT EXISTS subject_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    requested_by INT NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(userId) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(userId) ON DELETE SET NULL,
    INDEX idx_subject_requests_status (status),
    INDEX idx_subject_requests_requested_by (requested_by)
);

CREATE TABLE IF NOT EXISTS project_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    requested_by INT NOT NULL,
    subject_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    public_url VARCHAR(2048) NOT NULL,
    github_repo_url VARCHAR(2048) NOT NULL,
    github_repo_name VARCHAR(255) NULL,
    requested_version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    message TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(userId) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(userId) ON DELETE SET NULL,
    INDEX idx_project_requests_status (status),
    INDEX idx_project_requests_subject_status (subject_id, status),
    INDEX idx_project_requests_requested_by (requested_by)
);

CREATE TABLE IF NOT EXISTS projects (
    project_id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NULL,
    project_name VARCHAR(255) NOT NULL,
    public_url VARCHAR(2048) NULL,
    github_repo_url VARCHAR(2048) NULL,
    github_repo_name VARCHAR(255) NULL,
    deploy_path VARCHAR(2048) NULL,
    webhook_secret VARCHAR(128) NULL,
    current_version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    saved_at TIMESTAMP NULL,
    last_updated_at TIMESTAMP NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(userId) ON DELETE RESTRICT,
    INDEX idx_projects_subject (subject_id),
    INDEX idx_projects_owner (owner_id),
    INDEX idx_projects_repo_name (github_repo_name)
);

CREATE TABLE IF NOT EXISTS project_status (
    status_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL UNIQUE,
    status ENUM('initializing', 'building', 'deployed', 'error') NOT NULL DEFAULT 'initializing',
    last_commit VARCHAR(255) NULL,
    status_note TEXT NULL,
    checked_at TIMESTAMP NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(userId) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS project_members (
    project_member_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    userId INT NOT NULL,
    member_role ENUM('owner', 'contributor', 'viewer') NOT NULL DEFAULT 'contributor',
    added_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(userId) ON DELETE SET NULL,
    UNIQUE KEY unique_project_member (project_id, userId)
);

CREATE TABLE IF NOT EXISTS activity_logs (
    activity_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NULL,
    userId INT NULL,
    action VARCHAR(100) NOT NULL,
    version VARCHAR(50) NULL,
    note TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE SET NULL,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE SET NULL,
    INDEX idx_activity_project_created (project_id, created_at),
    INDEX idx_activity_user_created (userId, created_at)
);

CREATE TABLE IF NOT EXISTS files (
    file_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NULL,
    file_size BIGINT UNSIGNED NULL,
    visibility ENUM('private', 'project') NOT NULL DEFAULT 'project',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(userId) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    userId INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NULL,
    project_id INT NULL,
    type ENUM('status', 'comment', 'file', 'system') NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_attempts (
    attemptId INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    resetId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expiry TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    attemptId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS error_logs (
    logId INT PRIMARY KEY AUTO_INCREMENT,
    error_message TEXT NOT NULL,
    query TEXT,
    params TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO roles (role_name, description) VALUES
('admin', 'Full visibility and system monitoring access'),
('handler', 'Can create and manage owned or assigned projects'),
('visitor', 'Can view public non-sensitive project information');

INSERT IGNORE INTO users (username, passwordHash, fullName, email, role_id)
SELECT 'admin', '$2y$12$Fi3OAf7w0SeOoVM9v11BgeTwuVCbzmsnTPMqaYOGd0HKvq.VY8PfW', 'System Administrator', 'admin@example.com', role_id
FROM roles WHERE role_name = 'admin';

INSERT IGNORE INTO users (username, passwordHash, fullName, email, role_id)
SELECT 'handler', '$2y$12$EMMWGgdib0yMBx4280x6ze9oCyn17xasgrP2gPjztrsCsqU7DMqXK', 'Project Handler', 'handler@example.com', role_id
FROM roles WHERE role_name = 'handler';

INSERT IGNORE INTO users (username, passwordHash, fullName, email, role_id)
SELECT 'visitor', '$2y$12$RAgQ/fV3SmsGP94K0KP/NODu9vXhVNBU9lIad0WPphPqm4JoLgyd.', 'Project Visitor', 'visitor@example.com', role_id
FROM roles WHERE role_name = 'visitor';

INSERT IGNORE INTO subjects (subject_code, subject_name, description, created_by)
SELECT 'GENERAL', 'General Projects', 'Default subject for uncategorized academic projects.', userId
FROM users WHERE username = 'admin';
