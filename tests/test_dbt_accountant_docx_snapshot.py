"""Sanity-check the saved DBT Accountant assistant workflow snapshot."""

from __future__ import annotations

import json
import subprocess
from pathlib import Path

SNAPSHOT = Path(__file__).resolve().parents[1] / "workflows" / "dbt-accountant-assistant.json"


def _load() -> dict:
    return json.loads(SNAPSHOT.read_text())


def test_docx_extract_route_is_wired() -> None:
    wf = _load()
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


def test_docx_inplace_patch_return_is_wired() -> None:
    wf = _load()
    nodes = {n["name"]: n for n in wf["nodes"]}
    conns = wf["connections"]

    persist_targets = [c["node"] for arr in conns["Persist KVM + KV"]["main"] for c in arr]
    assert "Has DOCX patch?" in persist_targets
    assert "Prepare Compath Telegram egress" not in persist_targets

    has_outs = conns["Has DOCX patch?"]["main"]
    assert has_outs[0][0]["node"] == "Restore original DOCX binary"
    assert has_outs[1][0]["node"] == "Prepare Compath Telegram egress"

    assert (
        conns["Restore original DOCX binary"]["main"][0][0]["node"]
        == "Original DOCX binary present?"
    )
    orig_outs = conns["Original DOCX binary present?"]["main"]
    assert orig_outs[0][0]["node"] == "Rename return DOCX to ZIP"
    assert orig_outs[1][0]["node"] == "Prepare Compath Telegram egress"
    assert conns["Rename return DOCX to ZIP"]["main"][0][0]["node"] == "Decompress return DOCX"
    assert conns["Decompress return DOCX"]["main"][0][0]["node"] == "Patch Word table cells"
    assert conns["Patch Word table cells"]["main"][0][0]["node"] == "Compress return DOCX"
    assert conns["Compress return DOCX"]["main"][0][0]["node"] == "Stamp patched DOCX"
    assert conns["Stamp patched DOCX"]["main"][0][0]["node"] == "Prepare Compath Telegram egress"

    persist_js = nodes["Persist KVM + KV"]["parameters"]["jsCode"]
    assert "<<<DBT_DOCX_PATCH>>>" in persist_js
    assert "_docxPatchWanted" in persist_js

    patch_js = nodes["Patch Word table cells"]["parameters"]["jsCode"]
    assert "applyPatchToDocumentXml" in patch_js
    assert "copyFromColumn" in patch_js
    assert "setCellText" in patch_js
    assert "zipEntryName" in patch_js

    restore_js = nodes["Restore original DOCX binary"]["parameters"]["jsCode"]
    assert "Classify attachment" in restore_js

    stamp_js = nodes["Stamp patched DOCX"]["parameters"]["jsCode"]
    assert "_docxPatched" in stamp_js
    assert "_updated.docx" in stamp_js

    egress_js = nodes["Prepare Compath Telegram egress"]["parameters"]["jsCode"]
    assert "_docxPatched" in egress_js
    assert "stripDocxPatchEgress" in egress_js

    prompt = nodes["DBT Accountant Agent"]["parameters"]["options"]["systemMessage"]
    assert "<<<DBT_DOCX_PATCH>>>" in prompt
    assert "copyFromColumn" in prompt
    assert "FORBIDDEN" in prompt
    assert "ОС_updated.docx" in prompt


def test_unknown_telegram_user_notifies_admin_without_agent() -> None:
    wf = _load()
    nodes = {n["name"]: n for n in wf["nodes"]}
    conns = wf["connections"]

    assert "Telegram user access pending" not in nodes

    for name in ("Prep unauthorized access", "Apply admin auth decision"):
        js = nodes[name]["parameters"]["jsCode"]
        assert "getCredentials" not in js
        assert "telegramSendMessageUrl" not in js

    prep = nodes["Prep unauthorized access"]["parameters"]["jsCode"]
    assert "adminChatId = '1075735475'" in prep or 'adminChatId = "1075735475"' in prep
    assert "telegramApproveCallback" in prep
    assert "telegramAdminNotifyText" in prep
    assert "no_username" not in prep
    assert "escHtml" in prep
    assert "dbt:tg:pending:v2:" in prep

    auth_if = conns["If authorized user chats"]["main"]
    assert auth_if[0][0]["node"] == "Telegram typing (on receive)"
    assert auth_if[1][0]["node"] == "Prep unauthorized access"
    assert conns["Prep unauthorized access"]["main"][0][0]["node"] == "Redis Get pending flag"
    assert conns["Redis Get pending flag"]["main"][0][0]["node"] == "Already notified admin?"

    already = conns["Already notified admin?"]["main"]
    assert already[0] == [] or already[0] is None
    assert already[1][0]["node"] == "Message from unknown user"
    assert conns["Message from unknown user"]["main"][0][0]["node"] == "Redis Set pending flag"

    admin_msg = nodes["Message from unknown user"]
    assert admin_msg["type"] == "n8n-nodes-base.telegram"
    assert admin_msg["parameters"]["replyMarkup"] == "inlineKeyboard"
    assert admin_msg["parameters"]["chatId"] == "1075735475"
    assert "{{" not in str(admin_msg["parameters"]["chatId"])
    assert admin_msg["parameters"]["additionalFields"]["parse_mode"] == "HTML"
    assert admin_msg["credentials"]["telegramApi"]["id"] == "FeJMdrxnjZderBuQ"

    answer = nodes["Telegram answer auth callback"]
    assert answer["type"] == "n8n-nodes-base.telegram"
    assert answer["parameters"]["resource"] == "callback"

    notify = nodes["Telegram notify user auth result"]
    assert notify["type"] == "n8n-nodes-base.telegram"
    assert "telegramAuthTargetChatId" in notify["parameters"]["chatId"]
    assert conns["Telegram answer auth callback"]["main"][0][0]["node"] == (
        "Telegram notify user auth result"
    )
    assert "dbt:tg:pending:v2:" in nodes["Redis Del pending (admin)"]["parameters"]["key"]


