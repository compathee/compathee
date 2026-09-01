# shop.compath.ee — setup checklist

WordPress store for **Choir Rehearsal Pro**, **FluentCRM**, and Stripe via **SureCart**.

## 1. WordPress

1. Create subdomain `shop.compath.ee` in hosting panel → point to new folder / database
2. Install WordPress (latest)
3. Settings → General:
   - Site title: `Compath Shop`
   - Site URL: `https://shop.compath.ee`
4. Settings → Permalinks → **Post name** (required for SureCart)
5. Install **SSL** (Let's Encrypt) — mandatory for Stripe

## 2. Plugins to install

| Plugin | Where | Purpose |
|--------|-------|---------|
| **SureCart** | [wordpress.org/plugins/surecart](https://wordpress.org/plugins/surecart/) | Checkout, subscriptions, license keys, Pro zip delivery |
| **FluentCRM** | [wordpress.org/plugins/fluent-crm](https://wordpress.org/plugins/fluent-crm/) | Contacts, email lists, post-purchase automations |
| **FluentSMTP** (recommended) | wordpress.org | Reliable email delivery (order + license emails) |

Optional later:
- **Fluent Forms** — contact / quote forms linked to FluentCRM

**Do not install** WooCommerce — SureCart replaces it for digital products.

## 3. SureCart setup

1. Plugins → SureCart → run setup wizard
2. Connect **Stripe** (live mode when ready; start in **test mode**)
3. Settings → Branding → logo, colors (Compath OÜ)
4. Create product **Choir Rehearsal Pro**:
   - Price: **€49 / year** (subscription)
   - Downloads: upload `choir-rehearsal-pro.zip` (Lite + Pro addon bundle or Pro zip)
   - **Enable license creation** → activation limit **1**
   - Save
5. Create product **Done-for-you setup** (optional):
   - One-time **€120**
6. Copy **Checkout URL** for Pro → use on rehearsal.compath.ee

## 4. FluentCRM setup

1. FluentCRM → Settings → complete wizard
2. Lists:
   - `choir-customers`
   - `newsletter`
3. After SureCart purchase (when integration available) or manual import:
   - Tag buyers `choir-pro`
4. Automation (example):
   - Trigger: contact tagged `choir-pro`
   - Email: welcome + link to documentation `https://rehearsal.compath.ee`

## 5. Stripe ↔ api.compath.ee (accounting)

On **shop.compath.ee** SureCart owns Stripe checkout. Accounting is separate:

1. Deploy [api.compath.ee](../api.compath.ee/README.md) PHP hub
2. Stripe Dashboard → Webhooks → `https://api.compath.ee/webhook/stripe`
3. Events: `invoice.paid`, `checkout.session.completed`
4. ERPLY Books API token in hub `config.php`

Flow: **Pay in Stripe → Hub creates paid invoice in ERPLY** (no invoice before payment).

## 6. Customer journey (target)

```
rehearsal.compath.ee  →  "Subscribe €49/year" button
        ↓
shop.compath.ee (SureCart Checkout / Stripe)
        ↓
Email: license key + download choir-rehearsal-pro.zip
        ↓
Customer site: install Choir Rehearsal (Lite) + Choir Rehearsal Pro
        ↓
api.compath.ee → ERPLY paid invoice (automatic)
```

## 7. What customers install

| Package | Contents |
|---------|----------|
| **Lite** | `choir-rehearsal.zip` — free, wordpress.org / GitHub, max 4 tracks, no mic |
| **Pro** | `choir-rehearsal-pro.zip` — requires Lite, unlimited tracks + recording |

Pro buyers get **both** zips (or one bundle zip with both folders).

## 8. Secrets checklist

| Secret | Where |
|--------|-------|
| Stripe API keys | SureCart settings (not in git) |
| Stripe webhook secret | `api.compath.ee/config/config.php` |
| ERPLY API token | `api.compath.ee/config/config.php` |
| SureCart public token | Choir Rehearsal Pro plugin (when SDK added) |

## 9. Test order (before going live)

1. Stripe **test mode** ON
2. SureCart test purchase → license key appears in customer dashboard
3. Install Lite + Pro on test WP → unlimited tracks + Record button visible
4. Stripe CLI or test payment → check ERPLY for new paid invoice
5. `https://api.compath.ee/health` returns `ok`

## 10. Go live

1. Stripe live mode
2. SureCart live products
3. Update rehearsal.compath.ee checkout buttons
4. Remove public Pro zip from GitHub Releases (Lite only on GitHub)
