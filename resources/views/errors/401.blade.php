@extends('errors.layout')

@section('title', '401 — Требуется авторизация')
@section('code', '401')
@section('badge', '401 • Unauthorized')
@section('heading', 'Требуется авторизация')

@section('styles')
<style>
  :root {
    --error-accent: #38bdf8;
    --error-glow: rgba(56, 189, 248, 0.22);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
</svg>
@endsection

@section('message', 'Для доступа к этому разделу необходимо войти в систему. Пожалуйста, запустите мини-приложение через официального Telegram-бота.')

@section('actions')
<div class="action-group">
  <a href="https://t.me/{{ config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot') }}" class="btn-action btn-primary" target="_blank" rel="noopener">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.52 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .37z"/>
    </svg>
    Запустить в Telegram
  </a>
  <button type="button" class="btn-action btn-secondary" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '/'; }">
    Вернуться назад
  </button>
</div>
@endsection
