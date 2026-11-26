---
name: telegram-bot
description: Debug, test, and manage Nutgram Telegram bot, webhooks, mini app authentication, and notifications in the CRM.
---

# Telegram Bot & TMA Integration

The CRM uses Nutgram (`nutgram/nutgram`) for Telegram bot interactions and Telegram Mini App (TMA) for operator web interfaces.

## Webhook Commands

```bash
# View webhook status and error details
docker exec crm_app php artisan telegram:webhook-info

# Set webhook URL
docker exec crm_app php artisan telegram:set-webhook https://<domain>/api/telegram/webhook

# Delete webhook (e.g. for local long-polling debug)
docker exec crm_app php artisan telegram:delete-webhook
```

## Relevant Files

- Nutgram handler registration: [TelegramServiceProvider.php](file:///home/admin/projects/crm/app/Providers/TelegramServiceProvider.php)
- Telegram handlers: [app/Http/Telegram/Handlers/](file:///home/admin/projects/crm/app/Http/Telegram/Handlers/) ([OrderInlineQuery.php](file:///home/admin/projects/crm/app/Http/Telegram/Handlers/OrderInlineQuery.php), [OrderCallbacks.php](file:///home/admin/projects/crm/app/Http/Telegram/Handlers/OrderCallbacks.php), [SessionSecurityCallback.php](file:///home/admin/projects/crm/app/Http/Telegram/Handlers/SessionSecurityCallback.php))
- Bot webhook controller: [TelegramController.php](file:///home/admin/projects/crm/app/Http/Controllers/TelegramController.php)
- Telegram services: [app/Services/Telegram](file:///home/admin/projects/crm/app/Services/Telegram/) and [TelegramService.php](file:///home/admin/projects/crm/app/Services/TelegramService.php)
- TMA authentication & sessions: [AuthController.php](file:///home/admin/projects/crm/app/Http/Controllers/AuthController.php) (`/api/auth/tma`), [CloudPasswordController.php](file:///home/admin/projects/crm/app/Http/Controllers/Auth/CloudPasswordController.php), [SessionSecurityController.php](file:///home/admin/projects/crm/app/Http/Controllers/Auth/SessionSecurityController.php)
- Session security service: [SessionSecurityService.php](file:///home/admin/projects/crm/app/Services/Security/SessionSecurityService.php)
- TMA shared base & routing: [shared.js](file:///home/admin/projects/crm/resources/js/tma/shared.js)

## Inline Mode & Deep Linking (`startapp`)

- **BotFather setup**: Inline mode must be explicitly enabled for the bot via `@BotFather` (`/setinline`), otherwise `switchInlineQuery` will fail.
- **Buttons in inline messages**: Telegram Bot API rejects `web_app` buttons in messages sent via inline queries to external chats/groups. Always use standard `url` buttons with direct Mini App links: `https://t.me/<bot_username>?startapp=order_<id>`.
- **TMA routing**: TMA extracts `start_param` from `initData` / `initDataUnsafe` in `baseInit()`. If it matches `order_<id>`, it automatically navigates to `/tma/orders/<id>`.
- **Nutgram answerInlineQuery**: Requires named or positional arguments (`results: $results, cache_time: 5, is_personal: true`). Do not pass an associative array as the second argument.

## Diagnostics

```bash
# Check queue worker logs for notification delivery issues
docker logs --tail 100 crm_queue

# Search for Telegram exceptions in application logs
docker exec crm_app grep -i "telegram" storage/logs/laravel.log | tail -n 50
```
