<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>Enclave CRM — {{ !empty($hasCloudPassword) ? 'Облачный пароль' : 'Установка облачного пароля' }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg text-fg font-body min-h-screen flex items-center justify-center p-4">
  <div class="bg-surface rounded-2xl p-7 max-w-sm w-full text-center shadow-2xl">
    <div class="mx-auto w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-5 border border-primary/20">
      <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
      </svg>
    </div>

    @if (!empty($hasCloudPassword))
      <h1 class="font-display text-lg font-semibold text-fg mb-1.5">Облачный пароль</h1>
      <p class="text-fg-muted text-xs leading-relaxed mb-4">
        Обнаружен вход с нового IP-адреса:
        <br>
        <span class="font-mono text-fg font-medium">{{ $ip }}</span>
        <br>
        <span class="text-[11px] text-fg-muted">{{ $country ?? 'Не определена 🌐' }}</span>
      </p>

      <form id="verifyForm" class="space-y-4 text-left">
        <input type="hidden" id="sessionToken" value="{{ $sessionToken ?? '' }}">
        <div>
          <label class="block text-xs font-medium text-fg-muted mb-1.5" for="cloudPassword">
            Введите ваш облачный пароль:
          </label>
          <div class="relative">
            <input type="password" id="cloudPassword" placeholder="••••••••" autocomplete="current-password" autofocus
                   class="w-full text-base font-mono py-2.5 px-4 pr-10 bg-bg rounded-xl text-fg focus:outline-none focus:border-primary transition-all">
            <button type="button" id="togglePasswordBtn" class="absolute inset-y-0 right-0 pr-3 flex items-center text-fg-muted hover:text-fg text-xs focus:outline-none">
              👁️
            </button>
          </div>
        </div>

        <div id="errorMsg" class="text-error text-xs hidden"></div>

        <button type="submit" id="submitBtn"
                class="w-full py-2.5 px-4 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-hover active:scale-95 transition-all shadow-md">
          Подтвердить доступ
        </button>
      </form>

      <div class="mt-4 text-center">
        <p class="text-[11px] text-fg-muted">
          Забыли пароль? Обратитесь к администратору для сброса.
        </p>
      </div>
    @else
      <h1 class="font-display text-lg font-semibold text-fg mb-1.5">Задайте облачный пароль</h1>
      <p class="text-fg-muted text-xs leading-relaxed mb-4">
        @if (!empty($isNewIp))
          Обнаружен вход с нового IP-адреса:
          <br>
          <span class="font-mono text-fg font-medium">{{ $ip }}</span>
          <br>
          <span class="text-[11px] text-fg-muted">{{ $country ?? 'Не определена 🌐' }}</span>
        @else
          Для защиты вашего аккаунта установите персональный облачный пароль:
        @endif
      </p>

      <form id="setupForm" class="space-y-3.5 text-left">
        <input type="hidden" id="sessionToken" value="{{ $sessionToken ?? '' }}">
        <div>
          <label class="block text-xs font-medium text-fg-muted mb-1" for="newPassword">
            Новый пароль (мин. 4 символа):
          </label>
          <input type="password" id="newPassword" placeholder="Придумайте пароль" autocomplete="new-password" autofocus
                 class="w-full text-sm font-mono py-2.5 px-3.5 bg-bg rounded-xl text-fg focus:outline-none focus:border-primary transition-all">
        </div>

        <div>
          <label class="block text-xs font-medium text-fg-muted mb-1" for="newPasswordConfirm">
            Повторите пароль:
          </label>
          <input type="password" id="newPasswordConfirm" placeholder="Повторите пароль" autocomplete="new-password"
                 class="w-full text-sm font-mono py-2.5 px-3.5 bg-bg rounded-xl text-fg focus:outline-none focus:border-primary transition-all">
        </div>

        <div id="errorMsgSetup" class="text-error text-xs hidden"></div>

        <button type="submit" id="submitSetupBtn"
                class="w-full py-2.5 px-4 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-hover active:scale-95 transition-all shadow-md">
          Установить и продолжить
        </button>
      </form>
    @endif

    <div class="mt-5 pt-4 border-t border-border flex items-center justify-between text-xs">
      <button type="button" id="blockBtn" class="text-error/80 hover:text-error text-[11px] hover:underline focus:outline-none">
        🚨 Не вы? Заблокировать вход
      </button>

      <a href="https://t.me/{{ config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot') }}" 
         class="text-[11px] text-primary hover:underline">
        Telegram-бот &rarr;
      </a>
    </div>
  </div>

  <script>
    const token = document.getElementById('sessionToken')?.value.trim() || localStorage.getItem('tma_session_token') || '';
    const errorDiv = document.getElementById('errorMsg');
    const submitBtn = document.getElementById('submitBtn');

    // Toggle password visibility
    const toggleBtn = document.getElementById('togglePasswordBtn');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function() {
        const input = document.getElementById('cloudPassword');
        if (input.type === 'password') {
          input.type = 'text';
          toggleBtn.textContent = '🙈';
        } else {
          input.type = 'password';
          toggleBtn.textContent = '👁️';
        }
      });
    }

    // Handle Verify Form
    const verifyForm = document.getElementById('verifyForm');
    if (verifyForm) {
      verifyForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const password = document.getElementById('cloudPassword').value.trim();

        if (!password) {
          showError('Введите облачный пароль');
          return;
        }

        setLoading(true, 'Проверка...');

        try {
          const res = await fetch('/api/auth/cloud-password/verify', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              ...(token ? { 'Authorization': 'Bearer ' + token } : {})
            },
            body: JSON.stringify({ password: password, token: token })
          });

          const data = await res.json();
          if (res.ok && data.status === 'ok') {
            window.location.reload();
          } else {
            showError(data.message || data.error || 'Неверный облачный пароль');
            setLoading(false, 'Подтвердить доступ');
          }
        } catch (err) {
          showError('Ошибка сети. Попробуйте снова.');
          setLoading(false, 'Подтвердить доступ');
        }
      });
    }

    // Handle Setup Form
    const setupForm = document.getElementById('setupForm');
    if (setupForm) {
      setupForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const pwd = document.getElementById('newPassword').value.trim();
        const pwdConfirm = document.getElementById('newPasswordConfirm').value.trim();
        const errorSetupEl = document.getElementById('errorMsgSetup');
        const submitSetupBtn = document.getElementById('submitSetupBtn');

        if (!pwd || pwd.length < 4) {
          errorSetupEl.textContent = 'Пароль должен содержать минимум 4 символа';
          errorSetupEl.classList.remove('hidden');
          return;
        }

        if (pwd !== pwdConfirm) {
          errorSetupEl.textContent = 'Введенные пароли не совпадают';
          errorSetupEl.classList.remove('hidden');
          return;
        }

        submitSetupBtn.disabled = true;
        submitSetupBtn.textContent = 'Сохранение...';
        errorSetupEl.classList.add('hidden');

        try {
          const res = await fetch('/api/auth/cloud-password/setup', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              ...(token ? { 'Authorization': 'Bearer ' + token } : {})
            },
            body: JSON.stringify({
              password: pwd,
              password_confirmation: pwdConfirm,
              token: token
            })
          });

          const data = await res.json();
          if (res.ok && data.status === 'ok') {
            try {
              localStorage.setItem('has_cloud_password', '1');
            } catch (_) {}
            window.location.reload();
          } else {
            errorSetupEl.textContent = data.message || data.error || 'Ошибка при установке пароля';
            errorSetupEl.classList.remove('hidden');
            submitSetupBtn.disabled = false;
            submitSetupBtn.textContent = 'Установить и продолжить';
          }
        } catch (err) {
          errorSetupEl.textContent = 'Ошибка сети. Попробуйте снова.';
          errorSetupEl.classList.remove('hidden');
          submitSetupBtn.disabled = false;
          submitSetupBtn.textContent = 'Установить и продолжить';
        }
      });
    }

    // Handle Block Button via confirmDialog
    const blockBtn = document.getElementById('blockBtn');
    if (blockBtn) {
      blockBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        const confirmed = await window.confirmDialog({
          title: 'Заблокировать сессию?',
          message: 'Вы уверены, что хотите заблокировать эту сессию и закрыть доступ? Все активные действия с этого IP-адреса будут немедленно прекращены.',
          confirmText: 'Заблокировать',
          cancelText: 'Отмена',
          variant: 'danger',
        });

        if (!confirmed) return;

        blockBtn.disabled = true;
        try {
          const res = await fetch('/api/auth/block-session', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              ...(token ? { 'Authorization': 'Bearer ' + token } : {})
            },
            body: JSON.stringify({ token: token })
          });

          const data = await res.json();
          if (res.ok) {
            window.notify?.(data.message || 'Сессия отозвана, доступ заблокирован.', 'success');
            setTimeout(() => {
              window.location.reload();
            }, 1200);
          } else {
            showError(data.message || data.error || 'Ошибка при блокировке сессии.');
            blockBtn.disabled = false;
          }
        } catch (err) {
          showError('Ошибка сети при блокировке сессии.');
          blockBtn.disabled = false;
        }
      });
    }

    function showError(msg) {
      errorDiv.textContent = msg;
      errorDiv.classList.remove('hidden');
    }

    function setLoading(isLoading, text) {
      submitBtn.disabled = isLoading;
      submitBtn.textContent = text;
      if (isLoading) {
        errorDiv.classList.add('hidden');
      }
    }
  </script>

  @include('tma.partials.confirm-dialog')
  @include('tma.partials.notification')
</body>
</html>
