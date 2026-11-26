<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet"/>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <title>Enclave CRM</title>
  <meta name="theme-color" content="#090d16"/>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    .loading-screen { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  </style>
</head>
<body class="bg-bg text-fg font-body min-h-screen">
  <div id="loading" class="loading-screen">
    <div class="flex flex-col items-center gap-3">
      <div class="w-10 h-10 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-fg-muted text-sm">Авторизация...</p>
    </div>
  </div>

  <div id="inviteGate" class="loading-screen" style="display: none;">
    <div class="w-full max-w-sm mx-auto px-6">
      <div class="bg-surface rounded-2xl p-8 text-center shadow-2xl">
        <div class="mx-auto w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-5 border border-primary/20">
          <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
          </svg>
        </div>
        <h2 class="font-display text-lg font-semibold text-fg mb-2">Приглашение в CRM</h2>
        <p class="text-fg-muted text-sm leading-relaxed mb-6">
          Чтобы активировать приглашение и войти в систему, откройте ссылку через Telegram бота.
        </p>
        <a id="openInTelegramBtn" href="#"
           class="inline-flex items-center justify-center w-full py-2.5 px-4 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-hover active:scale-95 transition-all shadow-md">
          Открыть в Telegram
        </a>
      </div>
    </div>
  </div>

  <div id="error" class="loading-screen" style="display: none;">
    <div class="w-full max-w-sm mx-auto px-6">
      <div class="bg-surface rounded-2xl p-8 text-center shadow-2xl">
        <div class="mx-auto w-14 h-14 rounded-full bg-error-bg flex items-center justify-center mb-5">
          <svg class="w-7 h-7 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
          </svg>
        </div>
        <h2 class="font-display text-lg font-semibold text-fg mb-2">Невозможно открыть приложение</h2>
        <p class="text-fg-muted text-sm leading-relaxed mb-4">Откройте приложение через Telegram бота, чтобы продолжить работу.</p>
        <div id="debug-info" class="text-fg-subtle text-xs bg-bg/50 rounded-lg px-3 py-2 "></div>
      </div>
    </div>
  </div>

  <script>
    (async function() {
      const defaultBot = @json($botUsername ?? config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot'));

      async function waitForTelegramInitData(maxWait = 2500) {
        const start = Date.now();
        while (Date.now() - start < maxWait) {
          if (window.Telegram?.WebApp?.initData) {
            return true;
          }
          await new Promise(r => setTimeout(r, 50));
        }
        return false;
      }

      const hasInitData = await waitForTelegramInitData();
      const storedToken = localStorage.getItem('tma_token');

      const searchParams = new URLSearchParams(window.location.search);
      const initDataParams = new URLSearchParams(window.Telegram?.WebApp?.initData || '');
      const rawStartParam = initDataParams.get('start_param')
        || window.Telegram?.WebApp?.initDataUnsafe?.start_param
        || searchParams.get('startapp')
        || searchParams.get('start_param')
        || searchParams.get('invite')
        || '';

      const orderMatch = String(rawStartParam).match(/^(?:order[-_]?)?(\d+)$/i);
      let inviteToken = (!orderMatch && rawStartParam) ? String(rawStartParam) : null;
      if (inviteToken && inviteToken.startsWith('invite_')) {
        inviteToken = inviteToken.replace(/^invite_/, '');
      }

      const authAttemptsKey = 'tma_auth_loop_count';
      const authAttempts = parseInt(sessionStorage.getItem(authAttemptsKey) || '0', 10);
      if (authAttempts >= 4) {
        sessionStorage.removeItem(authAttemptsKey);
        const loadingEl = document.getElementById('loading');
        const errorEl = document.getElementById('error');
        if (loadingEl) loadingEl.style.display = 'none';
        if (errorEl) errorEl.style.display = 'flex';
        const debugEl = document.getElementById('debug-info');
        if (debugEl) {
          debugEl.textContent = 'Ошибка сохранения сессии браузера. Проверьте настройки cookies или перезапустите бота.';
        }
        return;
      }

      if (!hasInitData && !storedToken) {
        if (inviteToken) {
          const loadingEl = document.getElementById('loading');
          const gateEl = document.getElementById('inviteGate');
          const btn = document.getElementById('openInTelegramBtn');
          if (loadingEl) loadingEl.style.display = 'none';
          if (gateEl) gateEl.style.display = 'flex';
          if (btn) {
            btn.href = `https://t.me/${defaultBot}?start=${encodeURIComponent(inviteToken)}`;
          }
          return;
        }

        window.location.replace('/blocked');
        return;
      }

      if (hasInitData) {
        try {
          let inviteError = null;

          if (inviteToken) {
            try {
              const inviteRes = await fetch('/api/invitations/accept', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json'
                },
                body: JSON.stringify({
                  initData: window.Telegram.WebApp.initData,
                  invite_token: inviteToken
                })
              });

              const inviteData = await inviteRes.json().catch(() => ({}));
              if (inviteRes.ok && inviteData.session_token) {
                sessionStorage.setItem(authAttemptsKey, String(authAttempts + 1));
                localStorage.setItem('tma_token', inviteData.session_token);
                if (inviteData.telegram_user_id) {
                  localStorage.setItem('tma_telegram_id', String(inviteData.telegram_user_id));
                }
                try {
                  window.Telegram?.WebApp?.ready?.();
                } catch (_) {}
                const rolePath = inviteData.role === 'courier' ? '/tma/courier' : (inviteData.role === 'manager' ? '/tma/manager' : '/tma/owner');
                const targetUrl = rolePath + '?token=' + encodeURIComponent(inviteData.session_token);
                window.location.replace(targetUrl);
                return;
              }
              inviteError = inviteData.error || inviteData.message;
            } catch (_) {}
          }

          const res = await fetch('/api/auth/tma', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            body: JSON.stringify({
              initData: window.Telegram.WebApp.initData,
              session_token: storedToken || undefined
            })
          });

          const data = await res.json().catch(() => ({}));
          if (res.ok && data.session_token) {
            sessionStorage.setItem(authAttemptsKey, String(authAttempts + 1));
            localStorage.setItem('tma_token', data.session_token);
            if (data.telegram_user_id) {
              localStorage.setItem('tma_telegram_id', String(data.telegram_user_id));
            }
            try {
              window.Telegram?.WebApp?.ready?.();
            } catch (_) {}

            if (orderMatch) {
              window.location.replace('/tma/orders/' + orderMatch[1] + '?token=' + encodeURIComponent(data.session_token));
              return;
            }

            const roleRedirects = {
              owner: '/tma/owner',
              manager: '/tma/manager',
              courier: '/tma/courier'
            };
            const basePath = roleRedirects[data.role] || '/tma/owner';
            const targetUrl = basePath + '?token=' + encodeURIComponent(data.session_token);

            window.location.replace(targetUrl);
            return;
          }

          document.getElementById('loading').style.display = 'none';
          document.getElementById('error').style.display = 'flex';
          const debugEl = document.getElementById('debug-info');
          if (debugEl) {
            debugEl.textContent = inviteError || data.message || data.error || 'Ошибка авторизации Telegram';
          }
        } catch (e) {
          document.getElementById('loading').style.display = 'none';
          document.getElementById('error').style.display = 'flex';
          const debugEl = document.getElementById('debug-info');
          if (debugEl) debugEl.textContent = 'Ошибка подключения к серверу';
        }
        return;
      }

      if (storedToken) {
        try {
          const syncRes = await fetch('/api/auth/session/sync', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + storedToken
            }
          });
          if (syncRes.ok) {
            sessionStorage.setItem(authAttemptsKey, String(authAttempts + 1));
            const syncData = await syncRes.json().catch(() => ({}));
            const roleRedirects = {
              owner: '/tma/owner',
              manager: '/tma/manager',
              courier: '/tma/courier'
            };
            const basePath = roleRedirects[syncData.role] || '/tma/owner';
            const targetUrl = basePath + '?token=' + encodeURIComponent(storedToken);
            window.location.replace(targetUrl);
            return;
          }
        } catch (_) {}
        localStorage.removeItem('tma_token');
        window.location.replace('/blocked');
      }
    })();
  </script>
</body>
</html>
