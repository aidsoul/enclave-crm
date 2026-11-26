# API синхронизации каталога товаров и вариантов

API позволяет интернет-магазину передавать в Enclave CRM список товаров с вариантами (модификациями по цвету, размеру, объему и др.), обновлять их при изменениях на сайте и безопасно удалять.

---

## Авторизация

Все запросы к Catalog API требуют заголовок с API-токеном сайта:

| Заголовок | Обязательный | Значение |
| :--- | :--- | :--- |
| `Content-Type` | Да | `application/json` |
| `Accept` | Да | `application/json` |
| `X-API-Token` | Да | API токен сайта (UUID) |

---

## 1. Пакетная синхронизация каталога

Используется для полной выгрузки товаров интернет-магазина в CRM или периодической регулярной синхронизации.

- **Метод:** `POST`
- **URL:** `https://crm.example.com/api/catalog/sync`

### Поля запроса

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `async` | `boolean` | Нет | `true` — фоновая синхронизация через очередь (`202 Accepted`), `false` (по умолчанию) — синхронная обработка |
| `products` | `array` | **Да** | Массив товаров (от 1 до 500 позиций за один запрос) |
| `products.*.external_id` | `string` | **Да** | Уникальный ID товара на сайте / CMS (макс. 255) |
| `products.*.name` | `string` | **Да** | Название товара (макс. 255) |
| `products.*.sku` | `string` | Нет | Артикул товара (макс. 100) |
| `products.*.description` | `string` | Нет | Описание товара (макс. 5000) |
| `products.*.image_url` | `string (URL)`| Нет | Прямая ссылка на изображение товара (макс. 2048) |
| `products.*.url` | `string (URL)`| Нет | Ссылка на страницу товара на сайте (макс. 2048) |
| `products.*.price` | `numeric` | Нет | Розничная цена продажи ($\ge 0$) |
| `products.*.cost_price` | `numeric` | Нет | Закупочная себестоимость товара ($\ge 0$) |
| `products.*.is_active` | `boolean` | Нет | Активен ли товар (`true` по умолчанию) |
| `products.*.variants` | `array` | Нет | Массив вариантов/модификаций товара |
| `products.*.variants.*.external_id` | `string` | **Да** | Уникальный ID варианта на сайте (макс. 255) |
| `products.*.variants.*.title` | `string` | **Да** | Название варианта (например, `"Черный / L"`, макс. 255) |
| `products.*.variants.*.sku` | `string` | Нет | Артикул конкретного варианта (макс. 100) |
| `products.*.variants.*.price` | `numeric` | Нет | Цена варианта (если отличается от базовой цены товара) |
| `products.*.variants.*.purchase_price` | `numeric` | Нет | Себестоимость варианта (если отличается от базовой) |
| `products.*.variants.*.attributes` | `object` | Нет | Объект характеристик (`{"Цвет": "Черный", "Размер": "L"}`) |
| `products.*.variants.*.is_active` | `boolean` | Нет | Активность варианта (`true` по умолчанию) |

### Пример синхронного запроса

```bash
curl -X POST https://crm.example.com/api/catalog/sync \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-API-Token: a4b12c8e-73df-4c28-98e3-0599c4c7810f" \
  -d '{
    "async": false,
    "products": [
      {
        "external_id": "sp-101",
        "name": "Смартфон Nova 12",
        "sku": "NOVA-12",
        "image_url": "https://myshop.com/images/nova12.jpg",
        "url": "https://myshop.com/products/nova-12",
        "price": 1299.00,
        "cost_price": 850.00,
        "is_active": true,
        "variants": [
          {
            "external_id": "sp-101-blk-128",
            "sku": "NOVA-12-BLK-128",
            "title": "Черный 128GB",
            "price": 1299.00,
            "purchase_price": 850.00,
            "attributes": {
              "Цвет": "Черный",
              "Память": "128GB"
            }
          },
          {
            "external_id": "sp-101-slv-256",
            "sku": "NOVA-12-SLV-256",
            "title": "Серебристый 256GB",
            "price": 1499.00,
            "purchase_price": 990.00,
            "attributes": {
              "Цвет": "Серебристый",
              "Память": "256GB"
            }
          }
        ]
      }
    ]
  }'
```

**Ответ (200 OK):**
```json
{
  "message": "Каталог успешно синхронизирован",
  "synced_products": 1,
  "synced_variants": 2
}
```

### Пример фонового запроса (`async: true`)

**Ответ (202 Accepted):**
```json
{
  "message": "Каталог поставлен в очередь на синхронизацию",
  "job_id": "catalog_sync_654e12ab89c0"
}
```

