@extends('errors.layout')

@section('title', '503 — Технические работы')
@section('code', '503')
@section('badge', '503 • Service Unavailable')
@section('heading', 'Технические работы')

@section('styles')
<style>
  :root {
    --error-accent: #eab308;
    --error-glow: rgba(234, 179, 8, 0.22);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.07a4.5 4.5 0 004.486-6.32l-3.27 3.287a1.5 1.5 0 01-2.122-2.121l3.27-3.27a4.5 4.5 0 00-6.32 4.485c.118.58.094 1.193-.07 1.743" />
</svg>
@endsection

@section('message', 'Система временно находится на плановом обслуживании или обновлении. Мы восстановим доступ в самое ближайшее время.')

@section('actions')
<div class="action-group action-group-row">
  <button type="button" class="btn-action btn-primary" onclick="window.location.reload();">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
    </svg>
    Проверить статус
  </button>
  <a href="https://t.me/{{ config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot') }}" class="btn-action btn-secondary" target="_blank" rel="noopener">
    Бот в Telegram
  </a>
</div>
@endsection
