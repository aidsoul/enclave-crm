@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-6"
     @dblclick="handleOrderDoubleTap($event)"
     @touchstart.passive="handleOrderTouchStart($event)"
     @touchmove.passive="handleOrderTouchMove($event)"
     @touchend="handleOrderTouchEnd($event)">
  <button @click="goBack()" class="w-10 h-10 rounded-full bg-surface flex items-center justify-center active:scale-95 transition-transform">
    <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
    </svg>
  </button>
  <h1 class="font-display font-bold text-lg cursor-pointer" x-text="'Заказ #' + (orderId || '')"></h1>
  <div class="flex items-center gap-1.5">
    <a :href="'/tma/orders/' + orderId + '/chat'"
       class="relative w-10 h-10 rounded-full bg-surface flex items-center justify-center text-fg-muted hover:text-primary active:scale-95 transition-all"
       aria-label="Чат по заказу"
       title="Чат по заказу">
      <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.755-.783 7.828 7.828 0 0 0 .753-2.008C3.896 16.592 3 14.417 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
      </svg>
      <template x-if="getChatUnreadCount() > 0">
        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-error text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none ring-2 ring-surface shadow-sm"
              x-text="getChatUnreadCount() > 99 ? '99+' : getChatUnreadCount()"></span>
      </template>
    </a>
    <button type="button"
            x-show="order"
            @click="shareOrder()"
            class="w-10 h-10 rounded-full bg-surface flex items-center justify-center text-fg-muted hover:text-fg active:scale-95 transition-all"
            aria-label="Поделиться заказом"
            title="Поделиться заказом">
      <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
      </svg>
    </button>
    <button x-show="canEdit && !editing" @click="startEdit" class="w-10 h-10 rounded-full bg-surface flex items-center justify-center active:scale-95 transition-transform" aria-label="Редактировать">
      <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
      </svg>
    </button>
  </div>
</div>

<template x-if="loading">
  <div class="py-12 flex flex-col items-center justify-center gap-3">
    <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
    <p class="text-xs text-fg-muted">Загрузка...</p>
  </div>
</template>

<template x-if="!loading && loadError">
  <div class="bg-surface rounded-xl p-8 text-center">
    <p class="text-fg-muted text-sm" x-text="loadError"></p>
  </div>
</template>

