export const biometricsMixin = {
  biometricInited: false,
  biometricAvailable: false,
  biometricType: 'unknown',
  biometricAccessGranted: false,
  biometricEnabled: localStorage.getItem('tma_biometric_enabled') !== '0',

  get biometricLabel() {
    if (this.biometricType === 'face') return 'Face ID';
    if (this.biometricType === 'finger') return 'Touch ID / Отпечаток';
    return 'Биометрия';
  },

  initBiometrics() {
    const bm = window.Telegram?.WebApp?.BiometricManager;
    if (!bm) return;

    const syncState = () => {
      this.biometricInited = Boolean(bm.isInited);
      this.biometricAvailable = Boolean(bm.isBiometricAvailable);
      this.biometricType = bm.biometricType || 'unknown';
      this.biometricAccessGranted = Boolean(bm.isAccessGranted);
    };

    try {
      if (!bm.isInited) {
        bm.init(() => syncState());
      } else {
        syncState();
      }
    } catch (_) {}
  },

  async requestBiometricAuth(reason = 'Подтвердите действие биометрией', force = false) {
    const bm = window.Telegram?.WebApp?.BiometricManager;
    if (!bm || !bm.isBiometricAvailable) return true;
    if (!force && localStorage.getItem('tma_biometric_enabled') === '0') return true;

    return new Promise((resolve) => {
      const doAuth = () => {
        try {
          bm.authenticate({ reason }, (authenticated) => {
            if (authenticated) {
              window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
              resolve(true);
            } else {
              window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('error');
              window.notify?.('Биометрическая проверка не пройдена', 'error');
              resolve(false);
            }
          });
        } catch (_) {
          resolve(true);
        }
      };

      if (!bm.isAccessGranted) {
        try {
          bm.requestAccess({ reason }, (granted) => {
            this.biometricAccessGranted = granted;
            if (granted) {
              doAuth();
            } else {
              window.notify?.('Доступ к биометрии отклонен', 'warn');
              resolve(false);
            }
          });
        } catch (_) {
          resolve(true);
        }
      } else {
        doAuth();
      }
    });
  },

  async toggleBiometric(enable) {
    const bm = window.Telegram?.WebApp?.BiometricManager;
    if (enable && bm && bm.isBiometricAvailable && !bm.isAccessGranted) {
      return new Promise((resolve) => {
        try {
          bm.requestAccess({ reason: 'Включить защиту чувствительных операций' }, (granted) => {
            this.biometricAccessGranted = granted;
            if (granted) {
              this.biometricEnabled = true;
              localStorage.setItem('tma_biometric_enabled', '1');
              window.notify?.(`${this.biometricLabel} подключен`, 'success');
              resolve(true);
            } else {
              this.biometricEnabled = false;
              localStorage.setItem('tma_biometric_enabled', '0');
              window.notify?.('Доступ к биометрии отклонен', 'warn');
              resolve(false);
            }
          });
        } catch (_) {
          this.biometricEnabled = true;
          localStorage.setItem('tma_biometric_enabled', '1');
          resolve(true);
        }
      });
    }

    this.biometricEnabled = Boolean(enable);
    localStorage.setItem('tma_biometric_enabled', enable ? '1' : '0');
    if (enable) {
      window.notify?.(`${this.biometricLabel} включен`, 'success');
    } else {
      window.notify?.('Биометрия отключена', 'info');
    }
    return true;
  },

  openBiometricSettings() {
    try {
      window.Telegram?.WebApp?.BiometricManager?.openSettings?.();
    } catch (_) {}
  },
};
