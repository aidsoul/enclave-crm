@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-4">
  <div>
    <p class="text-fg-subtle text-xs">Заявки с сайта</p>
    <h1 class="font-display font-bold text-xl" x-text="(leadsTotal || leads.length) + ' ' + pluralize(leadsTotal || leads.length, 'заявка', 'заявки', 'заявок')"></h1>
  </div>
  <template x-if="customerIdFilter">
    <button @click="clearCustomerFilter()" class="flex items-center gap-1 px-2.5 py-1 bg-surface text-xs rounded-lg text-fg-muted hover:text-fg">
      <span>Клиент #<span x-text="customerIdFilter"></span></span>
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
    </button>
  </template>
</div>

<!-- Search -->
<div class="mb-3">
  <div class="relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
    </svg>
    <input
      type="text"
      x-model="search"
      @input="onSearch()"
      placeholder="Поиск по телефону, имени, email..."
      class="w-full pl-10 pr-4 py-2.5 bg-surface rounded-xl text-sm focus:border-primary focus:outline-none"
    >
  </div>
</div>

<!-- Status Filters -->
<div class="flex gap-2 mb-4 overflow-x-auto pb-1 text-xs">
  <button @click="setStatusFilter('')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="statusFilter === '' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'">
    Все
  </button>
  <button @click="setStatusFilter('new')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="statusFilter === 'new' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'">
    Новые
  </button>
  <button @click="setStatusFilter('in_progress')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="statusFilter === 'in_progress' ? 'bg-warning/20 text-warning border border-warning' : 'bg-surface text-fg-muted'">
    В работе
  </button>
  <button @click="setStatusFilter('converted')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="statusFilter === 'converted' ? 'bg-success/20 text-success border border-success' : 'bg-surface text-fg-muted'">
    В заказе
  </button>
  <button @click="setStatusFilter('rejected')" class="px-3 py-1.5 font-medium rounded-lg whitespace-nowrap transition-colors"
          :class="statusFilter === 'rejected' ? 'bg-error/20 text-error border border-error' : 'bg-surface text-fg-muted'">
    Отклонённые
  </button>
</div>

