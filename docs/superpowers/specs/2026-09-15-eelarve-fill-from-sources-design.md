# Excellent Books → Eelarve fill from monthly sources

**Date:** 2026-09-15  
**Status:** Approved  
**Scope:** Windows client bundle in `excellent/`. This **replaces the primary purpose** of the old flat unpivot combiner for that client. The unpivot script may remain as a legacy tool; it must not be what the Windows `.bat` launches.

## Problem

The accountant's real job is not to unpivot Kasumiaruanne object columns into a flat table. It is to take monthly Excellent Books profit-and-loss exports and **write those object amounts into an Eelarve workbook**, month column by month column, inside the correct konto block.

The old combiner (`excellent/scripts/combine_excellent_reports.py`) flattens object columns. That is the wrong output for this workflow. A complex sample Eelarve template must never be cloned or mutated unless the user explicitly selected that file.

## Goals

1. Interactive Windows flow: pick an existing Eelarve **or** create a new simple workbook; pick one or more monthly source `.xlsx` files; choose a month for each; optionally pick a CSV object dictionary; fill; save; show the absolute path and stats.
2. Match source facts `(konto, object_code, amount)` into the **konto block** in Eelarve (income vs expense can reuse the same object name).
3. Search inside that block by object **code** first, then by CSV **description**. On miss, append an object row inside the block.
4. Do not touch kontos/sections that are absent from the sources. Do not overwrite KOKKU / aggregate total rows.
5. Keep Excel `SUM` formulas correct when inserting rows. From-scratch workbooks must include `SUM` formulas so totals update when opened in Excel.
6. Unit tests with synthetic `.xlsx` fixtures (no private client samples).

## Non-goals

- Do not clone or ship a complex sample Eelarve template.
- Do not change unpivot/normalization internals of the legacy combiner except as needed to keep it available as a non-primary script.
- Do not call the Excellent Books API.
- Do not require the object CSV; a missing dictionary is a valid run.
- Do not overwrite KOKKU / `Kokku…` aggregate values with source amounts (those rows are totals, not objects).

## Primary entrypoint

| Role | Path |
|------|------|
| New fill module | `excellent/scripts/eelarve_fill.py` |
| Primary Windows launcher | `excellent/run_combine_excellent_reports.bat` **repointed** to `eelarve_fill.py --interactive` |
| Clearer alias | `excellent/run_eelarve_fill.bat` (same command) |
| Legacy unpivot | `excellent/scripts/combine_excellent_reports.py` remains; optional `excellent/run_combine_excellent_reports_legacy.bat` |
| Client docs | `excellent/README_RU.txt` rewritten for this algorithm (Russian) |
| Repo tests | `tests/test_eelarve_fill.py` (plus existing tests must still pass) |

`project_root()` is the `excellent/` folder (parent of `scripts/`), same as the UX spec. Reuse `ensure_work_dirs()` creating `excellent/input/` and `excellent/output/`.

The repo-root developer copy `scripts/combine_excellent_reports.py` stays an unpivot tool for developers; it is not the Windows client flow.

## Interactive flow

Used by the Windows `.bat` files via `--interactive`.

1. `ensure_work_dirs()` so `input/` and `output/` exist.
2. **Eelarve workbook** (`askopenfilename`, `initialdir=input/`, user may browse anywhere):
   - Title: existing Eelarve workbook.
   - If the user **cancels** (or console empty): **create a new** workbook at `output/Eelarve_<current_year>.xlsx` (year = local calendar year unless `--year` is passed in CLI tests). Structure grows from sources. **Do not** copy/clone any sample template. **Never** modify a pristine template the user did not select.
   - If the user **selects** a file: that file is the target; save back to that same path (unless CLI `--output` overrides).
3. **Monthly sources** (`askopenfilenames`, `.xlsx`, any folder):
   - One or more Kasumiaruanne workbooks.
   - Cancel / empty → failure, non-zero exit, clear message (Russian-friendly + English).
4. **Month per source**: for each selected file, ask which month (`Jaanuar` … `Detsember`, or `1`–`12`). Invalid input is an error (GUI may re-prompt once; CLI fails).
5. **Optional object dictionary** (`askopenfilename`, `.csv` or `.xlsx` with `object_code` / `object_name`; Cancel skips). If provided, use it; if missing, still run.
6. Fill, save, then GUI + console success with:
   - absolute `Path.resolve()` path
   - stats: **written** (month cells updated), **rows added** (object rows inserted), **missing konto blocks** (source kontos that had no pre-existing block in the selected Eelarve; from-scratch creates all blocks and reports `0` missing)

Console fallback when tkinter is missing or `Tk()` fails: same steps via stdin. Default new path is still `<project>/output/Eelarve_<year>.xlsx`.

