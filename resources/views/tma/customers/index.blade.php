@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-4">
  <div>
    <p class="text-fg-subtle text-xs">Клиенты</p>
    <h1 class="font-display font-bold text-xl" x-text="(customersTotal || customers.length) + ' ' + pluralize(customersTotal || customers.length, 'клиент', 'клиента', 'клиентов')"></h1>
  </div>
  <a href="/tma/leads" class="flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary/20 rounded-xl text-xs font-semibold transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
    </svg>
    Заявки с сайта
  </a>
</div>

<div class="mb-3">
  <div class="relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
    </svg>
    <input
      type="text"
      x-model="search"
      @input="onSearch()"
      placeholder="Поиск по имени, телефону, адресу..."
      class="w-full pl-10 pr-4 py-2.5 bg-surface2 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-primary/50 transition"
    >
  </div>
</div>

<!-- Filters & Sort -->
<div class="flex gap-2 mb-4 overflow-x-auto pb-1 text-xs scrollbar-none">
  <button @click="setSort('latest')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="sort === 'latest' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">
    Свежие
  </button>
  <button @click="setSort('ltv_desc')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="sort === 'ltv_desc' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">
    Топ LTV
  </button>
  <button @click="setSort('orders_desc')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="sort === 'orders_desc' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">
    По заказам
  </button>
  <button @click="toggleRepeatOnly()" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="repeatOnly ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">
    Повторные
  </button>
  <button @click="toggleBlacklistedOnly()" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="blacklistedOnly ? 'bg-error/15 text-error font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">
    🚫 ЧС
  </button>
</div>

<div id="customers-list" :class="isDesktopMode ? 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mb-6' : 'space-y-2 mb-4'">
  <template x-if="loading && customers.length === 0">
    <div class="py-12 flex flex-col items-center justify-center gap-3">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-xs text-fg-muted">Загрузка клиентов...</p>
    </div>
  </template>

  <template x-if="!loading && customers.length === 0">
    <div class="bg-surface rounded-xl p-6 text-center">
      <p class="text-fg-subtle text-sm">Клиенты не найдены</p>
    </div>
  </template>

  <template x-for="(c, idx) in customers" :key="c.id">
    <a :href="'/tma/customers/' + c.id"
       class="block p-4 bg-surface hover:bg-surface2/50 active:bg-surface2/50 transition-colors"
       :class="isDesktopMode ? 'rounded-2xl' : 'rounded-xl'">
      <div class="flex items-start justify-between gap-3 mb-1.5">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5 min-w-0">
            <h2 class="text-sm font-semibold text-fg truncate" x-text="c.name || 'Без имени'"></h2>
            <template x-if="c.is_blacklisted">
              <span class="text-[10px] px-1.5 py-0.2 rounded bg-error text-white font-bold uppercase shrink-0">ЧС</span>
            </template>
          </div>
          <p class="text-xs text-fg-muted mt-0.5" x-text="c.formatted_phone || formatPhone(c.phone) || '—'"></p>
          <template x-if="c.tags && c.tags.length">
            <div class="flex items-center gap-1 flex-wrap mt-1.5">
              <template x-for="tag in c.tags" :key="tag">
                <span class="inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-medium"
                      :class="['невыкуп', 'спам', 'частые отмены', 'отмена'].some(w => tag.toLowerCase().includes(w)) ? 'bg-error/15 text-error' : (tag.toLowerCase() === 'vip' ? 'bg-amber-500/15 text-amber-400 font-semibold' : 'bg-surface2 text-fg-muted')"
                      x-text="tag"></span>
              </template>
            </div>
          </template>
        </div>
        <div class="text-right shrink-0">
          <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-success/15 text-success tabular-nums whitespace-nowrap" x-text="'LTV ' + formatPrice(c.ltv)"></span>
        </div>
      </div>

      <p x-show="c.address" class="text-xs text-fg-subtle mb-2.5 line-clamp-1" x-text="c.address"></p>

      <div class="flex items-center justify-between text-xs pt-1.5 border-t border-white/[0.05]">
        <span class="text-fg-muted text-[11px]" x-text="c.orders_count + ' ' + pluralize(c.orders_count, 'заказ', 'заказа', 'заказов')"></span>
        <span x-show="c.site" class="px-2 py-0.5 rounded bg-surface2 text-[11px] text-fg-muted" x-text="c.site?.domain || c.site?.name"></span>
      </div>
    </a>
  </template>
</div>

@component('tma.partials.pagination-load-more', [
  'show' => 'hasMore',
  'loading' => 'loading',
  'hasItems' => 'customers.length > 0',
  'action' => 'loadMore()',
  'scrollTarget' => 'customers-list'
])
@endcomponent

@include('tma.partials.nav')
@endsection
