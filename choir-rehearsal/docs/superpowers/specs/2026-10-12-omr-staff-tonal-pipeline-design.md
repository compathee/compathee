# OMR: staff preprocess → Audiveris → tonal / meter / written-grid

**Date:** 2026-10-12  
**Status:** Draft for review  
**Related:** WP-19, WP-22 §3.18, WP-23, WP-25  
**Product framing:** Grammar + staff repair are licensed capabilities of Compath OMR / Pro+AI — not a free public “harmony textbook API”.

## 1. Goal

Reliable choir-score OMR for Compath: PDF/scan → MusicXML that respects **notation** (staff geometry, key, meter, written durations), then optional playback tempo and later audio alignment.

We do **not** replace Audiveris with a full custom OMR in v1. We own the stages where Audiveris is weak: missing staff lines, inconsistent key/meter across a system, spurious pitch “corrections”, and a written-event timing grid.

## 2. Non-goals (v1)

- Full custom OMR engine (notehead/rhythm CNN from scratch).
- Free public JSON “harmony textbook” API.
- Requiring wall-clock tempo before structural verification.
- Auto-rewriting chromatic pitches (e.g. A♭→A♮) as “literacy”.

## 3. Pipeline overview

```text
Phase A — BEFORE Audiveris (Compath preprocess)
  A1. Deskew / binarize (working copy only; never mutate source)
  A2. Staff-line reconstruction: always 5 lines per staff (template)
  A3. Optional: enhance already-visible clef/key/meter glyphs (no invention)

Phase B — Audiveris
  B1. Run on the repaired working copy only
  B2. Optional pass 2: only if pass 1 pitch/staff quality is poor after
      burning consensus key/meter into the working copy

Phase C — AFTER Audiveris (Compath symbolic brain)
  C1. System graph (barline-connected staves)
  C2. Key + meter state → memory (Redis)
  C3. Written-event grid + per-voice duration vs meter (hard)
  C4. Vertical alignment by written position (not sounding)
  C5. Pitch literacy relative to current key_state
  C6. Tempo for playback (score mark or manual); not required for C3–C5
```

**Hybrid roles:** Audiveris = eyes (glyphs on a cleaned page). Compath = brain (staff template, key/meter consensus, written grid, literacy, Redis).

## 4. Phase A — Staff lines before Audiveris

### 4.1 Rule

- A staff is always a group of **exactly five** lines.
- Groups form one staff; systems (e.g. S.A.T.B.) are several staves joined by barlines.
- If scan quality hides some lines, **infer or draw them on the working copy** before note reading, by fitting a 5-line template to visible fragments.

### 4.2 Method (v1)

1. Detect line fragments (projections / Hough / or seed from a light detector).
2. Estimate `staff_space` and staff skew/curve.
3. Fit a **5-line template** (RANSAC / least squares) anchored to visible segments.
4. Rasterize missing lines onto the **working copy** (debug overlay optional).
5. Do not treat short ledger lines as staff lines.
6. If a group cannot be fitted to 5 lines with confidence → `staff_lines_unreliable` dispute; do not trust pitches on that staff until resolved.

### 4.3 Why before Audiveris

Pitch height is defined relative to the five-line frame. Feeding Audiveris a copy with completed lines reduces wrong staff degrees before any MusicXML exists.

## 5. Phase B — Audiveris on the repaired copy only

- Input: working raster/PDF pages from Phase A (and optional burn-in from §6.3).
- Source upload remains immutable.
- Audiveris remains best-effort; confidence and disputes stay in the API response (WP-22).

## 6. Key and meter (system-wide)

### 6.1 Key change trigger (agreed)

If **at least one** staff in a barline-connected system shows a key signature with **new** accidentals (or shows a key where others are blank):

- Treat that as the key for **all** staves in the system.
- Update `key_state` immediately and persist to Redis.
- Subsequent written-pitch checks use this state.

### 6.2 Conflict

If two or more staves explicitly show **different** new keys in the same measure → do **not** guess:

