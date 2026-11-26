---
name: crm-security
description: Architecture, guidelines, and commands for TMA session security, GeoIP resolution, 2FA Cloud Password, and Anti-Fraud telemetry.
---

# CRM Security & 2FA Architecture

This document outlines the security architecture of Enclave CRM, including Telegram Mini App session verification, two-factor cloud passwords, IP tracking, and anti-fraud telemetry.

## Key Services and Controllers

- **Session Security Service**: [SessionSecurityService.php](file:///home/admin/projects/crm/app/Services/Security/SessionSecurityService.php)
  - IP verification and whitelist management (`verified_ips` in Redis session data).
  - Rate limiting failed password attempts by `security:attempts:pwd:user:{user_id}:{ip}` and `security:attempts:pwd:ip:{ip}`.
  - Temporary IP blocking (`security:ip_blocked:{ip}`).
  - Bot emergency kill switch (`blockByHash` via `sec_blk:{hash}` callback data <= 40 bytes).
- **GeoIP Service**: [GeoIpService.php](file:///home/admin/projects/crm/app/Services/Security/GeoIpService.php)
  - Resolves client IP from Cloudflare/reverse proxy headers (`CF-Connecting-IP`, `X-Forwarded-For`).
  - Resolves country name with emoji flags and caches lookups in Redis for 7 days.
- **Cloud Password Controller**: [CloudPasswordController.php](file:///home/admin/projects/crm/app/Http/Controllers/Auth/CloudPasswordController.php)
  - `POST /api/auth/cloud-password/verify`: Verify 2FA password.
  - `POST /api/auth/cloud-password/setup`: Initial password setup (rejected if already configured).
  - `POST /api/auth/cloud-password/change`: Password change with mandatory current password verification.
  - `POST /api/admin/users/{user}/reset-cloud-password`: Owner-only emergency reset.
- **Session Security Controller**: [SessionSecurityController.php](file:///home/admin/projects/crm/app/Http/Controllers/Auth/SessionSecurityController.php)
  - `GET /api/auth/sessions`: List active sessions with masked token hints and country.
  - `DELETE /api/auth/sessions`: Revoke a single session or all other sessions.
  - `POST /api/auth/block-session`: Emergency session termination.
- **Middleware**: [ValidateTmaUser.php](file:///home/admin/projects/crm/app/Http/Middleware/ValidateTmaUser.php)
  - Validates TMA sessions and enforces IP verification redirect to `verify-ip` view for unverified connections.

## Security Rules

1. **Password Complexity**: `SessionSecurityService::isPasswordTooTrivial()` rejects identical character repeats (`1111`, `0000`, `aaaa`) and simple numeric sequences (`1234`, `4321`, `qwerty`).
2. **Brute Force Protection**: Failed attempts are tracked atomically per user and per IP. 3 failed attempts trigger a 15-minute temporary IP ban.
3. **Audit Logging**: All security events (login, logout, session revocation, cloud password setup/change/reset, IP blocks) are logged to `action_logs` with `ActionType::AUTH`.
4. **Telegram Alerts**: When an unverified IP connects, the bot dispatches an immediate alert with device telemetry and a 1-tap `[🚨 Заблокировать сессию]` button.
5. **Content Security Policy (CSP)**:
   - Configured in `docker/nginx/default.conf` and `docker/nginx/prod.conf`.
   - `img-src 'self' data: blob: https:;` permits catalog images from HTTPS sources (S3, CDN, Unsplash).
   - `script-src` permits `'self'`, Telegram WebApp, jsDelivr, Pusher, and Cloudflare Insights.
   - External dependencies should be bundled into client assets (e.g. `pusher-js` via npm) rather than loaded from CDNs to eliminate CSP evasion and CDN outage risks.
6. **API Endpoint Exposure Protection**:
   - Server-to-server webhook routes (`api/telegram/webhook`, `api/orders/incoming`, `api/leads/incoming`, `api/catalog/*`) and administrative Horizon routes are excluded in `config/wayfinder.php`.
   - Client-side bundles must only contain definitions for routes callable by TMA users.

