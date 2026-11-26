# API входящих заявок (лидов) с сайта

Эндпоинт предназначен для интеграции любых форм сайта с CRM: «Заказать звонок», «Напишите нам», «Задать вопрос», «Консультация», «Купить в 1 клик».

---

## Запрос

- **Метод:** `POST`
- **URL:** `https://crm.example.com/api/leads/incoming`

### Заголовки

| Заголовок | Обязательный | Значение |
| :--- | :--- | :--- |
| `Content-Type` | Да | `application/json` |
| `Accept` | Да | `application/json` |
| `X-API-Token` | Да | API-токен сайта (UUID из настроек сайта в CRM) |

### Ограничение частоты (Rate Limiting)

На `/api/leads/incoming` действуют многоуровневые лимиты:
- **60 запросов в минуту** на один токен сайта (`X-API-Token`).
- **60 запросов в минуту** на физический IP-адрес отправителя (`lead_phys_ip:...`).
- **15 запросов в минуту** на IP клиента (`client_ip` или `CF-Connecting-IP`; если не переданы — используется физический IP сервера).
- **10 запросов в минуту** на цифровой отпечаток устройства (`fingerprint` / `visitor_id` / `X-Client-Fingerprint` или хэш от `client_ip|user_agent|accept_language`).
- **5 запросов в минуту** на один контакт (телефон или email).
- При превышении любого из лимитов возвращается `429 Too Many Requests`.
- Обработка входящих данных выполняется асинхронно через очередь (`ProcessIncomingLeadJob`), обеспечивая моментальный ответ API даже при всплесках трафика.

> **Если на одном сервере (одном IP) расположено несколько сайтов:**
> - В **антифроде** сайты изолированы по `site_id` и не влияют друг на друга.
> - В **Rate Limiter** лимит **60 req/min** на физический IP сервера является **общим** для всех сайтов на этом IP. Кроме того, если сайты не передают `client_ip` конечного посетителя, общий лимит для них падает до **15 req/min**.
> - **Обязательно** передавайте с бэкенда сайта реальные `client_ip`, `user_agent` и `fingerprint` (или `visitor_id`) покупателя — иначе все заявки с сайта будут выглядеть как исходящие от одного устройства (IP сервера) и быстро попадут под блокировку антифрода.

---

## Поля запроса

### 1. Тип и источник обращения

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `type` | `string` | Нет | Тип формы: `callback` (по умолчанию), `contact`, `question`, `one_click`, `consultation` |
| `form_type` | `string` | Нет | Алиас для `type` |
| `external_id` | `string` | Нет | ID заявки во внешней системе (макс. 255) |
| `page_url` | `string` | Нет | Полный URL страницы, на которой заполнена форма (макс. 1000) |
| `page_title` | `string` | Нет | Заголовок (`<title>`) страницы (макс. 255) |
| `product_name` | `string` | Нет | Название товара (для формы «в 1 клик» или вопроса по товару, макс. 255) |
| `product_sku` | `string` | Нет | Артикул (SKU) товара (макс. 100) |

### 2. Данные клиента

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `client_phone` | `string` | **Да\*** | Номер телефона (7–20 символов). Допустимы любые популярные форматы |
| `phone` | `string` | Нет | Алиас для `client_phone` |
| `client_name` | `string` | Нет | Имя или ФИО клиента (макс. 255) |
| `name` | `string` | Нет | Алиас для `client_name` |
| `client_email` | `string` | Нет\* | Email клиента (макс. 255) |
| `email` | `string` | Нет | Алиас для `client_email` |
| `message` | `string` | Нет | Текст вопроса, пожелания или комментария клиента (макс. 2000) |
| `comment` | `string` | Нет | Алиас для `message` |

*\* Обязательно указание хотя бы одного контакта: телефона или email.*

### 3. Дополнительные данные (`extra_data`)

Произвольный JSON-объект (`jsonb`). Сохраняется в карточке заявки и переносится в заказ при конвертации.

| Ключ | Тип | Описание | Пример |
| :--- | :--- | :--- | :--- |
| `utm_source` | `string` | Источник перехода | `"yandex"` / `"google"` |
| `utm_medium` | `string` | Канал трафика | `"cpc"` / `"organic"` |
| `utm_campaign` | `string` | Рекламная кампания | `"promo_autumn_2026"` |
| `utm_content` | `string` | Содержимое объявления | `"banner_1"` |
| `utm_term` | `string` | Ключевое слово | `"купить кофемашину"` |
| `user_ip` | `string` | IP-адрес посетителя сайта | `"178.120.1.2"` |
| `custom_fields` | `object` | Дополнительные поля формы | `{"call_time": "после 18:00"}` |

### 4. Телеметрия антифрода (рекомендуется для server-to-server интеграций)

| Поле | Тип | Обязательное | Описание |
| :--- | :--- | :--- | :--- |
| `client_ip` | `string` | Нет (рек.) | Реальный IPv4/IPv6 адрес посетителя сайта (макс. 45). Критически важен при отправке вебхука с бэкенда сайта |
| `fingerprint` | `string` | Нет (рек.) | Уникальный идентификатор браузера/устройства посетителя (`[a-zA-Z0-9_.:-]{8,128}`) |
| `visitor_id` | `string` | Нет | Алиас для `fingerprint` |
| `user_agent` | `string` | Нет | `User-Agent` браузера посетителя (макс. 500) |
| `accept_language` | `string` | Нет | Заголовок `Accept-Language` посетителя (макс. 100) |

---

## Связка с базой клиентов (CRM)

