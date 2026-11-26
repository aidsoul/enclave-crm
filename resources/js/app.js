import Alpine from 'alpinejs';
import './../css/app.css';
import './echo-init.js';
import { withTmaBase } from './tma/shared.js';
import { imageZoomViewer } from './tma/shared/image-viewer.js';
import { signaturePad } from './tma/shared/signature-pad.js';
import orders from '@/routes/tma/orders';
import customers from '@/routes/tma/customers';
import tma from '@/routes/tma';
import { formatDateRu, formatDateIso } from './tma/date-range-picker.js';

window.formatDateRu = formatDateRu;
window.formatDateIso = formatDateIso;

window.Alpine = Alpine;
window.signaturePad = signaturePad;
Alpine.data('imageZoomViewer', imageZoomViewer);
Alpine.data('signaturePad', signaturePad);
Alpine.magic('cleanup', (el) => (callback) => {
  if (typeof callback === 'function') {
    el._x_cleanups = el._x_cleanups || [];
    el._x_cleanups.push(callback);
  }
});

window.notify = (message, type = 'success', url = null) => {
  const normalizedType = type === 'warning' ? 'warn' : type;
  try {
    if (normalizedType === 'error') {
      window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('error');
    } else if (normalizedType === 'warn') {
      window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('warning');
    } else {
      window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
    }
  } catch (_) {}
  window.dispatchEvent(new CustomEvent('notify', { detail: { message, type: normalizedType, url } }));
};

window.confirmDialog = (options = {}) => {
  if (typeof options === 'string') {
    options = { message: options };
  }
  if (!document.querySelector('[data-confirm-dialog]')) {
    const ok = window.confirm(options.message || options.title || 'Подтвердить действие?');
    return Promise.resolve(ok);
  }
  return new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('confirm-dialog', {
      detail: { ...options, resolve }
    }));
  });
};

window.__pwaInstallPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  window.__pwaInstallPrompt = e;
  window.dispatchEvent(new CustomEvent('pwa-installable'));
});
window.addEventListener('appinstalled', () => {
  window.__pwaInstallPrompt = null;
  window.dispatchEvent(new CustomEvent('pwa-installed'));
});

let modalCount = 0;
const modalStack = [];
const closingConfirmationSources = new Set();

function syncClosingConfirmation() {
  try {
    const tg = window.Telegram?.WebApp;
    if (!tg) return;
    if (closingConfirmationSources.size > 0) {
      tg.enableClosingConfirmation?.();
    } else {
      tg.disableClosingConfirmation?.();
    }
  } catch (_) {}
}

window.enableClosingConfirmation = (source = 'default') => {
  closingConfirmationSources.add(source);
  syncClosingConfirmation();
};

window.disableClosingConfirmation = (source = 'default') => {
  if (source === true) {
    closingConfirmationSources.clear();
  } else {
    closingConfirmationSources.delete(source);
  }
  syncClosingConfirmation();
};

window.isClosingConfirmationActive = () => closingConfirmationSources.size > 0;

const backHandlerStack = [];
let explicitBackButtonShown = false;

function isNestedRoute(pathname) {
  if (/^\/tma\/orders\/\d+/.test(pathname)) return true;
  if (pathname === '/tma/new') return true;
  if (/^\/tma\/customers\/\d+/.test(pathname)) return true;
  if (/^\/tma\/audit\/\d+/.test(pathname)) return true;
  if (pathname === '/tma/sites') return true;
  if (pathname === '/tma/analytics') return true;
  if (pathname === '/tma/financial') return true;
  if (pathname === '/tma/audit' && window.location.search.includes('order')) return true;
  return false;
}

function updateBackButtonVisibility() {
  try {
    const tg = window.Telegram?.WebApp;
    if (!tg?.BackButton) return;

    const shouldShow = explicitBackButtonShown || modalCount > 0 || backHandlerStack.length > 0 || isNestedRoute(window.location.pathname);
    if (shouldShow) {
      tg.BackButton.show();
    } else {
      tg.BackButton.hide();
    }
  } catch (_) {}
}

