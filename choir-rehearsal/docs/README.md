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

**После релиза плагина** достаточно обновить JSON в репозитории на ветке, которую читает сайт (`cursor/youtube-embed-button-c2eb`, после merge — `main`) — **перезагрузка FTP не нужна**.

Оболочка `product-page.html` на FTP должна указывать актуальные ветки в `CONFIG.branches`. Если ветка сменилась — обновите HTML и задеплойте оболочку (workflow ниже).

### Что редактировать при обновлении

1. **`product-data.json`** — текст страницы, changelog (главный файл) → push в ветку из `CONFIG.branches`
2. **`update.json`** — версия для WordPress-updater
3. **`readme.txt`** — changelog для WordPress.org / плагина

Сайт сначала читает ветку `cursor/youtube-embed-button-c2eb`, затем `cursor/rehearsal-copy-main-abc2` / `main`. Версия в hero берётся из **GitHub Releases** (`compath-choir-rehearsal-v*`).

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
| [deploy/api/feedback.php](deploy/api/feedback.php) | Приём отзывов плагина → Jira + письмо. URL: `https://rehearsal.compath.ee/api/feedback.php` |
| [deploy/api/README.md](deploy/api/README.md) | Куда класть скрипт и файл секретов (не в git) |

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

## Отзывы (Jira)

Плагин на сайтах хоров отправляет JSON на `https://rehearsal.compath.ee/api/feedback.php`. Секреты Jira и SMTP **не** входят в плагин и **не** заливаются workflow.

Файл на хостинге: `/domains/rehearsal.compath.ee/public_html/api/feedback.php`

Конфиг (создать вручную, вне webroot): `/domains/rehearsal.compath.ee/private/feedback-config.php`

Образец: [deploy/api/config.example.php](deploy/api/config.example.php). Заполнить `jira_email`, `jira_token`, `smtp_pass`. Подробности в [deploy/api/README.md](deploy/api/README.md).
