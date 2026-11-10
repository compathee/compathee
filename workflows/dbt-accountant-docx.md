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

### Unknown Telegram users

Executions `3560` / `3561` (10 Nov 2026, Europe/Kiev) were `/start` from
Jelena Podoljan (`1794699518`). `Prep unauthorized access` crashed on
`this.getCredentials` (Code task runner has no such helper), so the admin
never got the add-user request.

Now an unknown chat is ignored (no agent, no reply to that user). One
Approve/Deny message is sent **only** to admin Telegram `1075735475`
(@compath), as HTML (no Markdown), so names like `no_username` cannot
break Telegram entity parsing. Repeat messages from the same unknown chat
are suppressed via `dbt:tg:pending:v2:<chatId>` after a successful send.
Approve/Deny callbacks still update the Redis whitelist; after that the
applicant gets a short granted/denied notice.

## Files

- `dbt-accountant-assistant.backup.json` — snapshot before Word extract (105 nodes).
- `dbt-accountant-assistant.json` — published snapshot after extract, in-place
  return, and unknown-user admin notify (115 nodes).
