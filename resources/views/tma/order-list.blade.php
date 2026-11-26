@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-6">
  <div>
    <p class="text-fg-subtle text-xs">Заказы</p>
    <h1 class="font-display font-bold text-xl" x-text="(ordersTotal || orders.length) + ' ' + pluralize(ordersTotal || orders.length, 'заказ', 'заказа', 'заказов')"></h1>
  </div>
  <div class="flex items-center gap-2">
    @if(!$user || !$user->isCourier())
    <button type="button"
            @click="toggleSelectionMode()"
            class="px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors"
            :class="selectionMode ? 'bg-primary text-primary-fg shadow-sm' : 'bg-surface2 text-fg-muted hover:text-fg'">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
      <span x-text="selectionMode ? 'Готово' : 'Выбрать'"></span>
    </button>
    <a href="/tma/new" class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-primary-fg shadow-sm active:scale-95 transition-transform">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
      </svg>
    </a>
    @endif
  </div>
</div>

<div class="mb-3 flex items-center gap-2">
  <div class="relative flex-1">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
    </svg>
    <input
      type="text"
      x-model="orderSearch"
      @input="onOrderSearch()"
      placeholder="Поиск..."
      class="w-full pl-10 pr-4 py-2.5 bg-surface2 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-primary/50 transition"
    >
  </div>

  <button
    type="button"
    @click="openFilterModal()"
    class="relative p-2.5 rounded-xl text-xs font-semibold flex items-center justify-center transition-colors shrink-0"
    :class="hasActiveFilters ? 'bg-primary/15 text-primary' : 'bg-surface2 text-fg-subtle hover:text-fg'"
    title="Фильтры"
  >
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
    </svg>
    <span
      x-show="hasActiveFilters"
      x-text="activeFiltersCount"
      class="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-primary text-primary-fg text-[10px] font-bold flex items-center justify-center shadow-sm"
      style="display: none;"
    ></span>
  </button>

  <div class="relative shrink-0" @click.away="sortDropdownOpen = false">
    <button
      type="button"
      @click="sortDropdownOpen = !sortDropdownOpen"
      class="p-2.5 rounded-xl text-xs font-semibold flex items-center justify-center transition-colors shrink-0"
      :class="sortDropdownOpen ? 'bg-surface3 text-primary' : 'bg-surface2 text-fg-subtle hover:text-fg'"
      title="Сортировка"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/>
      </svg>
    </button>

    <div
      x-show="sortDropdownOpen"
      x-transition
      class="absolute right-0 mt-2 w-56 bg-surface rounded-xl shadow-xl z-30 py-1.5 overflow-hidden text-sm"
      style="display: none;"
    >
      <div class="px-3 py-1.5 text-[10px] uppercase font-semibold text-fg-subtle tracking-wider">Сортировка</div>
      <button
        type="button"
        @click="setSort('created_at', 'desc')"
        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-surface2 transition-colors"
        :class="sortBy === 'created_at' && sortOrder === 'desc' ? 'text-primary font-medium' : 'text-fg'"
      >
        <span>Сначала новые</span>
        <svg x-show="sortBy === 'created_at' && sortOrder === 'desc'" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </button>
      <button
        type="button"
        @click="setSort('created_at', 'asc')"
        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-surface2 transition-colors"
        :class="sortBy === 'created_at' && sortOrder === 'asc' ? 'text-primary font-medium' : 'text-fg'"
      >
        <span>Сначала старые</span>
        <svg x-show="sortBy === 'created_at' && sortOrder === 'asc'" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </button>
      <button
        type="button"
        @click="setSort('total_price', 'desc')"
        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-surface2 transition-colors"
        :class="sortBy === 'total_price' && sortOrder === 'desc' ? 'text-primary font-medium' : 'text-fg'"
      >
        <span>Сумма: по убыванию</span>
        <svg x-show="sortBy === 'total_price' && sortOrder === 'desc'" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </button>
      <button
        type="button"
        @click="setSort('total_price', 'asc')"
        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-surface2 transition-colors"
        :class="sortBy === 'total_price' && sortOrder === 'asc' ? 'text-primary font-medium' : 'text-fg'"
      >
        <span>Сумма: по возрастанию</span>
        <svg x-show="sortBy === 'total_price' && sortOrder === 'asc'" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </button>
      <button
        type="button"
        @click="setSort('updated_at', 'desc')"
        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-surface2 transition-colors"
        :class="sortBy === 'updated_at' && sortOrder === 'desc' ? 'text-primary font-medium' : 'text-fg'"
      >
        <span>Недавно обновленные</span>
        <svg x-show="sortBy === 'updated_at' && sortOrder === 'desc'" class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- Active Filter Badges -->
