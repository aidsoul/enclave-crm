---
name: crm-frontend
description: Manage Vite build, Tailwind CSS, Alpine.js, Chart.js, and Telegram Mini App UI assets.
---

# CRM Frontend & Mini App Operations

Frontend assets use Vite 8, Tailwind CSS 3.4, Alpine.js, Chart.js, and htmx. The dev server runs in `crm_node` on port 5173.

## Node Container Commands

All npm commands should be executed inside `crm_node` or verified against the host:

```bash
# Production asset build
docker exec crm_node npm run build

# Install new npm package
docker exec crm_node npm install --legacy-peer-deps <package-name>

# Check Vite dev server logs
docker logs --tail 100 crm_node
```

## Telegram Mini App Integration

- Telegram WebApp script: loaded in Blade layouts (`https://telegram.org/js/telegram-web-app.js`).
- Alpine.js components handle reactive client state (`resources/js/app.js`).
- Chart.js renders CRM dashboards and analytics widgets.
- Real-time events: WebSockets run via Reverb with `pusher-js` bundled by Vite (`resources/js/echo-init.js`). Never load Pusher or external scripts via CDN script tags to avoid CSP violations.
- Date Formatting: `formatDateRu(isoStr)` (format `DD.MM.YYYY`) and `formatDateIso(date)` (`YYYY-MM-DD`) are available globally on `window` and in `createDateRangeMixin`.
- Hot reload cache file: if Vite hot-reload breaks or port 5173 is locked, restart container:
  ```bash
  docker restart crm_node
  ```

## Wayfinder API & Routing

- All API endpoints and TMA navigation URLs must use generated Wayfinder definitions (`@actions` and `@routes`), never hardcoded URL strings.
- Generation:
  ```bash
  docker exec crm_app php artisan wayfinder:generate
  ```
- Calling API in Alpine.js components:
  ```javascript
  import OrderController from '@/actions/App/Http/Controllers/OrderController';
  import orders from '@/routes/tma/orders';

  const res = await this.api(OrderController.show(this.orderId));
  window.location.href = orders.show.url(this.orderId);
  ```
- See `.agents/skills/wayfinder/SKILL.md` for full details.

## Modal Windows & Dialogs

- NEVER use `Telegram.WebApp.showPopup(...)`, native browser dialogs, alerts or prompts (`confirm()`, `alert()`, `prompt()`).
- ALWAYS use the project's built-in Blade component `@component('tma.partials.modal', ['show' => 'showModalStateVariable', ...])` controlled via Alpine.js state for modal views.
- ALWAYS use `await window.confirmDialog({ title, message, confirmText, cancelText, variant })` for confirmation prompts (Yes/No, Cancel/Confirm, Delete, etc.).
- For Cloud Password (2FA) setup/change, use the unified component `resources/views/tma/components/cloud-password-modal.blade.php` and `cloudPasswordMixin` in `resources/js/tma/shared.js`.
- Keep dialog styles, buttons, and animations uniform with the CRM surface and theme.