Do not open blocking GUI dialogs on non-interactive CLI runs.

### Success copy (interactive GUI when available; always console)

```text
Eelarve заполнен.

Записано: <written>
Добавлено строк: <rows_added>
Нет блока konto: <missing_konto_blocks>
<absolute-resolved-path>
```

### Cancel / error copy

Examples:

```text
Исходные файлы не выбраны.
```

```text
Ошибка / Error:
<reason>
```

## CLI (tests and power users)

```text
python excellent/scripts/eelarve_fill.py
  --interactive
  --eelarve PATH              # omit + non-interactive => create new
  --source PATH               # repeatable
  --month Jaanuar             # repeatable; paired by order with --source
  --objects PATH              # optional csv/xlsx
  --output PATH               # default: selected eelarve, or output/Eelarve_YYYY.xlsx
  --year 2026                 # only affects default new filename
```

Rules:

- Non-interactive create-new: omit `--eelarve`.
- `--source` and `--month` counts must match (one month per source file).
- `--output` if omitted: write to the selected `--eelarve` path, or to `default_new_eelarve_path()`.
- Missing required sources → exit ≠ 0.

## Source shape (`Kasumiaruanne`)

- Use the sheet named `Kasumiaruanne` (case-insensitive). If absent → error for that file.
- Header row: auto-detect (scan first ~30 rows) as the row with the most object-code columns.
- Column A: konto number (may include trailing description; parse the account number).
- Column B: description (konto / line label from the export).
- Columns C+: object codes such as `HK_*`, `KÜ_*`, `KV_*`, … (also `CODE (Name)` headers). Each source file holds amounts for **one** month.
- Ignore a `Periood` / `Period` column; it is not an object.
- Ignore total/section rows: line labels matching `Kokku…` / `KOKKU…` (case-insensitive prefix or whole-cell match after strip). Do not import their object amounts.
- Ignore empty rows and rows with no parseable konto number.
- Parse amounts like `2 848,00` (spaces / NBSP, decimal comma) into `float`. Native Excel numbers stay numeric. Empty / unparsable / zero → skip that object (no amount).

Object-code header detection: same idea as the legacy combiner — prefix `XX_` / regex `[A-Z0-9]+[_-][A-Z0-9ÕÄÖÜŠŽõäöüšž_-]+`. Do not treat Konto, Nimetus, Description, Name, Periood, Period, Kokku, Total as object columns.

Emit facts: `(konto, konto_description, object_code, amount)` for every non-empty object amount on a data row.

## Matching rules

For each fact `(konto, object_code, amount)` and the source's chosen month:

1. **Find the konto block** in the Eelarve sheet. Match/add objects **only inside that block**. The same object code may exist under income and expense kontos; never write across blocks.
2. If several blocks share the same konto number, use the **first** block in sheet order.
3. Inside the block, find a child **object row** (never the konto title row, never a KOKKU row):
   1. By object **name/code**: cell text equals or contains the object code as a whole token (case-insensitive, whitespace-collapsed).
   2. Else by CSV **description** (`object_name` for that code), same comparison.
4. **Found** → write a numeric amount into that month column (do not write a formatted string). Overwrite the previous value in that month cell if any (re-run / second file for the same month).
5. **Not found** → append an object row **inside that konto block** (before a trailing KOKKU row if the block has one; otherwise after the last child / directly under a plain konto row). Label: CSV description if available, else the object code. Write the amount into the month column. Prefer copying nearby row style when cheap; not required.
6. Every konto that appears in the sources with at least one object amount must end with the **full list** of those objects as rows in its block.

Konto identity: first 3–6 digit account number in columns A/B that is **not** a year `19xx`/`20xx`. Examples: cell `3241`, `3241 Müügitulu`, `3241.0` → konto `3241`.

### Existing Eelarve layout

- Detect the header row that contains Estonian month names (`Jaanuar` … `Detsember`). Extra columns (year total, notes) are left in place.
- Prefer the worksheet that contains the most month headers. Typical names: Eelarve, PL, Kasumiaruanne — do not require a specific sheet name.
- A **konto block** starts at a title row whose first columns contain that konto number (as in a sample PL sheet: title containing `3241` with child object-name rows).
- Child rows until the next konto title (or end of used range). A `KOKKU`/`Kokku` row inside the span is part of the block as a total, not an object.
- **Plain konto rows without children:** still ensure objects with amounts appear as object rows **under** that konto (append under the konto, turning it into a block). Do not treat the title row itself as the object row and do not overwrite title-row month values with object amounts.

### Do not touch

- Kontos / narrative sections whose konto numbers are **not** in the current source facts (e.g. general expenses without objects). Leave their cells unchanged.
- KOKKU / aggregate total rows: never write source amounts into them.

### Missing konto blocks

