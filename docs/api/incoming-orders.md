# API входящих заказов

## Запрос

- **Метод:** `POST`
- **URL:** `https://crm.example.com/api/orders/incoming`

### Заголовки

| Заголовок | Обязательный | Значение |
| :--- | :--- | :--- |
| `Content-Type` | Да | `application/json` |
| `Accept` | Да | `application/json` |
| `X-API-Token` | Да | API токен сайта (UUID) |

### Ограничение частоты (Rate Limiting) и несколько сайтов на одном IP

На `/api/orders/incoming` действуют лимиты:
- **60 запросов в минуту** на один токен сайта (`X-API-Token`).
- **60 запросов в минуту** на физический IP-адрес отправителя (`order_phys_ip:...`).
- **15 запросов в минуту** на явный IP покупателя (`client_ip` или `CF-Connecting-IP`), если он передан.
- **10 запросов в минуту** на явный цифровой отпечаток устройства (`fingerprint` / `visitor_id` / `X-Client-Fingerprint`), если он передан.
- При превышении любого из лимитов возвращается `429 Too Many Requests`.

> **Если на одном сервере (одном IP) расположено несколько сайтов:**
> - В **антифроде** сайты изолированы по `site_id` и не влияют друг на друга.
> - В **Rate Limiter** лимит **60 req/min** на физический IP сервера является **общим** для всех сайтов на этом IP.
> - **Обязательно** передавайте с бэкенда сайта реальные `client_ip`, `user_agent` и `fingerprint` (или `visitor_id`) покупателя — иначе все заказы с сайта будут привязаны к IP сервера и при частых заказах могут сработать эвристики антифрода (`rapid_phone_rotation` / `velocity_burst`).

---

## Поля запроса

### Идентификация заказа

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `external_id` | `string` | Нет | ID или номер заказа во внешней системе (макс. 255). Используется для дедупликации и поиска |
| `external_order_id` | `string` | Нет | Алиас для `external_id` |

### Данные клиента

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `client_name` | `string` | Нет | Имя / ФИО клиента (макс. 255) |
| `client_phone` | `string` | Нет* | Номер телефона (7–20 символов). Рекомендуется для поиска/создания клиента и дедупликации |
| `phone` | `string` | Нет | Алиас для `client_phone` |
| `client_email` | `string` | Нет | Email (макс. 255) |
| `client_address` | `string` | Нет | Адрес клиента (макс. 1000) |
| `client_comment` | `string` | Нет | Комментарий покупателя к заказу (макс. 2000). Создаётся как первый комментарий автора «Клиент» |
| `comment` | `string` | Нет | Алиас для `client_comment` |
| `client_data` | `object` | Нет | Объект `{name, phone, email, address}` |
| `customer_id` | `integer` | Нет | ID существующего клиента в CRM |

### Состав заказа (`order_items` или `order_content`)

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `order_items` | `array` | Да* | Список позиций (альтернатива: `order_content`) |
| `order_items.*.name` | `string` | Нет** | Название товара (макс. 255). Если не передано, автоматически заполняется названием найденного товара или варианта |
| `order_items.*.external_variant_id` | `string` | Нет | Внешний идентификатор варианта товара на сайте (макс. 255). Используется для приоритетного связывания позиции с вариантом в CRM |
| `order_items.*.external_product_id` | `string` | Нет | Внешний идентификатор базового товара на сайте (макс. 255). Используется для связывания с товаром каталога |
| `order_items.*.product_variant_id` | `integer` | Нет | Внутренний ID варианта (`product_variants.id`) в CRM |
| `order_items.*.product_id` | `integer` | Нет | Внутренний ID товара (`products.id`) в CRM |
| `order_items.*.variant_title` | `string` | Нет | Название модификации/варианта (например, `"Черный / L"`, макс. 255) |
| `order_items.*.sku` | `string` | Нет | Артикул варианта или базового товара (макс. 100). Используется для поиска товара/варианта, если не передан ID |
| `order_items.*.qty` | `integer` | **Да** | Количество (целое число от 1 до 10 000) |
| `order_items.*.price` | `numeric` | Нет | Цена за единицу. Если не указана, берется актуальная цена из каталога товаров CRM |
| `order_items.*.purchase_price` | `numeric` | Нет | Закупочная себестоимость единицы. Если не указана, берется из каталога CRM или вычисляется по проценту сайта |

