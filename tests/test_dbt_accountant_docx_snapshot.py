"""Sanity-check the saved DBT Accountant assistant workflow snapshot."""

from __future__ import annotations

import json
from pathlib import Path

SNAPSHOT = Path(__file__).resolve().parents[1] / "workflows" / "dbt-accountant-assistant.json"


def test_docx_extract_route_is_wired() -> None:
    wf = json.loads(SNAPSHOT.read_text())
    nodes = {n["name"]: n for n in wf["nodes"]}
    conns = wf["connections"]

    classify = nodes["Classify attachment"]["parameters"]["jsCode"]
    assert "ext === 'docx'" in classify
    assert "return 5;" in classify
    assert "return 6;" in classify

    extractable = nodes["Extractable attachment?"]["parameters"]["conditions"]["conditions"][0][
        "leftValue"
    ]
    assert "< 6" in extractable

    assert nodes["Route attachment format"]["parameters"]["numberOutputs"] == 6
    route_outs = conns["Route attachment format"]["main"]
    assert route_outs[5][0]["node"] == "Rename DOCX to ZIP"
    assert conns["Rename DOCX to ZIP"]["main"][0][0]["node"] == "Decompress DOCX zip"
    assert conns["Decompress DOCX zip"]["main"][0][0]["node"] == "Parse Word document.xml"
    assert conns["Parse Word document.xml"]["main"][0][0]["node"] == "Collapse extract into chatInput"

    parse_js = nodes["Parse Word document.xml"]["parameters"]["jsCode"]
    assert "document\\.xml" in parse_js
    assert "getBinaryDataBuffer" in parse_js

    prompt = nodes["DBT Accountant Agent"]["parameters"]["options"]["systemMessage"]
    assert "DOCX" in prompt
    assert "--- Extracted attachment ---" in prompt
