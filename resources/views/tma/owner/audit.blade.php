@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-6">
  <div class="flex items-center gap-3">
    <template x-if="orderNumber">
      <button @click="goBack()" class="w-10 h-10 rounded-full bg-surface flex items-center justify-center active:scale-95 transition-transform" aria-label="Назад к заказу">
        <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
      </button>
    </template>
    <div>
      <p class="text-fg-subtle text-xs" x-text="orderNumber ? ('Заказ #' + orderNumber) : 'Журнал'"></p>
      <h1 class="font-display font-bold text-xl">Аудит</h1>
    </div>
  </div>
  <span class="text-xs text-fg-subtle" x-text="(logsTotal || logs.length) + ' ' + pluralize(logsTotal || logs.length, 'запись', 'записи', 'записей')"></span>
</div>

<div class="flex gap-2 mb-4 overflow-x-auto pb-2 items-center">
  <button @click="setFilter('all')"
    :class="currentFilter === 'all' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">Все</button>
  <div class="relative">
    <input type="text" x-model="orderNumber" @keyup.enter="loadLogs(true)" placeholder="№ заказа..."
      class="w-24 pl-2 pr-7 py-1.5 text-xs rounded-lg border border-border bg-surface text-fg placeholder:text-fg-muted focus:outline-none focus:border-primary">
    <button @click="orderNumber = ''; loadLogs(true)" x-show="orderNumber"
      class="absolute right-1.5 top-1/2 -translate-y-1/2 text-fg-muted hover:text-fg">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
  <button @click="setFilter('creates')"
    :class="currentFilter === 'creates' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">Создания</button>
  <button @click="setFilter('updates')"
    :class="currentFilter === 'updates' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">Изменения</button>
  <button @click="setFilter('statuses')"
    :class="currentFilter === 'statuses' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">Статусы</button>
  <button @click="setFilter('auth')"
    :class="currentFilter === 'auth' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">Безопасность</button>
  <button @click="setFilter('exports')"
    :class="currentFilter === 'exports' ? 'bg-primary/20 text-primary border border-primary' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">Экспорт</button>
  <button @click="setFilter('edited')"
    :class="currentFilter === 'edited' ? 'bg-warning/20 text-warning border border-warning' : 'bg-surface text-fg-muted'"
    class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap">⚠️ Изменённые</button>
</div>

<div class="space-y-2 mb-4">
  <template x-if="logsLoading && logs.length === 0">
    <div class="py-12 flex flex-col items-center justify-center gap-3">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-xs text-fg-muted">Загрузка...</p>
    </div>
  </template>

  <template x-if="!logsLoading && logs.length === 0">
    <p class="text-fg-subtle text-sm text-center py-8">Нет записей</p>
  </template>

  <template x-for="log in logs" :key="log.id">
    <a :href="'/tma/audit/' + log.id" class="flex items-center gap-3 bg-surface rounded-2xl p-4 active:scale-[0.98] transition-transform">
      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs font-medium" :class="getActionColor(log.action_type)" x-text="getActionLabel(log.action_type)"></span>
          <span class="text-xs text-fg-subtle" x-text="new Date(log.created_at).toLocaleString('ru-RU')"></span>
        </div>
        <div class="text-sm text-fg truncate" x-text="getLogTitle(log)"></div>
        <div class="text-xs text-fg-muted mt-0.5" x-text="log.actor?.system_name || 'Система'"></div>
        <template x-if="log.field_name">
          <div class="text-xs text-fg-subtle mt-0.5 truncate">
            <span x-text="getFieldLabel(log.field_name)"></span>:
            <span x-text="log.old_value_display ?? log.old_value"></span> → <span x-text="log.new_value_display ?? log.new_value"></span>
          </div>
        </template>
      </div>
      <svg class="w-4 h-4 text-fg-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
      </svg>
    </a>
  </template>
</div>

  @component('tma.partials.pagination-load-more', [
    'show' => 'logsHasMore',
    'loading' => 'logsLoading',
    'hasItems' => 'logs.length > 0',
    'action' => 'loadLogs()',
    'scrollTarget' => 'orders-list'
  ])
  @endcomponent

  @include('tma.partials.nav')
@endsection
