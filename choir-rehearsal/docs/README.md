# Документация Choir Rehearsal

Публичный сайт продукта: **[rehearsal.compath.ee](https://rehearsal.compath.ee)**

## Файлы

| Файл | Назначение |
|------|------------|
| [product-page.html](product-page.html) | Готовая HTML-страница — загружается на поддомен как `index.html` |
| [product-page.md](product-page.md) | Markdown-копия для WordPress |

## Автодеплой через GitHub Release

Workflow: [`.github/workflows/deploy-rehearsal-site.yml`](../../.github/workflows/deploy-rehearsal-site.yml)

При публикации релиза с тегом `choir-rehearsal-v*` (например `choir-rehearsal-v0.3.9`) GitHub Actions:

1. Берёт `choir-rehearsal/docs/product-page.html`
2. Подставляет номер версии из тега
3. Загружает как `index.html` на **rehearsal.compath.ee** по FTP/FTPS

### Настройка (один раз)

В репозитории GitHub: **Settings → Secrets and variables → Actions → New repository secret**

| Secret | Пример | Описание |
|--------|--------|----------|
| `REHEARSAL_FTP_SERVER` | `ftp.compath.ee` | FTP/SFTP-сервер хостинга |
| `REHEARSAL_FTP_USERNAME` | `rehearsal` | Логин FTP |
| `REHEARSAL_FTP_PASSWORD` | `••••••` | Пароль FTP |
| `REHEARSAL_FTP_REMOTE_DIR` | `/public_html/` | Папка поддомена на сервере |

Уточните путь у хостера — часто это `/domains/rehearsal.compath.ee/public_html/` или `/rehearsal.compath.ee/public_html/`.

### SFTP вместо FTPS

Если хостинг даёт только SFTP, в workflow замените:

```yaml
protocol: sftp
port: 22
```

### Ручной деплой

**Actions → Deploy rehearsal.compath.ee → Run workflow** — без нового релиза, версия берётся из `choir-rehearsal.php`.

### Что обновлять перед релизом

- `product-page.html` — changelog и текст
- `readme.txt` — changelog плагина
- Версия в `choir-rehearsal.php` и `update.json`

Номер версии на странице подставится автоматически из тега релиза.

## Ручная публикация

1. Создайте поддомен `rehearsal.compath.ee` у хостинг-провайдера.
2. Загрузите `product-page.html` как `index.html` в корень поддомена.
3. Проверьте: https://rehearsal.compath.ee/

## Обновление changelog

При каждом релизе обновите:

- `product-page.html` — секция `#changelog`
- `product-page.md` — секция «История изменений»
- `readme.txt` — официальный changelog плагина

При настроенном автодеплое загрузка `index.html` не нужна — это делает GitHub Actions.
