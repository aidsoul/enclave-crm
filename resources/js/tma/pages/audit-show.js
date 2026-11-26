import { baseInit, withTmaBase } from '../shared.js';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import tma from '@/routes/tma';

function renderJsonTree(value) {
  if (value === null || value === undefined) {
    return '<span class="text-fg-subtle">null</span>';
  }

  if (typeof value === 'boolean') {
    return `<span class="text-warning">${value ? 'true' : 'false'}</span>`;
  }

  if (typeof value === 'number') {
    return `<span class="text-success">${value}</span>`;
  }

  if (typeof value === 'string') {
    const escaped = value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
    return `<span class="text-success">"${escaped}"</span>`;
  }

  if (Array.isArray(value)) {
    if (value.length === 0) return '<span class="text-fg-subtle">[]</span>';
    const items = value.map((item) => renderJsonTree(item)).join('<span class="text-fg-subtle">,</span>\n');
    const summary = value.length === 1 ? '1 item' : `${value.length} items`;
    return `<details class="mt-1 first:mt-0" open><summary class="json-summary text-xs text-fg-muted cursor-pointer select-none hover:text-fg transition-colors list-none">Array [${summary}]</summary><div class="mt-1 pl-3 border-l border-border whitespace-pre">${items}</div></details>`;
  }

  if (typeof value === 'object') {
    const keys = Object.keys(value);
    if (keys.length === 0) return '<span class="text-fg-subtle">{}</span>';
    const items = keys.map((key) => {
      const escapedKey = key.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      const rendered = renderJsonTree(value[key]);
      return `<span class="text-secondary">"${escapedKey}"</span>: ${rendered}`;
    }).join('<span class="text-fg-subtle">,</span>\n');
    const summary = keys.length === 1 ? '1 key' : `${keys.length} keys`;
    return `<details class="mt-1 first:mt-0" open><summary class="json-summary text-xs text-fg-muted cursor-pointer select-none hover:text-fg transition-colors list-none">Object {${summary}}</summary><div class="mt-1 pl-3 border-l border-border whitespace-pre">${items}</div></details>`;
  }

  return `<span>${String(value)}</span>`;
}

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    log: null,
    loading: true,
    loadError: null,

    get logId() {
      const match = window.location.pathname.match(/^\/tma\/audit\/(\d+)$/);
      return match ? parseInt(match[1], 10) : null;
    },

    get backUrl() {
      return tma.audit.url();
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok || this.user?.role !== 'owner') return;
      await this.loadLog();
    },

    async loadLog() {
      this.loading = true;
      this.loadError = null;
      try {
        const data = await this.api(AuditController.show(this.logId));
        this.log = data.log ?? null;
        if (this.log) {
          ['old_value', 'new_value'].forEach((field) => {
            const raw = this.log[field];
            if (typeof raw === 'string') {
              const trimmed = raw.trim();
              if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
                try {
                  this.log[field] = JSON.parse(trimmed);
                } catch {
                  // leave as string
                }
              }
            }
          });
        }
        if (!this.log) {
          this.loadError = 'Запись не найдена';
        }
      } catch (e) {
        this.loadError = e.status === 404 ? 'Запись не найдена' : 'Ошибка загрузки';
        console.error('Failed to load audit log:', e);
      } finally {
        this.loading = false;
      }
    },

    goBack() {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = this.backUrl;
      }
    },

    formatDate(value) {
      if (!value) return '';
      return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },

    formatOldValue(value) {
      if (value === null || value === undefined || value === '') return '—';
      return value;
    },

    formatNewValue(value) {
      if (value === null || value === undefined || value === '') return '—';
      return value;
    },

    formatFieldRaw(value) {
      return value;
    },

    formatJsonTree(value) {
      if (value === null || value === undefined || value === '') {
        return '<span class="text-fg-subtle">—</span>';
      }
      if (typeof value === 'string') {
        const trimmed = value.trim();
        if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
          try {
            value = JSON.parse(trimmed);
          } catch {
            const escaped = String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            return `<span class="text-fg">${escaped}</span>`;
          }
        } else {
          const escaped = String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
          return `<span class="text-fg">${escaped}</span>`;
        }
      }
      if (value !== null && typeof value === 'object') {
        return renderJsonTree(value);
      }
      return `<span class="text-fg">${String(value)}</span>`;
    },

    getActionLabel(type) {
      const labels = {
        create: 'Создание',
        update: 'Обновление',
        delete: 'Удаление',
        status_change: 'Смена статуса',
        assign: 'Назначение',
        panic: 'Паника',
        auth: 'Безопасность',
        export: 'Экспорт',
      };
      return labels[type] || type;
    },

    getActionColor(type) {
      const colors = {
        create: 'text-success',
        update: 'text-warning',
        delete: 'text-error',
        status_change: 'text-primary',
        assign: 'text-secondary',
        panic: 'text-error',
        auth: 'text-primary',
        export: 'text-warning',
      };
      return colors[type] || 'text-fg-muted';
    },

    getActionDescription(type) {
      const descriptions = {
        create: 'Заказ был создан',
        delete: 'Заказ был удалён',
        panic: 'Аварийное действие выполнено',
      };
      return descriptions[type] || 'Действие выполнено';
    },

    getFieldLabel(field) {
      if (!field) return '';
      const labels = {
        client_name: 'Имя клиента',
        client_phone: 'Телефон клиента',
        client_address: 'Адрес доставки',
        client_data: 'Данные клиента',
        total_price: 'Цена для клиента',
        purchase_price: 'Закупочная цена',
        courier_id: 'Курьер',
        manager_id: 'Менеджер',
        'delivery.user_id': 'Курьер',
        'delivery.type': 'Тип доставки',
        'delivery.city': 'Город доставки',
        'delivery.address': 'Адрес доставки',
        'delivery.cost': 'Оплата курьеру',
        'delivery.price': 'Стоимость доставки',
        'delivery.date': 'Дата доставки',
        'delivery.slot': 'Слот доставки',
        'delivery.pickup_point': 'Пункт выдачи',
        'delivery.courier_comment': 'Комментарий курьеру',
        'payment.method': 'Способ оплаты',
        'payment.status': 'Статус оплаты',
        'payment.transaction_id': 'ID транзакции',
        'payment.amount': 'Сумма оплаты',
        status: 'Статус',
        comment: 'Комментарий',
        order_items: 'Состав заказа',
        order_content: 'Состав заказа',
        discounts: 'Скидки',
        extra_data: 'Доп. данные',
        is_active: 'Активность',
        is_valuable: 'Ценное отправление',
        is_blacklisted: 'Чёрный список',
        blacklist_reason: 'Причина ЧС',
        api_token: 'API-токен',
        system_name: 'Имя сотрудника',
        role: 'Роль сотрудника',
        customer: 'Клиент',
        user: 'Сотрудник',
        order: 'Заказ',
        site: 'Сайт',
        product: 'Товар',
        message: 'Сообщение',
        converted_order_id: 'Конвертация в заказ',
      };
      if (field.startsWith('customer.')) {
        const sub = field.replace('customer.', '');
        const subLabels = { name: 'Имя клиента', phone: 'Телефон', email: 'Email', address: 'Адрес', city: 'Город', notes: 'Заметки', tags: 'Теги' };
        return subLabels[sub] || `Клиент (${sub})`;
      }
      if (field.startsWith('site.')) {
        const sub = field.replace('site.', '');
        const subLabels = { name: 'Название сайта', domain: 'Домен', display_name: 'Отображаемое имя', delivery_cost: 'Стоимость доставки' };
        return subLabels[sub] || `Сайт (${sub})`;
      }
      if (field.startsWith('product.')) {
        const sub = field.replace('product.', '');
        const subLabels = { name: 'Название товара', sku: 'Артикул', price: 'Цена', purchase_price: 'Себестоимость', is_active: 'Статус товара' };
        return subLabels[sub] || `Товар (${sub})`;
      }
      if (field.startsWith('lead.')) {
        const sub = field.replace('lead.', '');
        const subLabels = { name: 'Имя в заявке', phone: 'Телефон в заявке', type: 'Тип заявки' };
        return subLabels[sub] || `Заявка (${sub})`;
      }
      if (field.startsWith('setting.')) {
        return `Настройка: ${field.replace('setting.', '')}`;
      }
      return labels[field] || field;
    },

    getStatusClass(status) {
      const classes = {
        created: 'text-fg-muted',
        assigned: 'text-primary',
        at_warehouse: 'text-warning',
        completed: 'text-success',
      };
      return classes[status] || 'text-fg-muted';
    },

    getOldValueClass(field) {
      if (field === 'is_active') return 'text-fg';
      return 'text-fg';
    },

    getNewValueClass(field) {
      if (field === 'is_active') {
        return this.log?.new_value_display === 'Активен' ? 'text-success' : 'text-error';
      }
      return 'text-fg';
    },
  });
}
