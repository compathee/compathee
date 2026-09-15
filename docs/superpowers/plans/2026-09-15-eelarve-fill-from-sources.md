# Eelarve Fill From Sources Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Windows `excellent/` client's primary flow with filling monthly Kasumiaruanne object amounts into an Eelarve workbook (existing or from-scratch `output/Eelarve_YYYY.xlsx`).

**Architecture:** New self-contained module `excellent/scripts/eelarve_fill.py`: parse sources → facts `(konto, object, amount)` → locate/create konto blocks on the Eelarve sheet → write month cells / append object rows → expand neighboring `SUM` formulas. Interactive GUI (tkinter) plus argparse for tests. Legacy unpivot script stays but is no longer what the primary `.bat` launches.

**Tech Stack:** Python 3, openpyxl, tkinter (optional), unittest, Windows `.bat`.

## Global Constraints

- Scope: `excellent/` client + `tests/test_eelarve_fill.py` + this spec/plan + `excellent/README_RU.txt` + bat launchers. Do not change unpivot internals of `scripts/combine_excellent_reports.py`.
- `project_root() -> Path` is parent of `scripts/` (`excellent/`).
- `ensure_work_dirs(root=None) -> tuple[Path, Path]` creates `input/` and `output/`.
- Default new workbook: `output/Eelarve_<year>.xlsx` (never clone a sample template).
- Cancel Eelarve picker → create new. Cancel sources → non-zero exit.
- Match objects only inside the konto block; search code then CSV description; append on miss.
- Do not overwrite KOKKU rows; do not touch kontos absent from sources.
- Existing files: expand neighboring `SUM` ranges on insert. From-scratch: write `SUM` formulas.
- Optional object CSV; missing dictionary still runs.
- Parse amounts like `2 848,00`. Ignore `Periood` and `Kokku…` source rows.
- Success message: absolute path + written / rows added / missing konto blocks.
- Tests: `python -m unittest discover -s tests -v`

---

## File map

- Create: `docs/superpowers/specs/2026-09-15-eelarve-fill-from-sources-design.md` (already written)
- Create: `docs/superpowers/plans/2026-09-15-eelarve-fill-from-sources.md` (this file)
- Create: `excellent/scripts/eelarve_fill.py`
- Create: `tests/test_eelarve_fill.py`
- Create: `excellent/run_eelarve_fill.bat`
- Create: `excellent/run_combine_excellent_reports_legacy.bat`
- Modify: `excellent/run_combine_excellent_reports.bat` (repoint to `eelarve_fill.py --interactive`)
- Modify: `excellent/README_RU.txt`
- Modify: `README.md` (short pointer that the Windows client fills Eelarve; unpivot remains a developer/legacy tool)

---

### Task 1: Helpers + source parse + from-scratch fill (core engine)

**Files:**
- Create: `tests/test_eelarve_fill.py`
- Create: `excellent/scripts/eelarve_fill.py`

**Interfaces:**
- Consumes: openpyxl, optional CSV lookup
- Produces: `parse_amount`, `parse_konto`, `is_total_label`, `default_new_eelarve_path`, `load_object_lookup`, `parse_kasumiaruanne`, `fill_eelarve`, `FillStats`, `MONTHS`, `main`

- [ ] **Step 1: Write the failing tests**

Create `tests/test_eelarve_fill.py` with importlib loader pointing at `excellent/scripts/eelarve_fill.py` and tests for:

- `parse_amount("2 848,00") == 2848.0` and NBSP variant; empty → None
- `parse_konto("3241 Tulu") == "3241"`
- `is_total_label("Kokku tulu")` is True
- `default_new_eelarve_path(root, year=2026) == root / "output" / "Eelarve_2026.xlsx"`
- Parse a synthetic `Kasumiaruanne` sheet: skip `Periood`, skip `Kokku` row, emit object facts
- Existing Eelarve with two blocks (`3241` income, `5000` expense) sharing object code `HK_A`: January amount for 3241 writes only into 3241's block
- CSV description match: Eelarve row labeled `Nomme LOV` matches `HK_NOMME`
- Append-on-miss: missing object is inserted inside the 3241 block; a `Üldkulud` section with no matching konto stays unchanged
- Plain konto row (no children) gets an object row appended underneath
- KOKKU row numeric values unchanged; new object inserted above KOKKU
- From-scratch `main` without `--eelarve` writes `Eelarve_<year>.xlsx` and `SUM` formulas
- Vertical `SUM` on the konto title expands after an object row is inserted
- Missing konto in an existing file: new block appended, `missing_konto_blocks == 1`
- Interactive: tkinter missing, Eelarve path empty, sources provided via stdin → creates new file (not an error); sources empty → non-zero

Use tempfile workbooks built with openpyxl. Call `fill_eelarve(...)` / `main([...])` — do not mock openpyxl.

- [ ] **Step 2: Run tests to verify they fail**

```bash
python -m unittest tests.test_eelarve_fill -v
```

Expected: ERROR/FAIL because `eelarve_fill.py` does not exist or symbols are missing.

- [ ] **Step 3: Implement `excellent/scripts/eelarve_fill.py`**

Module responsibilities (keep in this one file unless it becomes unwieldy):

