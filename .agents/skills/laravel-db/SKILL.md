---
name: laravel-db
description: Safe database operations, PostgreSQL queries, migrations, seeders, and factories for CRM data.
---

# Laravel Database & Migrations

Primary database is PostgreSQL 17 running in `crm_postgres`. All Artisan commands run inside `crm_app`.

## Safety Guidelines

- **NEVER** run `migrate:fresh`, `migrate:reset`, or `db:wipe` without explicit confirmation.
- Prefer non-destructive forward migrations with safe rollbacks.
- Always protect API pagination: clamp `per_page` to `[1, 100]` (`min(max((int) $request->query('per_page', $default), 1), 100)`) to prevent memory exhaustion and database overload.

## Migrations

```bash
# Check current migration status
docker exec crm_app php artisan migrate:status

# Run pending migrations safely
docker exec crm_app php artisan migrate --force

# Rollback the last migration batch
docker exec crm_app php artisan migrate:rollback

# Create a new migration
docker exec crm_app php artisan make:migration <migration_name>
```

## Seeders and Factories

```bash
# Run specific seeder
docker exec crm_app php artisan db:seed --class=<SeederName>

# Tinker exploration
docker exec -it crm_app php artisan tinker
```

## Direct PostgreSQL Access

```bash
# Run read-only query or check tables
docker exec crm_postgres psql -U crm -d crm -c "\dt"

# Check active connections
docker exec crm_postgres psql -U crm -d crm -c "SELECT pid, usename, state, query FROM pg_stat_activity;"
```
