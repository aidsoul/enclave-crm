@extends('errors.layout')

@section('title', '403 — Доступ ограничен')
@section('code', '403')
@section('badge', '403 • Forbidden')
@section('heading', 'Доступ ограничен')

@section('styles')
<style>
  :root {
    --error-accent: #f43f5e;
    --error-glow: rgba(244, 63, 94, 0.22);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
</svg>
@endsection

@section('message', 'У вашей учетной записи недостаточно полномочий для просмотра этой страницы или выполнения операции. Обратитесь к владельцу CRM.')
