import { withTmaBase, baseInit } from '../shared.js';
import AuthController from '@/actions/App/Http/Controllers/AuthController';
import SessionSecurityController from '@/actions/App/Http/Controllers/Auth/SessionSecurityController';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import EmergencyWipeController from '@/actions/App/Http/Controllers/EmergencyWipeController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  const validTabs = ['interface', 'notifications', 'security', 'system'];
  const urlParams = new URLSearchParams(window.location.search);
  const tabFromUrl = urlParams.get('tab');
  const tabFromStorage = localStorage.getItem('settings_active_tab');
  const initialTab = validTabs.includes(tabFromUrl)
    ? tabFromUrl
    : (validTabs.includes(tabFromStorage) ? tabFromStorage : 'interface');

  return withTmaBase({
    user,
    retentionDays,
    activeTab: initialTab,
    setTab(tab) {
      if (!validTabs.includes(tab)) return;
      this.activeTab = tab;
      try {
        localStorage.setItem('settings_active_tab', tab);
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
      } catch (_) {}
      this.hapticImpact?.('light');
    },
    retentionSaving: false,
    notifyNewOrders: true,
    notifySaving: false,
    notifyChatOrders: true,
    notifyChatSaving: false,
    notifyChatMessages: true,
    notifyChatMessagesSaving: false,
    notifyChatLeads: true,
    notifyChatLeadsSaving: false,
    notifyStaleOrders: true,
    notifyStaleSaving: false,
    deleteChatMessagesOnAccept: (() => {
      try {
        const cached = localStorage.getItem('delete_chat_messages_on_accept');
        return cached !== null ? (cached === 'true' || cached === '1') : false;
      } catch (_) {
        return false;
      }
    })(),
    deleteChatMessagesSaving: false,
    showWipeModal: false,
    wipeTables: [],
    hasWipePin: false,
    showPinModal: false,
    currentWipePin: '',
    newWipePin: '',
    newWipePinConfirm: '',
    pinSaving: false,
    pinError: '',
    wipePin: '',
    wipeBackups: true,
    wipePinError: '',
    wipeLoading: false,
    sessions: [],
    sessionsLoading: false,
    maxSessions: 5,
    revokingToken: null,
    revokeAllLoading: false,
    hasCloudPassword: false,
    cloudPasswordSetAt: null,

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;
      this.notifyNewOrders = this.getNotifyNewOrders?.() ?? true;
      this.notifyChatOrders = this.getNotifyChatOrders?.() ?? true;
      this.notifyChatMessages = (() => {
        try {
          const v = localStorage.getItem('notify_chat_messages');
          return v !== null ? v === 'true' : true;
        } catch (_) {
          return true;
        }
      })();
      this.notifyChatLeads = this.getNotifyChatLeads?.() ?? true;
      this.notifyStaleOrders = this.getNotifyStaleOrders?.() ?? true;
      this.deleteChatMessagesOnAccept = this.getDeleteChatMessagesOnAccept?.() ?? this.deleteChatMessagesOnAccept;

      const tasks = [this.loadAllSettings(), this.loadSessions(), this.loadCloudPasswordStatus()];
      if (this.user?.role === 'owner') {
        tasks.push(this.loadWipeTables(), this.loadWipeStatus());
      }
      await Promise.all(tasks);
    },

    async loadCloudPasswordStatus() {
      try {
        const data = await this.api(AuthController.me());
        if (data) {
          this.hasCloudPassword = !!data.has_cloud_password;
          this.cloudPasswordSetAt = data.cloud_password_set_at || null;
        }
      } catch (e) {
        console.error('Failed to load cloud password status:', e);
      }
    },

    async loadSessions() {
      this.sessionsLoading = true;
      try {
        const data = await this.api(SessionSecurityController.sessions());
        this.sessions = data?.sessions || [];
        this.maxSessions = data?.max_sessions || 5;
      } catch (e) {
        console.error('Failed to load sessions:', e);
      } finally {
        this.sessionsLoading = false;
      }
    },

    async revokeSession(token) {
      if (this.revokingToken) return;
      this.revokingToken = token;
      try {
        await this.api(SessionSecurityController.revokeSession(), {
          body: JSON.stringify({ token }),
        });
        this.sessions = this.sessions.filter((s) => s.token !== token);
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Сессия завершена' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка', type: 'error' });
      } finally {
        this.revokingToken = null;
      }
    },

    async openRevokeAllConfirm() {
      if (this.revokeAllLoading) return;
      const ok = await window.confirmDialog({
        title: 'Завершить все сессии?',
        message: 'Все активные сессии на других устройствах будут принудительно завершены. Текущая сессия останется активной.',
        confirmText: 'Завершить все',
        variant: 'danger',
      });
      if (!ok) return;

      this.revokeAllLoading = true;
      try {
        await this.api(SessionSecurityController.revokeSession(), {
          body: JSON.stringify({ revoke_all: true }),
        });
        this.sessions = this.sessions.filter((s) => s.is_current);
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Все другие сессии завершены' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка', type: 'error' });
      } finally {
        this.revokeAllLoading = false;
      }
    },

    revokeAllSessions() {
      this.openRevokeAllConfirm();
    },

    async loadAllSettings() {
      try {
        const data = await this.api(SettingsController.index());
        const settings = data?.settings || {};

        if (settings.notify_new_orders && typeof settings.notify_new_orders.value === 'boolean') {
          this.notifyNewOrders = settings.notify_new_orders.value;
          localStorage.setItem('notify_new_orders', String(this.notifyNewOrders));
        }

        if (settings.notify_chat_orders && typeof settings.notify_chat_orders.value === 'boolean') {
          this.notifyChatOrders = settings.notify_chat_orders.value;
          localStorage.setItem('notify_chat_orders', String(this.notifyChatOrders));
        }

        if (settings.notify_chat_messages && typeof settings.notify_chat_messages.value === 'boolean') {
          this.notifyChatMessages = settings.notify_chat_messages.value;
          localStorage.setItem('notify_chat_messages', String(this.notifyChatMessages));
        }

        if (settings.notify_chat_leads && typeof settings.notify_chat_leads.value === 'boolean') {
          this.notifyChatLeads = settings.notify_chat_leads.value;
          localStorage.setItem('notify_chat_leads', String(this.notifyChatLeads));
        }

        if (settings.notify_stale_orders && typeof settings.notify_stale_orders.value === 'boolean') {
          this.notifyStaleOrders = settings.notify_stale_orders.value;
          localStorage.setItem('notify_stale_orders', String(this.notifyStaleOrders));
        }

        if (settings.delete_chat_messages_on_accept && typeof settings.delete_chat_messages_on_accept.value === 'boolean') {
          this.deleteChatMessagesOnAccept = settings.delete_chat_messages_on_accept.value;
          localStorage.setItem('delete_chat_messages_on_accept', String(this.deleteChatMessagesOnAccept));
        }

        if (settings.retention_days && typeof settings.retention_days.value === 'number') {
          this.retentionDays = settings.retention_days.value;
        }
      } catch (e) {
        console.error('Failed to load settings:', e);
      }
    },

    async loadDeleteChatMessagesSetting() {
      try {
        const data = await this.api(SettingsController.show('delete_chat_messages_on_accept'));
        if (data && typeof data.value === 'boolean') {
          this.deleteChatMessagesOnAccept = data.value;
          localStorage.setItem('delete_chat_messages_on_accept', String(this.deleteChatMessagesOnAccept));
        }
      } catch (e) {
        console.error('Failed to load delete_chat_messages_on_accept:', e);
      }
    },

    async saveDeleteChatMessagesOnAccept() {
      if (!this.user || this.deleteChatMessagesSaving) return;

      this.deleteChatMessagesSaving = true;
      localStorage.setItem('delete_chat_messages_on_accept', String(this.deleteChatMessagesOnAccept));
      try {
        await this.api(SettingsController.update('delete_chat_messages_on_accept'), {
          body: JSON.stringify({
            value: this.deleteChatMessagesOnAccept,
            scope: 'global',
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save delete_chat_messages_on_accept:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.deleteChatMessagesSaving = false;
      }
    },

    async loadNotifySetting() {
      try {
        const data = await this.api(SettingsController.show('notify_new_orders'));
        if (data && typeof data.value === 'boolean') {
          this.notifyNewOrders = data.value;
          localStorage.setItem('notify_new_orders', String(data.value));
        }
      } catch (e) {
        console.error('Failed to load notify setting:', e);
      }
    },

    async loadNotifyChatSetting() {
      try {
        const data = await this.api(SettingsController.show('notify_chat_orders'));
        if (data && typeof data.value === 'boolean') {
          this.notifyChatOrders = data.value;
          localStorage.setItem('notify_chat_orders', String(data.value));
        }
      } catch (e) {
        console.error('Failed to load notify chat setting:', e);
      }
    },

    async loadNotifyStaleSetting() {
      try {
        const data = await this.api(SettingsController.show('notify_stale_orders'));
        if (data && typeof data.value === 'boolean') {
          this.notifyStaleOrders = data.value;
          localStorage.setItem('notify_stale_orders', String(data.value));
        }
      } catch (e) {
        console.error('Failed to load notify stale setting:', e);
      }
    },

    async saveNotifyNewOrders() {
      if (!this.user || this.notifySaving) return;

      this.notifySaving = true;
      try {
        localStorage.setItem('notify_new_orders', String(this.notifyNewOrders));
        await this.api(SettingsController.update('notify_new_orders'), {
          body: JSON.stringify({
            value: this.notifyNewOrders,
            scope: 'user',
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save notify setting:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.notifySaving = false;
      }
    },

    async saveNotifyChatOrders() {
      if (!this.user || this.notifyChatSaving) return;

      this.notifyChatSaving = true;
      try {
        localStorage.setItem('notify_chat_orders', String(this.notifyChatOrders));
        await this.api(SettingsController.update('notify_chat_orders'), {
          body: JSON.stringify({
            value: this.notifyChatOrders,
            scope: 'user',
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save notify chat setting:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.notifyChatSaving = false;
      }
    },

    async saveNotifyChatMessages() {
      if (!this.user || this.notifyChatMessagesSaving) return;

      this.notifyChatMessagesSaving = true;
      try {
        localStorage.setItem('notify_chat_messages', String(this.notifyChatMessages));
        await this.api(SettingsController.update('notify_chat_messages'), {
          body: JSON.stringify({
            value: this.notifyChatMessages,
            scope: 'user',
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save notify chat messages setting:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.notifyChatMessagesSaving = false;
      }
    },

    async saveNotifyChatLeads() {
      if (!this.user || this.notifyChatLeadsSaving) return;

      this.notifyChatLeadsSaving = true;
      try {
        localStorage.setItem('notify_chat_leads', String(this.notifyChatLeads));
        await this.api(SettingsController.update('notify_chat_leads'), {
          body: JSON.stringify({
            value: this.notifyChatLeads,
            scope: 'user',
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save notify chat leads setting:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.notifyChatLeadsSaving = false;
      }
    },

    async saveNotifyStaleOrders() {
      if (!this.user || this.notifyStaleSaving) return;

      this.notifyStaleSaving = true;
      try {
        localStorage.setItem('notify_stale_orders', String(this.notifyStaleOrders));
        await this.api(SettingsController.update('notify_stale_orders'), {
          body: JSON.stringify({
            value: this.notifyStaleOrders,
            scope: 'user',
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save notify stale setting:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.notifyStaleSaving = false;
      }
    },

    async loadSetting() {
      try {
        const data = await this.api(SettingsController.show('retention_days'));
        this.retentionDays = data.value;
      } catch (e) {
        console.error('Failed to load setting:', e);
      }
    },

    async saveRetention() {
      if (!this.user) return;
      if (this.retentionSaving) return;

      const authOk = await this.requestBiometricAuth('Подтвердите изменение срока хранения данных');
      if (!authOk) {
        await this.loadSetting();
        return;
      }

      this.retentionSaving = true;
      try {
        await this.api(SettingsController.updateRetention(), {
          body: JSON.stringify({ retention_days: this.retentionDays }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Настройка сохранена' });
      } catch (e) {
        console.error('Failed to save retention:', e);
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
      } finally {
        this.retentionSaving = false;
      }
    },

    async loadWipeTables() {
      if (this.user?.role !== 'owner') return;
      this.wipeTables = [
        { name: 'orders', label: 'Заказы', count: 0 },
        { name: 'action_logs', label: 'Журнал аудита', count: 0 },
        { name: 'financial_stats', label: 'Финансовые отчёты', count: 0 },
        { name: 'users', label: 'Пользователи', count: 0 },
        { name: 'sites', label: 'Магазины', count: 0 },
      ];
    },

    getTableLabel(name) {
      const labels = {
        orders: 'Заказы',
        action_logs: 'Журнал аудита',
        financial_stats: 'Финансовые отчёты',
        users: 'Пользователи',
        sites: 'Магазины',
      };
      return labels[name] || name;
    },

    get selectedTablesCount() {
      return this.wipeTables.filter((t) => t.selected).length;
    },

    get wipePreviewText() {
      const selected = this.wipeTables.filter((t) => t.selected);
      if (selected.length === 0) return '';
      const parts = selected.map((t) => `${t.label}: ${t.count} записей`);
      return parts.join(' · ');
    },

    formatNumber(value) {
      return Number(value).toLocaleString('ru-RU');
    },

    async loadWipeStatus() {
      if (this.user?.role !== 'owner') return;
      try {
        const res = await this.api(EmergencyWipeController.status());
        this.hasWipePin = Boolean(res?.has_pin);
      } catch (e) {
        console.error('Failed to load wipe status:', e);
      }
    },

    openPinModal() {
      this.currentWipePin = '';
      this.newWipePin = '';
      this.newWipePinConfirm = '';
      this.pinError = '';
      this.showPinModal = true;
    },

    async saveWipePin() {
      this.pinError = '';
      const pin = String(this.newWipePin || '').trim();
      const pinConfirm = String(this.newWipePinConfirm || '').trim();
      const currentPin = String(this.currentWipePin || '').trim();

      if (!pin) {
        this.pinError = 'Придумайте PIN-код';
        return;
      }
      if (pin.length < 4) {
        this.pinError = 'PIN-код должен быть не менее 4 символов';
        return;
      }
      if (pin !== pinConfirm) {
        this.pinError = 'PIN-коды не совпадают';
        return;
      }
      if (this.hasWipePin && !currentPin) {
        this.pinError = 'Введите текущий PIN-код';
        return;
      }

      this.pinSaving = true;

      const reason = this.hasWipePin
        ? 'Подтвердите изменение PIN-кода биометрией'
        : 'Подтвердите создание PIN-кода биометрией';
      const authOk = await this.requestBiometricAuth(reason, true);
      if (!authOk) {
        this.pinSaving = false;
        this.pinError = 'Биометрическая проверка не пройдена';
        this.$dispatch('notify', { message: 'Биометрическая проверка не пройдена', type: 'error' });
        return;
      }

      try {
        const payload = {
          pin,
          pin_confirmation: pinConfirm,
        };
        if (this.hasWipePin) {
          payload.current_pin = currentPin;
        }

        const res = await this.api(EmergencyWipeController.setPin(), {
          body: JSON.stringify(payload),
        });

        this.hasWipePin = true;
        this.showPinModal = false;
        this.currentWipePin = '';
        this.newWipePin = '';
        this.newWipePinConfirm = '';
        this.pinError = '';
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: res?.message || 'PIN-код успешно сохранен', type: 'success' });
      } catch (e) {
        console.error('Failed to save wipe pin:', e);
        this.hapticError?.();
        this.pinError = e?.errors?.current_pin?.[0] || e?.errors?.pin?.[0] || e?.message || 'Ошибка сохранения PIN-кода';
        this.$dispatch('notify', { message: this.pinError, type: 'error' });
      } finally {
        this.pinSaving = false;
      }
    },

    showWipeConfirm() {
      if (!this.hasWipePin) {
        this.$dispatch('notify', { message: 'Сначала задайте PIN-код для защиты кнопки паники', type: 'warn' });
        this.openPinModal();
        return;
      }

      this.wipePin = '';
      this.wipeBackups = true;
      this.wipePinError = '';
      this.wipeLoading = false;
      this.showWipeModal = true;
    },

    async executeWipe() {
      const pin = String(this.wipePin || '').trim();
      if (!pin) {
        this.wipePinError = 'Введите PIN-код';
        return;
      }

      this.wipeLoading = true;
      this.wipePinError = '';

      let wipeToken = null;
      try {
        const initRes = await this.api(EmergencyWipeController.initiate(), {
          body: JSON.stringify({ pin }),
        });
        wipeToken = initRes?.wipe_token;
      } catch (err) {
        console.error('Initiate wipe failed:', err);
        this.hapticError?.();
        this.wipePinError = err?.errors?.pin?.[0] || err?.message || 'Неверный PIN-код';
        this.$dispatch('notify', { message: this.wipePinError, type: 'error' });
        this.wipeLoading = false;
        return;
      }

      if (!wipeToken) {
        this.wipeLoading = false;
        this.wipePinError = 'Токен подтверждения не получен';
        this.$dispatch('notify', { message: 'Токен подтверждения не получен', type: 'error' });
        return;
      }

      const authOk = await this.requestBiometricAuth('Подтвердите экстренную очистку базы данных', true);
      if (!authOk) {
        this.wipeLoading = false;
        this.showWipeModal = false;
        this.wipePin = '';
        this.$dispatch('notify', { message: 'Биометрическая проверка не пройдена. Стирание отменено.', type: 'error' });
        return;
      }

      try {
        await this.api(EmergencyWipeController.execute(), {
          body: JSON.stringify({
            wipe_token: wipeToken,
            confirm_code: pin,
            delete_backups: Boolean(this.wipeBackups),
          }),
        });

        this.showWipeModal = false;
        this.wipePin = '';
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Аварийное стирание успешно выполнено', type: 'success' });
        await this.loadWipeTables?.();
      } catch (e) {
        console.error('Wipe execution failed:', e);
        this.hapticError?.();
        this.wipePinError = e?.message || 'Ошибка при стирании';
        this.$dispatch('notify', { message: 'Ошибка при стирании', type: 'error' });
      } finally {
        this.wipeLoading = false;
      }
    },
  });
}