<div x-show="hasActiveFilters" x-transition class="flex flex-wrap items-center gap-1.5 mb-3" style="display: none;">
  <template x-if="filterSiteId">
    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs bg-primary/10 border border-primary/20 text-primary">
      <span class="text-fg-subtle">Сайт:</span>
      <span class="font-medium" x-text="activeSiteName"></span>
      <button type="button" @click="clearSiteFilter()" class="hover:text-primary-fg hover:bg-primary/30 rounded p-0.5 ml-0.5">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </span>
  </template>

  <template x-if="filterDatePreset || filterDateFrom || filterDateTo">
    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs bg-primary/10 border border-primary/20 text-primary">
      <span class="text-fg-subtle">Дата:</span>
      <span class="font-medium" x-text="activeDateLabel"></span>
      <button type="button" @click="clearDateFilter()" class="hover:text-primary-fg hover:bg-primary/30 rounded p-0.5 ml-0.5">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </span>
  </template>

  <template x-if="filterManagerId">
    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs bg-primary/10 border border-primary/20 text-primary">
      <span class="text-fg-subtle">Менеджер:</span>
      <span class="font-medium" x-text="activeManagerName"></span>
      <button type="button" @click="clearManagerFilter()" class="hover:text-primary-fg hover:bg-primary/30 rounded p-0.5 ml-0.5">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </span>
  </template>

  <template x-if="filterCourierId">
    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs bg-primary/10 border border-primary/20 text-primary">
      <span class="text-fg-subtle">Курьер:</span>
      <span class="font-medium" x-text="activeCourierName"></span>
      <button type="button" @click="clearCourierFilter()" class="hover:text-primary-fg hover:bg-primary/30 rounded p-0.5 ml-0.5">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </span>
  </template>

  <template x-if="filterMinPrice || filterMaxPrice">
    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs bg-primary/10 border border-primary/20 text-primary">
      <span class="text-fg-subtle">Сумма:</span>
      <span class="font-medium" x-text="activePriceLabel"></span>
      <button type="button" @click="clearPriceFilter()" class="hover:text-primary-fg hover:bg-primary/30 rounded p-0.5 ml-0.5">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </span>
  </template>

  <button
    type="button"
    @click="resetFilters()"
    class="text-xs text-fg-subtle hover:text-fg underline ml-1 px-1 py-0.5"
  >
    Сбросить все
  </button>
</div>

<!-- Customer Filter Banner -->
<div x-show="customerId" x-transition class="mb-4 bg-primary/10 border border-primary/30 rounded-xl p-3 flex items-center justify-between shadow-sm">
  <div class="flex items-center gap-2.5 min-w-0">
    <div class="w-8 h-8 rounded-lg bg-primary/20 text-primary flex items-center justify-center shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
      </svg>
    </div>
    <div class="min-w-0">
      <p class="text-[10px] text-fg-subtle uppercase tracking-wider font-semibold">Фильтр по клиенту</p>
      <p class="text-xs font-bold text-fg truncate" x-text="customerName || ('Клиент #' + customerId)"></p>
    </div>
  </div>
  <button @click="clearCustomerFilter()" class="px-2.5 py-1.5 text-xs font-semibold bg-surface rounded-xl text-fg-muted hover:text-fg hover:border-border/80 flex items-center gap-1 active:scale-95 transition-all shrink-0">
    <span>Сбросить</span>
    <svg class="w-3.5 h-3.5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
    </svg>
  </button>
</div>

