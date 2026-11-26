# Docker-окружение CRM (Telegram Mini App)

Стек: **Laravel 13 / PHP 8.5 / PostgreSQL 16 / Redis 7 / Nginx 1.27 / Node 22 (Vite, Mini App frontend)**.

## Сервисы

| Сервис | Контейнер | Порт | Назначение |
|---|---|---|---|
| `app` | crm_app | — | PHP-FPM 8.5 (Laravel API / Filament) |
| `webserver` | crm_nginx | 8080 | Nginx → проксирует в `app:9000` |
| `frontend` | crm_frontend | 5173 | Vite dev-сервер для Telegram Mini App |
| `db` | crm_postgres | — | PostgreSQL 16 |
| `redis` | crm_redis | — | Redis 7 (кэш, сессии, очередь, «паника») |
| `queue` | crm_queue | — | `php artisan queue:work` |
| `scheduler` | crm_scheduler | — | Цикл `schedule:run` (cron для Data Retention и пр.) |

## Быстрый старт

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Приложение: `http://localhost:8080`
Mini App (Vite dev): `http://localhost:5173`

## Полезные команды

```bash
# Логи
docker compose logs -f app
docker compose logs -f webserver

# Composer
docker compose exec app composer install

# Artisan
docker compose exec app php artisan tinker

# Миграции + сиды
docker compose exec app php artisan migrate --seed

# Войти в БД
docker compose exec db psql -U crm -d crm
```

## Что внутри

- `docker/php/Dockerfile` — PHP-FPM 8.5 с `pdo_pgsql`, `redis`, `intl`, `bcmath`, `opcache`, Composer.
- `docker/php/entrypoint.sh` — авто-создание Laravel-проекта, `composer install`, `key:generate`, миграции (если `RUN_MIGRATIONS=true`), права на `storage`.
- `docker/nginx/nginx.conf` + `default.conf` — безопасные заголовки (CSP/X-Frame-Options/Referrer-Policy), gzip, кэш статики, deny для `.git`/`.env`/`storage/exports`.
- `docker/frontend/Dockerfile` — Node 22 + Vite, шаблон `package.json` с `@telegram-apps/sdk`.
- `docker-compose.yml` — healthchecks для БД и Redis, отдельные контейнеры `queue` и `scheduler` (по ТЗ нужен cron для Data Retention Policy).

## Для продакшена

Для запуска в изолированном продакшен-окружении используется манифест `docker-compose.prod.yml`:
- Контейнер `node` исключен, ассеты собираются статически (`npm run build`).
- Конфигурация Nginx переключена на `docker/nginx/prod.conf` без dev-проксирования Vite.
- Порты PostgreSQL и Reverb изолированы во внутренней сети Docker.
- Добавлена поддержка аутентификации Redis по паролю (`REDIS_PASSWORD`).

```bash
# Сборка ассетов
npm run build

# Запуск продакшен-стека
docker compose -f docker-compose.prod.yml up -d --build
```

## Для разработки

В `docker-compose.yml` сервис `node` помечен профилем `dev`. Запуск со сборщиком Vite:

```bash
docker compose --profile dev up -d
```
