-- Webhook auto-update support for the normalized Nucleus schema.
-- Run this only on databases that predate these webhook columns.

ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS github_repo_url VARCHAR(2048) NULL AFTER public_url,
    ADD COLUMN IF NOT EXISTS github_repo_name VARCHAR(255) NULL AFTER github_repo_url,
    ADD COLUMN IF NOT EXISTS deploy_path VARCHAR(2048) NULL AFTER github_repo_name,
    ADD COLUMN IF NOT EXISTS webhook_secret VARCHAR(128) NULL AFTER deploy_path,
    ADD COLUMN IF NOT EXISTS last_updated_at TIMESTAMP NULL AFTER updated_at;

ALTER TABLE project_status
    ADD COLUMN IF NOT EXISTS last_commit VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS status_note TEXT NULL AFTER last_commit,
    ADD COLUMN IF NOT EXISTS checked_at TIMESTAMP NULL AFTER status_note;

CREATE INDEX IF NOT EXISTS idx_projects_repo_name ON projects (github_repo_name);
