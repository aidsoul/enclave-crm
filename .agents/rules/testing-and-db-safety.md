---
trigger: always_on
description: Strict safety rules forbidding destructive database commands and dangerous tests without user approval
---

# Testing & Database Safety Rules

- **Dangerous Tests**: Strictly forbidden to run `RestoreBackupCommandTest`, `EmergencyWipe*`, `DataRetention*`, or any tests executing `DROP SCHEMA`, `TRUNCATE`, or database wipes without explicit prior user approval.
- **Targeted Testing**: Only run focused test suites verifying the specific code changes (e.g., `php artisan test tests/.../SpecificTest.php`).
- **Database Protection**: Destructive commands (`backup:restore`, `migrate:fresh`, `db:wipe`, `migrate:reset`) are strictly forbidden without explicit user confirmation.
- **No `php -r` for Artisan**: Never invoke destructive console commands outside the proper test environment framework.
