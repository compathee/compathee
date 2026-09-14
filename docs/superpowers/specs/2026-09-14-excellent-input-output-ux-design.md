# Excellent Books client input/output UX

**Date:** 2026-09-14  
**Status:** Approved  
**Scope:** Windows client bundle in `excellent/` only. Do not change Excel unpivot/normalization logic.

## Problem

The client combiner (`excellent/scripts/combine_excellent_reports.py`, launched by `excellent/run_combine_excellent_reports.bat`) asks the user to pick source files and a save path, but it does not create a predictable project-local workspace. After a successful run it only prints a relative-looking path to the console, so the client cannot reliably find the combined report. Cancel/error paths are easy to miss.

## Goals

- Create `input/` and `output/` next to the project (`excellent/`, parent of `scripts/`).
- Keep free multi-select of source `.xlsx` files from **any** folder.
- Default the save dialog to `output/excellent-combined-report.xlsx`.
- After success, show a GUI messagebox with the **full absolute path** to the report, and also print that path.
- On error or cancel, show a clear message and exit with a non-zero status.
- Fall back to console-only messages when tkinter/GUI is unavailable.
- Leave unpivot/normalization (`normalize_workbook`, header detection, object prefixes) unchanged.

## Non-goals

- No change to the root developer copy at `scripts/combine_excellent_reports.py` unless a helper must be shared (it must not).
- No change to Excel parsing, unpivot, object lookup, or output sheet layout (`combined` / `summary` / `totals_by_object`).
- No restriction of the open-file dialog to `input/` — that folder is only a convenient default starting point.

## Layout

```text
excellent/
  input/          # optional drop folder; dialog may start here; user can browse away
  output/         # default save location
  scripts/combine_excellent_reports.py
  run_combine_excellent_reports.bat
  README_RU.txt
```

`project_root()` is the parent of the `scripts/` directory that contains this file (the `excellent/` folder), not the git repository root.

## Helpers

Add to `excellent/scripts/combine_excellent_reports.py`:

```python
def project_root() -> Path:
    """Return the Excellent client folder (parent of scripts/)."""

def ensure_work_dirs(root: Path | None = None) -> tuple[Path, Path]:
    """Create <root>/input and <root>/output if missing.

    Returns (input_dir, output_dir). When root is None, use project_root().
    """
```

Both directories are created with `mkdir(parents=True, exist_ok=True)`.

## Interactive flow (`prompt_interactive` / `--interactive`)

Used by `excellent/run_combine_excellent_reports.bat`.

1. Call `ensure_work_dirs()` first so `input/` and `output/` exist.
2. Open-file dialog (`askopenfilenames`):
   - `initialdir` may be `input/`
   - user can browse **any** folder
   - multi-select of `.xlsx` remains enabled
3. Keep the optional object lookup file prompt (Cancel/empty = skip).
4. Save dialog (`asksaveasfilename`):
   - default directory: `output/`
   - default filename: `excellent-combined-report.xlsx`
   - still allow `.xlsx` or `.csv`
5. Keep the optional object-prefix prompt (default `HK_`).
6. Console fallback (no tkinter / TclError): same steps via stdin; default output path is `output/excellent-combined-report.xlsx` under the project root.

Cancel of required source or output selection is a failure: show a clear message and exit non-zero.

## `main` behavior

- Parse args as today.
- If `--interactive`, run `prompt_interactive`.
- Resolve the output path and `mkdir` its parent before writing (in addition to the existing `write_xlsx` / `write_csv` mkdir).
- **Success:** print and (when GUI is available **and** this is an interactive run) show a Russian-friendly messagebox with:
  - row count of the combined report
  - `Path.resolve()` absolute path to the written file
- **Failure / cancel:** print a clear message; show a GUI error dialog when this is an interactive run and GUI is available; return a non-zero exit code.
- If tkinter is missing or `tk.Tk()` fails, skip the messagebox and keep the console message.
- Do not open a blocking GUI dialog on non-interactive CLI runs (keeps existing unittests and headless use working). Console still prints the absolute path on success.

### Suggested success copy

```text
Отчет сохранен.

Строк: <N>
<absolute-resolved-path>
```

### Suggested cancel/error copy

Russian-friendly title plus the concrete reason, for example:

```text
Исходные файлы не выбраны.
```

or

```text
Файл отчета не выбран.
```

or the exception text for unexpected errors.

## CLI default

Change `--input-dir` default from `excellent-exports` to `input` (relative `Path("input")`).  
`--output` default stays `output/excellent-combined-report.xlsx`.

This default is used when the user does not pass `--file` / `--interactive`. The Windows bat still uses `--interactive` and therefore the dialogs above.

## Bundled empty folders

Add:

- `excellent/input/.gitkeep`
- `excellent/output/.gitkeep`

Adjust `.gitignore` so `excellent/output/.gitkeep` is tracked even though `output/` is ignored globally.

## Client README

Update `excellent/README_RU.txt` to describe:

- `input/` and `output/` next to the bat/script
- source files may be chosen from any folder
- default save location `output/excellent-combined-report.xlsx`
- after success a dialog shows the full path to the report

## Testing

Lightweight unittests (do not require a display):

1. `ensure_work_dirs(tmp_root)` creates `input/` and `output/` and returns those paths; a second call is idempotent.
2. `parse_args([])` uses `--input-dir` default `input`.
3. Existing root tests in `tests/test_combine_excellent_reports.py` continue to pass (they exercise the developer script under `scripts/`, whose normalization must remain the reference). Client-script normalization is not reimplemented.

Run: `python -m unittest discover -s tests -v`

## Out of scope / unchanged

- `normalize_workbook`, `build_sheet_plan`, `is_object_header`, `write_xlsx` sheet structure
- `excellent/run_combine_excellent_reports.bat` launch command (`--interactive`)
- Root `README.md` / root `scripts/combine_excellent_reports.py` defaults (`excellent-exports`)
