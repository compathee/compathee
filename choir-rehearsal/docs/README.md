# Документация Choir Rehearsal

Публичный сайт продукта: **[rehearsal.compath.ee](https://rehearsal.compath.ee)**

## Как работает сайт (динамический контент)

Страница **не хранит текст на сервере** — на FTP лежит только оболочка `index.html` (~15 KB).

При открытии сайта браузер загружает из GitHub:

| Файл | Содержимое |
|------|------------|
| `docs/product-data.json` | Описание, цены, changelog, инструкции |
| `update.json` | Текущая версия и ссылка на zip |
| GitHub Releases API | Резервная ссылка «Download» |

**После релиза плагина** достаточно обновить JSON в репозитории и смержить в `main` — **перезагрузка FTP не нужна**.

### Что редактировать при обновлении

1. **`product-data.json`** — текст страницы, changelog (главный файл)
2. **`update.json`** — версия для WordPress-updater
3. **`readme.txt`** — changelog для WordPress.org / плагина

### Однократный деплой оболочки

FTP нужен только когда меняется сам `product-page.html` (дизайн, JS):

**Actions → Deploy rehearsal.compath.ee → Run workflow**

Или push с изменением `product-page.html`.

## Альтернатива: GitHub Pages

Можно вообще убрать FTP:

1. Settings → Pages → Source: branch `main`, folder `/docs` или `/choir-rehearsal/docs`
2. DNS: `rehearsal.compath.ee` CNAME → `compathee.github.io`
3. Файл `CNAME` в корне Pages с содержимым `rehearsal.compath.ee`

Тогда сайт обновляется при каждом push в `main` без FTP.

## Файлы

| Файл | Назначение |
|------|------------|
| [lite-pro-updates.md](lite-pro-updates.md) | Lite install, Buy Pro, Lite→Pro add-on, GitHub auto-update |
| [product-page.html](product-page.html) | Оболочка (JS) — загрузить как `index.html` **один раз** |
| [product-data.json](product-data.json) | **Контент страницы** — редактировать при каждом релизе |
| [product-page.md](product-page.md) | Markdown-копия (справочно) |
| [deploy/.htaccess](deploy/.htaccess) | Apache: index + права |

## FTP secrets (один раз)

| Secret | Пример |
|--------|--------|
| `REHEARSAL_FTP_SERVER` | `ftp.compath.ee` |
| `REHEARSAL_FTP_USERNAME` | логin FTP |
| `REHEARSAL_FTP_PASSWORD` | пароль |
| `REHEARSAL_FTP_REMOTE_DIR` | `/domains/rehearsal.compath.ee/public_html/` |

## Ошибка 403 Forbidden

См. предыдущий чеклист: document root, `index.html`, права 644/755.

Проверка: https://rehearsal.compath.ee/health.txt