<template x-if="!loading && !loadError && order">
  <div class="space-y-4 mb-4 relative"
       @paste.window="handlePaste($event)"
       @dragenter="handleDragEnter($event)"
       @dragover.prevent
       @dragleave="handleDragLeave($event)"
       @drop.prevent="handleDrop($event)"
       @dblclick="handleOrderDoubleTap($event)"
       @touchstart.passive="handleOrderTouchStart($event)"
       @touchmove.passive="handleOrderTouchMove($event)"
       @touchend="handleOrderTouchEnd($event)">

    <div x-cloak
         x-show="isDraggingFile"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 pointer-events-none flex items-center justify-center p-6 bg-bg/80 backdrop-blur-sm">
      <div class="w-full max-w-sm p-8 rounded-2xl border-2 border-dashed border-primary bg-primary/10 flex flex-col items-center justify-center text-center shadow-2xl animate-pulse">
        <div class="w-16 h-16 rounded-full bg-primary/20 text-primary flex items-center justify-center mb-4">
          <svg class="w-8 h-8 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
          </svg>
        </div>
        <h3 class="font-display font-bold text-base text-fg mb-1">Отпустите фото сюда</h3>
        <p class="text-xs text-primary font-medium">Фотофиксация автоматически прикрепится к заказу</p>
      </div>
    </div>
    <div class="bg-surface rounded-xl p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-medium" :class="getStatusClass(order.status)" x-text="getStatusLabel(order.status)"></span>
          <span x-show="order.is_valuable" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-500 border border-amber-500/30">Ценное</span>
        </div>
        <span class="text-xs text-fg-subtle" x-text="formatDate(order.created_at)"></span>
      </div>
      @if(!$user || !$user->isCourier())
      <div x-show="order.site" class="mt-2 pt-2 border-t border-border flex items-center justify-between gap-2">
        <span class="text-xs text-fg-subtle">Сайт</span>
        <span class="text-xs font-medium text-fg" x-text="formatSite(order.site)"></span>
      </div>
      <div x-show="order.site" class="mt-2 pt-2 border-t border-border flex items-center justify-between gap-2">
        <span class="text-xs text-fg-subtle">ID на сайте</span>
        <template x-if="order.external_id || order.extra_data?.external_order_id">
          <span @click="copyText(order.external_id || order.extra_data?.external_order_id, 'ID скопирован')"
                class="text-xs font-medium text-fg font-mono hover:text-primary cursor-pointer active:opacity-70 transition-colors inline-flex items-center gap-1 group"
                title="Нажмите, чтобы скопировать">
            <span x-text="(String(order.external_id || order.extra_data?.external_order_id).startsWith('#') ? '' : '#') + (order.external_id || order.extra_data?.external_order_id)"></span>
            <svg class="w-3.5 h-3.5 text-fg-muted group-hover:text-primary opacity-60 group-hover:opacity-100 transition-opacity shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
            </svg>
          </span>
        </template>
        <template x-if="!order.external_id && !order.extra_data?.external_order_id">
          <span class="text-xs text-fg-subtle">—</span>
        </template>
      </div>
      @endif
      <template x-if="order.is_edited && !isCourier">
        <div class="flex items-center gap-2 mt-3 text-warning text-xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
          </svg>
          Изменено менеджером
        </div>
      </template>
    </div>

    <!-- Blacklist Alert Banner for Manager -->
    <template x-if="order?.customer?.is_blacklisted">
      <div class="bg-error/15 border-2 border-error/50 rounded-xl p-3.5 flex items-start gap-3 text-error shadow-sm">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <span class="font-display font-bold text-sm tracking-wide">Внимание: Клиент в черном списке!</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-error text-white font-bold uppercase">ЧС</span>
          </div>
          <p class="text-xs text-fg mt-1" x-text="order.customer.blacklist_reason ? 'Причина: ' + order.customer.blacklist_reason : 'Клиент отмечен как неблагонадежный'"></p>
        </div>
      </div>
    </template>

    <div class="bg-surface rounded-xl overflow-hidden">
      <div @click="toggleAccordion('client')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50 cursor-pointer">
        <div class="flex items-center gap-2">
          <h2 class="font-display font-semibold text-sm text-fg">Клиент</h2>
          <button type="button"
                  @click.stop="copyCustomerInfo(order?.customer, getOrderAddress(order))"
                  class="p-1 text-fg-muted hover:text-primary active:scale-95 transition-all cursor-pointer rounded"
                  title="Скопировать данные клиента">
            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
            </svg>
          </button>
          <template x-if="order?.customer?.is_blacklisted">
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-error/20 text-error font-bold">ЧС</span>
          </template>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <template x-if="(order?.customer?.id || order?.customer_id) && !isCourier">
            <a :href="'/tma/customers/' + (order?.customer?.id || order?.customer_id)"
               @click.stop
               class="text-xs text-primary hover:underline font-medium inline-flex items-center gap-0.5 px-2 py-0.5 rounded-lg bg-primary/10 hover:bg-primary/20 transition-colors"
               title="Карточка клиента">
              <span>Карточка</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
              </svg>
            </a>
          </template>
          <span x-show="!accordions.client" class="text-xs text-fg-subtle truncate max-w-[140px]" x-text="order?.customer?.name || ''"></span>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.client ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </div>
      <div x-show="accordions.client" class="px-4 pb-4">
        <template x-if="order?.customer?.is_blacklisted">
          <div class="mb-3 p-2.5 rounded-lg bg-error/10 border border-error/30 text-error text-xs flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <div>
              <span class="font-semibold">В черном списке: </span>
              <span class="text-fg" x-text="order.customer.blacklist_reason || 'Неблагонадежный клиент'"></span>
            </div>
          </div>
        </template>
        <template x-if="order?.customer?.tags && order.customer.tags.length > 0">
          <div class="mb-3 flex items-center gap-1.5 flex-wrap">
            <span class="text-[11px] text-fg-subtle">Теги:</span>
            <template x-for="tag in order.customer.tags" :key="tag">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="['невыкуп', 'спам', 'частые отмены', 'отмена'].some(w => tag.toLowerCase().includes(w)) ? 'bg-error/15 text-error border border-error/30' : 'bg-surface2 text-fg-muted border border-border'"
                    x-text="tag"></span>
            </template>
          </div>
        </template>
        <div x-show="!editing" class="space-y-2">
          <div class="flex items-start justify-between gap-3">
            <span class="text-xs text-fg-subtle shrink-0">Имя</span>
            <div class="text-right min-w-0 flex-1">
              <template x-if="order?.customer?.name && (order?.customer?.id || order?.customer_id) && !isCourier">
                <div class="flex items-center gap-1 justify-end max-w-full">
                  <a :href="'/tma/customers/' + (order?.customer?.id || order?.customer_id)"
                     class="text-sm text-primary text-right break-all hover:underline active:opacity-70 transition-opacity font-medium"
                     x-text="order.customer.name"
                     title="Перейти в карточку клиента"></a>
                  <button type="button"
                          @click="copyText(order.customer.name, 'Имя скопировано')"
                          class="p-0.5 text-fg-subtle hover:text-primary active:scale-95 transition-all cursor-pointer shrink-0"
                          title="Скопировать имя">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
                    </svg>
                  </button>
                </div>
              </template>
              <template x-if="order?.customer?.name && !((order?.customer?.id || order?.customer_id) && !isCourier)">
                <span @click="copyText(order.customer.name, 'Имя скопировано')"
                      class="text-sm text-fg hover:text-primary cursor-pointer active:opacity-70 transition-colors inline-flex items-start justify-end gap-1 group max-w-full"
                      title="Нажмите, чтобы скопировать">
                  <span class="break-words [overflow-wrap:anywhere]" x-text="order?.customer?.name"></span>
                  <svg class="w-3.5 h-3.5 mt-0.5 text-fg-muted group-hover:text-primary opacity-60 group-hover:opacity-100 transition-opacity shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
                  </svg>
                </span>
              </template>
              <span x-show="!order?.customer?.name" class="text-sm text-right text-fg-muted">—</span>
            </div>
          </div>
          <div class="flex items-start justify-between gap-3">
            <span class="text-xs text-fg-subtle shrink-0">Телефон</span>
            <div class="flex flex-col items-end gap-1.5 min-w-0 flex-1">
              <div class="flex items-center gap-1 justify-end max-w-full" x-show="order?.customer?.phone">
                <a :href="getTelLink(order?.customer?.phone)"
                   class="text-sm text-primary text-right break-all hover:underline active:opacity-70 transition-opacity font-medium"
                   x-text="order?.customer?.formatted_phone || formatPhone(order?.customer?.phone)"
                   title="Позвонить"></a>
                <button type="button"
                        @click="copyText(order?.customer?.formatted_phone || formatPhone(order?.customer?.phone), 'Телефон скопирован')"
                        class="p-0.5 text-fg-subtle hover:text-primary active:scale-95 transition-all cursor-pointer shrink-0"
                        title="Скопировать телефон">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
                  </svg>
                </button>
              </div>
              <span x-show="!order?.customer?.phone" class="text-sm text-right text-fg-muted">—</span>
              <div x-show="order?.customer?.phone" class="flex items-center gap-1 mt-1 justify-end flex-nowrap">
                <a :href="'tg://resolve?phone=' + getCleanPhone(order?.customer?.phone)"
                   @click.prevent="openMessenger('telegram', order?.customer?.phone)"
                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-[#2AABEE]/15 hover:bg-[#2AABEE]/25 text-[#2AABEE] text-xs font-medium active:scale-95 transition-all shrink-0">
                  <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.63 3.73-.53.37-1.02.55-1.45.54-.48-.01-1.4-.27-2.09-.49-.84-.27-1.51-.42-1.45-.88.03-.24.38-.49 1.04-.75 4.07-1.77 6.78-2.94 8.14-3.51 3.86-1.61 4.67-1.89 5.2-1.9.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.19-.04.28z"/>
                  </svg>
                  <span>Telegram</span>
                </a>
                <a :href="'https://wa.me/' + getCleanPhone(order?.customer?.phone)"
                   @click.prevent="openMessenger('whatsapp', order?.customer?.phone)"
                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-[#25D366]/15 hover:bg-[#25D366]/25 text-[#25D366] text-xs font-medium active:scale-95 transition-all shrink-0">
                  <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.41a8.176 8.176 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.188 8.188 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.03-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.34-.76-1.84-.2-.49-.4-.42-.56-.43h-.47c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.12.17 1.78 2.72 4.31 3.81.6.26 1.07.41 1.44.53.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.12-.23-.19-.48-.31z"/>
                  </svg>
                  <span>WhatsApp</span>
                </a>
                <a :href="getMessengerLink('viber', order?.customer?.phone)"
                   @click.prevent="openMessenger('viber', order?.customer?.phone)"
                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-[#7360F2]/15 hover:bg-[#7360F2]/25 text-[#7360F2] text-xs font-medium active:scale-95 transition-all shrink-0">
                  <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                    <path d="M19.74 3.76A10.88 10.88 0 0 0 12.08 1.5C5.81 1.5 1.5 5.74 1.5 11.91c0 2.92 1.09 5.58 3.12 7.55l-.46 3.04 3.2-.84c1.4.38 2.87.58 4.38.58 6.46 0 10.76-4.34 10.76-10.33a10.6 10.6 0 0 0-2.76-8.15zm-2.07 11.45c-.32.88-1.55 1.63-2.38 1.67-.62.03-1.42-.16-3.83-1.15-2.92-1.2-4.83-4.14-4.97-4.34-.15-.2-1.18-1.57-1.18-2.99 0-1.42.74-2.12 1-2.41.27-.29.58-.36.78-.36.2 0 .39 0 .56.01.18.01.42-.07.65.49.24.58.81 1.99.88 2.14.07.15.12.33.02.53-.1.2-.15.32-.3.5-.15.17-.31.39-.45.52-.15.15-.3.31-.13.61.17.29.77 1.27 1.66 2.06 1.14 1.02 2.1 1.33 2.4 1.48.29.15.46.12.63-.07.17-.2.73-.85.93-1.15.2-.29.39-.24.66-.15.27.1 1.7.8 1.99.95.29.15.49.22.56.34.07.15.07.85-.25 1.73z"/>
                  </svg>
                  <span>Viber</span>
                </a>
              </div>
            </div>
          </div>
          <div class="flex items-start justify-between gap-3">
            <span class="text-xs text-fg-subtle shrink-0">Email</span>
            <div class="text-right min-w-0 flex-1">
              <div class="flex items-center justify-end gap-1 max-w-full" x-show="order?.customer?.email">
                <a :href="getEmailLink(order?.customer?.email)"
                   @click.prevent="openEmail(order?.customer?.email)"
                   class="text-sm text-primary text-right break-all hover:underline active:opacity-70 transition-opacity font-medium"
                   x-text="order?.customer?.email"
                   title="Написать письмо"></a>
                <button type="button"
                        @click="copyText(order?.customer?.email, 'Email скопирован')"
                        class="p-0.5 text-fg-subtle hover:text-primary active:scale-95 transition-all cursor-pointer shrink-0"
                        title="Скопировать email">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
                  </svg>
                </button>
              </div>
              <span x-show="!order?.customer?.email" class="text-sm text-right text-fg-muted">—</span>
            </div>
          </div>
          <div class="flex flex-col gap-1.5 pt-1" x-show="order?.customer?.address || getOrderAddress(order)">
            <div class="flex items-start justify-between gap-3">
              <span class="text-xs text-fg-subtle shrink-0">Адрес</span>
              <div class="text-right min-w-0 flex-1">
                <span @click="copyText(order?.customer?.address || getOrderAddress(order), 'Адрес скопирован')"
                      class="text-sm text-right text-fg hover:text-primary cursor-pointer active:opacity-70 transition-colors inline-flex items-start justify-end gap-1 group max-w-full"
                      title="Нажмите, чтобы скопировать">
                  <span class="break-words [overflow-wrap:anywhere]" x-text="order?.customer?.address || getOrderAddress(order)"></span>
                  <svg class="w-3.5 h-3.5 mt-0.5 text-fg-muted group-hover:text-primary opacity-60 group-hover:opacity-100 transition-opacity shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
                  </svg>
                </span>
              </div>
            </div>
            <div class="flex items-center justify-end gap-1.5 flex-wrap">
              <span class="text-xs text-fg-subtle mr-1">Карты:</span>
              <button type="button" @click="openNavigator('yandex', order?.customer?.address || getOrderAddress(order))" class="px-2 py-0.5 rounded bg-surface2 text-xs text-fg hover:border-primary active:scale-95 transition-all">Яндекс</button>
              <button type="button" @click="openNavigator('2gis', order?.customer?.address || getOrderAddress(order))" class="px-2 py-0.5 rounded bg-surface2 text-xs text-fg hover:border-primary active:scale-95 transition-all">2GIS</button>
              <button type="button" @click="openNavigator('google', order?.customer?.address || getOrderAddress(order))" class="px-2 py-0.5 rounded bg-surface2 text-xs text-fg hover:border-primary active:scale-95 transition-all">Google</button>
            </div>
          </div>
        </div>
        <div x-show="editing" class="space-y-3">
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Имя</label>
            <input type="text" x-model="form.client_name" @input="clearFieldError('client_name')" :class="inputClass('client_name')">
            <p class="text-error text-xs mt-1" x-show="errors.client_name" x-text="errors.client_name"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Телефон</label>
            <input type="tel" x-model="form.client_phone" @input="clearFieldError('client_phone')" :class="inputClass('client_phone')">
            <p class="text-error text-xs mt-1" x-show="errors.client_phone" x-text="errors.client_phone"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Email</label>
            <input type="email" x-model="form.client_email" @input="clearFieldError('client_email')" :class="inputClass('client_email')" placeholder="client@example.com">
            <p class="text-error text-xs mt-1" x-show="errors.client_email" x-text="errors.client_email"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Адрес</label>
            <textarea x-model="form.client_address" rows="2" @input="clearFieldError('client_address')" :class="inputClass('client_address', 'resize-none')"></textarea>
            <p class="text-error text-xs mt-1" x-show="errors.client_address" x-text="errors.client_address"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Адрес доставки</label>
            <textarea x-model="form.delivery_address" rows="2" @input="clearFieldError('delivery_address')" :class="inputClass('delivery_address', 'resize-none')" placeholder="Если отличается от адреса клиента"></textarea>
            <p class="text-error text-xs mt-1" x-show="errors.delivery_address" x-text="errors.delivery_address"></p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-surface rounded-xl" :class="{'relative z-30': quickProductDropdownOpen || activeItemProductDropdown !== null}">
      <div class="flex items-center justify-between p-4 select-none transition-colors hover:bg-surface2/50 rounded-xl" :class="accordions.items ? 'rounded-b-none' : ''">
        <button type="button" @click="toggleAccordion('items')" class="flex items-center gap-2 text-left flex-1 min-w-0">
          <h2 class="font-display font-semibold text-sm text-fg">Состав заказа</h2>
          <span class="text-xs text-fg-subtle" x-show="order?.items?.length" x-text="'(' + order.items.length + ')'"></span>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.items ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div class="flex items-center gap-2 shrink-0">
          <button type="button" @click.stop="showItemsModal = true" x-show="itemLines.length > 1" class="px-2 py-1 rounded-lg bg-surface2 text-xs text-fg font-medium hover:border-primary flex items-center gap-1 active:scale-95 transition-all" title="Просмотр всех товаров">
            <svg class="w-3.5 h-3.5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
            </svg>
            <span>Все товары</span>
          </button>
          <span x-show="!accordions.items && order?.total_price" class="text-xs font-medium text-fg" x-text="formatPrice(order.total_price)"></span>
        </div>
      </div>
      <div x-show="accordions.items" class="px-4 pb-4">
        <div x-show="!editing">
          <template x-if="itemLines.length === 0">
            <p class="text-fg-subtle text-sm">Состав не указан</p>
          </template>
          <ul class="space-y-2">
            <template x-for="(item, index) in itemLines" :key="index">
              @include('tma.partials.order-item')
            </template>
          </ul>
        </div>
        <div x-show="editing" class="space-y-3">
          <!-- Выпадающий список добавления товара из базы со справочником (лимит 5) -->
          <div class="relative" :class="{'z-30': quickProductDropdownOpen}">
            <button @click="openQuickProductDropdown()" type="button" class="w-full px-3.5 py-3 bg-surface2 rounded-xl text-xs text-left flex items-center justify-between text-fg hover:bg-surface2/80 transition-colors border border-border/40">
              <span class="flex items-center gap-2 text-fg-muted font-medium text-xs">
                <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <span>Добавить товар из базы (справочника)...</span>
              </span>
              <svg class="w-4 h-4 text-fg-muted shrink-0 transition-transform" :class="quickProductDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>

            <div x-show="quickProductDropdownOpen" @click.away="quickProductDropdownOpen = false" class="absolute z-40 left-0 right-0 mt-1.5 bg-surface rounded-xl shadow-2xl ring-1 ring-white/10 max-h-[460px] overflow-hidden flex flex-col border border-border/60">
              <div class="p-2.5 bg-surface2 border-b border-white/5">
                <div class="relative">
                  <input type="text" x-model="quickProductSearch" @input="searchQuickProducts()" placeholder="Поиск товара по названию, SKU..." class="w-full pl-9 pr-9 py-2.5 bg-surface border border-border/50 rounded-xl text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary transition-colors" @click.stop autofocus>
                  <svg class="w-4 h-4 text-fg-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                  </svg>
                  <div x-show="quickProductsLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="w-4 h-4 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                  </div>
                </div>
              </div>
              <div class="max-h-[380px] overflow-y-auto divide-y divide-white/5 p-1.5 space-y-1">
                <template x-for="prod in quickProducts" :key="prod.id">
                  <div class="rounded-lg hover:bg-surface2/60 transition-colors">
                    <template x-if="!prod.variants || prod.variants.length === 0">
                      <button type="button" @click="addProductToItems(prod)" class="w-full p-3 text-left flex items-start justify-between gap-3 transition-colors active:scale-[0.99] group">
                        <div class="min-w-0 flex-1">
                          <div class="text-sm font-medium text-fg group-hover:text-primary transition-colors leading-snug break-words" x-text="prod.name"></div>
                          <div class="flex items-center gap-2 mt-1.5 text-xs text-fg-subtle flex-wrap">
                            <span x-show="prod.sku" class="font-mono bg-surface2 px-1.5 py-0.5 rounded text-[11px] text-fg-muted border border-border/40" x-text="'SKU: ' + prod.sku"></span>
                            <span class="font-semibold text-fg text-sm" x-text="(prod.price || 0) + ' {{ config('app.currency_symbol', 'Br') }}'"></span>
                          </div>
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-surface2 group-hover:bg-primary group-hover:text-primary-fg flex items-center justify-center text-primary shrink-0 mt-0.5 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                          </svg>
                        </div>
                      </button>
                    </template>
                    <template x-if="prod.variants && prod.variants.length > 0">
                      <div class="p-3 space-y-2.5 bg-surface2/30 rounded-xl border border-white/5">
                        <div class="text-sm font-semibold text-fg leading-snug break-words" x-text="prod.name"></div>
                        <div class="text-[11px] text-fg-subtle uppercase tracking-wider font-semibold">Варианты товара:</div>
                        <div class="space-y-1.5">
                          <template x-for="variant in prod.variants" :key="variant.id">
                            <button type="button" @click="addProductToItems(prod, variant)" class="w-full p-2.5 rounded-lg bg-surface hover:bg-surface2 border border-border/40 text-left flex items-center justify-between gap-3 transition-all active:scale-[0.99] group">
                              <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-fg group-hover:text-primary transition-colors leading-snug break-words" x-text="variant.title || 'Модификация'"></div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-fg-subtle" x-show="variant.sku || prod.sku">
                                  <span class="font-mono bg-surface2 px-1.5 py-0.5 rounded text-[11px] text-fg-muted border border-border/30" x-text="'SKU: ' + (variant.sku || prod.sku)"></span>
                                </div>
                              </div>
                              <div class="flex items-center gap-2.5 shrink-0">
                                <span class="text-sm font-bold text-fg tabular-nums" x-text="(variant.price || prod.price || 0) + ' {{ config('app.currency_symbol', 'Br') }}'"></span>
                                <div class="w-7 h-7 rounded-md bg-surface2 group-hover:bg-primary group-hover:text-primary-fg flex items-center justify-center text-primary transition-colors">
                                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                  </svg>
                                </div>
                              </div>
                            </button>
                          </template>
                        </div>
                      </div>
                    </template>
                  </div>
                </template>
                <div x-show="!quickProductsLoading && quickProducts.length === 0" class="py-6 px-4 text-sm text-fg-muted text-center">Товары не найдены</div>
              </div>
            </div>
          </div>

          <template x-if="form.order_items.length === 0">
            <p class="text-fg-subtle text-sm">Позиции не добавлены</p>
          </template>
          <template x-for="(item, index) in form.order_items" :key="index">
            <div class="border border-border rounded-lg p-3 space-y-2 relative" :class="{'z-30': activeItemProductDropdown === index}">
              <div class="flex items-center gap-2">
                <div class="relative flex-1 min-w-0">
                  <input type="text" x-model="item.name" @input="clearFieldError('order_items.' + index + '.name')" :class="inputClass('order_items.' + index + '.name', 'w-full pr-20 text-sm')" placeholder="Название товара">
                  <button type="button" @click="openItemProductDropdown(index)" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-xs font-medium text-primary hover:text-primary-hover flex items-center gap-1 bg-surface2 px-2 py-1 rounded-md border border-border/40" title="Выбрать из справочника товаров">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    <span>Из базы</span>
                  </button>
                </div>
                <button type="button" @click="removeItem(index)" class="w-9 h-9 shrink-0 rounded-lg bg-surface2 flex items-center justify-center text-fg-muted hover:text-error transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <!-- Выпадающий список с поиском для конкретного товара (ограничение 5) -->
              <div x-show="activeItemProductDropdown === index" @click.away="activeItemProductDropdown = null" class="absolute z-40 left-0 right-0 mt-1.5 bg-surface rounded-xl shadow-2xl ring-1 ring-white/10 max-h-[420px] overflow-hidden flex flex-col border border-border/60">
                <div class="p-2.5 bg-surface2 border-b border-white/5">
                  <div class="relative">
                    <input type="text" x-model="itemProductSearch" @input="searchItemProducts()" placeholder="Поиск товара в базе..." class="w-full pl-8 pr-8 py-2 bg-surface border border-border/50 rounded-lg text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary" @click.stop autofocus>
                    <svg class="w-3.5 h-3.5 text-fg-muted absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <div x-show="itemProductsLoading" class="absolute right-2.5 top-1/2 -translate-y-1/2">
                      <svg class="w-3.5 h-3.5 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                      </svg>
                    </div>
                  </div>
                </div>
                <div class="max-h-[340px] overflow-y-auto divide-y divide-white/5 p-1.5 space-y-1">
                  <template x-for="prod in itemProducts" :key="prod.id">
                    <div class="rounded-lg hover:bg-surface2/60 transition-colors">
                      <template x-if="!prod.variants || prod.variants.length === 0">
                        <button type="button" @click="addProductToItems(prod, null, index)" class="w-full p-2.5 text-left flex items-start justify-between gap-2.5 transition-colors active:scale-[0.99] group">
                          <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-fg group-hover:text-primary transition-colors leading-snug break-words" x-text="prod.name"></div>
                            <div class="flex items-center gap-2 mt-1 text-xs text-fg-subtle flex-wrap">
                              <span x-show="prod.sku" class="font-mono bg-surface2 px-1.5 py-0.5 rounded text-[11px] text-fg-muted border border-border/40" x-text="'SKU: ' + prod.sku"></span>
                              <span class="font-semibold text-fg text-sm" x-text="(prod.price || 0) + ' {{ config('app.currency_symbol', 'Br') }}'"></span>
                            </div>
                          </div>
                          <span class="text-primary text-xs font-semibold px-2 py-1 rounded bg-primary/10 group-hover:bg-primary group-hover:text-primary-fg shrink-0 transition-colors">Выбрать</span>
                        </button>
                      </template>
                      <template x-if="prod.variants && prod.variants.length > 0">
                        <div class="p-2.5 space-y-2 bg-surface2/30 rounded-xl border border-white/5">
                          <div class="text-sm font-semibold text-fg leading-snug break-words" x-text="prod.name"></div>
                          <div class="space-y-1">
                            <template x-for="variant in prod.variants" :key="variant.id">
                              <button type="button" @click="addProductToItems(prod, variant, index)" class="w-full p-2 rounded-lg bg-surface hover:bg-surface2 border border-border/40 text-left flex items-center justify-between gap-2 transition-all active:scale-[0.99] group">
                                <div class="min-w-0 flex-1">
                                  <span class="text-sm font-medium text-fg group-hover:text-primary transition-colors leading-snug break-words" x-text="variant.title || 'Модификация'"></span>
                                  <span x-show="variant.sku || prod.sku" class="text-[11px] text-fg-subtle ml-1 font-mono" x-text="'(' + (variant.sku || prod.sku) + ')'"></span>
                                </div>
                                <span class="font-bold text-fg shrink-0 text-sm tabular-nums" x-text="(variant.price || prod.price || 0) + ' {{ config('app.currency_symbol', 'Br') }}'"></span>
                              </button>
                            </template>
                          </div>
                        </div>
                      </template>
                    </div>
                  </template>
                  <div x-show="!itemProductsLoading && itemProducts.length === 0" class="py-6 px-4 text-sm text-fg-muted text-center">Товары не найдены</div>
                </div>
              </div>

              <div class="flex items-center gap-1.5" x-show="item.variant_title">
                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-primary/10 text-primary" x-text="item.variant_title"></span>
              </div>
              <p class="text-error text-xs" x-show="errors['order_items.' + index + '.name']" x-text="errors['order_items.' + index + '.name']"></p>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div>
                  <input type="text" x-model="item.sku" @input="clearFieldError('order_items.' + index + '.sku')" :class="inputClass('order_items.' + index + '.sku')" placeholder="SKU">
                  <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.sku']" x-text="errors['order_items.' + index + '.sku']"></p>
                </div>
                <div>
                  <input type="number" min="1" x-model.number="item.qty" @input="onQtyInput(item, $event); clearFieldError('order_items.' + index + '.qty')" @blur="onQtyBlur(item, $event)" :class="inputClass('order_items.' + index + '.qty', 'px-2')" placeholder="Кол-во">
                  <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.qty']" x-text="errors['order_items.' + index + '.qty']"></p>
                </div>
                <div>
                  <input type="number" step="0.01" min="0" x-model="item.price" @input="onItemPriceInput(item, $event); clearFieldError('order_items.' + index + '.price')" @blur="onPriceBlur(item, 'price', $event, () => onItemPriceChange(item))" :class="inputClass('order_items.' + index + '.price')" placeholder="Цена, {{ config('app.currency_symbol', 'Br') }}">
                  <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.price']" x-text="errors['order_items.' + index + '.price']"></p>
                </div>
                <div>
                  <input type="number" step="0.01" min="0" x-model="item.purchase_price" @input="onItemPurchasePriceInput(item, $event); clearFieldError('order_items.' + index + '.purchase_price')" @blur="onPriceBlur(item, 'purchase_price', $event, () => recalculatePrices())" :class="inputClass('order_items.' + index + '.purchase_price')" placeholder="Закупка, {{ config('app.currency_symbol', 'Br') }}">
                  <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.purchase_price']" x-text="errors['order_items.' + index + '.purchase_price']"></p>
                </div>
              </div>
            </div>
          </template>
          <button type="button" @click="addItem" class="w-full py-2 border border-dashed border-border rounded-lg text-fg-muted text-xs flex items-center justify-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Добавить товар вручную
          </button>
        </div>
      </div>
    </div>

    @if($user && $user->isOwner())
    <div class="bg-surface rounded-xl overflow-hidden">
      <button type="button" @click="toggleAccordion('finances')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50">
        <h2 class="font-display font-semibold text-sm text-fg">Финансы</h2>
        <div class="flex items-center gap-2 shrink-0">
          <span x-show="!accordions.finances && order" class="text-xs font-semibold text-success" x-text="'Прибыль: ' + formatPrice(profit)"></span>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.finances ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </button>
      <div x-show="accordions.finances" class="px-4 pb-4">
        <div x-show="!editing" class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Стоимость товаров</span>
            <span class="text-sm font-medium" x-text="formatPrice(getItemsTotal())"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Стоимость доставки</span>
            <span class="text-sm font-medium" x-text="formatPrice(order.delivery?.[0]?.delivery_price || order.delivery?.delivery_price || 0)"></span>
          </div>
          <div class="flex items-center justify-between gap-3 pt-1 border-t border-border/40">
            <span class="text-xs font-semibold text-fg">Цена для клиента</span>
            <span class="text-sm font-bold text-primary" x-text="formatPrice(order.total_price)"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Закупочная цена</span>
            <span class="text-sm font-medium" x-text="formatPrice(order.purchase_price)"></span>
          </div>
          <div class="flex items-center justify-between gap-3 pt-2 border-t border-border">
            <span class="text-xs text-fg-subtle">Прибыль</span>
            <span class="text-sm font-bold text-success" x-text="formatPrice(profit)"></span>
          </div>
        </div>
        <div x-show="editing" class="space-y-3">
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Цена для клиента</label>
            <input type="number" step="0.01" min="0" x-model="form.total_price" @input="onTotalPriceInput($event); clearFieldError('total_price')" @blur="onPriceBlur(form, 'total_price', $event)" :class="inputClass('total_price')">
            <p class="text-error text-xs mt-1" x-show="errors.total_price" x-text="errors.total_price"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Закупочная цена</label>
            <input type="number" step="0.01" min="0" x-model="form.purchase_price" @input="onPurchasePriceInput($event); clearFieldError('purchase_price')" @blur="onPriceBlur(form, 'purchase_price', $event)" :class="inputClass('purchase_price')">
            <p class="text-error text-xs mt-1" x-show="errors.purchase_price" x-text="errors.purchase_price"></p>
          </div>
        </div>
      </div>
    </div>
    @endif

    @if($user && !$user->isCourier())
    <div class="bg-surface rounded-xl" :class="{'relative z-20': managerDropdownOpen || siteDropdownOpen || statusDropdownOpen}">
      <button type="button" @click="toggleAccordion('participants')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50 rounded-xl" :class="accordions.participants ? 'rounded-b-none' : ''">
        <h2 class="font-display font-semibold text-sm text-fg">Участники</h2>
        <div class="flex items-center gap-2 shrink-0">
          <span x-show="!accordions.participants" class="text-xs text-fg-subtle truncate max-w-[140px]" x-text="order?.manager?.system_name || 'Не назначен'"></span>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.participants ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </button>
      <div x-show="accordions.participants" class="px-4 pb-4">
        <div x-show="!editing" class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Сайт</span>
            <span class="text-sm text-right" x-text="formatSite(order.site)"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Менеджер</span>
            <span class="text-sm text-right" x-text="order.manager?.system_name || '—'"></span>
          </div>
        </div>
        <div x-show="editing" class="space-y-3">
          @if($user && $user->isOwner())
          <div class="relative" :class="{'z-30': siteDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Сайт</label>
            <button @click="openSiteDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.site_id ? 'border-error' : 'border-border'">
              <span x-text="form.site_id ? (sites.find(s => s.id == form.site_id)?.domain || sites.find(s => s.id == form.site_id)?.name || (order?.site?.id == form.site_id ? (order?.site?.domain || order?.site?.name) : 'Выберите сайт')) : 'Выберите сайт'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="siteDropdownOpen" @click.away="siteDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <input type="text" x-model="siteSearch" placeholder="Поиск..." class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary" @click.stop>
              <div class="max-h-40 overflow-y-auto">
                <button @click="form.site_id = ''; siteDropdownOpen = false; clearFieldError('site_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.site_id ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
                  <span>Выберите сайт</span>
                  <span x-show="!form.site_id" class="text-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                  </span>
                </button>
                <template x-for="site in filteredSites" :key="site.id">
                  <button @click="form.site_id = site.id; siteDropdownOpen = false; clearFieldError('site_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.site_id == site.id ? 'bg-surface2 text-primary font-medium' : ''">
                    <span x-text="site.domain || site.name"></span>
                    <span x-show="form.site_id == site.id" class="text-primary">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
                <div x-show="filteredSites.length === 0" class="px-3 py-2 text-sm text-fg-muted">Ничего не найдено</div>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.site_id" x-text="errors.site_id"></p>
          </div>
          @endif
          @if($user && $user->isOwner())
          <div class="relative" :class="{'z-30': statusDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Статус</label>
            <button @click="openStatusDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.status ? 'border-error' : 'border-border'">
              <span x-text="availableStatuses.find(s => s.value === form.status)?.label || form.status || 'Выберите статус'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="statusDropdownOpen" @click.away="statusDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <div class="max-h-48 overflow-y-auto">
                <template x-for="s in availableStatuses" :key="s.value">
                  <button @click="form.status = s.value; statusDropdownOpen = false; clearFieldError('status')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.status === s.value ? 'bg-surface2 text-primary font-medium' : ''">
                    <span x-text="s.label"></span>
                    <span x-show="form.status === s.value" class="text-primary">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
                <div x-show="availableStatuses.length === 0" class="px-3 py-2 text-sm text-fg-muted">Нет доступных статусов</div>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.status" x-text="errors.status"></p>
          </div>
          @endif
          @if($user && $user->isOwner())
          <div class="relative" :class="{'z-30': managerDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Менеджер</label>
            <button @click="openManagerDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.manager_id ? 'border-error' : 'border-border'">
              <span x-text="form.manager_id ? (managers.find(m => m.id === form.manager_id)?.system_name || (order?.manager?.id === form.manager_id ? order?.manager?.system_name : 'Не назначен')) : 'Не назначен'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="managerDropdownOpen" @click.away="managerDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <input type="text" x-model="managerSearch" @input="searchManagers()" placeholder="Поиск..." class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary" @click.stop>
              <div class="max-h-40 overflow-y-auto">
                <button @click="form.manager_id = ''; managerDropdownOpen = false; clearFieldError('manager_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.manager_id ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
                  <span>Не назначен</span>
                  <span x-show="!form.manager_id" class="text-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                  </span>
                </button>
                <template x-for="manager in managers" :key="manager.id">
                  <button @click="form.manager_id = manager.id; managerDropdownOpen = false; clearFieldError('manager_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.manager_id === manager.id ? 'bg-surface2 text-primary font-medium' : ''">
                    <span x-text="manager.system_name"></span>
                    <span x-show="form.manager_id === manager.id" class="text-primary">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
                <div x-show="managers.length === 0" class="px-3 py-2 text-sm text-fg-muted">Ничего не найдено</div>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.manager_id" x-text="errors.manager_id"></p>
          </div>
          @endif
        </div>
      </div>
    </div>
    @endif

    <div id="delivery-section" x-ref="deliverySection" class="bg-surface rounded-xl scroll-mt-4" :class="{'relative z-20': courierDropdownOpen || deliveryTypeDropdownOpen}">
      <button type="button" @click="toggleAccordion('delivery')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50 rounded-xl" :class="accordions.delivery ? 'rounded-b-none' : ''">
        <h2 class="font-display font-semibold text-sm text-fg">Доставка</h2>
        <div class="flex items-center gap-2 shrink-0">
          <span x-show="!accordions.delivery" class="text-xs text-fg-subtle truncate max-w-[140px]" x-text="order?.delivery?.[0]?.type?.label || order?.delivery?.type?.label || '—'"></span>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.delivery ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </button>
      <div x-show="accordions.delivery" class="px-4 pb-4">
        <div x-show="!editing" class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Тип</span>
            <span class="text-sm" x-text="order.delivery?.[0]?.type?.label || order.delivery?.type?.label || '—'"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Курьер</span>
            <span class="text-sm" x-text="order.delivery?.[0]?.courier?.system_name || order.delivery?.courier?.system_name || '—'"></span>
          </div>
          <div x-show="order.delivery?.[0]?.city || order.delivery?.city" class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Город</span>
            <span class="text-sm font-medium" x-text="order.delivery?.[0]?.city || order.delivery?.city"></span>
          </div>
          <div x-show="order.delivery?.[0]?.delivery_address || order.delivery?.delivery_address" class="flex flex-col gap-1.5 pt-1">
            <div class="flex items-start justify-between gap-3">
              <span class="text-xs text-fg-subtle shrink-0">Адрес доставки</span>
              <span class="text-sm text-right min-w-0 flex-1 break-words [overflow-wrap:anywhere]" x-text="order.delivery?.[0]?.delivery_address || order.delivery?.delivery_address"></span>
            </div>
            <div class="flex items-center justify-end gap-1.5 flex-wrap">
              <span class="text-xs text-fg-subtle mr-1">Карты:</span>
              <button type="button" @click="openNavigator('yandex', getOrderAddress(order))" class="px-2 py-0.5 rounded bg-surface2 text-xs text-fg hover:border-primary active:scale-95 transition-all">Яндекс</button>
              <button type="button" @click="openNavigator('2gis', getOrderAddress(order))" class="px-2 py-0.5 rounded bg-surface2 text-xs text-fg hover:border-primary active:scale-95 transition-all">2GIS</button>
              <button type="button" @click="openNavigator('google', getOrderAddress(order))" class="px-2 py-0.5 rounded bg-surface2 text-xs text-fg hover:border-primary active:scale-95 transition-all">Google</button>
            </div>
          </div>
          <div x-show="order.delivery?.[0]?.courier_comment || order.delivery?.courier_comment" class="flex items-start justify-between gap-3">
            <span class="text-xs text-fg-subtle shrink-0">Комментарий</span>
            <div class="max-h-20 overflow-y-auto overscroll-contain pr-1 scrollable-text text-right max-w-[70%]">
              <span class="text-sm break-words whitespace-pre-wrap" x-text="order.delivery?.[0]?.courier_comment || order.delivery?.courier_comment"></span>
            </div>
          </div>
          <div x-show="order.delivery?.[0]?.pickup_point || order.delivery?.pickup_point" class="flex items-start justify-between gap-3">
            <span class="text-xs text-fg-subtle shrink-0">Пункт самовывоза</span>
            <span class="text-sm text-right" x-text="order.delivery?.[0]?.pickup_point || order.delivery?.pickup_point"></span>
          </div>
          <div x-show="order.delivery?.[0]?.delivery_date || order.delivery?.delivery_date" class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Дата доставки</span>
            <span class="text-sm font-medium" x-text="formatDateOnly(order.delivery?.[0]?.delivery_date || order.delivery?.delivery_date)"></span>
          </div>
          <div x-show="order.delivery?.[0]?.delivery_slot || order.delivery?.delivery_slot" class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Интервал времени</span>
            <span class="text-sm font-medium" x-text="order.delivery?.[0]?.delivery_slot || order.delivery?.delivery_slot"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Стоимость доставки</span>
            <span class="text-sm font-medium" x-text="parseFloat(order.delivery?.[0]?.delivery_price || order.delivery?.delivery_price || 0) > 0 ? formatPrice(order.delivery?.[0]?.delivery_price || order.delivery?.delivery_price) : '0.00 Br (Бесплатно)'"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Ценное отправление</span>
            <span class="text-sm font-medium" :class="order.is_valuable ? 'text-amber-500' : 'text-fg-muted'" x-text="order.is_valuable ? 'Да (требуется подпись)' : 'Нет'"></span>
          </div>
          <div class="pt-2.5 mt-2 border-t border-border/60" x-show="canShowProofSection()">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs text-fg-subtle font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
                Фотофиксация вручения
                <span x-show="getPhotoProofUrls().length > 0" class="text-[11px] font-mono text-fg-muted" x-text="'(' + getPhotoProofUrls().length + '/3)'"></span>
              </span>
              <template x-if="getPhotoProofUrls().length > 0">
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded-md">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                  Зафиксировано
                </span>
              </template>
            </div>
            <template x-if="getPhotoProofUrls().length > 0">
              <div class="space-y-2">
                <div class="grid grid-cols-3 gap-2">
                  <template x-for="(url, idx) in getPhotoProofUrls()" :key="url">
                    <div class="relative group rounded-xl overflow-hidden border border-border bg-surface2 aspect-square flex items-center justify-center cursor-pointer" @click="openProofPhotoModal(url)">
                      <img :src="url" alt="Фото" class="w-full h-full object-cover">
                      <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="text-[10px] text-white bg-black/60 px-1.5 py-0.5 rounded backdrop-blur-sm">Увеличить</span>
                      </div>
                      <button type="button"
                              x-show="canManageProofPhoto()"
                              @click.stop="promptDeleteProof(url)"
                              class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/70 hover:bg-error text-white flex items-center justify-center shadow transition-colors"
                              title="Удалить фото">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                  </template>

                  <template x-if="canManageProofPhoto() && getPhotoProofUrls().length < 3">
                    <div class="rounded-xl border border-dashed border-border bg-surface/50 hover:bg-surface2/60 transition-colors aspect-square flex flex-col items-center justify-center p-1 text-center">
                      <div class="flex items-center gap-1">
                        <button type="button" @click="openCamera()" class="w-7 h-7 rounded-lg bg-surface2 flex items-center justify-center text-primary hover:bg-surface" title="С камеры">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /></svg>
                        </button>
                        <label class="w-7 h-7 rounded-lg bg-surface2 flex items-center justify-center text-primary hover:bg-surface cursor-pointer" title="Из файла">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                          <input type="file" multiple accept="image/*" class="hidden" @change="uploadDeliveryProof($event)">
                        </label>
                      </div>
                      <span class="text-[9px] text-fg-subtle mt-0.5 font-medium">Добавить</span>
                    </div>
                  </template>
                </div>

                <div class="flex items-center justify-between text-xs text-fg-subtle pt-0.5">
                  <span x-show="getPhotoProofAt()" x-text="'Снято: ' + formatDate(getPhotoProofAt())"></span>
                  <div class="flex items-center gap-2" x-show="canManageProofPhoto()">
                    <button type="button" @click="promptDeleteProof(null)" class="text-xs text-error font-medium hover:underline">Удалить все</button>
                  </div>
                </div>
              </div>
            </template>
            <template x-if="!getPhotoProofUrl()">
              <div>
                <template x-if="canManageProofPhoto()">
                  <div class="space-y-2 p-2.5 rounded-xl border transition-all"
                       :class="isDraggingFile ? 'border-dashed border-2 border-primary bg-primary/10 shadow-md ring-2 ring-primary/20' : 'border-transparent'"
                       @dragover.prevent
                       @drop.prevent="uploadDeliveryProof($event)">
                    <div class="grid grid-cols-2 gap-2">
                      <button type="button"
                              @click="openCamera()"
                              class="w-full py-2.5 px-3 bg-primary text-primary-fg rounded-lg font-semibold text-xs flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                        </svg>
                        <span>Открыть камеру</span>
                      </button>

                      <label class="w-full py-2.5 px-3 bg-surface2 text-fg rounded-lg font-medium text-xs flex items-center justify-center gap-1.5 cursor-pointer hover:border-primary/50 active:scale-[0.98] transition-all"
                             @click="checkWindowSizeForUpload($event)">
                        <input type="file" multiple accept="image/*" class="hidden" :disabled="proofUploading" @change="uploadDeliveryProof($event)">
                        <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span>Из галереи</span>
                      </label>
                    </div>
                    <p class="text-[11px] text-fg-subtle text-center">или перетащите фото сюда / вставьте через Ctrl + V</p>

                    <template x-if="proofUploading">
                      <div class="flex items-center justify-center gap-2 py-1 text-xs text-primary font-medium">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Загрузка фото...</span>
                      </div>
                    </template>
                  </div>
                </template>
                <template x-if="!canManageProofPhoto()">
                  <p class="text-xs text-fg-subtle italic py-1">Фотофиксация пока не загружена курьером</p>
                </template>
              </div>
            </template>
          </div>

          <div class="mt-4 pt-3 border-t border-border/60" x-show="canShowSignatureSection()">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs text-fg-subtle font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                </svg>
                Подпись клиента
              </span>
              <template x-if="hasSignature()">
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded-md">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                  Зафиксирована
                </span>
              </template>
              <template x-if="!hasSignature() && requiresSignature()">
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded-md">
                  Ценное отправление (требуется)
                </span>
              </template>
            </div>

            <template x-if="hasSignature()">
              <div class="space-y-2">
                <div class="flex items-center justify-between gap-3 p-2 bg-surface2 rounded-xl border border-border">
                  <div class="flex items-center gap-2.5 min-w-0 cursor-pointer" @click="openProofPhotoModal(getSignatureUrl())">
                    <div class="w-16 h-10 bg-white rounded-lg border border-border flex items-center justify-center p-1 shrink-0 overflow-hidden shadow-sm">
                      <img :src="getSignatureUrl()" alt="Подпись" class="max-w-full max-h-full object-contain" x-on:error="$el.style.display='none'">
                    </div>
                    <div class="min-w-0 text-left">
                      <div class="text-xs font-semibold text-fg truncate">Подпись получателя</div>
                      <div class="text-[10px] text-fg-subtle truncate" x-show="getSignatureAt()" x-text="'Подписано: ' + formatDate(getSignatureAt())"></div>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button"
                            @click="openProofPhotoModal(getSignatureUrl())"
                            class="px-2 py-1 bg-surface hover:bg-surface2 text-fg rounded-md text-[11px] font-medium border border-border">
                      Увеличить
                    </button>
                    <button type="button"
                            x-show="isOwner"
                            @click="promptDeleteSignature()"
                            class="p-1 text-fg-subtle hover:text-error rounded-md"
                            title="Удалить подпись (только владелец)">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </template>

            <template x-if="!hasSignature()">
              <div>
                <template x-if="canManageProofPhoto()">
                  <button type="button"
                          @click="openSignaturePad()"
                          class="w-full py-2.5 px-3 bg-surface2 hover:border-primary/50 text-fg rounded-lg font-medium text-xs flex items-center justify-center gap-2 active:scale-[0.98] transition-all">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    <span>Получить подпись клиента</span>
                  </button>
                </template>
                <template x-if="!canManageProofPhoto()">
                  <p class="text-xs text-fg-subtle italic py-1">Подпись клиента пока не получена</p>
                </template>
              </div>
            </template>
          </div>
        </div>
        <div x-show="editing" class="space-y-3">
          <div class="relative" :class="{'z-30': deliveryTypeDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Тип доставки</label>
            <button @click="openDeliveryTypeDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.delivery_type ? 'border-error' : 'border-border'">
              <span x-text="deliveryTypes.find(dt => dt.value === form.delivery_type)?.label || 'Выберите тип доставки'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="deliveryTypeDropdownOpen" @click.away="deliveryTypeDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <div class="max-h-48 overflow-y-auto">
                <template x-for="dt in deliveryTypes" :key="dt.value">
                  <button @click="form.delivery_type = dt.value; deliveryTypeDropdownOpen = false; clearFieldError('delivery_type')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.delivery_type === dt.value ? 'bg-surface2 text-primary font-medium' : ''">
                    <span x-text="dt.label"></span>
                    <span x-show="form.delivery_type === dt.value" class="text-primary">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.delivery_type" x-text="errors.delivery_type"></p>
          </div>
          <div class="relative" :class="{'z-30': courierDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Курьер</label>
            <template x-if="!form.manager_id && !order?.manager_id">
              <div class="w-full px-3 py-2.5 bg-surface2/50  rounded-lg text-sm text-fg-muted flex items-center justify-between cursor-not-allowed">
                <span>Курьер недоступен (нет менеджера)</span>
                <span class="text-xs text-warning">Сначала назначьте менеджера</span>
              </div>
            </template>
            <template x-if="form.manager_id || order?.manager_id">
              <div>
                <button @click="openCourierDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.delivery_courier_id ? 'border-error' : 'border-border'">
                  <span x-text="form.delivery_courier_id ? (couriers.find(c => c.id === form.delivery_courier_id)?.system_name || (order?.delivery?.[0]?.courier?.id === form.delivery_courier_id ? order?.delivery?.[0]?.courier?.system_name : 'Не назначен')) : 'Не назначен'"></span>
                  <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                  </svg>
                </button>
                <div x-show="courierDropdownOpen" @click.away="courierDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
                  <input type="text" x-model="courierSearch" @input="searchCouriers()" placeholder="Поиск..." class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary" @click.stop>
                  <div class="max-h-40 overflow-y-auto">
                    <button @click="form.delivery_courier_id = ''; courierDropdownOpen = false; onCourierChange(); clearFieldError('delivery_courier_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.delivery_courier_id ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
                      <span>Не назначен</span>
                      <span x-show="!form.delivery_courier_id" class="text-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                      </span>
                    </button>
                    <template x-for="courier in couriers" :key="courier.id">
                      <button @click="form.delivery_courier_id = courier.id; courierDropdownOpen = false; onCourierChange(); clearFieldError('delivery_courier_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.delivery_courier_id === courier.id ? 'bg-surface2 text-primary font-medium' : ''">
                        <span x-text="courier.system_name"></span>
                        <span x-show="form.delivery_courier_id === courier.id" class="text-primary">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                          </svg>
                        </span>
                      </button>
                    </template>
                    <div x-show="couriers.length === 0" class="px-3 py-2 text-sm text-fg-muted">Ничего не найдено</div>
                  </div>
                </div>
              </div>
            </template>
            <p class="text-error text-xs mt-1" x-show="errors.delivery_courier_id" x-text="errors.delivery_courier_id"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Город доставки</label>
            <input type="text" x-model="form.delivery_city" @input="clearFieldError('delivery_city')" :class="inputClass('delivery_city')" placeholder="Например: Минск">
            <p class="text-error text-xs mt-1" x-show="errors.delivery_city" x-text="errors.delivery_city"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Адрес доставки</label>
            <textarea x-model="form.delivery_address" rows="2" @input="clearFieldError('delivery_address')" :class="inputClass('delivery_address', 'resize-none')" placeholder="Если отличается от адреса клиента"></textarea>
            <p class="text-error text-xs mt-1" x-show="errors.delivery_address" x-text="errors.delivery_address"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Комментарий курьеру</label>
            <textarea x-model="form.delivery_courier_comment" rows="2" @input="clearFieldError('delivery_courier_comment')" :class="inputClass('delivery_courier_comment', 'resize-none')"></textarea>
            <p class="text-error text-xs mt-1" x-show="errors.delivery_courier_comment" x-text="errors.delivery_courier_comment"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Пункт самовывоза</label>
            <input type="text" x-model="form.delivery_pickup_point" @input="clearFieldError('delivery_pickup_point')" :class="inputClass('delivery_pickup_point')">
            <p class="text-error text-xs mt-1" x-show="errors.delivery_pickup_point" x-text="errors.delivery_pickup_point"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Стоимость доставки</label>
            <div class="relative">
              <input type="number" step="0.01" min="0" x-model="form.delivery_price" @input="onDeliveryPriceInput($event)" @blur="onPriceBlur(form, 'delivery_price', $event, () => recalculatePrices())" :class="inputClass('delivery_price', 'pr-8 tabular-nums')" placeholder="0">
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-fg-subtle font-medium pointer-events-none">Br</span>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.delivery_price" x-text="errors.delivery_price"></p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs text-fg-subtle mb-1">Дата доставки</label>
              <input type="date" x-model="form.delivery_date" @input="clearFieldError('delivery_date')" :class="inputClass('delivery_date')">
              <p class="text-error text-xs mt-1" x-show="errors.delivery_date" x-text="errors.delivery_date"></p>
            </div>
            <div>
              <label class="block text-xs text-fg-subtle mb-1">Интервал времени</label>
              <input type="text" x-model="form.delivery_slot" placeholder="10:00–14:00" @input="clearFieldError('delivery_slot')" :class="inputClass('delivery_slot')">
              <p class="text-error text-xs mt-1" x-show="errors.delivery_slot" x-text="errors.delivery_slot"></p>
            </div>
          </div>
          <div class="p-3 bg-surface2 rounded-xl">
            <label class="flex items-center justify-between cursor-pointer select-none">
              <div class="space-y-0.5 pr-3">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-semibold text-fg">Ценное отправление</span>
                  <span x-show="form.is_valuable" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/15 text-amber-400">Ценное</span>
                </div>
                <p class="text-[11px] text-fg-subtle">При получении курьер обязан запросить подпись клиента на экране</p>
              </div>
              <div class="relative inline-flex items-center shrink-0">
                <input type="checkbox" x-model="form.is_valuable" class="sr-only">
                <div class="w-11 h-6 rounded-full transition-colors relative"
                     :class="form.is_valuable ? 'bg-amber-500' : 'bg-surface3'">
                  <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-200"
                        :class="form.is_valuable ? 'translate-x-5' : 'translate-x-0'"></span>
                </div>
              </div>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-surface rounded-xl" :class="{'relative z-20': paymentMethodDropdownOpen || paymentStatusDropdownOpen}">
      <button type="button" @click="toggleAccordion('payment')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50 rounded-xl" :class="accordions.payment ? 'rounded-b-none' : ''">
        <h2 class="font-display font-semibold text-sm text-fg">Оплата</h2>
        <div class="flex items-center gap-2 shrink-0">
          <span x-show="!accordions.payment" class="text-xs text-fg-subtle truncate max-w-[140px]" x-text="order?.payment?.[0]?.status?.label || order?.payment?.status?.label || order?.payment?.[0]?.method?.label || ''"></span>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.payment ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </button>
      <div x-show="accordions.payment" class="px-4 pb-4">
        <div x-show="!editing" class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Метод</span>
            <span class="text-sm" x-text="order.payment?.[0]?.method?.label || order.payment?.method?.label || '—'"></span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Статус</span>
            <span class="text-sm" x-text="order.payment?.[0]?.status?.label || order.payment?.status?.label || '—'"></span>
          </div>
          <div x-show="order.payment?.[0]?.transaction_id || order.payment?.transaction_id" class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Транзакция</span>
            <span class="text-sm" x-text="order.payment?.[0]?.transaction_id || order.payment?.transaction_id"></span>
          </div>
          <div x-show="order.payment?.[0]?.amount || order.payment?.amount" class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Сумма</span>
            <span class="text-sm" x-text="formatPrice(order.payment?.[0]?.amount || order.payment?.amount)"></span>
          </div>

          <template x-if="order.extra_data && (order.extra_data.bank || order.extra_data.program || order.extra_data.card_name || order.extra_data.months || order.extra_data.monthly_payment || order.extra_data.initial_payment !== undefined)">
            <div class="mt-3 pt-3 border-t border-border/70 space-y-2">
              <div class="text-[11px] font-semibold text-fg-muted uppercase tracking-wider">
                <span x-text="(order.payment?.[0]?.method?.value === 'credit' || order.payment?.method === 'credit') ? 'Параметры кредита' : 'Параметры рассрочки'"></span>
              </div>
              <div x-show="order.extra_data.bank" class="flex items-center justify-between gap-3">
                <span class="text-xs text-fg-subtle">Банк</span>
                <span class="text-sm font-medium" x-text="order.extra_data.bank"></span>
              </div>
              <div x-show="order.extra_data.program || order.extra_data.card_name" class="flex items-center justify-between gap-3">
                <span class="text-xs text-fg-subtle">Программа / Карта</span>
                <span class="text-sm font-medium" x-text="order.extra_data.program || order.extra_data.card_name"></span>
              </div>
              <div x-show="order.extra_data.months" class="flex items-center justify-between gap-3">
                <span class="text-xs text-fg-subtle">Срок</span>
                <span class="text-sm font-medium" x-text="order.extra_data.months + ' мес.'"></span>
              </div>
              <div x-show="order.extra_data.monthly_payment" class="flex items-center justify-between gap-3">
                <span class="text-xs text-fg-subtle">Ежемесячно</span>
                <span class="text-sm font-medium text-primary" x-text="formatPrice(order.extra_data.monthly_payment)"></span>
              </div>
              <div x-show="order.extra_data.initial_payment !== undefined && order.extra_data.initial_payment !== null && order.extra_data.initial_payment !== ''" class="flex items-center justify-between gap-3">
                <span class="text-xs text-fg-subtle">Первоначальный взнос</span>
                <span class="text-sm font-medium" x-text="formatPrice(order.extra_data.initial_payment)"></span>
              </div>
            </div>
          </template>
        </div>
        <div x-show="editing" class="space-y-3">
          <div class="relative" :class="{'z-30': paymentMethodDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Метод оплаты</label>
            <button @click="openPaymentMethodDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.payment_method ? 'border-error' : 'border-border'">
              <span x-text="form.payment_method ? (paymentMethods.find(pm => pm.value === form.payment_method)?.label || form.payment_method) : 'Не выбран'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="paymentMethodDropdownOpen" @click.away="paymentMethodDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <div class="max-h-48 overflow-y-auto">
                <button @click="form.payment_method = ''; paymentMethodDropdownOpen = false; clearFieldError('payment_method')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.payment_method ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
                  <span>Не выбран</span>
                  <span x-show="!form.payment_method" class="text-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                  </span>
                </button>
                <template x-for="pm in paymentMethods" :key="pm.value">
                  <button @click="form.payment_method = pm.value; paymentMethodDropdownOpen = false; clearFieldError('payment_method')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.payment_method === pm.value ? 'bg-surface2 text-primary font-medium' : ''">
                    <span x-text="pm.label"></span>
                    <span x-show="form.payment_method === pm.value" class="text-primary">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.payment_method" x-text="errors.payment_method"></p>
          </div>
          <div class="relative" :class="{'z-30': paymentStatusDropdownOpen}">
            <label class="block text-xs text-fg-subtle mb-1">Статус оплаты</label>
            <button @click="openPaymentStatusDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm text-left flex items-center justify-between" :class="errors.payment_status ? 'border-error' : 'border-border'">
              <span x-text="form.payment_status ? (paymentStatuses.find(ps => ps.value === form.payment_status)?.label || form.payment_status) : 'Не выбран'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="paymentStatusDropdownOpen" @click.away="paymentStatusDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <div class="max-h-48 overflow-y-auto">
                <button @click="form.payment_status = ''; paymentStatusDropdownOpen = false; clearFieldError('payment_status')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.payment_status ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
                  <span>Не выбран</span>
                  <span x-show="!form.payment_status" class="text-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                  </span>
                </button>
                <template x-for="ps in paymentStatuses" :key="ps.value">
                  <button @click="form.payment_status = ps.value; paymentStatusDropdownOpen = false; clearFieldError('payment_status')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.payment_status === ps.value ? 'bg-surface2 text-primary font-medium' : ''">
                    <span x-text="ps.label"></span>
                    <span x-show="form.payment_status === ps.value" class="text-primary">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors.payment_status" x-text="errors.payment_status"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Транзакция</label>
            <input type="text" x-model="form.payment_transaction_id" @input="clearFieldError('payment_transaction_id')" :class="inputClass('payment_transaction_id')">
            <p class="text-error text-xs mt-1" x-show="errors.payment_transaction_id" x-text="errors.payment_transaction_id"></p>
          </div>
          <div>
            <label class="block text-xs text-fg-subtle mb-1">Сумма</label>
            <input type="number" step="0.01" min="0" x-model="form.payment_amount" @input="clearFieldError('payment_amount')" :class="inputClass('payment_amount')">
            <p class="text-error text-xs mt-1" x-show="errors.payment_amount" x-text="errors.payment_amount"></p>
          </div>
        </div>
      </div>
    </div>

    @if($user && $user->isCourier() && $order && $order->status !== \App\Enums\OrderStatus::COMPLETED && $order->status !== \App\Enums\OrderStatus::CANCELLED)
    <div class="bg-surface rounded-xl p-4">
      <div class="grid grid-cols-2 gap-3">
        <button x-show="order && order.status === 'assigned'" type="button" @click="promptAtWarehouse()" class="btn w-full bg-primary text-primary-fg font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
          </svg>
          В путь
        </button>
        <button x-show="order && order.status === 'at_warehouse'" type="button" @click="showCourierCompleteModal = true" class="btn w-full bg-success text-[oklch(15% 0.01 158)] font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          Доставлен
        </button>
      </div>
    </div>
    @endif

    @if(!$user || !$user->isCourier())
    <div class="bg-surface rounded-xl overflow-hidden" x-show="!isCourier">
      <button type="button" @click="toggleAccordion('comments')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50">
        <div class="flex items-center gap-2">
          <h2 class="font-display font-semibold text-sm text-fg">Комментарии</h2>
          <span class="text-xs text-fg-subtle" x-show="commentsTotal > 0 || comments.length > 0" x-text="'(' + (commentsTotal || comments.length) + ')'"></span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.comments ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </button>
      <div x-show="accordions.comments" class="px-4 pb-4">
        <template x-if="commentsLoading">
          <div class="py-4 flex flex-col items-center justify-center gap-2">
            <div class="w-5 h-5 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
            <p class="text-xs text-fg-muted">Загрузка...</p>
          </div>
        </template>
        <template x-if="!commentsLoading && comments.length === 0">
          <p class="text-fg-subtle text-sm text-center py-4">Нет комментариев</p>
        </template>
        <div class="space-y-3 mb-4">
          <template x-for="comment in comments.slice(0, 5)" :key="comment.id">
            <div class="border-b border-border last:border-b-0 pb-2 last:pb-0">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium" x-text="getCommentAuthorLabel(comment)"></span>
                <div class="flex items-center gap-2">
                  <span class="text-xs text-fg-subtle" x-text="formatDate(comment.created_at)"></span>
                  <template x-if="isOwner">
                    <button type="button" @click.stop="requestDeleteComment(comment)" :disabled="deletingCommentId === comment.id" class="text-fg-muted hover:text-error transition-colors disabled:opacity-50 p-1" aria-label="Удалить комментарий">
                      <svg class="w-3.5 h-3.5 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </template>
                </div>
              </div>
              <div class="text-sm text-fg whitespace-pre-wrap break-words max-h-28 overflow-y-auto overscroll-contain pr-1 scrollable-text" x-text="comment.body"></div>
            </div>
          </template>
        </div>
        <div x-show="commentsTotal > 5 || comments.length > 5" class="mb-4">
          <button type="button" @click="openAllCommentsModal()" class="w-full py-2 px-3 bg-surface2 hover:bg-surface2/80 text-primary border border-border rounded-lg text-xs font-medium flex items-center justify-center gap-1.5 transition-colors">
            <span>Показать больше</span>
            <span class="text-fg-subtle" x-text="'(' + (commentsTotal || comments.length) + ')'"></span>
          </button>
        </div>
        <form x-show="editing" @submit.prevent="addComment" class="space-y-2">
          <div>
            <textarea x-model="newCommentBody"
                      @input="commentError = ''"
                      rows="2"
                      maxlength="2000"
                      class="w-full bg-bg border rounded-lg p-3 text-sm text-fg placeholder:text-fg-muted focus:outline-none transition-colors"
                      :class="commentError ? 'border-error focus:border-error' : 'border-border focus:border-primary'"
                      placeholder="Написать комментарий..."></textarea>
            <p class="text-error text-xs mt-1" x-show="commentError" x-text="commentError"></p>
          </div>
          <button type="submit"
                  :disabled="commentSubmitting"
                  class="btn w-full bg-primary text-primary-fg font-semibold text-sm rounded-lg active:scale-[0.98] transition-transform disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!commentSubmitting">Отправить</span>
            <span x-show="commentSubmitting">Отправка...</span>
          </button>
        </form>
      </div>
    </div>
    @endif

    @if($user && $user->isOwner())
    <div class="bg-surface rounded-xl overflow-hidden" x-show="isOwner">
      <button type="button" @click="toggleAccordion('history')" class="w-full flex items-center justify-between p-4 text-left select-none transition-colors hover:bg-surface2/50">
        <div class="flex items-center gap-2">
          <h2 class="font-display font-semibold text-sm text-fg">История изменений</h2>
          <span class="text-xs text-fg-subtle" x-show="historyTotal > 0 || history.length > 0" x-text="'(' + (historyTotal || history.length) + ')'"></span>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
          <a :href="'/tma/audit?order_number=' + (order?.id || orderId)" @click.stop class="text-xs px-2.5 py-1 rounded bg-surface2 hover:bg-surface2/80 text-primary font-medium transition-colors">В аудит</a>
          <svg class="w-4 h-4 text-fg-muted transition-transform duration-200" :class="accordions.history ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </div>
      </button>
      <div x-show="accordions.history" class="px-4 pb-4">
        <template x-if="historyLoading">
          <div class="py-4 flex flex-col items-center justify-center gap-2">
            <div class="w-5 h-5 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
            <p class="text-xs text-fg-muted">Загрузка...</p>
          </div>
        </template>
        <template x-if="!historyLoading && history.length === 0">
          <p class="text-fg-subtle text-sm text-center py-4">Нет записей</p>
        </template>
        <div class="space-y-3">
          <template x-for="log in history" :key="log.id">
            <a :href="'/tma/audit/' + log.id" class="block border-b border-border last:border-b-0 pb-2 last:pb-0 active:opacity-80 transition-opacity">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium" :class="getActionColor(log.action_type)" x-text="getActionLabel(log.action_type)"></span>
                <span class="text-xs text-fg-subtle" x-text="formatDate(log.created_at)"></span>
              </div>
              <div class="text-xs text-fg-muted" x-text="log.actor?.system_name || 'Система'"></div>
              <template x-if="log.field_name">
                <div class="text-xs text-fg-subtle mt-1 truncate">
                  <span x-text="log.field_name"></span>:
                  <span x-text="log.old_value_display ?? log.old_value"></span> → <span x-text="log.new_value_display ?? log.new_value"></span>
                </div>
              </template>
            </a>
          </template>
        </div>
        <div x-show="!historyLoading && (historyTotal > 5 || historyTotal > history.length)" class="mt-4 pt-3 border-t border-border">
          <a :href="'/tma/audit?order_number=' + (order?.id || orderId)" class="w-full py-2 px-3 bg-surface2 hover:bg-surface2/80 text-primary border border-border rounded-lg text-xs font-medium flex items-center justify-center gap-1.5 transition-colors">
            <span>Смотреть всю историю в аудите</span>
            <span class="text-fg-subtle" x-show="historyTotal > 0" x-text="'(' + historyTotal + ')'"></span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
    @endif

    @if($user && ($user->isOwner() || $user->isManager()))
    <button x-show="canComplete" type="button" @click="promptCompleteOrder()" class="btn w-full bg-success text-white font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
      Завершить заказ
    </button>
    @endif

    @if($user && $user->isManager() && !$user->isOwner())
    <button x-show="!editing && order && !order.manager_id" type="button" @click="acceptOrder" :disabled="acceptingOrder" class="btn w-full bg-success text-white font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-50">
      <svg x-show="!acceptingOrder" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
      <svg x-show="acceptingOrder" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" stroke-opacity="0.25" stroke-width="4"></circle>
        <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75" stroke-width="4" stroke-linecap="round"></path>
      </svg>
      <span x-text="acceptingOrder ? 'Принятие...' : 'Принять заказ'"></span>
    </button>
    @endif

    <template x-if="canEdit && !editing">
      <button type="button" @click="startEdit" class="btn w-full bg-primary text-primary-fg font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
        </svg>
        Редактировать
      </button>
    </template>

    @if($user && ($user->isOwner() || $user->isManager()))
    <button x-show="canCancel" type="button" @click="promptCancelOrder()" class="btn w-full bg-error text-white font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
      Отменить заказ
    </button>
    @endif
  </div>
</template>

@Component('tma.partials.modal-sheet', ['show' => 'editing', 'padding' => 'px-4 py-3'])
  <div class="flex gap-3">
    <button type="button" @click="cancelEdit" class="btn flex-1 bg-surface2 text-fg font-semibold text-sm rounded-lg flex items-center justify-center active:scale-[0.98] transition-transform">Отмена</button>
    <button type="button" @click="saveEdit" :disabled="saving" class="btn flex-1 bg-primary text-primary-fg font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-50">
      <span x-text="saving ? 'Сохранение...' : 'Сохранить'"></span>
    </button>
  </div>
@endcomponent

  @include('tma.partials.nav')

  @if(!$user || !$user->isCourier())
  @component('tma.partials.modal', ['show' => 'showAllCommentsModal', 'maxWidth' => 'max-w-lg', 'padding' => 'p-4 sm:p-6', 'zIndex' => 'z-50'])
    <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
      <div class="flex items-center gap-2">
        <h3 class="font-display font-bold text-base sm:text-lg">Все комментарии</h3>
        <span class="text-xs text-fg-subtle" x-text="'(' + (commentsTotal || modalComments.length) + ')'"></span>
      </div>
      <button type="button" @click="closeAllCommentsModal()" class="text-fg-muted hover:text-fg p-1 rounded-lg">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="space-y-2 mb-3">
      <div class="relative">
        <input type="text"
               x-model="commentSearch"
               @input="onCommentSearchInput()"
               placeholder="Поиск по комментариям..."
               class="w-full bg-surface2 rounded-lg pl-9 pr-8 py-2 text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary">
        <svg class="w-4 h-4 text-fg-muted absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <button type="button"
                x-show="commentSearch"
                @click="commentSearch = ''; onCommentSearchInput()"
                class="absolute right-2.5 top-2.5 text-fg-muted hover:text-fg">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="relative w-full" :class="{'z-30': commentPersonnelDropdownOpen}">
        <button @click="openCommentPersonnelDropdown()" type="button" class="w-full px-3 py-2 bg-surface2 rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span x-text="getCommentPersonnelFilterLabel()"></span>
          <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="commentPersonnelDropdownOpen" @click.away="commentPersonnelDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <button @click="commentPersonnelFilter = ''; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="!commentPersonnelFilter ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span>Все авторы / персонал</span>
              <span x-show="!commentPersonnelFilter" class="text-primary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <div class="px-3 py-1 text-[10px] font-semibold text-fg-subtle uppercase tracking-wider bg-surface2/50">Роли</div>
            <button @click="commentPersonnelFilter = 'role:owner'; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-1.5 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="commentPersonnelFilter === 'role:owner' ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span>Администраторы (Owner)</span>
              <span x-show="commentPersonnelFilter === 'role:owner'" class="text-primary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <button @click="commentPersonnelFilter = 'role:manager'; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-1.5 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="commentPersonnelFilter === 'role:manager' ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span>Менеджеры</span>
              <span x-show="commentPersonnelFilter === 'role:manager'" class="text-primary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <button @click="commentPersonnelFilter = 'role:courier'; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-1.5 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="commentPersonnelFilter === 'role:courier' ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span>Курьеры</span>
              <span x-show="commentPersonnelFilter === 'role:courier'" class="text-primary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <button @click="commentPersonnelFilter = 'role:client'; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-1.5 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="commentPersonnelFilter === 'role:client' ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span>Клиент</span>
              <span x-show="commentPersonnelFilter === 'role:client'" class="text-primary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <button @click="commentPersonnelFilter = 'role:system'; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-1.5 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="commentPersonnelFilter === 'role:system' ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span>Система</span>
              <span x-show="commentPersonnelFilter === 'role:system'" class="text-primary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <template x-if="commentPersonnelList.length > 0">
              <div>
                <div class="px-3 py-1 text-[10px] font-semibold text-fg-subtle uppercase tracking-wider bg-surface2/50">Сотрудники</div>
                <template x-for="p in commentPersonnelList" :key="p.id">
                  <button @click="commentPersonnelFilter = p.id; commentPersonnelDropdownOpen = false; onCommentPersonnelChange()" type="button" class="w-full px-3 py-1.5 text-xs text-left hover:bg-surface2 flex items-center justify-between" :class="commentPersonnelFilter === p.id ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                    <span x-text="p.system_name + (p.role ? ' (' + p.role + ')' : '')"></span>
                    <span x-show="commentPersonnelFilter === p.id" class="text-primary">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                      </svg>
                    </span>
                  </button>
                </template>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div class="max-h-[60vh] overflow-y-auto space-y-3 pr-1" @scroll.passive="handleModalCommentsScroll($event)">
      <template x-if="modalCommentsLoading">
        <div class="py-6 flex flex-col items-center justify-center gap-2">
          <div class="w-6 h-6 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
          <p class="text-xs text-fg-muted">Загрузка комментариев...</p>
        </div>
      </template>

      <template x-if="!modalCommentsLoading && modalComments.length === 0">
        <p class="text-fg-subtle text-sm text-center py-6">Комментарии не найдены</p>
      </template>

      <template x-for="comment in modalComments" :key="comment.id">
        <div class="border-b border-border last:border-b-0 pb-2.5 last:pb-0">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium" x-text="getCommentAuthorLabel(comment)"></span>
            <div class="flex items-center gap-2">
              <span class="text-xs text-fg-subtle" x-text="formatDate(comment.created_at)"></span>
              <template x-if="isOwner">
                <button type="button" @click.stop="requestDeleteComment(comment)" :disabled="deletingCommentId === comment.id" class="text-fg-muted hover:text-error transition-colors disabled:opacity-50 p-1" aria-label="Удалить комментарий">
                  <svg class="w-3.5 h-3.5 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                  </svg>
                </button>
              </template>
            </div>
          </div>
          <div class="text-sm text-fg whitespace-pre-wrap break-words max-h-36 overflow-y-auto overscroll-contain pr-1 scrollable-text" x-text="comment.body"></div>
        </div>
      </template>

      <template x-if="modalCommentsLoadingMore">
        <div class="py-2 text-center text-xs text-fg-subtle flex items-center justify-center gap-2">
          <div class="w-4 h-4 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
          <span>Загрузка ещё...</span>
        </div>
      </template>
    </div>

    <div class="pt-3 border-t border-border mt-3 flex justify-end">
      <button type="button" @click="closeAllCommentsModal()" class="px-4 py-2 bg-surface2 text-fg rounded-lg text-sm font-medium">Закрыть</button>
    </div>
  @endcomponent
  @endif

  @include('tma.partials.nav')

  @component('tma.partials.modal', ['show' => 'showCourierCompleteModal'])
    <h3 class="font-display font-bold text-lg mb-2">Подтвердить доставку</h3>
    <p class="text-fg-muted text-sm mb-3">Вы уверены, что заказ доставлен?</p>

    <template x-if="requiresSignature()">
      <div class="mb-3 p-2.5 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-start gap-2 text-left">
        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <div class="text-xs">
          <div class="font-semibold text-amber-500">Ценное отправление</div>
          <div class="text-fg-subtle text-[11px]">Для завершения заказа требуется подпись получателя</div>
        </div>
      </div>
    </template>

    <div class="mb-3 p-3 bg-surface2/60 rounded-xl text-left">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-fg flex items-center gap-1.5">
          <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
          </svg>
          Подпись клиента
        </span>
        <template x-if="hasSignature()">
          <span class="text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded">Подписано</span>
        </template>
        <template x-if="!hasSignature() && requiresSignature()">
          <span class="text-[11px] font-semibold text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded">Обязательно</span>
        </template>
      </div>

      <template x-if="hasSignature()">
        <div class="flex items-center justify-between gap-3 p-2 bg-surface rounded-lg border border-border">
          <div class="flex items-center gap-2.5 min-w-0 cursor-pointer" @click="openProofPhotoModal(getSignatureUrl())">
            <div class="w-14 h-9 bg-white rounded border border-border flex items-center justify-center p-0.5 shrink-0 overflow-hidden">
              <img :src="getSignatureUrl()" alt="Подпись" class="max-w-full max-h-full object-contain" x-on:error="$el.style.display='none'">
            </div>
            <div class="min-w-0 text-left">
              <div class="text-xs font-medium text-fg truncate">Подпись зафиксирована</div>
              <div class="text-[10px] text-fg-subtle truncate" x-show="getSignatureAt()" x-text="'Подписано: ' + formatDate(getSignatureAt())"></div>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0" x-show="canManageProofPhoto()">
            <button type="button" @click="openSignaturePad()" class="px-2 py-1 bg-surface2 hover:bg-surface text-fg rounded text-[11px] font-medium border border-border">
              Переписать
            </button>
          </div>
        </div>
      </template>

      <template x-if="!hasSignature()">
        <button type="button"
                @click="openSignaturePad()"
                class="w-full py-2 px-2.5 bg-surface hover:border-primary/50 text-fg rounded-lg font-medium text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-all">
          <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
          </svg>
          <span>Распишитесь на экране</span>
        </button>
      </template>
    </div>

    <div class="mb-4 p-3 bg-surface2/60 rounded-xl text-left">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-fg flex items-center gap-1.5">
          <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
          </svg>
          Фотофиксация вручения
        </span>
        <template x-if="getPhotoProofUrl()">
          <span class="text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded">Готово</span>
        </template>
      </div>

      <template x-if="getPhotoProofUrls().length > 0">
        <div class="space-y-2">
          <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <template x-for="(url, idx) in getPhotoProofUrls()" :key="url">
              <div class="relative w-14 h-14 rounded-lg overflow-hidden shrink-0 group">
                <img :src="url" alt="Фото" class="w-full h-full object-cover cursor-pointer" @click="openProofPhotoModal(url)">
                <button type="button"
                        x-show="canManageProofPhoto()"
                        @click.stop="promptDeleteProof(url)"
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
            <span x-text="'Прикреплено: ' + getPhotoProofUrls().length + ' из 3'"></span>
            <template x-if="canManageProofPhoto() && getPhotoProofUrls().length < 3">
              <div class="flex items-center gap-2">
                <button type="button" @click="openCamera()" class="text-primary hover:underline font-medium text-xs">Камера</button>
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

      <template x-if="getPhotoProofUrls().length === 0">
        <div>
          <template x-if="canManageProofPhoto()">
            <div class="space-y-2">
              <div class="grid grid-cols-2 gap-2">
                <button type="button"
                        @click="openCamera()"
                        class="w-full py-2 px-2.5 bg-primary text-primary-fg rounded-lg font-semibold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                  </svg>
                  <span>Камера</span>
                </button>

                <label class="w-full py-2 px-2.5 bg-surface text-fg rounded-lg font-medium text-xs flex items-center justify-center gap-1.5 cursor-pointer hover:border-primary/50 active:scale-95 transition-all"
                       @click="checkWindowSizeForUpload($event)">
                  <input type="file" multiple accept="image/*" class="hidden" :disabled="proofUploading" @change="uploadDeliveryProof($event)">
                  <svg class="w-3.5 h-3.5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                  </svg>
                  <span>Из галереи</span>
                </label>
              </div>
            </div>
          </template>
          <template x-if="!canManageProofPhoto()">
            <p class="text-xs text-fg-subtle italic py-1">Фотофиксация пока не прикреплена курьером</p>
          </template>
        </div>
      </template>
    </div>

    @component('tma.partials.modal-actions')
      <button @click="showCourierCompleteModal = false" :disabled="completingOrder" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium disabled:opacity-50">Нет</button>
      <button @click="confirmCourierCompleteOrder()" :disabled="completingOrder" class="flex-1 px-4 py-2.5 bg-success text-white rounded-lg text-sm font-medium disabled:opacity-50" x-text="completingOrder ? 'Завершение...' : 'Да'"></button>
    @endcomponent
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
      <video x-ref="cameraVideo" playsinline autoplay muted class="w-full h-full object-cover" x-show="!capturedPreviewUrl && !cameraError"></video>

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

  @component('tma.partials.modal', ['show' => 'showItemsModal', 'maxWidth' => 'max-w-lg', 'padding' => 'p-4 sm:p-5'])
    <div class="flex items-center justify-between pb-3 mb-3 border-b border-border">
      <div>
        <h3 class="font-display font-bold text-base text-fg">Товары в заказе</h3>
        <p class="text-xs text-fg-subtle" x-show="itemLines.length" x-text="itemLines.length + ' ' + (itemLines.length === 1 ? 'позиция' : (itemLines.length < 5 ? 'позиции' : 'позиций'))"></p>
      </div>
      <button type="button" @click="showItemsModal = false" class="w-8 h-8 rounded-full bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg active:scale-95 transition-transform" aria-label="Закрыть">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="space-y-3 max-h-[60vh] overflow-y-auto overscroll-contain pr-1">
      <template x-if="itemLines.length === 0">
        <p class="text-fg-subtle text-sm text-center py-6">Состав заказа пуст</p>
      </template>

      <template x-for="(item, index) in itemLines" :key="index">
        <div class="bg-surface2/60 rounded-xl p-3 flex gap-3 items-start select-none"
             :class="(!isCourier && item.product_id) ? 'hover:border-primary/50 cursor-pointer active:scale-[0.99] transition-all' : ''"
             :title="(!isCourier && item.product_id) ? 'Нажмите для просмотра карточки товара' : ''"
             @click="!isCourier && item.product_id && $dispatch('show-product-detail', { productId: item.product_id })">
          <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-lg shrink-0 overflow-hidden bg-surface flex items-center justify-center relative">
            <template x-if="item.image_url">
              <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" x-on:error="$el.style.display='none'">
            </template>
            <div x-show="!item.image_url" class="w-full h-full flex flex-col items-center justify-center text-fg-muted/40 bg-surface">
              <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
              </svg>
            </div>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <h4 class="text-sm font-semibold text-fg leading-snug break-words"
                  :class="(!isCourier && item.product_id) ? 'hover:text-primary transition-colors' : ''"
                  x-text="item.name"></h4>
              <template x-if="item.url">
                <a :href="item.url" @click.stop target="_blank" rel="noopener noreferrer" class="shrink-0 text-primary hover:text-primary-hover p-1" title="Открыть товар на сайте">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                  </svg>
                </a>
              </template>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
              <span x-show="item.variant_title" class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-primary/10 text-primary border border-primary/20" x-text="item.variant_title"></span>
              <span x-show="item.sku" class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-mono bg-surface text-fg-muted" x-text="item.sku"></span>
              <span x-show="item.external_product_id" class="text-[11px] text-fg-subtle" x-text="'ID: ' + item.external_product_id"></span>
            </div>

            <div class="mt-2.5 pt-2 border-t border-border/50 flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5">
                @include('tma.partials.order-item-quantity', ['size' => 'xs'])
                <span class="text-fg-subtle" x-show="item.price !== null" x-text="'× ' + formatPrice(item.price || 0)"></span>
              </div>
              <span class="font-bold text-sm text-fg" x-text="formatPrice(item.subtotal || ((item.price || 0) * (item.qty || 1)))"></span>
            </div>

            <template x-if="!isCourier && item.purchase_price !== null && item.purchase_price !== undefined">
              <p class="text-[11px] text-fg-subtle mt-1" x-text="'Себестоимость: ' + formatPrice(item.purchase_price)"></p>
            </template>

            <template x-if="!isCourier && item.product_id">
              <div class="mt-2 pt-1.5 border-t border-border/40 flex items-center justify-between text-[11px] text-primary">
                <span class="inline-flex items-center gap-1 font-medium">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                  </svg>
                  Карточка товара
                </span>
                <span class="text-[10px] text-fg-subtle">Подробнее →</span>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>

    <div class="pt-3 border-t border-border mt-3 flex items-center justify-between">
      <div>
        <span class="text-xs text-fg-muted block">Итого за товары</span>
        <span class="font-display font-bold text-base text-fg" x-text="formatPrice(order?.total_price || 0)"></span>
      </div>
      <button type="button" @click="showItemsModal = false" class="px-5 py-2.5 bg-surface2 text-fg rounded-lg text-sm font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
        Закрыть
      </button>
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
@endsection
