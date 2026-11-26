@extends('tma.layout')

@section('content')
<!-- Header -->
<div class="flex items-center justify-between mb-4">
  <div class="flex items-center gap-3">
    <a href="/tma/settings" class="w-9 h-9 flex items-center justify-center rounded-xl bg-surface text-fg-muted hover:text-fg hover:border-primary/40 active:scale-95 transition-all" aria-label="Назад">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
      </svg>
    </a>
    <div>
      <p class="text-fg-subtle text-[11px] font-medium uppercase tracking-wider">Интеграции</p>
      <h1 class="font-display font-bold text-lg text-fg">Сайты и API-токены</h1>
    </div>
  </div>
  <button @click="showCreateSiteModal = true" class="px-3.5 py-2 bg-primary text-white text-xs font-semibold rounded-xl hover:bg-primary-hover flex items-center gap-1.5 shadow-sm active:scale-95 transition-all">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
    Добавить
  </button>
</div>

<!-- Search Bar -->
<div class="relative mb-3">
  <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-fg-muted">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
    </svg>
  </div>
  <input type="text"
         x-model="search"
         @input="onSearchInput()"
         placeholder="Поиск по названию или домену..."
         class="w-full pl-10 pr-9 py-2.5 bg-surface rounded-xl text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/40 transition-all">
  <button x-show="search"
          @click="clearSearch()"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-fg-muted hover:text-fg transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
    </svg>
  </button>
</div>

<!-- Stats Counter -->
<div class="flex items-center justify-between text-xs text-fg-subtle mb-3 px-1">
  <span x-text="'Всего сайтов: ' + sitesTotal"></span>
  <span x-show="sitesLastPage > 1" class="font-medium" x-text="'Стр. ' + sitesPage + ' из ' + sitesLastPage"></span>
</div>

