@extends('errors.layout')

@section('title', '300 — Множественный выбор')
@section('code', '300')
@section('badge', '300 • Multiple Choices')
@section('heading', 'Доступно несколько вариантов')

@section('styles')
<style>
  :root {
    --error-accent: #6366f1;
    --error-glow: rgba(99, 102, 241, 0.22);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
</svg>
@endsection

@section('message')
Запрошенный ресурс имеет несколько возможных путей или форматов. Выберите нужный раздел ниже либо вернитесь на предыдущую страницу.
@endsection

@section('actions')
<div class="action-group">
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.25rem;">
    <a href="/tma/orders" class="btn-action btn-secondary" style="height: 38px; font-size: 0.8rem;">
      📦 Заказы
    </a>
    <a href="/tma/customers" class="btn-action btn-secondary" style="height: 38px; font-size: 0.8rem;">
      👥 Клиенты
    </a>
  </div>
  <div class="action-group-row" style="display: flex; gap: 0.65rem;">
    <button type="button" class="btn-action btn-primary" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '/'; }">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Назад
    </button>
    <a href="/" class="btn-action btn-secondary">
      На главную
    </a>
  </div>
</div>
@endsection
