#!/usr/bin/env python3
"""
On-the-fly tonal grammar rules for Audiveris hypothesis correction.

Hard rules (must fix):
  - Key signatures on braced/barline-connected staves must match.
  - True enharmonic absurdities vs key (e.g. B♯ when key has B♭).

Soft / review (do NOT auto-rewrite pitch):
  - Chromatic tones outside the diatonic set (e.g. A♭ in g minor) —
    flag for Voice Leader; never force A♭→A♮ as "grammar".

No LLM tokens. CPU-only.
"""
from __future__ import annotations

from collections import Counter
from dataclasses import dataclass, asdict
from typing import Literal
import xml.etree.ElementTree as ET


Mode = Literal["major", "minor"]

SHARP_ORDER = ["F", "C", "G", "D", "A", "E", "B"]
FLAT_ORDER = ["B", "E", "A", "D", "G", "C", "F"]
PC = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11}
STEPS = list(PC)


def key_alters(fifths: int) -> dict[str, int]:
    acc = {s: 0 for s in STEPS}
    if fifths > 0:
        for s in SHARP_ORDER[:fifths]:
            acc[s] = 1
    elif fifths < 0:
        for s in FLAT_ORDER[:(-fifths)]:
            acc[s] = -1
    return acc


def enharmonic_forbidden(step: str, alter: int, fifths: int) -> str | None:
    """
    Hard literacy only — true nonsense against the key letter.

    Example: key has B♭ (fifths≤-1) → B♯ is forbidden.
    Does NOT ban chromatic A♭ in g minor (that is review, not force).
    """
    ka = key_alters(fifths)
    key_alt = ka.get(step, 0)

    # Same letter cannot carry the opposite extreme of the key accidental
    if key_alt == -1 and alter >= 1:
        return f"forbidden_sharp_on_flatted_key_degree:{step}(key♭)≠{step}{'♯'*alter}"
    if key_alt == 1 and alter <= -1:
        return f"forbidden_flat_on_sharpened_key_degree:{step}(key♯)≠{step}{'♭'*(-alter)}"

    if abs(alter) >= 2:
        return f"double_accidental_rare:{step} alter={alter}"

    if fifths <= -1 and step == "B" and alter == 1:
        return "B♯_with_flat_key_use_C_or_B♭"
    if fifths >= 1 and step == "F" and alter == -1:
        return "F♭_with_sharp_key_use_E_or_F♯"

    return None


def diatonic_and_common_chromatic(step: str, fifths: int, mode: Mode) -> set[int]:
    """Alters that are expected without review (diatonic + LT / melodic-6)."""
    ka = key_alters(fifths)
    key_alt = ka[step]
    allowed = {key_alt, 0}  # diatonic + natural cancellation

    if mode == "minor":
        leading = {
            0: ("G", 1),
            -1: ("C", 1),
            -2: ("F", 1),
            -3: ("B", 0),
            -4: ("E", 0),
            1: ("D", 1),
            2: ("A", 1),
        }
        if fifths in leading:
            ls, la = leading[fifths]
            if step == ls:
                allowed.add(la)
        raised6 = {
            -2: ("E", 0),
            -1: ("B", 0),
            0: ("F", 1),
            -3: ("A", 0),
        }
        if fifths in raised6:
            s6, a6 = raised6[fifths]
            if step == s6:
                allowed.add(a6)

    return allowed


@dataclass
class Correction:
    step: str
    alter_in: int
    alter_out: int | None
    action: Literal["keep", "force_alter", "review", "reject"]
    rule: str
    severity: Literal["hard", "soft"]


def correct_hypothesis(
    step: str,
    alter: int,
    fifths: int,
    mode: Mode,
    *,
    memory_hint: int | None = None,
) -> Correction:
    """
    Hard: only enharmonic_forbidden → force to key alter.
    Soft chromatic (e.g. A♭ in g minor): review, keep pitch unchanged.
    Memory may prefer alter only when current alter is hard-illegal.
    """
    bad = enharmonic_forbidden(step, alter, fifths)
    if bad:
        ka = key_alters(fifths)[step]
        if memory_hint is not None and memory_hint != alter:
            # memory still cannot keep a hard-illegal pitch
            pass
        return Correction(step, alter, ka, "force_alter", bad, "hard")

    expected = diatonic_and_common_chromatic(step, fifths, mode)
    if alter in expected:
        return Correction(step, alter, alter, "keep", "in_expected_set", "soft")

    # Chromatic / unexpected — DO NOT auto-rewrite (was wrong for A♭→A♮).
    return Correction(
        step,
        alter,
        None,
        "review",
        f"chromatic_or_unexpected_alter={alter}_step={step}_fifths={fifths}_mode={mode}",
        "soft",
    )