1. Constants: `MONTHS` Estonian names; `OBJECT_HEADER_RE`; source sheet name `Kasumiaruanne`.
2. `parse_amount(value) -> float | None` — strip spaces/NBSP, comma→dot.
3. `parse_konto(value) -> str | None` — 3–6 digit number, skip years 19xx/20xx.
4. `is_total_label(text) -> bool` — starts with `kokku` after strip/casefold.
5. `is_object_header(header) -> bool`.
6. `load_object_lookup(path) -> dict[str, str]` (csv/xlsx, same column aliases as the combiner).
7. `parse_kasumiaruanne(path) -> list[SourceFact]`.
8. Eelarve layout: find header row with month names; map month → column; find konto blocks.
9. `fill_worksheet(ws, facts, month, lookup) -> counters` — match/append/write; expand SUM.
10. `create_blank_eelarve() -> Workbook` with header `Konto | Object | Jaanuar | … | Detsember | Kokku`.
11. `fill_eelarve(eelarve_path | None, sources: list[tuple[Path, str]], objects, output, *, year) -> FillStats`.
12. `adjust_sum_formulas_after_insert(ws, insert_at, count=1)`:
    - Parse `SUM(A1:A2)` / `SUM(C5:N5)` with a simple regex.
    - If a vertical range ended at `insert_at - 1`, extend the end by `count`.
    - If a range already includes rows `>= insert_at`, shift those row numbers by `count`.
    - If insertion sits at the start of a range, keep covering the new row (start stays, end shifts).
13. CLI `parse_args` / `prompt_interactive` / `notify_user` / `main` (mirrors client UX: GUI with console fallback; success uses Russian stats copy from the spec).

Konto block scan: after the month header, a row is a title if `parse_konto` succeeds on col A or B and the row is not a total. Child rows follow until the next title. `KOKKU` children are totals. Append position: index of trailing total row if present, else last child + 1, else title + 1.

From-scratch: when adding the first object under a new konto, write title `SUM` over object rows and per-object `Kokku` `=SUM` of months. When adding further objects, insert and expand those `SUM`s.

- [ ] **Step 4: Run tests to verify they pass**

```bash
python -m unittest tests.test_eelarve_fill -v
```

Expected: all new tests PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/test_eelarve_fill.py excellent/scripts/eelarve_fill.py
git commit -m "Add Eelarve fill engine from monthly Kasumiaruanne sources"
```

---

### Task 2: Windows launchers + Russian README

**Files:**
- Modify: `excellent/run_combine_excellent_reports.bat`
- Create: `excellent/run_eelarve_fill.bat`
- Create: `excellent/run_combine_excellent_reports_legacy.bat`
- Modify: `excellent/README_RU.txt`
- Modify: `README.md`

**Interfaces:**
- Consumes: `eelarve_fill.py --interactive`
- Produces: primary bat launches fill; legacy bat launches unpivot; README describes the real algorithm

- [ ] **Step 1: Repoint bats**

`excellent/run_eelarve_fill.bat` and `excellent/run_combine_excellent_reports.bat`:

```bat
@echo off
setlocal
cd /d "%~dp0"
set "PYTHON_CMD=python"
where py >nul 2>nul
if %ERRORLEVEL% EQU 0 set "PYTHON_CMD=py -3"
echo Installing Python dependencies...
%PYTHON_CMD% -m pip install -r requirements.txt
if %ERRORLEVEL% NEQ 0 (
  echo.
  echo Failed to install dependencies. Make sure Python 3 is installed.
  pause
  exit /b 1
)
echo.
echo Starting Excellent Books Eelarve fill...
%PYTHON_CMD% scripts\eelarve_fill.py --interactive
echo.
pause
```

Legacy bat runs `scripts\combine_excellent_reports.py --interactive`.

- [ ] **Step 2: Rewrite `excellent/README_RU.txt` in Russian**

Cover: purpose (fill Eelarve, not flatten); install copy to `C:\temp\excellent`; run `run_eelarve_fill.bat`; cancel Eelarve = create `output\Eelarve_ГОД.xlsx`; select Kasumiaruanne; month per file; optional CSV `object_code,object_name`; matching by konto block then code then description; append missing objects; do not touch foreign sections / KOKKU; success dialog with path and stats; legacy unpivot bat name.

- [ ] **Step 3: Root `README.md`**

Keep developer unpivot examples for `scripts/combine_excellent_reports.py`. Add a short section: Windows client in `excellent/` now fills Eelarve from monthly Kasumiaruanne exports (see `excellent/README_RU.txt`).

- [ ] **Step 4: Commit**

```bash
git add excellent/run_combine_excellent_reports.bat excellent/run_eelarve_fill.bat excellent/run_combine_excellent_reports_legacy.bat excellent/README_RU.txt README.md
git commit -m "Point Excellent Windows client at Eelarve fill"
```

---

### Task 3: Full verification

- [ ] **Step 1: Run the full suite**

```bash
python -m unittest discover -s tests -v
```

Expected: all tests pass, including legacy combiner and client UX tests.

- [ ] **Step 2: CLI smoke on synthetic files** (same fixtures as tests, or a tiny script in `/tmp`)

Confirm from-scratch output path, January cell value, appended object row, untouched unrelated row, `SUM` formula present.

- [ ] **Step 3: PR body lists Windows manual smoke** from the spec.