- Keep previous `key_state`.
- Emit review dispute `key_conflict_across_system`.

### 6.3 Meter

Same pattern as key:

- New time signature on one staff → apply to all barline-connected staves.
- Conflicting explicit meters → review, keep previous `meter_state`.

### 6.4 Optional Audiveris pass 2

Only after consensus exists: burn agreed key/meter glyphs onto the working copy and re-run Audiveris if pass 1 geometry/pitch quality is still unacceptable. v1 may ship without pass 2.

### 6.5 Do not invent key/meter before any reading

Phase A may enhance visible glyphs; it must not paint 2♭ or 4/4 from imagination. Invention only after detection/consensus or explicit manual input.

## 7. Written-event timing grid (not sounding)

### 7.1 Terminology

| Term | Meaning |
|---|---|
| **Written event** | A notated notehead or rest as drawn (including notes that continue a tie) |
| **Sounding onset** | Acoustic/MIDI start (out of scope for structural v1 checks) |

Ties and anacrusis affect **playback**, not whether a written duration counts in the bar.

### 7.2 Two clocks

| Clock | Use |
|---|---|
| **Symbolic** (measure + beat + fraction of whole) | Meter check, vertical notation grid, literacy |
| **Wall-clock** (seconds via tempo) | Metronome, MIDI/MusicXML playback, later audio align |

Structural verification must work with symbolic time alone.

### 7.3 Meter hard rule

For each voice (layer) in a measure:

- Sum of **written** durations must equal the measure capacity implied by current `meter_state` (e.g. 4/4 → one whole).
- Exceptions (explicit disputes if violated without flag): pickup/anacrusis capacity, incomplete final bar, tuplets, multi-measure rests.

Example style (Viskov-like): per-voice duration lists such as S `(⅛+¼+⅛+⅛+⅛+¼)` must sum to the bar; we validate **sums**, not “same number of noteheads in every voice”.

### 7.4 Steps in the chronology array

- A new step appears when a **new written event** occurs on the symbolic timeline in at least one voice.
- Other voices at that step may hold a previous written duration, show a rest, or be empty (not yet entered).
- Vertical “chord” = written events whose symbolic positions coincide — **not** simultaneous sounding attacks.
- Array is bidirectional (forward/back) for inspection and later audio mapping.

### 7.5 Step record (logical)

```text
step {
  measure,
  onset_symbolic,       // e.g. measure 2 + 1/4
  duration_to_next,
  key_state,            // from memory at this point
  meter_state,
  voices: { S, A, T, B, ... } → { written pitch | rest | tie-continue, duration },
  wall_time_sec?        // filled when tempo known
}
```

Staff count is not always 4; track `n_staves` per system (Audiveris may disagree sheet-to-sheet).

## 8. Pitch literacy (relative to key_state)

### Hard (auto)

- Against current `key_state` only: e.g. B♯ forbidden when key has B♭ on that letter.
- Double accidentals → review/hard per policy table.

### Soft / review (do **not** auto-rewrite)

- Chromatic written pitches outside the diatonic set (e.g. A♭ under g minor) → **review** for Voice Leader.
- **Forbidden:** treating “out of scale” as automatic A♭→A♮ (rejected as arbitrary).

### Memory

- Redis stores accepted `key_state` / `meter_state` history and VL-confirmed pitch fixes.
- Memory never overrides hard enharmonic forbids.
- Memory never overrides legal written chromatics without VL confirmation.
- Full feedback / learning loop: **§10**.

## 9. Tempo (playback)

- If a tempo mark exists (usually above the first system/measure) → map via tempo vocabulary → `tempo_bpm`, `tempo_source=score` at read time.
- If absent → leave empty for **manual** input (`tempo_source=manual`).
- Used for MIDI / MusicXML playback and later audio; **not** required to validate meter or written grid.

## 10. Feedback loop & continual learning (MUST)

After each recognition + human/system correction cycle the system **must** get better on the next run — without learning from garbage or leaking tenants.

### 10.1 Principle

```text
recognize → propose → correct/confirm → learn → next run uses memory
```

