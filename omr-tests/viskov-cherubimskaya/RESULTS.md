# OMR trial — А. Висков, Херувимская песнь

**Jira:** WP-25 (relates WP-19, WP-22)
**Date:** 2026-10-12

## Environment

| Field | Value |
| --- | --- |
| Engine | Audiveris **5.11.0** (`9e1e55c`) |
| Java | OpenJDK 25 |
| OCR | Tesseract 5.5.2 (`eng` + `osd` from tessdata_fast) |
| OS | Linux amd64 |
| Command | `Audiveris -batch -transcribe -export -save -output … -- A.Viskov-Cherubimskaya.pdf` |

## Input

| Field | Value |
| --- | --- |
| File | `A.Viskov-Cherubimskaya.pdf` (А. Висков — Херувимская песнь) |
| Size | 827939 bytes |
| Pages | 4 (A4) |
| Producer | Skia/PDF m108 Google Docs Renderer |

## Run

| Field | Value |
| --- | --- |
| Duration | **53 s** |
| Exit code | **0** |
| Outputs | `.mxl` 9516 B, `.omr` 806537 B, book log |

## MusicXML analysis

| Field | Value |
| --- | --- |
| Well-formed XML | yes (`score-partwise`) |
| Unpacked XML | 156115 B |
| Score parts | **4** (all named `Voice`) |
| Notes (total) | **349** |
| Measures (sum) | 100 (P1/P2: 26, P3/P4: 24) |
| Pitch samples | B4 A4 C5 B4 G4 D5 … |

## Issues observed

1. Tesseract legacy init failed for `eng` — text/lyrics OCR degraded.
2. Export WARN/NPE (8×) in `PartwiseBuilder$WedgeIterators` (`timeOffset` null); export still completed.
3. Part names not SATB — generic `Voice`; measure counts differ between parts.
4. Unstable part count across sheets (sheet1: 3 parts, sheet2: 4 parts).

## Verdict

| Criterion | Result |
| --- | --- |
| No hard crash | PASS |
| MusicXML/MXL produced | PASS |
| Valid XML | PASS |
| Quality without edit | FAIL (needs review UI) |
| Audiveris as worker candidate | PASS as best-effort engine |

## Artifacts

- `out/A.Viskov-Cherubimskaya.mxl`
- `out/A.Viskov-Cherubimskaya.omr`
- `/opt/cursor/artifacts/omr-viskov/`
