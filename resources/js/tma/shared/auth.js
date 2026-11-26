import AuthController from '@/actions/App/Http/Controllers/AuthController';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import orders from '@/routes/tma/orders';
import tma from '@/routes/tma';

export async function waitForTelegramInitData(maxWait = 2000) {
  const start = Date.now();
  while (Date.now() - start < maxWait) {
    if (window.Telegram?.WebApp?.initData) {
      return true;
    }
    await new Promise((resolve) => setTimeout(resolve, 50));
  }
  return false;
}

export async function fetchWithTimeout(url, options = {}, timeout = 10000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  try {
    const response = await fetch(url, { credentials: 'include', ...options, signal: controller.signal });
    return response;
  } finally {
    clearTimeout(timer);
  }
}

function showErrorScreen(message) {
  try {
    document.documentElement.classList.remove('tma-ready');
    sessionStorage.removeItem('tma_app_loaded');
  } catch (_) {}
  const loadingEl = document.getElementById('loading');
  if (loadingEl) loadingEl.style.display = 'none';
  const appEl = document.getElementById('app');
  if (appEl) appEl.style.display = 'none';
  const errorEl = document.getElementById('error');
  if (errorEl) errorEl.style.display = 'flex';
  const debugEl = document.getElementById('debug-info');
  if (debugEl) debugEl.textContent = message;
}