Learning is **not** silent auto-mutation of hard literacy rules.  
Hard rules stay code-versioned; soft skills grow from confirmed feedback.

### 10.2 Sources of feedback (ranked)

| Rank | Source | Trust | Learns into |
|---|---|---|---|
| 1 | Voice Leader / editor **confirm** or **edit** in review UI | High | tenant + piece memory; optional global aggregates |
| 2 | Partner API callback / webhook with corrected MusicXML patch | High (if signed + authenticated) | tenant memory |
| 3 | Automatic hard fixes (key hole-fill, B♯ forbid) accepted without undo | Medium | reinforce pattern hits |
| 4 | Soft review left unresolved | None | no learn; may decay dispute weight only |
| 5 | User **undo** / reject of an auto-fix | Negative | decrement / blacklist that hint |

Only ranks 1–2 (and 3 if not undone within a short window) write durable skills.

### 10.3 What we learn (skill types)

1. **Staff geometry** — successful `staff_space`, skew, template offsets for similar page fingerprints (dpi/scan family).  
2. **Key / meter transitions** — “at measure M this piece/system flips to fifths=X” after confirm.  
3. **Written pitch preferences** — ctx → preferred alter/step when VL fixed Audiveris (never hard-illegal).  
4. **Duration / grid** — recurring mis-read rhythms for a glyph context (soft hints to validator).  
5. **Publisher / edition priors** (later) — anonymized global: “this engraving style puts key only on top staff”.  

Each skill record: `{ctx, value, hits, confirms, rejects, confidence, last_ts, source, rule_version}`.

### 10.4 Context key (`ctx`)

Stable, privacy-safe features — not raw PDF bytes in the key:

```text
ctx = hash(
  piece_fp | staff_i | measure_or_region |
  key_fifths | meter | clef |
  local_shape_hash |  // glyph neighborhood after staff normalize
  step_name?         // for pitch skills
)
```

`piece_fp` = content hash of page(s). Cross-piece learning uses **shape/edition** features only (no choir PII).

### 10.5 Apply on next run (order)

1. Load `key_state` / `meter_state` history for `piece_fp` if re-run.  
2. Apply staff-template priors if scan family matches.  
3. Run Phase A → B → C as usual.  
4. Soft skills = **hints** when hypothesis is uncertain or in review set; never override hard rules or unanimous written evidence.  
5. If hint applied → tag result `learned_hint=true` for UI transparency.

### 10.6 Safety rails (critique baked in)

| Risk | Mitigation |
|---|---|
| Learn wrong correction | Require explicit confirm; undo → negative feedback |
| One bad edit poisons all | Per-tenant first; global only after N confirms from ≥K tenants |
| Drift vs hard literacy | Hard rules code-only; skills cannot enable B♯ under B♭ key |
| Catastrophic overwrite | Append-only hist + confidence; never delete on single reject |
| Privacy / GDPR | Tenant isolation; global skills strip PII; retention = WP-22 |
| Partner spoofing | HMAC webhooks; only activated licenses write feedback |
| Infinite soft bias | Confidence cap; decay hits without recent confirms |

### 10.7 Feedback API / UI (product)

- Review UI (WP-19): per dispute Accept / Edit / Reject → `POST` internal feedback.  
- Partner: `POST /v1/omr/jobs/{id}/feedback` with patch (MusicXML diff or JSON ops) + idempotency key.  
- Server Admin: aggregates — learn rate, top ctx, reject rate (no raw scores).  
- Metrics: `% disputes resolved`, `hint_hit_rate`, `re-run improvement` (disputesΔ on same `fp`).

### 10.8 Loop diagram

```text
Phase A+B+C (+ hints)  -->  Result + disputes
        ^                        |
        |                        v
        |              VL / Partner feedback
        |                        |
        |                   Learn gate
        |              (confirm / edit / reject)
        +------ Redis skills / state ------+
```

### 10.9 v1 vs later

