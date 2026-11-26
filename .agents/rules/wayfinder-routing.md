---
trigger: always_on
description: Strict requirement to use Laravel Wayfinder action definitions and route helpers instead of hardcoded API or TMA route strings.
---

# Wayfinder Routing & API Calls Rule

1. **NO Hardcoded Endpoint Strings**:
   - Strictly forbidden to use hardcoded `/api/...` string URLs in `this.api(...)`, `this.apiFetch(...)`, or `fetch(...)` calls.
   - Strictly forbidden to use hardcoded `/tma/...` URL strings for page redirections (`window.location.href = ...`, `window.location.replace(...)`).

2. **Use Generated Wayfinder Actions**:
   - For backend API calls, import controller actions from `@actions/App/Http/Controllers/...` and pass the invocation result to `this.api(Controller.action(...), options)`.
   - The API client automatically reads `method` and `url` from the generated `RouteDefinition`.

3. **Use Generated Route Helpers**:
   - For TMA navigation links and redirects, import named routes from `@routes/tma/...` and invoke `.url(...)` (e.g. `orders.show.url(id)`, `orders.index.url()`, `tma.manager.url()`, `tma.role.url('courier')`).

4. **Regeneration on Route Changes**:
   - When adding new routes to `routes/api.php` or `routes/web.php`, or new controller actions, ALWAYS run `docker exec crm_app php artisan wayfinder:generate` and build frontend assets with `docker exec crm_node npm run build`.
   - Never manually edit files inside `resources/js/actions/` or `resources/js/routes/`; they are generated artifacts.
