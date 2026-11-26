import AuthController from '@/actions/App/Http/Controllers/AuthController';

export const desktopAuthMixin = {
  desktopAuthModalOpen: false,
  desktopAuthLoading: false,
  desktopAuthUrl: '',
  desktopAuthSent: false,
  desktopAuthTimer: 0,
  desktopAuthInterval: null,

  cleanupDesktopAuth() {
    if (this.desktopAuthInterval) {
      clearInterval(this.desktopAuthInterval);
      this.desktopAuthInterval = null;
    }
  },

  async openDesktopAuthModal(autoSend = false) {
    this.desktopAuthModalOpen = true;
    this.cleanupDesktopAuth();
    await this.generateDesktopLink(autoSend);
  },

  async generateDesktopLink(sendToTelegram = false) {
    this.desktopAuthLoading = true;
    try {
      const data = await this.api(AuthController.createDesktopLink(), {
        body: JSON.stringify({ send_to_telegram: sendToTelegram }),
      });
      if (data?.url) {
        this.desktopAuthUrl = data.url;
        this.desktopAuthSent = Boolean(data.sent_to_telegram);
        this.desktopAuthTimer = data.expires_in || 300;
        this.cleanupDesktopAuth();
        this.desktopAuthInterval = setInterval(() => {
          if (this.desktopAuthTimer > 0) {
            this.desktopAuthTimer--;
          } else {
            this.cleanupDesktopAuth();
          }
        }, 1000);
        if (sendToTelegram) {
          if (this.desktopAuthSent) {
            window.notify?.('Ссылка отправлена в чат с ботом!', 'success');
          } else {
            window.notify?.('Ссылка создана! Нажмите «Скопировать ссылку», чтобы открыть ее в браузере.', 'warn');
          }
        }
      } else {
        window.notify?.('Не удалось создать ссылку для ПК', 'error');
      }
    } catch (e) {
      console.error('generateDesktopLink error:', e);
      window.notify?.('Ошибка при создании ссылки', 'error');
    } finally {
      this.desktopAuthLoading = false;
    }
  },

  copyDesktopLink() {
    if (!this.desktopAuthUrl) return;
    this.copyText?.(this.desktopAuthUrl, 'Ссылка для ПК скопирована');
  },

  get desktopAuthTimerFormatted() {
    const m = Math.floor(this.desktopAuthTimer / 60);
    const s = this.desktopAuthTimer % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  },
};