<div class="flex gap-2 mb-4 overflow-x-auto pb-2 scrollbar-none">
  @if($user && $user->isCourier())
    <button @click="setFilter('')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === '' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Все</button>
    <button @click="setFilter('assigned')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'assigned' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Передан курьеру</button>
    <button @click="setFilter('at_warehouse')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'at_warehouse' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">В пути</button>
    <button @click="setFilter('completed')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'completed' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Доставлен</button>
    <button @click="setFilter('cancelled')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'cancelled' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Отменён</button>
  @else
    <button @click="setFilter('')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === '' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Все</button>
    <button @click="setFilter('created')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'created' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Новые</button>
    <button @click="setFilter('accepted')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'accepted' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Принятые</button>
    <button @click="setFilter('assigned')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'assigned' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Передан курьеру</button>
    <button @click="setFilter('at_warehouse')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'at_warehouse' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">В пути</button>
    <button @click="setFilter('completed')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'completed' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Доставлен</button>
    <button @click="setFilter('cancelled')" class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-colors"
            :class="filter === 'cancelled' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg-muted'">Отменён</button>
  @endif
</div>

<div id="orders-list" :class="isDesktopMode ? 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mb-6' : 'space-y-2 mb-4'">
  <template x-if="ordersLoading && orders.length === 0">
    <div class="py-12 flex flex-col items-center justify-center gap-3">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-xs text-fg-muted">Загрузка...</p>
    </div>
  </template>
  <template x-if="!ordersLoading && orders.length === 0">
    <div class="bg-surface rounded-xl p-8 text-center">
      <p class="text-fg-subtle text-sm">Нет заказов</p>
    </div>
  </template>
  <template x-for="(order, idx) in orders" :key="order.id">
    <div :class="isDesktopMode ? ('bg-surface rounded-2xl p-4 transition-colors ' + (isOrderSelected(order.id) ? 'ring-1 ring-primary/50 bg-primary/[0.04]' : 'hover:bg-surface2/40')) : ('bg-surface rounded-xl p-4 transition-colors ' + (isOrderSelected(order.id) ? 'ring-1 ring-primary/40 bg-primary/[0.06]' : 'active:bg-surface2/50'))">
      <div class="flex items-start gap-3">
          @if(!$user || !$user->isCourier())
          <!-- Checkbox when in selection mode or when any orders are selected -->
          <button type="button"
                  x-show="selectionMode || selectedOrderIds.length > 0"
                  @click.stop.prevent="toggleSelectOrder(order.id)"
                  class="mt-0.5 w-5 h-5 rounded-md flex items-center justify-center shrink-0 transition-colors"
                  :class="isOrderSelected(order.id) ? 'bg-primary text-primary-fg shadow-sm' : 'bg-surface2 text-transparent'">
            <svg x-show="isOrderSelected(order.id)" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
          </button>
          @endif

        <div class="flex-1 min-w-0">
          <a :href="'/tma/orders/' + order.id"
             @dblclick.prevent="window.location.href = '/tma/orders/' + order.id + '?edit=1'"
             class="block">
            <div class="flex items-center justify-between mb-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-sm font-medium" x-text="'#' + order.id"></span>
                <span x-show="order.external_id" class="text-xs text-fg-subtle font-mono" x-text="'(#' + order.external_id + ')'"></span>
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
              <span class="text-xs font-medium" :class="getStatusClass(order.status)" x-text="getStatusLabel(order.status)"></span>
            </div>
            @if(!$user || !$user->isCourier())
            <div x-show="order.site" class="mb-2">
              <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface2 text-fg-muted" x-text="formatSite(order.site)"></span>
            </div>
            @endif
            <div class="flex items-center gap-1.5 flex-wrap">
              <p class="text-sm font-medium" x-text="order.customer?.name"></p>
              <template x-if="order.customer?.is_blacklisted">
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-error text-white font-bold uppercase">ЧС</span>
              </template>
            </div>
            <template x-if="order.customer?.tags && order.customer.tags.length">
              <div class="flex items-center gap-1 flex-wrap my-1">
                <template x-for="tag in order.customer.tags" :key="tag">
                  <span class="inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-medium"
                        :class="['невыкуп', 'спам', 'частые отмены', 'отмена'].some(w => tag.toLowerCase().includes(w)) ? 'bg-error/15 text-error border border-error/30' : 'bg-surface2 text-fg-muted'"
                        x-text="tag"></span>
                </template>
              </div>
            </template>
            <p class="text-xs text-fg-muted mb-2" x-text="order.customer?.address"></p>
            <template x-if="getDeliverySchedule(order)">
              <div class="flex items-center gap-1.5 text-xs text-primary mb-2 bg-primary/10 px-2 py-0.5 rounded-md w-fit font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span x-text="getDeliverySchedule(order)"></span>
              </div>
            </template>
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs text-fg-subtle" x-text="formatDate(order.created_at)"></span>
              <span class="text-sm font-display font-bold tabular-nums text-fg-data" x-text="formatPrice(order.total_price)"></span>
            </div>
            <div x-show="order.manager_id" class="text-xs text-fg-subtle">Менеджер: <span x-text="order.manager?.system_name || order.manager_id"></span></div>
          </a>
          @if($user && $user->isManager() && !$user->isOwner())
          <div class="mt-2" x-show="!selectionMode && selectedOrderIds.length === 0">
            <button x-show="!order.manager_id && !acceptingOrderIds.has(order.id)"
                    type="button"
                    @click.prevent="acceptOrder(order.id)"
                    class="btn w-full bg-success text-white font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
              </svg>
              Принять заказ
            </button>
            <template x-if="acceptingOrderIds.has(order.id)">
              <button type="button" disabled class="btn w-full bg-success/60 text-white font-semibold text-sm rounded-lg flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10" stroke-opacity="0.25" stroke-width="4"></circle>
                  <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75" stroke-width="4" stroke-linecap="round"></path>
                </svg>
                Принятие...
              </button>
            </template>
          </div>
          @endif
        </div>
      </div>
    </div>
  </template>