| v1 (MUST) | Later |
|---|---|
| Redis skills for key/meter/pitch ctx; confirm/reject API | Global anonymized edition priors |
| Re-run same piece uses memory | Light online weight updates for staff template |
| Metrics in Server Admin | Optional offline training job (still no LLM required) |

LLM is **not** required for learning; optional later only for explaining disputes.

## 11. Redis memory (sketch)

| Key | Value |
|---|---|
| `omr:key:v1:{tenant}:{fp}:current` | `{fifths, mode?, measure, staff_ids[], source, ts}` |
| `omr:key:v1:{tenant}:{fp}:hist:{measure}` | key change events |
| `omr:meter:v1:{tenant}:{fp}:current` | `{beats, beat_type, measure, ...}` |
| `omr:meter:v1:{tenant}:{fp}:hist:{measure}` | meter changes |
| `omr:skill:v1:{tenant}:{ctx}` | `{value, hits, confirms, rejects, confidence, last_ts, source}` |
| `omr:skill:v1:global:{ctx}` | anonymized aggregate (optional, gated) |
| `omr:feedback:v1:{tenant}:{job_id}` | audit of feedback events (append) |
| `omr:grammar:v1:rules` | rule-set version |

`fp` = piece/page fingerprint. TTL / retention aligned with WP-22 Partner file policy where applicable.

## 12. Product advantages (shop / Pro+AI / Partner)

Carry in WP-23 / shop copy (see also `omr-tests/PRODUCT-ADVANTAGES-OMR.md`):

1. OMR → MusicXML  
2. Staff-line repair before recognition (5-line template)  
3. Live tonal + meter state with system-wide key/meter propagation  
4. Written-duration checks against meter  
5. Literacy hard rules without arbitrary chromatic rewrites  
6. **Feedback learning** — each confirmed correction improves the next run (Redis skills)  
7. Same SureCart licensing as Pro; Partner without mandatory WP  

## 13. Dependencies: ready libraries vs custom code

**Principle:** eyes and infra = off-the-shelf; brain (staff → key/meter → written grid → learn) = Compath code.  
Rough split: ~30% ready engines, ~70% of product value in our modules around them.

### 13.1 Ready (use, do not rewrite)

| Layer | Library / engine | Role |
|---|---|---|
| PDF → raster | **Poppler** (`pdftoppm`) and/or **PyMuPDF** | Page images for Phase A |
| Image preprocess | **OpenCV** + **NumPy** | Deskew, binarize, horizontal-line morphology, draw template lines |
| Glyph OMR | **Audiveris** (CLI) | Noteheads, clefs, rhythm draft → MusicXML / `.mxl` |
| MusicXML parse | **music21** and/or **lxml** | Measures, pitches, written durations |
| Theory helpers | **music21**; optional **@tonaljs/tonal** (TS clients) | Scales/keys as helpers — **not** hard-rule authority |
| Queue / skills store | **Redis** (`compath_redis`, WP-22) | Jobs, `key_state`, `meter_state`, skills |
| API / billing shell | Existing **`compath_api`**, SureCart licensing | Partner/Pro jobs, quotas |
| Playback check | **MuseScore** CLI (and/or MIDI export) | Tempo playback smoke tests — not notation validation |
| Observability | Prometheus / Admin metrics (WP-22) | `hint_hit_rate`, disputesΔ on re-run |

Optional references only (ideas, not product dependencies): academic staff-detect utilities, OpenCV morph-lines tutorials. Do **not** adopt a second full OMR stack alongside Audiveris in v1.

### 13.2 Custom (write ourselves)

| Module | Pipeline | Why custom |
|---|---|---|
| `staff_template` | A2 | Fit/complete **exactly 5** lines; ledger-safe; working-copy rasterize |
| `working_copy` I/O | A→B | Immutable source; cleared copy fed to Audiveris |
| `system_key_meter` | C2 | ≥1 staff new key/meter → all barline-linked; conflict → review |
| `written_grid` | C3–C4 | Written-event steps; per-voice duration sum vs meter |
| `literacy_rules` | C5 | Hard enharmonic forbids; chromatic → review only |
| `feedback_skills` | §10 | Confirm/edit/reject gate; Redis skill apply on next run |
| `omr_orchestrator` | A→B→C | Worker wiring, disputes payload, optional pass 2 |
| Review UI + `POST /v1/omr/jobs/{id}/feedback` | WP-19 / Partner | Product surface for learning loop |

