@extends('tma.layout')

@section('content')
{{-- Заголовок страницы с кнопкой назад --}}
<div class="flex items-center gap-3 mb-4">
  <a href="/tma" class="w-8 h-8 flex items-center justify-center rounded-xl bg-surface2 text-fg-muted hover:text-fg active:scale-95 transition-all shrink-0">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
    </svg>
  </a>
  <div class="min-w-0 flex-1">
    <p class="text-fg-subtle text-xs">Статистика</p>
    <h1 class="font-display font-bold text-lg text-fg truncate">Аналитика и KPI</h1>
  </div>
</div>

{{-- Переключатель вкладок во всю ширину экрана --}}
<div class="grid grid-cols-3 p-1 bg-surface rounded-2xl gap-1 mb-3.5 shadow-sm">
  <button type="button"
          class="py-2 text-xs font-semibold rounded-xl transition-all text-center"
          :class="activeTab === 'managers' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'"
          @click="setTab('managers')">
    Менеджеры
  </button>
  <button type="button"
          class="py-2 text-xs font-semibold rounded-xl transition-all text-center"
          :class="activeTab === 'couriers' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'"
          @click="setTab('couriers')">
    Курьеры
  </button>
  <button type="button"
          class="py-2 text-xs font-semibold rounded-xl transition-all text-center"
          :class="activeTab === 'products' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'"
          @click="setTab('products')">
    Товары
  </button>
</div>

{{-- Фильтры периодов --}}
<div class="flex gap-1.5 mb-3.5 overflow-x-auto pb-0.5 scrollbar-none">
  <button class="flex-1 px-2.5 py-1.5 text-xs font-medium rounded-xl transition-colors whitespace-nowrap text-center"
          :class="period === 'today' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg'"
          @click="setPeriod('today')">Сегодня</button>
  <button class="flex-1 px-2.5 py-1.5 text-xs font-medium rounded-xl transition-colors whitespace-nowrap text-center"
          :class="period === 'week' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg'"
          @click="setPeriod('week')">Неделя</button>
  <button class="flex-1 px-2.5 py-1.5 text-xs font-medium rounded-xl transition-colors whitespace-nowrap text-center"
          :class="period === 'month' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg'"
          @click="setPeriod('month')">Месяц</button>
  <button class="flex-1 px-2.5 py-1.5 text-xs font-medium rounded-xl transition-colors whitespace-nowrap text-center"
          :class="period === 'all' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg'"
          @click="setPeriod('all')">Все время</button>
  <button type="button"
          class="px-2.5 py-1.5 text-xs font-medium rounded-xl transition-colors flex items-center justify-center shrink-0"
          :class="period === 'custom' ? 'bg-primary/15 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg'"
          title="Выбрать период"
          @click="openDatePicker()">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
    </svg>
  </button>
</div>

{{-- Индикатор выбранного произвольного периода --}}
<div x-show="period === 'custom' && customFrom && customTo"
     class="flex items-center justify-between px-3 py-1.5 bg-primary/10 border border-primary/30 rounded-lg text-xs text-primary mb-3">
  <div class="flex items-center gap-1.5 cursor-pointer" @click="openDatePicker()">
    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
    </svg>
    <span class="font-medium" x-text="customRangeText"></span>
    <span class="text-fg-subtle text-[11px]" x-text="'(' + customDaysCount + ' дн.)'"></span>
  </div>
  <button type="button" @click="clearCustomRange()" class="p-1 text-primary hover:text-fg transition-colors" title="Сбросить период">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  </button>
</div>

