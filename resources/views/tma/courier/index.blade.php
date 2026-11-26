@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-4">
  <div>
    <p class="text-fg-subtle text-xs">Курьер</p>
    <h1 class="font-display font-bold text-xl">{{ $user?->system_name ?? 'Курьер' }}</h1>
  </div>
  <div class="w-10 h-10 rounded-full bg-surface flex items-center justify-center">
    <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
    </svg>
  </div>
</div>

<div class="bg-surface rounded-xl p-3.5 mb-4">
  <div class="flex items-center justify-between mb-2">
    <div class="flex items-center gap-1.5">
      <span class="w-2 h-2 rounded-full bg-success"></span>
      <h2 class="text-xs font-semibold text-fg">Смена за сегодня</h2>
    </div>
    <span class="text-[11px] text-fg-subtle" x-text="todayDateFormatted"></span>
  </div>
  <div class="grid grid-cols-3 gap-2 text-center">
    <div class="bg-bg rounded-lg p-2 ">
      <p class="text-[10px] text-fg-subtle mb-0.5">Доставок</p>
      <p class="font-display font-bold text-sm text-fg" x-text="shiftSummary.completed_deliveries || 0"></p>
    </div>
    <div class="bg-bg rounded-lg p-2 ">
      <p class="text-[10px] text-fg-subtle mb-0.5">Собрано</p>
      <p class="font-display font-bold text-sm text-success" x-text="formatPrice(shiftSummary.total_collected || 0)"></p>
    </div>
    <div class="bg-bg rounded-lg p-2 ">
      <p class="text-[10px] text-fg-subtle mb-0.5">Заработано</p>
      <p class="font-display font-bold text-sm text-warning" x-text="formatPrice(shiftSummary.payout_amount || 0)"></p>
    </div>
  </div>
  <template x-if="(shiftSummary.cash_amount > 0 || shiftSummary.terminal_amount > 0)">
    <div class="flex items-center justify-between mt-2 pt-2 border-t border-border/40 text-[10px] text-fg-subtle">
      <span>Наличными: <strong class="text-fg font-medium" x-text="formatPrice(shiftSummary.cash_amount || 0)"></strong></span>
      <span>Терминалом: <strong class="text-fg font-medium" x-text="formatPrice(shiftSummary.terminal_amount || 0)"></strong></span>
    </div>
  </template>
</div>

{{-- Баннер активной передачи при форс-мажоре --}}
<template x-if="activeHandover">
  <div class="bg-warning/15 border border-warning/30 rounded-xl p-3.5 mb-4 text-xs text-warning">
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-warning animate-ping"></span>
        <span class="font-bold text-fg">Передача активна: <span class="font-mono text-warning" x-text="activeHandover.transfer_code"></span></span>
      </div>
      <span class="text-[10px] text-fg-subtle" x-text="'Заказов: ' + (activeHandover.orders_count || activeHandover.orders?.length || 0)"></span>
    </div>
    <div class="flex items-center justify-between text-[11px] text-fg-subtle mb-3">
      <span>Касса: <strong class="text-fg" x-text="activeHandover.transfer_cash ? formatPrice(activeHandover.declared_cash_amount) : 'Без кассы'"></strong></span>
      <span>Срок: <strong class="text-fg" x-text="formatDate(activeHandover.expires_at)"></strong></span>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" @click="showHandoverQrModal = true" class="flex-1 py-1.5 px-3 bg-warning text-[oklch(20% 0.05 60)] font-semibold rounded-lg text-xs flex items-center justify-center gap-1 active:scale-95 transition-transform">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/></svg>
        <span>Показать код</span>
      </button>
      <button type="button" @click="cancelActiveHandover()" class="py-1.5 px-3 bg-surface2 text-fg-muted hover:text-fg rounded-lg text-xs font-medium active:scale-95 transition-transform">
        Отменить
      </button>
    </div>
  </div>
</template>

{{-- Кнопки оперативной передачи смены --}}
<div class="grid grid-cols-2 gap-2 mb-4">
  <button type="button"
          @click="openHandoverModal()"
          class="py-2.5 px-3 bg-error/10 hover:bg-error/15 border border-error/25 text-error rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-all">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
    </svg>
    <span>SOS / Передать</span>
  </button>

  <button type="button"
          @click="openScanHandoverModal()"
          class="py-2.5 px-3 bg-surface border border-border text-fg rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 hover:border-primary/50 active:scale-95 transition-all">
    <svg class="w-4 h-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
    </svg>
    <span>Принять смену</span>
  </button>
</div>

{{-- Оффлайн-статус и очередь синхронизации --}}
<template x-if="!isOnline || isOfflineMode">
  <div class="bg-amber-500/15 border border-amber-500/30 rounded-xl p-3 mb-3 text-xs text-amber-300 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.5 10.5a6 6 0 0 1 7.07 7.07M8.53 8.53a10.5 10.5 0 0 1 12.94 0M5.07 5.07a15 15 0 0 1 18.86 0M1.4 1.4a19.5 19.5 0 0 1 21.2 0"/>
      </svg>
      <span>Оффлайн-режим: данные из кэша</span>
    </div>
    <span class="text-[10px] text-amber-400/80 font-medium shrink-0">Без связи</span>
  </div>
</template>

