# Документация Choir Rehearsal

Публичный сайт продукта: **[rehearsal.compath.ee](https://rehearsal.compath.ee)**

## Как работает сайт

Текст каждой языковой страницы лежит в HTML. Скрипт `build-site.mjs` собирает страницы из `product-data.json` и `seo-copy.json`.

| URL | Файл |
|-----|------|
| `/` | английский `index.html` |
| `/et/` `/ru/` `/de/` `/fr/` `/it/` `/es/` `/sv/` `/fi/` | `index.html` в папке языка |
| `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/llms-full.txt` | для поисковых и AI-краулеров |

`?lang=xx` перенаправляет на чистый адрес. Автовыбор языка по браузеру делает только JavaScript на `/`, и только если посетитель ещё не выбрал английский. Краулеры JavaScript не выполняют и остаются на запрошенном URL.

Значок версии Lite может обновиться из `update.json` на jsDelivr. Остальной текст меняется только после пересборки и загрузки HTML.

### Что редактировать при обновлении

1. **`product-data.json`** и при необходимости **`seo-copy.json`**
2. `node choir-rehearsal/docs/build-site.mjs`
3. Загрузить содержимое `choir-rehearsal/docs/dist/` в корень сайта. Папку `api/` не трогать.
4. **`update.json`** — версия для WordPress-updater
5. **`readme.txt`** — changelog для WordPress.org / плагина

### Деплой

**Actions → Deploy rehearsal.compath.ee → Run workflow**

Workflow сам запускает сборку и выкладывает только allow-list. `api/` не копируется.

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
| [deploy/favicon.ico](deploy/favicon.ico) и соседние PNG/SVG | Значок вкладки. В корень FTP рядом с `index.html`. `api/` не выкладывать. |
| [deploy/compath-logo.png](deploy/compath-logo.png) | Логотип Compath в шапке, ссылка на compath.ee. В корень FTP как `/compath-logo.png`. |

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
