@extends('tma.layout')

@section('content')
<div>
<div class="flex items-center justify-between mb-6">
  <div>
    <p class="text-fg-subtle text-xs" x-text="greetingText">Добрый день</p>
    <h1 class="font-display font-bold text-xl" x-text="user?.system_name || 'Загрузка...'"></h1>
  </div>
  <div class="w-10 h-10 rounded-full bg-surface flex items-center justify-center">
    <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
    </svg>
  </div>
</div>

<div class="grid grid-cols-2 gap-3 mb-6">
  <a href="/tma/financial?metric=total_revenue" class="block bg-surface rounded-2xl p-4 active:scale-[0.98] transition-transform">
    <p class="text-fg-subtle text-xs mb-2">Оборот</p>
    <p class="text-xl font-display font-bold text-fg tabular-nums" x-text="formatPrice(stats.total_revenue)"></p>
  </a>
  <a href="/tma/financial?metric=net_profit" class="block bg-surface rounded-2xl p-4 active:scale-[0.98] transition-transform">
    <p class="text-fg-subtle text-xs mb-2">Чистая прибыль</p>
    <p class="text-xl font-display font-bold text-success tabular-nums" x-text="formatPrice(stats.net_profit)"></p>
  </a>
  <a href="/tma/financial?metric=total_purchase" class="block bg-surface rounded-2xl p-4 active:scale-[0.98] transition-transform">
    <p class="text-fg-subtle text-xs mb-2">Закуп</p>
    <p class="text-lg font-display font-semibold text-fg-data tabular-nums" x-text="formatPrice(stats.total_purchase)"></p>
  </a>
  <a href="/tma/financial?metric=courier_payouts" class="block bg-surface rounded-2xl p-4 active:scale-[0.98] transition-transform">
    <p class="text-fg-subtle text-xs mb-2">Выплаты</p>
    <p class="text-lg font-display font-semibold text-fg-data tabular-nums" x-text="formatPrice(stats.courier_payouts)"></p>
  </a>
</div>

<div class="flex justify-center gap-2 mb-4">
  <button class="px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted"
          :class="period === 'today' ? 'bg-primary/20 border-primary text-primary' : ''"
          @click="setPeriod('today')">Сегодня</button>
  <button class="px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted"
          :class="period === 'week' ? 'bg-primary/20 border-primary text-primary' : ''"
          @click="setPeriod('week')">Неделя</button>
  <button class="px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted"
          :class="period === 'month' ? 'bg-primary/20 border-primary text-primary' : ''"
          @click="setPeriod('month')">Месяц</button>
</div>