{{-- Сводная плашка текущей вкладки --}}
<div x-show="activeTab === 'managers'" class="grid grid-cols-2 gap-2 mb-3">
  <div class="bg-surface rounded-xl p-3">
    <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
      <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
      <span>Ср. скорость ответа</span>
    </div>
    <p class="font-display font-bold text-lg text-fg" x-text="managersData.summary?.avg_response_time_formatted || '—'"></p>
    <p class="text-[10px] text-fg-subtle mt-0.5">Всей команды</p>
  </div>

  <div class="bg-surface rounded-xl p-3">
    <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
      <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
      </svg>
      <span>Конверсия команды</span>
    </div>
    <p class="font-display font-bold text-lg text-success"
       x-text="managersData.summary?.total_accepted > 0 ? (managersData.summary?.conversion_rate || 0) + '%' : '—'"></p>
    <p class="text-[10px] text-fg-subtle mt-0.5" x-text="(managersData.summary?.total_completed || 0) + ' из ' + (managersData.summary?.total_accepted || 0) + ' доставлено'"></p>
  </div>
</div>

<div x-show="activeTab === 'couriers'" class="grid grid-cols-2 gap-2 mb-3">
  <div class="bg-surface rounded-xl p-3">
    <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
      <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
      </svg>
      <span>Всего доставок</span>
    </div>
    <p class="font-display font-bold text-lg text-fg" x-text="couriersData.summary?.total_deliveries || 0"></p>
    <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Выплаты: ' + formatPrice(couriersData.summary?.payout_amount || 0)"></p>
  </div>

  <div class="bg-surface rounded-xl p-3">
    <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
      <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
      <span>Собрано оплат</span>
    </div>
    <p class="font-display font-bold text-lg text-success" x-text="formatPrice(couriersData.summary?.total_collected || 0)"></p>
    <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Нал: ' + formatPrice(couriersData.summary?.cash_amount || 0) + ' • Безнал: ' + formatPrice(couriersData.summary?.terminal_amount || 0)"></p>
  </div>
</div>

{{-- Сводная плашка для товаров --}}
<div x-show="activeTab === 'products'" style="display: none;" class="grid grid-cols-2 gap-2 mb-3">
  <div class="bg-surface rounded-xl p-3">
    <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
      <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
      <span>Выручка по товарам</span>
    </div>
    <p class="font-display font-bold text-lg text-fg" x-text="formatPrice(productsData.summary?.total_revenue || 0)"></p>
    <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Прибыль: ' + formatPrice(productsData.summary?.total_profit || 0)"></p>
  </div>

  <div class="bg-surface rounded-xl p-3">
    <div class="flex items-center gap-1.5 mb-1 text-fg-subtle text-[11px]">
      <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25-2.25M12 13.875V3.75" />
      </svg>
      <span>Продано позиций</span>
    </div>
    <p class="font-display font-bold text-lg text-success" x-text="(productsData.summary?.total_units_sold || 0) + ' шт.'"></p>
    <p class="text-[10px] text-fg-subtle mt-0.5" x-text="'Маржа: ' + (productsData.summary?.avg_margin_rate || 0) + '% • ' + (productsData.summary?.unique_products_sold || 0) + ' тов.'"></p>
  </div>
</div>

