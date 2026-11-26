<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>@yield('title', 'Ошибка ' . $__env->yieldContent('code', '404')) — Enclave CRM</title>
  <meta name="author" content="AidSoul <work-aidsoul@outlook.com>">
  <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
  <meta name="theme-color" content="#090d16">
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  @vite(['resources/css/app.css'])
  <style>
    :root {
      --bg: #090d16;
      --surface: #121620;
      --surface-hover: #171d2b;
      --border: #232a38;
      --border-light: rgba(255, 255, 255, 0.08);
      --fg: #f1f3f6;
      --fg-muted: #94a3b8;
      --fg-subtle: #64748b;
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --accent: var(--error-accent, #38bdf8);
      --accent-glow: var(--error-glow, rgba(56, 189, 248, 0.2));
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      -webkit-tap-highlight-color: transparent;
    }
    body {
      background-color: var(--bg);
      color: var(--fg);
      font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      position: relative;
      overflow-x: hidden;
    }
    .grid-pattern {
      position: fixed;
      inset: 0;
      background-image: radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
      background-size: 24px 24px;
      pointer-events: none;
      z-index: 0;
    }
    .ambient-glow {
      position: fixed;
      top: -100px;
      left: 50%;
      transform: translateX(-50%);
      width: 700px;
      height: 450px;
      background: radial-gradient(ellipse at center, var(--accent-glow) 0%, rgba(9, 13, 22, 0) 70%);
      pointer-events: none;
      z-index: 0;
      transition: background 0.3s ease;
    }
    .error-card {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 440px;
      background: rgba(18, 22, 32, 0.88);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2rem 1.75rem;
      box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.03);
      text-align: center;
    }
    .error-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 25%;
      right: 25%;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--accent), transparent);
    }
    .icon-wrapper {
      margin: 0 auto 1.5rem;
      width: 76px;
      height: 76px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      position: relative;
      box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.4);
    }
    .icon-wrapper svg {
      width: 38px;
      height: 38px;
    }
    .error-code-big {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 3.5rem;
      font-weight: 700;
      line-height: 1;
      letter-spacing: -0.04em;
      margin-bottom: 0.5rem;
      background: linear-gradient(180deg, #ffffff 40%, var(--fg-muted) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .error-title {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--fg);
      margin-bottom: 0.6rem;
      letter-spacing: -0.02em;
    }
    .error-desc {
      font-size: 0.875rem;
      line-height: 1.55;
      color: var(--fg-muted);
      margin-bottom: 1.75rem;
    }
    .error-detail-box {
      margin-bottom: 1.5rem;
      padding: 0.65rem 0.85rem;
      border-radius: 10px;
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid rgba(255, 255, 255, 0.06);
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 0.75rem;
      color: var(--fg-muted);
      word-break: break-word;
      text-align: left;
    }
    .action-group {
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
    }
    .btn-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      height: 44px;
      padding: 0 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-radius: 12px;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.15s ease;
      border: none;
    }
    .btn-action:active {
      transform: scale(0.98);
    }
    .btn-primary {
      background: var(--primary);
      color: #ffffff;
      box-shadow: 0 4px 14px var(--primary-glow);
    }
    .btn-primary:hover {
      background: var(--primary-hover);
    }
    .btn-secondary {
      background: rgba(255, 255, 255, 0.04);
      color: var(--fg);
      border: 1px solid var(--border);
    }
    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.14);
    }
    .card-footer {
      margin-top: 1.5rem;
      padding-top: 1.25rem;
      border-top: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.75rem;
      color: var(--fg-subtle);
    }
    .card-footer a {
      color: var(--fg-muted);
      text-decoration: none;
      transition: color 0.15s;
    }
    .card-footer a:hover {
      color: var(--fg);
    }
    @media (min-width: 480px) {
      .action-group-row {
        flex-direction: row;
      }
      .action-group-row .btn-action {
        flex: 1;
      }
    }
  </style>
  @yield('styles')
</head>
<body>
  <div class="grid-pattern"></div>
  <div class="ambient-glow"></div>

  <div class="error-card">
    <div class="icon-wrapper">
      @hasSection('icon')
        @yield('icon')
      @else
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
      @endif
    </div>

    <div class="error-code-big">@yield('code', '404')</div>
    <h1 class="error-title">@yield('heading', $__env->yieldContent('title', 'Ошибка'))</h1>
    
    <p class="error-desc">
      @yield('message', 'Произошла непредвиденная ошибка при обработке вашего запроса.')
    </p>

    @if(config('app.debug') && !empty($exception) && $exception->getMessage())
      <div class="error-detail-box">
        <span style="opacity: 0.6; display: block; margin-bottom: 2px;">Технические подробности:</span>
        {{ $exception->getMessage() }}
      </div>
    @endif

    @hasSection('actions')
      @yield('actions')
    @else
      <div class="action-group action-group-row">
        <button type="button" class="btn-action btn-primary" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '/'; }">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
          </svg>
          Вернуться назад
        </button>
        <a href="/" class="btn-action btn-secondary">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>
          На главную
        </a>
      </div>
    @endif

    <div class="card-footer">
      <span>Enclave CRM System</span>
      <a href="https://t.me/{{ config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot') }}" target="_blank" rel="noopener">
        Поддержка в Telegram →
      </a>
    </div>
  </div>

  <script>
    try {
      if (window.Telegram?.WebApp) {
        window.Telegram.WebApp.ready();
        window.Telegram.WebApp.expand?.();
        if (window.Telegram.WebApp.BackButton) {
          window.Telegram.WebApp.BackButton.show();
          window.Telegram.WebApp.BackButton.onClick(function () {
            if (window.history.length > 1) {
              window.history.back();
            } else {
              window.location.href = '/';
            }
          });
        }
      }
    } catch (_) {}
  </script>
</body>
</html>
