-- Webhook auto-update support for the normalized Nucleus schema.
-- Run this only on databases that predate these webhook columns.

ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS github_repo_url VARCHAR(2048) NULL AFTER public_url,
    ADD COLUMN IF NOT EXISTS github_repo_name VARCHAR(255) NULL AFTER github_repo_url,
    ADD COLUMN IF NOT EXISTS deployment_mode ENUM('hostinger_git', 'custom_webhook') NOT NULL DEFAULT 'hostinger_git' AFTER github_repo_name,
    ADD COLUMN IF NOT EXISTS deploy_path VARCHAR(2048) NULL AFTER deployment_mode,
    ADD COLUMN IF NOT EXISTS webhook_secret VARCHAR(128) NULL AFTER deploy_path,
    ADD COLUMN IF NOT EXISTS last_updated_at TIMESTAMP NULL AFTER updated_at;

ALTER TABLE project_status
    MODIFY COLUMN status ENUM('initializing', 'building', 'deployed', 'warning', 'working', 'error') NOT NULL DEFAULT 'initializing',
    ADD COLUMN IF NOT EXISTS last_commit VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS status_note TEXT NULL AFTER last_commit,
    ADD COLUMN IF NOT EXISTS checked_at TIMESTAMP NULL AFTER status_note;

UPDATE project_status SET status = 'deployed' WHERE status = 'working';

ALTER TABLE project_status
    MODIFY COLUMN status ENUM('initializing', 'building', 'deployed', 'warning', 'error') NOT NULL DEFAULT 'initializing';

CREATE TABLE IF NOT EXISTS deployment_checks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('initializing', 'building', 'deployed', 'warning', 'error') NOT NULL,
    http_code INT NULL,
    response_time_ms INT NULL,
    status_source VARCHAR(50) NOT NULL,
    error_message TEXT NULL,
    version VARCHAR(100) NULL,
    commit_hash VARCHAR(255) NULL,
    branch VARCHAR(255) NULL,
    remote_updated_at TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    INDEX idx_deployment_checks_project_checked (project_id, checked_at),
    INDEX idx_deployment_checks_project_status (project_id, status)
);

CREATE INDEX IF NOT EXISTS idx_projects_repo_name ON projects (github_repo_name);
