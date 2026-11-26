<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>Enclave CRM — Авторизация на ПК</title>
  @vite(['resources/css/app.css'])
</head>
<body class="bg-bg text-fg font-body min-h-screen flex items-center justify-center p-4">
  <div class="bg-surface rounded-2xl p-8 max-w-sm w-full text-center shadow-xl">
    <div class="mx-auto w-14 h-14 rounded-full bg-primary/20 text-primary flex items-center justify-center mb-5 animate-pulse">
      <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
    </div>
    <h1 class="font-display text-lg font-semibold text-fg mb-1">Успешный вход</h1>
    <p class="text-fg-muted text-sm mb-6">Добро пожаловать, <span class="text-fg font-medium">{{ $user->system_name }}</span>! Перенаправляем в CRM...</p>
    <div class="w-8 h-8 mx-auto rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
  </div>

  <script>
    try {
      localStorage.setItem('tma_token', @json($sessionToken));
      localStorage.setItem('tma_desktop_mode', '1');
    } catch (e) {
      console.warn('LocalStorage error:', e);
    }
    setTimeout(function() {
      window.location.replace(@json($redirectPath));
    }, 400);
  </script>
</body>
</html>
