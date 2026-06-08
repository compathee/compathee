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
