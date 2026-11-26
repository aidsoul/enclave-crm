import orders from '@/routes/tma/orders';
import customers from '@/routes/tma/customers';
import tma from '@/routes/tma';

export const navigationMixin = {
  enableClosingConfirmation(source = 'default') {
    window.enableClosingConfirmation?.(source);
  },

  disableClosingConfirmation(source = 'default') {
    window.disableClosingConfirmation?.(source);
  },

  isClosingConfirmationActive() {
    return Boolean(window.isClosingConfirmationActive?.());
  },

  showBackButton(callback = null) {
    window.showBackButton?.(callback);
  },

  hideBackButton() {
    window.hideBackButton?.();
  },

  pushBackHandler(callback) {
    window.pushBackHandler?.(callback);
  },

  popBackHandler() {
    window.popBackHandler?.();
  },

  goBack() {
    const tg = window.Telegram?.WebApp;
    if (tg && window.history.length > 1) {
      window.history.back();
      return;
    }

    const p = window.location.pathname;
    const role = this.user?.role;

    if (/^\/tma\/orders\/\d+\/edit$/.test(p)) {
      const id = p.match(/^\/tma\/orders\/(\d+)\/edit$/)[1];
      window.location.href = orders.show.url(id);
    } else if (/^\/tma\/orders\/\d+$/.test(p)) {
      window.location.href = role === 'courier' ? tma.role.url('courier') : (role === 'manager' ? tma.manager.url() : orders.index.url());
    } else if (p === '/tma/new') {
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
  },

  openExternalLink(url) {
    if (!url) return;
    if (window.Telegram?.WebApp?.openLink) {
      window.Telegram.WebApp.openLink(url);
    } else {
      window.open(url, '_blank');
    }
  },

  openNavigator(type, address) {
    if (!address) return;
    const cleanAddress = String(address).trim();
    if (!cleanAddress) return;

    this.hapticImpact?.('light');

    const encoded = encodeURIComponent(cleanAddress);
    const urls = {
      yandex: `https://yandex.ru/maps/?text=${encoded}`,
      '2gis': `https://2gis.ru/search/${encoded}`,
      google: `https://www.google.com/maps/search/?api=1&query=${encoded}`,
    };
    const url = urls[type];
    if (!url) return;

    if (window.Telegram?.WebApp?.openLink) {
      window.Telegram.WebApp.openLink(url);
    } else {
      window.open(url, '_blank');
    }
  },

  openEmail(email) {
    if (!email) return;
    if (window.Telegram?.WebApp?.openLink) {
      const url = `${window.location.origin}/open/email?to=${encodeURIComponent(email)}`;
      window.Telegram.WebApp.openLink(url);
    } else {
      window.location.href = `mailto:${email}`;
    }
  },

  openMessenger(type, phone) {
    const digits = this.getCleanPhone(phone);
    if (!digits) return;
    if (type === 'telegram') {
      const tgUrl = `https://t.me/+${digits}`;
      if (window.Telegram?.WebApp?.openTelegramLink) {
        window.Telegram.WebApp.openTelegramLink(tgUrl);
      } else {
        window.location.href = `tg://resolve?phone=${digits}`;
      }
    } else if (type === 'whatsapp') {
      const waUrl = `https://wa.me/${digits}`;
      if (window.Telegram?.WebApp?.openLink) {
        window.Telegram.WebApp.openLink(waUrl);
      } else {
        window.open(waUrl, '_blank');
      }
    } else if (type === 'viber') {
      if (window.Telegram?.WebApp?.openLink) {
        const viberUrl = `${window.location.origin}/open/viber?phone=%2B${digits}`;
        window.Telegram.WebApp.openLink(viberUrl);
      } else {
        window.location.href = `viber://chat?number=%2B${digits}`;
      }
    }
  },

  openTelegramProfile(telegramUserId, username = null, e = null) {
    if (e) {
      try { e.preventDefault(); } catch (_) {}
    }
    try {
      window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
    } catch (_) {}

    const cleanUsername = username ? String(username).replace(/^@/, '').trim() : '';
    if (cleanUsername) {
      const tmeUrl = `https://t.me/${cleanUsername}`;
      if (window.Telegram?.WebApp?.openTelegramLink) {
        window.Telegram.WebApp.openTelegramLink(tmeUrl);
        return;
      }
      window.open(tmeUrl, '_blank');
      return;
    }

    const cleanId = telegramUserId ? String(telegramUserId).replace(/[^\d]/g, '').trim() : '';
    if (cleanId) {
      if (window.Telegram?.WebApp?.openLink) {
        const helperUrl = `${window.location.origin}/open/telegram?id=${encodeURIComponent(cleanId)}`;
        window.Telegram.WebApp.openLink(helperUrl);
        return;
      }
      window.location.href = `tg://user?id=${cleanId}`;
    }
  },
};
