import { baseInit, withTmaBase } from '../shared.js';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import OrderMessageController from '@/actions/App/Http/Controllers/OrderMessageController';
import orders from '@/routes/tma/orders';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    orderId: null,
    order: null,
    orderLoading: true,
    chatChannel: 'courier',
    chatMessages: [],
    chatLoading: false,
    chatLoadingMore: false,
    chatHasMore: false,
    chatOldestId: null,
    chatNewMessage: '',
    chatSubmitting: false,
    chatError: '',
    chatEditingMessage: null,
    chatDeleting: false,
    _chatEchoChannel: null,
    activeOrders: [],
    activeOrdersLoading: false,
    orderSearchOpen: false,
    orderSearchQuery: '',
    orderSearchResults: [],
    orderSearchLoading: false,

    get isCourier() {
      return this.user?.role === 'courier';
    },

    get isOwner() {
      return this.user?.role === 'owner';
    },

    get isManager() {
      return this.user?.role === 'manager';
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;

      const match = window.location.pathname.match(/\/tma\/orders\/(\d+)\/chat/);
      if (match) {
        this.orderId = Number(match[1]);
      }

      window.addEventListener('popstate', () => {
        const m = window.location.pathname.match(/\/tma\/orders\/(\d+)\/chat/);
        if (m) {
          const id = Number(m[1]);
          if (id && id !== this.orderId) {
            this.switchOrderChat(id, false);
          }
        }
      });

      if (this.orderId) {
        await this.loadOrderInfo();
        await this.initChat(this.isCourier ? 'courier' : 'courier');
        await this.fetchActiveOrders();
      }
    },

    async fetchActiveOrders() {
      this.activeOrdersLoading = true;
      try {
        const res = await this.api(OrderMessageController.activeChatOrders({ query: { limit: 4 } }));
        const list = res?.orders || [];
        if (this.orderId && !list.some((o) => o.id === this.orderId)) {
          list.unshift({
            id: this.orderId,
            customer_name: this.order?.customer?.name || null,
            unread_count: 0,
            status: this.order?.status || 'active',
          });
        }
        this.activeOrders = list;
      } catch (err) {
        console.error('Failed to load active chat orders:', err);
      } finally {
        this.activeOrdersLoading = false;
      }
    },

    toggleOrderSearchDropdown() {
      this.orderSearchOpen = !this.orderSearchOpen;
      if (this.orderSearchOpen) {
        this.orderSearchResults = [...this.activeOrders];
        this.$nextTick(() => {
          this.$refs.orderSearchInput?.focus();
        });
      }
    },

    closeOrderSearchDropdown() {
      this.orderSearchOpen = false;
    },

    clearOrderSearch() {
      this.orderSearchQuery = '';
      this.orderSearchResults = [...this.activeOrders];
      this.$refs.orderSearchInput?.focus();
    },

    async performOrderSearch() {
      const q = this.orderSearchQuery?.trim() || '';
      if (!q) {
        this.orderSearchResults = [...this.activeOrders];
        return;
      }

      this.orderSearchLoading = true;
      try {
        const res = await this.api(OrderMessageController.searchChatOrders({ query: { q } }));
        this.orderSearchResults = res?.orders || [];
      } catch (err) {
        console.error('Failed to search orders:', err);
        this.orderSearchResults = [];
      } finally {
        this.orderSearchLoading = false;
      }
    },

    async switchOrderChat(newOrderId, pushHistory = true) {
      if (!newOrderId || newOrderId === this.orderId) {
        this.closeOrderSearchDropdown();
        return;
      }

      this.orderId = Number(newOrderId);
      this.closeOrderSearchDropdown();
      this.orderSearchQuery = '';

      if (pushHistory) {
        const newUrl = orders.chat.url(this.orderId);
        if (window.location.pathname !== newUrl) {
          window.history.pushState({ orderId: this.orderId }, '', newUrl);
        }
      }

      await this.loadOrderInfo();
      await this.initChat(this.isCourier ? 'courier' : this.chatChannel);
      await this.fetchActiveOrders();
    },

    async loadOrderInfo() {
      if (!this.orderId) return;
      this.orderLoading = true;
      try {
        const res = await this.api(OrderController.show(this.orderId));
        this.order = res?.data || res || null;
      } catch (err) {
        console.error('Failed to load order:', err);
      } finally {
        this.orderLoading = false;
      }
    },

    async initChat(channel = 'courier') {
      this.chatChannel = channel;
      this.chatMessages = [];
      this.chatOldestId = null;
      this.chatHasMore = false;
      this.initChatEcho();
      await this.fetchChatMessages();
    },

    initChatEcho() {
      if (!window.Echo || !this.orderId) return;

      const targetChannel = `order.${this.orderId}.${this.chatChannel}`;
      if (this._chatEchoChannel === targetChannel) return;

      if (this._chatEchoChannel && typeof window.Echo?.leave === 'function') {
        window.Echo.leave(this._chatEchoChannel);
      }

      this._chatEchoChannel = targetChannel;
      try {
        const chan = window.Echo.private(this._chatEchoChannel);
        chan.listen('.OrderMessageSent', (e) => this.handleIncomingChatMessage(e));
        chan.listen('OrderMessageSent', (e) => this.handleIncomingChatMessage(e));
        chan.listen('.OrderMessageUpdated', (e) => this.handleMessageUpdated(e));
        chan.listen('OrderMessageUpdated', (e) => this.handleMessageUpdated(e));
        chan.listen('.OrderMessageDeleted', (e) => this.handleMessageDeleted(e));
        chan.listen('OrderMessageDeleted', (e) => this.handleMessageDeleted(e));
      } catch (err) {
        console.error('Echo chat subscribe failed:', err);
      }
    },

    switchChatChannel(channel) {
      if (this.chatChannel === channel) return;
      this.chatChannel = channel;
      this.chatMessages = [];
      this.chatOldestId = null;
      this.chatHasMore = false;
      this.chatError = '';
      this.initChatEcho();
      this.fetchChatMessages();
    },

    async fetchChatMessages(loadMore = false) {
      if (!this.orderId) return;

      if (loadMore) {
        if (!this.chatHasMore || this.chatLoadingMore || !this.chatOldestId) return;
        this.chatLoadingMore = true;
      } else {
        this.chatLoading = true;
      }

      try {
        const query = { channel: this.chatChannel, limit: 30 };
        if (loadMore && this.chatOldestId) {
          query.before_id = this.chatOldestId;
        }

        const res = await this.api(OrderMessageController.index(this.orderId, { query }));
        const incoming = res?.messages || [];
        this.chatHasMore = Boolean(res?.has_more);
        this.chatOldestId = res?.oldest_id || null;

        if (loadMore) {
          this.chatMessages = [...incoming, ...this.chatMessages];
        } else {
          this.chatMessages = incoming;
          this.$nextTick(() => this.scrollChatToBottom());
        }

        if (this.chatChannel === 'courier') {
          if (this.isCourier && this.order) {
            this.order.has_unread_manager_message = false;
            this.order.unread_manager_messages_count = 0;
          } else if (!this.isCourier && this.order) {
            this.order.has_unread_driver_message = false;
            this.order.unread_driver_messages_count = 0;
          }
        }
      } catch (err) {
        console.error('Failed to load chat messages:', err);
      } finally {
        this.chatLoading = false;
        this.chatLoadingMore = false;
      }
    },

    handleIncomingChatMessage(data) {
      if (!data || Number(data.order_id) !== Number(this.orderId) || data.channel !== this.chatChannel) {
        return;
      }

      const exists = this.chatMessages.some((m) => m.id === data.id);
      if (exists) return;

      const isMine = Boolean(this.user && data.sender_id && Number(this.user.id) === Number(data.sender_id));
      const isOwner = Boolean(this.user?.role === 'owner' || this.user?.is_owner);
      const isCompleted = this.order?.status === 'completed';
      this.chatMessages.push({
        id: data.id,
        order_id: data.order_id,
        channel: data.channel,
        message: data.message,
        is_mine: isMine,
        can_edit: isOwner || (isMine && !this.isCourier && !isCompleted),
        can_delete: isOwner,
        is_edited: false,
        sender: {
          name: data.sender_name || 'Пользователь',
          role: data.sender_role || 'system',
        },
        formatted_time: data.created_at ? new Date(data.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '',
      });

      this.$nextTick(() => this.scrollChatToBottom());
      this.hapticImpact?.('light');
    },

    handleMessageUpdated(data) {
      if (!data || Number(data.order_id) !== Number(this.orderId) || data.channel !== this.chatChannel) {
        return;
      }

      const msg = this.chatMessages.find((m) => m.id === data.id);
      if (msg) {
        msg.message = data.message;
        msg.is_edited = this.isOwner;
        msg.edited_at = this.isOwner ? data.edited_at : null;
      }
    },

    handleMessageDeleted(data) {
      if (!data || Number(data.order_id) !== Number(this.orderId) || data.channel !== this.chatChannel) {
        return;
      }

      this.chatMessages = this.chatMessages.filter((m) => m.id !== data.id);
      if (this.chatEditingMessage && this.chatEditingMessage.id === data.id) {
        this.cancelEditMessage();
      }
    },

    startEditMessage(msg) {
      if (!msg?.can_edit) return;
      this.chatEditingMessage = msg;
      this.chatNewMessage = msg.message || '';
      this.chatError = '';
      this.$nextTick(() => {
        this.adjustChatTextareaHeight();
        this.$refs.chatTextarea?.focus();
      });
    },

    cancelEditMessage() {
      this.chatEditingMessage = null;
      this.chatNewMessage = '';
      this.chatError = '';
      this.resetChatTextareaHeight();
    },

    adjustChatTextareaHeight() {
      const el = this.$refs.chatTextarea;
      if (!el) return;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    },

    resetChatTextareaHeight() {
      const el = this.$refs.chatTextarea;
      if (!el) return;
      el.style.height = 'auto';
    },

    async saveEditMessage() {
      if (this.chatSubmitting || !this.chatEditingMessage) return;

      const text = this.chatNewMessage?.trim() || '';
      if (!text) {
        this.chatError = 'Сообщение не может быть пустым';
        return;
      }

      if (this.chatEditingMessage && text === (this.chatEditingMessage.message || '').trim()) {
        this.cancelEditMessage();
        return;
      }

      this.chatError = '';
      this.chatSubmitting = true;

      try {
        const res = await this.api(OrderMessageController.update([this.orderId, this.chatEditingMessage.id]), {
          body: { message: text },
        });

        const updated = res?.data || res;
        const msg = this.chatMessages.find((m) => m.id === this.chatEditingMessage.id);
        if (msg) {
          msg.message = updated?.message || text;
          msg.is_edited = this.isOwner;
          msg.edited_at = this.isOwner ? (updated?.edited_at || new Date().toISOString()) : null;
        }

        this.cancelEditMessage();
        this.hapticSuccess?.();
      } catch (err) {
        console.error('Failed to update chat message:', err);
        this.chatError = err?.message || 'Ошибка при редактировании сообщения';
        this.hapticError?.();
      } finally {
        this.chatSubmitting = false;
      }
    },

    async openDeleteConfirm(msg) {
      if (!msg?.can_delete || this.chatDeleting) return;
      const text = msg.message ? (msg.message.length > 80 ? msg.message.slice(0, 80) + '...' : msg.message) : '';
      const ok = await window.confirmDialog({
        title: 'Удалить сообщение?',
        message: text,
        confirmText: 'Удалить',
        variant: 'danger',
      });
      if (!ok) return;

      this.chatDeleting = true;
      const targetId = msg.id;

      try {
        await this.api(OrderMessageController.destroy([this.orderId, targetId]));

        this.chatMessages = this.chatMessages.filter((m) => m.id !== targetId);
        if (this.chatEditingMessage && this.chatEditingMessage.id === targetId) {
          this.cancelEditMessage();
        }
        this.hapticSuccess?.();
      } catch (err) {
        console.error('Failed to delete chat message:', err);
        this.chatError = err?.message || 'Ошибка при удалении сообщения';
        this.hapticError?.();
      } finally {
        this.chatDeleting = false;
      }
    },

    async sendChatMessage() {
      if (this.chatSubmitting) return;

      if (this.order?.status === 'completed') {
        this.chatError = 'Чат закрыт для новых сообщений, так как заказ завершён';
        return;
      }

      const text = this.chatNewMessage?.trim() || '';
      if (!text) {
        this.chatError = 'Введите сообщение';
        return;
      }

      this.chatError = '';
      this.chatSubmitting = true;

      try {
        const res = await this.api(OrderMessageController.store(this.orderId), {
          body: { channel: this.chatChannel, message: text },
        });
        const created = res?.data || res;

        if (created && created.id) {
          const exists = this.chatMessages.some((m) => m.id === created.id);
          if (!exists) {
            this.chatMessages.push(created);
          }
        }

        this.chatNewMessage = '';
        this.resetChatTextareaHeight();
        this.$nextTick(() => this.scrollChatToBottom());
        this.hapticSuccess?.();
      } catch (err) {
        console.error('Failed to send chat message:', err);
        this.chatError = err?.message || 'Ошибка при отправке сообщения';
        this.hapticError?.();
      } finally {
        this.chatSubmitting = false;
      }
    },

    onChatScroll(e) {
      const el = e.target;
      if (el.scrollTop < 40 && this.chatHasMore && !this.chatLoadingMore && this.chatOldestId) {
        const prevHeight = el.scrollHeight;
        this.fetchChatMessages(true).then(() => {
          this.$nextTick(() => {
            el.scrollTop = el.scrollHeight - prevHeight;
          });
        });
      }
    },

    scrollChatToBottom() {
      const container = this.$refs.chatContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    },

    goBackToOrder() {
      if (this.orderId) {
        window.location.href = orders.show.url(this.orderId);
      } else {
        window.history.back();
      }
    },
  });
}

