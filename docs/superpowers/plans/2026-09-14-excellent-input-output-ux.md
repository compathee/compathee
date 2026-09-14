# Excellent Books Input/Output UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the Excellent Books Windows client (`excellent/`) project-local `input/` and `output/` folders, a default save path, and a success/error GUI that shows the full absolute report path — without changing Excel unpivot/normalization.

**Architecture:** Add two Path helpers on the client script (`project_root`, `ensure_work_dirs`). Interactive mode creates those dirs, seeds file dialogs with them, and still lets the user browse anywhere. `main` mkdirs the output parent, prints `Path.resolve()`, and shows a tkinter messagebox on interactive runs (console fallback otherwise). Lightweight unittests load the client script by file path so they do not depend on a display.

**Tech Stack:** Python 3, tkinter (optional GUI), openpyxl (unchanged), unittest.

## Global Constraints

- Scope is the Windows client bundle under `excellent/` plus docs/tests listed below. Do not modify `scripts/combine_excellent_reports.py` (repo-root developer copy) or Excel unpivot/normalization functions (`normalize_workbook`, `build_sheet_plan`, `is_object_header`, `find_header_row`, `write_xlsx` sheet layout).
- `project_root() -> Path` is the parent of `scripts/` (the `excellent/` folder), not the git repo root.
- `ensure_work_dirs(root=None) -> tuple[Path, Path]` creates `<root>/input` and `<root>/output` with `mkdir(parents=True, exist_ok=True)` and returns `(input_dir, output_dir)`.
- Open-file dialog may use `initialdir=input/` but the user can select `.xlsx` files from **any** folder; do not restrict the dialog to `input/`.
- Save dialog defaults to `output/excellent-combined-report.xlsx` (directory `output/`, filename `excellent-combined-report.xlsx`).
- Success message (interactive GUI when available, always console) includes the combined row count and `Path.resolve()` absolute path. Suggested copy: `Отчет сохранен.` then `Строк: <N>` then the absolute path.
- Failure/cancel: clear message + exit code != 0. Console fallback if tkinter/GUI is unavailable.
- Do not open a blocking GUI dialog on non-interactive CLI runs.
- Change default `--input-dir` from `excellent-exports` to `input`.
- Add `excellent/input/.gitkeep` and `excellent/output/.gitkeep`; keep `excellent/output/.gitkeep` tracked despite global `output/` gitignore.
- Update `excellent/README_RU.txt`. Do not change `excellent/run_combine_excellent_reports.bat` (`--interactive` stays).

---

## File map

- Create: `docs/superpowers/specs/2026-09-14-excellent-input-output-ux-design.md` (already written)
- Create: `docs/superpowers/plans/2026-09-14-excellent-input-output-ux.md` (this file)
- Create: `tests/test_excellent_client_ux.py`
- Modify: `excellent/scripts/combine_excellent_reports.py`
- Create: `excellent/input/.gitkeep`
- Create: `excellent/output/.gitkeep`
- Modify: `.gitignore`
- Modify: `excellent/README_RU.txt`

---

### Task 1: Work-dir helpers and argparse default

**Files:**
- Create: `tests/test_excellent_client_ux.py`
- Modify: `excellent/scripts/combine_excellent_reports.py` (add helpers near the top after imports; change `--input-dir` default)

**Interfaces:**
- Consumes: existing `parse_args`
- Produces: `project_root() -> Path`, `ensure_work_dirs(root: Path | None = None) -> tuple[Path, Path]`

- [ ] **Step 1: Write the failing tests**

```python
import importlib.util
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CLIENT_SCRIPT = ROOT / "excellent" / "scripts" / "combine_excellent_reports.py"


def load_client():
    spec = importlib.util.spec_from_file_location("excellent_client_combine", CLIENT_SCRIPT)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


class ExcellentClientUxTests(unittest.TestCase):
    def test_ensure_work_dirs_creates_input_and_output(self):
        client = load_client()
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            input_dir, output_dir = client.ensure_work_dirs(root)
            self.assertEqual(input_dir, root / "input")
            self.assertEqual(output_dir, root / "output")
            self.assertTrue(input_dir.is_dir())
            self.assertTrue(output_dir.is_dir())
            again_input, again_output = client.ensure_work_dirs(root)
            self.assertEqual(again_input, input_dir)
            self.assertEqual(again_output, output_dir)

    def test_parse_args_defaults_input_dir_to_input(self):
        client = load_client()
        args = client.parse_args([])
        self.assertEqual(args.input_dir, Path("input"))
        self.assertEqual(args.output, Path("output/excellent-combined-report.xlsx"))
```

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m unittest tests.test_excellent_client_ux -v`

Expected: FAIL/ERROR because `ensure_work_dirs` is missing and `--input-dir` still defaults to `excellent-exports`.

- [ ] **Step 3: Write minimal implementation**

Add after the constants / before dataclasses (or immediately after imports):

```python
def project_root() -> Path:
    return Path(__file__).resolve().parent.parent


