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

## Ошибка 403 Forbidden

Сообщение Apache «You don't have permission to access this resource» почти всегда означает одно из:

### 1. Неверная папка FTP (`REHEARSAL_FTP_REMOTE_DIR`)

Поддомен должен указывать **именно** на ту папку, куда загружаются файлы.

В панели хостинга найдите **Document root** для `rehearsal.compath.ee`.  
Примеры путей (зависит от хостера):

- `/domains/rehearsal.compath.ee/public_html/`
- `/home/user/domains/rehearsal.compath.ee/public_html/`
- `/public_html/rehearsal.compath.ee/`

Значение secret `REHEARSAL_FTP_REMOTE_DIR` должно совпадать с этим путём (часто с `/` в конце).

### 2. Нет `index.html` в корне поддомена

Через FTP-клиент проверьте, что в document root лежат:

- `index.html`
- `.htaccess`
- `health.txt`

Если папка пустая — деплой не сработал (нет secrets, неверный путь, workflow не в `main`).

### 3. Права доступа (самая частая причина 403)

Файлы после FTP часто получают права `600` — Apache не может их читать.

Нужно:

| Объект | Права |
|--------|-------|
| Папка | `755` |
| `index.html`, `.htaccess` | `644` |

В workflow добавлен шаг **Fix remote permissions** — после merge запустите деплой снова.

Вручную в FTP-клиенте: правый клик → File permissions → `644` для файлов, `755` для папки.

### 4. Быстрая проверка

Откройте:

- https://rehearsal.compath.ee/health.txt — должно быть: `Choir Rehearsal product site deploy OK`
- https://rehearsal.compath.ee/ — главная страница

Если `health.txt` открывается, а `/` — нет: проблема в `index.html` или `.htaccess`.  
Если оба 403 — неверная папка или права на каталог.

### 5. Чеклист

1. Workflow `deploy-rehearsal-site.yml` есть в ветке **`main`**
2. Secrets `REHEARSAL_FTP_*` заданы в GitHub
3. **Actions → Deploy rehearsal.compath.ee → Run workflow** — зелёный статус
4. В FTP видны `index.html`, `.htaccess`, `health.txt`
5. Права: папка `755`, файлы `644`
6. Document root поддомена = `REHEARSAL_FTP_REMOTE_DIR`

