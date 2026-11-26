@extends('errors.layout')

@section('title', '500 — Ошибка сервера')
@section('code', '500')
@section('badge', '500 • Server Error')
@section('heading', 'Внутренняя ошибка сервера')

@section('styles')
<style>
  :root {
    --error-accent: #ef4444;
    --error-glow: rgba(239, 68, 68, 0.25);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
</svg>
@endsection

@section('message', 'На сервере произошла непредвиденная ошибка. Мы уже получили уведомление об инциденте и занимаемся его устранением.')

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
