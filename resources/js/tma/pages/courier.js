import { baseInit, withTmaBase } from '../shared.js';
import { compressImageFiles } from '../shared/image-compression.js';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import CourierHandoverController from '@/actions/App/Http/Controllers/CourierHandoverController';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import {
  setOfflineUserId,
  getCachedOrders,
  saveOrdersCache,
  updateCachedOrder,
  removeCachedOrder,
  getCachedShiftSummary,
  saveShiftSummaryCache,
  queueAction,
  getPendingActions,
  getPendingActionsCount,
  isTransientError,
} from '../shared/courier-offline.js';
import { CourierSyncManager } from '../shared/courier-sync.js';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    orders: [],
    ordersLoading: true,
    shiftSummary: { completed_deliveries: 0, total_collected: 0, cash_amount: 0, terminal_amount: 0, payout_amount: 0 },
    showCompleteModal: false,
    pendingCompleteOrderId: null,
    showProofPhotoModal: false,
    proofPhotoModalUrl: '',
    proofUploading: false,
    deletingProof: false,
    movingAtWarehouse: false,
    completingOrder: false,
    showSignatureModal: false,
    signatureTargetOrderId: null,
    signatureUploading: false,
    showDeleteSignatureModal: false,
    deleteSignatureTargetOrderId: null,
    showCameraModal: false,
    cameraLoading: false,
    cameraError: null,
    cameraFacingMode: 'environment',
    capturedBlob: null,
    capturedPreviewUrl: null,
    _cameraStream: null,
    cameraTargetOrderId: null,

    // Courier Handover State (Force Majeure)
    showHandoverModal: false,
    showHandoverQrModal: false,
    showScanHandoverModal: false,
    showHandoverPreviewModal: false,
    handoverLoading: false,
    handoverSubmitting: false,
    activeHandover: null,
    transferableOrders: [],
    handoverForm: {
      order_ids: [],
      transfer_cash: true,
      declared_cash: 0,
      reason: 'vehicle_breakdown',
      reason_comment: '',
    },
    handoverReceiveForm: {
      code: '',
      actual_cash: 0,
      discrepancy_reason: '',
    },
    handoverPreviewData: null,

    // Offline & Sync state
    isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
    isSyncing: false,
    pendingSyncCount: 0,
    isOfflineMode: false,
    syncManager: null,
    _trackedBlobUrls: new Set(),
    sortBySlot: true,
    currentTime: Date.now(),
    _timeTicker: null,

    toggleRouteSort() {
      this.sortBySlot = !this.sortBySlot;
      this.hapticImpact?.('light');
    },

    startTimeTicker() {
      if (this._timeTicker) return;
      this._timeTicker = setInterval(() => {
        this.currentTime = Date.now();
      }, 60000);
    },

    stopTimeTicker() {
      if (this._timeTicker) {
        clearInterval(this._timeTicker);
        this._timeTicker = null;
      }
    },

    getCourierSlotUrgency(order) {
      return this.getSlotUrgency(order, this.currentTime);
    },

    get displayOrders() {
      if (!Array.isArray(this.orders)) return [];
      if (!this.sortBySlot) {
        return this.orders;
      }
      const slotCache = new WeakMap();
      const getSlot = (ord) => {
        if (!ord || typeof ord !== 'object') return { startMinutes: Infinity, endMinutes: Infinity };
        if (slotCache.has(ord)) return slotCache.get(ord);
        const del = Array.isArray(ord.delivery) ? ord.delivery[0] : ord.delivery;
        const parsed = this.parseDeliverySlot(del?.delivery_slot);
        slotCache.set(ord, parsed);
        return parsed;
      };

      return [...this.orders].sort((a, b) => {
        const delA = Array.isArray(a.delivery) ? a.delivery[0] : a.delivery;
        const delB = Array.isArray(b.delivery) ? b.delivery[0] : b.delivery;

        const dateA = delA?.delivery_date ? String(delA.delivery_date).slice(0, 10) : '';
        const dateB = delB?.delivery_date ? String(delB.delivery_date).slice(0, 10) : '';

        // 1. Delivery date: earlier dates first (today before tomorrow)
        if (dateA && dateB && dateA !== dateB) {
          return dateA.localeCompare(dateB);
        }
        if (dateA && !dateB) return -1;
        if (!dateA && dateB) return 1;

        // 2. Delivery slot start time
        const slotA = getSlot(a);
        const slotB = getSlot(b);

        if (slotA.startMinutes !== slotB.startMinutes) {
          return slotA.startMinutes - slotB.startMinutes;
        }

        // 3. Delivery slot end time (narrower window earlier)
        if (slotA.endMinutes !== slotB.endMinutes) {
          return slotA.endMinutes - slotB.endMinutes;
        }

        // 4. Status priority: 'at_warehouse' (already with courier) over 'assigned'
        if (a.status !== b.status) {
          if (a.status === 'at_warehouse') return -1;
          if (b.status === 'at_warehouse') return 1;
        }

        // 5. Fallback to order ID ascending
        return a.id - b.id;
      });
    },

    get todayDateFormatted() {
      return new Date().toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;

      if (this.user?.id) {
        setOfflineUserId(this.user.id);
      }

      this.initSyncManager();
      this.startTimeTicker();

      await Promise.all([
        this.loadActiveOrders(),
        this.loadShiftSummary(),
        this.loadActiveHandover(),
      ]);
    },

    destroy() {
      this.stopTimeTicker();
      this.syncManager?.stop();
      this.stopCameraStream();
      this.revokeTrackedBlobUrls();
    },

    createTrackedBlobUrl(blob) {
      if (!blob) return null;
      const url = URL.createObjectURL(blob);
      this._trackedBlobUrls.add(url);
      return url;
    },

    revokeTrackedBlobUrls() {
      if (this._trackedBlobUrls && this._trackedBlobUrls.size > 0) {
        for (const url of this._trackedBlobUrls) {
          try {
            URL.revokeObjectURL(url);
          } catch (_) {}
        }
        this._trackedBlobUrls.clear();
      }
    },

    initSyncManager() {
      this.syncManager = new CourierSyncManager({
        userId: this.user?.id || null,
        api: this.api.bind(this),
        onSyncStateChange: (isSyncing, count) => {
          this.isSyncing = isSyncing;
          this.pendingSyncCount = count;
        },
        onOrderUpdated: (updated) => {
          const idx = this.orders.findIndex(o => o.id === updated.id);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], ...updated, is_offline_pending: false };
            this.orders = [...this.orders];
          }
        },
        onOrderCompleted: (orderId) => {
          this.orders = this.orders.filter(o => o.id !== orderId);
          this.loadShiftSummary();
        },
        onSyncCompleted: ({ processed }) => {
          if (processed > 0 && typeof navigator !== 'undefined' && navigator.onLine) {
            this.loadActiveOrders(true);
            this.loadShiftSummary();
          }
          this.revokeTrackedBlobUrls();
        },
        onNotify: (msg, type = 'info') => {
          this.$dispatch('notify', { message: msg, type });
        },
        onHaptic: (type) => {
          if (type === 'success') this.hapticSuccess?.();
          else if (type === 'warn' || type === 'warning') this.hapticWarning?.();
          else if (type === 'error') this.hapticError?.();
        },
      });

      this.syncManager.start();

      getPendingActionsCount(this.user?.id).then((count) => {
        this.pendingSyncCount = count;
      });

      window.addEventListener('online', () => {
        this.isOnline = true;
        this.isOfflineMode = false;
      });

      window.addEventListener('offline', () => {
        this.isOnline = false;
        this.isOfflineMode = true;
        this.$dispatch('notify', { message: 'Потеряна связь. Включен оффлайн-режим', type: 'warn' });
      });
    },

    async manualSync() {
      if (this.isSyncing) return;
      if (!this.isOnline) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Нет подключения к интернету', type: 'warn' });
        return;
      }
      this.hapticImpact?.('light');
      const result = await this.syncManager?.sync();
      if (result?.success) {
        await Promise.all([
          this.loadActiveOrders(true),
          this.loadShiftSummary(),
        ]);
      }
    },

    async loadShiftSummary() {
      const uid = this.user?.id;
      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        const cached = await getCachedShiftSummary(uid);
        if (cached) {
          this.shiftSummary = cached;
        }
        return;
      }

      try {
        const res = await this.api(DashboardController.courierShiftSummary());
        if (res?.couriers && res.couriers.length > 0) {
          this.shiftSummary = res.couriers[0];
        } else if (res?.summary) {
          this.shiftSummary = {
            completed_deliveries: res.summary.total_deliveries,
            total_collected: res.summary.total_collected,
            cash_amount: res.summary.cash_amount,
            terminal_amount: res.summary.terminal_amount,
            payout_amount: res.summary.payout_amount,
          };
        }
        await saveShiftSummaryCache(this.shiftSummary, uid);
      } catch (e) {
        console.warn('Failed to load shift summary:', e);
        const cached = await getCachedShiftSummary(uid);
        if (cached) {
          this.shiftSummary = cached;
        }
      }
    },

    async loadActiveHandover() {
      try {
        const res = await this.api(CourierHandoverController.active());
        this.activeHandover = res?.handover || null;
      } catch (e) {
        console.warn('Failed to load active handover:', e);
      }
    },

    async openHandoverModal() {
      this.handoverLoading = true;
      try {
        const res = await this.api(CourierHandoverController.orders());
        this.transferableOrders = res?.orders || [];
        this.handoverForm.order_ids = (res?.orders || []).map(o => o.id);
        const cashAmt = typeof res?.current_cash === 'number' ? res.current_cash : (this.shiftSummary?.cash_amount || 0);
        this.handoverForm.declared_cash = cashAmt;
        this.handoverForm.transfer_cash = cashAmt > 0;
        this.handoverForm.reason = 'vehicle_breakdown';
        this.handoverForm.reason_comment = '';
        this.showHandoverModal = true;
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Не удалось загрузить заказы: ' + (e.message || ''), type: 'error' });
      } finally {
        this.handoverLoading = false;
      }
    },

    toggleHandoverOrder(orderId) {
      const idx = this.handoverForm.order_ids.indexOf(orderId);
      if (idx > -1) {
        this.handoverForm.order_ids.splice(idx, 1);
      } else {
        this.handoverForm.order_ids.push(orderId);
      }
    },

    selectAllHandoverOrders() {
      if (this.handoverForm.order_ids.length === this.transferableOrders.length) {
        this.handoverForm.order_ids = [];
      } else {
        this.handoverForm.order_ids = this.transferableOrders.map(o => o.id);
      }
    },

    async submitHandoverInitiate() {
      if (this.handoverSubmitting) return;
      if (!this.handoverForm.order_ids || this.handoverForm.order_ids.length === 0) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Выберите хотя бы один заказ для передачи', type: 'warn' });
        return;
      }
      this.handoverSubmitting = true;
      try {
        const res = await this.api(CourierHandoverController.initiate(), {
          body: {
            order_ids: this.handoverForm.order_ids,
            transfer_cash: Boolean(this.handoverForm.transfer_cash),
            declared_cash: Number(this.handoverForm.declared_cash) || 0,
            reason: this.handoverForm.reason,
            reason_comment: this.handoverForm.reason_comment || null,
          },
        });
        if (res?.success && res.handover) {
          this.activeHandover = res.handover;
          this.showHandoverModal = false;
          this.showHandoverQrModal = true;
          this.hapticSuccess?.();
          this.$dispatch('notify', { message: 'Код передачи сформирован. Покажите его принимающему курьеру', type: 'success' });
          await Promise.all([this.loadActiveOrders(true), this.loadShiftSummary()]);
        }
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e.message || 'Ошибка формирования передачи', type: 'error' });
      } finally {
        this.handoverSubmitting = false;
      }
    },

    async cancelActiveHandover() {
      if (!this.activeHandover?.id) return;
      const ok = await window.confirmDialog({
        title: 'Отмена передачи смены',
        message: 'Вы уверены, что хотите отменить передачу смены?',
        confirmText: 'Да, отменить',
        cancelText: 'Назад',
        variant: 'danger',
      });
      if (!ok) return;

      try {
        await this.api(CourierHandoverController.cancel(), {
          body: { handover_id: this.activeHandover.id },
        });
        this.activeHandover = null;
        this.showHandoverQrModal = false;
        this.hapticImpact?.('medium');
        this.$dispatch('notify', { message: 'Передача отменена', type: 'info' });
        await Promise.all([this.loadActiveOrders(true), this.loadShiftSummary()]);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e.message || 'Ошибка отмены передачи', type: 'error' });
      }
    },

    openScanHandoverModal() {
      this.handoverReceiveForm.code = '';
      this.handoverReceiveForm.actual_cash = 0;
      this.handoverReceiveForm.discrepancy_reason = '';

      if (window.Telegram?.WebApp?.showScanQrPopup) {
        window.Telegram.WebApp.showScanQrPopup({ text: 'Наведите камеру на QR-код курьера' }, (qrData) => {
          if (qrData) {
            window.Telegram.WebApp.closeScanQrPopup();
            this.fetchHandoverPreview(qrData.trim());
            return true;
          }
        });
      } else {
        this.showScanHandoverModal = true;
      }
    },

    async fetchHandoverPreview(code) {
      if (!code) return;
      this.handoverLoading = true;
      try {
        const res = await this.api(CourierHandoverController.preview(code));
        this.handoverPreviewData = res;
        this.handoverReceiveForm.code = code;
        this.handoverReceiveForm.actual_cash = res.declared_cash_amount || 0;
        this.handoverReceiveForm.discrepancy_reason = '';
        this.showScanHandoverModal = false;
        this.showHandoverPreviewModal = true;
        this.hapticImpact?.('light');
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e.message || 'Код передачи не найден или истёк', type: 'error' });
      } finally {
        this.handoverLoading = false;
      }
    },

    get handoverDiscrepancy() {
      if (!this.handoverPreviewData?.transfer_cash) return 0;
      const actual = Number(this.handoverReceiveForm.actual_cash) || 0;
      const declared = Number(this.handoverPreviewData?.declared_cash_amount) || 0;
      return Math.round((actual - declared) * 100) / 100;
    },

    async confirmAcceptHandover() {
      if (this.handoverSubmitting) return;
      const code = this.handoverReceiveForm.code;
      if (!code) return;

      const discrepancy = this.handoverDiscrepancy;
      if (discrepancy !== 0 && !this.handoverReceiveForm.discrepancy_reason?.trim()) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Укажите причину расхождения в сумме кассы', type: 'warn' });
        return;
      }

      this.handoverSubmitting = true;
      try {
        const res = await this.api(CourierHandoverController.accept(), {
          body: {
            code: code,
            actual_cash: Number(this.handoverReceiveForm.actual_cash) || 0,
            discrepancy_reason: this.handoverReceiveForm.discrepancy_reason || null,
          },
        });
        if (res?.success) {
          this.showHandoverPreviewModal = false;
          this.handoverPreviewData = null;
          this.hapticSuccess?.();
          const msg = res.status === 'disputed'
            ? `Заказы приняты (${res.orders_reassigned} шт.). Зафиксировано расхождение в кассе.`
            : `Заказы (${res.orders_reassigned} шт.) и касса успешно приняты!`;
          this.$dispatch('notify', { message: msg, type: res.status === 'disputed' ? 'warn' : 'success' });
          await Promise.all([this.loadActiveOrders(false), this.loadShiftSummary()]);
        }
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e.message || 'Ошибка принятия передачи', type: 'error' });
      } finally {
        this.handoverSubmitting = false;
      }
    },

    async loadActiveOrders(silent = false) {
      if (!silent) {
        this.ordersLoading = true;
      }

      const uid = this.user?.id;

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        const cached = await getCachedOrders(uid);
        this.orders = cached;
        this.isOfflineMode = true;
        if (!silent) {
          this.ordersLoading = false;
        }
        return;
      }

      try {
        const [assignedRes, warehouseRes] = await Promise.all([
          this.api(OrderController.index({ query: { status: 'assigned' } })),
          this.api(OrderController.index({ query: { status: 'at_warehouse' } })),
        ]);

        const fetched = [
          ...(assignedRes?.orders || []),
          ...(warehouseRes?.orders || []),
        ];

        // Retain pending offline modifications until synced
        const pending = await getPendingActions(uid);
        const pendingMap = new Map(pending.map(p => [p.orderId, p]));

        this.orders = fetched.map((ord) => {
          if (pendingMap.has(ord.id)) {
            const item = pendingMap.get(ord.id);
            const patch = { is_offline_pending: true };
            if (item.action === 'status' && item.payload?.endpoint === 'at-warehouse') {
              patch.status = 'at_warehouse';
            }
            return { ...ord, ...patch };
          }
          return ord;
        });

        // Filter out orders marked as completed offline
        const completedIds = new Set(
          pending.filter(p => p.action === 'status' && (p.payload?.endpoint === 'complete' || p.payload?.endpoint === 'delivered'))
                 .map(p => p.orderId)
        );
        if (completedIds.size > 0) {
          this.orders = this.orders.filter(o => !completedIds.has(o.id));
        }

        await saveOrdersCache(this.orders, uid);
        this.isOfflineMode = false;
      } catch (e) {
        console.error('Failed to load active orders:', e);
        if (isTransientError(e)) {
          this.orders = await getCachedOrders(uid);
          this.isOfflineMode = true;
          this.$dispatch('notify', { message: 'Нет подключения. Загружены данные из кэша', type: 'info' });
        } else if (!silent) {
          this.orders = [];
        }
      } finally {
        if (!silent) {
          this.ordersLoading = false;
        }
      }
    },

    async updateStatus(orderId, action) {
      if (!orderId) return;
      const endpoint = action === 'at_warehouse' ? 'at-warehouse' : action;
      const uid = this.user?.id;

      const handleOfflineStatus = async () => {
        await queueAction({ action: 'status', orderId, payload: { endpoint }, userId: uid });
        this.pendingSyncCount = await getPendingActionsCount(uid);

        if (action === 'complete') {
          this.orders = this.orders.filter((o) => o.id !== orderId);
          await removeCachedOrder(orderId, uid);
          if (this.shiftSummary) {
            this.shiftSummary = {
              ...this.shiftSummary,
              completed_deliveries: (this.shiftSummary.completed_deliveries || 0) + 1,
            };
            await saveShiftSummaryCache(this.shiftSummary, uid);
          }
        } else {
          const idx = this.orders.findIndex(o => o.id === orderId);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], status: action, is_offline_pending: true };
            this.orders = [...this.orders];
            await updateCachedOrder(orderId, { status: action, is_offline_pending: true }, uid);
          }
        }

        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Сохранено оффлайн. Отправится при появлении связи', type: 'warn' });
      };

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        await handleOfflineStatus();
        return;
      }

      try {
        const actionMap = {
          'at_warehouse': OrderController.atWarehouse,
          'at-warehouse': OrderController.atWarehouse,
          'complete': OrderController.complete,
          'cancel': OrderController.cancel,
          'accept': OrderController.accept,
        };
        const actionFn = actionMap[action] || actionMap[endpoint];
        const routeDef = actionFn ? actionFn(orderId) : { url: `/api/orders/${orderId}/${endpoint}`, method: 'patch' };
        const response = await this.api(routeDef);
        const updated = response?.order;
        if (updated) {
          if (updated.status === 'completed' || updated.status === 'cancelled') {
            this.orders = this.orders.filter((o) => o.id !== orderId);
            await removeCachedOrder(orderId, uid);
            if (updated.status === 'completed') {
              this.loadShiftSummary();
            }
          } else {
            const idx = this.orders.findIndex(o => o.id === orderId);
            if (idx !== -1) {
              this.orders[idx] = { ...this.orders[idx], ...updated, is_offline_pending: false };
              this.orders = [...this.orders];
              await updateCachedOrder(orderId, this.orders[idx], uid);
            }
          }
        }
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Статус обновлён' });
      } catch (e) {
        if (isTransientError(e)) {
          await handleOfflineStatus();
        } else {
          this.hapticError?.();
          this.$dispatch('notify', { message: e?.message || 'Ошибка обновления', type: 'error' });
        }
      }
    },

    async openAtWarehouseModal(orderId) {
      if (!orderId || this.movingAtWarehouse) return;
      const ok = await window.confirmDialog({
        title: 'Подтвердить действие',
        message: 'Вы уверены, что хотите отметить заказ как «Забрал / В пути»?',
        confirmText: 'Да',
        cancelText: 'Нет',
        variant: 'primary',
      });
      if (!ok) return;

      this.movingAtWarehouse = true;
      try {
        await this.updateStatus(orderId, 'at_warehouse');
      } finally {
        this.movingAtWarehouse = false;
      }
    },

    openCompleteModal(orderId) {
      this.pendingCompleteOrderId = orderId;
      this.showCompleteModal = true;
    },

    async confirmCompleteOrder() {
      if (this.completingOrder) return;
      const orderId = this.pendingCompleteOrderId;
      if (!orderId) {
        this.showCompleteModal = false;
        return;
      }

      const order = this.getPendingOrder();
      if (order && this.requiresSignature(order) && !this.hasSignature(order)) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Для ценного отправления требуется подпись клиента', type: 'warn' });
        this.openSignaturePad(orderId);
        return;
      }

      const authOk = await this.requestBiometricAuth('Подтвердите завершение доставки и получение оплаты');
      if (!authOk) return;

      this.completingOrder = true;
      try {
        await this.updateStatus(orderId, 'complete');
        this.showCompleteModal = false;
        this.pendingCompleteOrderId = null;
      } finally {
        this.completingOrder = false;
      }
    },

    hasSignature(order) {
      const d = order?.delivery;
      if (!d) return false;
      const item = Array.isArray(d) ? d[0] : d;
      return !!(item?.signature_url || item?.has_signature);
    },

    getSignatureUrl(order) {
      const d = order?.delivery;
      if (!d) return null;
      const item = Array.isArray(d) ? d[0] : d;
      return item?.signature_url || null;
    },

    getSignatureAt(order) {
      const d = order?.delivery;
      if (!d) return null;
      const item = Array.isArray(d) ? d[0] : d;
      return item?.signature_at || null;
    },

    requiresSignature(order) {
      return !!(order?.requires_signature || order?.is_valuable);
    },

    openSignaturePad(orderId = null) {
      const targetId = orderId || this.pendingCompleteOrderId;
      const order = this.orders.find(o => o.id === targetId);
      if (order && (order.status === 'completed' || order.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя изменить подпись для завершенного заказа', type: 'error' });
        return;
      }
      this.signatureTargetOrderId = targetId;
      this.showSignatureModal = true;
    },

    closeSignaturePad() {
      this.showSignatureModal = false;
      this.signatureTargetOrderId = null;
    },

    async saveSignature(blob, orderId = null) {
      if (this.signatureUploading) return;
      const targetId = orderId || this.signatureTargetOrderId || this.pendingCompleteOrderId;
      if (!targetId || !blob) return;

      const order = this.orders.find(o => o.id === targetId);
      if (order && (order.status === 'completed' || order.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя изменить подпись для завершенного заказа', type: 'error' });
        return;
      }

      this.signatureUploading = true;
      this.setUploadLoading(true, { title: 'Сохранение подписи...', subtitle: 'Передача на сервер' });

      const fileName = `signature_${targetId}_${Date.now()}.png`;
      const uid = this.user?.id;

      const handleOfflineSignature = async () => {
        await queueAction({
          action: 'delivery_signature',
          orderId: targetId,
          payload: { blob, fileName },
          userId: uid,
        });
        this.pendingSyncCount = await getPendingActionsCount(uid);

        const localUrl = this.createTrackedBlobUrl(blob);
        const idx = this.orders.findIndex(o => o.id === targetId);
        if (idx !== -1) {
          const d = this.orders[idx].delivery;
          const baseDelivery = Array.isArray(d) ? (d[0] || {}) : (d || {});
          const updatedDelivery = {
            ...baseDelivery,
            has_signature: true,
            signature_url: localUrl,
            signature_at: new Date().toISOString(),
          };
          this.orders[idx] = {
            ...this.orders[idx],
            delivery: Array.isArray(d) ? [updatedDelivery] : updatedDelivery,
            is_offline_pending: true,
          };
          this.orders = [...this.orders];
          await updateCachedOrder(targetId, this.orders[idx], uid);
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Подпись сохранена локально' });
        this.$dispatch('notify', { message: 'Подпись сохранена оффлайн. Отправится при появлении связи', type: 'warn' });
        this.closeSignaturePad();
      };

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        try {
          await handleOfflineSignature();
        } finally {
          this.signatureUploading = false;
        }
        return;
      }

      const file = new File([blob], fileName, { type: 'image/png' });
      const formData = new FormData();
      formData.append('signature', file);

      try {
        const response = await this.api(OrderController.uploadSignature(targetId), {
          body: formData,
        });

        if (response?.order) {
          const idx = this.orders.findIndex(o => o.id === targetId);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], ...response.order, is_offline_pending: false };
            this.orders = [...this.orders];
            await updateCachedOrder(targetId, this.orders[idx], uid);
          }
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Подпись сохранена' });
        this.$dispatch('notify', { message: 'Подпись клиента зафиксирована', type: 'success' });
        this.closeSignaturePad();
      } catch (e) {
        if (isTransientError(e)) {
          await handleOfflineSignature();
        } else {
          this.hapticError?.();
          this.setUploadLoading(false, { status: 'error', message: 'Ошибка сохранения' });
          this.$dispatch('notify', { message: e?.message || 'Ошибка сохранения подписи', type: 'error' });
        }
      } finally {
        this.signatureUploading = false;
      }
    },

    promptDeleteSignature(orderId = null) {
      this.deleteSignatureTargetOrderId = orderId || this.pendingCompleteOrderId;
      this.showDeleteSignatureModal = true;
    },

    async confirmDeleteSignature() {
      const orderId = this.deleteSignatureTargetOrderId;
      this.showDeleteSignatureModal = false;
      this.deleteSignatureTargetOrderId = null;
      if (orderId) {
        await this.deleteSignature(orderId);
      }
    },

    async deleteSignature(orderId = null) {
      const targetId = orderId || this.pendingCompleteOrderId;
      if (!targetId) return;

      this.signatureUploading = true;
      this.setUploadLoading(true, { title: 'Удаление подписи...', subtitle: 'Обновление данных' });
      const uid = this.user?.id;

      const handleOfflineDeleteSignature = async () => {
        await queueAction({ action: 'delete_signature', orderId: targetId, payload: {}, userId: uid });
        this.pendingSyncCount = await getPendingActionsCount(uid);

        const idx = this.orders.findIndex(o => o.id === targetId);
        if (idx !== -1) {
          const d = this.orders[idx].delivery;
          const baseDelivery = Array.isArray(d) ? (d[0] || {}) : (d || {});
          const updatedDelivery = {
            ...baseDelivery,
            has_signature: false,
            signature_url: null,
            signature_at: null,
          };
          this.orders[idx] = {
            ...this.orders[idx],
            delivery: Array.isArray(d) ? [updatedDelivery] : updatedDelivery,
            is_offline_pending: true,
          };
          this.orders = [...this.orders];
          await updateCachedOrder(targetId, this.orders[idx], uid);
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Подпись удалена локально' });
        this.$dispatch('notify', { message: 'Удаление подписи сохранено оффлайн', type: 'info' });
      };

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        try {
          await handleOfflineDeleteSignature();
        } finally {
          this.signatureUploading = false;
        }
        return;
      }

      try {
        const response = await this.api(OrderController.deleteSignature(targetId));

        if (response?.order) {
          const idx = this.orders.findIndex(o => o.id === targetId);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], ...response.order, is_offline_pending: false };
            this.orders = [...this.orders];
            await updateCachedOrder(targetId, this.orders[idx], uid);
          }
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Подпись удалена' });
        this.$dispatch('notify', { message: 'Подпись клиента удалена', type: 'info' });
      } catch (e) {
        if (isTransientError(e)) {
          await handleOfflineDeleteSignature();
        } else {
          this.hapticError?.();
          this.setUploadLoading(false, { status: 'error', message: 'Ошибка удаления' });
          this.$dispatch('notify', { message: e?.message || 'Ошибка удаления подписи', type: 'error' });
        }
      } finally {
        this.signatureUploading = false;
      }
    },

    getPhotoProofUrls(order) {
      const d = order?.delivery;
      if (!d) return [];
      const item = Array.isArray(d) ? d[0] : d;
      if (Array.isArray(item?.photo_proof_urls) && item.photo_proof_urls.length > 0) {
        return item.photo_proof_urls;
      }
      if (item?.photo_proof_url) {
        return [item.photo_proof_url];
      }
      return [];
    },

    hasPhotoProof(order) {
      return this.getPhotoProofUrls(order).length > 0;
    },

    getProofPhotoUrl(order) {
      const urls = this.getPhotoProofUrls(order);
      return urls.length > 0 ? urls[0] : null;
    },

    async promptDeleteProof(url = null, orderId = null) {
      const targetId = orderId || this.pendingCompleteOrderId;
      const order = this.orders.find((o) => o.id === targetId);
      if (order && (order.status === 'completed' || order.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя удалить фото для завершенного заказа', type: 'error' });
        return;
      }
      if (this.deletingProof) return;
      const ok = await window.confirmDialog({
        title: 'Удаление фотофиксации',
        message: url ? 'Удалить выбранную фотографию вручения? Это действие нельзя отменить.' : 'Удалить все прикрепленные фотографии вручения? Это действие нельзя отменить.',
        confirmText: 'Удалить',
        cancelText: 'Отмена',
        variant: 'danger',
      });
      if (!ok) return;

      this.deletingProof = true;
      try {
        await this.deleteDeliveryProof(url, targetId);
      } finally {
        this.deletingProof = false;
      }
    },

    async deleteDeliveryProof(url = null, orderId = null) {
      const targetId = orderId || this.pendingCompleteOrderId;
      if (!targetId) return;

      this.proofUploading = true;
      this.setUploadLoading(true, { title: 'Удаление фото...', subtitle: 'Обновление данных' });
      const uid = this.user?.id;

      const handleOfflineDeleteProof = async () => {
        await queueAction({ action: 'delete_delivery_proof', orderId: targetId, payload: { url }, userId: uid });
        this.pendingSyncCount = await getPendingActionsCount(uid);

        const idx = this.orders.findIndex(o => o.id === targetId);
        if (idx !== -1) {
          const d = this.orders[idx].delivery;
          const baseDelivery = Array.isArray(d) ? (d[0] || {}) : (d || {});
          let remainingUrls = this.getPhotoProofUrls(this.orders[idx]);
          if (url) {
            remainingUrls = remainingUrls.filter(u => u !== url);
          } else {
            remainingUrls = [];
          }
          const updatedDelivery = {
            ...baseDelivery,
            photo_proof_urls: remainingUrls,
            photo_proof_url: remainingUrls[0] || null,
          };
          this.orders[idx] = {
            ...this.orders[idx],
            delivery: Array.isArray(d) ? [updatedDelivery] : updatedDelivery,
            is_offline_pending: true,
          };
          this.orders = [...this.orders];
          await updateCachedOrder(targetId, this.orders[idx], uid);
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Фото удалено локально' });
        this.$dispatch('notify', { message: 'Удаление фото сохранено оффлайн', type: 'info' });
      };

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        try {
          await handleOfflineDeleteProof();
        } finally {
          this.proofUploading = false;
        }
        return;
      }

      try {
        const response = await this.api(OrderController.deleteProof(targetId, url ? { query: { path: url } } : undefined), {
          body: url ? { path: url } : undefined,
        });
        if (response?.order) {
          const idx = this.orders.findIndex(o => o.id === targetId);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], ...response.order, is_offline_pending: false };
            this.orders = [...this.orders];
            await updateCachedOrder(targetId, this.orders[idx], uid);
          }
        }
        await this.loadActiveOrders(true);
        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Фото удалено' });
        this.$dispatch('notify', { message: 'Фото вручения удалено' });
      } catch (e) {
        if (isTransientError(e)) {
          await handleOfflineDeleteProof();
        } else {
          this.hapticError?.();
          this.setUploadLoading(false, { status: 'error', message: 'Ошибка удаления' });
          this.$dispatch('notify', { message: e?.message || 'Ошибка удаления фото', type: 'error' });
        }
      } finally {
        this.proofUploading = false;
      }
    },

    getPendingOrder() {
      if (!this.pendingCompleteOrderId) return null;
      return this.orders.find(o => o.id === this.pendingCompleteOrderId) || null;
    },

    openProofPhotoModal(url) {
      if (!url) return;
      this.proofPhotoModalUrl = url;
      this.showProofPhotoModal = true;
    },

    async uploadDeliveryProof(event, orderId = null) {
      const targetId = orderId || this.pendingCompleteOrderId;
      const files = event.target?.files;
      if (!files || files.length === 0 || !targetId) return;

      const order = this.orders.find((o) => o.id === targetId);
      if (order && (order.status === 'completed' || order.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя добавить фото для завершенного заказа', type: 'error' });
        if (event.target) event.target.value = '';
        return;
      }

      const currentCount = this.getPhotoProofUrls(order).length;
      if (currentCount >= 3) {
        this.$dispatch('notify', { message: 'Достигнут лимит: максимум 3 фото', type: 'warn' });
        if (event.target) event.target.value = '';
        return;
      }

      this.proofUploading = true;
      const remainingSlots = 3 - currentCount;
      const toUpload = Array.from(files).slice(0, remainingSlots);

      if (files.length > remainingSlots) {
        this.$dispatch('notify', { message: `Выбрано больше лимита, загружено ${remainingSlots} фото`, type: 'info' });
      }

      this.setUploadLoading(true, {
        title: 'Загрузка фото...',
        subtitle: `Оптимизация и отправка (${toUpload.length})`,
      });

      let processedFiles = toUpload;
      try {
        processedFiles = await compressImageFiles(toUpload, { maxWidth: 1920, maxHeight: 1920, quality: 0.82 });
      } catch (err) {
        processedFiles = toUpload;
      }

      const serializedFiles = processedFiles.map((f, idx) => ({
        name: f.name || `proof_${targetId}_${Date.now()}_${idx}.jpg`,
        type: f.type || 'image/jpeg',
        blob: f instanceof Blob ? f : new Blob([f], { type: 'image/jpeg' }),
      }));

      const uid = this.user?.id;

      const handleOfflineProof = async () => {
        await queueAction({
          action: 'delivery_proof',
          orderId: targetId,
          payload: { files: serializedFiles },
          userId: uid,
        });
        this.pendingSyncCount = await getPendingActionsCount(uid);

        const localUrls = serializedFiles.map(f => this.createTrackedBlobUrl(f.blob));
        const idx = this.orders.findIndex(o => o.id === targetId);
        if (idx !== -1) {
          const d = this.orders[idx].delivery;
          const baseDelivery = Array.isArray(d) ? (d[0] || {}) : (d || {});
          const currentUrls = this.getPhotoProofUrls(this.orders[idx]);
          const updatedDelivery = {
            ...baseDelivery,
            photo_proof_urls: [...currentUrls, ...localUrls],
            photo_proof_url: currentUrls[0] || localUrls[0],
          };
          this.orders[idx] = {
            ...this.orders[idx],
            delivery: Array.isArray(d) ? [updatedDelivery] : updatedDelivery,
            is_offline_pending: true,
          };
          this.orders = [...this.orders];
          await updateCachedOrder(targetId, this.orders[idx], uid);
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Фото сохранено локально' });
        this.$dispatch('notify', { message: 'Фото сохранено оффлайн. Отправится при появлении связи', type: 'warn' });
        this.scrollToOrderCard(targetId);
      };

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        try {
          await handleOfflineProof();
        } finally {
          this.proofUploading = false;
          if (event.target) event.target.value = '';
        }
        return;
      }

      const formData = new FormData();
      if (processedFiles.length === 1) {
        formData.append('photo', processedFiles[0]);
      } else {
        processedFiles.forEach((f) => formData.append('photos[]', f));
      }

      try {
        const response = await this.api(OrderController.uploadProof(targetId), {
          body: formData,
        });

        if (response?.order) {
          const idx = this.orders.findIndex(o => o.id === targetId);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], ...response.order, is_offline_pending: false };
            this.orders = [...this.orders];
            await updateCachedOrder(targetId, this.orders[idx], uid);
          }
        }
        await this.loadActiveOrders(true);
        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Фото сохранено' });
        this.$dispatch('notify', { message: 'Фото вручения сохранено' });
        this.scrollToOrderCard(targetId);
      } catch (e) {
        if (isTransientError(e)) {
          await handleOfflineProof();
        } else {
          this.hapticError?.();
          this.setUploadLoading(false, { status: 'error', message: 'Ошибка сохранения' });
          this.$dispatch('notify', { message: e?.message || 'Ошибка загрузки фото', type: 'error' });
        }
      } finally {
        this.proofUploading = false;
        if (event.target) event.target.value = '';
      }
    },

    async openCamera(orderId = null) {
      const targetId = orderId || this.pendingCompleteOrderId;
      const order = this.orders.find((o) => o.id === targetId);
      if (order && (order.status === 'completed' || order.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя добавить фото для завершенного заказа', type: 'error' });
        return;
      }

      this.cameraTargetOrderId = targetId;
      this.capturedBlob = null;
      this.capturedPreviewUrl = null;
      this.cameraError = null;
      this.showCameraModal = true;
      await this.$nextTick();
      await this.initCameraStream();
    },

    async initCameraStream() {
      if (!navigator.mediaDevices?.getUserMedia) {
        this.cameraError = 'Камера не поддерживается вашим браузером. Загрузите фото файлом.';
        return;
      }

      this.cameraLoading = true;
      this.cameraError = null;
      this.stopCameraStream();

      try {
        const constraints = {
          video: {
            facingMode: { ideal: this.cameraFacingMode },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
          },
          audio: false,
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        this._cameraStream = stream;

        const videoEl = this.$refs.courierCameraVideo;
        if (videoEl) {
          videoEl.srcObject = stream;
          await videoEl.play();
        }
      } catch (err) {
        console.warn('Courier camera error:', err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
          this.cameraError = 'Доступ к камере запрещен. Разрешите доступ в настройках.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
          this.cameraError = 'Камера на устройстве не найдена.';
        } else {
          this.cameraError = 'Ошибка запуска камеры: ' + (err.message || 'сбой оборудования');
        }
      } finally {
        this.cameraLoading = false;
      }
    },

    async switchCamera() {
      this.cameraFacingMode = this.cameraFacingMode === 'environment' ? 'user' : 'environment';
      await this.initCameraStream();
    },

    stopCameraStream() {
      if (this._cameraStream) {
        this._cameraStream.getTracks().forEach((track) => track.stop());
        this._cameraStream = null;
      }
      if (this.$refs.courierCameraVideo) {
        this.$refs.courierCameraVideo.srcObject = null;
      }
    },

    closeCameraModal() {
      this.stopCameraStream();
      if (this.capturedPreviewUrl) {
        URL.revokeObjectURL(this.capturedPreviewUrl);
        this.capturedPreviewUrl = null;
      }
      this.capturedBlob = null;
      this.showCameraModal = false;
      this.cameraTargetOrderId = null;
    },

    takeSnapshot() {
      const videoEl = this.$refs.courierCameraVideo;
      if (!videoEl || !this._cameraStream) return;

      let width = videoEl.videoWidth || 1280;
      let height = videoEl.videoHeight || 720;
      const maxDim = 1920;

      if (width > maxDim || height > maxDim) {
        const ratio = Math.min(maxDim / width, maxDim / height);
        width = Math.round(width * ratio);
        height = Math.round(height * ratio);
      }

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(videoEl, 0, 0, width, height);

      canvas.toBlob((blob) => {
        if (!blob) return;
        this.capturedBlob = blob;
        this.capturedPreviewUrl = URL.createObjectURL(blob);
        this.stopCameraStream();
        this.hapticSuccess?.();
      }, 'image/jpeg', 0.82);
    },

    retakeSnapshot() {
      if (this.capturedPreviewUrl) {
        URL.revokeObjectURL(this.capturedPreviewUrl);
        this.capturedPreviewUrl = null;
      }
      this.capturedBlob = null;
      this.initCameraStream();
    },

    async confirmCapturedSnapshot() {
      const targetId = this.cameraTargetOrderId || this.pendingCompleteOrderId;
      if (!this.capturedBlob || !targetId) return;

      const order = this.orders.find((o) => o.id === targetId);
      if (order && (order.status === 'completed' || order.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя добавить фото для завершенного заказа', type: 'error' });
        this.closeCameraModal();
        return;
      }

      const currentCount = this.getPhotoProofUrls(order).length;
      if (currentCount >= 3) {
        this.$dispatch('notify', { message: 'Достигнут лимит: максимум 3 фото', type: 'warn' });
        this.closeCameraModal();
        return;
      }

      this.proofUploading = true;
      this.setUploadLoading(true, { title: 'Сохранение снимка...', subtitle: 'Обработка' });

      const fileName = `proof_${targetId}_${Date.now()}.jpg`;
      const serializedFiles = [{
        name: fileName,
        type: 'image/jpeg',
        blob: this.capturedBlob,
      }];
      const uid = this.user?.id;

      const handleOfflineSnapshot = async () => {
        await queueAction({
          action: 'delivery_proof',
          orderId: targetId,
          payload: { files: serializedFiles },
          userId: uid,
        });
        this.pendingSyncCount = await getPendingActionsCount(uid);

        const localUrl = this.createTrackedBlobUrl(this.capturedBlob);
        const idx = this.orders.findIndex(o => o.id === targetId);
        if (idx !== -1) {
          const d = this.orders[idx].delivery;
          const baseDelivery = Array.isArray(d) ? (d[0] || {}) : (d || {});
          const currentUrls = this.getPhotoProofUrls(this.orders[idx]);
          const updatedDelivery = {
            ...baseDelivery,
            photo_proof_urls: [...currentUrls, localUrl],
            photo_proof_url: currentUrls[0] || localUrl,
          };
          this.orders[idx] = {
            ...this.orders[idx],
            delivery: Array.isArray(d) ? [updatedDelivery] : updatedDelivery,
            is_offline_pending: true,
          };
          this.orders = [...this.orders];
          await updateCachedOrder(targetId, this.orders[idx], uid);
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Снимок сохранен локально' });
        this.$dispatch('notify', { message: 'Фото сохранено оффлайн. Отправится при появлении связи', type: 'warn' });
        this.closeCameraModal();
        this.scrollToOrderCard(targetId);
      };

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        try {
          await handleOfflineSnapshot();
        } finally {
          this.proofUploading = false;
        }
        return;
      }

      const file = new File([this.capturedBlob], fileName, { type: 'image/jpeg' });
      const formData = new FormData();
      formData.append('photo', file);

      try {
        const response = await this.api(OrderController.uploadProof(targetId), {
          body: formData,
        });

        if (response?.order) {
          const idx = this.orders.findIndex(o => o.id === targetId);
          if (idx !== -1) {
            this.orders[idx] = { ...this.orders[idx], ...response.order, is_offline_pending: false };
            this.orders = [...this.orders];
            await updateCachedOrder(targetId, this.orders[idx], uid);
          }
        }
        await this.loadActiveOrders(true);
        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Снимок сохранен' });
        this.$dispatch('notify', { message: 'Фото вручения сохранено' });
        this.closeCameraModal();
        this.scrollToOrderCard(targetId);
      } catch (e) {
        if (isTransientError(e)) {
          await handleOfflineSnapshot();
        } else {
          this.hapticError?.();
          this.setUploadLoading(false, { status: 'error', message: 'Ошибка сохранения' });
          this.$dispatch('notify', { message: e?.message || 'Ошибка сохранения фото', type: 'error' });
        }
      } finally {
        this.proofUploading = false;
      }
    },

    scrollToOrderCard(orderId) {
      if (!orderId) return;
      this.$nextTick(() => {
        setTimeout(() => {
          const el = document.getElementById(`order-card-${orderId}`);
          if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        }, 120);
      });
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

    onLiveOrderAssigned(order) {
      if (!Array.isArray(this.orders)) return;

      const orderId = order.id;
      const uid = this.user?.id;
      const exists = this.orders.some((o) => o.id === orderId);
      if (exists) {
        const idx = this.orders.findIndex((o) => o.id === orderId);
        this.orders[idx] = { ...this.orders[idx], ...order };
        this.orders = [...this.orders];
        updateCachedOrder(orderId, this.orders[idx], uid);
        return;
      }

      if (!order.status || order.status === 'assigned' || order.status === 'at_warehouse') {
        this.orders = [order, ...this.orders];
        saveOrdersCache(this.orders, uid);
      }
    },

    onLiveOrderStatusChanged(data) {
      const orderId = data?.order_id;
      const status = data?.status;
      if (!orderId || !status || !Array.isArray(this.orders)) return;
      const uid = this.user?.id;

      if (status === 'completed' || status === 'cancelled') {
        this.orders = this.orders.filter((o) => o.id !== orderId);
        removeCachedOrder(orderId, uid);
      } else {
        const idx = this.orders.findIndex((o) => o.id === orderId);
        if (idx !== -1) {
          this.orders[idx] = { ...this.orders[idx], status };
          this.orders = [...this.orders];
          updateCachedOrder(orderId, this.orders[idx], uid);
        }
      }
    },
  });
}