</div>

@if(!$user || !$user->isCourier())
<!-- Floating Bulk Actions Bar -->
<div x-show="selectedOrderIds.length > 0"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4"
     class="fixed bottom-20 left-4 right-4 z-40 bg-surface/95 backdrop-blur-md border border-primary/30 rounded-2xl p-3 shadow-2xl flex flex-col gap-2 max-w-lg mx-auto">
  <div class="flex items-center justify-between px-1">
    <div class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
      <span class="text-xs font-semibold text-fg">Выбрано: <span class="text-primary font-bold" x-text="selectedOrderIds.length"></span></span>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" @click="toggleSelectAll()" class="text-xs text-primary font-medium hover:underline">
        <span x-text="(orders.length > 0 && orders.every(o => selectedOrderIds.includes(o.id))) ? 'Снять все' : 'Выбрать все'"></span>
      </button>
      <button type="button" @click="clearSelection()" class="text-fg-subtle hover:text-fg text-xs ml-2">Отмена</button>
    </div>
  </div>
  <div class="grid gap-2 pt-1 border-t border-border/50" :class="canCancelSelected ? 'grid-cols-3' : 'grid-cols-2'">
    <!-- Dynamic action button: based on selectionAction -->
    <template x-if="selectionAction === 'assign_manager'">
      <button type="button"
              @click="openBulkManagerModal()"
              :disabled="bulkLoading"
              class="px-3 py-2 bg-primary text-primary-fg rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm disabled:opacity-50">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
        </svg>
        <span>Назначить менеджера</span>
      </button>
    </template>

    <template x-if="selectionAction === 'accept'">
      <button type="button"
              @click="submitBulkAccept()"
              :disabled="bulkLoading"
              class="px-3 py-2 bg-success text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm disabled:opacity-50">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
        </svg>
        <span x-show="!bulkLoading">Принять в работу</span>
        <span x-show="bulkLoading">Принятие...</span>
      </button>
    </template>

    <template x-if="selectionAction === 'assign_courier'">
      <button type="button"
              @click="openBulkCourierModal()"
              class="px-3 py-2 bg-primary text-primary-fg rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
        </svg>
        <span>Назначить курьера</span>
      </button>
    </template>

    <button type="button"
            @click="openBulkWarehouseModal()"
            class="px-3 py-2 bg-surface2 hover:bg-surface text-fg rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm">
      <svg class="w-4 h-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
      </svg>
      <span>На склад</span>
    </button>

    <template x-if="canCancelSelected">
      <button type="button"
              @click="openBulkCancelModal()"
              :disabled="bulkLoading"
              class="px-3 py-2 bg-error text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm disabled:opacity-50">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        <span x-show="!bulkLoading">Отменить</span>
        <span x-show="bulkLoading">Отмена...</span>
      </button>
    </template>
  </div>
