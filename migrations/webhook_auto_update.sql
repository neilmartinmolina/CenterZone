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
    MODIFY COLUMN status ENUM('initializing', 'building', 'deployed', 'working', 'error') NOT NULL DEFAULT 'initializing',
    ADD COLUMN IF NOT EXISTS last_commit VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS status_note TEXT NULL AFTER last_commit,
    ADD COLUMN IF NOT EXISTS checked_at TIMESTAMP NULL AFTER status_note;

UPDATE project_status SET status = 'deployed' WHERE status = 'working';

ALTER TABLE project_status
    MODIFY COLUMN status ENUM('initializing', 'building', 'deployed', 'error') NOT NULL DEFAULT 'initializing';

CREATE INDEX IF NOT EXISTS idx_projects_repo_name ON projects (github_repo_name);
