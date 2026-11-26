@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between gap-2 mb-4">
  <div class="flex items-center gap-2.5 min-w-0 flex-1">
    <a href="/tma/customers" @click.prevent="goBack()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-surface2 text-fg-muted hover:text-fg active:scale-95 transition-all shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
      </svg>
    </a>
    <div class="min-w-0 flex-1">
      <p class="text-fg-subtle text-xs">Карточка клиента</p>
      <div class="flex items-center gap-1.5 min-w-0">
        <h1 @click="customer?.name && copyText(customer?.name, 'Имя скопировано')"
            :class="customer?.name ? 'cursor-pointer hover:text-primary transition-colors' : ''"
            class="font-display font-bold text-lg truncate"
            x-text="customer?.name || 'Клиент #' + customerId"
            :title="customer?.name ? 'Нажмите, чтобы скопировать имя: ' + customer.name : ''"></h1>
        <template x-if="customer?.is_blacklisted">
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-error text-white font-bold uppercase shrink-0">ЧС</span>
        </template>
      </div>
    </div>
  </div>
  <div class="flex items-center gap-1.5 shrink-0">
    <button @click="copyCustomerInfo(customer)"
            type="button"
            class="p-2 sm:px-2.5 sm:py-1.5 text-xs font-semibold rounded-xl bg-surface2 text-fg-muted hover:text-primary flex items-center gap-1 active:scale-95 transition-all shrink-0"
            title="Скопировать все данные клиента">
      <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
      </svg>
      <span class="hidden sm:inline">Копировать</span>
    </button>
    <button @click="openEditModal()" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-surface2 text-fg hover:text-primary transition-colors shrink-0">
      Изменить
    </button>
  </div>
</div>

<!-- Blacklist Banner -->
<template x-if="customer?.is_blacklisted">
  <div class="bg-error/15 border border-error/40 rounded-2xl p-3.5 mb-4 flex items-start gap-3 text-error shadow-sm">
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
    </svg>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <span class="font-display font-bold text-sm tracking-wide">Клиент в черном списке!</span>
      </div>
      <div class="max-h-20 overflow-y-auto overscroll-contain pr-1 scrollbar-none">
        <p class="text-xs text-fg mt-1 break-words" x-text="customer.blacklist_reason ? 'Причина: ' + customer.blacklist_reason : 'Неблагонадежный клиент (невыкуп, частые отмены, спам)'"></p>
      </div>
    </div>
  </div>
</template>

<!-- Tags -->
<template x-if="customer?.tags && customer.tags.length > 0">
  <div class="mb-4 flex items-center gap-1.5 flex-wrap">
    <span class="text-xs text-fg-subtle">Теги:</span>
    <template x-for="tag in customer.tags" :key="tag">
      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
            :class="['невыкуп', 'спам', 'частые отмены', 'отмена'].some(w => tag.toLowerCase().includes(w)) ? 'bg-error/15 text-error' : (tag.toLowerCase() === 'vip' ? 'bg-amber-500/15 text-amber-400 font-semibold' : 'bg-surface2 text-fg-muted')"
            x-text="tag"></span>
    </template>
  </div>
</template>

<!-- LTV & Stats Grid -->
<div class="grid grid-cols-2 gap-3 mb-4">
  <div class="bg-surface rounded-2xl p-4">
    <p class="text-[11px] text-fg-subtle mb-1">LTV (Выручка)</p>
    <p class="text-xl font-display font-bold text-success tabular-nums" x-text="formatPrice(customer?.ltv ?? 0)"></p>
  </div>
  <div class="bg-surface rounded-2xl p-4">
    <p class="text-[11px] text-fg-subtle mb-1">Всего заказов</p>
    <p class="text-xl font-display font-bold text-fg tabular-nums" x-text="customer?.orders_count ?? 0"></p>
  </div>
</div>

