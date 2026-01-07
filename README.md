<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Балансы и транзакции (HTTP API)

### Эндпоинты
- POST `/api/deposit`
  - Тело: `{ "user_id": 1, "amount": 500.00, "comment": "Пополнение через карту" }`
  - 200: `{ "user_id": 1, "balance": 500.00 }`
  - 404: `User not found`

- POST `/api/withdraw`
  - Тело: `{ "user_id": 1, "amount": 200.00, "comment": "Покупка подписки" }`
  - 200: `{ "user_id": 1, "balance": 300.00 }`
  - 404: `User not found`
  - 409: `Insufficient funds`

- POST `/api/transfer`
  - Тело: `{ "from_user_id": 1, "to_user_id": 2, "amount": 150.00, "comment": "Перевод другу" }`
  - 200: `{ "from_user_id": 1, "to_user_id": 2, "amount": 150.0, "from_balance": 150.0, "to_balance": 150.0 }`
  - 404: `User not found`
  - 409: `Insufficient funds`
  - 422: `Cannot transfer to the same user`

- GET `/api/balance/{user_id}`
  - 200: `{ "user_id": 1, "balance": 350.00 }`
  - 404: `User not found`

Статусы транзакций: `deposit`, `withdraw`, `transfer_in`, `transfer_out`.

### Запуск в Docker
Требуется Docker и Docker Compose. Предпочтительный способ локального запуска — через Docker и проверка через `localhost` [[memory:3110816]].

1. Скопируйте `.env.example` в `.env` и укажите PostgreSQL параметры, например:
   - `DB_CONNECTION=pgsql`
   - `DB_HOST=laravel_test_db`
   - `DB_PORT=5432`
   - `DB_DATABASE=laravel_test`
   - `DB_USERNAME=postgres`
   - `DB_PASSWORD=secret`
   - `APP_HTTP_PORT=8876`

2. Старт:
```
docker-compose up --build -d
```

3. Выполните миграции и сиды внутри контейнера приложения:
```
docker exec -it laravel_test_app php artisan migrate --force
docker exec -it laravel_test_app php artisan db:seed --force
```

4. Приложение будет доступно на `http://localhost:8876`.

#### Тестовые пользователи
- Сидер создаёт 10 пользователей: `user1@example.com` ... `user10@example.com` с паролем `password`.

#### Примеры запросов (curl)
Пополнение:
```
curl -X POST http://localhost:8876/api/deposit \
  -H 'Content-Type: application/json' \
  -d '{"user_id":1, "amount":100.50, "comment":"topup"}'
```

Снятие:
```
curl -X POST http://localhost:8876/api/withdraw \
  -H 'Content-Type: application/json' \
  -d '{"user_id":1, "amount":50, "comment":"purchase"}'
```

Перевод:
```
curl -X POST http://localhost:8876/api/transfer \
  -H 'Content-Type: application/json' \
  -d '{"from_user_id":1, "to_user_id":2, "amount":25, "comment":"friend"}'
```

Проверка баланса:
```
curl http://localhost:8876/api/balance/1
```

### Примечания
- Все денежные операции выполняются в транзакциях базы данных.
- Баланс не может уходить в минус.
- Если записи о балансе нет — она создаётся при первом пополнении/операции.
- Все ответы и ошибки в формате JSON с корректными HTTP-кодами.

### Локальный запуск без Docker (SQLite)
По умолчанию в `config/database.php` указана `DB_CONNECTION=sqlite`. Можно быстро запустить проект без БД-контейнера:

```
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=0.0.0.0 --port=8876
```

После этого API будет доступно на `http://localhost:8876`.

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
