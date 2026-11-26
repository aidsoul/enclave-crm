import SettingsController from '@/actions/App/Http/Controllers/SettingsController';

export const notificationsMixin = {
  getNotifyNewOrders() {
    const cached = localStorage.getItem('notify_new_orders');
    if (cached !== null) {
      return cached === 'true' || cached === '1';
    }
    return true;
  },

  getNotifyApp() {
    const cached = localStorage.getItem('notify_app');
    if (cached !== null) {
      return cached === 'true' || cached === '1';
    }
    return true;
  },

  getNotifyChatOrders() {
    const cached = localStorage.getItem('notify_chat_orders');
    if (cached !== null) {
      return cached === 'true' || cached === '1';
    }
    return true;
  },

  getNotifyChatLeads() {
    const cached = localStorage.getItem('notify_chat_leads');
    if (cached !== null) {
      return cached === 'true' || cached === '1';
    }
    return true;
  },

  getDeleteChatMessagesOnAccept() {
    const cached = localStorage.getItem('delete_chat_messages_on_accept');
    if (cached !== null) {
      return cached === 'true' || cached === '1';
    }
    return false;
  },

  getNotifyStaleOrders() {
    const cached = localStorage.getItem('notify_stale_orders');
    if (cached !== null) {
      return cached === 'true' || cached === '1';
    }
    return true;
  },

  async loadNotificationSetting(force = false) {
    if (!this.user) return;
    if (!force && localStorage.getItem('notify_new_orders') !== null) return;
    try {
      const res = await this.api(SettingsController.index());
      if (res?.settings) {
        for (const [key, item] of Object.entries(res.settings)) {
          if (item && item.value !== undefined) {
            localStorage.setItem(key, String(item.value));
          }
        }
      }
    } catch (_) {}
  },

  notifyToast(message, type = 'success') {
    if (typeof this.$dispatch === 'function') {
      try {
        this.$dispatch('notify', { message, type });
        return;
      } catch (_) {}
    }
    window.dispatchEvent(new CustomEvent('notify', { detail: { message, type } }));
  },

  dispatchNotify(detail) {
    if (!this.getNotifyNewOrders()) return;
    if (typeof this.$dispatch === 'function') {
      try {
        this.$dispatch('notify', detail);
        return;
      } catch (_) {}
    }
    window.dispatchEvent(new CustomEvent('notify', { detail }));
  },
};
