export const uiMixin = {
  isOffline: typeof navigator !== 'undefined' ? !navigator.onLine : false,
  showSignatureModal: false,
  signatureUploading: false,
  showDeleteSignatureModal: false,
  deleteSignatureTargetOrderId: null,

  initNetworkListeners() {
    if (typeof window === 'undefined') return;
    window.addEventListener('online', () => {
      this.isOffline = false;
      this.notifyToast?.('Подключение к сети восстановлено', 'success');
    });
    window.addEventListener('offline', () => {
      this.isOffline = true;
      this.notifyToast?.('Отсутствует подключение к интернету', 'error');
    });
  },

  isDesktopMode: (() => {
    try {
      return localStorage.getItem('tma_desktop_mode') === '1';
    } catch (_) {
      return false;
    }
  })(),

  initDesktopMode() {
    try {
      this.isDesktopMode = localStorage.getItem('tma_desktop_mode') === '1';
      this.applyDesktopMode();
    } catch (_) {}
  },

  toggleDesktopMode(enable) {
    const target = enable !== undefined ? Boolean(enable) : !this.isDesktopMode;
    this.isDesktopMode = target;
    try {
      localStorage.setItem('tma_desktop_mode', target ? '1' : '0');
    } catch (_) {}
    this.applyDesktopMode();
    try {
      window.Telegram?.WebApp?.HapticFeedback?.selectionChanged?.();
    } catch (_) {}
  },

  applyDesktopMode() {
    if (this.isDesktopMode) {
      document.documentElement.classList.add('desktop-mode');
      document.body?.classList.add('desktop-mode');
    } else {
      document.documentElement.classList.remove('desktop-mode');
      document.body?.classList.remove('desktop-mode');
    }
  },

  isSidebarCollapsed: (() => {
    try {
      return localStorage.getItem('tma_sidebar_collapsed') === '1';
    } catch (_) {
      return false;
    }
  })(),

  toggleSidebarCollapse(force) {
    const target = force !== undefined ? Boolean(force) : !this.isSidebarCollapsed;
    this.isSidebarCollapsed = target;
    try {
      localStorage.setItem('tma_sidebar_collapsed', target ? '1' : '0');
      window.Telegram?.WebApp?.HapticFeedback?.selectionChanged?.();
    } catch (_) {}
  },

  fontSize: (() => {
    try {
      const val = localStorage.getItem('tma_font_size');
      return val && ['small', 'normal', 'large'].includes(val) ? val : 'normal';
    } catch (_) {
      return 'normal';
    }
  })(),

  setFontSize(size) {
    if (!['small', 'normal', 'large'].includes(size)) {
      size = 'normal';
    }
    this.fontSize = size;
    try {
      localStorage.setItem('tma_font_size', size);
      window.Telegram?.WebApp?.HapticFeedback?.selectionChanged?.();
    } catch (_) {}
    this.applyFontSize();
  },

  applyFontSize() {
    const size = this.fontSize || 'normal';
    document.documentElement.setAttribute('data-font-size', size);
  },

  initFontSize() {
    try {
      const val = localStorage.getItem('tma_font_size');
      this.fontSize = val && ['small', 'normal', 'large'].includes(val) ? val : 'normal';
      this.applyFontSize();
    } catch (_) {}
  },

  isFullscreen: false,

  initFullscreen() {
    const tg = window.Telegram?.WebApp;
    try {
      tg?.expand?.();
    } catch (_) {}

    if (tg) {
      this.isFullscreen = Boolean(tg.isFullscreen);
      try {
        tg.onEvent?.('fullscreenChanged', () => {
          this.isFullscreen = Boolean(tg.isFullscreen);
          localStorage.setItem('tma_fullscreen', this.isFullscreen ? '1' : '0');
        });
        tg.onEvent?.('fullscreenFailed', (error) => {
          console.warn('[TMA] fullscreenFailed:', error);
        });
      } catch (_) {}
    }

    if (!this._fullscreenListenerAdded) {
      this._fullscreenListenerAdded = true;
      document.addEventListener('fullscreenchange', () => {
        if (!tg?.isFullscreen && typeof tg?.requestFullscreen !== 'function') {
          this.isFullscreen = Boolean(document.fullscreenElement);
          localStorage.setItem('tma_fullscreen', this.isFullscreen ? '1' : '0');
        }
      });
    }

    if (localStorage.getItem('tma_fullscreen') === '1') {
      this.requestFullscreen();
    }
  },

  async requestFullscreen() {
    const tg = window.Telegram?.WebApp;
    if (tg?.isVersionAtLeast?.('8.0') && typeof tg?.requestFullscreen === 'function') {
      try {
        tg.HapticFeedback?.impactOccurred?.('light');
        tg.requestFullscreen();
        this.isFullscreen = true;
        localStorage.setItem('tma_fullscreen', '1');
        return;
      } catch (e) {
        console.warn('[TMA] requestFullscreen failed:', e);
      }
    }

    if (document.documentElement.requestFullscreen) {
      try {
        await document.documentElement.requestFullscreen();
        this.isFullscreen = true;
        localStorage.setItem('tma_fullscreen', '1');
      } catch (_) {}
    }
  },

  async exitFullscreen() {
    const tg = window.Telegram?.WebApp;
    if (tg?.isVersionAtLeast?.('8.0') && typeof tg?.exitFullscreen === 'function') {
      try {
        tg.HapticFeedback?.impactOccurred?.('light');
        tg.exitFullscreen();
        this.isFullscreen = false;
        localStorage.setItem('tma_fullscreen', '0');
        return;
      } catch (e) {
        console.warn('[TMA] exitFullscreen failed:', e);
      }
    }

    if (document.exitFullscreen && document.fullscreenElement) {
      try {
        await document.exitFullscreen();
        this.isFullscreen = false;
        localStorage.setItem('tma_fullscreen', '0');
      } catch (_) {}
    }
  },

  toggleFullscreen(enable) {
    const target = enable !== undefined ? enable : !this.isFullscreen;
    if (target) {
      this.requestFullscreen();
    } else {
      this.exitFullscreen();
    }
  },

  setUploadLoading(active, options = {}) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent('upload-loading', {
      detail: typeof options === 'string'
        ? { active, title: options }
        : { active, ...options },
    }));
  },
};
