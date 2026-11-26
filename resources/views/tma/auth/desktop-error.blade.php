<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>Enclave CRM — Ошибка входа</title>
  @vite(['resources/css/app.css'])
</head>
<body class="bg-bg text-fg font-body min-h-screen flex items-center justify-center p-4">
  <div class="bg-surface rounded-2xl p-8 max-w-sm w-full text-center shadow-xl">
    <div class="mx-auto w-14 h-14 rounded-full bg-error-bg text-error flex items-center justify-center mb-5">
      <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
      </svg>
    </div>
    <h1 class="font-display text-lg font-semibold text-fg mb-2">Не удалось войти</h1>
    <p class="text-fg-muted text-sm leading-relaxed mb-6">{{ $message ?? 'Ссылка для входа недействительна.' }}</p>
    <a href="https://t.me/{{ config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot') }}" 
       class="inline-flex items-center justify-center w-full px-4 py-2.5 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-hover active:scale-95 transition-all">
      Открыть бота в Telegram
    </a>
  </div>
</body>
</html>
