---
name: crm-orders
description: Test, debug, and implement incoming order APIs, site tokens, and order lifecycle transitions in the CRM.
---

# CRM Orders Workflow

Handles external site integrations, order intake, assignment, and status transitions.

## Site & API Token Management

External sites submit orders via API using an `X-API-Token`.

```bash
# Create site or inspect existing site token
docker exec crm_app php artisan site:create "<Site Name>" <domain.com>
```

## Testing Incoming Orders API

Test incoming order ingestion endpoint (`/api/orders/incoming`):

```bash
curl -X POST http://localhost:8080/api/orders/incoming \
  -H "Content-Type: application/json" \
  -H "X-API-Token: <SITE_API_TOKEN>" \
  -d '{
    "client_name": "Иван Иванов",
    "client_phone": "+375291234567",
    "delivery_address": "ул. Ленина 1, кв. 10",
    "order_items": [
      {
        "name": "Товар 1",
        "price": 100,
        "qty": 1
      }
    ],
    "comment": "Тестовый заказ"
  }'
```

## Relevant Code

- Intake Controller: [IncomingOrderController.php](file:///home/admin/projects/crm/app/Http/Controllers/IncomingOrderController.php)
- Order Management: [OrderController.php](file:///home/admin/projects/crm/app/Http/Controllers/OrderController.php)
- Order Services: [app/Services/Orders/](file:///home/admin/projects/crm/app/Services/Orders/)
- Order Models: [app/Models/Order.php](file:///home/admin/projects/crm/app/Models/Order.php)

## Order Lifecycle Actions

Endpoints under `/api/orders/{order}/` with `tma.auth` middleware:
- `/accept` (`role:owner,manager`)
- `/assign` (`can:update,order`)
- `/at-warehouse` (courier / manager)
- `/complete` (`can:complete,order`)
- `/cancel` (`can:cancel,order`)
