# Pro songs backup export / import (Settings)

Date: 2026-09-14  
Status: approved (product)  
Scope: Pro only — `choir-rehearsal-pro`

## Goal

Give Pro sites a reliable library backup: export all songs to a downloadable ZIP, import from a local ZIP while keeping non-matching songs, with explicit control over slug matches.

Demo library (Lite) stays separate and unchanged.

## Placement

Choir Rehearsal → Settings, **below** the Lite “Demo library” block, via existing hook `choir_rehearsal_settings_tools`.

Section title: **Backup songs** (Pro only; requires `Choir_Rehearsal_Edition::is_pro()` and `manage_options`).

## Export

- Control: button **Export songs (.zip)**
- Behavior: build ZIP of the full rehearsal library and trigger a browser download (Content-Disposition attachment)
- Contents:
  - `manifest.json` (format id `compath-choir-rehearsal-backup`, versioned)
  - media files (audio tracks, PDF scores) referenced by the manifest
- Includes: song title, slug, status, public flag, tracks (title, order, voice slug), attached audio/PDF when readable
- Requires PHP `ZipArchive`; if missing, show admin warning and hide export/import actions

## Import

- Control: file input (`.zip`) + primary **Import** button
- Match policy control (default **Skip**):
  - Label idea: **What to do with matches?**
  - **Skip** (default): if an imported song’s **slug** already exists, do not import that song
  - **Replace**: if slug exists, **delete** that existing song (and its tracks / song-owned media as already implemented for wipe) then create the song from the ZIP entry
- Non-matching songs from the ZIP are always added
- Existing songs that are not in the ZIP are never deleted by Import
- No “replace entire library” / wipe-all restore control in this UI

### Match definition

A match is the same **post slug** (`post_name`). Title may be shown in notices for clarity but is not the match key.

## Out of scope

- Moving backup UI or logic into Lite
- Rewriting Demo library to use ZIP import
- Sharing a common “create song from array” helper with `Choir_Rehearsal_Demo_Data` (optional later)
- SureCart licensing gates beyond current Pro unlock

## Implementation notes

- Primary file: `choir-rehearsal-pro/includes/class-backup.php`
- Adjust import mode from current merge / full-replace buttons to: merge + optional per-slug replace
- Keep manifest format compatible with existing 0.4.45+ exports when possible
- Bump Pro version after UI/behavior change; refresh Pro draft GitHub zip when shipping

## Success criteria

1. Export downloads a ZIP of the current library without leaving the Settings page flow (standard browser save dialog)
2. Import with Skip adds only new slugs; matching slugs are skipped; other existing songs remain
3. Import with Replace deletes matching slugs then recreates them from ZIP; non-matching existing songs remain; new slugs are added
4. Demo library section still works independently for Lite and Pro
5. Section does not appear when Pro is inactive
