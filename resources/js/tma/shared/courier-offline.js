/**
 * Robust, user-isolated offline storage for Courier TMA using IndexedDB.
 * Handles order caching, shift metrics, and transactional outbox queue.
 */

let currentUserId = null;
const dbInstances = new Map();

export function isIndexedDBSupported() {
  return typeof window !== 'undefined' && 'indexedDB' in window;
}

export function setOfflineUserId(userId) {
  currentUserId = userId || null;
}

export function getOfflineDbName(userId = null) {
  const uid = userId || currentUserId;
  return uid ? `crm_courier_db_${uid}` : 'crm_courier_db_guest';
}

/**
 * Safely strips reactive Alpine.js proxies, functions, and non-cloneable symbols
 * to prevent DataCloneError when writing to IndexedDB.
 */
export function safeCloneForDb(obj) {
  if (obj === null || typeof obj !== 'object') {
    return obj;
  }
  // Blobs, Files, and ArrayBuffers are natively supported by IndexedDB structured clone
  if (obj instanceof Blob || (typeof File !== 'undefined' && obj instanceof File) || obj instanceof ArrayBuffer) {
    return obj;
  }
  try {
    return JSON.parse(JSON.stringify(obj));
  } catch (_) {
    return obj;
  }
}

export function openOfflineDB(userId = null) {
  if (!isIndexedDBSupported()) {
    return Promise.reject(new Error('IndexedDB not supported'));
  }

  const dbName = getOfflineDbName(userId);
  if (dbInstances.has(dbName)) {
    return Promise.resolve(dbInstances.get(dbName));
  }

  const DB_VERSION = 1;

  return new Promise((resolve, reject) => {
    const request = indexedDB.open(dbName, DB_VERSION);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;

      if (!db.objectStoreNames.contains('orders')) {
        db.createObjectStore('orders', { keyPath: 'id' });
      }

      if (!db.objectStoreNames.contains('shift_summary')) {
        db.createObjectStore('shift_summary', { keyPath: 'key' });
      }

      if (!db.objectStoreNames.contains('outbox')) {
        const outboxStore = db.createObjectStore('outbox', {
          keyPath: 'id',
          autoIncrement: true,
        });
        outboxStore.createIndex('by_order', 'orderId', { unique: false });
        outboxStore.createIndex('by_action', 'action', { unique: false });
        outboxStore.createIndex('by_created', 'createdAt', { unique: false });
      }
    };

    request.onsuccess = () => {
      const db = request.result;

      db.onversionchange = () => {
        db.close();
        dbInstances.delete(dbName);
      };

      db.onclose = () => {
        dbInstances.delete(dbName);
      };

      dbInstances.set(dbName, db);
      resolve(db);
    };

    request.onblocked = () => {
      console.warn('[OfflineDB] Database upgrade blocked by another open tab');
    };

    request.onerror = () => {
      dbInstances.delete(dbName);
      reject(request.error || new Error('Failed to open IndexedDB'));
    };
  });
}

/**
 * Distinguishes transient errors (network loss, timeout, 5xx server crash, 429 rate limit)
 * from permanent business rejections (400, 401, 403, 404, 422).
 */
export function isTransientError(err) {
  if (typeof navigator !== 'undefined' && !navigator.onLine) {
    return true;
  }
  if (!err) return false;

  const status = Number(err.status);
  if (status === 408 || status === 429 || (status >= 500 && status < 600)) {
    return true;
  }

  const msg = String(err.message || '').toLowerCase();
  const name = String(err.name || '').toLowerCase();
  return (
    name === 'aborterror' ||
    name === 'timeouterror' ||
    msg.includes('failed to fetch') ||
    msg.includes('networkerror') ||
    msg.includes('network request failed') ||
    msg.includes('network error') ||
    msg.includes('timed out') ||
    msg.includes('load failed') ||
    msg.includes('connection refused')
  );
}

/**
 * Sorts outbox items with dependency awareness:
 * For any given order, proof and signature uploads MUST execute before status: complete,
 * preventing 403 Forbidden errors from the backend OrderPolicy.
 */
export function sortOutboxItems(items) {
  if (!Array.isArray(items)) return [];

  const getActionPriority = (item) => {
    if (item.action === 'delivery_proof') return 1;
    if (item.action === 'delivery_signature') return 2;
    if (item.action === 'delete_delivery_proof') return 3;
    if (item.action === 'delete_signature') return 4;
    if (item.action === 'status') {
      if (item.payload?.endpoint === 'complete' || item.payload?.endpoint === 'delivered') {
        return 10;
      }
      return 5;
    }
    return 6;
  };

  return [...items].sort((a, b) => {
    if (a.orderId === b.orderId) {
      const prioDiff = getActionPriority(a) - getActionPriority(b);
      if (prioDiff !== 0) return prioDiff;
    }
    return (a.createdAt || 0) - (b.createdAt || 0);
  });
}

