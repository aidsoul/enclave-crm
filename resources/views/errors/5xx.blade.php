@extends('errors.layout')

@section('title', 'Сбой сервера')
@section('code', $exception?->getStatusCode() ?? '5xx')
@section('badge', ($exception?->getStatusCode() ?? '5xx') . ' • Server Error')
@section('heading', 'Неполадки на сервере')

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

@section('message', 'При обработке запроса на сервере произошла непредвиденная ошибка. Мы уже работаем над её устранением.')
