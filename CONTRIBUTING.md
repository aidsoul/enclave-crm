# Руководство по разработке (Contributing Guide)

## Требования к окружению

Все процессы сборки, миграций и тестов выполняются внутри Docker-контейнеров:
- `crm_app` — PHP-FPM приложение
- `crm_nginx` — Web-сервер (порт `8080`)
- `crm_postgres` — PostgreSQL 17
- `crm_redis` — Redis
- `crm_queue` — Воркер очередей
- `crm_reverb` — WebSocket сервер (порт `6001`)
- `crm_node` — Сборщик Vite/Tailwind (порт `5173`)

---

## Запуск тестов и линтеров

### 1. Запуск тестов (PHPUnit)
```bash
docker exec crm_app php artisan test
```

### 2. Статический анализ (Larastan / PHPStan)
```bash
docker exec crm_app ./vendor/bin/phpstan analyse
```

### 3. Форматирование кода (Laravel Pint)
```bash
# Проверка стиля кода
docker exec crm_app ./vendor/bin/pint --test

# Автоматическое исправление стиля
docker exec crm_app ./vendor/bin/pint
```

---

## Стандарты кода

1. **Пагинация API:**
   Всегда ограничивать размер выборки (`per_page`) в пределах от 1 до 100:
   ```php
   $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
   ```

2. **Модальные окна в TMA:**
   Никогда не использовать нативные алерты Telegram (`Telegram.WebApp.showPopup`). Всегда использовать компонент `@component('tma.partials.modal')` с реактивным состоянием на Alpine.js.

3. **Безопасность:**
   Никогда не логировать и не возвращать в публичные API токены ботов, секреты или данные авторизации.

---

## Ветвление и слияние (Git Workflow)

1. **Ветвление:**
   - Новые функции: ветка `feat/...` (например, `feat/orders-export`).
   - Исправления багов: ветка `fix/...` (например, `fix/courier-cash`).
   - Прямые коммиты в `main` запрещены.

2. **Слияние через Squash (для компактной и чистой истории):**
   При слиянии ветки в `main` все промежуточные коммиты схлопываются в один:
   ```bash
   # Переход в main и схлопывание коммитов
   git switch main
   git merge --squash feat/my-feature

   # Создание одного чистого коммита в main
   git commit -m "feat(orders): add export orders feature"

   # Удаление рабочей ветки
   git branch -D feat/my-feature
   ```

3. **Выпуск версий (SemVer):**
   - Версии фиксируются аннотированными Git-тегами (`vMAJOR.MINOR.PATCH`):
   ```bash
   git tag -a v1.0.1 -m "Fix courier cash calculation"
   git push origin v1.0.1
   ```

