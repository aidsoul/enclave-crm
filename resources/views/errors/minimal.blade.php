@extends('errors.layout')

@section('title')
  @yield('code', '404') — @yield('message', 'Ошибка')
@endsection

@section('code')
  @yield('code', '404')
@endsection

@section('heading')
  @yield('message', 'Произошла ошибка')
@endsection

@section('message')
  @yield('message', 'Сервер не смог выполнить ваш запрос.')
@endsection
