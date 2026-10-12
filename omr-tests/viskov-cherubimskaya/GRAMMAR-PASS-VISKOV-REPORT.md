# Grammar pass v2 — Висков (исправление ошибок v1)

## Что было не так в v1

1. **Знаки при ключе** не выравнивались по всем линейкам системы — Audiveris часто писал ключ только на P1; MuseScore рисовал «дыры» (как на вашем скрине).
2. **A♭→A♮** — ошибочное hard-правило («вне звукоряда ⇒ переписать»). Это отсебятина: A♭ в g-moll может быть хроматикой (II♭ / неаполитанский и т.п.). В v2 такие ноты только **review**, высота не меняется.

## Политика v2

| Правило | Действие |
|---|---|
| Знаки при ключе на одной линейке системы | **Те же fifths на всех** линейках, соединённых тактовыми чертами |
| B♯ при си♭ в ключе и т.п. | **hard** force |
| A♭ / прочая хроматика вне диатоники | **review**, pitch не трогаем |

| | |
|---|---|
| Mode | **minor** (fifths=-2) |
| Key fixes | **13** |
| Pitch kept | 334 |
| Pitch forced | **0** |
| Pitch review | 6 |
| Wall | 0.0132 s |
| LLM | **0** |

## Key fixes (фрагмент)

| М. | Part | Was | → | Action | Rule |
|---|---|---|---|---|---|
| 17 | P1 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 1 | P2 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 1 | P3 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 2 | P3 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 3 | P3 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 1 | P4 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 2 | P4 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 3 | P4 | None | -2 | insert | `pad_missing_measure_for_system_align` |
| 2 | P1 | 0 | -2 | replace | `reject_spurious_key_flip_not_on_all_staves` |
| 2 | P2 | 0 | -2 | replace | `reject_spurious_key_flip_not_on_all_staves` |
| 17 | P2 | None | -2 | insert | `system_key_must_match_all_barline_staves` |
| 17 | P3 | None | -2 | insert | `system_key_must_match_all_barline_staves` |
| 17 | P4 | None | -2 | insert | `system_key_must_match_all_barline_staves` |

## Pitch forced (только hard)

_Нет hard-правок высоты (B♯ и т.п. не встретились)._

## Pitch review (хроматика, без переписывания)

| Part | М. | Pitch | Rule |
|---|---|---|---|
| P2 | 12 | A♭ | `chromatic_or_unexpected_alter=-1_step=A_fifths=-2_mode=minor` |
| P2 | 14 | A♭ | `chromatic_or_unexpected_alter=-1_step=A_fifths=-2_mode=minor` |
| P4 | 13 | A♭ | `chromatic_or_unexpected_alter=-1_step=A_fifths=-2_mode=minor` |
| P4 | 13 | A♭ | `chromatic_or_unexpected_alter=-1_step=A_fifths=-2_mode=minor` |
| P4 | 14 | A♭ | `chromatic_or_unexpected_alter=-1_step=A_fifths=-2_mode=minor` |
| P4 | 14 | A♭ | `chromatic_or_unexpected_alter=-1_step=A_fifths=-2_mode=minor` |

## Артефакты

- `A.Viskov-Cherubimskaya.grammar-corrected.musicxml`
- `grammar-pass-viskov.json`

