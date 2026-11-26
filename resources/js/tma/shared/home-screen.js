export const homeScreenMixin = {
  homeScreenStatus: 'unknown',
  isHomeScreenBannerDismissed: localStorage.getItem('tma_dismiss_homescreen_banner') === '1',

  get canPromptHomeScreen() {
    return this.homeScreenStatus === 'missed' || (Boolean(window.__pwaInstallPrompt) && this.homeScreenStatus !== 'added');
  },

  initHomeScreen() {
    const tg = window.Telegram?.WebApp;
    if (tg?.isVersionAtLeast?.('8.0') && tg?.checkHomeScreenStatus) {
      try {
        tg.checkHomeScreenStatus((status) => {
          this.homeScreenStatus = status;
        });
      } catch (_) {}
    }

    try {
      if (tg?.isVersionAtLeast?.('8.0')) {
        tg?.onEvent?.('homeScreenAdded', () => {
          this.homeScreenStatus = 'added';
          window.notify?.('Иконка добавлена на рабочий стол', 'success');
        });
        tg?.onEvent?.('homeScreenChecked', (payload) => {
          if (payload?.status) {
            this.homeScreenStatus = payload.status;
          }
        });
      }
    } catch (_) {}

    window.addEventListener('pwa-installable', () => {
      if (this.homeScreenStatus !== 'added') {
        this.homeScreenStatus = 'missed';
      }
    });
    window.addEventListener('pwa-installed', () => {
      this.homeScreenStatus = 'added';
    });
  },

  dismissHomeScreenBanner() {
    this.isHomeScreenBannerDismissed = true;
    localStorage.setItem('tma_dismiss_homescreen_banner', '1');
  },

  addToHomeScreen() {
    const tg = window.Telegram?.WebApp;
    if (tg?.isVersionAtLeast?.('8.0') && tg?.addToHomeScreen) {
      try {
        tg.HapticFeedback?.impactOccurred?.('light');
        tg.addToHomeScreen();
        return;
      } catch (_) {}
    }

    if (window.__pwaInstallPrompt) {
      window.__pwaInstallPrompt.prompt();
      window.__pwaInstallPrompt.userChoice.then((choiceResult) => {
        if (choiceResult?.outcome === 'accepted') {
          this.homeScreenStatus = 'added';
          window.notify?.('Иконка добавлена на рабочий стол', 'success');
        }
        window.__pwaInstallPrompt = null;
      }).catch(() => {});
      return;
    }

    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIOS) {
      window.notify?.('Нажмите «Поделиться» и выберите «На экран «Домой»»', 'info');
    } else {
      window.notify?.('Добавление не поддерживается вашим браузером', 'warn');
    }
  },
};
