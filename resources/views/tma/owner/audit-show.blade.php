@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-6">
  <button @click="goBack()" class="w-10 h-10 rounded-full bg-surface flex items-center justify-center active:scale-95 transition-transform">
    <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
    </svg>
  </button>
  <h1 class="font-display font-bold text-lg" x-text="'Аудит #' + (log?.id || '')"></h1>
  <div class="w-10"></div>
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

<template x-if="!loading && !loadError && log">
  <div class="space-y-3 mb-4">
    <div class="bg-surface rounded-xl p-4">
      <h2 class="font-display font-semibold text-sm mb-3">Общее</h2>
      <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
          <span class="text-xs text-fg-subtle">Действие</span>
          <span class="text-xs font-medium" :class="getActionColor(log.action_type)" x-text="getActionLabel(log.action_type)"></span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-xs text-fg-subtle">Дата и время</span>
          <span class="text-xs text-fg" x-text="formatDate(log.created_at)"></span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-xs text-fg-subtle">Выполнил</span>
          <span class="text-xs text-fg" x-text="log.actor?.system_name || 'Система'"></span>
        </div>
        <div class="flex items-center justify-between gap-3" x-show="log.ip_address">
          <span class="text-xs text-fg-subtle">IP-адрес</span>
          <span class="text-xs text-fg font-mono" x-text="log.ip_address"></span>
        </div>
        <div class="flex items-center justify-between gap-3" x-show="log.user_agent">
          <span class="text-xs text-fg-subtle">Устройство</span>
          <span class="text-xs text-fg truncate max-w-[200px]" :title="log.user_agent" x-text="log.user_agent"></span>
        </div>
      </div>
    </div>

    <template x-if="log.order">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Заказ</h2>
        <a :href="'/tma/orders/' + log.order.id" class="text-sm text-primary" x-text="'Заказ #' + log.order.id"></a>
        <div class="flex items-center justify-between gap-3 mt-2">
          <span class="text-xs text-fg-subtle">Сайт</span>
          <span class="text-xs text-fg" x-text="formatSite(log.order.site)"></span>
        </div>
      </div>
    </template>

    <template x-if="log.lead">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Заявка с сайта</h2>
        <a :href="'/tma/leads'" class="text-sm text-primary" x-text="'Заявка #' + log.lead.id + (log.lead.type_label ? ' (' + log.lead.type_label + ')' : '')"></a>
        <div class="flex items-center justify-between gap-3 mt-2" x-show="log.lead.site">
          <span class="text-xs text-fg-subtle">Сайт</span>
          <span class="text-xs text-fg" x-text="formatSite(log.lead.site)"></span>
        </div>
        <div class="flex items-center justify-between gap-3 mt-2" x-show="log.lead.name">
          <span class="text-xs text-fg-subtle">Клиент</span>
          <span class="text-xs text-fg" x-text="log.lead.name"></span>
        </div>
        <div class="flex items-center justify-between gap-3 mt-2" x-show="log.lead.phone">
          <span class="text-xs text-fg-subtle">Телефон</span>
          <span class="text-xs text-fg" x-text="log.lead.formatted_phone || formatPhone(log.lead.phone)"></span>
        </div>
      </div>
    </template>

    <template x-if="log.target_user">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Сотрудник</h2>
        <div class="flex items-center justify-between gap-3">
          <span class="text-xs text-fg-subtle">Имя</span>
          <span class="text-xs font-medium text-fg" x-text="log.target_user.system_name"></span>
        </div>
        <div class="flex items-center justify-between gap-3 mt-2" x-show="log.target_user.role">
          <span class="text-xs text-fg-subtle">Роль</span>
          <span class="text-xs text-fg" x-text="log.target_user.role"></span>
        </div>
      </div>
    </template>

    <template x-if="log.customer">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Клиент</h2>
        <a :href="'/tma/customers/' + log.customer.id" class="text-sm text-primary font-medium" x-text="log.customer.name || ('Клиент #' + log.customer.id)"></a>
        <div class="flex items-center justify-between gap-3 mt-2" x-show="log.customer.phone">
          <span class="text-xs text-fg-subtle">Телефон</span>
          <span class="text-xs text-fg" x-text="log.customer.formatted_phone || formatPhone(log.customer.phone)"></span>
        </div>
      </div>
    </template>

    <template x-if="log.site">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Сайт</h2>
        <p class="text-sm font-medium text-fg" x-text="log.site.name"></p>
        <div class="flex items-center justify-between gap-3 mt-2" x-show="log.site.domain">
          <span class="text-xs text-fg-subtle">Домен</span>
          <span class="text-xs text-fg" x-text="log.site.domain"></span>
        </div>
      </div>
    </template>

    <template x-if="log.field_name">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Изменение</h2>
        <div class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-fg-subtle">Поле</span>
            <span class="text-xs font-medium text-fg" x-text="getFieldLabel(log.field_name)"></span>
          </div>

          <template x-if="['courier_id', 'delivery.user_id', 'status', 'total_price', 'purchase_price', 'is_active'].includes(log.field_name)">
            <div class="space-y-2">
              <div class="bg-surface2 rounded-lg p-3">
                <p class="text-[10px] text-fg-subtle mb-1 uppercase tracking-wide">Было</p>
                <p class="text-xs" :class="getOldValueClass(log.field_name)" x-text="formatOldValue(log.old_value_display ?? log.old_value)"></p>
              </div>
              <div class="bg-success-bg border border-success/20 rounded-lg p-3">
                <p class="text-[10px] text-success mb-1 uppercase tracking-wide">Стало</p>
                <p class="text-xs" :class="getNewValueClass(log.field_name)" x-text="formatNewValue(log.new_value_display ?? log.new_value)"></p>
              </div>
            </div>
          </template>

          <template x-if="!['courier_id', 'delivery.user_id', 'status', 'total_price', 'purchase_price', 'is_active'].includes(log.field_name)">
            <div class="bg-surface2 rounded-lg p-3">
              <p class="text-[10px] text-fg-subtle mb-2 uppercase tracking-wide">Было</p>
              <div class="json-tree max-h-56 overflow-y-auto overflow-x-auto pr-1 scrollable-text" x-html="formatJsonTree(log.old_value)"></div>
            </div>
          </template>

          <template x-if="!['courier_id', 'delivery.user_id', 'status', 'total_price', 'purchase_price', 'is_active'].includes(log.field_name)">
            <div class="bg-success-bg border border-success/20 rounded-lg p-3">
              <p class="text-[10px] text-success mb-2 uppercase tracking-wide">Стало</p>
              <div class="json-tree max-h-56 overflow-y-auto overflow-x-auto pr-1 scrollable-text" x-html="formatJsonTree(log.new_value)"></div>
            </div>
          </template>
        </div>
      </div>
    </template>

    <template x-if="!log.field_name && ['create', 'delete', 'panic'].includes(log.action_type)">
      <div class="bg-surface rounded-xl p-4">
        <h2 class="font-display font-semibold text-sm mb-3">Действие</h2>
        <p class="text-xs text-fg-muted" x-text="getActionDescription(log.action_type)"></p>
      </div>
    </template>
  </div>
</template>

  @include('tma.partials.nav')
@endsection
