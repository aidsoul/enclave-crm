---
name: wayfinder
description: Use Laravel Wayfinder for type-safe route definitions and controller actions in frontend JavaScript/TypeScript.
---

# Laravel Wayfinder Integration Guide

This project uses `laravel/wayfinder` and `@laravel/vite-plugin-wayfinder` to provide type-safe, auto-generated definitions for Laravel controller actions and named routes in frontend JavaScript.

## Path Aliases

Vite is configured with aliases defined in `vite.config.js`:
- `@actions`: points to `resources/js/actions` (generated controller actions)
- `@routes`: points to `resources/js/routes` (generated named routes)
- `@`: points to `resources/js`

## Commands

Whenever routes or controller methods are added, modified, or deleted:

```bash
# Generate action and route definitions (runs inside crm_app)
docker exec crm_app php artisan wayfinder:generate

# Build frontend assets (runs inside crm_node)
docker exec crm_node npm run build
```

Generation is also run automatically during `composer dump-autoload` via the `post-autoload-dump` hook.

## How to Call Endpoints

The project's TMA API mixin (`resources/js/tma/shared/api.js`) seamlessly accepts `RouteDefinition` objects. It automatically extracts `url` and sets the HTTP `method`:

### 1. Simple GET Request

```javascript
import ProductController from '@/actions/App/Http/Controllers/ProductController';

// Calling an action generates: { url: '/api/products', method: 'get' }
const res = await this.api(ProductController.index());
```

### 2. Request with Parameters (Path & Query)

```javascript
import OrderController from '@/actions/App/Http/Controllers/OrderController';

// Path parameter:
const res = await this.api(OrderController.show(this.orderId));

// Query parameters:
const res = await this.api(OrderController.index({
  query: { status: 'assigned', per_page: 20 }
}));
```

### 3. Mutation Requests (POST, PATCH, DELETE)

Method is detected automatically from the action definition. Pass the request payload in `body`:

```javascript
import OrderController from '@/actions/App/Http/Controllers/OrderController';

// POST request:
await this.api(OrderController.store(), {
  body: JSON.stringify(payload),
});

// PATCH request with ID:
await this.api(OrderController.update(this.orderId), {
  body: JSON.stringify(payload),
});

// DELETE request with query params (e.g. file deletion):
await this.api(OrderController.deleteProof(this.orderId, { query: { path: url } }));
```

### 4. Page Redirection and URLs

Use `.url(...)` helpers for navigation and links:

```javascript
import orders from '@/routes/tma/orders';
import tma from '@/routes/tma';

// Redirect to order details:
window.location.href = orders.show.url(this.orderId);

// Redirect to manager dashboard or order list:
window.location.href = this.user?.role === 'manager' 
  ? tma.manager.url() 
  : orders.index.url();

// Role-based redirects:
window.location.href = tma.role.url('courier');
```

## Route Filtering & Exclusions

To prevent polluting frontend IDE autocomplete and exposing internal routes:
- Exclusions are configured in `config/wayfinder.php`.
- Handled by `app/Console/Commands/WayfinderGenerateCommand.php`.
- Excluded by default:
  - Horizon routes (`horizon.*`, `horizon/*`, `Laravel\Horizon\*`)
  - WebSocket internal routes (`broadcasting/*`, `Illuminate\Broadcasting\*`)
  - Server-to-server webhooks (`api/telegram/webhook`, `api/orders/incoming`, `api/leads/incoming`, `api/catalog/*`)