export async function baseInit() {
  window.__tmaApp = this;

  // Clean URL query parameters for security and aesthetics
  if (typeof window !== 'undefined' && window.history?.replaceState) {
    try {
      const url = new URL(window.location.href);
      if (url.searchParams.has('token') || url.searchParams.has('session_token')) {
        url.searchParams.delete('token');
        url.searchParams.delete('session_token');
        window.history.replaceState({}, '', url.pathname + (url.search ? url.search : '') + url.hash);
      }
    } catch (_) {}
  }

  const isTelegram = await waitForTelegramInitData();

  // Detect Telegram account switch on shared devices/browsers
  const currentTelegramId = window.Telegram?.WebApp?.initDataUnsafe?.user?.id;
  if (isTelegram && currentTelegramId) {
    const sessionTelegramId = this.user?.telegram_user_id;
    const storedTelegramId = localStorage.getItem('tma_telegram_id');

    if ((sessionTelegramId && String(sessionTelegramId) !== String(currentTelegramId)) ||
        (storedTelegramId && String(storedTelegramId) !== String(currentTelegramId))) {
      this.user = null;
      localStorage.removeItem('tma_token');
      localStorage.removeItem('tma_telegram_id');
      window.__tmaToken = null;

      await this.authenticate();
      if (this.user?.session_token) {
        const roleRedirects = {
          owner: tma.role.url('owner'),
          manager: tma.manager.url(),
          courier: tma.role.url('courier'),
        };
        const targetUrl = (roleRedirects[this.user.role] || tma.role.url('owner')) + '?token=' + encodeURIComponent(this.user.session_token);
        this._isRedirecting = true;
        window.location.replace(targetUrl);
        return false;
      }
    }
  }

  const storedToken = localStorage.getItem('tma_token');
  let isAuthorizedBrowser = false;

  if (!isTelegram) {
    if (storedToken || this.user?.id) {
      try {
        const checkHeaders = { 'Accept': 'application/json' };
        if (storedToken) {
          checkHeaders['Authorization'] = `Bearer ${storedToken}`;
        }
        const meRes = await fetchWithTimeout(AuthController.me.url(), { headers: checkHeaders }, 5000);
        if (meRes.ok) {
          const meData = await meRes.json();
          this.user = { ...(this.user || {}), ...meData, session_token: storedToken };
          window.__tmaToken = storedToken;
          isAuthorizedBrowser = true;
        }
      } catch (e) {
        console.warn('[TMA] Browser session check failed:', e);
      }
    }

    if (!isAuthorizedBrowser) {
      this.user = null;
      localStorage.removeItem('tma_token');
      window.__tmaToken = null;
      showErrorScreen('Доступ разрешён только через Telegram Mini App или по ссылке для ПК');
      return false;
    }
  }

  const searchParams = new URLSearchParams(window.location.search);
  const initDataParams = new URLSearchParams(window.Telegram?.WebApp?.initData || '');
  const startParam = initDataParams.get('start_param')
    || window.Telegram?.WebApp?.initDataUnsafe?.start_param
    || searchParams.get('startapp')
    || searchParams.get('start_param')
    || searchParams.get('invite')
    || '';
  const orderMatch = String(startParam).match(/^(?:order[-_]?)?(\d+)$/i);
  const inviteToken = (!orderMatch && startParam) ? startParam : null;

  if (this.user?.session_token) {
    localStorage.setItem('tma_token', this.user.session_token);
    window.__tmaToken = this.user.session_token;
  } else if (this.user && storedToken) {
    this.user.session_token = storedToken;
    window.__tmaToken = storedToken;
  }

  if (!this.user?.session_token) {
    if (inviteToken && !this.user) {
      await this.acceptInvite(inviteToken);
      if (!this.user) return false;
    } else {
      await this.authenticate();
    }
  }

  if (!this.user) {
    const appEl = document.getElementById('app');
    if (appEl) appEl.style.display = 'none';
    showErrorScreen(this.lastAuthError || 'Ошибка авторизации Telegram');
    return false;
  }

  if (orderMatch) {
    const orderId = orderMatch[1];
    const targetPath = orders.show.url(orderId);
    if (window.location.pathname !== targetPath) {
      this._isRedirecting = true;
      window.location.replace(targetPath);
      return false;
    }
  }

  const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
  if (this.user?.role) {
    if (this.user.role === 'manager' && (currentPath === '/' || currentPath === '/tma' || currentPath.startsWith('/tma/owner') || currentPath === '/tma/financial' || currentPath === '/tma/sites' || currentPath === '/tma/personnel' || currentPath === '/tma/audit' || currentPath === '/tma/analytics')) {
      this._isRedirecting = true;
      window.location.replace(tma.manager.url());
      return false;
    }
    if (this.user.role === 'courier' && (currentPath === '/' || currentPath === '/tma' || currentPath.startsWith('/tma/owner') || currentPath.startsWith('/tma/manager') || currentPath === '/tma/financial' || currentPath === '/tma/sites' || currentPath === '/tma/personnel' || currentPath === '/tma/audit' || currentPath === '/tma/analytics')) {
      this._isRedirecting = true;
      window.location.replace(tma.role.url('courier'));
      return false;
    }
    if (this.user.role === 'owner' && (currentPath === '/' || currentPath === '/tma' || currentPath === '/tma/manager' || currentPath === '/tma/courier')) {
      this._isRedirecting = true;
      window.location.replace(tma.role.url('owner'));
      return false;
    }
  }
  if (this.user?.session_token) {
    localStorage.setItem('tma_token', this.user.session_token);
    window.__tmaToken = this.user.session_token;
  }
  window.disableClosingConfirmation?.(true);
  window.updateBackButtonVisibility?.();
  this.loadNotificationSetting?.();
  this.initHomeScreen?.();
  this.initBiometrics?.();
  this.initFullscreen?.();
  this.initDesktopMode?.();
  this.initNetworkListeners?.();
  this.initFontSize?.();
  this.initEcho();
  try {
    sessionStorage.removeItem('tma_auth_loop_count');
    document.documentElement.classList.add('tma-ready');
    sessionStorage.setItem('tma_app_loaded', '1');
  } catch (_) {}
  const loadingEl = document.getElementById('loading');
  if (loadingEl) loadingEl.style.display = 'none';
  const appEl = document.getElementById('app');
  if (appEl) appEl.style.display = 'block';

  if (this.user?.has_cloud_password) {
    try {
      localStorage.setItem('has_cloud_password', '1');
    } catch (_) {}
  } else if (this.user && this.user.has_cloud_password === false) {
    try {
      localStorage.removeItem('has_cloud_password');
    } catch (_) {}
  }

  this.checkCloudPasswordOnboarding?.();

  return true;
}

