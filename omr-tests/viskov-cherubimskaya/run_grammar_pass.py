#!/usr/bin/env python3
"""
Layer D+ grammar pass on Viskov Audiveris MusicXML.

Walks every pitched note with active key signature, applies on-the-fly
literacy rules (+ optional Redis-like memory), writes corrections JSON,
markdown report, and a corrected .musicxml (not re-zipped .mxl).

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
    MemoryDemo,
    Mode,
    PC,
    correct_hypothesis,
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
    """Pick major vs relative minor by pitch-class histogram fit."""
    major_tonic = (PC["C"] + 7 * fifths) % 12  # circle of fifths
    # relative minor tonic = major tonic - 3 semitones
    minor_tonic = (major_tonic - 3) % 12
    major_scale = {(major_tonic + i) % 12 for i in (0, 2, 4, 5, 7, 9, 11)}
    # natural minor; harmonic adds +1 on 7th later in rules
    minor_scale = {(minor_tonic + i) % 12 for i in (0, 2, 3, 5, 7, 8, 10)}
    total = sum(pcs.values()) or 1
    maj = sum(pcs[p] for p in major_scale) / total
    # boost tonic weight slightly
    maj += 0.15 * (pcs[major_tonic] / total)
    mn = sum(pcs[p] for p in minor_scale) / total
    mn += 0.15 * (pcs[minor_tonic] / total)
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


def run_pass(
    root: ET.Element,
    *,
    mode: Mode,
    use_memory: bool = True,
    apply: bool = True,
) -> dict:
    mem = MemoryDemo()
    corrections: list[dict] = []
    kept = 0
    forced = 0
    by_rule: Counter = Counter()
    by_severity: Counter = Counter()
    # track active fifths per part
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
                alter_in = int(float(alter_el.text)) if alter_el is not None and alter_el.text else 0
                octave = int(p.findtext("octave") or 4)

                ctx = f"fifths={fifths}|mode={mode}|step={step}|part={pid}"
                hint = mem.hint(ctx) if use_memory else None
                c = correct_hypothesis(
                    step, alter_in, fifths, mode, memory_hint=hint
                )
                by_rule[c.rule.split(":")[0] if ":" in c.rule else c.rule] += 1
                by_severity[c.severity] += 1

                if c.action == "keep":
                    kept += 1
                    continue

                forced += 1
                rec = {
                    "part": pid,
                    "measure": mid,
                    "fifths": fifths,
                    "mode": mode,
                    "octave": octave,
                    **asdict(c),
                }
                corrections.append(rec)

                # Learn hard/soft force into memory for later notes
                if use_memory and c.alter_out is not None:
                    mem.learn(ctx, c.alter_out)

                if apply and c.alter_out is not None and c.alter_out != alter_in:
                    if alter_el is None:
                        alter_el = ET.SubElement(p, "alter")
                        # keep order: step, alter, octave — ElementTree appends at end; OK for trial
                    alter_el.text = str(c.alter_out)
                    if c.alter_out == 0 and alter_el is not None:
                        # keep explicit 0 for naturals that cancelled key flats
                        alter_el.text = "0"

    return {
        "mode": mode,
        "pitched_checked": kept + forced,
        "kept": kept,
        "forced": forced,
        "corrections": corrections,
        "by_rule": dict(by_rule),
        "by_severity": dict(by_severity),
        "memory_entries": len(mem),
        "memory_hits_total": sum(int(v.get("hits", 0)) for v in mem.values()),
    }


def rss_mb() -> float:
    return resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024.0


def main() -> None:
    ART.mkdir(parents=True, exist_ok=True)
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    t0 = time.perf_counter()
    root_src, xml_name = load_score(MXL)
    pcs = collect_global_pcs(root_src)
    # Primary key from score: majority fifths
    fifths_hist: Counter = Counter()
    for ks in root_src.iter("key"):
        f = ks.findtext("fifths")
        if f is not None:
            fifths_hist[int(f)] += 1
    primary_fifths = fifths_hist.most_common(1)[0][0] if fifths_hist else -2
    mode = detect_mode(primary_fifths, pcs)

    root = deepcopy(root_src)
    result = run_pass(root, mode=mode, use_memory=True, apply=True)
    wall = time.perf_counter() - t0

    # Write corrected MusicXML
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
        "engine": "tonal_rules_onthefly + MemoryDemo",
    }

    summary = {
        "source": str(MXL),
        "xml_entry": xml_name,
        "primary_fifths": primary_fifths,
        "fifths_hist": dict(fifths_hist),
        "mode_selected": mode,
        "key_alters": key_alters(primary_fifths),
        "resources": resources,
        **{k: v for k, v in result.items() if k != "corrections"},
        "corrections_sample": result["corrections"][:40],
        "corrections_all_count": len(result["corrections"]),
    }

    json_path = OUT_DIR / "grammar-pass-viskov.json"
    full = {**summary, "corrections": result["corrections"]}
    json_path.write_text(json.dumps(full, ensure_ascii=False, indent=2), encoding="utf-8")
    (ART / "grammar-pass-viskov.json").write_text(
        json.dumps(full, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    # Markdown report
    lines = [
        "# Grammar pass — Висков, Херувимская (Layer D+)",
        "",
        f"Источник: `{MXL.name}` → правила `tonal_rules_onthefly.py` + in-process Redis memory.",
        "",
        "| | |",
        "|---|---|",
        f"| Primary fifths | **{primary_fifths}** (hist {dict(fifths_hist)}) |",
        f"| Mode | **{mode}** (histogram fit) |",
        f"| Pitched notes | {result['pitched_checked']} |",
        f"| Kept | {result['kept']} |",
        f"| Forced corrections | **{result['forced']}** |",
        f"| Memory entries | {result['memory_entries']} (hits sum {result['memory_hits_total']}) |",
        f"| Wall | {resources['wall_s']} s |",
        f"| Peak RSS | {resources['peak_rss_mb']} MB |",
        f"| LLM tokens | **0 / 0** (€0) |",
        "",
        "## По правилам",
        "",
    ]
    for rule, n in sorted(result["by_rule"].items(), key=lambda x: -x[1]):
        lines.append(f"- `{rule}`: {n}")
    lines += ["", "## Severity", ""]
    for sev, n in result["by_severity"].items():
        lines.append(f"- {sev}: {n}")

    lines += ["", "## Примеры правок (до 25)", ""]
    if not result["corrections"]:
        lines.append("_Правок не потребовалось — все ноты уже в allowed set._")
    else:
        lines.append("| Part | М. | In | Out | Severity | Rule |")
        lines.append("|---|---|---|---|---|---|")
        for c in result["corrections"][:25]:
            inn = f"{c['step']}{'♯'*c['alter_in'] if c['alter_in']>0 else '♭'*(-c['alter_in']) if c['alter_in']<0 else '♮'}"
            outa = c["alter_out"]
            out = f"{c['step']}{'♯'*outa if outa and outa>0 else '♭'*(-outa) if outa and outa<0 else '♮'}"
            lines.append(
                f"| {c['part']} | {c['measure']} | {inn} | {out} | {c['severity']} | `{c['rule'][:60]}` |"
            )

    lines += [
        "",
        "## Артефакты",
        "",
        f"- Corrected MusicXML: `{corrected_path.name}`",
        f"- Full JSON: `{json_path.name}`",
        "",
        "## Вывод",
        "",
    ]
    if result["forced"]:
        lines.append(
            f"Grammar-pass изменил **{result['forced']}** гипотез Audiveris "
            f"из {result['pitched_checked']} pitched (режим {mode}, fifths={primary_fifths}). "
            "Память подсказала повторные контексты без LLM."
        )
    else:
        lines.append(
            "Явных hard/soft нарушений в alter-поле не найдено; "
            "ключ и вводные тона уже согласованы с правилами."
        )

    report = "\n".join(lines) + "\n"
    report_path = ROOT / "GRAMMAR-PASS-VISKOV-REPORT.md"
    report_path.write_text(report, encoding="utf-8")
    (ART / "GRAMMAR-PASS-VISKOV-REPORT.md").write_text(report, encoding="utf-8")

    print(json.dumps(summary, ensure_ascii=False, indent=2))
    print("---")
    print(report)


if __name__ == "__main__":
    main()
