# Nucleus
Centralized Updator for Systems and Projects 

## Database

Nucleus now targets the `nucleus` database by default. The normalized 3NF schema is in `migrations/nucleus_3nf_schema.sql`.

The redesign replaces the old `folders`, `websites`, and `user_permissions` model with `subjects`, `projects`, `project_status`, `roles`, `project_members`, `activity_logs`, `files`, `comments`, and `notifications`.

Run `php init_db.php` for a clean install. If a different `nucleus` schema already exists, the initializer stops instead of rewriting data.

## Deployment monitoring

1. Create or edit a project from the Projects page. The project setup page stores:
   - `github_repo_url`: GitHub clone URL ending in `.git`.
   - `github_repo_name`: derived from the clone URL.
   - `deployment_mode`: `hostinger_git` by default, or `custom_webhook`.
   - `webhook_secret`: generated per project and copied into GitHub.
   - Status phases: `initializing`, `building`, `deployed`, or `error`.
2. For `hostinger_git`, let Hostinger own deployment. Nucleus monitors the public URL, `status.json` if present, `/api/status` if present, optional `version.json`, and HTTP reachability.
3. For `custom_webhook`, put a deploy script in the deployed project, such as `deploy.example.php` adapted as `deploy.php`.
4. In GitHub, use one webhook only. In `hostinger_git` mode, point it at Hostinger's Git deployment flow. In `custom_webhook` mode, point it at the deployed project's `deploy.php`.

Nucleus never deploys from a webhook and must not be configured as a second GitHub webhook. It polls each project's status endpoints, saves every result to `deployment_checks`, and mirrors the current read-only status into the dashboard. If a Hostinger Git project has no remote status file but the homepage is reachable, Nucleus marks it online/deployed with the note `Hostinger Git mode: no remote status file found.`

Optional `version.json` format for any project:

```json
{
  "project": "ProjectName",
  "version": "1.0.0",
  "commit": "manual-or-github-hash",
  "branch": "main",
  "updated_at": "2026-05-02 17:00:00"
}
```
