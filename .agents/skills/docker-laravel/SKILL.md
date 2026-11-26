---
name: docker-laravel
description: Run Artisan commands, migrations, manage queues, inspect container logs, and clear caches in Docker for the CRM.
---

# Docker Laravel Operations

All PHP, Composer, and Artisan commands execute inside the `crm_app` container.

## Standard Execution

```bash
# Artisan commands
docker exec crm_app php artisan <command>

# Composer commands
docker exec crm_app composer <command>
```

## Cache and Optimization

When configuration, routes, or code changes are not taking effect:

```bash
docker exec crm_app php artisan optimize:clear
```

## Queue Operations

Queue jobs run in the `crm_queue` container via Redis.

```bash
# Restart queue workers after updating job classes or services
docker exec crm_app php artisan queue:restart

# Inspect failed jobs
docker exec crm_app php artisan queue:failed

# Retry failed jobs
docker exec crm_app php artisan queue:retry all
```

## Database Migrations

```bash
# Check migration status
docker exec crm_app php artisan migrate:status

# Run pending migrations
docker exec crm_app php artisan migrate --force

# Rollback last batch
docker exec crm_app php artisan migrate:rollback
```

Never run `migrate:fresh`, `migrate:reset`, or `db:wipe` without explicit user instruction.

## Container Logs

```bash
# Web/API app logs
docker logs --tail 100 crm_app

# Queue worker logs
docker logs --tail 100 crm_queue

# Nginx webserver logs
docker logs --tail 100 crm_nginx

# Application log file
tail -n 100 storage/logs/laravel.log
```
