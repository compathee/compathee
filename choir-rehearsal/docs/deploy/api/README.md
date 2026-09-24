# Feedback relay

Standalone PHP endpoint for Choir Rehearsal feedback. Customer sites POST JSON here. This script creates a Jira task and emails the submitter a confirmation. It does not use WordPress or Composer.

Public URL after deploy: `https://rehearsal.compath.ee/api/feedback.php`

## Where the files go

The GitHub Action **Deploy rehearsal.compath.ee** copies `feedback.php` and this folder's `.htaccess` into the FTP document root (secret `REHEARSAL_FTP_REMOTE_DIR`). On compath.ee that directory is:

`/domains/rehearsal.compath.ee/public_html/`

So the script lands at:

`/domains/rehearsal.compath.ee/public_html/api/feedback.php`

`config.example.php` and this README stay in git only. The workflow does not upload them.

The Action runs on `workflow_dispatch`, and on push to its watched branches when `choir-rehearsal/docs/deploy/**` changes. Pushing the plugin branch alone does not deploy the site.

PHP on this host is required and has not been confirmed from the repository. If `feedback.php` is downloaded as source or returns a server error, the Apache vhost is not executing PHP.

## Config (not in git)

Create the private directory next to `public_html` (not inside it):

`/domains/rehearsal.compath.ee/private/feedback-config.php`

Copy `config.example.php` there. The script resolves that path as two levels above `api/` (`public_html/api` → domain root → `private/feedback-config.php`). Override with the environment variable `CHOIR_FEEDBACK_CONFIG` if the layout differs.

The example already sets `jira_email` to `compath@compath.ee`. That account is the Basic-auth user for `https://compath.atlassian.net`, project `WP`. Issue types there are Task and Sub-task; the relay creates a Task. Fill in only the secrets:

| Key | Value |
|-----|--------|
| `jira_token` | Jira Cloud API token for `compath@compath.ee`. Keep it out of git. |
| `smtp_pass` | Password for `compath@compath.ee` |

Other defaults already match: Jira `https://compath.atlassian.net`, project `WP`, issue type `Task`, SMTP `compath.ee:465`, login `compath@compath.ee`, From `Compath Support <support@compath.ee>`, Reply-To `support@compath.ee`.

The PHP user must be able to create `/domains/rehearsal.compath.ee/private/feedback-rate/` (per-IP counters) and append `/domains/rehearsal.compath.ee/private/feedback-mail.log`. If the rate directory cannot be written, requests are still accepted and a line is logged.

## Request

`POST` JSON, no CORS (WordPress calls it server-side):

- `message`, `title`, `type` (`bug`, `wish`, `other`)
- `name` (optional), `email` (required)
- `locale`, `site_url`, `role`
- `edition` (`Lite` or `Pro`), `plugin_version`, `pro_version`, `wp_version`, `php_version`
- `pro` (boolean) and `license` (SureCart ids; masked key only when no public id)
- `company` honeypot, must be empty

Response: `{"ok":true,"case":"WP-57"}`. A failed confirmation email still returns that JSON; the failure is appended to `feedback-mail.log` without the API token or SMTP password.

Jira summary: `[CR][Pro] …` or `[CR] …`. Labels: `choir-rehearsal`, `feedback`, and `pro` on Pro sites. Description stays English. The email follows the locale: Estonian for `et` / `et_EE`, Russian for `ru*`, English otherwise.

The relay sees the WordPress server's IP, so the file rate limit is per choir host (default 40 requests per hour). Each plugin also limits its own visitors before calling out.