### 13.3 Explicitly not built in v1

- Full custom notehead/stem/beam CNN OMR  
- Public free “harmony textbook” JSON API  
- Homegrown Redis / SureCart / LLM gateway (use WP-22/23)  
- Silent mutation of hard literacy rules by ML weights  

### 13.4 Suggested v1 runtime stack

```text
Python worker: OpenCV, NumPy, lxml|music21, redis
Java CLI:      Audiveris
Infra:         Redis, compath_omr_worker, compath_api
Custom pkgs:   staff_template, system_key_meter, written_grid,
               literacy_rules, feedback_skills, omr_orchestrator
```

### 13.5 Build order (dependencies-aware)

1. OpenCV staff template + working copy (unblocks better Audiveris input)  
2. Audiveris wire-up on working copy  
3. `system_key_meter` + `written_grid` + `literacy_rules` on MusicXML  
4. Redis state + `feedback_skills` + feedback API  
5. Optional Audiveris pass 2 (burn-in)  

## 14. Implementation slices (for later plan)

1. **A2 staff template** on Viskov pages → working PNGs → visual QA  
2. Wire Audiveris input to working copy  
3. C2 key/meter consensus + Redis schema  
4. C3 written-duration validator + step array export (JSON)  
5. C5 literacy hard/review (no A♭ auto-force)  
6. C6 tempo field + manual override in result JSON  
7. **Feedback API + skill apply/learn gate** (confirm/reject; re-run improvement metric)  
8. Optional B2 burn-in re-pass  
9. Optional global skill aggregates  

## 15. Decisions log

| Topic | Decision |
|---|---|
| Custom OMR vs Audiveris | Hybrid: preprocess + symbolic brain; Audiveris as glyph engine |
| Staff lines | Always 5; template-complete **before** Audiveris on working copy |
| Staff CV stack | **OpenCV + NumPy** for A1/A2; Audiveris not used as staff authority |
| Key change | ≥1 staff shows new key → all barline-connected staves; conflict → review |
| Meter change | Same as key |
| Attack vs written | Structural grid = **written** events; ties count as written duration |
| Tempo | Optional for playback; manual if missing |
| A♭ in g minor | Review only; never auto A♭→A♮ |
| Feedback / learning | MUST: confirm→skill→next run; hard rules not silently mutated; tenant-first |
| Dependencies | Ready: Audiveris, OpenCV, music21/lxml, Redis; custom: template/grid/literacy/learn |
| Public harmony API | Not a product |

## 16. Open points (non-blocking)

- Whether A2 seeds line fragments from OpenCV only or also reads Audiveris `.omr` staff seeds after a failed first pass.  
- Whether v1 ships optional Audiveris pass 2.  
- Pickup detection heuristics beyond MusicXML `implicit` / incomplete backup.  
- Thresholds N/K for promoting tenant skills to `global`.  

## 17. Copyright & rights (product pointer)

OMR, correction, and PDF→MusicXML→audio process **copyrighted musical works** (and often editions). Conversion does **not** grant rights.

- Full product memo (warranty, ToS checklist, PD vs licensed, learning/privacy, audio vs notation):  
  `2026-10-12-omr-copyright-rights-memo.md`
- Aligns with WP-22: partner warrants upload rights; Compath is not a publisher; short retention; no public third-party score catalog.
- Sell **technology** (Pro+AI / Partner API), not a repertoire license.
- **Lawyer review (EE/EU) required** before public sale / ToS finalization. This design is not legal advice.

## 18. Approval

Design captured from product discussion (2026-10-12), including feedback/learning MUST (§10), dependencies (§13), and copyright pointer (§17). Please review this file and the rights memo; after approval, create an implementation plan via writing-plans (no coding until plan is agreed).
