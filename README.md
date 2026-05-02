# Nucleus
Centralized Updator for Systems and Projects 

## Database

Nucleus now targets the `nucleus` database by default. The normalized 3NF schema is in `migrations/nucleus_3nf_schema.sql`.

The redesign replaces the old `folders`, `websites`, and `user_permissions` model with `subjects`, `projects`, `project_status`, `roles`, `project_members`, `activity_logs`, `files`, `comments`, and `notifications`.

Run `php init_db.php` for a clean install. If a different `nucleus` schema already exists, the initializer stops instead of rewriting data.

## GitHub webhook auto-updates

1. Create or edit a project from the Projects page. The project setup page stores:
   - `github_repo_url`: GitHub clone URL ending in `.git`.
   - `github_repo_name`: derived from the clone URL.
   - `deploy_path`: optional absolute path to the deployed Git checkout.
   - `webhook_secret`: generated per project and copied into GitHub.
   - Status phases: `initializing`, `building`, `deployed`, or `error`.
2. Optionally set `SITES_BASE_PATH` in `.env`. If omitted, it defaults to the parent folder of this Nucleus checkout.
3. In GitHub, create one webhook using the generated project webhook URL, select JSON payloads, and enable push events.

The web server user must be able to run `git pull` in each target checkout. On each push, Nucleus verifies the webhook signature, marks the project as initializing/building, pulls the checkout, pings the homepage URL, and marks the project deployed only when the homepage responds successfully.
Webhook pushes store operational activity without exposing email addresses in public views.