function handleBackButton() {
  const tg = window.Telegram?.WebApp;
  tg?.HapticFeedback?.impactOccurred?.('light');

  if (backHandlerStack.length > 0) {
    const top = backHandlerStack.pop();
    updateBackButtonVisibility();
    if (typeof top === 'function') {
      const handled = top();
      if (handled !== false) return;
    }
  }

  if (modalStack.length > 0) {
    window.closeTopModal();
    return;
  }

  if (modalCount > 0) {
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    return;
  }

  const appEl = document.getElementById('app');
  if (appEl && window.Alpine) {
    try {
      const alpineData = window.Alpine.$data(appEl);
      if (alpineData && typeof alpineData.goBack === 'function') {
        alpineData.goBack();
        return;
      }
    } catch (_) {}
  }

  if (window.history.length > 1) {
    window.history.back();
    return;
  }

  const p = window.location.pathname;
  if (/^\/tma\/orders\/\d+\/edit$/.test(p)) {
    const id = p.match(/^\/tma\/orders\/(\d+)\/edit$/)[1];
    window.location.href = orders.show.url(id);
  } else if (/^\/tma\/orders\/\d+$/.test(p)) {
    const role = window.__tmaApp?.user?.role;
    window.location.href = role === 'courier' ? tma.role.url('courier') : (role === 'manager' ? tma.manager.url() : orders.index.url());
  } else if (p === '/tma/new') {
    const role = window.__tmaApp?.user?.role;
    window.location.href = role === 'manager' ? tma.manager.url() : orders.index.url();
  } else if (/^\/tma\/customers\/\d+$/.test(p)) {
    window.location.href = customers.index.url();
  } else if (/^\/tma\/audit\/\d+$/.test(p)) {
    window.location.href = tma.audit.url();
  } else if (p === '/tma/sites') {
    window.location.href = tma.settings.url();
  } else if (p === '/tma/analytics' || p === '/tma/financial') {
    window.location.href = tma.home.url();
  } else {
    window.location.href = tma.home.url();
  }
}

window.showBackButton = (callback = null) => {
  explicitBackButtonShown = true;
  if (typeof callback === 'function') {
    backHandlerStack.push(callback);
  }
  updateBackButtonVisibility();
};

window.hideBackButton = () => {
  explicitBackButtonShown = false;
  backHandlerStack.length = 0;
  updateBackButtonVisibility();
};

window.pushBackHandler = (callback) => {
  if (typeof callback === 'function') {
    backHandlerStack.push(callback);
  }
  updateBackButtonVisibility();
};

window.popBackHandler = () => {
  const handler = backHandlerStack.pop();
  updateBackButtonVisibility();
  return handler;
};

window.updateBackButtonVisibility = updateBackButtonVisibility;

try {
  const tg = window.Telegram?.WebApp;
  if (tg?.BackButton) {
    tg.BackButton.onClick(handleBackButton);
  }
} catch (_) {}
updateBackButtonVisibility();

window.lockScroll = () => {
  modalCount++;
  if (modalCount === 1) {
    document.body.classList.add('overflow-hidden');
    document.body.classList.add('has-modal-open');
    window.dispatchEvent(new CustomEvent('modal-state-changed', { detail: { open: true } }));
    document.documentElement.classList.add('overflow-hidden');
    try {
      window.Telegram?.WebApp?.disableVerticalSwipes?.();
    } catch (_) {}
  }
  updateBackButtonVisibility();
};

window.unlockScroll = () => {
  modalCount = Math.max(0, modalCount - 1);
  if (modalCount === 0) {
    document.body.classList.remove('overflow-hidden');
    document.body.classList.remove('has-modal-open');
    window.dispatchEvent(new CustomEvent('modal-state-changed', { detail: { open: false } }));
    document.documentElement.classList.remove('overflow-hidden');
    try {
      window.Telegram?.WebApp?.enableVerticalSwipes?.();
    } catch (_) {}
    window.disableClosingConfirmation?.('modal-form');
  }
  updateBackButtonVisibility();
};

window.registerModal = (closeFn) => {
  if (typeof closeFn === 'function' && !modalStack.includes(closeFn)) {
    modalStack.push(closeFn);
  }
  window.lockScroll();
};

window.unregisterModal = (closeFn) => {
  if (typeof closeFn === 'function') {
    const idx = modalStack.lastIndexOf(closeFn);
    if (idx !== -1) {
      modalStack.splice(idx, 1);
      window.unlockScroll();
      return;
    }
  }
  window.unlockScroll();
};

window.closeTopModal = () => {
  if (modalStack.length > 0) {
    const topClose = modalStack.pop();
    window.unlockScroll();
    try {
      topClose();
    } catch (_) {}
    return true;
  }
  return false;
};

window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modalStack.length > 0) {
    e.preventDefault();
    e.stopImmediatePropagation();
    window.closeTopModal();
  }
});

window.addEventListener('pagehide', () => {
  modalCount = 0;
  modalStack.length = 0;
  explicitBackButtonShown = false;
  backHandlerStack.length = 0;
  document.body.classList.remove('overflow-hidden');
    document.body.classList.remove('has-modal-open');
    window.dispatchEvent(new CustomEvent('modal-state-changed', { detail: { open: false } }));
  document.documentElement.classList.remove('overflow-hidden');
  window.disableClosingConfirmation?.(true);
});

const path = window.location.pathname;

