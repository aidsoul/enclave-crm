import { baseInit, withTmaBase } from '../shared.js';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import orders from '@/routes/tma/orders';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    logs: [],
    logsPage: 1,
    logsLastPage: 1,
    logsTotal: 0,
    logsLoading: true,
    currentFilter: 'all',
    orderNumber: '',

    get logsHasMore() {
      return this.logsPage < this.logsLastPage;
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok || this.user?.role !== 'owner') return;
      if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const orderNum = params.get('order_number') || params.get('order_id');
        if (orderNum) {
          this.orderNumber = orderNum;
        }
      }
      await this.loadLogs(true);
    },

    goBack() {
      if (window.history.length > 1) {
        window.history.back();
      } else if (this.orderNumber) {
        window.location.href = orders.show.url(this.orderNumber);
      } else {
        window.location.href = '/tma';
      }
    },

    async loadLogs(reset = false) {
      if (this.logsLoading && !reset) return;

      if (reset) {
        this.logs = [];
        this.logsPage = 1;
      } else {
        this.logsPage += 1;
      }

      this.logsLoading = true;
      try {
        const filterMap = {
          all: '',
          creates: 'creates',
          updates: 'updates',
          statuses: 'statuses',
          edited: 'edited',
        };
        const query = filterMap[this.currentFilter] ?? '';
        const queryParamsObj = { page: this.logsPage, per_page: 20 };
        if (query) {
          queryParamsObj.filter = query;
        }
        if (this.orderNumber) {
          queryParamsObj.order_number = this.orderNumber;
        }
        const res = await this.api(AuditController.index({ query: queryParamsObj }));
        const items = res?.logs ?? [];

        if (this.logsPage === 1) {
          this.logs = items;
        } else {
          this.logs = [...this.logs, ...items];
        }

        this.logsPage = res?.current_page || this.logsPage;
        this.logsLastPage = res?.last_page || this.logsLastPage;
        this.logsTotal = res?.total ?? this.logsTotal;
      } catch (e) {
        console.error('Failed to load audit logs:', e);
        this.logs = [];
      } finally {
        this.logsLoading = false;
      }
    },

    setFilter(filter) {
      this.currentFilter = filter;
      this.logsTotal = 0;
      this.loadLogs(true);
    },

    getActionLabel(type) {
      const labels = {
        create: 'Создан',
        update: 'Обновлён',
        delete: 'Удалён',
        status_change: 'Статус',
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

    getLogTitle(log) {
      if (log.action_type === 'auth') return 'Авторизация: ' + (log.field_name || '');
      if (log.action_type === 'export') return 'Экспорт данных';
      if (log.field_name === 'product_variant' || log.field_name?.startsWith('product_variant.')) return 'Вариант товара';
      if (log.order) return `Заказ #${log.order.id}`;
      if (log.order_id) return `Заказ #${log.order_id} (удалён)`;
      if (log.lead) return `Заявка #${log.lead.id}` + (log.lead.type_label ? ` (${log.lead.type_label})` : '');
      if (log.customer) return `Клиент: ${log.customer.name || log.customer.phone || '#' + log.customer.id}`;
      if (log.target_user) return `Сотрудник: ${log.target_user.system_name}`;
      if (log.site) return `Сайт: ${log.site.name}`;
      if (log.action_type === 'panic') return 'Пользователь заблокирован';
      if (log.field_name === 'lead') return 'Заявка с сайта';
      if (log.field_name?.startsWith('setting.')) return `Настройка: ${log.field_name.replace('setting.', '')}`;
      return 'Запись журнала';
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
        chat_message: 'Сообщение в чате',
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
  });
}
