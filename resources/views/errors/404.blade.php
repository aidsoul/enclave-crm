@extends('errors.layout')

@section('title', '404 — Страница не найдена')
@section('code', '404')
@section('badge', '404 • Not Found')
@section('heading', 'Страница не найдена')

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
  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
</svg>
@endsection

@section('message', 'Запрошенная страница не существует, была удалена или перемещена по другому адресу. Проверьте правильность URL или вернитесь в систему.')

@section('actions')
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
@endsection
