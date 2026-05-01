-- Subject request workflow and subject-level access grants.

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
