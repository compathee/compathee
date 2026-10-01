# SureCart licensing (Pro 0.5.0+)

Pro uses the official [SureCart WordPress SDK](https://github.com/surecart/wordpress-sdk) vendored in `licensing/`.

## Before uploading to shop.compath.ee

1. Copy your **Public token** (`pt_…`) from SureCart → Settings into `includes/surecart-config.php`.
2. Build the zip:

```bash
./scripts/build-pro-zip.sh
# → dist/choir-rehearsal-pro.zip
```

3. SureCart product **Choir Rehearsal Pro**:
   - Downloads → Secure Storage → upload `choir-rehearsal-pro.zip`
   - Enable **license creation** (activation limit 1)
   - Set **Current Release** to this zip → Save

## Customer flow

1. Install Lite, then Pro.
2. **Choir Rehearsal → Pro License** → paste key → Activate.
3. Pro features unlock when `activation_id` is stored locally.

## Files

| Path | Role |
|------|------|
| `licensing/` | SureCart SDK |
| `release.json` | Product identity for updates (`slug` = folder name) |
| `includes/class-licensing.php` | Boot SDK, license gate, admin notices |
| `includes/surecart-config.php` | Public token |

## Lite companion

Lite `Choir_Rehearsal_Edition::is_pro()` applies the `choir_rehearsal_is_pro` filter so an unlicensed Pro install stays on Lite limits.
