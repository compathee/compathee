#!/usr/bin/env python3
"""
On-the-fly tonal grammar rules for Audiveris hypothesis correction.

Rules fire per note given current key signature (fifths) + chosen mode.
Results can be cached in Redis-like memory keyed by
  (piece_fingerprint, staff, measure, shape_context) → preferred pitch/alter.

No LLM tokens. CPU-only.
"""
from __future__ import annotations

from dataclasses import dataclass, asdict
from typing import Literal


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
    Hard literacy rules independent of mode.

    Examples:
    - Key has Bb (fifths=-2): B# is nonsense in that context (should be C or Bb).
    - Key has F#: Fb is almost never correct (should be E or F#).
    - Double accidentals against the key direction are suspicious.
    """
    ka = key_alters(fifths)
    key_alt = ka.get(step, 0)

    # Same letter cannot carry the opposite extreme of the key accidental
    # e.g. key Bb → B# forbidden; key F# → Fb forbidden.
    if key_alt == -1 and alter >= 1:
        return f"forbidden_sharp_on_flatted_key_degree:{step}(key♭)≠{step}{'♯'*alter}"
    if key_alt == 1 and alter <= -1:
        return f"forbidden_flat_on_sharpened_key_degree:{step}(key♯)≠{step}{'♭'*(-alter)}"

    # Double accidentals are rare in choral editions; flag always for review
    if abs(alter) >= 2:
        return f"double_accidental_rare:{step} alter={alter}"

    # Enharmonic absurdities in common keys
    if fifths <= -1 and step == "B" and alter == 1:
        return "B♯_with_flat_key_use_C_or_B♭"
    if fifths >= 1 and step == "F" and alter == -1:
        return "F♭_with_sharp_key_use_E_or_F♯"
    if fifths <= -2 and step == "E" and alter == 1:
        return "E♯_unlikely_with_≥2_flats_prefer_F"
    if fifths >= 2 and step == "C" and alter == -1:
        return "C♭_unlikely_with_≥2_sharps_prefer_B"

    return None


def allowed_alters(step: str, fifths: int, mode: Mode) -> set[int]:
    """Alters permitted on-the-fly for this step under key+mode."""
    ka = key_alters(fifths)
    key_alt = ka[step]
    allowed = {key_alt, 0}  # diatonic + natural cancellation always possible

    # chromatic inflection toward raised leading tone in minor
    if mode == "minor":
        # raised 7th: for g minor (2 flats), leading tone is F#
        # F is flatted? No — in 2 flats F is natural in key. Raised → +1
        # degree mapping via tonic of relative minor
        # Simpler table by fifths for choral keys:
        leading = {
            # fifths: (step, alter) of raised 7
            0: ("G", 1),   # a minor → G#
            -1: ("E", 1),  # d minor → C# wait: d minor LT is C#
            -2: ("F", 1),  # g minor → F#
            -3: ("B", 0),  # c minor → B natural (key has Bb)
            -4: ("E", 0),  # f minor → E natural
            1: ("D", 1),   # e minor → D#
            2: ("A", 1),   # b minor → A#
        }
        # fix -1: d minor leading = C#
        leading[-1] = ("C", 1)
        if fifths in leading:
            ls, la = leading[fifths]
            if step == ls:
                allowed.add(la)

        # raised 6 (melodic minor ascending) — optional
        raised6 = {
            -2: ("E", 0),  # g minor: E natural (key has Eb)
            -1: ("B", 0),  # d minor: B natural
            0: ("F", 1),   # a minor: F#
            -3: ("A", 0),  # c minor: A natural
        }
        if fifths in raised6:
            s6, a6 = raised6[fifths]
            if step == s6:
                allowed.add(a6)

    # In major, secondary dominants sometimes raise 4th / etc. — keep narrow:
    # allow single chromatic neighbor ±1 from key alter only as soft (not hard allow)
    return allowed


@dataclass
class Correction:
    step: str
    alter_in: int
    alter_out: int | None
    action: Literal["keep", "force_alter", "reject", "review"]
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
    Apply on-the-fly literacy + optional Redis memory hint.

    memory_hint: previously learned preferred alter for this context, if any.
    """
    bad = enharmonic_forbidden(step, alter, fifths)
    if bad:
        # Prefer key-consistent alter
        ka = key_alters(fifths)[step]
        return Correction(step, alter, ka, "force_alter", bad, "hard")

    allowed = allowed_alters(step, fifths, mode)

    # Valid under grammar → keep. Memory must NOT override legal diatonic/LT notes.
    if alter in allowed:
        return Correction(step, alter, alter, "keep", "in_allowed_set", "soft")

    # Illegal alter: prefer Redis hint if it is legal, else snap to key/nearest allowed.
    ka = key_alters(fifths)[step]
    if memory_hint is not None and memory_hint in allowed:
        return Correction(
            step,
            alter,
            memory_hint,
            "force_alter",
            f"redis_memory_prefer_alter={memory_hint}",
            "soft",
        )
    prefer = ka if ka in allowed else sorted(allowed, key=lambda a: abs(a - alter))[0]
    return Correction(
        step,
        alter,
        prefer,
        "force_alter",
        f"alter_{alter}_not_in_allowed_{sorted(allowed)}_→{prefer}",
        "hard",
    )


# --- Redis memory schema (documentation + in-process demo store) ---
#
# Key patterns (compath Redis DB for OMR):
#   omr:grammar:v1:rules                       → hash of static rule ids (versioned)
#   omr:mem:v1:{tenant}:{fp}:{ctx}             → JSON {alter, step, hits, last_ts, source}
#   omr:mem:v1:global:{fp}:{ctx}               → anonymized cross-tenant aggregates (optional)
#
# ctx = f"fifths={fifths}|mode={mode}|step={step}|shape={shape}|staff={staff}"
# fp  = content hash of first page binary or MusicXML skeleton
#
# On each correction accepted by Voice Leader (or auto hard-rule):
#   INCR hits; SET alter; EXPIRE adaptive (e.g. 180d)
# On next Audiveris post-pass:
#   GET memory_hint → feed correct_hypothesis(..., memory_hint=)
# Memory never overrides hard enharmonic_forbidden.


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
    """Examples matching user intent: 2 flats / g minor."""
    fifths = -2
    mode: Mode = "minor"
    mem = MemoryDemo()
    cases = [
        ("B", 1, "B♯ при си♭ в ключе"),
        ("B", -1, "B♭ по ключу — ок"),
        ("B", 0, "B♮ — отмена, ок в миноре/как бекар"),
        ("F", 1, "F♯ — вводный тон g-moll — ок"),
        ("F", 0, "F♮ — диатоника; память не перебивает легальное"),
        ("E", 1, "E♯ при 2♭ — hard forbid → E♭"),
        ("A", 1, "A♯ вне грамматики; Redis hint A♮ если учили"),
        ("A", -1, "A♭ вне g-moll → snap"),
    ]
    out = []
    # prior VL confirms: in this context wrong A# should become A natural
    mem.learn("fifths=-2|mode=minor|step=A", 0)
    mem.learn("fifths=-2|mode=minor|step=F", 1)
    for step, alter, title in cases:
        ctx = f"fifths={fifths}|mode={mode}|step={step}"
        c = correct_hypothesis(step, alter, fifths, mode, memory_hint=mem.hint(ctx))
        out.append({"title": title, **asdict(c), "memory_hits": mem.get(ctx, {}).get("hits", 0)})
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
