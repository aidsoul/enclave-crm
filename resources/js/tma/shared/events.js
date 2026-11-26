import orders from '@/routes/tma/orders';
import leads from '@/routes/tma/leads';

export const eventsMixin = {
  handleSseEvent(event) {
    const data = event.detail;
    switch (data.event) {
      case 'OrderCreated':
        this.handleOrderCreated(data);
        break;
      case 'OrderAssigned':
        this.handleOrderAssigned(data);
        break;
      case 'OrderStatusChanged':
        this.handleOrderStatusChanged(data);
        break;
      case 'UserRegisteredViaInvitation':
        if (this.user?.role === 'owner') {
          if (this.getNotifyNewOrders()) {
            this.dispatchNotify({ message: 'Новый пользователь зарегистрировался' });
          }
          this.loadDashboard?.();
        }
        break;
      case 'Panic':
        if (!data.user_map_id || String(data.user_map_id) === String(this.user?.telegram_user_id)) {
          this.handlePanic(data);
        }
        break;
    }
  },

  updateOrderStatus(orderId, status) {
    const order = this.orders?.find?.((o) => o.id === orderId);
    if (order) {
      order.status = status;
    }
  },

  handlePanic(data) {
    if (this._isHandlingPanic) return;
    this._isHandlingPanic = true;

    console.warn('PANIC triggered: user access blocked', {
      user: this.user,
      data,
      time: new Date().toISOString(),
      url: window.location.href,
    });

    try {
      localStorage.removeItem('tma_token');
      sessionStorage.clear();
    } catch (_) {}
    this.user = null;

    try {
      window.Echo?.disconnect?.();
    } catch (_) {}

    const tg = window.Telegram?.WebApp;
    if (tg?.close) {
      try { tg.close(); } catch (_) {}
    }

    window.location.replace('/blocked');
  },

  handleOrderCreated(data) {
    const order = data?.order;
    if (!order) return;

    this.onLiveOrderCreated?.(order);

    if (this.getNotifyNewOrders()) {
      const siteStr = this.formatSite(order.site);
      const siteSuffix = siteStr ? ` (${siteStr})` : '';
      this.dispatchNotify({
        message: 'Новый заказ #' + order.id + siteSuffix,
        url: orders.show.url(order.id),
      });
      try {
        window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('heavy');
        navigator.vibrate?.(100);
      } catch (_) {}
    }
  },

  handleLeadCreated(data) {
    const lead = data?.lead;
    if (!lead) return;

    this.onLiveLeadCreated?.(lead);

    if (Array.isArray(this.leads)) {
      const exists = this.leads.some((l) => l.id === lead.id);
      if (!exists) {
        this.leads = [lead, ...this.leads];
        if (typeof this.leadsTotal === 'number') {
          this.leadsTotal += 1;
        }
      }
    }

    if (this.getNotifyNewOrders() && this.getNotifyChatLeads()) {
      const clientName = lead.name || lead.formatted_phone || 'Новый клиент';
      const typeStr = lead.type_label || 'Заявка с сайта';
      const siteStr = lead.site?.name ? ` (${lead.site.name})` : '';
      this.dispatchNotify({
        message: `Новая заявка: ${typeStr} — ${clientName}${siteStr}`,
        url: leads.index.url(),
      });
      try {
        window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('heavy');
        navigator.vibrate?.(100);
      } catch (_) {}
    }
  },

  handleOrderAssigned(data) {
    if (!data) return;
    const order = data.order || {
      id: data.order_id,
      status: data.status || 'assigned',
    };
    const orderId = order.id || data.order_id;
    if (!orderId) return;

    if (this.onLiveOrderAssigned) {
      this.onLiveOrderAssigned(order, data);
    } else if (Array.isArray(this.orders)) {
      const exists = this.orders.some((o) => o.id === orderId);
      if (!exists) {
        this.orders = [order, ...this.orders];
        if (typeof this.ordersTotal === 'number') {
          this.ordersTotal += 1;
        }
      }
    } else {
      this.activeOrder = order;
    }

    if (this.getNotifyNewOrders()) {
      this.dispatchNotify({
        message: 'Вам назначен заказ #' + orderId,
        url: orders.show.url(orderId),
      });
      try {
        window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
      } catch (_) {}
    }

    if (this.orderId && Number(this.orderId) === Number(orderId)) {
      this.loadOrder?.();
      this.loadComments?.();
    }
  },

  handleOrderStatusChanged(data) {
    this.onLiveOrderStatusChanged?.(data);
    this.updateOrderStatus?.(data?.order_id, data?.status);
  },

  initEcho() {
    if (this._echoInitialized) return;
    this._echoInitialized = true;

    if (!this.user?.telegram_user_id) return;
    if (!window.Echo) {
      console.error('Laravel Echo not loaded');
      return;
    }

    try {
      const userChannel = window.Echo.private(`user-${this.user.telegram_user_id}`);
      const onPanic = (e) => this.handlePanic(e);
      userChannel.listen('.Panic', onPanic);
      userChannel.listen('Panic', onPanic);

      if (this.user?.role === 'owner' || this.user?.role === 'manager') {
        const ordersChannel = window.Echo.private('orders-live');
        ordersChannel.listen('.OrderCreated', (e) => {
          this.handleOrderCreated(e);
          this.loadDashboard?.();
        });
        ordersChannel.listen('.OrderStatusChanged', (e) => {
          this.handleOrderStatusChanged(e);
          this.loadDashboard?.();
        });
        ordersChannel.listen('.LeadCreated', (e) => this.handleLeadCreated(e));
        ordersChannel.listen('.UserRegisteredViaInvitation', (e) => this.handleSseEvent({ detail: e }));
        ordersChannel.error((err) => console.error('[TMA] orders-live subscription error:', err));
      }

      if (this.user?.role === 'courier') {
        userChannel.listen('.OrderAssigned', (e) => this.handleOrderAssigned(e));
        userChannel.listen('OrderAssigned', (e) => this.handleOrderAssigned(e));
        userChannel.listen('.OrderStatusChanged', (e) => this.handleOrderStatusChanged(e));
        userChannel.listen('OrderStatusChanged', (e) => this.handleOrderStatusChanged(e));
      }

      userChannel.error((err) => console.error('Echo channel error', err));
    } catch (e) {
      console.error('Failed to init Echo channel:', e);
    }
  },
};