---

## 2. Добавление / обновление одного товара

Используется вебхуком сайта при создании нового товара или редактировании существующего.

- **Метод:** `POST`
- **URL:** `https://crm.example.com/api/catalog/products`

### Поля запроса

Тело запроса содержит объект одного товара с теми же полями, что и элемент в массиве `products` пакетной синхронизации:

```json
{
  "external_id": "sp-102",
  "name": "Защитное стекло 2.5D",
  "sku": "ACC-GLS-15",
  "image_url": "https://myshop.com/images/glass.jpg",
  "url": "https://myshop.com/products/glass-25d",
  "price": 25.00,
  "cost_price": 10.00,
  "is_active": true,
  "variants": []
}
```

**Ответ (200 OK):**
```json
{
  "message": "Товар успешно сохранен",
  "product": {
    "id": 15,
    "external_id": "sp-102",
    "name": "Защитное стекло 2.5D",
    "sku": "ACC-GLS-15",
    "price": 25.0,
    "cost_price": 10.0,
    "margin": 15.0,
    "is_active": true,
    "variants": []
  }
}
```

---

## 3. Удаление и деактивация товаров

### Сценарий А. Полное удаление товара с сайта (`DELETE`)

Используется вебхуком сайта при полном удалении товара из каталога интернет-магазина.

- **Метод:** `DELETE`
- **URL:** `https://crm.example.com/api/catalog/products/{external_id}`
- **Заголовки:**
  - `Accept: application/json`
  - `X-API-Token: <API_TOKEN_САЙТА>`

#### Пример запроса:
```bash
curl -X DELETE https://crm.example.com/api/catalog/products/sp-101 \
  -H "Accept: application/json" \
  -H "X-API-Token: a4b12c8e-73df-4c28-98e3-0599c4c7810f"
```

#### Поведение и защита данных при удалении:

- **Если товар уже оформлялся в заказах:**  
  Товар и все его варианты **не удаляются физически**, а автоматически переводятся в архив (`is_active = false`). Это предотвращает нарушение целостности аналитики, финансовых отчетов и истории покупок клиентов.  
  **Ответ (`200 OK`):**
  ```json
  {
    "message": "Товар архивирован, так как он используется в заказах",
    "archived": true
  }
  ```

- **Если товар ни разу не заказывался:**  
  Товар и связанные варианты безвозвратно удаляются из базы данных.  
  **Ответ (`200 OK`):**
  ```json
  {
    "message": "Товар успешно удален",
    "deleted": true
  }
  ```

---

### Сценарий Б. Временное снятие с публикации / деактивация (`is_active: false`)

Если товар временно закончился на складе или скрыт с витрины сайта, рекомендуется не удалять его, а передать `"is_active": false` через `POST /api/catalog/products`:

```bash
curl -X POST https://crm.example.com/api/catalog/products \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-API-Token: a4b12c8e-73df-4c28-98e3-0599c4c7810f" \
  -d '{
    "external_id": "sp-101",
    "name": "Смартфон Nova 12",
    "is_active": false
  }'
```

---

### Сценарий В. Удаление отдельных вариантов товара

При передаче массива `variants` в `POST /api/catalog/products` или `POST /api/catalog/sync` CRM сопоставляет варианты по `external_id`. Любые ранее существовавшие варианты товара, которые **не были переданы** в новом списке, автоматически помечаются неактивными (`is_active = false`).

---

## 4. Ограничение частоты запросов (Rate Limiting)

Для защиты сервиса от перегрузок и скрапинга на эндпоинты внешнего каталога товаров действует многоуровневый Rate Limiter (`throttle:catalog-sync`):
* **60 запросов в минуту** на API-токен сайта;
* **60 запросов в минуту** на физический IP-адрес клиента.

При превышении лимита возвращается HTTP-код `429 Too Many Requests`.

---

## 5. Коды ответов и ошибок

| Код | Описание |
| :--- | :--- |
| `200 OK` | Товар успешно сохранен, синхронизирован, удален или архивирован |
| `202 Accepted` | Пакет каталога принят в очередь на асинхронную обработку (`async: true`) |
| `401 Unauthorized` | Отсутствует заголовок `X-API-Token` |
| `403 Forbidden` | Недействительный или отключенный API-токен сайта |
| `404 Not Found` | Товар с указанным `external_id` не найден у данного сайта |
| `422 Unprocessable Content` | Ошибка валидации данных (некорректный формат, отрицательные цены и т.д.) |
| `429 Too Many Requests` | Превышен лимит запросов в минуту на токен или IP-адрес |
