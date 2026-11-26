import OrderMessageController from '@/actions/App/Http/Controllers/OrderMessageController';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import orders from '@/routes/tma/orders';
import tma from '@/routes/tma';

export const floatingChatMixin = {
  floatingChatEnabled: (() => {
    try {
      const stored = localStorage.getItem('tma_floating_chat_icon');
      return stored === null ? true : stored === '1';
    } catch (_) {
      return true;
    }
  })(),
  floatingChatUnreadCount: 0,
  floatingChatLatestOrderId: null,
  _floatingChatInterval: null,
  _floatingChatEchoBound: false,
  _floatingChatVisibilityHandler: null,

  get isChatPage() {
    return typeof window !== 'undefined' && /\/tma\/orders\/\d+\/chat/.test(window.location.pathname);
  },

  get isCourier() {
    return this.user?.role === 'courier';
  },

  get isManager() {
    return this.user?.role === 'manager';
  },

  get isOwner() {
    return this.user?.role === 'owner';
  },

  async initFloatingChat() {
    if (this.isChatPage) return;

    try {
      const stored = localStorage.getItem('tma_floating_chat_icon');
      if (stored !== null) {
        this.floatingChatEnabled = stored === '1';
      }
    } catch (_) {}

    if (!this.floatingChatEnabled) {
      return;
    }

    if (!this.user?.session_token && !localStorage.getItem('tma_token')) {
      return;
    }

    await this.fetchFloatingChatSummary();

    if (!this._floatingChatInterval) {
      this._floatingChatInterval = setInterval(() => {
        if (this.floatingChatEnabled && !this.isChatPage) {
          this.fetchFloatingChatSummary();
        }
      }, 30000);
    }

    if (!this._floatingChatVisibilityHandler) {
      this._floatingChatVisibilityHandler = () => {
        if (document.hidden) {
          if (this._floatingChatInterval) {
            clearInterval(this._floatingChatInterval);
            this._floatingChatInterval = null;
          }
        } else if (this.floatingChatEnabled && !this.isChatPage && !this._floatingChatInterval) {
          this._floatingChatInterval = setInterval(() => {
            if (this.floatingChatEnabled && !this.isChatPage) {
              this.fetchFloatingChatSummary();
            }
          }, 30000);
        }
      };
      document.addEventListener('visibilitychange', this._floatingChatVisibilityHandler);
    }

    this.bindFloatingChatEcho();
  },

  bindFloatingChatEcho() {
    if (this._floatingChatEchoBound || !window.Echo || !this.user) return;

    try {
      const isOwnerOrManager = this.user.role === 'owner' || this.user.role === 'manager';
      if (isOwnerOrManager) {
        const chan = window.Echo.private('orders-live');
        chan.listen('.OrderMessageSent', () => this.fetchFloatingChatSummary());
        chan.listen('OrderMessageSent', () => this.fetchFloatingChatSummary());
      }

      if (this.user.telegram_user_id) {
        const userChan = window.Echo.private(`user-${this.user.telegram_user_id}`);
        userChan.listen('.OrderMessageSent', () => this.fetchFloatingChatSummary());
        userChan.listen('OrderMessageSent', () => this.fetchFloatingChatSummary());
      }

      this._floatingChatEchoBound = true;
    } catch (_) {}
  },

  async fetchFloatingChatSummary() {
    if (!this.floatingChatEnabled || this.isChatPage) return;

    try {
      const res = await this.api(OrderMessageController.unreadSummary());
      if (res && typeof res.unread_count === 'number') {
        this.floatingChatUnreadCount = res.unread_count;
        this.floatingChatLatestOrderId = res.latest_order_id || null;
      }
    } catch (_) {}
  },

  openFloatingChat() {
    const match = window.location.pathname.match(/\/tma\/orders\/(\d+)/);
    if (match && !this.isChatPage) {
      window.location.href = orders.chat.url(match[1]);
      return;
    }

    if (this.floatingChatLatestOrderId) {
      window.location.href = orders.chat.url(this.floatingChatLatestOrderId);
      return;
    }

    if (this.isCourier) {
      window.location.href = tma.role.url('courier');
    } else {
      window.location.href = orders.index.url();
    }
  },

  toggleFloatingChatIcon(enable) {
    const target = enable !== undefined ? Boolean(enable) : !this.floatingChatEnabled;
    this.floatingChatEnabled = target;

    try {
      localStorage.setItem('tma_floating_chat_icon', target ? '1' : '0');
    } catch (_) {}

    if (target) {
      this.initFloatingChat();
    } else {
      if (this._floatingChatInterval) {
        clearInterval(this._floatingChatInterval);
        this._floatingChatInterval = null;
      }
      if (this._floatingChatVisibilityHandler) {
        document.removeEventListener('visibilitychange', this._floatingChatVisibilityHandler);
        this._floatingChatVisibilityHandler = null;
      }
      this.floatingChatUnreadCount = 0;
      this.floatingChatLatestOrderId = null;
    }

    this.api?.(SettingsController.update('floating_chat_icon'), {
      body: JSON.stringify({ value: target, scope: 'user' }),
    })?.catch(() => {});
  },
};