<!-- Leads List -->
<div id="leads-list" :class="isDesktopMode ? 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mb-6' : 'space-y-3 mb-6 pb-20'">
  <template x-if="loading && leads.length === 0">
    <div class="py-12 flex flex-col items-center justify-center gap-3">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-xs text-fg-muted">Загрузка заявок...</p>
    </div>
  </template>

  <template x-if="!loading && leads.length === 0">
    <div class="bg-surface rounded-xl p-8 text-center">
      <p class="text-fg-subtle text-sm">Заявки не найдены</p>
    </div>
  </template>

  <template x-for="lead in leads" :key="lead.id">
    <div class="bg-surface rounded-2xl p-4 space-y-2.5 transition-shadow hover:shadow-sm">
      <!-- Header: Type, Status, Date -->
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-primary/10 text-primary border border-primary/20 whitespace-nowrap" x-text="lead.type_label"></span>
          <span class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold whitespace-nowrap"
                :class="{
                  'bg-primary/10 text-primary border border-primary/20': lead.status === 'new',
                  'bg-warning/10 text-warning border border-warning/20': lead.status === 'in_progress',
                  'bg-success/10 text-success border border-success/20': lead.status === 'converted',
                  'bg-error/10 text-error border border-error/20': lead.status === 'rejected'
                }"
                x-text="lead.status_label">
          </span>
          <template x-if="lead.extra_data && lead.extra_data._antifraud && lead.extra_data._antifraud.is_suspicious">
            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-warning/15 text-warning border border-warning/30 whitespace-nowrap">
              ⚠️ Спам-риск
            </span>
          </template>
        </div>
        <span class="text-xs text-fg-subtle whitespace-nowrap shrink-0" x-text="formatDate(lead.created_at)"></span>
      </div>

      <!-- Site & Network Info -->
      <div class="flex items-center justify-between text-[11px] text-fg-subtle -mt-1">
        <template x-if="lead.site">
          <span x-text="lead.site.name"></span>
        </template>
        <template x-if="lead.extra_data && lead.extra_data._antifraud && lead.extra_data._antifraud.client_ip">
          <span class="font-mono text-[10px]" x-text="'IP: ' + lead.extra_data._antifraud.client_ip"></span>
        </template>
      </div>

      <!-- Client Name & Profile Link -->
      <div class="flex items-center justify-between gap-2 pt-1">
        <div class="font-semibold text-sm text-fg truncate" x-text="lead.name || 'Без имени'"></div>
        <template x-if="lead.customer_id">
          <a :href="'/tma/customers/' + lead.customer_id" class="text-xs text-primary hover:underline shrink-0 flex items-center gap-0.5 font-medium">
            <span>Профиль</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
          </a>
        </template>
      </div>

      <!-- Phone & Email -->
      <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
        <template x-if="lead.phone">
          <a :href="getTelLink(lead.phone)" class="inline-flex items-center gap-1.5 font-medium text-success hover:underline whitespace-nowrap">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
            <span x-text="lead.formatted_phone || formatPhone(lead.phone)"></span>
          </a>
        </template>
        <template x-if="lead.email">
          <span class="text-fg-subtle truncate text-xs" x-text="lead.email"></span>
        </template>
      </div>

      <!-- Product (if present) -->
      <template x-if="lead.product_name">
        <div class="bg-surface2/60  rounded-lg px-2.5 py-1.5 text-xs leading-snug space-y-0.5">
          <div>
            <span class="text-fg-subtle mr-1">Товар:</span>
            <span class="font-medium text-fg break-words" x-text="lead.product_name"></span>
          </div>
          <template x-if="lead.product_sku">
            <div class="text-[11px] text-fg-subtle">
              <span>артикул:</span>
              <span class="font-mono text-fg ml-1" x-text="lead.product_sku"></span>
            </div>
          </template>
        </div>
      </template>

      <!-- Message (if present) -->
      <template x-if="lead.message">
        <div class="bg-surface2/40  rounded-lg p-2.5 text-xs">
          <p class="text-fg-subtle text-[10px] uppercase font-semibold tracking-wider mb-1">Сообщение клиента</p>
          <div class="max-h-28 overflow-y-auto overscroll-contain pr-1 scrollable-text">
            <p class="text-fg whitespace-pre-wrap break-words leading-relaxed" x-text="lead.message"></p>
          </div>
        </div>
      </template>

      <!-- Page URL (if present) -->
      <template x-if="lead.page_url">
        <div class="text-[11px] text-fg-subtle truncate flex items-center gap-1">
          <span class="shrink-0">Страница:</span>
          <a :href="lead.page_url" target="_blank" rel="noopener" class="text-primary hover:underline truncate" x-text="lead.page_title || lead.page_url"></a>
        </div>
      </template>

      <!-- Actions Footer -->
      <div class="pt-2 border-t border-border flex items-center justify-between gap-2">
        <!-- Status Changer -->
        <div class="flex items-center gap-1.5 text-xs">
          <template x-if="lead.status === 'new'">
            <button @click="updateStatus(lead, 'in_progress')" class="px-2.5 py-1 bg-surface2 hover:bg-surface2/80 rounded-lg text-fg transition-colors">
              В работу
            </button>
          </template>
          <template x-if="lead.status !== 'rejected' && lead.status !== 'converted'">
            <button @click="updateStatus(lead, 'rejected')" class="px-2 py-1 text-fg-subtle hover:text-error text-xs transition-colors">
              Отклонить
            </button>
          </template>
        </div>

        <!-- Convert / View Order Button -->
        <div>
          <template x-if="lead.converted_order_id">
            <a :href="'/tma/orders/' + lead.converted_order_id" class="inline-flex items-center gap-1 px-3 py-1.5 bg-success/15 text-success hover:bg-success/20 rounded-xl text-xs font-semibold">
              <span>Заказ #<span x-text="lead.converted_order_id"></span></span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
          </template>
          <template x-if="!lead.converted_order_id">
            <button @click="convertToOrder(lead)" :disabled="convertingId === lead.id" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-primary-fg hover:opacity-90 rounded-xl text-xs font-semibold disabled:opacity-50 transition-opacity">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
              <span x-text="convertingId === lead.id ? 'Создание...' : 'Создать заказ'"></span>
            </button>
          </template>
        </div>
      </div>
    </div>
  </template>

  <!-- Pagination -->
  <template x-if="hasMore && leads.length > 0">
    <div class="text-center pt-2">
      <button @click="loadMore()" :disabled="loading" class="w-full py-2.5 bg-surface rounded-xl text-xs font-medium text-fg hover:border-primary disabled:opacity-50 flex items-center justify-center gap-2">
        <template x-if="!loading">
          <span>Загрузить ещё</span>
        </template>
        <template x-if="loading">
          <span class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-full border-2 border-primary border-t-transparent animate-spin inline-block"></span>
            <span>Загрузка...</span>
          </span>
        </template>
      </button>
    </div>
  </template>
</div>

<!-- Order Created Modal -->
@component('tma.partials.modal', ['show' => 'createdOrderModal', 'padding' => 'p-5', 'maxWidth' => 'max-w-sm'])
  <div class="text-center space-y-3">
    <div class="mx-auto w-12 h-12 rounded-full bg-success/15 text-success flex items-center justify-center">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
      </svg>
    </div>
    <h3 class="font-display font-bold text-base text-fg">Заказ успешно создан</h3>
    <p class="text-xs text-fg-muted">Создан заказ #<span class="font-bold text-fg" x-text="createdOrderId"></span>. Открыть его для редактирования и оформления доставки?</p>

    <div class="pt-2 flex items-center gap-2">
      <button type="button" @click="createdOrderModal = false" class="flex-1 py-2 px-3 bg-surface2 hover:bg-surface2/80 text-fg text-xs font-semibold rounded-xl border border-border transition-colors">
        Остаться
      </button>
      <a :href="'/tma/orders/' + createdOrderId + '/edit'" class="flex-1 py-2 px-3 bg-primary hover:bg-primary-hover text-white text-xs font-semibold rounded-xl text-center transition-colors">
        Открыть заказ
      </a>
    </div>
  </div>
@endcomponent

@include('tma.partials.nav')
@endsection
