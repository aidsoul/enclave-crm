@extends('tma.layout')

@section('content')
<div class="flex items-center gap-3 mb-6">
  <a href="/tma" class="w-8 h-8 flex items-center justify-center rounded-lg bg-surface text-fg-muted hover:text-fg transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
    </svg>
  </a>
  <div>
    <p class="text-fg-subtle text-xs">Финансовые показатели</p>
    <h1 class="font-display font-bold text-lg" x-text="metricLabel"></h1>
  </div>
</div>


<div class="flex gap-2 mb-4">
  <button class="flex-1 px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted"
          :class="days === 7 && !isCustom ? 'bg-primary/20 border-primary text-primary' : ''"
          @click="setDays(7)">7 дней</button>
  <button class="flex-1 px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted"
          :class="days === 30 && !isCustom ? 'bg-primary/20 border-primary text-primary' : ''"
          @click="setDays(30)">30 дней</button>
  <button class="flex-1 px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted"
          :class="days === 90 && !isCustom ? 'bg-primary/20 border-primary text-primary' : ''"
          @click="setDays(90)">90 дней</button>
  <button type="button"
          class="px-3 py-2 text-xs font-medium rounded-lg bg-surface text-fg-muted transition-colors flex items-center justify-center hover:text-fg"
          :class="isCustom ? 'bg-primary/20 border-primary text-primary font-semibold shadow-sm' : ''"
          title="Выбрать период"
          @click="openDatePicker()">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
    </svg>
  </button>
</div>

{{-- Индикатор выбранного произвольного периода --}}
<div x-show="isCustom && customFrom && customTo"
     class="flex items-center justify-between px-3 py-1.5 bg-primary/10 border border-primary/30 rounded-lg text-xs text-primary mb-4">
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

<div class="bg-surface rounded-xl p-4 mb-4">
  <div class="flex items-center justify-between mb-2">
    <span class="text-fg-subtle text-xs">Итого за период</span>
    <span class="text-xs font-medium" :class="trendClass" x-text="trendText"></span>
  </div>
  <p class="text-2xl font-display font-bold" x-text="formatPrice(total)"></p>
</div>

<div class="bg-surface rounded-xl p-4 mb-4">
  <canvas id="financialChart" height="220"></canvas>
</div>

<div class="bg-surface rounded-xl p-4">
  <h2 class="font-display font-semibold text-sm mb-3">Показатели по дням</h2>
  <div class="space-y-2 max-h-64 overflow-y-auto">
    <template x-for="item in reversed" :key="item.label">
      <div class="flex items-center justify-between p-2 bg-bg rounded-lg">
        <span class="text-xs text-fg-subtle" x-text="item.label"></span>
        <span class="text-sm font-medium" x-text="formatPrice(item.value)"></span>
      </div>
    </template>
    <template x-if="reversed.length === 0">
      <p class="text-fg-subtle text-xs text-center py-4">Нет данных</p>
    </template>
  </div>
</div>

@include('tma.partials.date-range-modal')
@include('tma.partials.nav')
@endsection
