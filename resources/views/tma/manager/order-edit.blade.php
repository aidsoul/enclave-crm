@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-5">
  <button type="button" @click="goBack()" class="w-10 h-10 rounded-full bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg active:scale-95 transition-all" title="Назад">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
    </svg>
  </button>
  <h1 class="font-display font-bold text-lg">Редактирование заказа</h1>
  <div class="w-10"></div>
</div>

<template x-if="order">
  <form @submit.prevent="submitEdit" class="space-y-4 mb-4" novalidate>
    <!-- Карточка статуса заказа -->
    <div class="bg-surface rounded-2xl p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-sm font-semibold text-primary" x-text="'#' + order.id"></span>
          <span x-show="order.external_id" class="text-xs text-fg-subtle font-mono" x-text="'(#' + order.external_id + ')'"></span>
        </div>
        <div class="flex items-center gap-1.5">
          <span x-show="form.is_valuable" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-400">Ценное</span>
          <span :class="getStatusClass(order.status)" x-text="getStatusLabel(order.status)"></span>
        </div>
      </div>

      <template x-if="order.is_edited && user?.role !== 'courier'">
        <div class="flex items-center gap-2 mt-2 pt-2 border-t border-white/[0.05] text-warning text-xs">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
          </svg>
          Изменено менеджером
        </div>
      </template>
    </div>

    <!-- Блок 1: Назначения и сайт -->
    <div class="bg-surface rounded-2xl p-4 space-y-3">
      <h2 class="text-xs font-semibold text-fg-subtle uppercase tracking-wider">Назначения</h2>

      @if($user && $user->isOwner())
      <div class="relative" :class="{'z-20': siteDropdownOpen}">
        <label class="block text-xs text-fg-subtle mb-1">Сайт</label>
        <button @click="openSiteDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 rounded-xl text-sm text-left flex items-center justify-between" :class="errors.site_id ? 'ring-1 ring-error' : ''">
          <span x-text="form.site_id ? (sites.find(s => s.id == form.site_id)?.domain || sites.find(s => s.id == form.site_id)?.name || (order?.site?.id == form.site_id ? (order?.site?.domain || order?.site?.name) : 'Выберите сайт')) : 'Выберите сайт'"></span>
          <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
          </svg>
        </button>
        <div x-show="siteDropdownOpen" @click.away="siteDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
          <input type="text" x-model="siteSearch" placeholder="Поиск..." class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary" @click.stop>
          <div class="max-h-40 overflow-y-auto">
            <button @click="selectSite('')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.site_id ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
              <span>Выберите сайт</span>
              <span x-show="!form.site_id" class="text-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </span>
            </button>
            <template x-for="site in filteredSites" :key="site.id">
              <button @click="selectSite(site.id)" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.site_id == site.id ? 'bg-surface2 text-primary font-medium' : ''">
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

      <div class="relative" :class="{'z-20': courierDropdownOpen}">
        <label class="block text-xs text-fg-subtle mb-1">Курьер</label>
        <template x-if="!form.manager_id && !order?.manager_id">
          <div class="w-full px-3 py-2.5 bg-surface2/50 rounded-xl text-sm text-fg-muted flex items-center justify-between cursor-not-allowed">
            <span>Курьер недоступен (нет менеджера)</span>
            <span class="text-xs text-warning">Сначала назначьте менеджера</span>
          </div>
        </template>
        <template x-if="form.manager_id || order?.manager_id">
          <div>
            <button @click="openCourierDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 rounded-xl text-sm text-left flex items-center justify-between">
              <span x-text="form.delivery_courier_id ? (couriers.find(c => c.id === form.delivery_courier_id)?.system_name || (order?.delivery?.[0]?.courier?.id === form.delivery_courier_id ? order?.delivery?.[0]?.courier?.system_name : 'Не назначен')) : 'Не назначен'"></span>
              <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
              </svg>
            </button>
            <div x-show="courierDropdownOpen" @click.away="courierDropdownOpen = false" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-xl max-h-60 overflow-hidden">
              <input type="text" x-model="courierSearch" @input="searchCouriers()" placeholder="Поиск..." class="w-full px-3 py-2 bg-surface2 border-b border-border text-sm text-fg focus:outline-none focus:border-primary" @click.stop>
              <div class="max-h-40 overflow-y-auto">
                <button @click="form.delivery_courier_id = ''; courierDropdownOpen = false; clearFieldError('delivery_courier_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="!form.delivery_courier_id ? 'bg-surface2 text-primary font-medium' : 'text-fg-muted'">
                  <span>Не назначен</span>
                  <span x-show="!form.delivery_courier_id" class="text-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                  </span>
                </button>
                <template x-for="courier in couriers" :key="courier.id">
                  <button @click="form.delivery_courier_id = courier.id; courierDropdownOpen = false; clearFieldError('delivery_courier_id')" type="button" class="w-full px-3 py-2 text-sm text-left hover:bg-surface2 flex items-center justify-between" :class="form.delivery_courier_id === courier.id ? 'bg-surface2 text-primary font-medium' : ''">
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

      @if($user && $user->isOwner())
      <div class="relative" :class="{'z-20': managerDropdownOpen}">
        <label class="block text-xs text-fg-subtle mb-1">Менеджер</label>
        <button @click="openManagerDropdown()" type="button" class="w-full px-3 py-2.5 bg-surface2 rounded-xl text-sm text-left flex items-center justify-between">
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

    <!-- Блок 2: Данные клиента и доставка -->
    <div class="bg-surface rounded-2xl p-4 space-y-3">
      <h2 class="text-xs font-semibold text-fg-subtle uppercase tracking-wider">Клиент и доставка</h2>

      <!-- Имя клиента с кнопкой вставки справа внутри инпута -->
      <div>
        <label class="block text-xs text-fg-subtle mb-1">Имя клиента</label>
        <div class="relative">
          <input type="text" x-model="form.client_name" @input="clearFieldError('client_name')" :class="inputClass('client_name', 'pr-9')" placeholder="Иван Иванов">
          <button type="button" @click="pasteToField('client_name')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-fg-subtle hover:text-primary transition-colors p-1" title="Вставить из буфера">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
            </svg>
          </button>
        </div>
        <p class="text-error text-xs mt-1" x-show="errors.client_name" x-text="errors.client_name"></p>
      </div>

      <!-- Телефон с маской и кнопкой вставки справа внутри инпута -->
      <div>
        <label class="block text-xs text-fg-subtle mb-1">Телефон</label>
        <div class="relative">
          <input type="tel" x-model="form.client_phone" @input="formatPhoneInput($event)" :class="inputClass('client_phone', 'pr-9')" placeholder="+375 (29) 123-45-67">
          <button type="button" @click="pasteToField('client_phone')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-fg-subtle hover:text-primary transition-colors p-1" title="Вставить из буфера">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
            </svg>
          </button>
        </div>
        <p class="text-error text-xs mt-1" x-show="errors.client_phone" x-text="errors.client_phone"></p>
      </div>

      <!-- Email клиента -->
      <div>
        <label class="block text-xs text-fg-subtle mb-1">Email</label>
        <input type="email" x-model="form.client_email" @input="clearFieldError('client_email')" :class="inputClass('client_email')" placeholder="client@example.com">
        <p class="text-error text-xs mt-1" x-show="errors.client_email" x-text="errors.client_email"></p>
      </div>

      <!-- Адрес доставки с кнопкой вставки справа внутри инпута -->
      <div>
        <label class="block text-xs text-fg-subtle mb-1">Адрес доставки</label>
        <div class="relative">
          <textarea x-model="form.delivery_address" @input="clearFieldError('delivery_address')" rows="2" :class="inputClass('delivery_address', 'pr-9 resize-none')" placeholder="Улица, дом, кв..."></textarea>
          <button type="button" @click="pasteToField('delivery_address')" class="absolute right-2.5 top-3 text-fg-subtle hover:text-primary transition-colors p-1" title="Вставить из буфера">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
            </svg>
          </button>
        </div>
        <p class="text-error text-xs mt-1" x-show="errors.delivery_address" x-text="errors.delivery_address"></p>
      </div>

      <!-- Стоимость доставки -->
      <div>
        <label class="block text-xs text-fg-subtle mb-1">Стоимость доставки</label>
        <div class="relative">
          <input type="number" step="0.01" min="0" x-model="form.delivery_price" @input="onDeliveryPriceInput($event)" @blur="onPriceBlur(form, 'delivery_price', $event, () => recalculatePrices())" :class="inputClass('delivery_price', 'pr-8 tabular-nums')" placeholder="0">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-fg-subtle font-medium pointer-events-none">Br</span>
        </div>
        <p class="text-error text-xs mt-1" x-show="errors.delivery_price" x-text="errors.delivery_price"></p>
      </div>

      <!-- Тумблер: Ценное отправление (Switch) -->
      <label class="flex items-center justify-between cursor-pointer select-none p-3 bg-surface2 rounded-xl">
        <div class="space-y-0.5 pr-3">
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-semibold text-fg">Ценное отправление</span>
            <span x-show="form.is_valuable" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/15 text-amber-400">Ценное</span>
          </div>
          <p class="text-[11px] text-fg-subtle">Курьер обязан запросить подпись клиента на экране</p>
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

    <!-- Блок 3: Состав заказа -->
    <div class="bg-surface rounded-2xl p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-xs font-semibold text-fg-subtle uppercase tracking-wider">Товары</h2>
        <span class="text-xs text-fg-muted" x-show="form.order_items.length" x-text="form.order_items.length + ' поз.'"></span>
      </div>

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

      <template x-for="(item, index) in form.order_items" :key="index">
        <div class="p-3 bg-surface2 rounded-xl space-y-2 relative" :class="{'z-20': activeItemProductDropdown === index}">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-fg-muted" x-text="'Товар #' + (index + 1)"></span>
            <button type="button" @click="removeItem(index)" class="text-fg-subtle hover:text-error text-xs p-1 transition-colors" title="Удалить товар">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[11px] text-fg-subtle">Название *</label>
              <button type="button" @click="openItemProductDropdown(index)" class="text-[10px] text-primary hover:underline flex items-center gap-1" title="Выбрать из справочника товаров">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <span>Из базы</span>
              </button>
            </div>
            <div class="relative">
              <input type="text" x-model="item.name" @input="clearFieldError('order_items.' + index + '.name')" :class="inputClass('order_items.' + index + '.name')" placeholder="Название товара">
              <div class="flex items-center gap-1.5 mt-1" x-show="item.variant_title || item.sku">
                <span x-show="item.variant_title" class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-primary/10 text-primary" x-text="item.variant_title"></span>
                <span x-show="item.sku" class="text-[10px] text-fg-subtle truncate font-mono" x-text="'SKU: ' + item.sku"></span>
              </div>

              <!-- Выпадающий список с поиском для конкретного товара (ограничение 5) -->
              <div x-show="activeItemProductDropdown === index" @click.away="activeItemProductDropdown = null" class="absolute z-30 w-full mt-1 bg-surface rounded-xl shadow-2xl ring-1 ring-white/10 max-h-60 overflow-hidden">
                <div class="relative">
                  <input type="text" x-model="itemProductSearch" @input="searchItemProducts()" placeholder="Поиск товара в базе..." class="w-full px-3 py-2 bg-surface2 border-b border-white/5 text-xs text-fg focus:outline-none pr-7" @click.stop>
                  <div x-show="itemProductsLoading" class="absolute right-2 top-1/2 -translate-y-1/2">
                    <svg class="w-3.5 h-3.5 animate-spin text-primary" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                  </div>
                </div>
                <div class="max-h-44 overflow-y-auto divide-y divide-white/5 text-xs">
                  <template x-for="prod in itemProducts" :key="prod.id">
                    <div>
                      <template x-if="!prod.variants || prod.variants.length === 0">
                        <button type="button" @click="addProductToItems(prod, null, index)" class="w-full px-3 py-2 text-left hover:bg-surface2 flex items-center justify-between transition-colors">
                          <div class="min-w-0 pr-2">
                            <div class="font-medium text-fg truncate" x-text="prod.name"></div>
                            <div class="text-[10px] text-fg-subtle flex items-center gap-2 mt-0.5">
                              <span x-show="prod.sku" class="font-mono" x-text="'SKU: ' + prod.sku"></span>
                              <span class="font-semibold text-fg" x-text="(prod.price || 0) + ' Br'"></span>
                            </div>
                          </div>
                          <span class="text-primary text-[10px] font-medium shrink-0">Выбрать</span>
                        </button>
                      </template>
                      <template x-if="prod.variants && prod.variants.length > 0">
                        <div class="p-1.5 space-y-1 bg-surface2/30">
                          <div class="text-[11px] font-medium text-fg px-1 truncate" x-text="prod.name"></div>
                          <template x-for="variant in prod.variants" :key="variant.id">
                            <button type="button" @click="addProductToItems(prod, variant, index)" class="w-full px-2 py-1 rounded bg-surface2 hover:bg-surface text-left flex items-center justify-between text-[11px] transition-colors">
                              <span class="text-primary truncate" x-text="variant.title || 'Вариант'"></span>
                              <span class="font-semibold text-fg shrink-0" x-text="(variant.price || prod.price || 0) + ' Br'"></span>
                            </button>
                          </template>
                        </div>
                      </template>
                    </div>
                  </template>
                  <div x-show="!itemProductsLoading && itemProducts.length === 0" class="px-3 py-2.5 text-[11px] text-fg-muted text-center">Товары не найдены</div>
                </div>
              </div>
            </div>
            <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.name']" x-text="errors['order_items.' + index + '.name']"></p>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="block text-[11px] text-fg-subtle mb-1">Кол-во *</label>
              <input type="number" min="1" x-model.number="item.qty" @input="clearFieldError('order_items.' + index + '.qty')" :class="inputClass('order_items.' + index + '.qty', 'tabular-nums')" placeholder="1">
              <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.qty']" x-text="errors['order_items.' + index + '.qty']"></p>
            </div>
            <div>
              <label class="block text-[11px] text-fg-subtle mb-1">Цена продажи</label>
              <div class="relative">
                <input type="number" step="0.01" min="0" x-model="item.price" @input="onItemPriceInput(index); clearFieldError('order_items.' + index + '.price')" @blur="onPriceBlur(item, 'price', $event)" :class="inputClass('order_items.' + index + '.price', 'pr-6 tabular-nums')" placeholder="0">
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[11px] text-fg-subtle font-medium pointer-events-none">Br</span>
              </div>
              <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.price']" x-text="errors['order_items.' + index + '.price']"></p>
            </div>
            <div>
              <label class="block text-[11px] text-fg-subtle mb-1">Закупка</label>
              <div class="relative">
                <input type="number" step="0.01" min="0" x-model="item.purchase_price" @input="item._customPurchasePrice = true; clearFieldError('order_items.' + index + '.purchase_price')" @blur="onPriceBlur(item, 'purchase_price', $event)" :class="inputClass('order_items.' + index + '.purchase_price', 'pr-6 tabular-nums')" placeholder="0">
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[11px] text-fg-subtle font-medium pointer-events-none">Br</span>
              </div>
              <p class="text-error text-xs mt-1" x-show="errors['order_items.' + index + '.purchase_price']" x-text="errors['order_items.' + index + '.purchase_price']"></p>
            </div>
          </div>
        </div>
      </template>

      <div class="grid grid-cols-2 gap-2 pt-1">
        <button type="button" @click="addItem" class="w-full py-2.5 bg-surface2 rounded-xl text-fg-muted hover:text-fg text-xs font-medium flex items-center justify-center gap-1.5 transition-colors active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
          </svg>
          Вручную
        </button>
        <button type="button" @click="openProductSearchModal" class="w-full py-2.5 bg-primary/10 text-primary rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-primary/20 transition-colors active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
          </svg>
          Из каталога
        </button>
      </div>
    </div>

    <!-- Блок 4: Итоговые суммы и расчёт прибыли -->
    <div class="bg-surface rounded-2xl p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-xs font-semibold text-fg-subtle uppercase tracking-wider">Оплата и финансы</h2>
        <div class="flex items-center gap-3 text-xs text-fg-subtle" x-show="form.order_items.length > 0 || parseFloat(form.delivery_price || 0) > 0">
          <span>Товары: <strong class="text-fg tabular-nums" x-text="formatPrice(getItemsTotal())"></strong></span>
          <span>Доставка: <strong class="text-fg tabular-nums" x-text="formatPrice(parseFloat(form.delivery_price || 0))"></strong></span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-fg-subtle mb-1">Цена для клиента</label>
          <div class="relative">
            <input type="number" step="0.01" min="0" x-model="form.total_price" @input="onTotalPriceInput($event); clearFieldError('total_price')" @blur="onPriceBlur(form, 'total_price', $event)" :class="inputClass('total_price', 'pr-8 tabular-nums font-semibold')" placeholder="0">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-fg-subtle font-medium pointer-events-none">Br</span>
          </div>
          <p class="text-error text-xs mt-1" x-show="errors.total_price" x-text="errors.total_price"></p>
        </div>
        <div>
          <label class="block text-xs text-fg-subtle mb-1">Закупочная цена</label>
          <div class="relative">
            <input type="number" step="0.01" min="0" x-model="form.purchase_price" @input="onPurchasePriceInput($event); clearFieldError('purchase_price')" @blur="onPriceBlur(form, 'purchase_price', $event)" :class="inputClass('purchase_price', 'pr-8 tabular-nums font-semibold')" placeholder="0">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-fg-subtle font-medium pointer-events-none">Br</span>
          </div>
          <p class="text-error text-xs mt-1" x-show="errors.purchase_price" x-text="errors.purchase_price"></p>
        </div>
      </div>

      <!-- Живой расчёт чистой прибыли -->
      <div class="p-3 bg-surface2/60 rounded-xl flex items-center justify-between text-xs">
        <span class="text-fg-subtle">Ожидаемая прибыль:</span>
        <span class="font-display font-bold text-base tabular-nums"
              :class="(parseFloat(form.total_price || 0) - parseFloat(form.purchase_price || 0)) > 0 ? 'text-success' : 'text-fg-subtle'"
              x-text="formatPrice(Math.max(0, (parseFloat(form.total_price || 0) - parseFloat(form.purchase_price || 0))))"></span>
      </div>
    </div>

    <!-- Главная кнопка действия во всю ширину -->
    <div class="pt-2">
      <button type="submit" :disabled="submitting" class="w-full py-3.5 bg-primary text-primary-fg font-semibold text-sm rounded-xl flex items-center justify-center gap-2 active:scale-[0.98] transition-all shadow-md disabled:opacity-50">
        <span x-text="submitting ? 'Сохранение...' : 'Сохранить изменения'"></span>
      </button>
    </div>
  </form>
</template>

  @include('tma.partials.order-product-search-modal')
  @include('tma.partials.nav')
@endsection
