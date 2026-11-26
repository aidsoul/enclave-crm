<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>Написать письмо</title>
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
      background-color: rgba(59, 130, 246, 0.15);
      color: #3b82f6;
    }
    h2 {
      margin: 0 0 8px;
      font-size: 18px;
      font-weight: 600;
    }
    .email {
      font-size: 14px;
      color: #a1a1aa;
      margin: 0 0 20px;
      word-break: break-all;
    }
    .btn {
      display: block;
      width: 100%;
      padding: 12px 0;
      background-color: #3b82f6;
      color: #ffffff;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      border-radius: 10px;
      box-sizing: border-box;
      transition: opacity 0.2s;
      margin-bottom: 10px;
    }
    .btn-secondary {
      background-color: #27272a;
      color: #e4e4e7;
    }
    .btn:active {
      opacity: 0.8;
    }
    .subtext {
      font-size: 12px;
      color: #71717a;
      margin-top: 14px;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
      </svg>
    </div>
    <h2>Написать письмо</h2>
    <div class="email">{{ $email }}</div>
    <a href="{{ $mailtoUrl }}" class="btn">Почтовый клиент</a>
    <a href="{{ $gmailUrl }}" target="_blank" class="btn btn-secondary">Открыть в Gmail</a>
    <div class="subtext">Переход выполняется автоматически...</div>
  </div>
  <script>
    window.location.href = @json($mailtoUrl);
    setTimeout(function() {
      try { window.close(); } catch (e) {}
    }, 3000);
  </script>
</body>
</html>
