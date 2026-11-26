export const hapticsMixin = {
  hapticFeedback(type = 'success') {
    try {
      const normalizedType = type === 'warn' ? 'warning' : type;
      if (normalizedType === 'success' || normalizedType === 'error' || normalizedType === 'warning') {
        window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.(normalizedType);
      } else {
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.(type);
      }
    } catch (_) {}
  },

  hapticSuccess() {
    this.hapticFeedback('success');
  },

  hapticError() {
    this.hapticFeedback('error');
  },

  hapticWarning() {
    this.hapticFeedback('warning');
  },

  hapticImpact(style = 'medium') {
    try {
      window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.(style);
    } catch (_) {}
  },
};
