@extends('errors.layout')

@section('title', '419 — Срок действия сессии истек')
@section('code', '419')
@section('badge', '419 • Page Expired')
@section('heading', 'Сессия устарела')

@section('styles')
<style>
  :root {
    --error-accent: #f59e0b;
    --error-glow: rgba(245, 158, 11, 0.22);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
</svg>
@endsection

@section('message')
Время ожидания страницы или токена защиты истекло из-за длительного отсутствия активности. Просто обновите страницу, чтобы продолжить.
@endsection

@section('actions')
<div class="action-group action-group-row">
  <button type="button" class="btn-action btn-primary" onclick="window.location.reload();">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
    </svg>
    Обновить страницу
  </button>
  <a href="/" class="btn-action btn-secondary">
    На главную
  </a>
</div>
@endsection