{{-- Строка поиска и сортировки --}}
<div class="bg-surface rounded-xl p-3 mb-3 space-y-2">
  <div class="relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fg-muted pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
    </svg>
    <input
      type="text"
      x-model="search"
      @input="onSearchInput()"
      :placeholder="activeTab === 'products' ? 'Поиск по названию или артикулу...' : 'Поиск по имени сотрудника...'"
      class="w-full pl-9 pr-8 py-2 bg-bg rounded-lg text-xs focus:border-primary focus:outline-none placeholder:text-fg-subtle text-fg"
    >
    <button
      type="button"
      x-show="search"
      @click="clearSearch()"
      class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 flex items-center justify-center text-fg-muted hover:text-fg">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div class="grid grid-cols-2 gap-2 pt-0.5">
    {{-- Dropdown: Фильтр --}}
    <div class="space-y-1 relative" :class="{'z-20': managerFilterDropdownOpen || courierFilterDropdownOpen || productFilterDropdownOpen}">
      <label class="text-[11px] font-medium text-fg-subtle block">Фильтр</label>
      
      <div x-show="activeTab === 'managers'" class="relative">
        <button @click="managerFilterDropdownOpen = !managerFilterDropdownOpen; courierFilterDropdownOpen = false; productFilterDropdownOpen = false; managerSortDropdownOpen = false; courierSortDropdownOpen = false; productSortDropdownOpen = false" type="button" class="w-full px-2.5 py-2 bg-bg rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span class="truncate" x-text="getManagerFilterLabel()"></span>
          <svg class="w-3.5 h-3.5 text-fg-muted shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="managerFilterDropdownOpen" @click.away="managerFilterDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <template x-for="opt in [
              { value: 'all', label: 'Все' },
              { value: 'with_orders', label: 'С заказами' },
              { value: 'with_completed', label: 'С доставками' },
              { value: 'without_orders', label: 'Без заказов' }
            ]" :key="opt.value">
              <button @click="managerFilter = opt.value; managerFilterDropdownOpen = false; onFilterChange()" type="button" class="w-full px-2.5 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors" :class="managerFilter === opt.value ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="opt.label"></span>
                <span x-show="managerFilter === opt.value" class="text-primary">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'couriers'" style="display: none;" class="relative">
        <button @click="courierFilterDropdownOpen = !courierFilterDropdownOpen; managerFilterDropdownOpen = false; productFilterDropdownOpen = false; managerSortDropdownOpen = false; courierSortDropdownOpen = false; productSortDropdownOpen = false" type="button" class="w-full px-2.5 py-2 bg-bg rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span class="truncate" x-text="getCourierFilterLabel()"></span>
          <svg class="w-3.5 h-3.5 text-fg-muted shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="courierFilterDropdownOpen" @click.away="courierFilterDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <template x-for="opt in [
              { value: 'all', label: 'Все' },
              { value: 'with_deliveries', label: 'С доставками' },
              { value: 'without_deliveries', label: 'Без доставок' }
            ]" :key="opt.value">
              <button @click="courierFilter = opt.value; courierFilterDropdownOpen = false; onFilterChange()" type="button" class="w-full px-2.5 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors" :class="courierFilter === opt.value ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="opt.label"></span>
                <span x-show="courierFilter === opt.value" class="text-primary">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'products'" style="display: none;" class="relative">
        <button @click="productFilterDropdownOpen = !productFilterDropdownOpen; managerFilterDropdownOpen = false; courierFilterDropdownOpen = false; managerSortDropdownOpen = false; courierSortDropdownOpen = false; productSortDropdownOpen = false" type="button" class="w-full px-2.5 py-2 bg-bg rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span class="truncate" x-text="getProductFilterLabel()"></span>
          <svg class="w-3.5 h-3.5 text-fg-muted shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="productFilterDropdownOpen" @click.away="productFilterDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <template x-for="opt in [
              { value: 'all', label: 'Все товары' },
              { value: 'with_sales', label: 'С продажами' },
              { value: 'without_sales', label: 'Без продаж' }
            ]" :key="opt.value">
              <button @click="productFilter = opt.value; productFilterDropdownOpen = false; onFilterChange()" type="button" class="w-full px-2.5 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors" :class="productFilter === opt.value ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="opt.label"></span>
                <span x-show="productFilter === opt.value" class="text-primary">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    {{-- Dropdown: Сортировка --}}
    <div class="space-y-1 relative" :class="{'z-20': managerSortDropdownOpen || courierSortDropdownOpen || productSortDropdownOpen}">
      <label class="text-[11px] font-medium text-fg-subtle block">Сортировка</label>

      <div x-show="activeTab === 'managers'" class="relative">
        <button @click="managerSortDropdownOpen = !managerSortDropdownOpen; managerFilterDropdownOpen = false; courierFilterDropdownOpen = false; productFilterDropdownOpen = false; courierSortDropdownOpen = false; productSortDropdownOpen = false" type="button" class="w-full px-2.5 py-2 bg-bg rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span class="truncate" x-text="getManagerSortLabel()"></span>
          <svg class="w-3.5 h-3.5 text-fg-muted shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="managerSortDropdownOpen" @click.away="managerSortDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <template x-for="opt in [
              { value: 'orders_desc', label: 'По заказам (макс)' },
              { value: 'orders_asc', label: 'По заказам (мин)' },
              { value: 'conversion_desc', label: 'По конверсии (макс)' },
              { value: 'conversion_asc', label: 'По конверсии (мин)' },
              { value: 'speed_fast', label: 'По скорости (быстрее)' },
              { value: 'speed_slow', label: 'По скорости (медленнее)' },
              { value: 'name_asc', label: 'По имени (А-Я)' },
              { value: 'name_desc', label: 'По имени (Я-А)' }
            ]" :key="opt.value">
              <button @click="managerSort = opt.value; managerSortDropdownOpen = false; onSortChange()" type="button" class="w-full px-2.5 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors" :class="managerSort === opt.value ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="opt.label"></span>
                <span x-show="managerSort === opt.value" class="text-primary">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'couriers'" style="display: none;" class="relative">
        <button @click="courierSortDropdownOpen = !courierSortDropdownOpen; managerFilterDropdownOpen = false; courierFilterDropdownOpen = false; productFilterDropdownOpen = false; managerSortDropdownOpen = false; productSortDropdownOpen = false" type="button" class="w-full px-2.5 py-2 bg-bg rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span class="truncate" x-text="getCourierSortLabel()"></span>
          <svg class="w-3.5 h-3.5 text-fg-muted shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="courierSortDropdownOpen" @click.away="courierSortDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <template x-for="opt in [
              { value: 'deliveries_desc', label: 'По доставкам (макс)' },
              { value: 'deliveries_asc', label: 'По доставкам (мин)' },
              { value: 'collected_desc', label: 'По оплатам (макс)' },
              { value: 'collected_asc', label: 'По оплатам (мин)' },
              { value: 'payout_desc', label: 'К выплате (макс)' },
              { value: 'name_asc', label: 'По имени (А-Я)' },
              { value: 'name_desc', label: 'По имени (Я-А)' }
            ]" :key="opt.value">
              <button @click="courierSort = opt.value; courierSortDropdownOpen = false; onSortChange()" type="button" class="w-full px-2.5 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors" :class="courierSort === opt.value ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="opt.label"></span>
                <span x-show="courierSort === opt.value" class="text-primary">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
          </div>
        </div>
      </div>

      <div x-show="activeTab === 'products'" style="display: none;" class="relative">
        <button @click="productSortDropdownOpen = !productSortDropdownOpen; managerFilterDropdownOpen = false; courierFilterDropdownOpen = false; productFilterDropdownOpen = false; managerSortDropdownOpen = false; courierSortDropdownOpen = false" type="button" class="w-full px-2.5 py-2 bg-bg rounded-lg text-xs text-left flex items-center justify-between text-fg">
          <span class="truncate" x-text="getProductSortLabel()"></span>
          <svg class="w-3.5 h-3.5 text-fg-muted shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="productSortDropdownOpen" @click.away="productSortDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <div class="max-h-48 overflow-y-auto">
            <template x-for="opt in [
              { value: 'revenue_desc', label: 'По выручке (макс)' },
              { value: 'profit_desc', label: 'По прибыли (макс)' },
              { value: 'units_desc', label: 'По проданным (макс)' },
              { value: 'orders_desc', label: 'По заказам (макс)' },
              { value: 'margin_desc', label: 'По марже % (макс)' },
              { value: 'buyout_desc', label: 'По выкупу % (макс)' },
              { value: 'buyout_asc', label: 'По выкупу % (мин)' },
              { value: 'name_asc', label: 'По названию (А-Я)' }
            ]" :key="opt.value">
              <button @click="productSort = opt.value; productSortDropdownOpen = false; onSortChange()" type="button" class="w-full px-2.5 py-2 text-xs text-left hover:bg-surface2 flex items-center justify-between transition-colors" :class="productSort === opt.value ? 'bg-surface2 text-primary font-medium' : 'text-fg'">
                <span x-text="opt.label"></span>
                <span x-show="productSort === opt.value" class="text-primary">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                  </svg>
                </span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Список сотрудников --}}
