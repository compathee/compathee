# Установка api.compath.ee (FTP)

Папка **`deploy/`** — готовый пакет для загрузки в **document root** поддомена `api.compath.ee`.

Обычный путь на compath.ee: `/home/ВАШ_ЛОГИН/api.compath.ee/public_html/`

## 1. Загрузить файлы

Залейте **всё содержимое** папки `deploy/` в `public_html`:

```
public_html/
├── index.php
├── .htaccess
├── health.txt
├── config/
│   ├── config.example.php
│   └── .htaccess
├── data/
│   └── .htaccess
└── src/
    └── ...
```

Можно использовать zip `api-compath-ee-deploy.zip` из корня `api.compath.ee/` — распаковать на сервере.

## 2. Права (chmod)

| Путь | Права |
|------|-------|
| `data/` | **755** или **775** (запись для SQLite) |
| остальные файлы | **644** |
| папки | **755** |

## 3. Конфигурация

1. Скопируйте на сервере: `config/config.example.php` → `config/config.php`
2. Заполните:

```php
'stripe_webhook_secret' => 'whsec_...',  // Stripe → Webhooks → Signing secret
'erply_api_token'       => '...',        // ERPLY Books → Settings → API
```

3. **Не загружайте** `config.php` в git — только на сервер.

## 4. Stripe Webhook

Stripe Dashboard → **Developers → Webhooks → Add endpoint**

| Поле | Значение |
|------|----------|
| URL | `https://api.compath.ee/webhook/stripe` |
| Events | `invoice.paid`, `checkout.session.completed` |

Signing secret вставьте в `config.php`.

## 5. Проверка

| URL | Ожидание |
|-----|----------|
| https://api.compath.ee/health | `ok comppath-hub 2026-...` |
| https://api.compath.ee/health.txt | `ok` |
| POST /webhook/stripe без подписи | HTTP 400 |

## 6. Тест оплаты

1. SureCart test mode → тестовая покупка
2. Stripe Dashboard → Webhooks → выбрать endpoint → **Recent deliveries** → должно быть **200**
3. ERPLY Books → новый **оплаченный** счёт

## Требования PHP

- PHP **8.0+**
- Расширения: `curl`, `json`, `pdo_sqlite`

Проверка: создайте временно `info.php` с `<?php phpinfo();` и откройте в браузере (удалите после проверки).

## Проблемы

| Симптом | Решение |
|---------|---------|
| 403 Forbidden | chmod 644/755, проверьте `index.php` в корне |
| 500 + JSON «Configuration missing» | создайте `config/config.php` |
| 500 на webhook | проверьте `erply_api_token`, логи PHP на хостинге |
| Stripe retry failed | URL должен быть HTTPS, secret должен совпадать |