</div>

@component('tma.partials.modal', ['show' => 'showBulkManagerModal'])
  <h3 class="font-display font-bold text-lg mb-2">Назначить менеджера</h3>
  <p class="text-fg-muted text-sm mb-3">Выберите менеджера для <span class="font-bold text-fg" x-text="selectedOrderIds.length"></span> заказов:</p>
  <div class="mb-4">
    <div class="relative" :class="{'z-30': managerDropdownOpen}">
      <button @click="toggleManagerDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 rounded-xl text-sm text-left flex items-center justify-between text-fg">
        <span x-text="selectedBulkManagerId ? (managers.find(m => m.id === selectedBulkManagerId)?.system_name || 'Выберите менеджера') : 'Выберите менеджера'"></span>
        <svg class="w-4 h-4 text-fg-muted transition-transform" :class="managerDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
      </button>
      <div x-show="managerDropdownOpen"
           @click.away="managerDropdownOpen = false"
           class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
        <input type="text"
               x-model="bulkManagerSearch"
               @input="searchBulkManagers()"
               placeholder="Поиск..."
               class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary"
               @click.stop>
        <div class="max-h-40 overflow-y-auto">
          <template x-for="m in filteredBulkManagers" :key="m.id">
            <button type="button"
                    @click="selectedBulkManagerId = m.id; managerDropdownOpen = false"
                    class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between transition-colors"
                    :class="selectedBulkManagerId === m.id ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
              <span x-text="m.system_name"></span>
              <span x-show="selectedBulkManagerId === m.id" class="text-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
          </template>
          <div x-show="!managersLoading && filteredBulkManagers.length === 0" class="px-3 py-2 text-sm text-fg-muted">Ничего не найдено</div>
          <div x-show="managersLoading" class="px-3 py-2 text-sm text-fg-muted">Загрузка...</div>
        </div>
      </div>
    </div>
  </div>
  @component('tma.partials.modal-actions')
    <button @click="showBulkManagerModal = false" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium">Отмена</button>
    <button @click="submitBulkAssignManager()" :disabled="bulkLoading || !selectedBulkManagerId" class="flex-1 px-4 py-2.5 bg-primary text-primary-fg rounded-lg text-sm font-medium disabled:opacity-50">
      <span x-show="!bulkLoading">Назначить</span>
      <span x-show="bulkLoading">Сохранение...</span>
    </button>
  @endcomponent
@endcomponent

