#!/usr/bin/env python3
"""
Layer D+ grammar pass on Viskov Audiveris MusicXML.

1) Harmonize key signatures across all barline-connected staves.
2) Pitch rules: hard only for true enharmonic absurdities;
   chromatic (e.g. A♭ in g minor) → review, pitch unchanged.

LLM tokens = 0. CPU only.
"""
from __future__ import annotations

import json
import resource
import time
import zipfile
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from copy import deepcopy
from dataclasses import asdict
from pathlib import Path

from tonal_rules_onthefly import (
    Mode,
    PC,
    correct_hypothesis,
    harmonize_system_key_signatures,
    key_alters,
)

ROOT = Path(__file__).resolve().parent
MXL = ROOT / "out" / "A.Viskov-Cherubimskaya.mxl"
OUT_DIR = ROOT / "out"
ART = Path("/opt/cursor/artifacts/omr-viskov/compare")


def strip_ns(root: ET.Element) -> None:
    for el in root.iter():
        if "}" in el.tag:
            el.tag = el.tag.split("}", 1)[1]


def load_score(mxl: Path) -> tuple[ET.Element, str]:
    with zipfile.ZipFile(mxl) as z:
        name = next(
            n
            for n in z.namelist()
            if n.endswith(".xml") and "META" not in n.upper()
        )
        data = z.read(name)
    root = ET.fromstring(data)
    strip_ns(root)
    return root, name


def pitch_class(step: str, alter: int) -> int:
    return (PC[step] + alter) % 12


def detect_mode(fifths: int, pcs: Counter) -> Mode:
    major_tonic = (PC["C"] + 7 * fifths) % 12
    minor_tonic = (major_tonic - 3) % 12
    major_scale = {(major_tonic + i) % 12 for i in (0, 2, 4, 5, 7, 9, 11)}
    minor_scale = {(minor_tonic + i) % 12 for i in (0, 2, 3, 5, 7, 8, 10)}
    total = sum(pcs.values()) or 1
    maj = sum(pcs[p] for p in major_scale) / total + 0.15 * (pcs[major_tonic] / total)
    mn = sum(pcs[p] for p in minor_scale) / total + 0.15 * (pcs[minor_tonic] / total)
    return "minor" if mn >= maj else "major"


def collect_global_pcs(root: ET.Element) -> Counter:
    pcs: Counter = Counter()
    for note in root.iter("note"):
        p = note.find("pitch")
        if p is None:
            continue
        step = p.findtext("step")
        if not step:
            continue
        alter = int(float(p.findtext("alter") or 0))
        pcs[pitch_class(step, alter)] += 1
    return pcs


def run_pitch_pass(root: ET.Element, *, mode: Mode) -> dict:
    reviews: list[dict] = []
    forced: list[dict] = []
    kept = 0
    by_rule: Counter = Counter()
    by_action: Counter = Counter()
    current_fifths: dict[str, int] = defaultdict(lambda: -2)

    for part in root.findall("part"):
        pid = part.get("id") or "P?"
        for measure in part.findall("measure"):
            mid = measure.get("number") or "?"
            attrs = measure.find("attributes")
            if attrs is not None:
                key = attrs.find("key")
                if key is not None and key.findtext("fifths") is not None:
                    current_fifths[pid] = int(key.findtext("fifths"))

            fifths = current_fifths[pid]
            for note in measure.findall("note"):
                if note.find("rest") is not None:
                    continue
                p = note.find("pitch")
                if p is None:
                    continue
                step = p.findtext("step")
                if not step:
                    continue
                alter_el = p.find("alter")
                alter_in = (
                    int(float(alter_el.text))
                    if alter_el is not None and alter_el.text
                    else 0
                )
                octave = int(p.findtext("octave") or 4)
                c = correct_hypothesis(step, alter_in, fifths, mode)
                by_rule[c.rule.split(":")[0] if ":" in c.rule else c.rule] += 1
                by_action[c.action] += 1

                if c.action == "keep":
                    kept += 1
                    continue

                rec = {
                    "part": pid,
                    "measure": mid,
                    "fifths": fifths,
                    "mode": mode,
                    "octave": octave,
                    **asdict(c),
                }
                if c.action == "force_alter":
                    forced.append(rec)
                    if c.alter_out is not None and c.alter_out != alter_in:
                        if alter_el is None:
                            alter_el = ET.SubElement(p, "alter")
                        alter_el.text = str(c.alter_out)
                else:
                    # review — pitch unchanged
                    reviews.append(rec)

    return {
        "mode": mode,
        "pitched_checked": kept + len(forced) + len(reviews),
        "kept": kept,
        "forced": forced,
        "reviews": reviews,
        "by_rule": dict(by_rule),
        "by_action": dict(by_action),
    }


def rss_mb() -> float:
    return resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024.0