async function loadPageModule() {
  if (path.startsWith('/tma/personnel')) {
    const m = await import('./tma/pages/personnel.js');
    return m.createPage;
  }
  if (path === '/tma/settings') {
    const m = await import('./tma/pages/settings.js');
    return m.createPage;
  }
  if (path === '/tma/sites') {
    const m = await import('./tma/pages/sites.js');
    return m.createPage;
  }
  if (path === '/tma/customers') {
    const m = await import('./tma/pages/customers.js');
    return m.createPage;
  }
  if (path.match(/^\/tma\/customers\/\d+$/)) {
    const m = await import('./tma/pages/customer-show.js');
    return m.createPage;
  }
  if (path === '/tma/leads') {
    const m = await import('./tma/pages/leads.js');
    return m.createPage;
  }
  if (path === '/tma/audit') {
    const m = await import('./tma/pages/audit.js');
    return m.createPage;
  }
  if (path.match(/^\/tma\/audit\/\d+$/)) {
    const m = await import('./tma/pages/audit-show.js');
    return m.createPage;
  }
  if (path === '/tma/financial') {
    const m = await import('./tma/pages/financial.js');
    return m.createPage;
  }
  if (path === '/tma/analytics') {
    const m = await import('./tma/pages/analytics.js');
    return m.createPage;
  }
  if (path === '/tma/manager') {
    const m = await import('./tma/pages/manager-orders.js');
    return m.createPage;
  }
  if (path === '/tma/new') {
    const m = await import('./tma/pages/order-form.js');
    return m.createPage;
  }
  if (path === '/tma/orders') {
    const m = await import('./tma/pages/order-list.js');
    return m.createPage;
  }
  if (path.match(/^\/tma\/orders\/\d+\/edit$/)) {
    const m = await import('./tma/pages/order-form.js');
    return m.createPage;
  }
  if (path.match(/^\/tma\/orders\/\d+\/chat$/)) {
    const m = await import('./tma/pages/order-chat.js');
    return m.createPage;
  }
  if (path.match(/^\/tma\/orders\/\d+$/)) {
    const m = await import('./tma/pages/order-show.js');
    return m.createPage;
  }
  if (path === '/tma/courier') {
    const m = await import('./tma/pages/courier.js');
    return m.createPage;
  }
  const m = await import('./tma/pages/dashboard.js');
  return m.createPage;
}

try {
  const createPage = await loadPageModule();
  Alpine.data('tmaApp', createPage);
} catch (e) {
  console.error('[TMA] Failed to load page module:', e);
  Alpine.data('tmaApp', (user = null, retentionDays = 0) => withTmaBase({ user, retentionDays }));
  document.getElementById('loading').style.display = 'none';
}

Alpine.start();

(function initSwipeToBack() {
  if (!('ontouchstart' in window)) return;

  let startX = 0;
  let startY = 0;
  let tracking = false;
  const EDGE_THRESHOLD = 40;
  const MIN_DX = 80;
  const MAX_DY = 60;

  function isScrollable(el) {
    const style = window.getComputedStyle(el);
    return (style.overflowX === 'auto' || style.overflowX === 'scroll') && el.scrollWidth > el.clientWidth;
  }

  function findScrollableAncestor(node) {
    let el = node;
    while (el && el !== document.body) {
      if (isScrollable(el)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function onTouchStart(e) {
    if (modalCount > 0 || window.isClosingConfirmationActive?.()) return;
    const touch = e.touches[0];
    if (touch.clientX > EDGE_THRESHOLD) return;
    if (findScrollableAncestor(e.target)) return;
    startX = touch.clientX;
    startY = touch.clientY;
    tracking = true;
  }

  function onTouchMove(e) {
    if (!tracking) return;
    const touch = e.touches[0];
    const dx = touch.clientX - startX;
    const dy = touch.clientY - startY;
    if (dx < -10 || Math.abs(dy) > MIN_DX) tracking = false;
  }

  function onTouchEnd(e) {
    if (!tracking) return;
    tracking = false;
    const touch = e.changedTouches[0];
    const dx = touch.clientX - startX;
    const dy = touch.clientY - startY;
    if (dx > MIN_DX && Math.abs(dy) < MAX_DY) {
      handleBackButton();
    }
  }

  document.addEventListener('touchstart', onTouchStart, { passive: true });
  document.addEventListener('touchmove', onTouchMove, { passive: true });
  document.addEventListener('touchend', onTouchEnd, { passive: true });
})();

function isSearchOrFilterInput(el) {
  if (!el || !el.matches) return false;
  if (el.type === 'search') return true;
  if (el.matches('[data-search], .search-input, [name="search"], [name="q"]')) return true;
  const placeholder = (el.placeholder || '').toLowerCase();
  if (placeholder.includes('поиск') || placeholder.includes('search')) return true;
  return false;
}

document.addEventListener('input', (e) => {
  const el = e.target;
  if (!el || !el.matches) return;
  if (!el.matches('input, textarea, select')) return;
  if (isSearchOrFilterInput(el)) return;
  if (el.value && String(el.value).trim().length > 0) {
    if (modalCount > 0) {
      window.enableClosingConfirmation?.('modal-form');
    } else {
      window.enableClosingConfirmation?.('form-input');
    }
  }
}, { passive: true });

document.addEventListener('submit', () => {
  window.disableClosingConfirmation?.('form-input');
  window.disableClosingConfirmation?.('modal-form');
});