<template x-if="pendingSyncCount > 0">
  <div class="bg-primary/10 border border-primary/25 rounded-xl p-3 mb-3 text-xs text-fg flex items-center justify-between">
    <div class="flex items-center gap-2">
      <span class="relative flex h-2 w-2 shrink-0">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
      </span>
      <span>В очереди на отправку: <strong class="text-primary font-semibold" x-text="pendingSyncCount"></strong></span>
    </div>
    <button type="button"
            @click="manualSync()"
            :disabled="isSyncing || !isOnline"
            class="px-2.5 py-1 bg-primary text-primary-fg rounded-lg text-[11px] font-semibold disabled:opacity-50 flex items-center gap-1 active:scale-95 transition-transform shrink-0">
      <svg x-show="isSyncing" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
      <span x-text="isSyncing ? 'Отправка...' : 'Синхронизировать'"></span>
    </button>
  </div>
</template>

<div class="flex items-center justify-between mb-2.5 px-0.5">
  <div class="flex items-center gap-1.5">
    <h2 class="text-xs font-semibold text-fg">Маршрутный лист</h2>
    <template x-if="displayOrders.length > 0">
      <span class="text-[10px] font-medium px-1.5 py-0.5 bg-surface text-fg-subtle rounded-full border border-border/40" x-text="displayOrders.length + ' ' + pluralize(displayOrders.length, 'точка', 'точки', 'точек')"></span>
    </template>
  </div>
  <template x-if="displayOrders.length > 1">
    <button type="button"
            @click="toggleRouteSort()"
            class="inline-flex items-center gap-1 text-[11px] text-fg-muted hover:text-fg bg-surface px-2.5 py-1 rounded-lg border border-border/50 active:scale-95 transition-all">
      <svg class="w-3.5 h-3.5" :class="sortBySlot ? 'text-primary' : 'text-fg-subtle'" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
      </svg>
      <span x-text="sortBySlot ? 'По окнам доставки' : 'Без сортировки'"></span>
    </button>
  </template>
</div>

<div id="orders-list" class="space-y-3 mb-4">
  <template x-if="ordersLoading">
    <div class="py-12 flex flex-col items-center justify-center gap-3">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-xs text-fg-muted">Загрузка...</p>
    </div>
  </template>
  <template x-if="!ordersLoading && displayOrders.length === 0">
    <div class="bg-surface rounded-xl p-8 text-center">
      <svg class="w-12 h-12 mx-auto text-fg-subtle mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
      </svg>
      <p class="text-fg-muted text-sm">Нет активных заказов</p>
      <p class="text-fg-subtle text-xs mt-1">Ожидание назначения...</p>
    </div>
  </template>
  <template x-for="(order, index) in displayOrders" :key="order.id">
    <div :id="'order-card-' + order.id" class="bg-surface rounded-xl p-4 scroll-mt-4">
      <a :href="'/tma/orders/' + order.id" class="block">
        <div class="flex items-center justify-between mb-1">
          <div class="flex items-center gap-1.5 flex-wrap">
            <template x-if="sortBySlot">
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px] font-bold shrink-0"
                    :title="'Точка #' + (index + 1)"
                    x-text="index + 1"></span>
            </template>
            <span class="text-sm font-medium" x-text="'#' + order.id"></span>
            <button type="button"
                    @click.stop.prevent="window.location.href = '/tma/orders/' + order.id + '/chat'"
                    class="relative inline-flex items-center gap-1 text-[11px] font-medium text-primary bg-primary/10 px-2 py-0.5 rounded hover:bg-primary/20 transition-colors"
                    title="Чат по заказу">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.755-.783 7.828 7.828 0 0 0 .753-2.008C3.896 16.592 3 14.417 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
              </svg>
              <span>Чат</span>
            </button>
          </div>
          <div class="flex items-center gap-1.5">
            <template x-if="order.is_offline_pending">
              <span class="inline-flex items-center gap-1 text-[10px] font-medium text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded border border-amber-400/20" title="Изменения сохранены локально">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Оффлайн</span>
              </span>
            </template>
            <template x-if="hasPhotoProof(order)">
              <button type="button" @click.stop.prevent="openProofPhotoModal(getProofPhotoUrl(order))" class="inline-flex items-center gap-1 text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded hover:bg-success/20 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
                Фото
              </button>
            </template>
            <span class="text-xs font-medium" :class="getStatusClass(order.status)" x-text="getStatusLabel(order.status)"></span>
          </div>
        </div>
        <p class="text-xs text-fg-muted mb-1">Адрес доставки</p>
        <p class="text-sm text-fg font-medium leading-snug mb-2" x-text="getOrderAddress(order) || 'Адрес не указан'"></p>
        <template x-if="getDeliverySchedule(order)">
          <div x-data="{ get urgency() { return getCourierSlotUrgency(order); } }" class="flex items-center justify-between gap-2 mb-2 flex-wrap">
            <div class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-md font-medium"
                 :class="urgency.bgClass">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
              <span x-text="'Время: ' + getDeliverySchedule(order)"></span>
            </div>
            <template x-if="urgency.badge">
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                    :class="urgency.badgeClass"
                    x-text="urgency.badge"></span>
            </template>
          </div>
        </template>
        <template x-if="getOrderAddress(order)">
          <div class="flex items-center gap-2 mb-3 pt-0.5">
            <span class="text-xs text-fg-subtle shrink-0">Карты:</span>
            <div class="flex items-center gap-1.5 flex-wrap">
              <button type="button"
                      @click.stop.prevent="openNavigator('yandex', getOrderAddress(order))"
                      class="px-2.5 py-1 rounded-md bg-surface2 text-xs font-medium text-fg hover:border-primary active:scale-95 transition-all">
                Яндекс
              </button>
              <button type="button"
                      @click.stop.prevent="openNavigator('2gis', getOrderAddress(order))"
                      class="px-2.5 py-1 rounded-md bg-surface2 text-xs font-medium text-fg hover:border-primary active:scale-95 transition-all">
                2GIS
              </button>
              <button type="button"
                      @click.stop.prevent="openNavigator('google', getOrderAddress(order))"
                      class="px-2.5 py-1 rounded-md bg-surface2 text-xs font-medium text-fg hover:border-primary active:scale-95 transition-all">
                Google
              </button>
            </div>
          </div>
        </template>
        <template x-if="order.items && order.items.length">
          <div class="mb-3">
            <p class="text-xs text-fg-muted mb-1.5">Состав заказа</p>
            <ul class="space-y-1.5">
              <template x-for="(item, index) in order.items" :key="index">
                @include('tma.partials.order-item', ['showPrice' => false, 'showSku' => false, 'showId' => false, 'showPurchasePrice' => false, 'imageSize' => 'sm', 'interactive' => false])
              </template>
            </ul>
          </div>
        </template>
        <div class="flex items-center justify-between pt-3 border-t border-border">
          <span class="text-xs text-fg-muted">К получению</span>
          <span class="font-display font-bold text-success" x-text="formatPrice(order.total_price)"></span>
        </div>
      </a>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <button x-show="order && order.status === 'assigned'" type="button" @click="openAtWarehouseModal(order.id)" class="btn w-full bg-primary text-primary-fg font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
          </svg>
          Забрал / В пути
        </button>
        <button x-show="order && order.status === 'at_warehouse'" type="button" @click="openCompleteModal(order.id)" class="btn w-full bg-success text-[oklch(15% 0.01 158)] font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          Доставлен
        </button>
      </div>
    </div>
  </template>
