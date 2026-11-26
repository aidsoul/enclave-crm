@extends('errors.layout')

@section('title', 'Ошибка клиента')
@section('code', $exception?->getStatusCode() ?? '4xx')
@section('badge', ($exception?->getStatusCode() ?? '4xx') . ' • Client Error')
@section('heading', 'Ошибка в запросе')

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
  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
</svg>
@endsection

@section('message', 'При обработке запроса возникла клиентская ошибка. Пожалуйста, проверьте введённые данные или вернитесь на главную страницу.')
