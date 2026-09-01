# Compath Integration Hub (api.compath.ee)

Stripe webhooks → paid sales invoice in **ERPLY Books** (Merit Aktiva later).

## Upload layout on hosting

Point the subdomain **document root** to the `public/` folder:

```
api.compath.ee/
├── public/          ← document root (FTP here)
│   ├── index.php
│   └── .htaccess
├── src/
├── config/
│   ├── config.example.php
│   └── config.php       ← create from example (not in git)
└── data/                ← writable (chmod 755), SQLite logs
```

If your host only allows one `public_html` folder, upload **contents of `public/`** into `public_html/` and place `src`, `config`, `data` **one level above** (outside web root) when possible. Then edit `public/index.php` bootstrap path if needed.

## Setup

1. Copy `config/config.example.php` → `config/config.php`
2. Fill in:
   - `stripe_webhook_secret` — from Stripe Dashboard → Webhooks
   - `erply_api_token` — from ERPLY Books → Settings → API
3. Create writable `data/` directory (`chmod 755` or `775`)
4. In **Stripe Dashboard → Developers → Webhooks → Add endpoint**:
   - URL: `https://api.compath.ee/webhook/stripe`
   - Events: `invoice.paid`, `checkout.session.completed`
5. Health check: `https://api.compath.ee/health` → `ok compath-hub ...`

## How it works (autonomous)

1. Customer pays in Stripe (SureCart Checkout on shop.compath.ee)
2. Stripe POSTs webhook to this hub (24/7, no cron needed)
3. Hub verifies signature, skips duplicates (SQLite)
4. Hub creates **confirmed sales invoice + payment** in ERPLY Books via Partner API
5. Hub returns HTTP 200 — Stripe stops retrying

**Invoice is created only after successful payment** — matches your accounting workflow.

## Switch to Merit Aktiva later

1. Implement `MeritAktivaAdapter` (see Merit API docs)
2. Set `'accounting_provider' => 'merit'` in `config.php`
3. Stripe + SureCart unchanged

## Requirements

- PHP 8.0+
- ext-curl, ext-json, ext-pdo_sqlite
- HTTPS (required by Stripe)

## Test with Stripe CLI

```bash
stripe listen --forward-to https://api.compath.ee/webhook/stripe
stripe trigger invoice.paid
```

Use the `whsec_...` secret from `stripe listen` in `config.php` while testing.
