# Water meter submission workflow backup

Snapshot taken before changing the `SMTP error alert` attachment configuration.

- n8n host: `https://n8n.dbtmuuga.ee`
- Workflow name: `Water meter submission`
- Workflow ID: `ZMRAkwLhZ5pHQp5w`
- Version ID at backup time: `f96574b6-1f16-4011-9dca-5f50460d6064`
- Active version ID at backup time: `f96574b6-1f16-4011-9dca-5f50460d6064`
- Workflow updated at backup time: `2026-06-08T11:07:18.273Z`
- Execution that exposed the issue: `3008`

## Current affected node

Node name: `SMTP error alert`

```json
{
  "id": "e9e0978a-8808-46b3-ba60-dccd9e46a438",
  "name": "SMTP error alert",
  "type": "n8n-nodes-base.emailSend",
  "typeVersion": 2.1,
  "position": [5824, 560],
  "webhookId": "0a9b1f7c-fb21-46df-8dcb-4bba99d60b93",
  "onError": "continueRegularOutput",
  "parameters": {
    "fromEmail": "noreply@naidud.compath.ee",
    "toEmail": "={{ $json.wm_alert_email }}",
    "subject": "={{ $json.smtp_error_subject }}",
    "emailFormat": "text",
    "text": "={{ $json.smtp_error_text }}",
    "options": {
      "attachments": "cold_attachment,hot_attachment"
    }
  }
}
```

## Upstream affected node

Node name: `Error email attachments`

This node only creates binary fields when the submitted form contains photos.
When both photos are absent, it returns `binary: {}`, while `SMTP error alert`
still references `cold_attachment,hot_attachment`.

```javascript
var row = $input.first().json;
if (!row.wm_alert_email) {
  row = $('Prepare error notify').first().json;
}

var item = { json: row, binary: {} };

var apt = String(row.apartment || '').replace(/[^a-zA-Z0-9]/g, '') || 'na';
var slug = String(row.address_slug || 'address').slice(0, 80);
var ts = String(row.timestamp || '').replace(/[:.]/g, '-').slice(0, 19);
var stem = slug + '_apt' + apt + '_' + ts;

function attach(key, base64, label) {
  if (!base64 || String(base64).trim() === '') return;
  item.binary[key] = {
    data: String(base64).trim(),
    mimeType: 'image/jpeg',
    fileExtension: 'jpg',
    fileName: stem + '_' + label + '.jpg'
  };
}

attach('cold_attachment', row.cold_photo, 'cold');
attach('hot_attachment', row.hot_photo, 'hot');

return [item];
```

## Error-path connections

```json
{
  "Prepare error notify": { "main": [[{ "node": "Unknown customer?", "type": "main", "index": 0 }]] },
  "Unknown customer?": {
    "main": [
      [{ "node": "Redis save pending", "type": "main", "index": 0 }],
      [{ "node": "Error email attachments", "type": "main", "index": 0 }]
    ]
  },
  "Redis save pending": { "main": [[{ "node": "Error path cold photo?", "type": "main", "index": 0 }]] },
  "Error path cold photo?": {
    "main": [
      [{ "node": "Error cold photo to binary", "type": "main", "index": 0 }],
      [{ "node": "Error path hot photo?", "type": "main", "index": 0 }]
    ]
  },
  "Error path hot photo?": {
    "main": [
      [{ "node": "Error hot photo to binary", "type": "main", "index": 0 }],
      [{ "node": "Error email attachments", "type": "main", "index": 0 }]
    ]
  },
  "Error email attachments": { "main": [[{ "node": "SMTP error alert", "type": "main", "index": 0 }]] },
  "SMTP error alert": { "main": [[{ "node": "Respond error", "type": "main", "index": 0 }]] }
}
```

## Planned change

Change:

```json
{ "attachments": "cold_attachment,hot_attachment" }
```

to:

```json
{ "attachments": "={{ Object.keys($binary || {}).join(',') }}" }
```

The Email Send node's attachment parameter is a string field containing
comma-separated binary property names, so the expression joins the existing
binary keys into that format. When no binary data exists, it evaluates to an
empty string and does not reference missing fields.
