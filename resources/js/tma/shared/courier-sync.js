/**
 * Robust Sync Manager for Courier TMA.
 * Features:
 * - User-scoped outbox processing
 * - Dependency-aware action execution (proof/signature before completion)
 * - Intelligent error handling (transient vs permanent)
 * - Automatic background queue drain
 * - Lifecycle cleanup
 */

import OrderController from '@/actions/App/Http/Controllers/OrderController';
import {
  getPendingActions,
  getPendingActionsCount,
  removePendingAction,
  updatePendingAction,
  isTransientError,
  updateCachedOrder,
  removeCachedOrder,
} from './courier-offline.js';

export class CourierSyncManager {
  /**
   * @param {Object} options
   * @param {number|string|null} options.userId - Active courier user ID
   * @param {Function} options.api - TMA API client method
   * @param {Function} options.onSyncStateChange - Callback (isSyncing: boolean, pendingCount: number) => void
   * @param {Function} options.onOrderUpdated - Callback (updatedOrder: Object) => void
   * @param {Function} options.onOrderCompleted - Callback (orderId: number) => void
   * @param {Function} options.onSyncCompleted - Callback (result: Object) => void
   * @param {Function} options.onNotify - Callback (message: string, type?: string) => void
   * @param {Function} options.onHaptic - Callback (type: string) => void
   */
  constructor(options = {}) {
    this.userId = options.userId || null;
    this.api = options.api;
    this.onSyncStateChange = options.onSyncStateChange || (() => {});
    this.onOrderUpdated = options.onOrderUpdated || (() => {});
    this.onOrderCompleted = options.onOrderCompleted || (() => {});
    this.onSyncCompleted = options.onSyncCompleted || (() => {});
    this.onNotify = options.onNotify || (() => {});
    this.onHaptic = options.onHaptic || (() => {});

    this.isSyncing = false;
    this._intervalId = null;
    this._boundOnOnline = this.handleOnline.bind(this);
    this._boundOnVisibility = this.handleVisibility.bind(this);
  }

  setUserId(userId) {
    this.userId = userId || null;
  }

  start() {
    if (typeof window === 'undefined') return;

    window.addEventListener('online', this._boundOnOnline);
    document.addEventListener('visibilitychange', this._boundOnVisibility);

    // Periodic check every 45 seconds when online
    this._intervalId = setInterval(() => {
      if (typeof navigator !== 'undefined' && navigator.onLine && !this.isSyncing) {
        this.sync().catch(() => {});
      }
    }, 45000);
  }

  stop() {
    if (typeof window === 'undefined') return;
    window.removeEventListener('online', this._boundOnOnline);
    document.removeEventListener('visibilitychange', this._boundOnVisibility);
    if (this._intervalId) {
      clearInterval(this._intervalId);
      this._intervalId = null;
    }
  }

  handleOnline() {
    this.onNotify('Связь восстановлена. Синхронизация...', 'info');
    this.sync().catch((e) => console.warn('[CourierSync] Auto-sync on online failed:', e));
  }

  handleVisibility() {
    if (document.visibilityState === 'visible' && typeof navigator !== 'undefined' && navigator.onLine && !this.isSyncing) {
      this.sync().catch(() => {});
    }
  }

