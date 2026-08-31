# Документация Choir Rehearsal

Публичный сайт продукта: **[rehearsal.compath.ee](https://rehearsal.compath.ee)**

## Файлы

| Файл | Назначение |
|------|------------|
| [product-page.html](product-page.html) | Готовая HTML-страница — загрузить на поддомен |
| [product-page.md](product-page.md) | Markdown-копия для WordPress |

## Публикация на rehearsal.compath.ee

1. Создайте поддомен `rehearsal.compath.ee` у хостинг-провайдера.
2. Загрузите `product-page.html` как `index.html` в корень поддомена.
3. Проверьте: https://rehearsal.compath.ee/
4. В плагине URL уже задан: `CHOIR_REHEARSAL_DOCS_URL` → `https://rehearsal.compath.ee/`

### Альтернатива — WordPress на поддомене

1. Установите WordPress на `rehearsal.compath.ee`.
2. Создайте страницу «Главная» с содержимым из `product-page.md` или HTML-блоком.
3. Настройки → Чтение → статическая главная.

## Обновление changelog

При каждом релизе обновите:

- `product-page.html` — секция `#changelog` и версию в hero
- `product-page.md` — секция «История изменений»
- `readme.txt` — официальный changelog плагина
- Загрузите обновлённый `index.html` на поддомен
