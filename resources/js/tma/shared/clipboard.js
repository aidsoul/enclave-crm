export const clipboardMixin = {
  async readClipboardText() {
    const tg = window.Telegram?.WebApp;
    if (typeof tg?.readTextFromClipboard === 'function') {
      return new Promise((resolve) => {
        try {
          tg.readTextFromClipboard((text) => resolve(text || ''));
        } catch (_) {
          resolve('');
        }
      });
    }

    if (navigator.clipboard?.readText) {
      try {
        return await navigator.clipboard.readText() || '';
      } catch (_) {}
    }

    return '';
  },

  copyText(text, successMsg = 'Скопировано в буфер') {
    if (!text) return;
    const str = String(text).trim();
    if (!str) return;

    const onSuccess = () => {
      window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
      if (window.notify) {
        window.notify(successMsg);
      } else if (this.$dispatch) {
        this.$dispatch('notify', { message: successMsg });
      }
    };

    const onError = () => {
      window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('error');
      if (window.notify) {
        window.notify('Не удалось скопировать', 'error');
      } else if (this.$dispatch) {
        this.$dispatch('notify', { message: 'Не удалось скопировать', type: 'error' });
      }
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(str).then(onSuccess).catch(() => {
        this._fallbackCopy(str, onSuccess, onError);
      });
      return;
    }

    this._fallbackCopy(str, onSuccess, onError);
  },

  copyCustomerInfo(customer, fallbackAddress = '') {
    if (!customer && !fallbackAddress) {
      if (window.notify) window.notify('Данные клиента отсутствуют', 'warn');
      return;
    }
    const lines = [];
    const name = customer?.name ? String(customer.name).trim() : '';
    const phone = customer?.phone || customer?.formatted_phone ? String(customer.phone || customer.formatted_phone).trim() : '';
    const email = customer?.email ? String(customer.email).trim() : '';
    const address = (customer?.address || fallbackAddress) ? String(customer.address || fallbackAddress).trim() : '';

    if (name) lines.push(`Имя: ${name}`);
    if (phone) lines.push(`Телефон: ${phone}`);
    if (email) lines.push(`Email: ${email}`);
    if (address) lines.push(`Адрес: ${address}`);

    if (lines.length === 0) {
      if (window.notify) window.notify('Нет данных для копирования', 'warn');
      return;
    }

    this.copyText(lines.join('\n'), 'Данные клиента скопированы');
  },

  _fallbackCopy(str, onSuccess, onError) {
    try {
      const el = document.createElement('textarea');
      el.value = str;
      el.setAttribute('readonly', '');
      el.style.cssText = 'position:fixed;left:-9999px;top:0';
      document.body.appendChild(el);
      el.focus();
      el.select();
      const successful = document.execCommand('copy');
      document.body.removeChild(el);
      if (successful) {
        onSuccess();
      } else {
        onError();
      }
    } catch (_) {
      onError();
    }
  },
};
