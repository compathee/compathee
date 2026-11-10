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

Admin **Approve/Deny** no longer enters `Merge whitelist auth` / the accountant
agent. The true branch of `Admin auth callback?` only updates the Redis
whitelist. Session memory keys off
`message?.chat?.id || callback_query?.message?.chat?.id` so an Approve
callback does not throw “Key parameter is empty”.

### Standard Books / Excellent Books (crash guards)

Unbounded Hansa REST list GETs (`/api/{company}/{register}` with an empty
suffix, or without `fields=` / `filter.*` / `limit`) dump whole registers
and can crash Excellent Books. Basic Auth on every call adds load; the
agent was also able to fire Standard Books tools on an empty `[No user text]`
Approve callback.

Published together (10 Nov 2026, Europe/Kiev):

- **Standard Books — API (Portal + REST)** `yW6RvQG3Bl87M8pK`
  (`3fec07e8-a25b-4fa3-92ff-a002ca152518`): `Build API request` injects
  `limit=25` (cap 50) on REST list GETs and **refuses** a list read that
  omits `fields=` or `filter.*`. Detail paths like `145/1` are unchanged.
  Portal `register=` queries without `action=` get `perpage=50&page=0`
  (Excellent pages are 0-based). REST and portal always pin **company 2**;
  company **1** is the archive. Portal `register=VIVc`/`IVVc` injects
  `usercode` (n8n var `STANDARD_BOOKS_PORTAL_REPORT_USERCODE`, else `API`)
  and requires `sd`/`ed`. REST `/api/2/VIVc` returns 401 with this account.
- **DBT Accountant assistant** `vQnGxLzinMZR6Vhg`
  (`7d95a2c5-d1ff-4fcc-ab99-244893946c41`): system prompt HARD LIMITS
  (one bounded Standard Books call; never on empty/`[No user text]`);
  invoice day counts use **Standard_Books_Portal_Query**
  `register=VIVc|IVVc` with `sd`/`ed` and `page=0` (REST `/api/2/VIVc`
  is 401); REST tool description requires `fields=` + `filter.*` +
  `limit≤50`; Portal login only after a session-expired error; reports
  need a non-empty date spec.
- List/report/write calls follow **PLAN → SHOW → WAIT IN THIS CHAT → CALL**:
  draft from the REST skill (no Hansa yet), `Telegram_Notify_User` with
  the plan, then a **Да / Нет** reply in the same Telegram chat, then one
  bounded API call. `Telegram_Confirm_With_User` (`sendAndWait`) is
  **disabled and disconnected**: the Telegram Trigger owns the bot
  webhook, so Confirm callbacks never resume the wait.
  Fast path (no Да/Нет): only when the **type is already clear** and they
  named the code (REST detail like `145/1`, or paste of that code).
- Bare «счёт / arve / invoice / konto / 145» is ambiguous. The agent must
  ask one question and wait: конто (`AccVc`), счёт поставщика (`VIVc`),
  or счёт на продажу (`IVVc`). It must not guess a register or open Hansa
  on that turn.

### Telegram confirm stays in chat

Exec **3578** (10 Nov 2026, ~12:28 Europe/Kiev) used
`Telegram_Confirm_With_User` `sendAndWait` without `chatApproval`, so
Telegram got an n8n form URL (`n8n.dbtmuuga.ee`) instead of in-chat
buttons. Exec **3586** (~12:39 Europe/Kiev) used in-chat Confirm buttons
with `chatApproval`, but clicking Confirm never resumed the wait: the
Telegram Trigger owns the bot webhook, so the sendAndWait callback never
arrives. Confirm is now disabled. After a plan, answer **Да** or **Нет**
as a normal Telegram message. Ignore any leftover Confirm button from
the hung wait.

The same 3578 run then called Hansa REST twice and failed
`certificate has expired` on `stbooks.dbtmuuga.ee:1335`. The HTTP node
now has **Ignore SSL issues** (`allowUnauthorizedCerts`) so the local
expired Hansa cert does not block bounded reads. The agent must not
quote SSL/n8n internals to the user.

### False “0 invoices” (company 1 / Portal list)

Exec **3589 / 3595** (~12:47 Europe/Kiev) answered **Да** and called
Portal `register=VIVc&sd=…&ed=…` with `compno=2` but no `usercode` and
`page=1`. Excellent returned `<data total='0'>`. A direct probe then
found the real invoice via Portal `register=VIVc&usercode=…&sd=2026-11-09&ed=2026-11-10&page=0&compno=2`:
SerNr **35663**, Rahandusministeerium, 289.00 EUR, InvDate 2026-11-09.
REST `/api/2/VIVc` with the same Basic Auth is **401**. Invoice counts
now use that Portal list (usercode injected; company **2**; page **0**).

### GL postings (konto + object, no month dump)

AccVc is the chart-of-accounts **card**, not проводки. Journal lines are
TRVc **rows** (`AccNumber`, `Objects`, `DebVal`, `CredVal`). REST
`filter.*` only matches headers; `filter.Konto` is ignored. REST
`/api/2/AccVc` and `/api/2/TRVc` return **401** with this portal account.
The server-side filter without dumping October is Portal report
**Nominal Ledger** `MainRClass` / `MainRn` with `sStartDate`/`sEndDate`,
`f4` = one account, `ObjStr` = object. Two kontos = two calls. Probe
exec **3650** with usercode `API` returned Access denied; exec **3682**
with usercode **N8N** (UserVc `n8n Rest API`) ran the report. Object
codes **LM** = Lokatsioon Muuga (Muuga terminal) and **LS** = Lokatsioon
Sillamäe (BCT Sillamäe): 4106 Oct opening LM 6663,05 + LS 15959,18 =
unfiltered 22622,23. Portal VIVc/IVVc lists ignore object params;
`search=LM` matches vendor names. REST ObjVc/IVVc listfilter is 401.

### Query catalog (from Excellent API examples)

`workflows/standard-books-query-catalog.json` holds typical Portal/REST
examples from https://api-docs.excellent.ee/ adapted for AS DBT company
**2**. Live ids (`purchase_invoices_period`, `sales_invoices_period`,
`gl_nominal_ledger`, paste/search, PDFs, balance, payments/receipts)
are built by `operation=catalog` in **Standard Books — API**. Official
REST samples that dump AccVc/TRVc/VIVc stay `blocked` (this portal
login gets HTTP 401; `filter.Konto` is ignored). The agent tool is
**Standard_Books_Catalog**: pick `catalog_id` + slots; unknown types
must not call Hansa.

## Files

- `dbt-accountant-assistant.backup.json` — snapshot before Word extract (105 nodes).
- `dbt-accountant-assistant.json` — published snapshot after extract, in-place
  return, unknown-user admin notify, Standard Books crash guards, and
  the query catalog (116 nodes).
- `standard-books-api.json` — published snapshot of the Portal + REST
  sub-workflow with REST/Portal list clamps and catalog builder.
- `standard-books-query-catalog.json` — live/blocked query templates
  from Excellent Portal + REST examples.
