# compathee

## n8n workflows

Workflow backups and deployable n8n JSON files are stored in
[`workflows/`](workflows/).

## Excellent Books Excel report combiner

Use `scripts/combine_excellent_reports.py` to combine Standard/Excellent Books
Excel exports into one normalized report without using the API.

Install dependencies:

```bash
python -m pip install -r requirements.txt
```

Example with all `.xlsx` files in a folder:

```bash
python scripts/combine_excellent_reports.py \
  --input-dir excellent-exports \
  --output output/excellent-combined-report.xlsx
```

Example with specific files:

```bash
python scripts/combine_excellent_reports.py \
  --file "excellent-exports/Eelarve - 2027 var 2026.09.10.xlsx" \
  --file "excellent-exports/Kinnisvara.xlsx" \
  --file "excellent-exports/Korterühistud.xlsx" \
  --file "excellent-exports/Lepingud.xlsx" \
  --output output/excellent-combined-report.xlsx
```

By default, object columns are detected by the `HK_` prefix and converted into
rows:

```text
Account | Name    | HK_NOMME | HK_KESKLINN
4000    | Revenue | 100      | 200
```

becomes:

```text
source_file | sheet | source_row | object_code | object_name | amount | Account | Name
Kinnisvara.xlsx | Report | 2 | HK_NOMME |  | 100 | 4000 | Revenue
Kinnisvara.xlsx | Report | 2 | HK_KESKLINN |  | 200 | 4000 | Revenue
```

Optional object name lookup:

```csv
object_code,object_name
HK_NOMME,Nomme LOV
HK_KESKLINN,Kesklinn LOV
```

Run with:

```bash
python scripts/combine_excellent_reports.py \
  --input-dir excellent-exports \
  --objects excellent-objects.csv \
  --output output/excellent-combined-report.xlsx
```

For object codes with a different prefix, repeat `--object-prefix`, for example:

```bash
python scripts/combine_excellent_reports.py \
  --input-dir excellent-exports \
  --object-prefix HK_ \
  --object-prefix KP_ \
  --output output/excellent-combined-report.xlsx
```
