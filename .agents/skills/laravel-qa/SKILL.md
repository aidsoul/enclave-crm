---
name: laravel-qa
description: Run static analysis with Larastan/PHPStan, code styling with Laravel Pint, and tests with PHPUnit inside Docker.
---

# Laravel QA & Code Quality

All quality checks execute inside the `crm_app` container.

## Static Analysis (Larastan / PHPStan)

Configuration is located at `phpstan.neon`.

```bash
# Run full static analysis
docker exec crm_app ./vendor/bin/phpstan analyse --memory-limit=1G

# Run analysis on a specific file or directory
docker exec crm_app ./vendor/bin/phpstan analyse app/Services --memory-limit=1G
```

## Code Style (Laravel Pint)

```bash
# Check code style without modifying files
docker exec crm_app ./vendor/bin/pint --test

# Fix code style in modified files only
docker exec crm_app ./vendor/bin/pint --dirty

# Fix code style in specific paths or entire codebase
docker exec crm_app ./vendor/bin/pint app/
```

## Testing (PHPUnit)

```bash
# Run all tests
docker exec crm_app php artisan test

# Run a specific test class
docker exec crm_app php artisan test --filter=OrderTest

# Run unit or feature tests
docker exec crm_app php artisan test --testsuite=Unit
docker exec crm_app php artisan test --testsuite=Feature
```
