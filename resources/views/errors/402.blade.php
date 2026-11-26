@extends('errors.layout')

@section('title', '402 — Требуется оплата')
@section('code', '402')
@section('badge', '402 • Payment Required')
@section('heading', 'Требуется подписка')

@section('styles')
<style>
  :root {
    --error-accent: #10b981;
    --error-glow: rgba(16, 185, 129, 0.22);
  }
</style>
@endsection

@section('icon')
<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15A2.25 2.25 0 002.25 6.75v10.5A2.25 2.25 0 004.5 19.5z" />
</svg>
@endsection

@section('message', 'Для использования этого функционала требуется активный тарифный план или продление подписки. Свяжитесь с администратором системы.')