<!-- Contact & Info Card -->
<div class="bg-surface rounded-2xl p-4 mb-4 space-y-2.5">
  <div class="flex items-start justify-between gap-3">
    <span class="text-xs text-fg-subtle shrink-0 pt-0.5">Имя</span>
    <div class="text-right min-w-0 flex-1">
      <button type="button"
              x-show="customer?.name"
              @click="copyText(customer?.name, 'Имя скопировано')"
              class="text-sm font-medium text-fg hover:text-primary active:opacity-75 transition-colors inline-flex items-center justify-end gap-1.5 max-w-full text-right ml-auto"
              title="Нажмите, чтобы скопировать">
        <span class="truncate break-all" x-text="customer?.name"></span>
        <svg class="w-3.5 h-3.5 text-fg-muted hover:text-primary opacity-60 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
        </svg>
      </button>
      <span x-show="!customer?.name" class="text-sm text-right text-fg-muted">—</span>
    </div>
  </div>

  <div class="flex items-start justify-between gap-3">
    <span class="text-xs text-fg-subtle shrink-0 pt-0.5">Телефон</span>
    <div class="flex flex-col items-end gap-1.5 min-w-0 flex-1">
      <div class="flex items-center gap-1 justify-end max-w-full" x-show="customer?.phone">
        <a :href="getTelLink(customer?.phone)"
           class="text-sm text-primary text-right truncate break-all hover:underline active:opacity-70 transition-opacity font-medium"
           x-text="customer?.formatted_phone || formatPhone(customer?.phone)"
           title="Позвонить"></a>
        <button type="button"
                @click="copyText(customer?.formatted_phone || formatPhone(customer?.phone), 'Телефон скопирован')"
                class="p-0.5 text-fg-subtle hover:text-primary active:scale-95 transition-all cursor-pointer shrink-0"
                title="Скопировать телефон">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
          </svg>
        </button>
      </div>
      <span x-show="!customer?.phone" class="text-sm text-right text-fg-muted">—</span>
      <div x-show="customer?.phone" class="flex items-center gap-1 mt-1 justify-end flex-wrap">
        <a :href="'tg://resolve?phone=' + getCleanPhone(customer?.phone)"
           @click.prevent="openMessenger('telegram', customer?.phone)"
           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-[#2AABEE]/15 hover:bg-[#2AABEE]/25 text-[#2AABEE] text-xs font-medium active:scale-95 transition-all shrink-0">
          <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.63 3.73-.53.37-1.02.55-1.45.54-.48-.01-1.4-.27-2.09-.49-.84-.27-1.51-.42-1.45-.88.03-.24.38-.49 1.04-.75 4.07-1.77 6.78-2.94 8.14-3.51 3.86-1.61 4.67-1.89 5.2-1.9.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.19-.04.28z"/>
          </svg>
          <span>Telegram</span>
        </a>
        <a :href="'https://wa.me/' + getCleanPhone(customer?.phone)"
           @click.prevent="openMessenger('whatsapp', customer?.phone)"
           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-[#25D366]/15 hover:bg-[#25D366]/25 text-[#25D366] text-xs font-medium active:scale-95 transition-all shrink-0">
          <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
            <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.41a8.176 8.176 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.188 8.188 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.03-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.34-.76-1.84-.2-.49-.4-.42-.56-.43h-.47c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.12.17 1.78 2.72 4.31 3.81.6.26 1.07.41 1.44.53.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.12-.23-.19-.48-.31z"/>
          </svg>
          <span>WhatsApp</span>
        </a>
        <a :href="getMessengerLink('viber', customer?.phone)"
           @click.prevent="openMessenger('viber', customer?.phone)"
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
      <a x-show="customer?.email"
         :href="'mailto:' + customer?.email"
         class="text-sm text-primary text-right truncate break-all hover:underline active:opacity-70 transition-opacity block"
         x-text="customer?.email"></a>
      <span x-show="!customer?.email" class="text-sm text-right text-fg-muted">—</span>
    </div>
  </div>

  <div class="flex items-start justify-between gap-3">
    <span class="text-xs text-fg-subtle shrink-0 pt-0.5">Адрес доставки</span>
    <div class="text-right min-w-0 flex-1">
      <span x-show="customer?.address"
            @click="copyText(customer?.address, 'Адрес скопирован')"
            class="text-sm text-fg hover:text-primary cursor-pointer active:opacity-70 transition-colors inline-flex items-start justify-end gap-1.5 group max-w-full text-right ml-auto"
            title="Нажмите, чтобы скопировать">
        <span class="break-words [overflow-wrap:anywhere]" x-text="customer?.address"></span>
        <svg class="w-3.5 h-3.5 mt-0.5 text-fg-muted group-hover:text-primary opacity-60 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
        </svg>
      </span>
      <span x-show="!customer?.address" class="text-sm text-right text-fg-muted">—</span>
      <div x-show="customer?.address" class="flex items-center gap-1.5 mt-2 justify-end flex-wrap">
        <span class="text-[11px] text-fg-subtle">Карты:</span>
        <button type="button" @click="openMap('yandex', customer?.address)" class="px-2 py-0.5 rounded-lg bg-surface2 text-fg hover:text-primary text-[11px] active:scale-95 transition-all">Яндекс</button>
        <button type="button" @click="openMap('2gis', customer?.address)" class="px-2 py-0.5 rounded-lg bg-surface2 text-fg hover:text-primary text-[11px] active:scale-95 transition-all">2GIS</button>
        <button type="button" @click="openMap('google', customer?.address)" class="px-2 py-0.5 rounded-lg bg-surface2 text-fg hover:text-primary text-[11px] active:scale-95 transition-all">Google</button>
      </div>
    </div>
  </div>

  <div class="flex items-center justify-between gap-3 pt-1 border-t border-white/[0.05]">
    <span class="text-xs text-fg-subtle">Сайт регистрации</span>
    <span x-show="customer?.site" class="px-2 py-0.5 rounded-md bg-surface2 text-xs text-fg-muted" x-text="customer?.site?.domain || customer?.site?.name"></span>
    <span x-show="!customer?.site" class="text-sm text-right text-fg-muted">—</span>
  </div>

  <div class="pt-2">
    <button @click="copyCustomerInfo(customer)"
            type="button"
            class="w-full py-2.5 px-3 bg-surface2 hover:bg-surface2/80 rounded-xl text-xs font-semibold text-fg flex items-center justify-center gap-2 active:scale-[0.98] transition-all">
      <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
      </svg>
      <span>Скопировать данные клиента</span>
    </button>
  </div>
</div>

<!-- Order History -->
<div class="mb-4">
  <div class="flex items-center justify-between mb-3">
    <div>
      <h2 class="font-display font-semibold text-sm">История заказов</h2>
      <p class="text-[11px] text-fg-subtle" x-show="customer?.orders_count > 5">Показаны последние 5 заказов</p>
    </div>
    <span class="text-xs text-fg-subtle tabular-nums" x-text="(customer?.orders_count ?? customer?.orders?.length ?? 0) + ' шт.'"></span>
  </div>

  <template x-if="!customer?.orders || customer.orders.length === 0">
    <p class="text-fg-subtle text-xs text-center py-6 bg-surface rounded-2xl">Заказов пока нет</p>
  </template>

  <template x-if="customer?.orders && customer.orders.length > 0">
    <div class="space-y-2 mb-3">
      <template x-for="(order, idx) in ((customer?.orders || []).slice(0, 5))" :key="order.id">
        <a :href="'/tma/orders/' + order.id" class="block p-3.5 bg-surface rounded-xl hover:bg-surface2/50 transition-colors">
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-semibold" x-text="'Заказ #' + order.id"></span>
            <span class="text-[11px] px-2 py-0.5 rounded-full font-medium"
                  :class="'bg-' + order.status_color + '/20 text-' + order.status_color"
                  x-text="order.status_label"></span>
          </div>

          <div class="text-xs text-fg-muted mb-2 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
            <template x-for="(item, itemIdx) in (order.items || [])" :key="itemIdx">
              <span class="inline-flex items-center">
                <template x-if="item.product_id">
                  <button type="button"
                          @click.prevent.stop="$dispatch('show-product-detail', { productId: item.product_id })"
                          class="text-fg hover:text-primary underline decoration-dotted decoration-border hover:decoration-primary transition-colors text-left font-medium"
                          :title="'Открыть карточку товара ' + item.name">
                    <span x-text="item.name + ' × ' + item.qty"></span>
                  </button>
                </template>
                <template x-if="!item.product_id">
                  <span x-text="item.name + ' × ' + item.qty"></span>
                </template>
                <span x-show="itemIdx < (order.items.length - 1)" class="text-fg-subtle ml-0.5">,</span>
              </span>
            </template>
          </div>

          <div class="flex items-center justify-between pt-1.5 border-t border-white/[0.05] text-xs">
            <span class="text-fg-subtle" x-text="formatDate(order.created_at)"></span>
            <span class="font-display font-bold text-fg tabular-nums" x-text="formatPrice(order.total_price)"></span>
          </div>
        </a>
      </template>
    </div>
  </template>

  <!-- Button to view all customer orders on the Orders page with filter -->
  <template x-if="(customer?.orders_count || 0) > 5">
    <a :href="'/tma/orders?customer_id=' + customer.id + '&customer_name=' + encodeURIComponent(customer.name || customer.formatted_phone || ('Клиент #' + customer.id))"
       class="w-full py-2.5 px-3 bg-surface hover:bg-surface2 rounded-xl text-xs font-semibold text-fg flex items-center justify-center gap-2 active:scale-[0.98] transition-all group">
      <svg class="w-4 h-4 text-primary group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
      </svg>
      <span>Смотреть все заказы клиента</span>
    </a>
  </template>
</div>

<!-- Edit Modal -->
@component('tma.partials.modal', ['show' => 'showEditModal', 'padding' => 'p-4 sm:p-5', 'maxWidth' => 'max-w-md'])
  <div class="flex items-center justify-between pb-3 border-b border-white/[0.06] mb-3">
    <h3 class="font-display font-bold text-base">Редактирование клиента</h3>
    <button type="button" @click="showEditModal = false" class="text-fg-muted hover:text-fg p-1.5 rounded-lg active:scale-95 transition-all" title="Закрыть">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div class="space-y-3 mb-4 max-h-[55vh] overflow-y-auto pr-1 scrollbar-none">
    <div>
      <label class="text-xs text-fg-subtle mb-1 block">Имя</label>
      <input type="text" x-model="editForm.name" class="w-full px-3 py-2 bg-surface2 rounded-xl text-sm focus:border-primary focus:outline-none">
    </div>
    <div>
      <label class="text-xs text-fg-subtle mb-1 block">Телефон</label>
      <input type="tel" x-model="editForm.phone" class="w-full px-3 py-2 bg-surface2 rounded-xl text-sm focus:border-primary focus:outline-none">
    </div>
    <div>
      <label class="text-xs text-fg-subtle mb-1 block">Email</label>
      <input type="email" x-model="editForm.email" class="w-full px-3 py-2 bg-surface2 rounded-xl text-sm focus:border-primary focus:outline-none">
    </div>
    <div>
      <label class="text-xs text-fg-subtle mb-1 block">Адрес</label>
      <textarea x-model="editForm.address" rows="2" class="w-full px-3 py-2 bg-surface2 rounded-xl text-sm resize-none focus:border-primary focus:outline-none"></textarea>
    </div>

    <div class="pt-2">
      <label class="flex items-center justify-between cursor-pointer select-none p-3 bg-surface2 rounded-xl">
        <span class="text-xs font-semibold text-error">В черном списке (неблагонадежный)</span>
        <div class="relative inline-flex items-center shrink-0">
          <input type="checkbox" x-model="editForm.is_blacklisted" class="sr-only">
          <div class="w-11 h-6 rounded-full transition-colors relative"
               :class="editForm.is_blacklisted ? 'bg-error' : 'bg-surface3'">
            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-200"
                  :class="editForm.is_blacklisted ? 'translate-x-5' : 'translate-x-0'"></span>
          </div>
        </div>
      </label>
    </div>

    <div x-show="editForm.is_blacklisted" class="space-y-1">
      <label class="text-xs text-fg-subtle block">Причина внесения в ЧС</label>
      <input type="text" x-model="editForm.blacklist_reason" placeholder="Например: невыкуп, спам, частые отмены" class="w-full px-3 py-2 bg-surface2 rounded-xl text-sm focus:border-error focus:outline-none">
    </div>

    <div class="pt-2 border-t border-white/[0.06]">
      <label class="text-xs text-fg-subtle mb-1.5 block">Теги клиента</label>
      <div class="flex items-center gap-1.5 flex-wrap mb-2">
        <template x-for="preset in ['невыкуп', 'частые отмены', 'спам', 'vip']" :key="preset">
          <button type="button" @click="toggleTag(preset)"
                  class="px-2.5 py-1 rounded-full text-xs font-medium transition-all"
                  :class="editForm.tags?.includes(preset) 
                    ? (preset === 'vip' ? 'bg-amber-500/20 text-amber-300 font-semibold shadow-sm' : 'bg-error/20 text-error font-semibold shadow-sm') 
                    : 'bg-surface2 text-fg-subtle hover:text-fg'"
                  x-text="preset"></button>
        </template>
      </div>

      <div class="flex items-center gap-2">
        <input type="text" x-model="editForm.tagInput" @keydown.enter.prevent="addCustomTag()" placeholder="Добавить свой тег..." class="flex-1 px-3 py-1.5 bg-surface2 rounded-xl text-xs focus:border-primary focus:outline-none">
        <button type="button" @click="addCustomTag()" class="px-3 py-1.5 bg-surface2 text-fg rounded-xl text-xs font-medium hover:text-primary transition-colors">
          + Тег
        </button>
      </div>

      <template x-if="editForm.tags && editForm.tags.length > 0">
        <div class="flex items-center gap-1.5 flex-wrap mt-2">
          <template x-for="(tag, idx) in editForm.tags" :key="idx">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="['невыкуп', 'спам', 'частые отмены', 'отмена'].some(w => tag.toLowerCase().includes(w)) ? 'bg-error/15 text-error' : (tag.toLowerCase() === 'vip' ? 'bg-amber-500/15 text-amber-400' : 'bg-surface2 text-fg')">
              <span x-text="tag"></span>
              <button type="button" @click="toggleTag(tag)" class="text-fg-subtle hover:text-error ml-0.5">×</button>
            </span>
          </template>
        </div>
      </template>
    </div>
  </div>

  <div class="pt-3">
    <button @click="saveCustomer()" :disabled="saving" class="w-full py-3 bg-primary text-primary-fg rounded-xl text-sm font-semibold disabled:opacity-50 active:scale-[0.98] transition-all shadow-sm" x-text="saving ? 'Сохранение...' : 'Сохранить изменения'"></button>
  </div>
@endcomponent

@include('tma.partials.nav')
@endsection