If a source konto has no matching block in an **existing** workbook:

- Append a **new simple block** at the end of the used range (konto title + object rows) so the objects can still be stored.
- Increment `missing_konto_blocks` once per such konto (it was not in the original file).

From-scratch workbooks create every needed block from sources; `missing_konto_blocks` is `0`.

## Totals and SUM formulas

Rely on Excel formulas. Do not compute yearly totals in Python and paste values over formula cells.

When inserting object rows:

- After `insert_rows`, adjust `SUM` formulas on the sheet so they still cover the new object rows.
- Neighboring vertical `SUM`s that ended on the last object row (immediately above the insert), or that already spanned the insert, must expand.
- Horizontal month totals (`SUM` across Jaanuar–Detsember on the same row) stay on that row; if the formula cell shifts down, row references in that formula shift with the cell's original row.

From-scratch workbook **must** include:

- Object row year total: `=SUM(<first_month>:<last_month>)` on that row.
- Konto title month cells: `=SUM` of the object rows in the block for that month.
- Konto title year total: `=SUM` of that title row's month cells.

Existing complex files: only **expand** formulas that already exist; do not invent a full formula layout onto a sheet the user selected.

## From-scratch layout

Simple sheet title `Eelarve`. Do not copy a sample template.

| Konto | Object | Jaanuar | … | Detsember | Kokku |
|-------|--------|---------|---|-----------|-------|
| 3241  |        | `=SUM(objects)` | … | `=SUM(objects)` | `=SUM(months)` |
|       | label  | amount  | … |           | `=SUM(months)` |

- Header row 1; data starts row 2.
- Konto number only on the title row; object label in the Object column (description preferred).
- Blocks grow as sources are applied. Later months fill additional columns on existing object rows.

Default path: `<excellent>/output/Eelarve_<year>.xlsx` e.g. `Eelarve_2026.xlsx`.

## Object dictionary

Optional. Columns (case-insensitive): `object_code` / `code` / `object` / `objekt` and `object_name` / `name` / `description` / `nimetus`.

Used only for (a) matching Eelarve rows by description and (b) the label of newly appended rows. Absence → match by code only; append the code as the label.

## Stats

```python
@dataclass(frozen=True)
class FillStats:
    written: int
    rows_added: int
    missing_konto_blocks: int
    output_path: Path
```

`written` counts month cells assigned a numeric amount (including overwrite). `rows_added` counts inserted object rows. `missing_konto_blocks` as above.

## Testing

File: `tests/test_eelarve_fill.py`. Load `excellent/scripts/eelarve_fill.py` via importlib (same pattern as `tests/test_excellent_client_ux.py`). Synthetic workbooks only.

Required cases:

1. Helpers: `parse_amount("2 848,00") == 2848.0`; NBSP; native int/float; empty → `None`.
2. Konto parse from `"3241"`, `"3241 Tulu"`, `3241.0`.
3. Skip source `Kokku…` rows; ignore `Periood` column.
4. Block match: same object code in income konto `3241` and expense konto `5000` — amount goes only to the matching konto's block.
5. CSV description match when the Eelarve row shows the description, not the code.
6. Append-on-miss inside the block; unrelated sections unchanged.
7. Plain konto row (no children) gets object rows appended underneath.
8. KOKKU row values are not overwritten; new objects insert above it.
9. Create-new naming `Eelarve_<year>.xlsx` under `output/`.
10. From-scratch sheet has `SUM` formulas on title and Kokku columns.
11. Inserting an object row expands a neighboring vertical `SUM`.
12. Interactive cancel of Eelarve selection (GUI mocked / tkinter missing) creates a new file rather than erroring; cancel of sources still fails.

Run: `python -m unittest discover -s tests -v`

Existing `tests/test_combine_excellent_reports.py` and `tests/test_excellent_client_ux.py` must remain green (legacy unpivot still works).

## Windows manual smoke (PR body)

1. Copy `excellent/` to `C:\temp\excellent`. Double-click `run_eelarve_fill.bat` (or the repointed `run_combine_excellent_reports.bat`).
2. Cancel the Eelarve picker → confirm a new `output\Eelarve_<year>.xlsx` will be used; **or** select an existing Eelarve.
3. Attach a January-like `Kasumiaruanne` source; choose Jaanuar.
4. Skip CSV or attach one.
5. Confirm: January column fills; new object rows appear under the right konto; unrelated sections unchanged; success dialog shows the absolute path and stats.
6. Re-run, Cancel source selection → error message, non-zero exit (bat still `pause`s).

## Out of scope / unchanged

- Legacy unpivot algorithm in `scripts/combine_excellent_reports.py` (repo root).
- n8n / choir-rehearsal / other repo apps.
- Shipping real client Eelarve or Kasumiaruanne files in git.
