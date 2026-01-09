# Инструкция по деплою Telegram бота

## Шаги после загрузки на сервер

### 1. Установка зависимостей
```bash
composer install --no-dev --optimize-autoloader
```

### 2. Настройка .env файла

Создайте `.env` файл (скопируйте из `.env.example` если есть):
```bash
cp .env.example .env
php artisan key:generate
```

**Обязательные настройки в `.env`:**
```env
# Приложение
APP_NAME="Telegram AI Bot"
APP_ENV=production
APP_KEY=base64:... (генерируется автоматически)
APP_DEBUG=false
APP_URL=https://yourdomain.com

# База данных
DB_CONNECTION=pgsql  # или mysql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Telegram Bot
TELEGRAM_BOT_TOKEN=8396945717:AAEh7_dwSPrQRN-cJVfjUEgv39gjyDq5oS4

# Fal.ai (если будете использовать)
FAL_KEY=your_fal_key
```

### 3. Выполнение миграций
```bash
php artisan migrate --force
```

Это создаст таблицы:
- `telegram_users` (с полями chat_id, balance, pending_action и т.д.)
- `jobs` (для очередей, если будете использовать)

### 4. Установка webhook

**Важно:** Замените `https://yourdomain.com` на ваш реальный домен!

```bash
php artisan telegram:set-webhook https://yourdomain.com/api/telegram/webhook
```

Или вручную через curl:
```bash
curl -X POST "https://api.telegram.org/bot8396945717:AAEh7_dwSPrQRN-cJVfjUEgv39gjyDq5oS4/setWebhook?url=https://yourdomain.com/api/telegram/webhook"
```

### 5. Настройка веб-сервера

#### Для Nginx:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/telegramAi/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Для Apache:
Убедитесь, что `.htaccess` в папке `public/` настроен правильно.

### 6. Права доступа
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. Оптимизация (опционально, но рекомендуется)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Проверка работы

1. Откройте бота в Telegram: `t.me/UPutiAiBot`
2. Отправьте `/start`
3. Должны появиться кнопки

### 9. Мониторинг логов
```bash
# Просмотр логов Laravel
tail -f storage/logs/laravel.log

# Просмотр логов веб-сервера
tail -f /var/log/nginx/error.log  # для Nginx
```

## Дополнительно: Очереди (если будете использовать)

Если будете генерировать видео/изображения, нужно запустить worker:

```bash
# В фоне или через supervisor
php artisan queue:work --tries=3
```

Или через Supervisor (рекомендуется для production):

```ini
[program:telegram-ai-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/telegramAi/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/telegramAi/storage/logs/queue.log
stopwaitsecs=3600
```

## Проверка webhook

Проверить, что webhook установлен:
```bash
curl "https://api.telegram.org/bot8396945717:AAEh7_dwSPrQRN-cJVfjUEgv39gjyDq5oS4/getWebhookInfo"
```

Должен вернуть:
```json
{
  "ok": true,
  "result": {
    "url": "https://yourdomain.com/api/telegram/webhook",
    "has_custom_certificate": false,
    "pending_update_count": 0
  }
}
```

## Troubleshooting

### Бот не отвечает:
1. Проверьте логи: `tail -f storage/logs/laravel.log`
2. Проверьте webhook: `curl "https://api.telegram.org/botTOKEN/getWebhookInfo"`
3. Проверьте доступность URL: `curl https://yourdomain.com/api/telegram/webhook`
4. Убедитесь, что SSL сертификат валидный (Telegram требует HTTPS)

### Ошибки БД:
1. Проверьте подключение к БД в `.env`
2. Убедитесь, что миграции выполнены: `php artisan migrate:status`

### 500 ошибки:
1. Проверьте права: `chmod -R 755 storage bootstrap/cache`
2. Проверьте логи: `tail -f storage/logs/laravel.log`
3. Временно включите `APP_DEBUG=true` для детальных ошибок
