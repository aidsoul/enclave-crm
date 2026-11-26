---
name: laravel-horizon-reverb
description: Manage queues, Horizon monitoring, Reverb WebSockets broadcasting, and Redis events inside Docker.
---

# Laravel Horizon & Reverb Operations

Handles asynchronous queues (`crm_queue`, `crm_redis`), scheduler (`crm_scheduler`), and WebSocket broadcasting (`crm_reverb`).

## Queue Workers & Horizon

```bash
# Check Horizon status and metrics
docker exec crm_app php artisan horizon:status

# Restart workers after modifying Job, Mail, or Event classes
docker exec crm_app php artisan queue:restart

# Inspect failed jobs
docker exec crm_app php artisan queue:failed

# Retry failed jobs
docker exec crm_app php artisan queue:retry all
# Retry specific job UUID
docker exec crm_app php artisan queue:retry <uuid>

# Clear all jobs from queue
docker exec crm_app php artisan queue:clear
```

## Reverb (WebSockets)

Reverb runs in the `crm_reverb` container on port 6001.

```bash
# View Reverb real-time server logs
docker logs --tail 100 -f crm_reverb

# Restart Reverb container if connection drops or configuration changes
docker restart crm_reverb
```

### Client-side Connection (`resources/js/echo-init.js`)

- `pusher-js` is bundled via npm into Vite assets (never loaded from CDN).
- Pusher client requires `cluster` configuration: `cluster: import.meta.env.VITE_REVERB_APP_CLUSTER || 'mt1'`.
- Channel authorization endpoint: `/broadcasting/auth` (protected by `['api', 'tma.auth']` middleware in `BroadcastServiceProvider`).
- Authorization headers pass the TMA session token: `headers['Authorization'] = 'Bearer ' + token` and `headers['X-Session-Token'] = token`.

## Scheduler & Redis

```bash
# Inspect scheduler output
docker logs --tail 100 crm_scheduler

# Test scheduled list
docker exec crm_app php artisan schedule:list

# Redis health and CLI
docker exec crm_redis redis-cli ping
docker exec crm_redis redis-cli info memory
```