<div class="space-y-2 mb-4">
  <template x-if="loading">
    <div class="bg-surface rounded-xl p-8 text-center">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin mx-auto mb-2"></div>
      <p class="text-fg-subtle text-xs">Загрузка аналитики...</p>
    </div>
  </template>

  {{-- Менеджеры --}}
  <template x-if="!loading && activeTab === 'managers'">
    <div>
      <template x-if="visibleManagers.length === 0">
        <div class="bg-surface rounded-xl p-8 text-center text-fg-subtle text-xs">
          Ничего не найдено
        </div>
      </template>
      <div class="space-y-2">
        <template x-for="(m, idx) in visibleManagers" :key="m.id">
          <div class="bg-surface rounded-xl p-3 text-xs">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                      :class="idx < 3 && m.accepted_orders > 0 ? 'bg-primary/20 text-primary' : 'bg-surface2 text-fg-subtle'"
                      x-text="'#' + (idx + 1)"></span>
                <span class="font-medium text-fg text-sm" x-text="m.name"></span>
                <span x-show="m.role === 'owner'" class="px-1.5 py-0.2 rounded text-[9px] bg-primary/10 text-primary font-semibold">Владелец</span>
              </div>
              <span class="font-display font-bold text-sm"
                    :class="m.accepted_orders === 0 ? 'text-fg-subtle' : (m.conversion_rate >= 50 ? 'text-success' : 'text-warning')"
                    x-text="m.accepted_orders > 0 ? m.conversion_rate + '% конв.' : '—'"></span>
            </div>

            <div class="grid grid-cols-4 gap-1 text-[11px] bg-bg rounded-lg p-2  text-center">
              <div>
                <span class="text-fg-subtle text-[10px] block">Ответ</span>
                <span class="font-medium text-fg" x-text="m.avg_response_time_formatted"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Принято</span>
                <span class="font-medium text-fg" x-text="m.accepted_orders"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Доставлено</span>
                <span class="font-medium text-success" x-text="m.completed_orders"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Отменено</span>
                <span class="font-medium text-error" x-text="m.cancelled_orders"></span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  {{-- Курьеры --}}
  <template x-if="!loading && activeTab === 'couriers'">
    <div>
      <template x-if="visibleCouriers.length === 0">
        <div class="bg-surface rounded-xl p-8 text-center text-fg-subtle text-xs">
          Ничего не найдено
        </div>
      </template>
      <div class="space-y-2">
        <template x-for="(c, idx) in visibleCouriers" :key="c.id">
          <div class="bg-surface rounded-xl p-3 text-xs">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                      :class="idx < 3 && c.completed_deliveries > 0 ? 'bg-primary/20 text-primary' : 'bg-surface2 text-fg-subtle'"
                      x-text="'#' + (idx + 1)"></span>
                <span class="font-medium text-fg text-sm" x-text="c.name"></span>
              </div>
              <span class="font-display font-bold text-success text-sm" x-text="formatPrice(c.total_collected)"></span>
            </div>

            <div class="grid grid-cols-3 gap-1 text-[11px] bg-bg rounded-lg p-2  text-center">
              <div>
                <span class="text-fg-subtle text-[10px] block">Доставок</span>
                <span class="font-medium text-fg" x-text="c.completed_deliveries"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Нал / Безнал</span>
                <span class="font-medium text-fg" x-text="formatPrice(c.cash_amount) + ' / ' + formatPrice(c.terminal_amount)"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">К выплате</span>
                <span class="font-medium text-warning" x-text="formatPrice(c.payout_amount)"></span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  {{-- Товары --}}
  <template x-if="!loading && activeTab === 'products'">
    <div>
      <template x-if="visibleProducts.length === 0">
        <div class="bg-surface rounded-xl p-8 text-center text-fg-subtle text-xs">
          Товары не найдены
        </div>
      </template>
      <div class="space-y-2">
        <template x-for="(p, idx) in visibleProducts" :key="p.id">
          <div class="bg-surface rounded-xl p-3 text-xs">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="flex items-start gap-2.5 min-w-0">
                <template x-if="p.image_url">
                  <img :src="p.image_url" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0  bg-bg">
                </template>
                <template x-if="!p.image_url">
                  <div class="w-10 h-10 rounded-lg bg-surface2  flex items-center justify-center shrink-0 text-fg-subtle">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                  </div>
                </template>
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-bold shrink-0"
                          :class="idx < 3 && p.revenue > 0 ? 'bg-primary/20 text-primary' : 'bg-surface2 text-fg-subtle'"
                          x-text="'#' + (idx + 1)"></span>
                    <span class="font-medium text-fg text-sm truncate" x-text="p.name"></span>
                  </div>
                  <div class="flex items-center gap-2 mt-0.5 text-[11px] text-fg-subtle">
                    <span x-show="p.sku" class="font-mono" x-text="p.sku"></span>
                    <span x-show="p.site_name" class="px-1.5 py-0.2 rounded bg-surface2 text-[10px]" x-text="p.site_name"></span>
                    <span x-show="!p.is_active" class="px-1 py-0.2 rounded bg-error/10 text-error text-[10px]">Неактивен</span>
                  </div>
                </div>
              </div>
              <div class="text-right shrink-0">
                <span class="font-display font-bold text-sm block"
                      :class="p.revenue > 0 ? 'text-fg' : 'text-fg-subtle'"
                      x-text="formatPrice(p.revenue)"></span>
                <span class="text-[11px] font-medium"
                      :class="p.profit > 0 ? 'text-success' : (p.profit < 0 ? 'text-error' : 'text-fg-subtle')"
                      x-text="(p.profit > 0 ? '+' : '') + formatPrice(p.profit)"></span>
              </div>
            </div>

            <div class="grid grid-cols-4 gap-1 text-[11px] bg-bg rounded-lg p-2  text-center">
              <div>
                <span class="text-fg-subtle text-[10px] block">Продано</span>
                <span class="font-medium text-fg" x-text="p.completed_qty + ' / ' + p.total_qty + ' шт.'"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Заказов</span>
                <span class="font-medium text-fg" x-text="p.orders_count"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Маржа</span>
                <span class="font-medium"
                      :class="p.margin_rate >= 30 ? 'text-success' : (p.margin_rate > 0 ? 'text-warning' : 'text-fg-subtle')"
                      x-text="p.revenue > 0 ? p.margin_rate + '%' : '—'"></span>
              </div>
              <div>
                <span class="text-fg-subtle text-[10px] block">Выкуп</span>
                <span class="font-medium"
                      :class="p.buyout_rate >= 70 ? 'text-success' : (p.buyout_rate > 0 ? 'text-warning' : 'text-fg-subtle')"
                      x-text="p.total_qty > 0 ? p.buyout_rate + '%' : '—'"></span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>
</div>

{{-- Кнопка "Загрузить ещё" (пагинация) --}}
<div x-show="!loading && hasMore" class="mb-4">
  <button type="button"
          @click="loadMore()"
          :disabled="loadingMore"
          class="w-full py-2.5 bg-surface2 rounded-xl text-xs font-medium text-fg-muted hover:text-fg active:scale-[0.99] transition-all flex items-center justify-center gap-2 disabled:opacity-60">
    <span x-show="!loadingMore">Показать ещё (осталось <span x-text="remainingCount"></span>)</span>
    <span x-show="loadingMore" class="flex items-center gap-1.5">
      <span class="w-3.5 h-3.5 rounded-full border-2 border-primary border-t-transparent animate-spin inline-block"></span>
      Загрузка...
    </span>
  </button>
</div>

@include('tma.partials.date-range-modal')
@include('tma.partials.nav')
@endsection
