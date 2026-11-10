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

The follow-up request is to take that **original** Word file, fill the requested
cells, and return it in the same OOXML form — no added/removed rows or columns,
and no HTML/xlsx substitute.

## Fix (published)

### Extract

- Route **5** = Word (`docx` / `dotx` / `docm`). Unknown types are now route **6**.
- `Extractable attachment?` is `_extractRoute < 6`.
- `Route attachment format` has **6** outputs. Output 5:

  `Rename DOCX to ZIP` → `Decompress DOCX zip` → `Parse Word document.xml`
  → `Collapse extract into chatInput`

- Parser reads `word/document.xml`, turns tables into JSON rows (first row =
  headers), and keeps non-table paragraphs. Collapse still wraps the JSON in
  `--- Extracted attachment ---`.
- Agent system prompt lists Word/DOCX as auto-extracted and tells the model
  not to ask for a resend when that block is present.
- Legacy `.doc` is still skipped (it is not a zip). The skip note asks for `.docx`.

### In-place return

When the user asks to fill/return the uploaded Word file, the agent emits:

```
<<<DBT_DOCX_PATCH>>>
{"table":1,"targetColumn":"Остаточная балансовая стоимость на 31.12.2026","copyFromColumn":"Sum of Остаточная стоимость","outputFileName":"ОС_updated.docx"}
<<<END_DBT_DOCX_PATCH>>>
```

`Persist KVM + KV` parses that block into `_docxPatch` / `_docxPatchWanted`.
The return path then:

`Has DOCX patch?` → `Restore original DOCX binary` (from `Classify attachment`
/ Telegram Trigger) → unzip → `Patch Word table cells` (replace `w:t` in the
named column only; `copyFromColumn` is 1:1; optional `updates` by `_row` or
`match`) → zip the **same** OOXML parts → `Stamp patched DOCX` → Telegram
document `*_updated.docx`.

No rows or columns are added or removed. HTML (`DBT_HTML_FILE`) is skipped
when `_docxPatched` is set.

## Files

- `dbt-accountant-assistant.backup.json` — snapshot before Word extract (105 nodes).
- `dbt-accountant-assistant.json` — published snapshot after extract + in-place
  return (116 nodes).