def main() -> None:
    ART.mkdir(parents=True, exist_ok=True)
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    t0 = time.perf_counter()
    root_src, xml_name = load_score(MXL)
    pcs = collect_global_pcs(root_src)
    fifths_hist: Counter = Counter()
    for ks in root_src.iter("key"):
        f = ks.findtext("fifths")
        if f is not None:
            fifths_hist[int(f)] += 1
    primary_fifths = fifths_hist.most_common(1)[0][0] if fifths_hist else -2
    mode = detect_mode(primary_fifths, pcs)

    root = deepcopy(root_src)

    # --- Pass 1: system key signatures ---
    key_fixes = harmonize_system_key_signatures(root)

    # --- Pass 2: pitch literacy ---
    pitch = run_pitch_pass(root, mode=mode)
    wall = time.perf_counter() - t0

    corrected_path = OUT_DIR / "A.Viskov-Cherubimskaya.grammar-corrected.musicxml"
    ET.ElementTree(root).write(
        corrected_path, encoding="utf-8", xml_declaration=True
    )

    resources = {
        "wall_s": round(wall, 4),
        "peak_rss_mb": round(rss_mb(), 2),
        "llm_tokens_in": 0,
        "llm_tokens_out": 0,
        "llm_cost_eur": 0.0,
        "engine": "system_key_harmonize + tonal_rules_onthefly v2",
    }

    full = {
        "source": str(MXL),
        "xml_entry": xml_name,
        "primary_fifths": primary_fifths,
        "fifths_hist_before": dict(fifths_hist),
        "mode_selected": mode,
        "key_alters": key_alters(primary_fifths),
        "resources": resources,
        "key_fixes": [asdict(f) for f in key_fixes],
        "key_fixes_count": len(key_fixes),
        "pitch": {
            "pitched_checked": pitch["pitched_checked"],
            "kept": pitch["kept"],
            "forced_count": len(pitch["forced"]),
            "review_count": len(pitch["reviews"]),
            "by_rule": pitch["by_rule"],
            "by_action": pitch["by_action"],
            "forced": pitch["forced"],
            "reviews": pitch["reviews"],
        },
        "policy_note": (
            "A♭ in g minor is REVIEW only — never auto A♭→A♮. "
            "Key signatures must match on all barline-connected staves."
        ),
    }

    json_path = OUT_DIR / "grammar-pass-viskov.json"
    json_path.write_text(json.dumps(full, ensure_ascii=False, indent=2), encoding="utf-8")
    (ART / "grammar-pass-viskov.json").write_text(
        json.dumps(full, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    lines = [
        "# Grammar pass v2 — Висков (исправление ошибок v1)",
        "",
        "## Что было не так в v1",
        "",
        "1. **Знаки при ключе** не выравнивались по всем линейкам системы — Audiveris часто писал ключ только на P1; MuseScore рисовал «дыры» (как на вашем скрине).",
        "2. **A♭→A♮** — ошибочное hard-правило («вне звукоряда ⇒ переписать»). Это отсебятина: A♭ в g-moll может быть хроматикой (II♭ / неаполитанский и т.п.). В v2 такие ноты только **review**, высота не меняется.",
        "",
        "## Политика v2",
        "",
        "| Правило | Действие |",
        "|---|---|",
        "| Знаки при ключе на одной линейке системы | **Те же fifths на всех** линейках, соединённых тактовыми чертами |",
        "| B♯ при си♭ в ключе и т.п. | **hard** force |",
        "| A♭ / прочая хроматика вне диатоники | **review**, pitch не трогаем |",
        "",
        f"| | |",
        f"|---|---|",
        f"| Mode | **{mode}** (fifths={primary_fifths}) |",
        f"| Key fixes | **{len(key_fixes)}** |",
        f"| Pitch kept | {pitch['kept']} |",
        f"| Pitch forced | **{len(pitch['forced'])}** |",
        f"| Pitch review | {len(pitch['reviews'])} |",
        f"| Wall | {resources['wall_s']} s |",
        f"| LLM | **0** |",
        "",
        "## Key fixes (фрагмент)",
        "",
    ]
    if not key_fixes:
        lines.append("_Нет правок ключа._")
    else:
        lines.append("| М. | Part | Was | → | Action | Rule |")
        lines.append("|---|---|---|---|---|---|")
        for f in key_fixes[:40]:
            lines.append(
                f"| {f.measure} | {f.part} | {f.fifths_in} | {f.fifths_out} | {f.action} | `{f.rule}` |"
            )
        if len(key_fixes) > 40:
            lines.append(f"| … | | | | | +{len(key_fixes)-40} more |")

    lines += ["", "## Pitch forced (только hard)", ""]
    if not pitch["forced"]:
        lines.append("_Нет hard-правок высоты (B♯ и т.п. не встретились)._")
    else:
        for c in pitch["forced"][:20]:
            lines.append(f"- {c['part']} m{c['measure']}: {c['step']} alter {c['alter_in']}→{c['alter_out']} (`{c['rule']}`)")

    lines += ["", "## Pitch review (хроматика, без переписывания)", ""]
    if not pitch["reviews"]:
        lines.append("_Нет._")
    else:
        lines.append("| Part | М. | Pitch | Rule |")
        lines.append("|---|---|---|---|")
        for c in pitch["reviews"][:30]:
            a = c["alter_in"]
            sym = "♯" * a if a > 0 else ("♭" * (-a) if a < 0 else "♮")
            lines.append(
                f"| {c['part']} | {c['measure']} | {c['step']}{sym} | `{c['rule'][:70]}` |"
            )

    lines += [
        "",
        "## Артефакты",
        "",
        f"- `{corrected_path.name}`",
        f"- `{json_path.name}`",
        "",
    ]
    report = "\n".join(lines) + "\n"
    report_path = ROOT / "GRAMMAR-PASS-VISKOV-REPORT.md"
    report_path.write_text(report, encoding="utf-8")
    (ART / "GRAMMAR-PASS-VISKOV-REPORT.md").write_text(report, encoding="utf-8")

    print(json.dumps({
        "key_fixes": len(key_fixes),
        "forced": len(pitch["forced"]),
        "reviews": len(pitch["reviews"]),
        "kept": pitch["kept"],
        "resources": resources,
        "policy_note": full["policy_note"],
    }, ensure_ascii=False, indent=2))
    print(report)


if __name__ == "__main__":
    main()
