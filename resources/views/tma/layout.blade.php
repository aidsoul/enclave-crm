<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet"/>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <title>Enclave CRM</title>
  <meta name="author" content="AidSoul <work-aidsoul@outlook.com>"/>
  <link rel="manifest" href="/manifest.json"/>
  <link rel="icon" type="image/svg+xml" href="/icons/icon.svg"/>
  <link rel="apple-touch-icon" href="/icons/icon-512.png"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
  <meta name="apple-mobile-web-app-title" content="Enclave CRM"/>
  <meta name="theme-color" content="#090d16"/>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    [x-cloak] { display: none !important; }
    .loading-screen { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    html.tma-ready #loading { display: none !important; }
    html.tma-ready #app { display: block !important; }
    #debug-panel {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.9); color: #0f0; font-family: monospace;
      font-size: 12px; padding: 10px; overflow: auto; z-index: 9999;
      white-space: pre-wrap; word-break: break-word;
    }
    #debug-panel .log-entry { margin: 2px 0; }
    #debug-panel .log-error { color: #f55; }
    #debug-panel .log-warn { color: #fa5; }
    #debug-panel .log-info { color: #5f5; }
    .json-tree details > summary { list-style: none; }
    .json-tree details > summary::-webkit-details-marker { display: none; }
    .json-tree details > summary::before {
      content: '▶';
      display: inline-block;
      font-size: 9px;
      margin-right: 4px;
      transition: transform 150ms ease;
      color: currentColor;
      opacity: 0.7;
    }
    .json-tree details[open] > summary::before {
      transform: rotate(90deg);
    }
    .json-tree details > summary:hover { opacity: 1; }
    .json-tree { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .json-tree .json-summary { padding: 2px 0; }
    .json-tree .json-summary:hover { background: rgba(255,255,255,0.04); border-radius: 4px; }
  </style>
  <script>
    try {
      if (localStorage.getItem('tma_desktop_mode') === '1') {
        document.documentElement.classList.add('desktop-mode');
      }
      const fs = localStorage.getItem('tma_font_size');
      if (fs && ['small', 'normal', 'large'].includes(fs)) {
        document.documentElement.setAttribute('data-font-size', fs);
      }
      if (localStorage.getItem('tma_token') || sessionStorage.getItem('tma_app_loaded') === '1') {
        document.documentElement.classList.add('tma-ready');
      }
    } catch (_) {}
    document.addEventListener('click', function(e) {
      const link = e.target && e.target.closest ? e.target.closest('a') : null;
      if (!link || link.target === '_blank') return;
      const href = link.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:') || href.startsWith('http://') || href.startsWith('https://')) return;
      if (!href.startsWith('/tma') && href !== '/') return;
      const token = localStorage.getItem('tma_token');
      if (token) {
        try {
          const url = new URL(link.href, window.location.origin);
          if (!url.searchParams.has('token')) {
            url.searchParams.set('token', token);
            link.href = url.pathname + (url.search ? url.search : '') + url.hash;
          }
        } catch (_) {}
      }
    }, true);
    window.__APP_CURRENCY__ = @json(config('app.currency', 'BYN'));
    window.__APP_CURRENCY_SYMBOL__ = @json(config('app.currency_symbol', 'Br'));
    window.__DEFAULT_PURCHASE_PERCENT__ = @json(config('orders.default_purchase_percent', 15.0));
    window.__TMA_DEBUG__ = [];
    window.__TMA_LOG__ = function(msg, type) {
      const entry = { time: new Date().toISOString(), msg: String(msg), type: type || 'info' };
      window.__TMA_DEBUG__.push(entry);
    };
    window.__TMA_SHOW_DEBUG__ = function() {
      let panel = document.getElementById('debug-panel');
      if (!panel) {
        panel = document.createElement('div');
        panel.id = 'debug-panel';
        document.body.appendChild(panel);
      }
      panel.replaceChildren(...window.__TMA_DEBUG__.map(e => {
        const div = document.createElement('div');
        div.className = 'log-entry log-' + String(e.type || 'info').replace(/[^a-zA-Z0-9_-]/g, '');
        div.textContent = '[' + e.time + '] ' + e.msg;
        return div;
      }));
    };
    setTimeout(() => {
      if (!window.__TMA_DEBUG__ || window.__TMA_DEBUG__.length === 0) return;
      const loading = document.getElementById('loading');
      if (loading && loading.style.display !== 'none') {
        window.__TMA_SHOW_DEBUG__();
      }
    }, 15000);
  </script>
</head>
<body class="bg-bg text-fg font-body min-h-screen @yield('body_class')">
  <div id="loading" class="loading-screen">
    <div class="flex flex-col items-center gap-3">
      <div class="w-10 h-10 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-fg-muted text-sm">Загрузка...</p>
    </div>
  </div>

  <div id="error" class="loading-screen" style="display: none;">
    <div class="w-full max-w-sm mx-auto px-6">
      <div class="bg-surface rounded-2xl p-8 text-center">
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

  <div id="app" style="display: none;" x-data='tmaApp(@json($appUserData, JSON_HEX_APOS), @json($retentionDays ?? 0), @json(config("services.telegram.bot_username") ?: config("nutgram.name")))'>

    <div :class="isDesktopMode ? 'w-full min-h-screen flex items-start' : 'max-w-[480px] mx-auto px-4 py-4 pb-24'">

      @include('tma.partials.sidebar')

      <div :class="isDesktopMode ? 'flex-1 min-w-0 px-6 lg:px-8 py-6 pb-16' : 'min-w-0 flex-1 w-full'">
        <div x-cloak x-show="isOffline" class="mb-4 p-3 bg-error/10 border border-error/20 rounded-xl flex items-center gap-3 text-error">
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 4.243a9 9 0 01-2.828-6.364m0 0l2.828 2.828M3 3l18 18"/>
          </svg>
          <div class="min-w-0 flex-1 text-xs">
            <p class="font-semibold text-fg">Нет подключения к сети</p>
            <p class="text-fg-muted">Проверьте интернет-соединение. Некоторые функции могут быть недоступны.</p>
          </div>
        </div>

        <div x-cloak x-show="canPromptHomeScreen && !isHomeScreenBannerDismissed" class="mb-4 p-3 bg-primary/10 border border-primary/20 rounded-xl flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center text-primary shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold text-fg truncate">Иконка на рабочий стол</p>
              <p class="text-[11px] text-fg-muted truncate">Быстрый запуск CRM в 1 клик</p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button @click="addToHomeScreen()" class="px-2.5 py-1.5 bg-primary text-white text-xs font-medium rounded-lg shadow-sm hover:bg-primary-hover active:scale-95 transition-all">
              Добавить
            </button>
            <button @click="dismissHomeScreenBanner()" class="p-1 text-fg-subtle hover:text-fg transition-colors" title="Скрыть">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        @yield('content')
      </div>
    </div>

    @include('tma.partials.desktop-auth-modal')
    @include('tma.partials.product-detail-modal')
    @include('tma.partials.floating-chat')
    @include('tma.components.cloud-password-modal')
  </div>

  @include('tma.partials.confirm-dialog')
  @include('tma.partials.notification')
  @include('tma.partials.upload-loader')
</body>
</html>