def test_admin_approve_does_not_run_accountant_agent() -> None:
    wf = _load()
    nodes = {n["name"]: n for n in wf["nodes"]}
    conns = wf["connections"]

    admin_true = [c["node"] for c in conns["Admin auth callback?"]["main"][0]]
    admin_false = [c["node"] for c in conns["Admin auth callback?"]["main"][1]]
    assert admin_true == ["Redis Get tg whitelist (admin)"]
    assert "Merge whitelist auth" not in admin_true
    assert admin_false == ["Redis Get tg whitelist"]

    session_key = nodes["Session memory"]["parameters"]["sessionKey"]
    assert "callback_query" in session_key
    assert "message?.chat?.id" in session_key

    prompt = nodes["DBT Accountant Agent"]["parameters"]["options"]["systemMessage"]
    assert nodes["DBT Accountant Agent"]["parameters"]["options"]["maxIterations"] == 24
    assert "HARD LIMITS" in prompt
    assert "[No user text]" in prompt
    assert "crash Excellent Books" in prompt
    assert "`limit` (max 50)" in prompt
    assert "PLAN → SHOW → CONFIRM → CALL" in prompt
    assert "FAST PATH" in prompt
    assert "do **not** open Hansa/Excellent yet" in prompt
    assert "ACCOUNT NUMBER" in prompt
    assert "**AccVc**" in prompt
    assert "**VIVc**" in prompt
    assert "**IVVc**" in prompt
    assert "type is already clear" in prompt

    confirm = nodes["Telegram_Confirm_With_User"]["parameters"]
    assert confirm["approvalOptions"]["values"]["approveLabel"] == "Confirm"
    assert "read or write" in confirm["message"]
    assert confirm.get("chatApproval") is True
    assert "Telegram in-chat buttons" in prompt
    assert "n8n.dbtmuuga.ee" in prompt
    assert "Never quote n8n" in prompt

    rest = nodes["Standard_Books_REST_API"]["parameters"]
    assert "AccVc konto" in rest["description"]
    assert "VIVc supplier invoice" in rest["description"]
    assert "IVVc sales invoice" in rest["description"]
    paste = nodes["Standard_Books_Portal_Paste"]["parameters"]
    assert "bare счёт/arve number" in paste["description"]
    suffix = rest["workflowInputs"]["value"]["rest_query_suffix"]
    assert "Never empty" in suffix
    assert "crashes Excellent Books" in suffix

    assert "session expired" in nodes["Standard_Books_Portal_Login"]["parameters"]["description"]
    assert "NON-EMPTY report_spec_json" in nodes["Standard_Books_Portal_Report"]["parameters"]["description"]
    assert "Confirm with the user first" in nodes["Standard_Books_Portal_Query"]["parameters"]["description"]
    assert "Confirm with the user first" in nodes["Standard_Books_Portal_Report"]["parameters"]["description"]


def test_standard_books_api_refuses_unbounded_register_dumps() -> None:
    sb_path = Path(__file__).resolve().parents[1] / "workflows" / "standard-books-api.json"
    wf = json.loads(sb_path.read_text())
    nodes = {n["name"]: n for n in wf["nodes"]}
    js = nodes["Build API request"]["parameters"]["jsCode"]

    assert "function clampRestListQuery" in js
    assert "function guardPortalListQuery" in js
    assert "REST list GET refused" in js
    assert "at least one filter.*" in js
    assert "MAX_LIMIT = 50" in js
    assert "DEFAULT_LIMIT = 25" in js
    assert "perpage=50" in js
    http = nodes["Call Standard Books API"]["parameters"]
    assert http["options"].get("allowUnauthorizedCerts") is True

    script = (
        js.split("function attachPostBody")[0]
        + """
function assert(cond, msg) { if (!cond) { throw new Error(msg); } }
try { clampRestListQuery(''); throw new Error('empty suffix should refuse'); }
catch (e) { assert(/fields=/.test(e.message), e.message); }
try { clampRestListQuery('fields=SerNr&limit=100'); throw new Error('missing filter should refuse'); }
catch (e) { assert(/filter/.test(e.message), e.message); }
var q = clampRestListQuery('fields=SerNr,TransDate&filter.TransDate=2026-01-01:2026-01-31&limit=999');
assert(q.indexOf('limit=50') >= 0, q);
assert(isRestDetailPath('145/1') === true, 'detail path');
assert(isRestDetailPath('fields=SerNr') === false, 'query is not detail');
var g = guardPortalListQuery('register=CUVc&compno=2');
assert(/perpage=50/.test(g) && /page=1/.test(g), g);
console.log('ok');
"""
    )
    completed = subprocess.run(
        ["node", "-e", script],
        check=False,
        capture_output=True,
        text=True,
    )
    assert completed.returncode == 0, completed.stderr
    assert "ok" in completed.stdout
