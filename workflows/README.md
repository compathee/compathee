# n8n workflow backups

This folder stores n8n workflows as JSON so they can be reviewed in GitHub and
deployed with the n8n API or GitHub Actions.

## Files

- `water-meter-submission.backup.json` - pre-change backup of the current
  workflow state. The `SMTP error alert` node still has the original
  hard-coded attachment list:
  `cold_attachment,hot_attachment`.
- `water-meter-submission.json` - deployable desired workflow state. The
  `SMTP error alert` node uses:
  `={{ Object.keys($binary || {}).join(',') }}`.
- `water-meter-submission-backup.md` - human-readable investigation notes for
  execution `3008`.
- `dbt-accountant-assistant.backup.json` - snapshot of **DBT Accountant
  assistant** (`vQnGxLzinMZR6Vhg`) before Word/DOCX extraction.
- `dbt-accountant-assistant.json` - published snapshot after Word/DOCX
  extraction, in-place cell patch return, unknown-user access requests
  sent only to admin Telegram `1075735475`, and Standard Books crash
  guards (HARD LIMITS; list/report reads confirm before Hansa;
  bare «счёт» is asked as konto vs supplier vs sales invoice;
  list/report reads wait for Да/Нет in the same Telegram chat
  (sendAndWait Confirm is disabled because it never resumes);
  Approve callbacks do not run the agent;
  invoice day counts use Portal `register=VIVc`/`IVVc` with injected
  usercode and page=0; accounting reads go through the query catalog).
- `standard-books-api.json` - published snapshot of **Standard Books —
  API (Portal + REST)** (`yW6RvQG3Bl87M8pK`). REST list GETs require
  `fields=` + `filter.*` and inject `limit=25` (cap 50) so empty
  register dumps cannot crash Excellent Books. Live reads always use
  company **2** (company **1** is archive); invoice counts go through
  Portal `register=VIVc` with injected usercode and page=0 (REST `/api/2/VIVc`
  returns 401 with this account). `operation=catalog` builds a query
  from `workflows/standard-books-query-catalog.json`.
- `standard-books-query-catalog.json` - live Portal templates taken from
  Excellent WebExcellent/REST examples and pinned to company **2**
  (blocked REST AccVc/TRVc/VIVc dumps stay documented, not callable).
- `dbt-accountant-docx.md` - notes for Telegram execution `3554` (`ОС.docx`)
  plus Standard Books API crash guards.

## Deploying with n8n API

Set these environment variables:

```bash
export N8N_BASE_URL="https://n8n.dbtmuuga.ee"
export N8N_API_KEY="..."
```

Then update the workflow:

```bash
curl -X PUT "$N8N_BASE_URL/api/v1/workflows/ZMRAkwLhZ5pHQp5w" \
  -H "X-N8N-API-KEY: $N8N_API_KEY" \
  -H "Content-Type: application/json" \
  --data-binary @workflows/water-meter-submission.json
```

Credentials are not stored in this repository. Confirm credential assignments
in n8n after import/update if the API response reports missing credentials.

## Deploying with GitHub Actions

Add these repository secrets in GitHub:

- `N8N_BASE_URL` - `https://n8n.dbtmuuga.ee`
- `N8N_API_KEY` - your n8n API key

Then deploy from GitHub:

1. Open the repository on GitHub.
2. Go to `Actions`.
3. Select `Deploy n8n Water Meter Workflow`.
4. Click `Run workflow`.
5. Keep the defaults:
   - workflow file: `workflows/water-meter-submission.json`
   - workflow ID: `ZMRAkwLhZ5pHQp5w`
6. Click `Run workflow`.
