# Nucleus
Centralized Updator for Systems and Projects 

## GitHub webhook auto-updates

1. Run `migrations/webhook_auto_update.sql` on the Nucleus database.
2. Create or edit a project from the Websites page. The project setup page stores:
   - `repo_url`: GitHub clone URL ending in `.git`.
   - `repo_name`: derived from the clone URL.
   - `webhook_secret`: generated per project and copied into GitHub.
   - `deploy_path`: optional absolute path to the local checkout. If blank, Nucleus uses `SITES_BASE_PATH/repo_name`.
3. Optionally set `SITES_BASE_PATH` in `.env`. If omitted, it defaults to the parent folder of this Nucleus checkout.
4. In GitHub, create a webhook pointing to `https://domain.com/nucleus/webhook.php`, select JSON payloads, and enable push events.

The web server user must be able to run `git pull` in each target checkout.
Webhook pushes also store the GitHub commit author as the displayed "Updated By" value.