def ensure_work_dirs(root: Path | None = None) -> tuple[Path, Path]:
    base = project_root() if root is None else root
    input_dir = base / "input"
    output_dir = base / "output"
    input_dir.mkdir(parents=True, exist_ok=True)
    output_dir.mkdir(parents=True, exist_ok=True)
    return input_dir, output_dir
```

In `parse_args`, change:

```python
        default=Path("excellent-exports"),
```

to:

```python
        default=Path("input"),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m unittest tests.test_excellent_client_ux -v`

Expected: PASS both tests.

- [ ] **Step 5: Commit**

```bash
git add tests/test_excellent_client_ux.py excellent/scripts/combine_excellent_reports.py
git commit -m "Add Excellent client work dirs and input default"
```

---

### Task 2: Interactive dialogs and main success/error UX

**Files:**
- Modify: `excellent/scripts/combine_excellent_reports.py` (`prompt_interactive`, `main`, add `notify_user`)
- Modify: `tests/test_excellent_client_ux.py` (optional extra test that `main` on empty `input/` returns non-zero without hanging)

**Interfaces:**
- Consumes: `ensure_work_dirs`, `project_root`
- Produces: `notify_user(title: str, message: str, *, error: bool = False, use_gui: bool = False) -> None`; `main` returns 0 on success and != 0 on cancel/error; interactive save default `output/excellent-combined-report.xlsx`

- [ ] **Step 1: Write a failing test for empty-input CLI failure still non-zero**

Append to `tests/test_excellent_client_ux.py`:

```python
    def test_main_missing_input_dir_returns_nonzero(self):
        client = load_client()
        with tempfile.TemporaryDirectory() as tmp:
            missing = Path(tmp) / "no-xlsx-here"
            missing.mkdir()
            output = Path(tmp) / "out.xlsx"
            code = client.main(["--input-dir", str(missing), "--output", str(output)])
            self.assertNotEqual(code, 0)
```

This currently returns 2 (already non-zero). Keep it as a regression guard that the new notify path does not change the exit code to 0 or hang on GUI.

- [ ] **Step 2: Run the new test (should already pass on current main; if notify hangs, fail and fix)**

Run: `python -m unittest tests.test_excellent_client_ux.ExcellentClientUxTests.test_main_missing_input_dir_returns_nonzero -v`

- [ ] **Step 3: Implement notify + interactive + main**

Add:

```python
def notify_user(
    title: str,
    message: str,
    *,
    error: bool = False,
    use_gui: bool = False,
) -> None:
    stream = sys.stderr if error else sys.stdout
    print(message, file=stream)
    if not use_gui:
        return
    try:
        import tkinter as tk
        from tkinter import messagebox

        root = tk.Tk()
        root.withdraw()
        try:
            if error:
                messagebox.showerror(title, message)
            else:
                messagebox.showinfo(title, message)
        finally:
            root.destroy()
    except Exception:
        return
```

In `prompt_interactive`, **first line of the function body** after the try/import (and also before the console fallback):

```python
    input_dir, output_dir = ensure_work_dirs()
    default_output = output_dir / "excellent-combined-report.xlsx"
```

GUI open dialog: pass `initialdir=str(input_dir)` (do not otherwise restrict). On empty selection, `notify_user` / `messagebox.showerror` with `Исходные файлы не выбраны.` then `raise SystemExit("No source files selected.")`.

GUI save dialog:

```python
        output_file = filedialog.asksaveasfilename(
            title="Save combined report as",
            defaultextension=".xlsx",
            initialdir=str(output_dir),
            initialfile="excellent-combined-report.xlsx",
            filetypes=[("Excel workbook", "*.xlsx"), ("CSV file", "*.csv")],
        )
```

On empty save selection: `Файл отчета не выбран.` then `SystemExit("No output file selected.")`.

Console fallback default output: `default_output` (the Path under project `output/`), not a CWD-relative string.

Rewrite `main` along these lines (keep the normalize loop identical):

```python
def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv or sys.argv[1:])
    interactive = args.interactive
    try:
        if interactive:
            args = prompt_interactive(args)
        object_lookup = load_object_lookup(args.objects)
        object_regex = re.compile(args.object_pattern)
        prefixes = tuple(prefix.upper() for prefix in args.object_prefix if prefix)
        sheet_names = set(args.sheet) if args.sheet else None
        input_files = iter_xlsx_files(args.input_dir, args.files)
        if not input_files:
            notify_user(
                "Excellent Books",
                "Не найдены файлы .xlsx.\nNo .xlsx files found.",
                error=True,
                use_gui=interactive,
            )
            return 2

        all_rows: list[dict[str, Any]] = []
        all_summary: list[dict[str, Any]] = []
        for workbook_path in input_files:
            try:
                rows, summary = normalize_workbook(
                    workbook_path=workbook_path,
                    object_lookup=object_lookup,
                    object_regex=object_regex,
                    prefixes=prefixes,
                    sheet_names=sheet_names,
                    header_row=args.header_row,
                    max_scan_rows=args.max_scan_rows,
                    include_empty_amounts=args.include_empty_amounts,
                )
            except Exception as exc:  # noqa: BLE001
                all_summary.append(
                    {
                        "source_file": workbook_path.name,
                        "sheet": "",
                        "status": "error",
                        "message": str(exc),
                        "rows": 0,
                    }
                )
                continue
            all_rows.extend(rows)
            all_summary.extend(summary)

        output_path = args.output.expanduser()
        output_path.parent.mkdir(parents=True, exist_ok=True)
        if output_path.suffix.lower() == ".csv":
            write_csv(output_path, all_rows)
        elif output_path.suffix.lower() == ".xlsx":
            write_xlsx(output_path, all_rows, all_summary)
        else:
            raise ValueError("Output path must end with .xlsx or .csv")

        resolved = output_path.resolve()
        message = f"Отчет сохранен.\n\nСтрок: {len(all_rows)}\n{resolved}"
        notify_user("Excellent Books", message, use_gui=interactive)
        return 0
    except SystemExit as exc:
        code = exc.code
        if code is None or code == 0:
            raise
        text = code if isinstance(code, str) else str(code)
        if isinstance(code, str):
            notify_user("Excellent Books", text, error=True, use_gui=interactive)
            return 1
        return int(code)
    except Exception as exc:  # noqa: BLE001
        notify_user(
            "Excellent Books",
            f"Ошибка / Error:\n{exc}",
            error=True,
            use_gui=interactive,
        )
        return 1