<!-- Sites List -->
<div class="space-y-3 mb-6">
  <!-- Loading state -->
  <template x-if="sitesLoading && sites.length === 0">
    <div class="bg-surface rounded-2xl p-8 text-center text-fg-subtle text-sm">
      <div class="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      Загрузка сайтов...
    </div>
  </template>

  <!-- Empty search state -->
  <template x-if="!sitesLoading && sites.length === 0 && search">
    <div class="bg-surface rounded-2xl p-8 text-center">
      <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-surface2 flex items-center justify-center text-fg-muted">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
      </div>
      <p class="text-sm font-semibold text-fg mb-1">Ничего не найдено</p>
      <p class="text-xs text-fg-muted mb-3">По запросу «<span class="text-fg" x-text="search"></span>» сайтов не найдено</p>
      <button @click="clearSearch()" class="px-3.5 py-1.5 bg-surface2 rounded-lg text-xs font-medium text-fg hover:bg-surface2/80 transition-colors">
        Сбросить поиск
      </button>
    </div>
  </template>

  <!-- Empty initial state -->
  <template x-if="!sitesLoading && sites.length === 0 && !search">
    <div class="bg-surface border border-dashed border-border rounded-2xl p-8 text-center">
      <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/>
        </svg>
      </div>
      <p class="text-sm font-semibold text-fg mb-1">Нет подключенных сайтов</p>
      <p class="text-xs text-fg-muted mb-4 max-w-xs mx-auto">Добавьте первый сайт, чтобы сгенерировать API-токен для интеграции магазина</p>
      <button @click="showCreateSiteModal = true" class="px-4 py-2 bg-primary text-white text-xs font-semibold rounded-xl hover:bg-primary-hover shadow-sm">
        + Добавить сайт
      </button>
    </div>
  </template>

  <!-- Site Cards -->
  <template x-for="site in sites" :key="site.id">
    <div class="bg-surface/80 rounded-2xl p-4 shadow-sm hover:border-border transition-all">
      <!-- Card Header -->
      <div class="flex items-start justify-between gap-3 mb-3.5">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-display font-bold text-xs shrink-0 tracking-wider">
            <span x-text="(site.name || site.domain || 'S').slice(0, 2).toUpperCase()"></span>
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2 mb-0.5">
              <h3 class="font-display font-bold text-sm text-fg truncate" x-text="site.name"></h3>
              <span :class="site.is_active ? 'bg-success/15 text-success border-success/30' : 'bg-surface2 text-fg-muted border-border'"
                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border shrink-0">
                <span class="w-1.5 h-1.5 rounded-full" :class="site.is_active ? 'bg-success' : 'bg-fg-muted'"></span>
                <span x-text="site.is_active ? 'Активен' : 'Отключен'"></span>
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs flex-wrap">
              <span class="font-mono text-fg-muted text-[11px] truncate" x-text="site.domain"></span>
              <span class="text-fg-subtle">•</span>
              <span class="text-fg-subtle text-[11px]" x-text="(site.orders_count ?? 0) + ' ' + pluralize(site.orders_count ?? 0, 'заказ', 'заказа', 'заказов')"></span>
              <span class="text-fg-subtle">•</span>
              <span class="text-primary/90 text-[11px] font-medium" x-text="'Закупка: ' + (site.default_purchase_percent !== null && site.default_purchase_percent !== undefined && site.default_purchase_percent !== '' ? parseFloat(site.default_purchase_percent) + '%' : '{{ config('orders.default_purchase_percent', 15) }}% (по умолч.)')"></span>
            </div>
          </div>
        </div>

        <!-- Header Actions: Edit & Delete -->
        <div class="flex items-center gap-1 shrink-0">
          <button @click="openEditSite(site)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg text-fg-muted hover:text-fg hover:bg-surface2 active:scale-95 transition-all"
                  title="Редактировать">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
            </svg>
          </button>
          <button @click="openDeleteConfirm(site)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg text-fg-muted hover:text-error hover:bg-error/10 active:scale-95 transition-all"
                  title="Удалить">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- API Token Section -->
      <div class="bg-bg/60  rounded-xl p-3">
        <div class="flex items-center justify-between text-[11px] mb-2">
          <span class="text-fg-subtle font-semibold uppercase tracking-wider text-[10px]">API-токен</span>
          <span class="text-fg-muted text-[10px] font-mono">Authorization: Bearer</span>
        </div>

        <!-- Token Active -->
        <template x-if="site.api_token">
          <div>
            <!-- Full-width token field with embedded copy button -->
            <div class="flex items-center bg-surface rounded-xl pl-3 pr-1.5 py-1.5 mb-2.5 focus-within:border-primary/60 transition-colors">
              <span class="font-mono text-xs text-fg select-all truncate flex-1 tracking-tight" x-text="site.api_token"></span>
              <button @click="copySiteToken(site)"
                      type="button"
                      class="ml-2 px-2.5 py-1 text-xs font-semibold rounded-md bg-surface2 hover:bg-surface2/80 active:scale-95 text-fg flex items-center gap-1.5 shrink-0 transition-all ">
                <svg x-show="copiedSiteId !== site.id" class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                </svg>
                <svg x-show="copiedSiteId === site.id" class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
                <span x-text="copiedSiteId === site.id ? 'Скопировано!' : 'Копировать'"></span>
              </button>
            </div>

            <!-- Token Status & Security Hint -->
            <div x-show="site.api_token && site.api_token.includes('•')" class="flex items-center gap-1.5 text-[10px] text-fg-muted mb-2 px-0.5">
              <svg class="w-3 h-3 text-fg-muted/70 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
              </svg>
              <span>Токен защищен SHA-256 хешированием. В БД хранится только хеш.</span>
            </div>
            <div x-show="site.api_token && !site.api_token.includes('•')" class="flex items-center gap-1.5 text-[10px] text-success font-medium mb-2 px-0.5">
              <svg class="w-3 h-3 text-success shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
              </svg>
              <span>Новый токен: скопируйте сейчас (в целях безопасности он больше не будет показан).</span>
            </div>

            <!-- Token Management Action Links -->
            <div class="flex items-center justify-between text-xs pt-1 border-t border-border/40">
              <button @click="openRotateConfirm(site)"
                      type="button"
                      class="text-fg-muted hover:text-primary active:scale-95 transition-colors flex items-center gap-1.5 py-0.5">
                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                <span>Сгенерировать новый</span>
              </button>
              <button @click="openRevokeConfirm(site)"
                      type="button"
                      class="text-fg-muted hover:text-error active:scale-95 transition-colors flex items-center gap-1.5 py-0.5">
                <svg class="w-3.5 h-3.5 text-error/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                <span>Отозвать токен</span>
              </button>
            </div>
          </div>
        </template>

        <!-- Token Revoked -->
        <template x-if="!site.api_token">
          <div class="flex items-center justify-between gap-3 py-1">
            <div class="flex items-center gap-2 text-warning text-xs">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
              </svg>
              <span class="text-fg-muted text-[11px]">Токен отозван. Заказы не принимаются.</span>
            </div>
            <button @click="openRotateConfirm(site)"
                    type="button"
                    class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-hover active:scale-95 transition-all shrink-0">
              Выпустить токен
            </button>
          </div>
        </template>
      </div>
    </div>
  </template>
</div>

