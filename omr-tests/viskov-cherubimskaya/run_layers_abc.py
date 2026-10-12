#!/usr/bin/env python3
"""OMR compare layers A/B/C with resource + token accounting (Viskov trial)."""
from __future__ import annotations

import json
import re
import resource
import time
import zipfile
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from dataclasses import asdict, dataclass, field
from pathlib import Path

import numpy as np
from PIL import Image
from skimage.metrics import structural_similarity as ssim

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "out"
CMP = ROOT / "compare"
ART = Path("/opt/cursor/artifacts/omr-viskov/compare")
MXL = OUT / "A.Viskov-Cherubimskaya.mxl"
OMR = OUT / "A.Viskov-Cherubimskaya.omr"
LOG = OUT / "A.Viskov-Cherubimskaya-20261012T1149.log"
MUSE_LOG = ART.parent / "musescore-export.log"

ART.mkdir(parents=True, exist_ok=True)
CMP.mkdir(parents=True, exist_ok=True)


@dataclass
class ResourceSnap:
    wall_s: float = 0.0
    user_s: float = 0.0
    sys_s: float = 0.0
    max_rss_kb: int = 0
    llm_tokens_in: int = 0
    llm_tokens_out: int = 0
    llm_cost_eur: float = 0.0
    notes: str = ""


@dataclass
class Dispute:
    layer: str
    severity: str  # critical|high|medium|low
    type: str
    measure: str | None = None
    part: str | None = None
    page: int | None = None
    system: int | None = None
    evidence: str = ""


@dataclass
class LayerResult:
    name: str
    resources: ResourceSnap
    disputes: list[Dispute] = field(default_factory=list)
    metrics: dict = field(default_factory=dict)


def rss_kb() -> int:
    return int(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss)


def cpu_times() -> tuple[float, float]:
    u = resource.getrusage(resource.RUSAGE_SELF)
    return u.ru_utime, u.ru_stime


class Meter:
    def __init__(self, llm_note: str):
        self.llm_note = llm_note
        self.t0 = 0.0
        self.u0 = 0.0
        self.s0 = 0.0

    def __enter__(self):
        self.t0 = time.perf_counter()
        self.u0, self.s0 = cpu_times()
        return self

    def __exit__(self, *args):
        pass

    def snap(self) -> ResourceSnap:
        t1 = time.perf_counter()
        u1, s1 = cpu_times()
        return ResourceSnap(
            wall_s=round(t1 - self.t0, 3),
            user_s=round(u1 - self.u0, 3),
            sys_s=round(s1 - self.s0, 3),
            max_rss_kb=rss_kb(),
            llm_tokens_in=0,
            llm_tokens_out=0,
            llm_cost_eur=0.0,
            notes=self.llm_note,
        )


