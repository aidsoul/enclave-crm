<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>Открытие Viber</title>
  <style>
    body {
      background-color: #09090b;
      color: #fafafa;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      box-sizing: border-box;
      text-align: center;
    }
    .card {
      background-color: #18181b;
      border: 1px solid #27272a;
      border-radius: 16px;
      padding: 28px 20px;
      max-width: 360px;
      width: 100%;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .icon {
      width: 52px;
      height: 52px;
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 14px;
      background-color: rgba(115, 96, 242, 0.15);
      color: #7360f2;
    }
    h2 {
      margin: 0 0 8px;
      font-size: 18px;
      font-weight: 600;
    }
    .phone {
      font-size: 14px;
      color: #a1a1aa;
      margin: 0 0 20px;
    }
    .btn {
      display: block;
      width: 100%;
      padding: 12px 0;
      background-color: #7360f2;
      color: #ffffff;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      border-radius: 10px;
      box-sizing: border-box;
      transition: opacity 0.2s;
    }
    .btn:active {
      opacity: 0.8;
    }
    .subtext {
      font-size: 12px;
      color: #71717a;
      margin-top: 16px;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
        <path d="M19.74 3.76A10.88 10.88 0 0 0 12.08 1.5C5.81 1.5 1.5 5.74 1.5 11.91c0 2.92 1.09 5.58 3.12 7.55l-.46 3.04 3.2-.84c1.4.38 2.87.58 4.38.58 6.46 0 10.76-4.34 10.76-10.33a10.6 10.6 0 0 0-2.76-8.15zm-2.07 11.45c-.32.88-1.55 1.63-2.38 1.67-.62.03-1.42-.16-3.83-1.15-2.92-1.2-4.83-4.14-4.97-4.34-.15-.2-1.18-1.57-1.18-2.99 0-1.42.74-2.12 1-2.41.27-.29.58-.36.78-.36.2 0 .39 0 .56.01.18.01.42-.07.65.49.24.58.81 1.99.88 2.14.07.15.12.33.02.53-.1.2-.15.32-.3.5-.15.17-.31.39-.45.52-.15.15-.3.31-.13.61.17.29.77 1.27 1.66 2.06 1.14 1.02 2.1 1.33 2.4 1.48.29.15.46.12.63-.07.17-.2.73-.85.93-1.15.2-.29.39-.24.66-.15.27.1 1.7.8 1.99.95.29.15.49.22.56.34.07.15.07.85-.25 1.73z"/>
      </svg>
    </div>
    <h2>Открытие Viber</h2>
    <div class="phone">{{ $phone }}</div>
    <a href="{{ $targetUrl }}" class="btn">Открыть Viber</a>
    <div class="subtext">Переход выполняется автоматически...</div>
  </div>
  <script>
    window.location.href = @json($targetUrl);
    setTimeout(function() {
      try { window.close(); } catch (e) {}
    }, 3000);
  </script>
</body>
</html>