export async function getCachedOrders(userId = null) {
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('orders', 'readonly');
      const store = tx.objectStore('orders');
      const req = store.getAll();
      req.onsuccess = () => resolve(Array.isArray(req.result) ? req.result : []);
      req.onerror = () => resolve([]);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to get cached orders:', e);
    return [];
  }
}

export async function saveOrdersCache(orders, userId = null) {
  if (!Array.isArray(orders)) return;
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve, reject) => {
      const tx = db.transaction('orders', 'readwrite');
      const store = tx.objectStore('orders');
      store.clear();
      for (const order of orders) {
        if (order && order.id) {
          store.put(safeCloneForDb(order));
        }
      }
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => reject(tx.error);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to save orders cache:', e);
  }
}

export async function updateCachedOrder(orderId, patch, userId = null) {
  if (!orderId) return;
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('orders', 'readwrite');
      const store = tx.objectStore('orders');
      const req = store.get(orderId);
      req.onsuccess = () => {
        const existing = req.result;
        if (existing) {
          const updated = { ...existing, ...safeCloneForDb(patch) };
          store.put(updated);
        }
      };
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => resolve(false);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to update cached order:', e);
  }
}

export async function removeCachedOrder(orderId, userId = null) {
  if (!orderId) return;
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('orders', 'readwrite');
      tx.objectStore('orders').delete(orderId);
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => resolve(false);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to remove cached order:', e);
  }
}

export async function saveShiftSummaryCache(summary, userId = null) {
  if (!summary) return;
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('shift_summary', 'readwrite');
      tx.objectStore('shift_summary').put({
        key: 'current',
        data: safeCloneForDb(summary),
        updatedAt: Date.now(),
      });
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => resolve(false);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to save shift summary cache:', e);
  }
}

export async function getCachedShiftSummary(userId = null) {
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('shift_summary', 'readonly');
      const req = tx.objectStore('shift_summary').get('current');
      req.onsuccess = () => resolve(req.result?.data || null);
      req.onerror = () => resolve(null);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to get cached shift summary:', e);
    return null;
  }
}

export async function queueAction({ action, orderId, payload, userId = null }) {
  const clientUuid = (typeof crypto !== 'undefined' && crypto.randomUUID)
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;

  const item = {
    action,
    orderId,
    payload,
    clientUuid,
    createdAt: Date.now(),
    attempts: 0,
    status: 'pending',
  };

  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve, reject) => {
      const tx = db.transaction('outbox', 'readwrite');
      const store = tx.objectStore('outbox');
      const req = store.add(item);
      req.onsuccess = () => {
        item.id = req.result;
      };
      tx.oncomplete = () => resolve(item);
      tx.onerror = () => reject(tx.error);
    });
  } catch (e) {
    console.error('[OfflineDB] Failed to queue action:', e);
    throw e;
  }
}

export async function getPendingActions(userId = null) {
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('outbox', 'readonly');
      const store = tx.objectStore('outbox');
      const req = store.getAll();
      req.onsuccess = () => {
        const rawItems = Array.isArray(req.result) ? req.result : [];
        resolve(sortOutboxItems(rawItems));
      };
      req.onerror = () => resolve([]);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to get pending actions:', e);
    return [];
  }
}

export async function getPendingActionsCount(userId = null) {
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('outbox', 'readonly');
      const countReq = tx.objectStore('outbox').count();
      countReq.onsuccess = () => resolve(countReq.result || 0);
      countReq.onerror = () => resolve(0);
    });
  } catch (e) {
    return 0;
  }
}

export async function removePendingAction(id, userId = null) {
  if (!id) return;
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('outbox', 'readwrite');
      tx.objectStore('outbox').delete(id);
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => resolve(false);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to remove pending action:', e);
  }
}

export async function updatePendingAction(id, changes, userId = null) {
  if (!id) return;
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction('outbox', 'readwrite');
      const store = tx.objectStore('outbox');
      const req = store.get(id);
      req.onsuccess = () => {
        const item = req.result;
        if (item) {
          store.put({ ...item, ...changes });
        }
      };
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => resolve(false);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to update pending action:', e);
  }
}

export async function clearUserOfflineData(userId = null) {
  try {
    const db = await openOfflineDB(userId);
    return new Promise((resolve) => {
      const tx = db.transaction(['orders', 'shift_summary', 'outbox'], 'readwrite');
      tx.objectStore('orders').clear();
      tx.objectStore('shift_summary').clear();
      tx.objectStore('outbox').clear();
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => resolve(false);
    });
  } catch (e) {
    console.warn('[OfflineDB] Failed to clear user offline data:', e);
  }
}