@component('tma.partials.modal', ['show' => 'showBulkCourierModal'])
  <h3 class="font-display font-bold text-lg mb-2">Назначить курьера</h3>
  <p class="text-fg-muted text-sm mb-4">Выберите курьера для <span class="font-bold text-fg" x-text="selectedOrderIds.length"></span> заказов:</p>
  <div class="mb-4">
    <template x-if="couriersLoading">
      <div class="py-2 flex items-center justify-center gap-2">
        <div class="w-4 h-4 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
        <p class="text-xs text-fg-subtle">Загрузка курьеров...</p>
      </div>
    </template>
    <template x-if="!couriersLoading && couriers.length === 0">
      <p class="text-xs text-fg-subtle py-2 text-center">Нет активных курьеров</p>
    </template>
    <template x-if="!couriersLoading && couriers.length > 0">
      <div class="relative" :class="{'z-30': courierDropdownOpen}">
        <button @click="toggleCourierDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 rounded-lg text-sm text-left flex items-center justify-between text-fg">
          <span x-text="selectedBulkCourierId ? (couriers.find(c => c.id === selectedBulkCourierId)?.system_name || 'Выберите курьера') : 'Выберите курьера'"></span>
          <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="courierDropdownOpen"
             @click.away="courierDropdownOpen = false"
             class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <input type="text"
                 x-model="bulkCourierSearch"
                 placeholder="Поиск..."
                 class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary"
                 @click.stop>
          <div class="max-h-40 overflow-y-auto">
            <template x-for="c in filteredBulkCouriers" :key="c.id">
              <button type="button"
                      @click="selectedBulkCourierId = c.id; courierDropdownOpen = false"
                      class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between transition-colors"
                      :class="selectedBulkCourierId === c.id ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="c.system_name"></span>
                <span x-show="selectedBulkCourierId === c.id" class="text-primary">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
            <div x-show="filteredBulkCouriers.length === 0" class="px-3 py-2 text-sm text-fg-muted">Ничего не найдено</div>
          </div>
        </div>
      </div>
    </template>
  </div>
  @component('tma.partials.modal-actions')
    <button @click="showBulkCourierModal = false" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium">Отмена</button>
    <button @click="submitBulkAssign()" :disabled="bulkLoading || !selectedBulkCourierId" class="flex-1 px-4 py-2.5 bg-primary text-primary-fg rounded-lg text-sm font-medium disabled:opacity-50">
      <span x-show="!bulkLoading">Назначить</span>
      <span x-show="bulkLoading">Сохранение...</span>
    </button>
  @endcomponent
@endcomponent
@endif