export const authMixin = {
  get isCourier() {
    return this.user?.role === 'courier';
  },

  get isManager() {
    return this.user?.role === 'manager';
  },

  get isOwner() {
    return this.user?.role === 'owner';
  },

  async authenticate() {
    if (this._authPromise) return this._authPromise;
    this._authPromise = (async () => {
      try {
        const response = await fetchWithTimeout(AuthController.tma.url(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ initData: window.Telegram?.WebApp?.initData || '' }),
        });
        const data = await response.json().catch(() => ({}));
        if (data.session_token) {
          this.user = { ...(this.user || {}), ...data };
          localStorage.setItem('tma_token', data.session_token);
          if (data.telegram_user_id || this.user?.telegram_user_id) {
            localStorage.setItem('tma_telegram_id', String(data.telegram_user_id || this.user.telegram_user_id));
          }
          window.__tmaToken = data.session_token;
          window.Telegram?.WebApp?.ready?.();
          window.dispatchEvent(new CustomEvent('tma:token-refreshed', { detail: { token: data.session_token } }));
        } else {
          this.lastAuthError = data.error || data.message || `Ошибка сервера (${response.status})`;
        }
      } catch (e) {
        console.error('[TMA] Auth failed:', e);
        this.lastAuthError = e.message || 'Ошибка сети';
      } finally {
        this._authPromise = null;
      }
    })();
    return this._authPromise;
  },

  async acceptInvite(token) {
    if (this._authPromise) return this._authPromise;
    this._authPromise = (async () => {
      try {
        const response = await fetchWithTimeout(InvitationController.accept.url(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ initData: window.Telegram?.WebApp?.initData || '', invite_token: token }),
        });
        const data = await response.json().catch(() => ({}));
        if (data.session_token) {
          this.user = { ...(this.user || {}), ...data };
          localStorage.setItem('tma_token', data.session_token);
          if (data.telegram_user_id || this.user?.telegram_user_id) {
            localStorage.setItem('tma_telegram_id', String(data.telegram_user_id || this.user.telegram_user_id));
          }
          window.__tmaToken = data.session_token;
          window.Telegram?.WebApp?.ready?.();
          window.dispatchEvent(new CustomEvent('tma:token-refreshed', { detail: { token: data.session_token } }));
        } else {
          await this.authenticate();
          if (!this.user?.session_token) {
            const loadingEl = document.getElementById('loading');
            if (loadingEl) loadingEl.style.display = 'none';
            const errorEl = document.getElementById('error');
            if (errorEl) errorEl.style.display = 'flex';
            const debugEl = document.getElementById('debug-info');
            if (debugEl) debugEl.textContent = data.error || data.message || JSON.stringify(data) || 'Invite invalid';
          }
        }
      } catch (e) {
        console.error('Invite accept failed:', e);
        await this.authenticate();
        if (!this.user?.session_token) {
          const loadingEl = document.getElementById('loading');
          if (loadingEl) loadingEl.style.display = 'none';
          const errorEl = document.getElementById('error');
          if (errorEl) errorEl.style.display = 'flex';
          const debugEl = document.getElementById('debug-info');
          if (debugEl) debugEl.textContent = 'Invite accept failed';
        }
      } finally {
        this._authPromise = null;
      }
    })();
    return this._authPromise;
  },

  async logout() {
    try {
      await this.api(AuthController.logout());
    } catch (e) {
      console.error('Logout failed:', e);
    } finally {
      this.user = null;
      try {
        localStorage.removeItem('tma_token');
        sessionStorage.removeItem('tma_app_loaded');
        document.documentElement.classList.remove('tma-ready');
      } catch (_) {}
      window.__tmaToken = null;
      window.location.href = '/tma';
    }
  },
};