# ---------------------------------------------------------------------------
# System key-signature consistency (barline-connected staves)
# ---------------------------------------------------------------------------

@dataclass
class KeyFix:
    measure: str
    part: str
    fifths_in: int | None
    fifths_out: int
    rule: str
    action: Literal["insert", "replace", "ok"]


def _ensure_key_element(attrs: ET.Element, fifths: int) -> None:
    key = attrs.find("key")
    if key is None:
        # insert key near start of attributes
        key = ET.Element("key")
        # place before time/clef if possible
        kids = list(attrs)
        insert_at = 0
        for i, ch in enumerate(kids):
            if ch.tag in ("divisions", "key"):
                insert_at = i + 1
            elif ch.tag in ("time", "clef", "staves"):
                insert_at = i
                break
            else:
                insert_at = i + 1
        attrs.insert(insert_at, key)
    fifths_el = key.find("fifths")
    if fifths_el is None:
        fifths_el = ET.SubElement(key, "fifths")
    fifths_el.text = str(fifths)
    # drop mode if we don't know; leave if present
    # remove empty leftover
    for child in list(key):
        if child.tag not in ("fifths", "mode") and (child.text is None or not str(child.text).strip()):
            key.remove(child)


def _measure_num(mid: str) -> int | None:
    try:
        return int(mid)
    except (TypeError, ValueError):
        return None


def deepcopy_el(el: ET.Element) -> ET.Element:
    return ET.fromstring(ET.tostring(el, encoding="unicode"))


def _first_clef_xml(part: ET.Element) -> ET.Element | None:
    for m in part.findall("measure"):
        attrs = m.find("attributes")
        if attrs is None:
            continue
        clef = attrs.find("clef")
        if clef is not None:
            return deepcopy_el(clef)
    return None


def _empty_measure(
    number: str,
    fifths: int,
    *,
    with_time: bool = False,
    clef: ET.Element | None = None,
) -> ET.Element:
    """Rest measure so lagging parts align visually with the system."""
    m = ET.Element("measure", number=number)
    attrs = ET.SubElement(m, "attributes")
    div = ET.SubElement(attrs, "divisions")
    div.text = "1"
    key = ET.SubElement(attrs, "key")
    fif = ET.SubElement(key, "fifths")
    fif.text = str(fifths)
    if with_time:
        time_el = ET.SubElement(attrs, "time")
        beats = ET.SubElement(time_el, "beats")
        beats.text = "4"
        beat_type = ET.SubElement(time_el, "beat-type")
        beat_type.text = "4"
    if clef is not None:
        attrs.append(clef)
    note = ET.SubElement(m, "note")
    rest = ET.SubElement(note, "rest")
    rest.set("measure", "yes")
    dur = ET.SubElement(note, "duration")
    dur.text = "4"
    typ = ET.SubElement(note, "type")
    typ.text = "whole"
    return m


