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

1. Install Lite from WordPress.org (or GitHub), then upload the Pro zip from the shop.compath.ee account beside it. Do not replace Lite.
2. **Choir Rehearsal → Pro License** → paste key → Activate.
3. Pro features unlock when `activation_id` is stored locally.
4. From **0.5.0**, WordPress offers Pro updates automatically (**Dashboard → Updates** or **Plugins**) while the license stays active. Customers can also enable auto-updates for the plugin.
5. Anyone still on Pro **older than 0.5.0** (no licensing SDK) must download 0.5.0 from their shop account once and replace the plugin manually (deactivate/delete the old Pro and upload the new zip, or use **Replace current with uploaded**), then activate the license. After that, updates are automatic.

The SDK updater starts with the client (`licensing/src/Client.php` constructs `Updater` for `CHOIR_REHEARSAL_PRO_FILE`). The plugin folder slug is `choir-rehearsal-pro`, matching `release.json`. `License::get_current_release()` requires both a stored license key and an activation id, so update packages are requested only for an activated license.

## Files

| Path | Role |
|------|------|
| `licensing/` | SureCart SDK |
| `release.json` | Product identity for updates (`slug` = folder name) |
| `includes/class-licensing.php` | Boot SDK, license gate, admin notices |
| `includes/surecart-config.php` | Public token |

## Lite companion

Lite `Choir_Rehearsal_Edition::is_pro()` applies the `choir_rehearsal_is_pro` filter so an unlicensed Pro install stays on Lite limits.