<!-- Pagination Controls -->
<div x-show="sitesLastPage > 1" class="flex items-center justify-between gap-2 mt-4 mb-6 pt-3 border-t border-border">
  <button type="button"
          @click="prevPage()"
          :disabled="sitesPage <= 1 || sitesLoading"
          class="px-3 py-1.5 rounded-xl bg-surface2 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:border-primary/50 transition-all flex items-center gap-1 text-fg active:scale-95">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
    </svg>
    Назад
  </button>

  <div class="flex items-center gap-1">
    <template x-for="p in paginationPages" :key="p">
      <div>
        <template x-if="p === '...'">
          <span class="px-1 text-xs text-fg-subtle font-mono">...</span>
        </template>
        <template x-if="p !== '...'">
          <button type="button"
                  @click="goToPage(p)"
                  :disabled="sitesLoading"
                  class="w-7 h-7 rounded-lg text-xs font-medium transition-all flex items-center justify-center active:scale-95"
                  :class="sitesPage === p ? 'bg-primary text-white font-bold shadow-sm' : 'bg-surface text-fg-muted hover:text-fg'"
                  x-text="p">
          </button>
        </template>
      </div>
    </template>
  </div>

  <button type="button"
          @click="nextPage()"
          :disabled="sitesPage >= sitesLastPage || sitesLoading"
          class="px-3 py-1.5 rounded-xl bg-surface2 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:border-primary/50 transition-all flex items-center gap-1 text-fg active:scale-95">
    Вперед
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
    </svg>
  </button>
</div>

{{-- Модалка создания сайта --}}
@component('tma.partials.modal', ['show' => 'showCreateSiteModal'])
  <h3 class="font-display font-bold text-lg mb-2">Добавить сайт</h3>
  <p class="text-fg-muted text-xs mb-4">Создает сайт и генерирует уникальный API-токен для интеграции.</p>
  <div class="space-y-3 mb-6">
    <div>
      <label class="block text-xs text-fg-subtle mb-1">Название сайта *</label>
      <input type="text" x-model="newSite.name" placeholder="Например: Главный интернет-магазин" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary">
    </div>
    <div>
      <label class="block text-xs text-fg-subtle mb-1">Домен сайта *</label>
      <input type="text" x-model="newSite.domain" placeholder="example.com" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary">
    </div>
    <div>
      <label class="block text-xs text-fg-subtle mb-1">Закупочная цена по умолчанию (%)</label>
      <input type="number" step="0.01" min="0" max="100" x-model="newSite.default_purchase_percent" placeholder="{{ config('orders.default_purchase_percent', 15) }} (по умолчанию {{ config('orders.default_purchase_percent', 15) }}%)" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary">
      <p class="text-[11px] text-fg-muted mt-1">Процент от розничной цены для расчета закупки, если не указана в заказе.</p>
    </div>
    <div class="flex items-center gap-2 pt-1">
      <input type="checkbox" id="new_site_active" x-model="newSite.is_active" class="rounded border-border text-primary focus:ring-0">
      <label for="new_site_active" class="text-xs text-fg">Сайт активен (принимать заказы)</label>
    </div>
  </div>
  @component('tma.partials.modal-actions')
    <button @click="showCreateSiteModal = false" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium">Отмена</button>
    <button @click="createSite()" :disabled="savingSite" class="flex-1 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium disabled:opacity-50" x-text="savingSite ? 'Создание...' : 'Создать'"></button>
  @endcomponent
@endcomponent

{{-- Модалка редактирования сайта --}}
@component('tma.partials.modal', ['show' => 'showEditSiteModal'])
  <h3 class="font-display font-bold text-lg mb-2">Редактировать сайт</h3>
  <div class="space-y-3 mb-6">
    <div>
      <label class="block text-xs text-fg-subtle mb-1">Название сайта</label>
      <input type="text" x-model="editingSite.name" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg focus:outline-none focus:border-primary">
    </div>
    <div>
      <label class="block text-xs text-fg-subtle mb-1">Домен</label>
      <input type="text" x-model="editingSite.domain" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg focus:outline-none focus:border-primary">
    </div>
    <div>
      <label class="block text-xs text-fg-subtle mb-1">Закупочная цена по умолчанию (%)</label>
      <input type="number" step="0.01" min="0" max="100" x-model="editingSite.default_purchase_percent" placeholder="{{ config('orders.default_purchase_percent', 15) }} (по умолчанию {{ config('orders.default_purchase_percent', 15) }}%)" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary">
      <p class="text-[11px] text-fg-muted mt-1">Процент от розничной цены для расчета закупки, если не указана в заказе.</p>
    </div>
    <div class="flex items-center gap-2 pt-1">
      <input type="checkbox" id="edit_site_active" x-model="editingSite.is_active" class="rounded border-border text-primary focus:ring-0">
      <label for="edit_site_active" class="text-xs text-fg">Сайт активен</label>
    </div>
  </div>
  @component('tma.partials.modal-actions')
    <button @click="showEditSiteModal = false" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium">Отмена</button>
    <button @click="updateSite()" :disabled="savingSite" class="flex-1 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium disabled:opacity-50" x-text="savingSite ? 'Сохранение...' : 'Сохранить'"></button>
  @endcomponent
@endcomponent

@include('tma.partials.nav')
@endsection
