<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet"/>
  <title>Доступ ограничен</title>
  <style>
    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background: #0f1115; color: #e5e7eb; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
    .card { background: #16181d; border: 1px solid #252932; border-radius: 16px; padding: 32px 24px; max-width: 360px; width: 100%; text-align: center; }
    .title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
    .subtitle { font-size: 14px; color: #9aa3af; line-height: 1.5; }
  </style>
</head>
<body>
  <div class="card">
    <div class="title">Доступ ограничен</div>
    <p class="subtitle">{{ $message ?? 'Доступ разрешён только через Telegram Mini App. Откройте бота в Telegram для продолжения.' }}</p>
    <a id="homeBtn" href="/" style="display: inline-block; margin-top: 16px; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px;">На главную</a>
  </div>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script>
    (function() {
      try {
        const token = localStorage.getItem('tma_token');
        const hasTg = Boolean(window.Telegram?.WebApp?.initData);
        const homeBtn = document.getElementById('homeBtn');
        if (token) {
          if (homeBtn) homeBtn.href = '/tma?token=' + encodeURIComponent(token);
          window.location.replace('/tma?token=' + encodeURIComponent(token));
        } else if (hasTg) {
          window.location.replace('/');
        }
      } catch (_) {}
    })();
  </script>
</body>
</html>