*\* `order_items` обязателен, если не передан `order_content`.*  
*\*\* `name` обязателен, только если позиция передается без `sku`, `external_variant_id` или `external_product_id`.*

#### Приоритет связывания позиции с каталогом CRM:
1. По `product_variant_id` или `external_variant_id` (привязка к конкретному варианту товара).
2. По `sku` (среди вариантов товара сайта).
3. По `external_product_id` (привязка к базовому товару сайта).
4. По `sku` (среди базовых товаров сайта без вариантов).
5. При обнаружении соответствия позиция получает ссылку на карточку товара в CRM, актуальное фото, ссылку на страницу товара на сайте, цену и себестоимость.

### Стоимость заказа

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `total_price` | `numeric` | Нет | Итоговая сумма заказа. Если не указана, вычисляется как сумма `price * qty` всех позиций |
| `purchase_price` | `numeric` | Нет | Общая закупочная стоимость. Если не указана, вычисляется как сумма `purchase_price * qty` всех позиций |

### Доставка

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `delivery_type` | `string` | Нет | `courier` (по умолчанию) или `pickup` |
| `delivery_city` | `string` | Нет | Город / населённый пункт (макс. 100) |
| `city` | `string` | Нет | Алиас для `delivery_city` |
| `delivery_address` | `string` | Нет | Адрес доставки (макс. 1000). Если не передан, берётся адрес клиента |
| `delivery_pickup_point` | `string` | Нет | Пункт самовывоза (макс. 500) |
| `delivery_date` | `date` | Нет | Дата доставки (`YYYY-MM-DD`) |
| `delivery_slot` | `string` | Нет | Временной интервал (макс. 50) |
| `delivery_cost` | `numeric` | Нет | Стоимость доставки (по умолчанию `0`) |
| `delivery_courier_id` | `integer` | Нет | ID конкретного курьера в CRM |
| `delivery_courier_comment` | `string` | Нет | Комментарий курьеру (макс. 1000) |

### Оплата

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `payment_method` | `string` | Нет | `cash` (по умолчанию), `terminal`, `erip`, `credit`, `installment` |
| `payment_status` | `string` | Нет | `pending` (по умолчанию), `paid`, `failed` |
| `payment_amount` | `numeric` | Нет | Сумма платежа (по умолчанию равна `total_price`) |
| `payment_transaction_id` | `string` | Нет | Номер транзакции, кредитного договора или заявки в банке (макс. 255) |

### Скидки (`discounts`)

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `discounts` | `array` | Нет | Массив скидок |
| `discounts.*.type` | `string` | **Да** (если есть `discounts`) | `percentage`, `fixed`, `coupon` |
| `discounts.*.value` | `numeric` | **Да** (если есть `discounts`) | Размер скидки ($\ge 0$) |
| `discounts.*.code` | `string` | Нет | Промокод (макс. 100) |

### Дополнительные данные (`extra_data`)

Произвольный JSON-объект (`jsonb`). Для заказов в рассрочку (`installment`) или кредит (`credit`) следующие поля автоматически отображаются в блоке оплаты CRM:

| Ключ | Тип | Описание | Пример |
| :--- | :--- | :--- | :--- |
| `bank` | `string` | Название банка | `"МТБанк"` |
| `program` / `card_name` | `string` | Программа / название карты | `"Карта Халва"` |
| `months` | `integer` | Срок рассрочки в месяцах | `6` |
| `monthly_payment` | `numeric` | Ежемесячный платёж | `466.67` |
| `initial_payment` | `numeric` | Первоначальный взнос | `0` |

