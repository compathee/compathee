# Преимущества продукта: Compath OMR / Pro+AI

Копирайт для shop.compath.ee, Partner page, rehearsal.compath.ee (EN/ET — перевести при публикации). Источник: WP-22 §3.18.0, WP-23.

## Bullet list (RU)

* **Распознавание нот (OMR)** — загрузка PDF/скана, на выходе MusicXML / `.mxl` для MuseScore и веб-плееров.
* **Починка станов до распознавания** — всегда 5 линеек; недостающие достраиваются шаблоном на рабочей копии, затем Audiveris.
* **Ключ и размер на всю систему** — новые знаки/размер хотя бы на одной линейке → на все, связанные тактовой чертой; конфликт → на проверку.
* **Сетка написанных нот** — проверка суммы длительностей голоса против размера такта (не «звучание», а нотация).
* **Нотная грамота на лету** — hard-запреты (напр. си♯ при си♭ в ключе); хроматика без отсебятины (A♭ не переписываем в A♮ автоматически).
* **Память (Redis)** — key/meter state и подтверждённые правки для следующих прогонов.
* **Темп** — из партитуры для MIDI/MusicXML playback или ручной ввод, если не указан.
* **Знакомый биллинг** — SureCart как у Pro; Partner API без обязательного WordPress.

Канон: `choir-rehearsal/docs/superpowers/specs/2026-10-12-omr-staff-tonal-pipeline-design.md`.

## What we do **not** sell

Отдельный публичный бесплатный «учебник гармонии как JSON API для всех» — не продукт. Правила грамоты входят в лицензированный OMR / Pro+AI.

## Short EN blurb

Compath turns choir score scans into MusicXML — then applies live music-literacy rules and learned corrections so pitches make sense in the key, not just as OCR guesses. Same SureCart licensing as Pro; Partner API for third-party apps.
