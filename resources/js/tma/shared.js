import { baseInit, authMixin } from './shared/auth.js';
import { apiMixin } from './shared/api.js';
import { uiMixin } from './shared/ui.js';
import { biometricsMixin } from './shared/biometrics.js';
import { desktopAuthMixin } from './shared/desktop-auth.js';
import { homeScreenMixin } from './shared/home-screen.js';
import { hapticsMixin } from './shared/haptics.js';
import { navigationMixin } from './shared/navigation.js';
import { notificationsMixin } from './shared/notifications.js';
import { eventsMixin } from './shared/events.js';
import { formattersMixin } from './shared/formatters.js';
import { clipboardMixin } from './shared/clipboard.js';
import { floatingChatMixin } from './shared/floating-chat.js';
import { cloudPasswordMixin } from './shared/cloud-password.js';

export { baseInit };

export function withTmaBase(...extensions) {
  const merged = {};

  const baseMixins = [
    {
      user: null,
      retentionDays: 0,
      botUsername: '',

      async init() {
        await baseInit.call(this);
      },
    },
    authMixin,
    apiMixin,
    uiMixin,
    biometricsMixin,
    desktopAuthMixin,
    homeScreenMixin,
    hapticsMixin,
    navigationMixin,
    notificationsMixin,
    eventsMixin,
    formattersMixin,
    clipboardMixin,
    floatingChatMixin,
    cloudPasswordMixin,
    ...extensions,
  ];

  for (const mixin of baseMixins) {
    if (!mixin || typeof mixin !== 'object') continue;
    for (const [key, descriptor] of Object.entries(Object.getOwnPropertyDescriptors(mixin))) {
      Object.defineProperty(merged, key, descriptor);
    }
  }

  const userInit = merged.init;
  merged.init = async function() {
    try {
      if (typeof userInit === 'function') {
        await userInit.call(this);
      } else {
        await baseInit.call(this);
      }
      this.initFloatingChat?.();
    } finally {
      const errorEl = document.getElementById('error');
      const isErrorVisible = errorEl && errorEl.style.display !== 'none';
      if (!isErrorVisible && !this._isRedirecting) {
        try {
          document.documentElement.classList.add('tma-ready');
          sessionStorage.setItem('tma_app_loaded', '1');
        } catch (_) {}
        const loadingEl = document.getElementById('loading');
        if (loadingEl) loadingEl.style.display = 'none';
        const appEl = document.getElementById('app');
        if (appEl) appEl.style.display = 'block';
      }
    }
  };

  return merged;
}
