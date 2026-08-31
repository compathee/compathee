# Документация Choir Rehearsal

## Файлы

| Файл | Назначение |
|------|------------|
| [product-page.html](product-page.html) | Готовая HTML-страница продукта (описание, цены, установка, changelog) |
| [product-page.md](product-page.md) | Тот же контент в Markdown — для копирования в WordPress |

## Публикация на сайте

### Вариант A — отдельная HTML-страница

1. Загрузите `product-page.html` на хостинг (например `https://veneta.ee/choir-rehearsal/`).
2. Добавьте ссылку в меню сайта.

### Вариант B — страница WordPress

1. Создайте новую страницу (например «Choir Rehearsal»).
2. Скопируйте содержимое из `product-page.md` или вставьте HTML через блок «Произвольный HTML».
3. Укажите URL страницы в настройках плагина (константа `CHOIR_REHEARSAL_DOCS_URL`) или обновите ссылку в Settings.

## Обновление changelog

При каждом релизе обновите:

- `product-page.html` — секция `#changelog` и версию в hero
- `product-page.md` — секция «История изменений»
- `readme.txt` — официальный changelog плагина
