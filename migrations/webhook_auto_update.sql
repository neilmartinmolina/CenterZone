-- Webhook auto-update support for Nucleus.
-- Run this once on the target MariaDB database.

ALTER TABLE websites
    ADD COLUMN IF NOT EXISTS repo_url VARCHAR(2048) NULL AFTER url,
    ADD COLUMN IF NOT EXISTS repo_name VARCHAR(255) NULL AFTER repo_url,
    ADD COLUMN IF NOT EXISTS last_commit VARCHAR(64) NULL AFTER repo_name,
    ADD COLUMN IF NOT EXISTS webhook_secret VARCHAR(255) NULL AFTER last_commit,
    ADD COLUMN IF NOT EXISTS deploy_path VARCHAR(1024) NULL AFTER webhook_secret,
    ADD COLUMN IF NOT EXISTS github_updated_by VARCHAR(255) NULL AFTER deploy_path,
    ADD COLUMN IF NOT EXISTS github_updated_by_email VARCHAR(255) NULL AFTER github_updated_by,
    ADD COLUMN IF NOT EXISTS github_updated_by_username VARCHAR(255) NULL AFTER github_updated_by_email;

CREATE INDEX IF NOT EXISTS idx_websites_repo_name ON websites (repo_name);
