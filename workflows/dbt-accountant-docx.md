# DBT Accountant — Word/DOCX Telegram attachments

Workflow: **DBT Accountant assistant** (`vQnGxLzinMZR6Vhg`)
n8n: https://n8n.dbtmuuga.ee/workflow/vQnGxLzinMZR6Vhg

## Problem

Telegram execution `3554` (10 Nov 2026, Europe/Kiev) received `ОС.docx`
(`application/vnd.openxmlformats-officedocument.wordprocessingml.document`).

`Classify attachment` set `_extractRoute = 5`. `Extractable attachment?` only
treated routes `0–4` as extractable (PDF/CSV/XLSX/XLS/TXT), so the file went to
`Annotate skipped attachment`. `chatInput` never got a
`--- Extracted attachment ---` block. The agent then refused to fill the
residual book-value column and asked the user to resend the Word file.

## Fix (published)

- Route **5** = Word (`docx` / `dotx` / `docm`). Unknown types are now route **6**.
- `Extractable attachment?` is `_extractRoute < 6`.
- `Route attachment format` has **6** outputs. Output 5:

  `Rename DOCX to ZIP` → `Decompress DOCX zip` → `Parse Word document.xml`
  → `Collapse extract into chatInput`

- Parser reads `word/document.xml`, turns tables into JSON rows (first row =
  headers), and keeps non-table paragraphs. Collapse still wraps the JSON in
  `--- Extracted attachment ---`.
- Agent system prompt now lists Word/DOCX as auto-extracted and tells the model
  not to ask for a resend when that block is present.
- Legacy `.doc` is still skipped (it is not a zip). The skip note asks for `.docx`.

Returning a filled **.docx** with original Word styling is out of scope of this
pipeline (n8n has no Word writer). After extraction the agent can return the
filled table this turn as HTML (`DBT_HTML_FILE`) and/or `Document_from_JSON`
(`xlsx`).

## Files

- `dbt-accountant-assistant.backup.json` — snapshot before this change (105 nodes).
- `dbt-accountant-assistant.json` — published snapshot after this change (108 nodes).
