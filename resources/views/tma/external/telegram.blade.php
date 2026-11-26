<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>Открытие Telegram</title>
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
      background-color: rgba(42, 171, 238, 0.15);
      color: #2AABEE;
    }
    h2 {
      margin: 0 0 8px;
      font-size: 18px;
      font-weight: 600;
    }
    .user-info {
      font-size: 14px;
      color: #a1a1aa;
      margin: 0 0 20px;
    }
    .btn {
      display: block;
      width: 100%;
      padding: 12px 0;
      background-color: #2AABEE;
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
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.63 3.73-.53.37-1.02.55-1.45.54-.48-.01-1.4-.27-2.09-.49-.84-.27-1.51-.42-1.45-.88.03-.24.38-.49 1.04-.75 4.07-1.77 6.78-2.94 8.14-3.51 3.86-1.61 4.67-1.89 5.2-1.9.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.19-.04.28z"/>
      </svg>
    </div>
    <h2>Открытие Telegram</h2>
    <div class="user-info">{{ $username ? '@' . $username : 'ID: ' . $id }}</div>
    <a href="{{ $targetUrl }}" class="btn">Открыть Telegram</a>
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
