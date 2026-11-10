# ClientFlow — backend

Laravel 13 REST API with Sanctum SPA authentication and MySQL. Setup, commands and
architecture are documented in the [root README](../README.md); endpoints in
[`docs/API.md`](../docs/API.md).

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve --host=localhost --port=8000
```
