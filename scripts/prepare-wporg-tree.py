#!/usr/bin/env python3
"""Prepare a staged WordPress.org plugin tree.

Drops SureCart and commercial Pro lines from the catalog readme while keeping
every release heading, and removes SureCart strings from the translation catalogs.
"""

from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

PRO_LINE = re.compile(r"\bPro\b|SureCart|surecart|choir-rehearsal-pro", re.I)
VERSION_HEADING = re.compile(r"^= \d+\.\d+\.\d+ =\s*$")
SECTION_HEADING = re.compile(r"^== .+ ==\s*$")


def sanitize_readme(text: str) -> str:
    original_versions = re.findall(r"(?m)^= (\d+\.\d+\.\d+) =$", text)
    kept: list[str] = []
    for line in text.splitlines(keepends=True):
        stripped = line.strip()
        if VERSION_HEADING.match(stripped) or SECTION_HEADING.match(stripped) or stripped == "":
            kept.append(line if line.endswith("\n") else line + "\n")
            continue
        if PRO_LINE.search(line):
            continue
        kept.append(line if line.endswith("\n") else line + "\n")

    out = "".join(kept)
    # A release heading with no remaining bullets still has to be listed.
    parts = re.split(r"(?m)(?=^= \d+\.\d+\.\d+ =$)", out)
    rebuilt: list[str] = []
    for part in parts:
        match = re.match(r"^= (\d+\.\d+\.\d+) =$", part.strip().splitlines()[0] if part.strip() else "")
        if match and not re.search(r"(?m)^\*", part):
            part = part.rstrip() + "\n\n* Included in this release.\n\n"
        rebuilt.append(part)
    out = "".join(rebuilt)
    shipped = re.findall(r"(?m)^= (\d+\.\d+\.\d+) =$", out)
    if shipped != original_versions:
        missing = [version for version in original_versions if version not in shipped]
        raise SystemExit(f"wporg readme lost changelog headings: {missing}")
    return out


def strip_po(path: Path) -> None:
    text = path.read_text(encoding="utf-8")
    chunks = re.split(r"\n\n+", text.strip())
    kept = []
    for chunk in chunks:
        msgid = chunk.split("msgstr", 1)[0]
        if re.search(r"(?m)^msgid(?:_plural)? ", chunk) and PRO_LINE.search(msgid):
            continue
        kept.append(chunk.strip("\n"))
    path.write_text("\n\n".join(kept) + "\n", encoding="utf-8")
    subprocess.check_call(["msgfmt", "-o", str(path.with_suffix(".mo")), str(path)])


def strip_l10n(path: Path) -> None:
    lines = []
    for line in path.read_text(encoding="utf-8").splitlines(keepends=True):
        if PRO_LINE.search(line):
            continue
        lines.append(line)
    path.write_text("".join(lines), encoding="utf-8")


def main() -> None:
    root = Path(sys.argv[1])
    readme = root / "readme.txt"
    readme.write_text(sanitize_readme(readme.read_text(encoding="utf-8")), encoding="utf-8")
    languages = root / "languages"
    if languages.is_dir():
        for po in languages.glob("*.po"):
            strip_po(po)
        for php in languages.glob("*.l10n.php"):
            strip_l10n(php)


if __name__ == "__main__":
    main()