  async sync() {
    if (this.isSyncing) {
      return { success: false, inProgress: true };
    }

    if (typeof navigator !== 'undefined' && !navigator.onLine) {
      return { success: false, offline: true };
    }

    this.isSyncing = true;
    let totalProcessed = 0;
    let hasNetworkFailure = false;
    const MAX_PASSES = 3;

    try {
      for (let pass = 0; pass < MAX_PASSES; pass++) {
        const items = await getPendingActions(this.userId);
        if (items.length === 0) {
          break;
        }

        this.onSyncStateChange(true, items.length);

        for (const item of items) {
          try {
            await updatePendingAction(item.id, { attempts: (item.attempts || 0) + 1 }, this.userId);
            const result = await this.executeAction(item);

            await removePendingAction(item.id, this.userId);
            totalProcessed++;

            if (item.action === 'status' && (item.payload?.endpoint === 'complete' || item.payload?.endpoint === 'delivered')) {
              await removeCachedOrder(item.orderId, this.userId);
              this.onOrderCompleted(item.orderId);
            } else if (result?.order) {
              await updateCachedOrder(item.orderId, result.order, this.userId);
              this.onOrderUpdated(result.order);
            }

            const remaining = await getPendingActionsCount(this.userId);
            this.onSyncStateChange(true, remaining);
          } catch (err) {
            if (isTransientError(err)) {
              console.warn('[CourierSync] Transient error, pausing queue:', err);
              hasNetworkFailure = true;
              break;
            }

            // Check for idempotent success (e.g. status transition already applied on server)
            const errMsg = String(err.message || '').toLowerCase();
            const isIdempotentStatusMatch = (
              item.action === 'status' &&
              (errMsg.includes('некорректный переход') || errMsg.includes('invalid status'))
            );

            if (isIdempotentStatusMatch) {
              console.info('[CourierSync] Status transition already recorded on server, dropping duplicate action');
              await removePendingAction(item.id, this.userId);
              totalProcessed++;
              continue;
            }

            // Permanent 4xx business error (e.g. order deleted or unassigned)
            console.error('[CourierSync] Permanent rejection for item:', item, err);
            await removePendingAction(item.id, this.userId);
            this.onNotify(`Заказ #${item.orderId}: ${err.message || 'Ошибка обработки на сервере'}`, 'warn');
          }
        }

        if (hasNetworkFailure) {
          break;
        }
      }
    } finally {
      const remainingCount = await getPendingActionsCount(this.userId);
      this.isSyncing = false;
      this.onSyncStateChange(false, remainingCount);

      if (totalProcessed > 0) {
        this.onHaptic('success');
        this.onNotify(`Синхронизировано действий: ${totalProcessed}`, 'success');
      }

      this.onSyncCompleted({
        success: !hasNetworkFailure,
        processed: totalProcessed,
        remaining: remainingCount,
      });
    }

    const finalRemaining = await getPendingActionsCount(this.userId);
    return {
      success: !hasNetworkFailure,
      processed: totalProcessed,
      remaining: finalRemaining,
    };
  }

  async executeAction(item) {
    const headers = {};
    if (item.clientUuid) {
      headers['X-Idempotency-Key'] = item.clientUuid;
    }

    switch (item.action) {
      case 'status': {
        const endpoint = item.payload?.endpoint;
        const actionMap = {
          'at_warehouse': OrderController.atWarehouse,
          'at-warehouse': OrderController.atWarehouse,
          'complete': OrderController.complete,
          'cancel': OrderController.cancel,
          'accept': OrderController.accept,
        };
        const actionFn = actionMap[endpoint] || (OrderController[endpoint] || null);
        const routeDef = actionFn ? actionFn(item.orderId) : { url: `/api/orders/${item.orderId}/${endpoint}`, method: 'patch' };
        return this.api(routeDef, {
          headers,
        });
      }

      case 'delivery_proof': {
        const formData = new FormData();
        const files = item.payload?.files || [];
        if (files.length === 1) {
          const f = files[0];
          formData.append('photo', f.blob, f.name || 'proof.jpg');
        } else if (files.length > 1) {
          files.forEach((f) => {
            formData.append('photos[]', f.blob, f.name || 'proof.jpg');
          });
        }
        return this.api(OrderController.uploadProof(item.orderId), {
          body: formData,
          headers,
        });
      }

      case 'delivery_signature': {
        const formData = new FormData();
        const blob = item.payload?.blob;
        const name = item.payload?.fileName || `signature_${item.orderId}.png`;
        formData.append('signature', blob, name);
        return this.api(OrderController.uploadSignature(item.orderId), {
          body: formData,
          headers,
        });
      }

      case 'delete_delivery_proof': {
        const url = item.payload?.url;
        return this.api(OrderController.deleteProof(item.orderId, url ? { query: { path: url } } : undefined), {
          body: url ? { path: url } : undefined,
          headers,
        });
      }

      case 'delete_signature': {
        return this.api(OrderController.deleteSignature(item.orderId), {
          headers,
        });
      }

      default:
        throw new Error(`Unknown action: ${item.action}`);
    }
  }
}
