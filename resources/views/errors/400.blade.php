@extends('errors.layout')

@section('title', '400 — Некорректный запрос')
@section('code', '400')
@section('badge', '400 • Bad Request')
@section('heading', 'Некорректный запрос')

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
  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
</svg>
@endsection

@section('message', 'Сервер не смог распознать запрос из-за некорректного синтаксиса или недопустимых параметров. Пожалуйста, проверьте введённые данные.')
