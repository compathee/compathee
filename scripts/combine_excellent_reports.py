#!/usr/bin/env python3
"""Combine Standard/Excellent Books Excel exports into one normalized report.

The script expects standard report exports where object codes are represented as
columns, for example HK_NOMME / HK_NÕMME. It converts those object columns into
rows ("unpivot") and writes a single combined XLSX or CSV report.
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
from collections import defaultdict
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable

try:
    from openpyxl import Workbook, load_workbook
    from openpyxl.worksheet.worksheet import Worksheet
except ModuleNotFoundError as exc:  # pragma: no cover - exercised by users without deps
    raise SystemExit(
        "Missing dependency: openpyxl. Install dependencies with "
        "`python -m pip install -r requirements.txt`."
    ) from exc


DEFAULT_OBJECT_PATTERN = r"^[A-Z0-9]+[_-][A-Z0-9ÕÄÖÜŠŽõäöüšž_-]+$"
BASE_OUTPUT_COLUMNS = [
    "source_file",
    "sheet",
    "source_row",
    "object_code",
    "object_name",
    "amount",
]
SUMMARY_OUTPUT_COLUMNS = [
    "source_file",
    "sheet",
    "status",
    "message",
    "header_row",
    "object_columns",
    "rows",
]


@dataclass(frozen=True)
class ObjectColumn:
    index: int
    code: str
    name: str


@dataclass(frozen=True)
class SheetPlan:
    header_row: int
    headers: list[str]
    object_columns: list[ObjectColumn]
    context_indexes: list[int]


def cell_text(value: Any) -> str:
    if value is None:
        return ""
    text = str(value).strip()
    if text.endswith(".0"):
        try:
            as_number = float(text)
            if as_number.is_integer():
                return str(int(as_number))
        except ValueError:
            pass
    return text


def normalize_header(value: Any, fallback_index: int) -> str:
    text = cell_text(value)
    if not text:
        text = f"column_{fallback_index}"
    return re.sub(r"\s+", " ", text)


def deduplicate_headers(headers: list[str]) -> list[str]:
    seen: dict[str, int] = {}
    result: list[str] = []
    for header in headers:
        count = seen.get(header, 0) + 1
        seen[header] = count
        result.append(header if count == 1 else f"{header}_{count}")
    return result


def parse_object_header(header: str, object_lookup: dict[str, str]) -> tuple[str, str]:
    text = header.strip()
    match = re.match(r"^(?P<code>.+?)\s*\((?P<name>.+)\)\s*$", text)
    if match:
        code = match.group("code").strip()
        return code, match.group("name").strip()
    return text, object_lookup.get(text, "")


def is_object_header(header: str, object_regex: re.Pattern[str], prefixes: tuple[str, ...]) -> bool:
    clean = header.strip()
    if not clean:
        return False
    if prefixes and clean.upper().startswith(prefixes):
        return True
    return bool(object_regex.match(clean))


def iter_xlsx_files(input_dir: Path, explicit_files: list[Path]) -> list[Path]:
    if explicit_files:
        return [path.expanduser().resolve() for path in explicit_files]
    return sorted(
        path.resolve()
        for path in input_dir.expanduser().glob("*.xlsx")
        if not path.name.startswith("~$")
    )


def load_object_lookup(path: Path | None) -> dict[str, str]:
    if path is None:
        return {}
    path = path.expanduser().resolve()
    if not path.exists():
        raise FileNotFoundError(f"Object lookup file not found: {path}")

    rows: list[dict[str, Any]]
    if path.suffix.lower() == ".csv":
        with path.open("r", encoding="utf-8-sig", newline="") as handle:
            rows = list(csv.DictReader(handle))
    elif path.suffix.lower() == ".xlsx":
        workbook = load_workbook(path, read_only=True, data_only=True)
        sheet = workbook[workbook.sheetnames[0]]
        iterator = sheet.iter_rows(values_only=True)
        try:
            header = [cell_text(value) for value in next(iterator)]
        except StopIteration:
            return {}
        rows = [dict(zip(header, row, strict=False)) for row in iterator]
    else:
        raise ValueError("Object lookup must be .csv or .xlsx")

    lookup: dict[str, str] = {}
    for row in rows:
        lowered = {str(key).strip().lower(): value for key, value in row.items()}
        code = cell_text(
            lowered.get("object_code")
            or lowered.get("code")
            or lowered.get("object")
            or lowered.get("objekt")
        )
        name = cell_text(
            lowered.get("object_name")
            or lowered.get("name")
            or lowered.get("description")
            or lowered.get("nimetus")
        )
        if code:
            lookup[code] = name
    return lookup


def find_header_row(
    worksheet: Worksheet,
    object_regex: re.Pattern[str],
    prefixes: tuple[str, ...],
    max_scan_rows: int,
) -> int:
    best_row = 1
    best_score = -1
    for row_number, row in enumerate(
        worksheet.iter_rows(min_row=1, max_row=max_scan_rows, values_only=True), start=1
    ):
        headers = [normalize_header(value, index + 1) for index, value in enumerate(row)]
        object_count = sum(is_object_header(header, object_regex, prefixes) for header in headers)
        non_empty = sum(1 for value in row if cell_text(value))
        score = object_count * 100 + non_empty
        if score > best_score:
            best_score = score
            best_row = row_number
        if object_count:
            return row_number
    return best_row


def build_sheet_plan(
    worksheet: Worksheet,
    object_lookup: dict[str, str],
    object_regex: re.Pattern[str],
    prefixes: tuple[str, ...],
    header_row: int | None,
    max_scan_rows: int,
) -> SheetPlan:
    resolved_header_row = header_row or find_header_row(
        worksheet, object_regex, prefixes, max_scan_rows
    )
    raw_headers = next(
        worksheet.iter_rows(
            min_row=resolved_header_row,
            max_row=resolved_header_row,
            values_only=True,
        )
    )
    headers = deduplicate_headers(
        [normalize_header(value, index + 1) for index, value in enumerate(raw_headers)]
    )
    object_columns: list[ObjectColumn] = []
    context_indexes: list[int] = []
    for index, header in enumerate(headers):
        if is_object_header(header, object_regex, prefixes):
            code, name = parse_object_header(header, object_lookup)
            object_columns.append(ObjectColumn(index=index, code=code, name=name))
        else:
            context_indexes.append(index)
    if not object_columns:
        raise ValueError(
            f"No object columns found in {worksheet.title!r} row {resolved_header_row}. "
            "Adjust --object-prefix or --object-pattern."
        )
    return SheetPlan(
        header_row=resolved_header_row,
        headers=headers,
        object_columns=object_columns,
        context_indexes=context_indexes,
    )


def is_empty_amount(value: Any) -> bool:
    if value is None:
        return True
    if isinstance(value, str):
        return value.strip() == ""
    return False


def normalize_workbook(
    workbook_path: Path,
    object_lookup: dict[str, str],
    object_regex: re.Pattern[str],
    prefixes: tuple[str, ...],
    sheet_names: set[str] | None,
    header_row: int | None,
    max_scan_rows: int,
    include_empty_amounts: bool,
) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    workbook = load_workbook(workbook_path, read_only=True, data_only=True)
    rows: list[dict[str, Any]] = []
    summary: list[dict[str, Any]] = []

    selected_sheets = [
        worksheet
        for worksheet in workbook.worksheets
        if sheet_names is None or worksheet.title in sheet_names
    ]
    if not selected_sheets:
        raise ValueError(f"No requested sheets found in {workbook_path.name}")

    for worksheet in selected_sheets:
        try:
            plan = build_sheet_plan(
                worksheet=worksheet,
                object_lookup=object_lookup,
                object_regex=object_regex,
                prefixes=prefixes,
                header_row=header_row,
                max_scan_rows=max_scan_rows,
            )
        except ValueError as exc:
            summary.append(
                {
                    "source_file": workbook_path.name,
                    "sheet": worksheet.title,
                    "status": "skipped",
                    "message": str(exc),
                    "rows": 0,
                }
            )
            continue

        produced = 0
        for source_row, row in enumerate(
            worksheet.iter_rows(min_row=plan.header_row + 1, values_only=True),
            start=plan.header_row + 1,
        ):
            context = {
                plan.headers[index]: row[index] if index < len(row) else None
                for index in plan.context_indexes
                if index < len(plan.headers)
            }
            if not any(not is_empty_amount(value) for value in context.values()):
                continue
            for object_column in plan.object_columns:
                amount = row[object_column.index] if object_column.index < len(row) else None
                if is_empty_amount(amount) and not include_empty_amounts:
                    continue
                output_row = {
                    "source_file": workbook_path.name,
                    "sheet": worksheet.title,
                    "source_row": source_row,
                    "object_code": object_column.code,
                    "object_name": object_column.name,
                    "amount": amount,
                }
                output_row.update(context)
                rows.append(output_row)
                produced += 1

        summary.append(
            {
                "source_file": workbook_path.name,
                "sheet": worksheet.title,
                "status": "ok",
                "message": "",
                "header_row": plan.header_row,
                "object_columns": len(plan.object_columns),
                "rows": produced,
            }
        )

    return rows, summary


def collect_headers(
    rows: Iterable[dict[str, Any]], preferred: list[str] | None = None
) -> list[str]:
    preferred_headers = preferred or BASE_OUTPUT_COLUMNS
    dynamic: list[str] = []
    seen = set(preferred_headers)
    for row in rows:
        for key in row:
            if key not in seen:
                seen.add(key)
                dynamic.append(key)
    return preferred_headers + dynamic


def write_csv(path: Path, rows: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    headers = collect_headers(rows)
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def numeric_or_none(value: Any) -> float | None:
    if isinstance(value, (int, float)):
        return float(value)
    if isinstance(value, str):
        cleaned = value.replace(" ", "").replace(",", ".")
        try:
            return float(cleaned)
        except ValueError:
            return None
    return None


def write_xlsx(path: Path, rows: list[dict[str, Any]], summary: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    workbook = Workbook()
    combined = workbook.active
    combined.title = "combined"
    headers = collect_headers(rows)
    combined.append(headers)
    for row in rows:
        combined.append([row.get(header) for header in headers])
    combined.freeze_panes = "A2"
    combined.auto_filter.ref = combined.dimensions

    summary_sheet = workbook.create_sheet("summary")
    summary_headers = collect_headers(summary, SUMMARY_OUTPUT_COLUMNS)
    summary_sheet.append(summary_headers)
    for row in summary:
        summary_sheet.append([row.get(header) for header in summary_headers])
    summary_sheet.freeze_panes = "A2"
    summary_sheet.auto_filter.ref = summary_sheet.dimensions

    totals_sheet = workbook.create_sheet("totals_by_object")
    totals: dict[tuple[str, str, str], float] = defaultdict(float)
    for row in rows:
        numeric = numeric_or_none(row.get("amount"))
        if numeric is None:
            continue
        key = (
            str(row.get("source_file") or ""),
            str(row.get("object_code") or ""),
            str(row.get("object_name") or ""),
        )
        totals[key] += numeric
    totals_sheet.append(["source_file", "object_code", "object_name", "amount_sum"])
    for (source_file, object_code, object_name), amount_sum in sorted(totals.items()):
        totals_sheet.append([source_file, object_code, object_name, amount_sum])
    totals_sheet.freeze_panes = "A2"
    totals_sheet.auto_filter.ref = totals_sheet.dimensions

    workbook.save(path)


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Combine Standard/Excellent Books XLSX exports into one normalized report."
    )
    parser.add_argument(
        "--input-dir",
        type=Path,
        default=Path("excellent-exports"),
        help="Folder with exported .xlsx files. Ignored when --file is used.",
    )
    parser.add_argument(
        "--file",
        dest="files",
        action="append",
        type=Path,
        default=[],
        help="Specific .xlsx file to include. Can be repeated.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("output/excellent-combined-report.xlsx"),
        help="Output .xlsx or .csv path.",
    )
    parser.add_argument(
        "--objects",
        type=Path,
        help="Optional object lookup .csv/.xlsx with columns object_code/code and object_name/name.",
    )
    parser.add_argument(
        "--sheet",
        action="append",
        default=[],
        help="Sheet name to process. Can be repeated. Defaults to all sheets.",
    )
    parser.add_argument(
        "--header-row",
        type=int,
        help="1-based header row number. Defaults to auto-detection.",
    )
    parser.add_argument(
        "--object-prefix",
        action="append",
        default=["HK_"],
        help="Object column prefix. Can be repeated. Default: HK_. Use --object-prefix '' to rely on regex only.",
    )
    parser.add_argument(
        "--object-pattern",
        default=DEFAULT_OBJECT_PATTERN,
        help=f"Regex for object column headers. Default: {DEFAULT_OBJECT_PATTERN}",
    )
    parser.add_argument(
        "--max-scan-rows",
        type=int,
        default=30,
        help="Maximum rows to scan for automatic header detection.",
    )
    parser.add_argument(
        "--include-empty-amounts",
        action="store_true",
        help="Keep rows where an object amount cell is empty.",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv or sys.argv[1:])
    object_lookup = load_object_lookup(args.objects)
    object_regex = re.compile(args.object_pattern)
    prefixes = tuple(prefix.upper() for prefix in args.object_prefix if prefix)
    sheet_names = set(args.sheet) if args.sheet else None
    input_files = iter_xlsx_files(args.input_dir, args.files)
    if not input_files:
        print("No .xlsx files found.", file=sys.stderr)
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
        except Exception as exc:  # noqa: BLE001 - CLI should continue across files
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

    if args.output.suffix.lower() == ".csv":
        write_csv(args.output, all_rows)
    elif args.output.suffix.lower() == ".xlsx":
        write_xlsx(args.output, all_rows, all_summary)
    else:
        raise ValueError("Output path must end with .xlsx or .csv")

    print(f"Wrote {len(all_rows)} normalized rows to {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