def align_parts_and_harmonize_keys(root: ET.Element) -> list[KeyFix]:
    """
    Rule: key signatures on barline-connected staves must match.

    Audiveris often:
      - omits early measures on lower parts (P2 starts at 2, P3/P4 at 4);
      - writes key only on some parts;
      - thrash-flips fifths (e.g. −2 → 0 → −2) on a subset of staves.

    Fix:
      1. Pad missing measure numbers on each part (whole rests) so systems align.
      2. Per measure: if any staff has a key, ALL staves get the same fifths.
         Spurious minority flips (not agreed by all active parts) → piece majority.
    """
    parts = list(root.findall("part"))
    if not parts:
        return []

    all_explicit: list[int] = []
    for part in parts:
        for m in part.findall("measure"):
            attrs = m.find("attributes")
            if attrs is None:
                continue
            key = attrs.find("key")
            if key is not None and key.findtext("fifths") is not None:
                all_explicit.append(int(key.findtext("fifths")))
    piece_majority = (
        Counter(all_explicit).most_common(1)[0][0] if all_explicit else 0
    )

    # Global numeric measure range
    all_nums: list[int] = []
    for part in parts:
        for m in part.findall("measure"):
            n = _measure_num(m.get("number") or "")
            if n is not None:
                all_nums.append(n)
    if not all_nums:
        return []
    lo, hi = min(all_nums), max(all_nums)

    fixes: list[KeyFix] = []

    # --- 1) Pad missing measures ---
    for part in parts:
        pid = part.get("id") or "?"
        part_clef = _first_clef_xml(part)
        existing = {
            _measure_num(m.get("number") or ""): m
            for m in part.findall("measure")
            if _measure_num(m.get("number") or "") is not None
        }
        # insert missing in order at correct positions
        for n in range(lo, hi + 1):
            if n in existing:
                continue
            new_m = _empty_measure(
                str(n),
                piece_majority,
                with_time=(n == lo),
                clef=deepcopy_el(part_clef) if part_clef is not None else None,
            )
            # find insertion point: before first measure with number > n
            inserted = False
            for i, child in enumerate(list(part)):
                if child.tag != "measure":
                    continue
                cn = _measure_num(child.get("number") or "")
                if cn is not None and cn > n:
                    part.insert(i, new_m)
                    inserted = True
                    break
            if not inserted:
                part.append(new_m)
            existing[n] = new_m
            fixes.append(
                KeyFix(
                    str(n),
                    pid,
                    None,
                    piece_majority,
                    "pad_missing_measure_for_system_align",
                    "insert",
                )
            )

    # Rebuild index
    by_part: dict[str, dict[int, ET.Element]] = {}
    for part in parts:
        pid = part.get("id") or "?"
        by_part[pid] = {}
        for m in part.findall("measure"):
            n = _measure_num(m.get("number") or "")
            if n is not None:
                by_part[pid][n] = m

    # --- 2) Harmonize keys measure-by-measure ---
    # Genuine modulation: EVERY active staff explicitly states the same new fifths.
    # Otherwise (subset / conflict / thrash) → piece_majority on all staves.
    for n in range(lo, hi + 1):
        explicits: dict[str, int] = {}
        active_pids = [pid for pid, mmap in by_part.items() if n in mmap]
        for pid in active_pids:
            m = by_part[pid][n]
            attrs = m.find("attributes")
            if attrs is None:
                continue
            key = attrs.find("key")
            if key is not None and key.findtext("fifths") is not None:
                explicits[pid] = int(key.findtext("fifths"))

        if (
            explicits
            and len(explicits) == len(active_pids)
            and len(set(explicits.values())) == 1
        ):
            target = next(iter(explicits.values()))
            rule_base = "system_key_unanimous_modulation"
        elif explicits or n == lo:
            target = piece_majority
            rule_base = "system_key_must_match_all_barline_staves"
        else:
            continue

        for pid in active_pids:
            m = by_part[pid][n]
            attrs = m.find("attributes")
            if attrs is None:
                attrs = ET.Element("attributes")
                m.insert(0, attrs)
            prev = None
            key = attrs.find("key")
            if key is not None and key.findtext("fifths") is not None:
                prev = int(key.findtext("fifths"))
            if prev == target:
                continue
            _ensure_key_element(attrs, target)
            action: Literal["insert", "replace"] = (
                "replace" if prev is not None else "insert"
            )
            rule = rule_base
            if prev is not None and prev != target:
                rule = "reject_spurious_key_flip_not_on_all_staves"
            fixes.append(KeyFix(str(n), pid, prev, target, rule, action))

    # Deduplicate: keep last fix per (measure, part)
    uniq: dict[tuple[str, str], KeyFix] = {}
    for f in fixes:
        uniq[(f.measure, f.part)] = f
    return list(uniq.values())


# Back-compat alias
def harmonize_system_key_signatures(root: ET.Element) -> list[KeyFix]:
    return align_parts_and_harmonize_keys(root)


class MemoryDemo(dict):
    """In-process stand-in for Redis during trials."""

    def learn(self, ctx: str, alter: int) -> None:
        rec = self.get(ctx, {"alter": alter, "hits": 0})
        rec["alter"] = alter
        rec["hits"] = int(rec.get("hits", 0)) + 1
        self[ctx] = rec

    def hint(self, ctx: str) -> int | None:
        rec = self.get(ctx)
        return None if not rec else int(rec["alter"])


def demo_viskov_rules() -> list[dict]:
    """Examples: 2 flats / g minor — Ab is REVIEW not force."""
    fifths = -2
    mode: Mode = "minor"
    cases = [
        ("B", 1, "B♯ при си♭ в ключе — hard forbid"),
        ("B", -1, "B♭ по ключу — ok"),
        ("B", 0, "B♮ — бекар ok"),
        ("F", 1, "F♯ — вводный тон g-moll — ok"),
        ("F", 0, "F♮ — диатоника — ok"),
        ("E", 1, "E♯ при 2♭ — hard forbid"),
        ("A", -1, "A♭ — хроматика: review, НЕ переписывать в A♮"),
        ("A", 0, "A♮ — диатоника g-moll — ok"),
    ]
    out = []
    for step, alter, title in cases:
        c = correct_hypothesis(step, alter, fifths, mode)
        out.append({"title": title, **asdict(c)})
    return out


if __name__ == "__main__":
    import json
    from pathlib import Path

    rows = demo_viskov_rules()
    art = Path("/opt/cursor/artifacts/omr-viskov/compare")
    art.mkdir(parents=True, exist_ok=True)
    (art / "tonal-rules-onthefly-demo.json").write_text(
        json.dumps(rows, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(json.dumps(rows, ensure_ascii=False, indent=2))
