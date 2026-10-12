# OMR compare layers A/B/C — resource & token report

**Piece:** А. Висков — Херувимская песнь  
**Started (UTC):** 2026-10-12T12:24:57Z  
**Jira:** WP-25

## Resource & token accounting (per layer)

| Layer | Wall s | CPU user s | CPU sys s | Peak RSS MB | LLM tokens in/out | LLM € |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| A_structural | 0.134 | 0.169 | 0.008 | 78.8 | 0/0 | 0.0000 |
| B_visual_align | 5.65 | 4.139 | 0.587 | 278.6 | 0/0 | 0.0000 |
| C_note_level | 0.023 | 0.023 | 0.0 | 278.6 | 0/0 | 0.0000 |
| **TOTAL** | **5.807** |  |  | **278.6** | **0** | **0.0000** |

> LLM tokens = 0 on all layers (algorithmic). Production billing: CPU-time / pages for OMR+compare; LLM only if optional adjudication is enabled via `compath_llm_gateway`.

## A_structural

- Disputes: **11** `{'high': 4, 'medium': 6, 'low': 1}`
- Resources note: No LLM. Pure CPU parse of MusicXML/.omr/log. Tokens=0. Would route via compath_llm_gateway only if future LLM post-check enabled (WP-22 §3.17).

| Sev | Type | Page/Sys/Part | Evidence |
| --- | --- | --- | --- |
| high | `measure_count_mismatch` | — | measures per part: {'P1': 26, 'P2': 26, 'P3': 24, 'P4': 24} |
| medium | `generic_part_name` | P1 | name='Voice' (expected SATB-like) |
| medium | `generic_part_name` | P2 | name='Voice' (expected SATB-like) |
| medium | `generic_part_name` | P3 | name='Voice' (expected SATB-like) |
| medium | `generic_part_name` | P4 | name='Voice' (expected SATB-like) |
| high | `key_signature_thrash` | P1 | fifths sequence (n=5): [-2, 0, -2, -2, -2]… changes=2 |
| high | `part_count_unstable_across_pages` | — | logical parts by sheet: {1: ['2', '3', '4'], 2: ['1', '2', '3', '4'], 3: ['1', '2', '3', '4'], 4: []} |
| low | `low_confidence_symbols` | — | 6 symbols with grade<0.5 |
| medium | `tesseract_ocr_init_failed` | — | eng legacy mode failed — lyrics/text unreliable |
| high | `export_npe_wedges` | — | 8 NullPointerException during MusicXML export (wedges) |
| medium | `musicxml_import_errors` | — | MuseScore: spanner-related≈28, unknown arpeggio≈7 |

- Measure skew: 2
- Low-grade symbols: 6
- Log: `{'WARN': 13, 'NPE': 8, 'tesseract_legacy_fail': 4}`

## B_visual_align

- Disputes: **4** `{'high': 4}`
- Resources note: No LLM. CPU image ops (resize, SSIM, projections). Tokens=0. Heavy RAM on page rasters @150dpi; no GPU; no compath_llm_gateway.

| Sev | Type | Page/Sys/Part | Evidence |
| --- | --- | --- | --- |
| high | `page_vs_recon_pdf_weak` | p1 | best SSIM vs MusicXML→PDF page (recon-4.png)=0.522 (layout reflow expected; still flags weak match) |
| high | `page_vs_recon_pdf_weak` | p2 | best SSIM vs MusicXML→PDF page (recon-4.png)=0.531 (layout reflow expected; still flags weak match) |
| high | `page_vs_recon_pdf_weak` | p3 | best SSIM vs MusicXML→PDF page (recon-4.png)=0.541 (layout reflow expected; still flags weak match) |
| high | `page_vs_recon_pdf_weak` | p4 | best SSIM vs MusicXML→PDF page (recon-4.png)=0.539 (layout reflow expected; still flags weak match) |

- Mean page SSIM (orig vs OMR binary): **0.8352**
- Mean best recon-page SSIM: **0.5332**
- Heatmaps saved under `/opt/cursor/artifacts/omr-viskov/compare`

## C_note_level

- Disputes: **1** `{'medium': 1}`
- Resources note: No LLM. CPU MusicXML→piano-roll + shift-F1 + .omr head parse. Tokens=0. Same compute class as WP-20 mir_eval path; not billed as LLM tokens. Future optional LLM adjudication of disputed bars would add tokens via gateway.

| Sev | Type | Page/Sys/Part | Evidence |
| --- | --- | --- | --- |
| medium | `low_grade_noteheads` | — | 47 noteheads grade<0.55 (sample grades=[0.52, 0.47, 0.5, 0.533, 0.482, 0.488, 0.509, 0.474]) |

- OMR heads: 1531 (with pitch 475)
- MIDI range: [43, 79]
- Silent-part measures: 1
- Note: No human-verified ground-truth MusicXML for this piece; layer C uses internal consistency + Audiveris head grades as proxy. True GT compare would replace ensemble proxy.

## Production metering mapping (WP-22 / WP-23)

| Layer | Meter in Server Admin | Counts toward |
| --- | --- | --- |
| A structural | CPU-ms, parse time | Pro+AI / Partner job overhead (cheap) |
| B visual | CPU-ms, RAM peak, raster MB | pages × dpi cost; not LLM |
| C note-level | CPU-ms (mir_eval-class) | same job; not LLM |
| (future) LLM bar judge | `compath_llm_gateway` tokens | AI token budget / Pro+AI |
| Audiveris OMR | worker CPU/RAM/queue wait | pages OMR quota |
