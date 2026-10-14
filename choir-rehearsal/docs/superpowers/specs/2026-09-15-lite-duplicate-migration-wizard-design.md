# Lite duplicate-install Migration Wizard

Date: 2026-09-15  
Status: approved (product: option B)  
Scope: Lite `choir-rehearsal` 0.4.49+

## Problem

From 0.4.39, GitHub/wporg packages used folder `compath-choir-rehearsal/` while existing sites keep `choir-rehearsal/` (0.4.38). Upload/update creates a second Lite copy; both load → conflict notice; Pro may not see `CHOIR_REHEARSAL_VERSION`.

## Goal

Admin-guided wizard to detect duplicate Lite folders, keep one, deactivate/delete the extras **without** running `uninstall.php` (songs stay in DB).

## Detection

Scan `WP_PLUGIN_DIR/*/choir-rehearsal.php`, exclude Pro. Count installs. Wizard/notice when count ≥ 2 **or** dual-load guard fired.

## Bootstrap

- Normal load: register migration with full plugin.
- Dual-load early return: still `require` migration class and `register_duplicate_bootstrap()` so wizard works when the **first** loaded copy is old 0.38 (no wizard) and the **second** is 0.4.49+.

## Wizard steps (admin confirms each)

1. **Report** — list folder, version, active yes/no  
2. **Choose Keep** — default `choir-rehearsal/` if present, else current `CHOIR_REHEARSAL_FILE` folder, else newest version  
3. **Deactivate** other Lite plugins (option `active_plugins` only)  
4. **Delete** other folders via filesystem delete (not `delete_plugins()` / not uninstall)  
5. **Activate** Keep if needed → success

## Safety

- `manage_options` + nonces  
- No song/CPT/option deletes  
- If folder not writable → show FTP path instructions  

## Out of scope

- Auto-migrate without confirmation  
- Renaming Plugin Name in DB  
- Changing Pro package layout  