```

Do **not** copy-paste or rewrite `normalize_workbook`. Catching `SystemExit` with a string payload covers cancel from `prompt_interactive`. Integer `SystemExit` from argparse `--help` happens inside `parse_args` before this try, so it is unaffected.

- [ ] **Step 4: Run tests**

Run:

```bash
python -m unittest tests.test_excellent_client_ux tests.test_combine_excellent_reports -v
```

Expected: all PASS. Existing unpivot tests still pass because they use `scripts/combine_excellent_reports.py`, and the client `main` non-interactive path does not open a GUI.

- [ ] **Step 5: Commit**

```bash
git add excellent/scripts/combine_excellent_reports.py tests/test_excellent_client_ux.py
git commit -m "Show Excellent report absolute path on success and errors"
```

---

### Task 3: Bundled folders and Russian README

**Files:**
- Create: `excellent/input/.gitkeep`
- Create: `excellent/output/.gitkeep`
- Modify: `.gitignore`
- Modify: `excellent/README_RU.txt`

**Interfaces:**
- Consumes: layout from spec
- Produces: tracked empty `input/` and `output/` in the client bundle

- [ ] **Step 1: Allow tracking `excellent/output/.gitkeep`**

Update `.gitignore` to:

```
__pycache__/
*.py[cod]
.pytest_cache/
.venv/
venv/
output/
!excellent/output/
!excellent/output/.gitkeep
```

Create empty `excellent/input/.gitkeep` and `excellent/output/.gitkeep`.

- [ ] **Step 2: Update `excellent/README_RU.txt`**

Document the copied folder structure including `input/` and `output/`. In "Как запускать" state:

- исходные `.xlsx` можно выбрать из любой папки (диалог может открыться в `input/`)
- по умолчанию отчет сохраняется в `output/excellent-combined-report.xlsx`
- после успеха появится окно с полным путем к файлу

Also list `input/` and `output/` in "Что внутри" and in the `C:\temp\excellent\` tree.

- [ ] **Step 3: Verify git tracks gitkeep files**

Run: `git check-ignore -v excellent/output/.gitkeep excellent/input/.gitkeep`

Expected: `input/.gitkeep` not ignored; `output/.gitkeep` not ignored (the `!` rules win).

- [ ] **Step 4: Commit**

```bash
git add .gitignore excellent/input/.gitkeep excellent/output/.gitkeep excellent/README_RU.txt
git commit -m "Document Excellent input/output folders for the Windows client"
```

---

### Task 4: Full verification

- [ ] **Step 1: Run the full unittest suite**

```bash
python -m unittest discover -s tests -v
```

Expected: all tests pass.

- [ ] **Step 2: Confirm normalization functions are unchanged in the client script vs developer script**

```bash
git diff cursor/water-meter-smtp-attachments-f8f3 -- excellent/scripts/combine_excellent_reports.py
```

Expected: diff touches helpers, argparse default, `prompt_interactive`, `main`, and notify — not `normalize_workbook` / `build_sheet_plan` / `write_xlsx` internals.

- [ ] **Step 3: Windows bat smoke notes for the PR** (manual, on a Windows machine)

1. Copy `excellent/` to `C:\temp\excellent`.
2. Double-click `run_combine_excellent_reports.bat`.
3. Confirm `input\` and `output\` exist after the first dialog appears.
4. Multi-select `.xlsx` files from a folder **other than** `input\`.
5. Skip object lookup (Cancel).
6. Accept default save `output\excellent-combined-report.xlsx` (or rename).
7. Accept default prefix `HK_`.
8. Confirm the success messagebox shows the full absolute path and row count; the same path is printed in the console.
9. Re-run and Cancel the file dialog; confirm an error message and that the process is a failure (non-zero; bat still `pause`s).