</div>

  @include('tma.partials.nav')

  @component('tma.partials.modal', ['show' => 'showCompleteModal'])
    <h3 class="font-display font-bold text-lg mb-2">Подтвердить доставку</h3>
    <p class="text-fg-muted text-sm mb-3">Вы уверены, что заказ доставлен?</p>

    <div class="mb-4 text-left" x-data="{ currentOrder: getPendingOrder() }" x-effect="currentOrder = getPendingOrder()">
      <template x-if="requiresSignature(currentOrder)">
        <div class="mb-3 p-2.5 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-start gap-2">
          <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
          </svg>
          <div class="text-xs">
            <div class="font-semibold text-amber-500">Ценное отправление</div>
            <div class="text-fg-subtle text-[11px]">Для завершения заказа требуется подпись получателя</div>
          </div>
        </div>
      </template>

      <div class="mb-3 p-3 bg-surface2/60 rounded-xl">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-fg flex items-center gap-1.5">
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            Подпись клиента
          </span>
          <template x-if="hasSignature(currentOrder)">
            <span class="text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded">Подписано</span>
          </template>
          <template x-if="!hasSignature(currentOrder) && requiresSignature(currentOrder)">
            <span class="text-[11px] font-semibold text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded">Обязательно</span>
          </template>
        </div>

        <template x-if="hasSignature(currentOrder)">
          <div class="flex items-center justify-between gap-3 p-2 bg-surface rounded-lg border border-border">
            <div class="flex items-center gap-2.5 min-w-0 cursor-pointer" @click="openProofPhotoModal(getSignatureUrl(currentOrder))">
              <div class="w-14 h-9 bg-white rounded border border-border flex items-center justify-center p-0.5 shrink-0 overflow-hidden">
                <img :src="getSignatureUrl(currentOrder)" alt="Подпись" class="max-w-full max-h-full object-contain">
              </div>
              <div class="min-w-0 text-left">
                <div class="text-xs font-medium text-fg truncate">Подпись зафиксирована</div>
                <div class="text-[10px] text-fg-subtle truncate" x-show="getSignatureAt(currentOrder)" x-text="formatDate(getSignatureAt(currentOrder))"></div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <button type="button" @click="openSignaturePad(currentOrder?.id)" class="px-2 py-1 bg-surface2 hover:bg-surface text-fg rounded text-[11px] font-medium border border-border">
                Переписать
              </button>
            </div>
          </div>
        </template>

        <template x-if="!hasSignature(currentOrder)">
          <button type="button"
                  @click="openSignaturePad(currentOrder?.id)"
                  class="w-full py-2 px-2.5 bg-surface hover:border-primary/50 text-fg rounded-lg font-medium text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all">
            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            <span>Распишитесь на экране</span>
          </button>
        </template>
      </div>

      <div class="p-3 bg-surface2/60 rounded-xl">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-fg flex items-center gap-1.5">
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
            Фотофиксация вручения
          </span>
          <template x-if="hasPhotoProof(currentOrder)">
            <span class="text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded">Готово</span>
          </template>
        </div>

        <template x-if="hasPhotoProof(currentOrder)">
          <div class="space-y-2">
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
              <template x-for="(url, idx) in getPhotoProofUrls(currentOrder)" :key="url">
                <div class="relative w-14 h-14 rounded-lg overflow-hidden shrink-0 group">
                  <img :src="url" alt="Фото" class="w-full h-full object-cover cursor-pointer" @click="openProofPhotoModal(url)">
                  <button type="button"
                          @click.stop="promptDeleteProof(url, currentOrder.id)"
                          class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-error"
                          title="Удалить">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </template>
            </div>
            <div class="flex items-center justify-between text-xs text-fg-subtle">
              <span x-text="'Прикреплено: ' + getPhotoProofUrls(currentOrder).length + ' из 3'"></span>
              <template x-if="getPhotoProofUrls(currentOrder).length < 3">
                <div class="flex items-center gap-2">
                  <button type="button" @click="openCamera(currentOrder?.id)" class="text-primary hover:underline font-medium text-xs">Камера</button>
                  <span>•</span>
                  <label class="text-primary hover:underline font-medium text-xs cursor-pointer">
                    Галерея
                    <input type="file" multiple accept="image/*" class="hidden" :disabled="proofUploading" @change="uploadDeliveryProof($event)">
                  </label>
                </div>
              </template>
            </div>
          </div>
        </template>

        <template x-if="!hasPhotoProof(currentOrder)">
          <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
              <button type="button"
                      @click="openCamera(currentOrder?.id)"
                      class="w-full py-2 px-2.5 bg-primary text-primary-fg rounded-lg font-semibold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
                <span>Камера</span>
              </button>

              <label class="w-full py-2 px-2.5 bg-surface text-fg rounded-lg font-medium text-xs flex items-center justify-center gap-1.5 cursor-pointer hover:border-primary/50 active:scale-95 transition-all">
                <input type="file" multiple accept="image/*" class="hidden" :disabled="proofUploading" @change="uploadDeliveryProof($event)">
                <svg class="w-3.5 h-3.5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <span>Из галереи</span>
              </label>
            </div>
          </div>
        </template>
      </div>
    </div>

    @component('tma.partials.modal-actions')
      <button @click="showCompleteModal = false; pendingCompleteOrderId = null" :disabled="completingOrder" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium disabled:opacity-50">Нет</button>
      <button @click="confirmCompleteOrder()" :disabled="completingOrder" class="flex-1 px-4 py-2.5 bg-success text-white rounded-lg text-sm font-medium disabled:opacity-50" x-text="completingOrder ? 'Завершение...' : 'Да'"></button>
    @endcomponent
  @endcomponent

  @component('tma.partials.modal', ['show' => 'showCameraModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-3'])
    <div class="flex items-center justify-between pb-2 mb-2 border-b border-border">
      <div class="flex items-center gap-2">
        <div class="w-2.5 h-2.5 rounded-full bg-error animate-pulse" x-show="_cameraStream && !capturedBlob"></div>
        <h3 class="font-display font-semibold text-sm text-fg">Камера</h3>
      </div>
      <div class="flex items-center gap-1.5">
        <button type="button"
                x-show="_cameraStream && !capturedBlob"
                @click="switchCamera()"
                class="w-8 h-8 rounded-full bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg active:scale-95 transition-transform"
                title="Переключить камеру">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
        </button>
        <button type="button" @click="closeCameraModal()" class="w-8 h-8 rounded-full bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg active:scale-95 transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="relative rounded-xl overflow-hidden bg-black aspect-[3/4] flex items-center justify-center">
      <video x-ref="courierCameraVideo" playsinline autoplay muted class="w-full h-full object-cover" x-show="!capturedPreviewUrl && !cameraError"></video>

      <template x-if="capturedPreviewUrl">
        <img :src="capturedPreviewUrl" alt="Снимок" class="w-full h-full object-cover">
      </template>

      <div x-show="cameraLoading" class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center text-white gap-2">
        <svg class="w-6 h-6 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        <span class="text-xs">Запуск камеры...</span>
      </div>

      <div x-show="cameraError" class="p-6 text-center text-white">
        <svg class="w-10 h-10 mx-auto text-error mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <p class="text-xs text-white/80 mb-3" x-text="cameraError"></p>
        <label class="btn inline-flex items-center gap-1.5 px-3 py-2 bg-primary text-primary-fg text-xs font-semibold rounded-lg cursor-pointer">
          <input type="file" multiple accept="image/*" class="hidden" @change="uploadDeliveryProof($event); closeCameraModal()">
          <span>Выбрать файл из галереи</span>
        </label>
      </div>

      <div x-show="!capturedPreviewUrl && !cameraError && !cameraLoading" class="absolute inset-4 border border-white/30 rounded-lg pointer-events-none flex items-center justify-center">
        <div class="w-6 h-6 border-t-2 border-l-2 border-white/60 absolute top-0 left-0"></div>
        <div class="w-6 h-6 border-t-2 border-r-2 border-white/60 absolute top-0 right-0"></div>
        <div class="w-6 h-6 border-b-2 border-l-2 border-white/60 absolute bottom-0 left-0"></div>
        <div class="w-6 h-6 border-b-2 border-r-2 border-white/60 absolute bottom-0 right-0"></div>
      </div>
    </div>

    <div class="mt-3">
      <div x-show="!capturedPreviewUrl && !cameraError" class="flex items-center justify-center py-1">
        <button type="button"
                @click="takeSnapshot()"
                :disabled="cameraLoading"
                class="w-16 h-16 rounded-full border-4 border-white/80 bg-white/20 hover:bg-white/40 active:scale-90 transition-all flex items-center justify-center shadow-lg"
                aria-label="Сделать снимок">
          <div class="w-12 h-12 rounded-full bg-white"></div>
        </button>
      </div>

      <div x-show="capturedPreviewUrl" class="flex items-center gap-2">
        <button type="button"
                @click="retakeSnapshot()"
                class="flex-1 py-2.5 px-3 bg-surface2 text-fg rounded-lg text-xs font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
          Переснять
        </button>
        <button type="button"
                @click="confirmCapturedSnapshot()"
                :disabled="proofUploading"
                class="flex-1 py-2.5 px-3 bg-success text-white rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
          <template x-if="!proofUploading">
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
              </svg>
              Использовать фото
            </span>
          </template>
          <template x-if="proofUploading">
            <span>Сохранение...</span>
          </template>
        </button>
      </div>
    </div>
  @endcomponent

  @component('tma.partials.modal', ['show' => 'showProofPhotoModal', 'maxWidth' => 'max-w-2xl', 'padding' => 'p-3', 'zIndex' => 'z-[60]'])
    <div x-data="imageZoomViewer()" @keydown.window="handleKeydown($event)">
      <div class="flex items-center justify-between pb-2 mb-2 border-b border-border">
        <h3 class="font-display font-semibold text-sm text-fg">Фотофиксация вручения</h3>
        <div class="flex items-center gap-1.5">
          <div class="flex items-center bg-surface2 rounded-lg px-1 py-0.5 text-xs">
            <button type="button" @click="zoomOut()" :disabled="zoom <= 1" class="w-6 h-6 rounded flex items-center justify-center text-fg hover:bg-surface disabled:opacity-30 disabled:pointer-events-none" title="Уменьшить (Ctrl -)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
            </button>
            <button type="button" @click="reset()" class="px-1.5 min-w-[3rem] text-center font-mono font-medium text-[11px] text-fg hover:text-primary" title="Сбросить (Ctrl 0)">
              <span x-text="zoomPercent"></span>
            </button>
            <button type="button" @click="zoomIn()" :disabled="zoom >= 5" class="w-6 h-6 rounded flex items-center justify-center text-fg hover:bg-surface disabled:opacity-30 disabled:pointer-events-none" title="Увеличить (Ctrl +)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
            </button>
          </div>
          <button type="button"
                  @click="downloadPhoto(proofPhotoModalUrl)"
                  class="w-7 h-7 rounded-lg bg-surface2 flex items-center justify-center text-fg hover:text-primary hover:bg-surface active:scale-95 transition-all"
                  title="Скачать фото">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
          </button>
          <button type="button" @click="showProofPhotoModal = false" class="w-7 h-7 rounded-full bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg ml-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      <div x-ref="zoomContainer"
           @wheel="handleWheel($event)"
           @touchstart="handleTouchStart($event)"
           @touchmove="handleTouchMove($event)"
           @touchend="handleTouchEnd($event)"
           @mousedown="handleMouseDown($event)"
           @mousemove="handleMouseMove($event)"
           @mouseup="handleMouseUp()"
           @mouseleave="handleMouseUp()"
           @dblclick="handleDoubleClick()"
           class="relative rounded-lg overflow-hidden bg-black/40 flex items-center justify-center h-[65vh] select-none touch-none">
        <img :src="proofPhotoModalUrl" alt="Фото вручения" class="max-w-full max-h-full object-contain pointer-events-none rounded-lg bg-white" :style="imageStyle">
      </div>
      <div class="mt-2 flex items-center justify-between text-[11px] text-fg-subtle px-0.5">
        <span>Жесты pinch / Ctrl + / Колесико / 2-й клик</span>
        <button type="button" x-show="zoom > 1" @click="reset()" class="text-primary hover:underline font-medium">Сбросить масштаб</button>
      </div>
    </div>
  @endcomponent

  @component('tma.partials.modal', ['show' => 'showSignatureModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-4', 'zIndex' => 'z-[60]'])
    <div x-data="signaturePad()"
         x-init="$watch('showSignatureModal', value => { if (value) { $nextTick(() => initPad()); } else { destroyPad(); } })"
         class="space-y-3">
      <div class="flex items-center justify-between border-b border-border pb-2.5">
        <div class="text-left">
          <h3 class="font-display font-bold text-base text-fg">Подпись получателя</h3>
          <p class="text-xs text-fg-subtle">Попросите клиента расписаться пальцем на экране</p>
        </div>
        <button type="button" @click="closeSignaturePad()" class="p-1 rounded-lg text-fg-subtle hover:text-fg hover:bg-surface2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="relative w-full h-52 bg-white rounded-xl border-2 border-dashed border-border overflow-hidden select-none touch-none shadow-inner">
        <canvas x-ref="signatureCanvas"
                class="w-full h-full block cursor-crosshair touch-none bg-white"
                @pointerdown="handlePointerDown($event)"
                @pointermove="handlePointerMove($event)"
                @pointerup="handlePointerUp($event)"
                @pointercancel="handlePointerUp($event)"
                @pointerleave="if (isDrawing) handlePointerUp($event)">
        </canvas>

        <div class="pointer-events-none absolute bottom-5 left-6 right-6 border-b border-slate-300 flex items-center justify-between text-[11px] text-slate-400 select-none pb-0.5">
          <span>✕ Место для подписи</span>
        </div>

        <template x-if="!hasSignature && !isDrawing">
          <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-slate-400 text-xs font-medium">
            Проведите пальцем здесь
          </div>
        </template>
      </div>

      <div class="flex items-center justify-between text-xs">
        <div class="flex items-center gap-2">
          <button type="button"
                  @click="clear()"
                  :disabled="!hasSignature && strokes.length === 0"
                  class="px-2.5 py-1.5 bg-surface2 text-fg rounded-lg font-medium hover:bg-surface disabled:opacity-40 disabled:pointer-events-none transition-all">
            Очистить
          </button>
          <button type="button"
                  @click="undo()"
                  :disabled="strokes.length === 0"
                  class="px-2.5 py-1.5 bg-surface2 text-fg rounded-lg font-medium hover:bg-surface disabled:opacity-40 disabled:pointer-events-none transition-all"
                  title="Отменить последний штрих">
            Отменить
          </button>
        </div>
        <span class="text-[11px] text-fg-subtle" x-show="hasSignature">Штрихов: <span x-text="strokes.length"></span></span>
      </div>

      <div class="flex items-center gap-2 pt-1 border-t border-border">
        <button type="button"
                @click="closeSignaturePad()"
                class="flex-1 py-2.5 px-3 bg-surface2 text-fg rounded-lg text-xs font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
          Отмена
        </button>
        <button type="button"
                @click="if (submitting) return; submitting = true; try { const b = await getBlob(); await saveSignature(b); } finally { submitting = false; }"
                :disabled="!hasSignature || submitting"
                class="flex-1 py-2.5 px-3 bg-primary text-primary-fg rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:pointer-events-none active:scale-95 transition-transform shadow-sm">
          <template x-if="!submitting">
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
              </svg>
              Принять подпись
            </span>
          </template>
          <template x-if="submitting">
            <span>Сохранение...</span>
          </template>
        </button>
      </div>
    </div>
  @endcomponent

  {{-- Модалка 1: Инициация передачи заказа/кассы при форс-мажоре --}}
  @component('tma.partials.modal', ['show' => 'showHandoverModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-4'])
    <div class="text-left">
      <div class="flex items-center justify-between pb-2 mb-3 border-b border-border">
        <div class="flex items-center gap-2">
          <div class="w-2.5 h-2.5 rounded-full bg-error animate-pulse"></div>
          <h3 class="font-display font-bold text-base text-fg">Форс-мажор / Передача</h3>
        </div>
        <button type="button" @click="showHandoverModal = false" class="p-1 rounded-lg text-fg-subtle hover:text-fg hover:bg-surface2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="space-y-3.5 max-h-[70vh] overflow-y-auto pr-0.5">
        <div>
          <label class="block text-xs font-semibold text-fg mb-1.5">Причина передачи:</label>
          <select x-model="handoverForm.reason" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-xs font-medium text-fg focus:border-primary outline-none">
            <option value="vehicle_breakdown">Поломка транспорта / ТС</option>
            <option value="accident">ДТП</option>
            <option value="health">Ухудшение самочувствия</option>
            <option value="other">Другое</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-fg mb-1">Комментарий (где вы находитесь, детали):</label>
          <textarea x-model="handoverForm.reason_comment" rows="2" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-xs text-fg focus:border-primary outline-none resize-none" placeholder="Например: Стою на ул. Ленина 14, пробито колесо..."></textarea>
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-semibold text-fg">Передаваемые заказы:</label>
            <button type="button" @click="selectAllHandoverOrders()" class="text-[11px] text-primary hover:underline font-medium" x-text="handoverForm.order_ids.length === transferableOrders.length ? 'Снять все' : 'Выбрать все'"></button>
          </div>
          <template x-if="transferableOrders.length === 0">
            <p class="text-xs text-fg-subtle italic">Нет активных заказов для передачи</p>
          </template>
          <div class="space-y-1.5 max-h-40 overflow-y-auto">
            <template x-for="order in transferableOrders" :key="order.id">
              <label class="flex items-center justify-between p-2 rounded-lg bg-surface2/60 border border-border/50 text-xs cursor-pointer hover:border-primary/50 transition-colors">
                <div class="flex items-center gap-2 min-w-0">
                  <input type="checkbox" :value="order.id" :checked="handoverForm.order_ids.includes(order.id)" @change="toggleHandoverOrder(order.id)" class="rounded text-primary focus:ring-0">
                  <span class="font-medium text-fg shrink-0" x-text="'#' + order.id"></span>
                  <span class="text-fg-subtle truncate text-[11px]" x-text="order.address"></span>
                </div>
                <span class="font-semibold text-success shrink-0 ml-2" x-text="formatPrice(order.total_price)"></span>
              </label>
            </template>
          </div>
        </div>

        <div class="bg-surface2/40 rounded-xl p-3 border border-border/50">
          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input type="checkbox" x-model="handoverForm.transfer_cash" class="rounded text-primary focus:ring-0">
            <span class="text-xs font-semibold text-fg">Передать наличную выручку коллеге</span>
          </label>
          <template x-if="handoverForm.transfer_cash">
            <div>
              <div class="flex items-center justify-between text-[11px] text-fg-subtle mb-1">
                <span>Сумма к передаче наличными:</span>
                <span>В кассе по системе: <strong class="text-fg" x-text="formatPrice(shiftSummary?.cash_amount || 0)"></strong></span>
              </div>
              <input type="number" step="0.01" x-model.number="handoverForm.declared_cash" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm font-bold text-fg focus:border-primary outline-none" placeholder="0.00">
            </div>
          </template>
        </div>
      </div>

      <div class="flex items-center gap-2 pt-3 border-t border-border mt-3">
        <button type="button" @click="showHandoverModal = false" class="flex-1 py-2 px-3 bg-surface2 text-fg rounded-lg text-xs font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
          Отмена
        </button>
        <button type="button" @click="submitHandoverInitiate()" :disabled="handoverSubmitting || handoverForm.order_ids.length === 0" class="flex-1 py-2 px-3 bg-error text-white rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:pointer-events-none active:scale-95 transition-transform">
          <template x-if="!handoverSubmitting">
            <span>Сформировать код</span>
          </template>
          <template x-if="handoverSubmitting">
            <span>Создание...</span>
          </template>
        </button>
      </div>
    </div>
  @endcomponent

  {{-- Модалка 2: Показ кода и QR Курьеру А --}}
  @component('tma.partials.modal', ['show' => 'showHandoverQrModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-4'])
    <div class="text-center" x-data="{ copied: false }">
      <div class="flex items-center justify-between pb-2 mb-3 border-b border-border">
        <h3 class="font-display font-bold text-base text-fg">Код передачи смены</h3>
        <button type="button" @click="showHandoverQrModal = false" class="p-1 rounded-lg text-fg-subtle hover:text-fg hover:bg-surface2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <p class="text-xs text-fg-muted mb-3">Покажите этот экран или назовите код коллеге для приёма заказов и сверки кассы</p>

      <div class="bg-surface2/80 border-2 border-dashed border-primary/40 rounded-xl p-3.5 mb-3">
        <div class="text-[11px] text-fg-subtle uppercase tracking-wider mb-1">Код передачи</div>
        <div class="text-3xl font-mono font-extrabold tracking-widest text-primary mb-2" x-text="activeHandover?.transfer_code || '—'"></div>
        <button type="button" @click="navigator.clipboard?.writeText(activeHandover?.transfer_code || ''); copied = true; setTimeout(() => copied = false, 2000)" class="inline-flex items-center gap-1 text-[11px] font-medium text-fg-muted hover:text-fg bg-surface px-2.5 py-1 rounded-md border border-border">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
          <span x-text="copied ? 'Скопировано!' : 'Скопировать код'"></span>
        </button>
      </div>

      <template x-if="activeHandover?.qr_svg">
        <div class="w-44 h-44 mx-auto bg-white p-2.5 rounded-xl border border-border mb-3 flex items-center justify-center shadow-sm [&>svg]:w-full [&>svg]:h-full" x-html="activeHandover.qr_svg"></div>
      </template>

      <div class="bg-surface2/40 rounded-lg p-2.5 text-xs text-left mb-3 space-y-1">
        <div class="flex justify-between text-fg-subtle">
          <span>Заказов к передаче:</span>
          <strong class="text-fg" x-text="activeHandover?.orders_count || activeHandover?.orders?.length || 0"></strong>
        </div>
        <div class="flex justify-between text-fg-subtle">
          <span>Сумма кассы:</span>
          <strong class="text-fg" x-text="activeHandover?.transfer_cash ? formatPrice(activeHandover.declared_cash_amount) : 'Без кассы'"></strong>
        </div>
      </div>

      <div class="flex items-center gap-2 pt-2 border-t border-border">
        <button type="button" @click="cancelActiveHandover()" class="flex-1 py-2 px-3 bg-surface2 text-error rounded-lg text-xs font-semibold hover:bg-error/10 active:scale-95 transition-transform">
          Отменить передачу
        </button>
        <button type="button" @click="showHandoverQrModal = false" class="flex-1 py-2 px-3 bg-primary text-primary-fg rounded-lg text-xs font-semibold active:scale-95 transition-transform">
          Свернуть
        </button>
      </div>
    </div>
  @endcomponent

  {{-- Модалка 3: Ввод кода Курьером Б --}}
  @component('tma.partials.modal', ['show' => 'showScanHandoverModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-4'])
    <div class="text-left">
      <div class="flex items-center justify-between pb-2 mb-3 border-b border-border">
        <h3 class="font-display font-bold text-base text-fg">Приём смены / заказов</h3>
        <button type="button" @click="showScanHandoverModal = false" class="p-1 rounded-lg text-fg-subtle hover:text-fg hover:bg-surface2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <p class="text-xs text-fg-muted mb-3">Введите код передачи от коллеги (например, <code class="font-bold text-fg">FM-8K4P</code>):</p>

      <div class="mb-4">
        <input type="text"
               x-model="handoverReceiveForm.code"
               @keyup.enter="fetchHandoverPreview(handoverReceiveForm.code)"
               placeholder="FM-XXXX"
               class="w-full bg-surface border border-border rounded-xl px-4 py-3 text-center font-mono text-xl font-bold uppercase tracking-widest text-fg focus:border-primary outline-none">
      </div>

      <div class="flex items-center gap-2 pt-2 border-t border-border">
        <button type="button" @click="showScanHandoverModal = false" class="flex-1 py-2 px-3 bg-surface2 text-fg rounded-lg text-xs font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
          Отмена
        </button>
        <button type="button"
                @click="fetchHandoverPreview(handoverReceiveForm.code)"
                :disabled="handoverLoading || !handoverReceiveForm.code.trim()"
                class="flex-1 py-2 px-3 bg-primary text-primary-fg rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:pointer-events-none active:scale-95 transition-transform">
          <template x-if="!handoverLoading">
            <span>Проверить код</span>
          </template>
          <template x-if="handoverLoading">
            <span>Загрузка...</span>
          </template>
        </button>
      </div>
    </div>
  @endcomponent

  {{-- Модалка 4: Сверка заказов и кассы Курьером Б --}}
  @component('tma.partials.modal', ['show' => 'showHandoverPreviewModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-4'])
    <div class="text-left" x-data="{ discrepancy() { return handoverDiscrepancy; } }">
      <div class="flex items-center justify-between pb-2 mb-3 border-b border-border">
        <div>
          <h3 class="font-display font-bold text-base text-fg">Сверка и приём смены</h3>
          <p class="text-[11px] text-fg-subtle" x-text="'Передаёт: ' + (handoverPreviewData?.from_courier?.name || 'Курьер')"></p>
        </div>
        <button type="button" @click="showHandoverPreviewModal = false" class="p-1 rounded-lg text-fg-subtle hover:text-fg hover:bg-surface2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="space-y-3.5 max-h-[70vh] overflow-y-auto pr-0.5">
        <div class="p-2.5 bg-surface2/60 rounded-xl text-xs">
          <div class="flex items-center justify-between mb-1">
            <span class="text-fg-subtle">Причина форс-мажора:</span>
            <span class="font-semibold text-warning" x-text="handoverPreviewData?.reason_label || '—'"></span>
          </div>
          <template x-if="handoverPreviewData?.reason_comment">
            <p class="text-[11px] text-fg-muted italic pt-1 border-t border-border/40" x-text="handoverPreviewData.reason_comment"></p>
          </template>
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-semibold text-fg">Принимаемые заказы:</label>
            <span class="text-[11px] font-bold text-fg-subtle" x-text="(handoverPreviewData?.orders?.length || 0) + ' шт.'"></span>
          </div>
          <div class="space-y-1.5 max-h-40 overflow-y-auto">
            <template x-for="ord in handoverPreviewData?.orders || []" :key="ord.id">
              <div class="p-2 rounded-lg bg-surface2/60 border border-border/50 text-xs">
                <div class="flex items-center justify-between mb-0.5">
                  <span class="font-bold text-fg" x-text="'#' + ord.id"></span>
                  <span class="font-semibold text-success" x-text="formatPrice(ord.total_price)"></span>
                </div>
                <div class="text-[11px] text-fg-subtle truncate" x-text="ord.address"></div>
              </div>
            </template>
          </div>
        </div>

        <template x-if="handoverPreviewData?.transfer_cash">
          <div class="bg-surface2/40 rounded-xl p-3 border border-border/50">
            <div class="flex items-center justify-between text-xs mb-2">
              <span class="text-fg-subtle">Заявлено к передаче:</span>
              <span class="font-extrabold text-fg text-sm" x-text="formatPrice(handoverPreviewData.declared_cash_amount)"></span>
            </div>

            <label class="block text-xs font-semibold text-fg mb-1">Фактически пересчитано наличных:</label>
            <input type="number"
                   step="0.01"
                   x-model.number="handoverReceiveForm.actual_cash"
                   class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm font-bold text-fg focus:border-primary outline-none"
                   placeholder="0.00">

            <template x-if="discrepancy() === 0">
              <div class="mt-2 p-2 bg-success/10 border border-success/30 rounded-lg text-xs text-success flex items-center gap-1.5 font-medium">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <span>Касса сошлась без расхождений</span>
              </div>
            </template>

            <template x-if="discrepancy() !== 0">
              <div class="mt-2 space-y-2">
                <div class="p-2 bg-warning/15 border border-warning/30 rounded-lg text-xs text-warning flex items-center justify-between font-semibold">
                  <span>Расхождение:</span>
                  <span x-text="formatPrice(discrepancy())"></span>
                </div>
                <div>
                  <label class="block text-[11px] font-semibold text-error mb-1">Причина расхождения (обязательно):</label>
                  <textarea x-model="handoverReceiveForm.discrepancy_reason" rows="2" class="w-full bg-surface border border-error/50 rounded-lg px-2.5 py-1.5 text-xs text-fg focus:border-error outline-none resize-none" placeholder="Укажите, почему сумма отличается от заявленной..."></textarea>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>

      <div class="flex items-center gap-2 pt-3 border-t border-border mt-3">
        <button type="button" @click="showHandoverPreviewModal = false" class="flex-1 py-2 px-3 bg-surface2 text-fg rounded-lg text-xs font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
          Отклонить
        </button>
        <button type="button"
                @click="confirmAcceptHandover()"
                :disabled="handoverSubmitting || (handoverDiscrepancy !== 0 && !handoverReceiveForm.discrepancy_reason.trim())"
                class="flex-1 py-2 px-3 bg-success text-white rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:pointer-events-none active:scale-95 transition-transform shadow-sm">
          <template x-if="!handoverSubmitting">
            <span>Подтвердить приём</span>
          </template>
          <template x-if="handoverSubmitting">
            <span>Принятие...</span>
          </template>
        </button>
      </div>
    </div>
  @endcomponent
@endsection