<div class="bg-surface rounded-2xl p-4 mb-4">
<div class="flex border-b border-white/5 mb-4">
    <button type="button"
            class="flex-1 py-2 text-xs font-medium transition-colors relative pb-3"
            :class="activeKpiTab === 'managers' ? 'text-fg' : 'text-fg-subtle hover:text-fg-muted'"
            @click="activeKpiTab = 'managers'">
      Менеджеры
      <span x-show="activeKpiTab === 'managers'" class="absolute bottom-0 left-1/4 right-1/4 h-0.5 bg-primary rounded-full"></span>
    </button>
    <button type="button"
            class="flex-1 py-2 text-xs font-medium transition-colors relative pb-3"
            :class="activeKpiTab === 'couriers' ? 'text-fg' : 'text-fg-subtle hover:text-fg-muted'"
            @click="activeKpiTab = 'couriers'">
      Курьеры
      <span x-show="activeKpiTab === 'couriers'" class="absolute bottom-0 left-1/4 right-1/4 h-0.5 bg-primary rounded-full"></span>
    </button>
    <button type="button"
            class="flex-1 py-2 text-xs font-medium transition-colors relative pb-3"
            :class="activeKpiTab === 'products' ? 'text-fg' : 'text-fg-subtle hover:text-fg-muted'"
            @click="activeKpiTab = 'products'">
      Товары
      <span x-show="activeKpiTab === 'products'" class="absolute bottom-0 left-1/4 right-1/4 h-0.5 bg-primary rounded-full"></span>
    </button>
  </div>

  <div x-show="activeKpiTab === 'managers'">
    <div class="grid grid-cols-2 gap-2 mb-3">
      <div class="bg-bg rounded-lg p-3">
        <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
          <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <span>Скорость ответа</span>
        </div>
        <p class="font-display font-bold text-base text-fg" x-text="managersKpi.summary?.avg_response_time_formatted || '—'"></p>
        <p class="text-[10px] text-fg-subtle mt-0.5">Взятие в работу</p>
      </div>

      <div class="bg-bg rounded-lg p-3">
        <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
          <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
          </svg>
          <span>Конверсия</span>
        </div>
        <p class="font-display font-bold text-base"
           :class="managersKpi.summary?.total_accepted > 0 ? 'text-success' : 'text-fg-subtle'"
           x-text="managersKpi.summary?.total_accepted > 0 ? (managersKpi.summary?.conversion_rate || 0) + '%' : '—'"></p>
        <p class="text-[10px] text-fg-subtle mt-0.5" x-text="(managersKpi.summary?.total_completed || 0) + ' из ' + (managersKpi.summary?.total_accepted || 0) + ' доставлено'"></p>
      </div>
    </div>

    <div class="space-y-2">
      <div class="flex items-center justify-between px-0.5 pt-1 text-[11px] text-fg-subtle">
        <span>Топ-5 активных</span>
        <a href="/tma/analytics?tab=managers" class="text-primary hover:underline flex items-center gap-0.5 font-medium">
          <span>Все менеджеры</span>
          <span x-show="managersKpi.managers?.length" x-text="'(' + managersKpi.managers.length + ')'"></span>
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
      </div>

      <template x-if="kpiLoading">
        <div class="py-3 flex items-center justify-center gap-2">
          <div class="w-4 h-4 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
          <p class="text-fg-subtle text-xs">Загрузка метрик...</p>
        </div>
      </template>
      <template x-if="!kpiLoading && (!managersKpi.managers || managersKpi.managers.length === 0)">
        <p class="text-fg-subtle text-xs text-center py-3">Нет данных за выбранный период</p>
      </template>
      <template x-for="(m, idx) in (managersKpi.managers || []).slice(0, 5)" :key="m.id">
        <div class="p-2.5 bg-bg rounded-lg text-xs">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-1.5">
              <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                    :class="idx === 0 && m.accepted_orders > 0 ? 'bg-primary/20 text-primary' : 'bg-surface2 text-fg-subtle'"
                    x-text="'#' + (idx + 1)"></span>
              <span class="font-medium text-fg" x-text="m.name"></span>
              <span x-show="m.role === 'owner'" class="px-1.5 py-0.2 rounded text-[9px] bg-primary/10 text-primary font-semibold">Владелец</span>
            </div>
            <span class="font-display font-bold text-sm"
                  :class="m.accepted_orders === 0 ? 'text-fg-subtle' : (m.conversion_rate >= 50 ? 'text-success' : 'text-warning')"
                  x-text="m.accepted_orders > 0 ? m.conversion_rate + '% конв.' : '—'"></span>
          </div>
          <div class="grid grid-cols-3 gap-1 text-[11px] text-fg-muted pt-1">
            <div>
              <span class="text-fg-subtle text-[10px] block">Ответ:</span>
              <span class="font-medium text-fg" x-text="m.avg_response_time_formatted"></span>
            </div>
            <div>
              <span class="text-fg-subtle text-[10px] block">Принято:</span>
              <span class="font-medium text-fg" x-text="m.accepted_orders"></span>
            </div>
            <div>
              <span class="text-fg-subtle text-[10px] block">Доставлено:</span>
              <span class="font-medium text-success" x-text="m.completed_orders"></span>
            </div>
          </div>
        </div>
      </template>

      <a href="/tma/analytics?tab=managers" class="block text-center mt-2 py-2 px-3 rounded-lg bg-surface2 text-xs font-medium text-fg hover:text-primary transition-colors">
        Полный отчет по менеджерам →
      </a>
    </div>
  </div>

  <div x-show="activeKpiTab === 'couriers'">
    <div class="grid grid-cols-2 gap-2 mb-3">
      <div class="bg-bg rounded-lg p-3">
        <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
          <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
          </svg>
          <span>Доставок выполнено</span>
        </div>
        <p class="font-display font-bold text-base text-fg" x-text="couriersKpi.summary?.total_deliveries || 0"></p>
        <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Выплаты: ' + formatPrice(couriersKpi.summary?.payout_amount || 0)"></p>
      </div>

      <div class="bg-bg rounded-lg p-3">
        <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
          <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <span>Собрано оплат</span>
        </div>
        <p class="font-display font-bold text-base text-success" x-text="formatPrice(couriersKpi.summary?.total_collected || 0)"></p>
        <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Нал: ' + formatPrice(couriersKpi.summary?.cash_amount || 0) + ' • Безнал: ' + formatPrice(couriersKpi.summary?.terminal_amount || 0)"></p>
      </div>
    </div>

    <div class="space-y-2">
      <div class="flex items-center justify-between px-0.5 pt-1 text-[11px] text-fg-subtle">
        <span>Топ-5 активных</span>
        <a href="/tma/analytics?tab=couriers" class="text-primary hover:underline flex items-center gap-0.5 font-medium">
          <span>Все курьеры</span>
          <span x-show="couriersKpi.couriers?.length" x-text="'(' + couriersKpi.couriers.length + ')'"></span>
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
      </div>

      <template x-if="kpiLoading">
        <div class="py-3 flex items-center justify-center gap-2">
          <div class="w-4 h-4 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
          <p class="text-fg-subtle text-xs">Загрузка метрик...</p>
        </div>
      </template>
      <template x-if="!kpiLoading && (!couriersKpi.couriers || couriersKpi.couriers.length === 0)">
        <p class="text-fg-subtle text-xs text-center py-3">Нет данных за выбранный период</p>
      </template>
      <template x-for="(c, idx) in (couriersKpi.couriers || []).slice(0, 5)" :key="c.id">
        <div class="p-2.5 bg-bg rounded-lg text-xs">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-1.5">
              <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                    :class="idx === 0 && c.completed_deliveries > 0 ? 'bg-primary/20 text-primary' : 'bg-surface2 text-fg-subtle'"
                    x-text="'#' + (idx + 1)"></span>
              <span class="font-medium text-fg" x-text="c.name"></span>
            </div>
            <span class="font-display font-bold text-success text-sm" x-text="formatPrice(c.total_collected)"></span>
          </div>
          <div class="grid grid-cols-3 gap-1 text-[11px] text-fg-muted pt-1">
            <div>
              <span class="text-fg-subtle text-[10px] block">Доставок:</span>
              <span class="font-medium text-fg" x-text="c.completed_deliveries"></span>
            </div>
            <div>
              <span class="text-fg-subtle text-[10px] block">Нал / Безнал:</span>
              <span class="font-medium text-fg" x-text="formatPrice(c.cash_amount) + ' / ' + formatPrice(c.terminal_amount)"></span>
            </div>
            <div>
              <span class="text-fg-subtle text-[10px] block">К выплате:</span>
              <span class="font-medium text-warning" x-text="formatPrice(c.payout_amount)"></span>
            </div>
          </div>
        </div>
      </template>

      <a href="/tma/analytics?tab=couriers" class="block text-center mt-2 py-2 px-3 rounded-lg bg-surface2 text-xs font-medium text-fg hover:text-primary transition-colors">
        Полный отчет по курьерам →
      </a>
    </div>
  </div>

  <div x-show="activeKpiTab === 'products'" style="display: none;">
    <div class="grid grid-cols-2 gap-2 mb-3">
      <div class="bg-bg rounded-lg p-3">
        <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
          <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <span>Выручка по товарам</span>
        </div>
        <p class="font-display font-bold text-base text-fg" x-text="formatPrice(productsKpi.summary?.total_revenue || 0)"></p>
        <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Прибыль: ' + formatPrice(productsKpi.summary?.total_profit || 0)"></p>
      </div>

      <div class="bg-bg rounded-lg p-3">
        <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
          <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25-2.25M12 13.875V3.75" />
          </svg>
          <span>Продано позиций</span>
        </div>
        <p class="font-display font-bold text-base text-success" x-text="(productsKpi.summary?.total_units_sold || 0) + ' шт.'"></p>
        <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Маржа: ' + (productsKpi.summary?.avg_margin_rate || 0) + '%'"></p>
      </div>
    </div>

    <div class="space-y-2">
      <div class="flex items-center justify-between px-0.5 pt-1 text-[11px] text-fg-subtle">
        <span>Топ-5 по выручке</span>
        <a href="/tma/analytics?tab=products" class="text-primary hover:underline flex items-center gap-0.5 font-medium">
          <span>Все товары</span>
          <span x-show="productsKpi?.pagination?.total" x-text="'(' + (productsKpi?.pagination?.total ?? '') + ')'"></span>
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
      </div>

      <template x-if="kpiLoading">
        <div class="py-3 flex items-center justify-center gap-2">
          <div class="w-4 h-4 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
          <p class="text-fg-subtle text-xs">Загрузка товаров...</p>
        </div>
      </template>
      <template x-if="!kpiLoading && (!productsKpi.products || productsKpi.products.length === 0)">
        <p class="text-fg-subtle text-xs text-center py-3">Нет данных за выбранный период</p>
      </template>
      <template x-for="(p, idx) in (productsKpi.products || []).slice(0, 5)" :key="p.id">
        <div class="p-2.5 bg-bg rounded-lg text-xs">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0"
                    :class="idx === 0 && p.revenue > 0 ? 'bg-primary/20 text-primary' : 'bg-surface2 text-fg-subtle'"
                    x-text="'#' + (idx + 1)"></span>
              <span class="font-medium text-fg truncate" x-text="p.name"></span>
            </div>
            <span class="font-display font-bold text-sm text-right shrink-0"
                  :class="p.revenue > 0 ? 'text-fg' : 'text-fg-subtle'"
                  x-text="formatPrice(p.revenue)"></span>
          </div>
          <div class="grid grid-cols-3 gap-1 text-[11px] text-fg-muted pt-1">
            <div>
              <span class="text-fg-subtle text-[10px] block">Продано:</span>
              <span class="font-medium text-fg" x-text="p.completed_qty + ' шт.'"></span>
            </div>
            <div>
              <span class="text-fg-subtle text-[10px] block">Прибыль:</span>
              <span class="font-medium text-success" x-text="(p.profit > 0 ? '+' : '') + formatPrice(p.profit)"></span>
            </div>
            <div>
              <span class="text-fg-subtle text-[10px] block">Маржа:</span>
              <span class="font-medium text-fg" x-text="p.revenue > 0 ? p.margin_rate + '%' : '—'"></span>
            </div>
          </div>
        </div>
      </template>

      <a href="/tma/analytics?tab=products" class="block text-center mt-2 py-2 px-3 rounded-lg bg-surface2 text-xs font-medium text-fg hover:text-primary transition-colors">
        Полный отчет по товарам →
      </a>
    </div>
  </div>