При обработке заявки система автоматически выполняет поиск клиента в таблице `customers`:
- Если клиент с таким номером телефона уже существует, обращение привязывается к его профилю (`customer_id`).
- В карточке клиента в CRM отображается история всех его обращений и заказов.
- При нажатии менеджером кнопки «Создать заказ» все контактные данные клиента подтягиваются автоматически.

---

## Дедупликация и защита от спама

1. **По `external_id`:** если передан идентификатор внешней системы, повторный запрос с тем же `external_id` игнорируется.
2. **По связке `site_id + phone + type`:** если у клиента уже есть активная заявка данного типа в статусе `new` («Новая»), повторная отправка формы блокируется (`duplicate: true`).
3. **Разблокировка повторной отправки:** как только менеджер переводит предыдущую заявку в другой статус (`in_progress` / «В работе», `converted` / «В заказе», `rejected` / «Отклонён»), клиент снова сможет отправить обращение.

---

## Статусы заявки в CRM

| Статус (`status`) | Название | Описание |
| :--- | :--- | :--- |
| `new` | **Новая** | Заявка только поступила с сайта, ожидает реакции менеджера |
| `in_progress` | **В работе** | Менеджер взял заявку в обработку (звонит клиенту) |
| `converted` | **В заказе** | Из заявки создан полноценный заказ в CRM |
| `rejected` | **Отклонённые** | Спам, не дозвонились, отказ клиента |

---

## Примеры запросов

### 1. Форма «Заказать звонок» (cURL)

```bash
curl -X POST https://crm.example.com/api/leads/incoming \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-API-Token: YOUR_SITE_API_TOKEN" \
  -d '{
    "type": "callback",
    "client_name": "Дмитрий",
    "client_phone": "+375 29 123-45-67",
    "page_url": "https://site.example.com/catalog"
  }'
```

### 2. Форма «Вопрос по товару / Купить в 1 клик» (Полный JSON)

```json
{
  "type": "question",
  "external_id": "LEAD-WEB-90412",
  "client_name": "Елена Смирнова",
  "client_phone": "+375 (29) 987-65-43",
  "client_email": "elena.smirnova@example.com",
  "message": "Подскажите, есть ли данная модель в наличии и действует ли гарантия?",
  "product_name": "Кофемашина DeLonghi Magnifica S ECAM 22.110.B",
  "product_sku": "DL-ECAM-22110B",
  "page_url": "https://site.example.com/product/delonghi-ecam-22110b",
  "page_title": "Кофемашина DeLonghi Magnifica S — купить в Минске",
  "extra_data": {
    "utm_source": "yandex",
    "utm_medium": "cpc",
    "utm_campaign": "coffee_machines_search",
    "user_ip": "178.120.45.67",
    "custom_fields": {
      "preferred_messenger": "telegram"
    }
  }
}
```

### 3. Пример отправки на JavaScript (для сайта)

```javascript
async function sendSiteLead(formData) {
  try {
    const response = await fetch('https://crm.example.com/api/leads/incoming', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-API-Token': 'YOUR_SITE_API_TOKEN'
      },
      body: JSON.stringify({
        type: 'callback',
        client_name: formData.name,
        client_phone: formData.phone,
        message: formData.message,
        page_url: window.location.href,
        page_title: document.title
      })
    });

    const result = await response.json();

    if (response.status === 202) {
      if (result.duplicate) {
        alert('Ваша заявка уже принята и находится в обработке менеджером.');
      } else {
        alert('Спасибо! Мы перезвоним вам в ближайшее время.');
      }
    } else {
      alert(result.message || 'Ошибка отправки формы');
    }
  } catch (error) {
    console.error('Ошибка отправки заявки:', error);
  }
}
```

---

## Ответы API

### 202 Accepted (Заявка принята)
Запрос принят и отправлен в очередь на обработку:
```json
{
  "job_id": "lead_6713dc2f90a1b",
  "status": "accepted"
}
```

### 202 Accepted (Дубликат проигнорирован)
Если у клиента уже есть активная заявка в статусе `new` или передан повторяющийся `external_id`:
```json
{
  "job_id": "dedup_6713dc3a12b3c",
  "duplicate": true,
  "message": "Duplicate lead ignored"
}
```

### 202 Accepted (Отклонено антифрод-системой)
Запрос отклонён из-за срабатывания эвристик защиты от спама/SMS-бомбинга (заявка не создаётся в CRM и не отправляет уведомлений персоналу):
```json
{
  "job_id": "spam_6b06166d065aa",
  "spam": true,
  "message": "Lead rejected by anti-fraud protection",
  "reasons": [
    "Multiple different phone numbers submitted from single device",
    "Submission frequency exceeds burst threshold"
  ]
}
```
Основные причины (`reasons`):
- `Multiple different phone numbers submitted from single device` — с одного устройства (`fingerprint` / IP) за 10 минут отправлено 3 и более разных номера телефона (`+85` баллов).
- `Submission frequency exceeds burst threshold` — с одного устройства за 5 минут отправлено более 5 заявок (`+60` баллов).
- `Honeypot form field filled`, `Automated bot user-agent detected`, `Disposable email address detected`, `Suspicious or dummy phone number pattern`, `Spam URLs or HTML markup detected in message or name`.

### 422 Unprocessable Entity (Ошибка валидации)
```json
{
  "message": "Укажите номер телефона или email.",
  "errors": {
    "client_phone": [
      "Некорректный формат телефона. Пример: +375 29 123-45-67."
    ]
  }
}
```

### 429 Too Many Requests (Превышен лимит запросов)
```json
{
  "message": "Too Many Attempts."
}
```

### 401 Unauthorized / 403 Forbidden
```json
{
  "error": "Unauthorized"
}
```