### Телеметрия антифрода (рекомендуется для server-to-server интеграций)

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `client_ip` | `string` | Нет (рек.) | Реальный IPv4/IPv6 адрес покупателя (макс. 45). Критически важен при отправке вебхука с бэкенда сайта |
| `fingerprint` | `string` | Нет (рек.) | Уникальный идентификатор браузера/устройства покупателя (`[a-zA-Z0-9_.:-]{8,128}`) |
| `visitor_id` | `string` | Нет | Алиас для `fingerprint` |
| `user_agent` | `string` | Нет | `User-Agent` браузера покупателя (макс. 500) |
| `accept_language` | `string` | Нет | Заголовок `Accept-Language` покупателя (макс. 100) |

---

## Примеры запросов

### Минимальный пример

```bash
curl -X POST https://crm.example.com/api/orders/incoming \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-API-Token: YOUR_SITE_API_TOKEN" \
  -d '{
    "client_name": "Иван Иванов",
    "client_phone": "+375291234567",
    "order_items": [
      {
        "name": "Смартфон Pixel 9 Pro 256GB",
        "qty": 1,
        "price": 3199.00
      }
    ]
  }'
```

### Полный пример (JSON)

```json
{
  "external_id": "ORDER-2026-9918",
  "client_name": "Алексей Смирнов",
  "client_phone": "+375 29 987-65-43",
  "client_email": "alexey@example.com",
  "client_address": "ул. Притыцкого 35, кв. 112",
  "client_comment": "Позвонить за 30 минут до приезда",
  "order_items": [
    {
      "name": "Беспроводные наушники",
      "sku": "WH-1000XM5-BLK",
      "qty": 2,
      "price": 250.00
    },
    {
      "name": "Чехол силиконовый",
      "sku": "CASE-WH-SIL-01",
      "qty": 1,
      "price": 35.00
    }
  ],
  "total_price": 535.00,
  "delivery_type": "courier",
  "delivery_city": "Минск",
  "delivery_address": "ул. Притыцкого 35, кв. 112, под. 3, эт. 7",
  "delivery_date": "2026-10-25",
  "delivery_slot": "14:00-18:00",
  "delivery_cost": 10.00,
  "delivery_courier_comment": "Домофон 112",
  "payment_method": "installment",
  "payment_status": "pending",
  "payment_transaction_id": "MTB-HALVA-883190",
  "discounts": [
    {
      "type": "coupon",
      "value": 15.00,
      "code": "AUTUMN2026"
    }
  ],
  "extra_data": {
    "bank": "МТБанк",
    "program": "Карта Халва",
    "months": 6,
    "monthly_payment": 89.17,
    "initial_payment": 0,
    "utm_source": "yandex_direct"
  }
}
```

---

## Ответы API

### 202 Accepted (Заказ принят)
```json
{
  "job_id": "order_6713dc2f90a1b"
}
```

### 202 Accepted (Дубликат проигнорирован)
```json
{
  "job_id": "dedup_6713dc3a12b3c",
  "duplicate": true,
  "message": "Duplicate order ignored"
}
```

### 202 Accepted (Отклонено антифрод-системой)
Запрос отклонён из-за срабатывания эвристик защиты от спама/фрода (заказ не создаётся в CRM):
```json
{
  "job_id": "spam_6b06166d065aa",
  "spam": true,
  "message": "Order rejected by anti-fraud protection",
  "reasons": [
    "Multiple different phone numbers submitted from single device",
    "Submission frequency exceeds burst threshold"
  ]
}
```

### 422 Unprocessable Entity (Ошибка валидации)
```json
{
  "message": "Укажите состав заказа.",
  "errors": {
    "order_items": [
      "Укажите состав заказа."
    ]
  }
}
```

### 401 Unauthorized / 403 Forbidden
```json
{
  "error": "Unauthorized"
}
```
