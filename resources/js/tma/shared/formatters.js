export const formattersMixin = {
  formatPrice(value) {
    const number = parseFloat(value);
    const symbol = window.__APP_CURRENCY_SYMBOL__ || 'Br';
    if (Number.isNaN(number)) return `0 ${symbol}`;
    return `${number.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${symbol}`;
  },

  formatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  },

  formatDateOnly(value) {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  },

  formatSite(site) {
    if (!site) return '';
    if (typeof site === 'string') return site;
    return site.domain || site.display_name || site.name || '';
  },

  getStatusLabel(status) {
    const labels = {
      created: 'Создан',
      accepted: 'Принят',
      assigned: 'Передан курьеру',
      at_warehouse: 'В пути',
      cancelled: 'Отменён',
      completed: 'Завершён',
    };
    return labels[status] || status;
  },

  getStatusClass(status) {
    const classes = {
      created: 'text-primary',
      accepted: 'text-info',
      assigned: 'text-secondary',
      at_warehouse: 'text-warning',
      cancelled: 'text-error',
      completed: 'text-success',
    };
    return classes[status] || 'text-fg-muted';
  },

  pluralize(count, one, few, many) {
    const n = Math.abs(count);
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod100 >= 11 && mod100 <= 19) return many;
    if (mod10 === 1) return one;
    if (mod10 >= 2 && mod10 <= 4) return few;
    return many;
  },

  getOrderAddress(order) {
    if (!order) return '';
    const address = order.delivery?.[0]?.delivery_address
      || order.delivery?.delivery_address
      || order.customer?.address
      || '';
    const city = order.delivery?.[0]?.city || order.delivery?.city || '';
    if (city && address && !address.toLowerCase().includes(city.toLowerCase())) {
      return `${city}, ${address}`;
    }
    return address || city;
  },

  getDeliverySchedule(order) {
    if (!order) return '';
    const delivery = Array.isArray(order.delivery) ? order.delivery[0] : order.delivery;
    if (!delivery) return '';
    const parts = [];
    if (delivery.delivery_date) {
      const d = new Date(delivery.delivery_date);
      if (!isNaN(d.getTime())) {
        parts.push(d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' }));
      } else {
        parts.push(delivery.delivery_date);
      }
    }
    if (delivery.delivery_slot) {
      parts.push(delivery.delivery_slot);
    }
    return parts.join(', ');
  },

  parseDeliverySlot(slot) {
    if (!slot || typeof slot !== 'string') {
      return { startMinutes: Infinity, endMinutes: Infinity, raw: '' };
    }
    const truncated = slot.length > 100 ? slot.slice(0, 100) : slot;
    const clean = truncated.trim().toLowerCase();
    if (!clean || clean.includes('течени') || clean === 'весь день') {
      return { startMinutes: Infinity, endMinutes: Infinity, raw: slot };
    }
    const timeMatches = [...clean.matchAll(/(\d{1,2})[:.](\d{2})/g)];
    if (timeMatches.length >= 2) {
      const startH = Math.min(23, Math.max(0, parseInt(timeMatches[0][1], 10)));
      const startM = Math.min(59, Math.max(0, parseInt(timeMatches[0][2], 10)));
      let endH = Math.min(23, Math.max(0, parseInt(timeMatches[1][1], 10)));
      const endM = Math.min(59, Math.max(0, parseInt(timeMatches[1][2], 10)));
      let endMinutes = endH * 60 + endM;
      const startMinutes = startH * 60 + startM;
      if (endMinutes < startMinutes) {
        endMinutes += 24 * 60;
      }
      return {
        startMinutes,
        endMinutes,
        raw: slot,
      };
    }
    if (timeMatches.length === 1) {
      const h = Math.min(23, Math.max(0, parseInt(timeMatches[0][1], 10)));
      const m = Math.min(59, Math.max(0, parseInt(timeMatches[0][2], 10)));
      const mins = h * 60 + m;
      if (clean.includes('до')) {
        return { startMinutes: 0, endMinutes: mins, raw: slot };
      }
      if (clean.includes('после') || clean.includes('с ') || clean.includes('c ')) {
        return { startMinutes: mins, endMinutes: 24 * 60, raw: slot };
      }
      return { startMinutes: mins, endMinutes: mins + 120, raw: slot };
    }
    const hourRangeMatch = clean.match(/(\d{1,2})\s*(?:[-–—]|до)\s*(\d{1,2})/);
    if (hourRangeMatch) {
      const startH = Math.min(23, Math.max(0, parseInt(hourRangeMatch[1], 10)));
      let endH = Math.min(23, Math.max(0, parseInt(hourRangeMatch[2], 10)));
      let endMinutes = endH * 60;
      const startMinutes = startH * 60;
      if (endMinutes < startMinutes) {
        endMinutes += 24 * 60;
      }
      return {
        startMinutes,
        endMinutes,
        raw: slot,
      };
    }
    return { startMinutes: Infinity, endMinutes: Infinity, raw: slot };
  },

  getSlotUrgency(order, referenceTime = null) {
    if (!order) return { badge: null, bgClass: 'text-primary bg-primary/10', badgeClass: '' };
    const delivery = Array.isArray(order.delivery) ? order.delivery[0] : order.delivery;
    if (!delivery || (!delivery.delivery_slot && !delivery.delivery_date)) {
      return { badge: null, bgClass: 'text-primary bg-primary/10', badgeClass: '' };
    }

    const now = referenceTime instanceof Date ? referenceTime : (referenceTime ? new Date(referenceTime) : new Date());
    const nowMins = now.getHours() * 60 + now.getMinutes();
    const nowDateStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    let isToday = true;
    let isPastDate = false;

    if (delivery.delivery_date) {
      const rawDate = String(delivery.delivery_date).trim().slice(0, 10);
      if (/^\d{4}-\d{2}-\d{2}$/.test(rawDate)) {
        if (rawDate < nowDateStr) {
          isPastDate = true;
          isToday = false;
        } else if (rawDate > nowDateStr) {
          isToday = false;
        }
      } else {
        const d = new Date(delivery.delivery_date);
        if (!isNaN(d.getTime())) {
          const delDateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
          if (delDateStr < nowDateStr) {
            isPastDate = true;
            isToday = false;
          } else if (delDateStr > nowDateStr) {
            isToday = false;
          }
        }
      }
    }

    if (isPastDate) {
      return {
        badge: 'Просрочено',
        bgClass: 'text-error bg-error/10',
        badgeClass: 'text-error bg-error/15 border border-error/30',
      };
    }

    if (isToday) {
      const parsed = this.parseDeliverySlot(delivery.delivery_slot);
      if (parsed.endMinutes !== Infinity) {
        if (nowMins > parsed.endMinutes) {
          return {
            badge: 'Слот истёк',
            bgClass: 'text-error bg-error/10',
            badgeClass: 'text-error bg-error/15 border border-error/30',
          };
        }
        const remaining = parsed.endMinutes - nowMins;
        if (remaining <= 45 && nowMins >= parsed.startMinutes) {
          return {
            badge: `Осталось < ${Math.max(1, remaining)} мин`,
            bgClass: 'text-warning bg-warning/10',
            badgeClass: 'text-warning bg-warning/15 border border-warning/30',
          };
        }
        if (nowMins >= parsed.startMinutes && nowMins <= parsed.endMinutes) {
          return {
            badge: 'Текущее окно',
            bgClass: 'text-success bg-success/10',
            badgeClass: 'text-success bg-success/15 border border-success/30',
          };
        }
      }
    }

    return {
      badge: null,
      bgClass: 'text-primary bg-primary/10',
      badgeClass: '',
    };
  },

  getCleanPhone(phone) {
    if (!phone) return '';
    let digits = String(phone).replace(/\D/g, '');
    if (digits.length === 11 && digits.startsWith('80') && digits[2] !== '0') {
      digits = '375' + digits.slice(2);
    } else if (digits.length === 9 && ['25', '29', '33', '44', '17', '16', '15', '21', '22', '23'].includes(digits.slice(0, 2))) {
      digits = '375' + digits;
    } else if (digits.length === 11 && digits.startsWith('8')) {
      digits = '7' + digits.slice(1);
    } else if (digits.length === 10) {
      digits = '7' + digits;
    }
    return digits;
  },

  formatPhone(phone) {
    if (!phone) return '';
    const digits = this.getCleanPhone(phone);
    if (!digits) return String(phone);

    if (digits.length === 11 && digits.startsWith('7')) {
      return `+7 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
    }
    if (digits.length === 12 && digits.startsWith('375')) {
      return `+375 (${digits.slice(3, 5)}) ${digits.slice(5, 8)}-${digits.slice(8, 10)}-${digits.slice(10, 12)}`;
    }
    if (digits.length === 12 && digits.startsWith('380')) {
      return `+380 (${digits.slice(3, 5)}) ${digits.slice(5, 8)}-${digits.slice(8, 10)}-${digits.slice(10, 12)}`;
    }
    if (digits.length === 12 && digits.startsWith('998')) {
      return `+998 (${digits.slice(3, 5)}) ${digits.slice(5, 8)}-${digits.slice(8, 10)}-${digits.slice(10, 12)}`;
    }
    if (digits.length === 12 && digits.startsWith('995')) {
      return `+995 (${digits.slice(3, 6)}) ${digits.slice(6, 8)}-${digits.slice(8, 10)}-${digits.slice(10, 12)}`;
    }
    if (digits.length === 11 && digits.startsWith('374')) {
      return `+374 (${digits.slice(3, 5)}) ${digits.slice(5, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
    }
    if (digits.length === 11 && digits.startsWith('1')) {
      return `+1 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 11)}`;
    }

    return '+' + digits;
  },

  getTelLink(phone) {
    const digits = this.getCleanPhone(phone);
    return digits ? `tel:+${digits}` : '#';
  },

  getEmailLink(email) {
    if (!email) return '#';
    if (window.Telegram?.WebApp) {
      return `/open/email?to=${encodeURIComponent(email)}`;
    }
    return `mailto:${email}`;
  },

  getMessengerLink(type, phone) {
    const digits = this.getCleanPhone(phone);
    if (!digits) return '#';
    if (type === 'telegram') return `https://t.me/+${digits}`;
    if (type === 'whatsapp') return `https://wa.me/${digits}`;
    if (type === 'viber') {
      if (window.Telegram?.WebApp) {
        return `/open/viber?phone=%2B${digits}`;
      }
      return `viber://chat?number=%2B${digits}`;
    }
    return '#';
  },

  shareOrder(order = null) {
    const ord = order || this.order;
    const orderId = typeof ord === 'object' ? ord?.id : ord;
    if (!orderId) {
      window.notify?.('Заказ не найден', 'warn');
      return;
    }

    const tg = window.Telegram?.WebApp;
    if (typeof tg?.switchInlineQuery === 'function') {
      try {
        tg.HapticFeedback?.impactOccurred?.('light');
        tg.switchInlineQuery(`order:${orderId}`, ['users', 'groups', 'channels']);
        return;
      } catch (_) {}
    }

    const siteStr = ord?.site?.domain || ord?.site?.name ? ` (${ord.site.domain || ord.site.name})` : '';
    const statusStr = ord?.status ? `Статус: ${this.getStatusLabel?.(ord.status) || ord.status}\n` : '';
    const customerStr = ord?.customer?.name ? `Клиент: ${ord.customer.name} (${ord.customer?.phone || ''})\n` : '';
    const addressStr = (ord?.delivery_address || ord?.customer?.address) ? `Адрес: ${ord.delivery_address || ord.customer.address}\n` : '';
    const priceStr = ord?.total_price ? `Сумма: ${this.formatPrice?.(ord.total_price) || ord.total_price}\n` : '';
    const botUser = this.botUsername || 'enclave_crmbot';
    const link = botUser
      ? `https://t.me/${botUser}?startapp=order_${orderId}`
      : `${window.location.origin}/tma/orders/${orderId}`;

    const text = `📦 Заказ #${orderId}${siteStr}\n${statusStr}${customerStr}${addressStr}${priceStr}${link}`;
    this.copyText?.(text, 'Сводка заказа скопирована');
  },

  stripLeadingZeros(val) {
    if (val === null || val === undefined) return '';
    const str = String(val).replace(',', '.');
    return str.replace(/^0+(?=\d)/, '');
  },

  onQtyInput(item, event) {
    if (event?.target) {
      const cleaned = this.stripLeadingZeros(event.target.value);
      if (cleaned !== event.target.value) {
        event.target.value = cleaned;
      }
      item.qty = cleaned === '' ? '' : parseInt(cleaned, 10);
    }
    this.recalculatePrices?.();
  },

  onQtyBlur(item, event) {
    if (item.qty === '' || item.qty === null || item.qty < 1 || isNaN(item.qty)) {
      item.qty = 1;
      if (event?.target) {
        event.target.value = 1;
      }
      this.recalculatePrices?.();
    }
  },

  onItemPriceInput(item, event) {
    if (event?.target) {
      const cleaned = this.stripLeadingZeros(event.target.value);
      if (cleaned !== event.target.value) {
        event.target.value = cleaned;
      }
      item.price = cleaned;
    }
    this.onItemPriceChange?.(item);
  },

  onItemPurchasePriceInput(item, event) {
    item._customPurchasePrice = true;
    if (event?.target) {
      const cleaned = this.stripLeadingZeros(event.target.value);
      if (cleaned !== event.target.value) {
        event.target.value = cleaned;
      }
      item.purchase_price = cleaned;
    }
    this.recalculatePrices?.();
  },

  onTotalPriceInput(event) {
    if (event?.target) {
      const cleaned = this.stripLeadingZeros(event.target.value);
      if (cleaned !== event.target.value) {
        event.target.value = cleaned;
      }
      this.form.total_price = cleaned;
    }
  },

  onPurchasePriceInput(event) {
    if (event?.target) {
      const cleaned = this.stripLeadingZeros(event.target.value);
      if (cleaned !== event.target.value) {
        event.target.value = cleaned;
      }
      this.form.purchase_price = cleaned;
    }
  },

  onPriceBlur(obj, field, event, callback) {
    if (event?.target) {
      let val = this.stripLeadingZeros(event.target.value);
      if (val.endsWith('.')) {
        val = val.slice(0, -1);
      }
      if (val !== event.target.value) {
        event.target.value = val;
      }
      obj[field] = val;
    }
    if (typeof callback === 'function') {
      callback();
    }
  },
};