@component('tma.partials.modal', ['show' => 'showFilterModal'])
  <div class="flex items-center justify-between mb-4">
    <h3 class="font-display font-bold text-lg">Фильтры заказов</h3>
    <button type="button" @click="showFilterModal = false" class="text-fg-muted hover:text-fg p-1">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div class="space-y-4 max-h-[65vh] overflow-y-auto pr-1 pb-24">
    <!-- Период дат -->
    <div>
      <label class="block text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">Период</label>
      <div class="grid grid-cols-4 gap-2 mb-2">
        <button
          type="button"
          @click="setDatePreset('today')"
          class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition-colors"
          :class="filterDatePreset === 'today' ? 'bg-primary text-primary-fg border-primary' : 'bg-surface2 border-border text-fg hover:bg-surface2/80'"
        >Сегодня</button>
        <button
          type="button"
          @click="setDatePreset('yesterday')"
          class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition-colors"
          :class="filterDatePreset === 'yesterday' ? 'bg-primary text-primary-fg border-primary' : 'bg-surface2 border-border text-fg hover:bg-surface2/80'"
        >Вчера</button>
        <button
          type="button"
          @click="setDatePreset('week')"
          class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition-colors"
          :class="filterDatePreset === 'week' ? 'bg-primary text-primary-fg border-primary' : 'bg-surface2 border-border text-fg hover:bg-surface2/80'"
        >7 дней</button>
        <button
          type="button"
          @click="setDatePreset('month')"
          class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition-colors"
          :class="filterDatePreset === 'month' ? 'bg-primary text-primary-fg border-primary' : 'bg-surface2 border-border text-fg hover:bg-surface2/80'"
        >30 дней</button>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <span class="text-[11px] text-fg-subtle block mb-1">С даты</span>
          <input
            type="date"
            x-model="filterDateFrom"
            @change="onCustomDateChange()"
            class="w-full px-2.5 py-1.5 bg-surface2 rounded-lg text-xs text-fg focus:outline-none focus:border-primary"
          >
        </div>
        <div>
          <span class="text-[11px] text-fg-subtle block mb-1">По дату</span>
          <input
            type="date"
            x-model="filterDateTo"
            @change="onCustomDateChange()"
            class="w-full px-2.5 py-1.5 bg-surface2 rounded-lg text-xs text-fg focus:outline-none focus:border-primary"
          >
        </div>
      </div>
    </div>

    <!-- Сайт / Источник -->
    <div class="relative" :class="{'z-30': filterSiteDropdownOpen}">
      <label class="block text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">Сайт / Источник</label>
      <button
        type="button"
        @click="filterSiteDropdownOpen = !filterSiteDropdownOpen; if (filterSiteDropdownOpen) { filterManagerDropdownOpen = false; filterCourierDropdownOpen = false; }"
        class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-left flex items-center justify-between text-fg focus:outline-none focus:border-primary transition-colors"
      >
        <span class="truncate" x-text="activeSiteName || 'Все сайты'"></span>
        <svg class="w-4 h-4 text-fg-muted transition-transform shrink-0 ml-2" :class="filterSiteDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
      </button>

      <div
        x-show="filterSiteDropdownOpen"
        @click.away="filterSiteDropdownOpen = false"
        class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl overflow-hidden"
        style="display: none;"
      >
        <div class="p-2 border-b border-border bg-surface2">
          <input
            type="text"
            x-model="filterSiteSearch"
            placeholder="Поиск сайта..."
            class="w-full px-2.5 py-1.5 bg-surface rounded-xl text-xs text-fg focus:outline-none focus:border-primary"
            @click.stop
          >
        </div>
        <div class="max-h-48 overflow-y-auto py-1">
          <button
            type="button"
            @click="selectFilterSite('')"
            class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
            :class="!filterSiteId ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
          >
            <span>Все сайты</span>
            <svg x-show="!filterSiteId" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </button>

          <template x-for="s in filteredFilterSites" :key="s.id">
            <button
              type="button"
              @click="selectFilterSite(s.id)"
              class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
              :class="String(filterSiteId) === String(s.id) ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
            >
              <span class="truncate" x-text="s.name || s.domain"></span>
              <svg x-show="String(filterSiteId) === String(s.id)" class="w-3.5 h-3.5 text-primary shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </button>
          </template>

          <div x-show="!sitesLoading && filteredFilterSites.length === 0" class="px-3 py-2 text-xs text-fg-muted">Ничего не найдено</div>
          <div x-show="sitesLoading" class="px-3 py-2 text-xs text-fg-muted">Загрузка...</div>
        </div>
      </div>
    </div>

    @if(!$user || !$user->isCourier())
    <!-- Менеджер -->
    <div class="relative" :class="{'z-20': filterManagerDropdownOpen}">
      <label class="block text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">Менеджер</label>
      <button
        type="button"
        @click="filterManagerDropdownOpen = !filterManagerDropdownOpen; if (filterManagerDropdownOpen) { filterSiteDropdownOpen = false; filterCourierDropdownOpen = false; }"
        class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-left flex items-center justify-between text-fg focus:outline-none focus:border-primary transition-colors"
      >
        <span class="truncate" x-text="activeManagerName || 'Все менеджеры'"></span>
        <svg class="w-4 h-4 text-fg-muted transition-transform shrink-0 ml-2" :class="filterManagerDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
      </button>

      <div
        x-show="filterManagerDropdownOpen"
        @click.away="filterManagerDropdownOpen = false"
        class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl overflow-hidden"
        style="display: none;"
      >
        <div class="p-2 border-b border-border bg-surface2">
          <input
            type="text"
            x-model="filterManagerSearch"
            placeholder="Поиск менеджера..."
            class="w-full px-2.5 py-1.5 bg-surface rounded-xl text-xs text-fg focus:outline-none focus:border-primary"
            @click.stop
          >
        </div>
        <div class="max-h-48 overflow-y-auto py-1">
          <button
            type="button"
            @click="selectFilterManager('')"
            class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
            :class="!filterManagerId ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
          >
            <span>Все менеджеры</span>
            <svg x-show="!filterManagerId" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </button>

          <button
            type="button"
            @click="selectFilterManager('unassigned')"
            class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
            :class="filterManagerId === 'unassigned' ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
          >
            <span>Без менеджера</span>
            <svg x-show="filterManagerId === 'unassigned'" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </button>

          <template x-for="m in filteredFilterManagers" :key="m.id">
            <button
              type="button"
              @click="selectFilterManager(m.id)"
              class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
              :class="String(filterManagerId) === String(m.id) ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
            >
              <span class="truncate" x-text="m.system_name"></span>
              <svg x-show="String(filterManagerId) === String(m.id)" class="w-3.5 h-3.5 text-primary shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </button>
          </template>

          <div x-show="!managersLoading && filteredFilterManagers.length === 0" class="px-3 py-2 text-xs text-fg-muted">Ничего не найдено</div>
          <div x-show="managersLoading" class="px-3 py-2 text-xs text-fg-muted">Загрузка...</div>
        </div>
      </div>
    </div>

    <!-- Курьер -->
    <div class="relative" :class="{'z-10': filterCourierDropdownOpen}">
      <label class="block text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">Курьер</label>
      <button
        type="button"
        @click="filterCourierDropdownOpen = !filterCourierDropdownOpen; if (filterCourierDropdownOpen) { filterSiteDropdownOpen = false; filterManagerDropdownOpen = false; }"
        class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-left flex items-center justify-between text-fg focus:outline-none focus:border-primary transition-colors"
      >
        <span class="truncate" x-text="activeCourierName || 'Все курьеры'"></span>
        <svg class="w-4 h-4 text-fg-muted transition-transform shrink-0 ml-2" :class="filterCourierDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
      </button>

      <div
        x-show="filterCourierDropdownOpen"
        @click.away="filterCourierDropdownOpen = false"
        class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl overflow-hidden"
        style="display: none;"
      >
        <div class="p-2 border-b border-border bg-surface2">
          <input
            type="text"
            x-model="filterCourierSearch"
            placeholder="Поиск курьера..."
            class="w-full px-2.5 py-1.5 bg-surface rounded-xl text-xs text-fg focus:outline-none focus:border-primary"
            @click.stop
          >
        </div>
        <div class="max-h-48 overflow-y-auto py-1">
          <button
            type="button"
            @click="selectFilterCourier('')"
            class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
            :class="!filterCourierId ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
          >
            <span>Все курьеры</span>
            <svg x-show="!filterCourierId" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </button>

          <button
            type="button"
            @click="selectFilterCourier('unassigned')"
            class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
            :class="filterCourierId === 'unassigned' ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
          >
            <span>Без курьера</span>
            <svg x-show="filterCourierId === 'unassigned'" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </button>

          <template x-for="c in filteredFilterCouriers" :key="c.id">
            <button
              type="button"
              @click="selectFilterCourier(c.id)"
              class="w-full px-3 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors"
              :class="String(filterCourierId) === String(c.id) ? 'bg-surface2 text-primary font-medium' : 'text-fg'"
            >
              <span class="truncate" x-text="c.system_name"></span>
              <svg x-show="String(filterCourierId) === String(c.id)" class="w-3.5 h-3.5 text-primary shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </button>
          </template>

          <div x-show="!couriersLoading && filteredFilterCouriers.length === 0" class="px-3 py-2 text-xs text-fg-muted">Ничего не найдено</div>
          <div x-show="couriersLoading" class="px-3 py-2 text-xs text-fg-muted">Загрузка...</div>
        </div>
      </div>
    </div>
    @endif

    <!-- Диапазон сумм -->
    <div>
      <label class="block text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">Сумма заказа</label>
      <div class="grid grid-cols-2 gap-2">
        <input
          type="number"
          step="any"
          placeholder="От"
          x-model="filterMinPrice"
          class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg focus:outline-none focus:border-primary"
        >
        <input
          type="number"
          step="any"
          placeholder="До"
          x-model="filterMaxPrice"
          class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg focus:outline-none focus:border-primary"
        >
      </div>
    </div>
  </div>

  @component('tma.partials.modal-actions')
    <button type="button" @click="resetFilters()" class="px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium text-fg hover:bg-surface2/80">
      Сбросить
    </button>
    <button type="button" @click="applyFilters()" class="flex-1 px-4 py-2.5 bg-primary text-primary-fg rounded-lg text-sm font-medium shadow-sm">
      Применить
    </button>
  @endcomponent
@endcomponent

@component('tma.partials.pagination-load-more', [
  'show' => 'ordersHasMore',
  'loading' => 'ordersLoading',
  'hasItems' => 'orders.length > 0',
  'action' => 'loadOrders()',
  'scrollTarget' => 'orders-list'
])
@endcomponent

  @include('tma.partials.nav')
@endsection
