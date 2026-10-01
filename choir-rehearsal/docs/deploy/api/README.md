# Feedback relay

Standalone PHP endpoint for Choir Rehearsal feedback. Customer sites POST JSON here. This script creates a Jira task and emails the submitter a confirmation. It does not use WordPress or Composer.

Public URL after deploy: `https://rehearsal.compath.ee/api/feedback.php`

## Where the files go

The live relay is already at:

`/domains/rehearsal.compath.ee/public_html/api/feedback.php`

Upload changes to `feedback.php` and `.htaccess` by hand. The GitHub Action **Deploy rehearsal.compath.ee** uploads only `index.html`, the site `.htaccess`, and `health.txt`. It excludes `api/**` and does not use a clean slate, so it does not replace or delete `api/config.php`, `api/feedback-rate/`, or logs.

`config.example.php` and this README stay in git only.

## Config (not in git)

Copy `config.example.php` to the same folder as the script:

`/domains/rehearsal.compath.ee/public_html/api/config.php`

`api/.htaccess` denies `config.php` and turns off directory indexes. The script looks for that file first (after `CHOIR_FEEDBACK_CONFIG`). If it is missing, the fallback is `/domains/rehearsal.compath.ee/private/feedback-config.php`, which this host's FTP cannot reach.

The example already sets `jira_email` to `compath@compath.ee`. That account is the Basic-auth user for `https://compath.atlassian.net`, project `WP`. Issue types there are Task and Sub-task; the relay creates a Task. Fill in only the secrets:

| Key | Value |
|-----|--------|
| `jira_token` | Jira Cloud API token for `compath@compath.ee`. Keep it out of git. |
| `smtp_pass` | Password for `compath@compath.ee` |

Other defaults already match: Jira `https://compath.atlassian.net`, project `WP`, issue type `Task`, SMTP `compath.ee:465`, login `compath@compath.ee`, From `Compath Support <support@compath.ee>`, Reply-To `support@compath.ee`.

With `config.php` beside the script, the rate-limit store is `api/feedback-rate/` and mail failures are appended to `api/feedback-mail.log`. Both are blocked by `.htaccess` (`*.json`, `*.log`, and `Options -Indexes`). If that directory cannot be written, requests are still accepted and a line is logged.

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