</div>

<div class="bg-surface rounded-2xl p-4 mb-4">
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-2">
      <h2 class="font-display font-semibold text-sm">Последние заказы</h2>
      <span class="text-xs text-fg-subtle" x-text="orders.length + ' ' + pluralize(orders.length, 'заказ', 'заказа', 'заказов')"></span>
    </div>
    <a href="/tma/orders" class="text-xs text-primary hover:underline flex items-center gap-0.5 font-medium">
      <span>Все заказы</span>
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
  </div>
  <div id="recent-orders" class="space-y-2">
    <template x-if="dashboardLoading">
      <div class="py-4 flex flex-col items-center justify-center gap-2">
        <div class="w-5 h-5 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
        <p class="text-xs text-fg-muted">Загрузка...</p>
      </div>
    </template>
    <template x-if="!dashboardLoading && orders.length === 0">
      <p class="text-fg-subtle text-sm text-center py-4">Нет заказов</p>
    </template>
    <template x-for="order in orders" :key="order.id">
      <div>
        <div class="h-px bg-white/5" x-show="orders.indexOf(order) > 0"></div>
        <a :href="'/tma/orders/' + order.id"
           @dblclick.prevent="window.location.href = '/tma/orders/' + order.id + '?edit=1'"
           class="flex items-center justify-between px-3 py-3 active:bg-surface2/40 transition-colors rounded-lg">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium shrink-0" x-text="'#' + order.id"></p>
              <span x-show="order.site" class="text-[11px] text-fg-muted truncate" x-text="formatSite(order.site)"></span>
            </div>
            <p class="text-xs text-fg-muted truncate" x-text="order.customer?.address"></p>
          </div>
          <span class="flex-shrink-0 text-xs font-medium" :class="getStatusClass(order.status)" x-text="getStatusLabel(order.status)"></span>
        </a>
      </div>
    </template>
  </div>
  <a x-show="!dashboardLoading && orders.length > 5" href="/tma/orders" class="block text-center mt-3 py-2 px-3 rounded-lg bg-surface2 text-xs font-medium text-fg hover:text-primary transition-colors">
    Все заказы →
  </a>
</div>

</div>

  @include('tma.partials.nav')
@endsection
