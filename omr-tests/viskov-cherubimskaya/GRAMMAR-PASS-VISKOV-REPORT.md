# Grammar pass — Висков, Херувимская (Layer D+)

Источник: `A.Viskov-Cherubimskaya.mxl` → правила `tonal_rules_onthefly.py` + in-process Redis memory.

| | |
|---|---|
| Primary fifths | **-2** (hist {-2: 11, 0: 2}) |
| Mode | **minor** (histogram fit) |
| Pitched notes | 340 |
| Kept | 334 |
| Forced corrections | **6** |
| Memory entries | 2 (hits sum 6) |
| Wall | 0.0199 s |
| Peak RSS | 23.75 MB |
| LLM tokens | **0 / 0** (€0) |

## По правилам

- `in_allowed_set`: 334
- `redis_memory_prefer_alter=0`: 4
- `alter_-1_not_in_allowed_[0]_→0`: 2

## Severity

- soft: 338
- hard: 2

## Примеры правок (до 25)

| Part | М. | In | Out | Severity | Rule |
|---|---|---|---|---|---|
| P2 | 12 | A♭ | A♮ | hard | `alter_-1_not_in_allowed_[0]_→0` |
| P2 | 14 | A♭ | A♮ | soft | `redis_memory_prefer_alter=0` |
| P4 | 13 | A♭ | A♮ | hard | `alter_-1_not_in_allowed_[0]_→0` |
| P4 | 13 | A♭ | A♮ | soft | `redis_memory_prefer_alter=0` |
| P4 | 14 | A♭ | A♮ | soft | `redis_memory_prefer_alter=0` |
| P4 | 14 | A♭ | A♮ | soft | `redis_memory_prefer_alter=0` |

## Артефакты

- Corrected MusicXML: `A.Viskov-Cherubimskaya.grammar-corrected.musicxml`
- Full JSON: `grammar-pass-viskov.json`

## Вывод

Grammar-pass изменил **6** гипотез Audiveris из 340 pitched (режим minor, fifths=-2). Память подсказала повторные контексты без LLM.