def local(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def load_mxl_root() -> ET.Element:
    with zipfile.ZipFile(MXL) as z:
        name = next(n for n in z.namelist() if n.endswith(".xml") and "META" not in n)
        return ET.fromstring(z.read(name))


# ---------- Layer A ----------
def layer_a() -> LayerResult:
    with Meter(
        "No LLM. Pure CPU parse of MusicXML/.omr/log. Tokens=0. "
        "Would route via compath_llm_gateway only if future LLM post-check enabled (WP-22 §3.17)."
    ) as m:
        disputes: list[Dispute] = []
        metrics: dict = {}
        root = load_mxl_root()

        part_names = []
        for sp in root.iter():
            if local(sp.tag) != "score-part":
                continue
            pid = sp.attrib.get("id")
            pname = None
            for c in sp:
                if local(c.tag) == "part-name" and (c.text or "").strip():
                    pname = c.text.strip()
            part_names.append((pid, pname))

        measure_counts: dict[str, int] = {}
        note_counts: dict[str, int] = {}
        rest_counts: dict[str, int] = {}
        key_sigs: dict[str, list[int]] = defaultdict(list)
        time_sigs: dict[str, list[str]] = defaultdict(list)

        for part in root.iter():
            if local(part.tag) != "part":
                continue
            pid = part.attrib.get("id", "?")
            measure_counts[pid] = 0
            note_counts[pid] = 0
            rest_counts[pid] = 0
            for meas in part:
                if local(meas.tag) != "measure":
                    continue
                measure_counts[pid] += 1
                mid = meas.attrib.get("number")
                for child in meas:
                    tag = local(child.tag)
                    if tag == "note":
                        note_counts[pid] += 1
                        if any(local(x.tag) == "rest" for x in child):
                            rest_counts[pid] += 1
                    elif tag == "attributes":
                        for a in child:
                            if local(a.tag) == "key":
                                fifths = next(
                                    (x.text for x in a if local(x.tag) == "fifths"), None
                                )
                                if fifths is not None:
                                    key_sigs[pid].append(int(fifths))
                            if local(a.tag) == "time":
                                beats = next(
                                    (x.text for x in a if local(x.tag) == "beats"), "?"
                                )
                                beat_type = next(
                                    (x.text for x in a if local(x.tag) == "beat-type"), "?"
                                )
                                time_sigs[pid].append(f"{beats}/{beat_type}")

        counts = list(measure_counts.values())
        metrics["parts"] = [
            {
                "id": pid,
                "name": name,
                "measures": measure_counts.get(pid, 0),
                "notes": note_counts.get(pid, 0),
                "rests": rest_counts.get(pid, 0),
            }
            for pid, name in part_names
        ]
        metrics["measure_counts"] = measure_counts
        metrics["measure_skew"] = max(counts) - min(counts) if counts else 0

        if len(set(counts)) > 1:
            disputes.append(
                Dispute(
                    "A",
                    "high",
                    "measure_count_mismatch",
                    evidence=f"measures per part: {measure_counts}",
                )
            )

        for pid, name in part_names:
            if not name or name.lower() in {"voice", "part", "musicxml part"}:
                disputes.append(
                    Dispute(
                        "A",
                        "medium",
                        "generic_part_name",
                        part=pid,
                        evidence=f"name={name!r} (expected SATB-like)",
                    )
                )

        # key signature thrashing
        for pid, keys in key_sigs.items():
            if len(keys) >= 3 and len(set(keys)) >= 2:
                changes = sum(1 for i in range(1, len(keys)) if keys[i] != keys[i - 1])
                if changes >= 2:
                    disputes.append(
                        Dispute(
                            "A",
                            "high",
                            "key_signature_thrash",
                            part=pid,
                            evidence=f"fifths sequence (n={len(keys)}): {keys[:20]}… changes={changes}",
                        )
                    )

        # .omr book: parts per sheet
        with zipfile.ZipFile(OMR) as z:
            book = ET.fromstring(z.read("book.xml"))
        sheet_parts = {}
        for sheet in book.findall("sheet"):
            num = int(sheet.attrib.get("number", 0))
            logical = set()
            for part in sheet.iter("part"):
                lid = part.attrib.get("logical-id")
                if lid:
                    logical.add(lid)
            sheet_parts[num] = sorted(logical)
        metrics["omr_sheet_logical_parts"] = sheet_parts
        part_sets = {tuple(v) for v in sheet_parts.values()}
        if len(part_sets) > 1:
            disputes.append(
                Dispute(
                    "A",
                    "high",
                    "part_count_unstable_across_pages",
                    evidence=f"logical parts by sheet: {sheet_parts}",
                )
            )

        # grades from sheet XML
        low_grade = []
        grade_stats = []
        with zipfile.ZipFile(OMR) as z:
            for i in range(1, 5):
                xml = z.read(f"sheet#{i}/sheet#{i}.xml").decode("utf-8", errors="replace")
                grades = [float(x) for x in re.findall(r'grade="([0-9.]+)"', xml)]
                ctx = [float(x) for x in re.findall(r'ctx-grade="([0-9.]+)"', xml)]
                grade_stats.append(
                    {
                        "sheet": i,
                        "n_grade": len(grades),
                        "min": min(grades) if grades else None,
                        "p10": float(np.percentile(grades, 10)) if grades else None,
                        "median": float(np.median(grades)) if grades else None,
                        "frac_lt_0_5": float(np.mean(np.array(grades) < 0.5)) if grades else None,
                        "frac_lt_0_7": float(np.mean(np.array(grades) < 0.7)) if grades else None,
                    }
                )
                # sample low-grade symbols with shape
                for mobj in re.finditer(
                    r'<([a-z-]+)[^>]*shape="([^"]+)"[^>]*grade="([0-9.]+)"[^>]*id="(\d+)"',
                    xml,
                ):
                    g = float(mobj.group(3))
                    if g < 0.5:
                        low_grade.append(
                            {
                                "sheet": i,
                                "tag": mobj.group(1),
                                "shape": mobj.group(2),
                                "grade": g,
                                "id": mobj.group(4),
                            }
                        )
        metrics["grade_stats"] = grade_stats
        metrics["low_grade_count"] = len(low_grade)
        metrics["low_grade_sample"] = low_grade[:25]
        if len(low_grade) >= 10:
            disputes.append(
                Dispute(
                    "A",
                    "medium",
                    "many_low_confidence_symbols",
                    evidence=f"{len(low_grade)} symbols with grade<0.5 across sheets",
                )
            )
        elif low_grade:
            disputes.append(
                Dispute(
                    "A",
                    "low",
                    "low_confidence_symbols",
                    evidence=f"{len(low_grade)} symbols with grade<0.5",
                )
            )

        # log signals
        log = LOG.read_text(errors="replace") if LOG.exists() else ""
        tess = log.count("Could not initialize TessBaseAPI")
        npe = log.count("NullPointerException")
        warn = log.count(" WARN ")
        metrics["log"] = {"WARN": warn, "NPE": npe, "tesseract_legacy_fail": tess}
        if tess:
            disputes.append(
                Dispute(
                    "A",
                    "medium",
                    "tesseract_ocr_init_failed",
                    evidence="eng legacy mode failed — lyrics/text unreliable",
                )
            )
        if npe:
            disputes.append(
                Dispute(
                    "A",
                    "high",
                    "export_npe_wedges",
                    evidence=f"{npe} NullPointerException during MusicXML export (wedges)",
                )
            )

        # MuseScore import errors if present
        if MUSE_LOG.exists():
            ml = MUSE_LOG.read_text(errors="replace")
            span = len(re.findall(r"spanner ", ml))
            arp = len(re.findall(r"unknown arpeggio", ml))
            metrics["musescore_import"] = {"spanner_msgs": span, "arpeggio_msgs": arp}
            if span or arp:
                disputes.append(
                    Dispute(
                        "A",
                        "medium",
                        "musicxml_import_errors",
                        evidence=f"MuseScore: spanner-related≈{span}, unknown arpeggio≈{arp}",
                    )
                )

        emptyish = [
            pid
            for pid, n in note_counts.items()
            if n > 0 and rest_counts[pid] / max(n, 1) > 0.5
        ]
        if emptyish:
            disputes.append(
                Dispute(
                    "A",
                    "medium",
                    "rest_heavy_parts",
                    evidence=f"parts with >50% rests: {emptyish}",
                )
            )

        res = m.snap()
        return LayerResult("A_structural", res, disputes, metrics)


# ---------- Layer B ----------
def _to_gray_bin(path: Path, size: tuple[int, int] | None = None) -> np.ndarray:
    im = Image.open(path).convert("L")
    if size:
        im = im.resize(size, Image.Resampling.BILINEAR)
    arr = np.asarray(im, dtype=np.float32) / 255.0
    return arr


def _system_bands(binary: np.ndarray, min_gap: int = 40) -> list[tuple[int, int]]:
    """Find horizontal bands with ink (rough system detection via row projection)."""
    # ink = dark
    ink = binary < 0.5
    proj = ink.mean(axis=1)
    thr = max(0.02, float(np.percentile(proj[proj > 0], 30)) if np.any(proj > 0) else 0.02)
    active = proj > thr
    bands = []
    start = None
    for i, a in enumerate(active):
        if a and start is None:
            start = i
        elif not a and start is not None:
            if i - start >= 30:
                bands.append((start, i))
            start = None
    if start is not None and len(active) - start >= 30:
        bands.append((start, len(active)))
    # merge close bands
    if not bands:
        return [(0, binary.shape[0])]
    merged = [bands[0]]
    for b in bands[1:]:
        if b[0] - merged[-1][1] < min_gap:
            merged[-1] = (merged[-1][0], b[1])
        else:
            merged.append(b)
    return merged


def layer_b() -> LayerResult:
    with Meter(
        "No LLM. CPU image ops (resize, SSIM, projections). Tokens=0. "
        "Heavy RAM on page rasters @150dpi; no GPU; no compath_llm_gateway."
    ) as m:
        disputes: list[Dispute] = []
        metrics: dict = {}
        page_rows = []
        system_rows = []

        # Compare each original page to OMR BINARY (same sheet index) — best geometric match
        # Also compare orig page i to best recon page by SSIM (layout reflow)
        recon_paths = sorted(CMP.glob("recon-*.png"))
        for i in range(1, 5):
            orig_p = CMP / f"orig-{i}.png"
            bin_p = CMP / f"omr-binary-{i}.png"
            orig = _to_gray_bin(orig_p)
            # binarize orig roughly
            orig_bin = (orig < 0.85).astype(np.float32)
            omr_bin = _to_gray_bin(bin_p, size=(orig.shape[1], orig.shape[0]))
            omr_bin = (omr_bin < 0.5).astype(np.float32)

            # SSIM on full page (structural)
            # skimage ssim wants same shape; data_range=1
            try:
                score_full = float(
                    ssim(orig_bin, omr_bin, data_range=1.0)
                )
            except Exception as e:
                score_full = float("nan")
                disputes.append(
                    Dispute(
                        "B",
                        "medium",
                        "ssim_failed",
                        page=i,
                        evidence=str(e),
                    )
                )

            # heatmap
            diff = np.abs(orig_bin - omr_bin)
            heat = (diff * 255).astype(np.uint8)
            heat_rgb = np.stack([heat, (255 - heat) // 3, np.zeros_like(heat)], axis=-1)
            heat_path = ART / f"heatmap-orig-vs-omr-binary-p{i}.png"
            Image.fromarray(heat_rgb).save(heat_path)

            bands_o = _system_bands(orig_bin)
            bands_b = _system_bands(omr_bin)
            n_sys = min(len(bands_o), len(bands_b))
            sys_scores = []
            for si in range(n_sys):
                y0a, y1a = bands_o[si]
                y0b, y1b = bands_b[si]
                # crop and resize to common height
                ca = orig_bin[y0a:y1a, :]
                cb = omr_bin[y0b:y1b, :]
                h = min(ca.shape[0], cb.shape[0], 200)
                w = min(ca.shape[1], cb.shape[1])
                if h < 10 or w < 10:
                    continue
                ca2 = np.array(
                    Image.fromarray((ca * 255).astype(np.uint8)).resize(
                        (w, h), Image.Resampling.BILINEAR
                    ),
                    dtype=np.float32,
                ) / 255.0
                cb2 = np.array(
                    Image.fromarray((cb * 255).astype(np.uint8)).resize(
                        (w, h), Image.Resampling.BILINEAR
                    ),
                    dtype=np.float32,
                ) / 255.0
                sc = float(ssim(ca2, cb2, data_range=1.0))
                sys_scores.append(sc)
                system_rows.append(
                    {
                        "page": i,
                        "system": si + 1,
                        "ssim": round(sc, 4),
                        "band_orig": [y0a, y1a],
                        "band_omr": [y0b, y1b],
                    }
                )
                if sc < 0.55:
                    disputes.append(
                        Dispute(
                            "B",
                            "high",
                            "system_visual_mismatch",
                            page=i,
                            system=si + 1,
                            evidence=f"SSIM={sc:.3f} (threshold 0.55)",
                        )
                    )
                elif sc < 0.7:
                    disputes.append(
                        Dispute(
                            "B",
                            "medium",
                            "system_visual_weak",
                            page=i,
                            system=si + 1,
                            evidence=f"SSIM={sc:.3f}",
                        )
                    )

            # best recon page match (reflow)
            best_recon = None
            best_s = -1.0
            target_size = (orig.shape[1], orig.shape[0])
            for rp in recon_paths:
                r = _to_gray_bin(rp, size=target_size)
                rbin = (r < 0.85).astype(np.float32)
                s = float(ssim(orig_bin, rbin, data_range=1.0))
                if s > best_s:
                    best_s = s
                    best_recon = rp.name

            page_rows.append(
                {
                    "page": i,
                    "ssim_orig_vs_omr_binary": round(score_full, 4),
                    "mean_ink_diff": round(float(diff.mean()), 4),
                    "systems_orig": len(bands_o),
                    "systems_omr": len(bands_b),
                    "system_ssim_mean": round(float(np.mean(sys_scores)), 4)
                    if sys_scores
                    else None,
                    "best_recon_page": best_recon,
                    "best_recon_ssim": round(best_s, 4),
                    "heatmap": str(heat_path.name),
                }
            )
            # OMR binary is derived from the same scan — high SSIM is expected.
            # Low SSIM here means preprocessing/binarization drift, not MusicXML quality.
            if score_full < 0.5:
                disputes.append(
                    Dispute(
                        "B",
                        "critical",
                        "page_vs_omr_binary_mismatch",
                        page=i,
                        evidence=f"full-page SSIM vs OMR binary={score_full:.3f}",
                    )
                )
            elif score_full < 0.65:
                disputes.append(
                    Dispute(
                        "B",
                        "medium",
                        "page_vs_omr_binary_weak",
                        page=i,
                        evidence=f"full-page SSIM vs OMR binary={score_full:.3f}",
                    )
                )

            # Reconstructed MuseScore PDF (from MusicXML) vs original — true visual QA
            if best_s < 0.45:
                disputes.append(
                    Dispute(
                        "B",
                        "critical",
                        "page_vs_recon_pdf_mismatch",
                        page=i,
                        evidence=f"best SSIM vs MusicXML→PDF page ({best_recon})={best_s:.3f}",
                    )
                )
            elif best_s < 0.6:
                disputes.append(
                    Dispute(
                        "B",
                        "high",
                        "page_vs_recon_pdf_weak",
                        page=i,
                        evidence=f"best SSIM vs MusicXML→PDF page ({best_recon})={best_s:.3f} (layout reflow expected; still flags weak match)",
                    )
                )

            if abs(len(bands_o) - len(bands_b)) >= 2:
                disputes.append(
                    Dispute(
                        "B",
                        "medium",
                        "system_count_mismatch",
                        page=i,
                        evidence=f"systems orig={len(bands_o)} omr_bin={len(bands_b)}",
                    )
                )

        metrics["pages"] = page_rows
        metrics["systems"] = system_rows
        metrics["mean_page_ssim_omr_binary"] = round(
            float(np.mean([p["ssim_orig_vs_omr_binary"] for p in page_rows])), 4
        )
        metrics["mean_best_recon_ssim"] = round(
            float(np.mean([p["best_recon_ssim"] for p in page_rows])), 4
        )
        # raster footprint
        metrics["raster_bytes"] = {
            "orig_pages": sum((CMP / f"orig-{i}.png").stat().st_size for i in range(1, 5)),
            "recon_pages": sum(p.stat().st_size for p in recon_paths),
            "omr_binary": sum((CMP / f"omr-binary-{i}.png").stat().st_size for i in range(1, 5)),
        }

        res = m.snap()
        return LayerResult("B_visual_align", res, disputes, metrics)


# ---------- Layer C ----------
def _midi_pitch(step: str, alter: float | None, octave: int) -> int:
    base = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11}[step]
    return 12 * (octave + 1) + base + int(round(alter or 0))


def _parse_musicxml_events(root: ET.Element) -> dict[str, list[dict]]:
    """Return part -> list of {measure, onset_divs, dur_divs, midi, is_rest}."""
    parts: dict[str, list[dict]] = {}
    for part in root.iter():
        if local(part.tag) != "part":
            continue
        pid = part.attrib.get("id", "?")
        events = []
        divisions = 1
        for meas in part:
            if local(meas.tag) != "measure":
                continue
            mid = meas.attrib.get("number", "?")
            cursor = 0
            last_dur = 0
            for child in meas:
                tag = local(child.tag)
                if tag == "attributes":
                    for a in child:
                        if local(a.tag) == "divisions" and a.text:
                            divisions = int(a.text)
                elif tag == "note":
                    is_chord = any(local(x.tag) == "chord" for x in child)
                    is_rest = any(local(x.tag) == "rest" for x in child)
                    dur_el = next((x for x in child if local(x.tag) == "duration"), None)
                    dur = int(dur_el.text) if dur_el is not None and dur_el.text else last_dur
                    last_dur = dur
                    if is_chord and events:
                        onset = events[-1]["onset_divs"]
                    else:
                        onset = cursor
                    midi = None
                    if not is_rest:
                        pitch = next((x for x in child if local(x.tag) == "pitch"), None)
                        if pitch is not None:
                            step = next(x.text for x in pitch if local(x.tag) == "step")
                            octave = int(
                                next(x.text for x in pitch if local(x.tag) == "octave")
                            )
                            alter_el = next(
                                (x for x in pitch if local(x.tag) == "alter"), None
                            )
                            alter = float(alter_el.text) if alter_el is not None else 0.0
                            midi = _midi_pitch(step, alter, octave)
                    events.append(
                        {
                            "measure": mid,
                            "onset_divs": onset,
                            "dur_divs": dur,
                            "midi": midi,
                            "is_rest": is_rest,
                            "divisions": divisions,
                        }
                    )
                    if not is_chord:
                        cursor += dur
                elif tag == "backup":
                    dur_el = next((x for x in child if local(x.tag) == "duration"), None)
                    if dur_el is not None:
                        cursor -= int(dur_el.text)
                elif tag == "forward":
                    dur_el = next((x for x in child if local(x.tag) == "duration"), None)
                    if dur_el is not None:
                        cursor += int(dur_el.text)
        parts[pid] = events
    return parts


def _events_to_roll(events: list[dict], steps_per_quarter: int = 4) -> np.ndarray:
    """Binary piano-roll: time x 128."""
    if not events:
        return np.zeros((1, 128), dtype=np.uint8)
    # normalize to fixed grid using first divisions
    rows = []
    for e in events:
        if e["is_rest"] or e["midi"] is None:
            continue
        div = max(e["divisions"], 1)
        # convert to sixteenth-ish steps
        scale = steps_per_quarter / div
        t0 = int(e["onset_divs"] * scale)
        t1 = max(t0 + 1, int((e["onset_divs"] + e["dur_divs"]) * scale))
        rows.append((t0, t1, int(e["midi"])))
    if not rows:
        return np.zeros((1, 128), dtype=np.uint8)
    tmax = max(t1 for _, t1, _ in rows) + 1
    roll = np.zeros((tmax, 128), dtype=np.uint8)
    for t0, t1, midi in rows:
        if 0 <= midi < 128:
            roll[t0:t1, midi] = 1
    return roll


def _roll_f1(a: np.ndarray, b: np.ndarray) -> dict:
    """Frame-wise F1 after padding to common length."""
    T = max(a.shape[0], b.shape[0])
    if a.shape[0] < T:
        a = np.pad(a, ((0, T - a.shape[0]), (0, 0)))
    if b.shape[0] < T:
        b = np.pad(b, ((0, T - b.shape[0]), (0, 0)))
    # DTW-lite: simple shift search ±8 frames for best F1
    best = {"f1": -1.0, "precision": 0.0, "recall": 0.0, "shift": 0}

    def score(x, y):
        tp = int(np.logical_and(x, y).sum())
        fp = int(np.logical_and(x, 1 - y).sum())
        fn = int(np.logical_and(1 - x, y).sum())
        prec = tp / (tp + fp) if tp + fp else 0.0
        rec = tp / (tp + fn) if tp + fn else 0.0
        f1 = 2 * prec * rec / (prec + rec) if prec + rec else 0.0
        return prec, rec, f1

    for shift in range(-8, 9):
        if shift >= 0:
            x = a[shift:]
            y = b[: x.shape[0]]
        else:
            y = b[-shift:]
            x = a[: y.shape[0]]
        if x.size == 0:
            continue
        prec, rec, f1 = score(x, y)
        if f1 > best["f1"]:
            best = {
                "f1": round(f1, 4),
                "precision": round(prec, 4),
                "recall": round(rec, 4),
                "shift": shift,
            }
    return best


def _parse_omr_heads(sheet_xml: str, sheet: int) -> list[dict]:
    """Extract note head pitches/grades from Audiveris sheet XML if present."""
    heads = []
    # heads appear as <head ... pitch="..." grade="..."> or nested
    for mobj in re.finditer(
        r'<head\b([^>]*)>',
        sheet_xml,
    ):
        attrs = mobj.group(1)
        grade_m = re.search(r'grade="([0-9.]+)"', attrs)
        pitch_m = re.search(r'pitch="([^"]+)"', attrs)
        shape_m = re.search(r'shape="([^"]+)"', attrs)
        staff_m = re.search(r'staff="(\d+)"', attrs)
        id_m = re.search(r'id="(\d+)"', attrs)
        heads.append(
            {
                "sheet": sheet,
                "grade": float(grade_m.group(1)) if grade_m else None,
                "pitch": pitch_m.group(1) if pitch_m else None,
                "shape": shape_m.group(1) if shape_m else None,
                "staff": int(staff_m.group(1)) if staff_m else None,
                "id": id_m.group(1) if id_m else None,
            }
        )
    return heads


def layer_c() -> LayerResult:
    with Meter(
        "No LLM. CPU MusicXML→piano-roll + shift-F1 + .omr head parse. Tokens=0. "
        "Same compute class as WP-20 mir_eval path; not billed as LLM tokens. "
        "Future optional LLM adjudication of disputed bars would add tokens via gateway."
    ) as m:
        disputes: list[Dispute] = []
        metrics: dict = {}
        root = load_mxl_root()
        events = _parse_musicxml_events(root)
        metrics["events_per_part"] = {pid: len(ev) for pid, ev in events.items()}

        rolls = {pid: _events_to_roll(ev) for pid, ev in events.items()}
        metrics["roll_shapes"] = {pid: list(r.shape) for pid, r in rolls.items()}

        # Pairwise part comparison (detect odd-one-out / misaligned voices)
        pids = sorted(rolls.keys())
        pairwise = []
        for i, a in enumerate(pids):
            for b in pids[i + 1 :]:
                sc = _roll_f1(rolls[a], rolls[b])
                pairwise.append({"a": a, "b": b, **sc})
                # choral parts should not be identical; very low F1 with huge length skew is worse
        metrics["pairwise_f1"] = pairwise

        # Length skew between rolls
        lengths = {pid: rolls[pid].shape[0] for pid in pids}
        metrics["roll_lengths"] = lengths
        if lengths and max(lengths.values()) - min(lengths.values()) > max(
            8, 0.15 * max(lengths.values())
        ):
            disputes.append(
                Dispute(
                    "C",
                    "high",
                    "piano_roll_length_skew",
                    evidence=f"roll lengths (steps): {lengths}",
                )
            )

        # Self-consistency: empty rolls
        for pid, r in rolls.items():
            if r.sum() == 0:
                disputes.append(
                    Dispute(
                        "C",
                        "critical",
                        "empty_pitch_roll",
                        part=pid,
                        evidence="no pitched notes in MusicXML part",
                    )
                )

        # Measure-level density anomalies
        dens = {}
        for pid, ev in events.items():
            by_m: Counter = Counter()
            for e in ev:
                if not e["is_rest"] and e["midi"] is not None:
                    by_m[e["measure"]] += 1
            dens[pid] = dict(by_m)
            # measures with zero pitched notes while others have many
        all_measures = sorted(
            {m for d in dens.values() for m in d.keys()},
            key=lambda x: int(re.sub(r"\D", "", str(x)) or 0),
        )
        empty_measures = []
        for mid in all_measures:
            counts = [dens[pid].get(mid, 0) for pid in pids]
            if sum(counts) == 0:
                continue
            if min(counts) == 0 and max(counts) >= 2:
                empty_measures.append({"measure": mid, "notes_per_part": dict(zip(pids, counts))})
        metrics["measures_with_silent_part"] = empty_measures[:40]
        if len(empty_measures) >= 3:
            disputes.append(
                Dispute(
                    "C",
                    "high",
                    "silent_part_in_active_measures",
                    evidence=f"{len(empty_measures)} measures where some part has 0 notes and another has ≥2",
                )
            )

        # OMR heads vs MusicXML pitch inventory
        omr_heads = []
        with zipfile.ZipFile(OMR) as z:
            for i in range(1, 5):
                xml = z.read(f"sheet#{i}/sheet#{i}.xml").decode("utf-8", errors="replace")
                omr_heads.extend(_parse_omr_heads(xml, i))
        metrics["omr_heads_total"] = len(omr_heads)
        pitched_heads = [h for h in omr_heads if h.get("pitch")]
        metrics["omr_heads_with_pitch"] = len(pitched_heads)
        low = [h for h in omr_heads if h.get("grade") is not None and h["grade"] < 0.55]
        metrics["omr_heads_grade_lt_0_55"] = len(low)
        if low:
            disputes.append(
                Dispute(
                    "C",
                    "medium",
                    "low_grade_noteheads",
                    evidence=f"{len(low)} noteheads grade<0.55 (sample grades={[h['grade'] for h in low[:8]]})",
                )
            )

        # MusicXML pitch histogram entropy / range sanity
        midis = [
            e["midi"]
            for ev in events.values()
            for e in ev
            if e["midi"] is not None
        ]
        if midis:
            metrics["midi_range"] = [int(min(midis)), int(max(midis))]
            metrics["midi_count"] = len(midis)
            if max(midis) - min(midis) > 40:
                disputes.append(
                    Dispute(
                        "C",
                        "medium",
                        "extreme_pitch_span",
                        evidence=f"MIDI span {min(midis)}–{max(midis)} (>40 semitones) — possible clef/octave errors",
                    )
                )

        # Proxy "ground truth": compare each part roll to the mean ensemble roll
        # (flags parts that don't fit — not true GT, but useful dispute signal)
        if pids:
            T = max(rolls[p].shape[0] for p in pids)
            acc = np.zeros((T, 128), dtype=np.float32)
            for p in pids:
                r = rolls[p]
                if r.shape[0] < T:
                    r = np.pad(r, ((0, T - r.shape[0]), (0, 0)))
                acc += r
            ensemble = (acc >= 1).astype(np.uint8)
            fit = {}
            for p in pids:
                r = rolls[p]
                if r.shape[0] < T:
                    r = np.pad(r, ((0, T - r.shape[0]), (0, 0)))
                fit[p] = _roll_f1(r, ensemble)
                if fit[p]["f1"] < 0.15 and r.sum() > 0:
                    disputes.append(
                        Dispute(
                            "C",
                            "medium",
                            "part_poor_ensemble_fit",
                            part=p,
                            evidence=f"F1 vs ensemble union={fit[p]['f1']}",
                        )
                    )
            metrics["part_vs_ensemble_f1"] = fit

        metrics["note"] = (
            "No human-verified ground-truth MusicXML for this piece; "
            "layer C uses internal consistency + Audiveris head grades as proxy. "
            "True GT compare would replace ensemble proxy."
        )

        res = m.snap()
        return LayerResult("C_note_level", res, disputes, metrics)


def severity_counts(disputes: list[Dispute]) -> dict:
    c = Counter(d.severity for d in disputes)
    return dict(c)


def main():
    started = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
    results = []
    for fn in (layer_a, layer_b, layer_c):
        print(f"Running {fn.__name__}…")
        results.append(fn())

    # totals
    total_wall = sum(r.resources.wall_s for r in results)
    total_tokens = sum(
        r.resources.llm_tokens_in + r.resources.llm_tokens_out for r in results
    )
    peak_rss = max(r.resources.max_rss_kb for r in results)

    summary = {
        "started_utc": started,
        "piece": "А. Висков — Херувимская песнь",
        "inputs": {
            "pdf": str(ROOT / "A.Viskov-Cherubimskaya.pdf"),
            "mxl": str(MXL),
            "omr": str(OMR),
        },
        "layers": [
            {
                "name": r.name,
                "resources": asdict(r.resources),
                "dispute_count": len(r.disputes),
                "severity_counts": severity_counts(r.disputes),
                "disputes": [asdict(d) for d in r.disputes],
                "metrics": r.metrics,
            }
            for r in results
        ],
        "totals": {
            "wall_s": round(total_wall, 3),
            "peak_rss_kb": peak_rss,
            "peak_rss_mb": round(peak_rss / 1024, 2),
            "llm_tokens_total": total_tokens,
            "llm_cost_eur": 0.0,
            "billing_note": (
                "Layers A/B/C are non-LLM. In production, record under "
                "compath_api compute metrics (CPU-s, RAM, pages), NOT llm_gateway tokens. "
                "Audiveris OMR itself also uses 0 LLM tokens (WP-22)."
            ),
        },
    }

    json_path = ART / "layers-abc-results.json"
    json_path.write_text(json.dumps(summary, ensure_ascii=False, indent=2))

    # Markdown report
    lines = []
    lines.append("# OMR compare layers A/B/C — resource & token report")
    lines.append("")
    lines.append(f"**Piece:** А. Висков — Херувимская песнь  ")
    lines.append(f"**Started (UTC):** {started}  ")
    lines.append(f"**Jira:** WP-25")
    lines.append("")
    lines.append("## Resource & token accounting (per layer)")
    lines.append("")
    lines.append(
        "| Layer | Wall s | CPU user s | CPU sys s | Peak RSS MB | LLM tokens in/out | LLM € |"
    )
    lines.append("| --- | ---: | ---: | ---: | ---: | ---: | ---: |")
    for r in results:
        rs = r.resources
        lines.append(
            f"| {r.name} | {rs.wall_s} | {rs.user_s} | {rs.sys_s} | "
            f"{rs.max_rss_kb/1024:.1f} | {rs.llm_tokens_in}/{rs.llm_tokens_out} | {rs.llm_cost_eur:.4f} |"
        )
    lines.append(
        f"| **TOTAL** | **{total_wall:.3f}** |  |  | **{peak_rss/1024:.1f}** | "
        f"**{total_tokens}** | **0.0000** |"
    )
    lines.append("")
    lines.append(
        "> LLM tokens = 0 on all layers (algorithmic). Production billing: CPU-time / pages "
        "for OMR+compare; LLM only if optional adjudication is enabled via `compath_llm_gateway`."
    )
    lines.append("")
    for r in results:
        lines.append(f"## {r.name}")
        lines.append("")
        lines.append(f"- Disputes: **{len(r.disputes)}** `{severity_counts(r.disputes)}`")
        lines.append(f"- Resources note: {r.resources.notes}")
        lines.append("")
        if r.disputes:
            lines.append("| Sev | Type | Page/Sys/Part | Evidence |")
            lines.append("| --- | --- | --- | --- |")
            for d in r.disputes:
                loc = "/".join(
                    x
                    for x in [
                        f"p{d.page}" if d.page else "",
                        f"s{d.system}" if d.system else "",
                        d.part or "",
                        f"m{d.measure}" if d.measure else "",
                    ]
                    if x
                )
                ev = d.evidence.replace("|", "\\|")[:160]
                lines.append(f"| {d.severity} | `{d.type}` | {loc or '—'} | {ev} |")
            lines.append("")
        # key metrics brief
        if r.name.startswith("A"):
            lines.append(f"- Measure skew: {r.metrics.get('measure_skew')}")
            lines.append(f"- Low-grade symbols: {r.metrics.get('low_grade_count')}")
            lines.append(f"- Log: `{r.metrics.get('log')}`")
        if r.name.startswith("B"):
            lines.append(
                f"- Mean page SSIM (orig vs OMR binary): "
                f"**{r.metrics.get('mean_page_ssim_omr_binary')}**"
            )
            lines.append(
                f"- Mean best recon-page SSIM: **{r.metrics.get('mean_best_recon_ssim')}**"
            )
            lines.append(f"- Heatmaps saved under `{ART}`")
        if r.name.startswith("C"):
            lines.append(f"- OMR heads: {r.metrics.get('omr_heads_total')} "
                         f"(with pitch {r.metrics.get('omr_heads_with_pitch')})")
            lines.append(f"- MIDI range: {r.metrics.get('midi_range')}")
            lines.append(f"- Silent-part measures: {len(r.metrics.get('measures_with_silent_part') or [])}")
            lines.append(f"- Note: {r.metrics.get('note')}")
        lines.append("")

    lines.append("## Production metering mapping (WP-22 / WP-23)")
    lines.append("")
    lines.append("| Layer | Meter in Server Admin | Counts toward |")
    lines.append("| --- | --- | --- |")
    lines.append("| A structural | CPU-ms, parse time | Pro+AI / Partner job overhead (cheap) |")
    lines.append("| B visual | CPU-ms, RAM peak, raster MB | pages × dpi cost; not LLM |")
    lines.append("| C note-level | CPU-ms (mir_eval-class) | same job; not LLM |")
    lines.append("| (future) LLM bar judge | `compath_llm_gateway` tokens | AI token budget / Pro+AI |")
    lines.append("| Audiveris OMR | worker CPU/RAM/queue wait | pages OMR quota |")
    lines.append("")

    md_path = ART / "LAYERS-ABC-REPORT.md"
    md_path.write_text("\n".join(lines), encoding="utf-8")
    (ROOT / "LAYERS-ABC-REPORT.md").write_text("\n".join(lines), encoding="utf-8")
    print("Wrote", json_path)
    print("Wrote", md_path)
    print(
        json.dumps(
            {
                "totals": summary["totals"],
                "disputes": {r.name: len(r.disputes) for r in results},
                "resources": {r.name: asdict(r.resources) for r in results},
            },
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
