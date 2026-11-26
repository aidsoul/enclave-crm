@php
  $loading = $loading ?? false;
  $action = $action ?? '';
  $label = $label ?? 'Загрузить ещё';
  $scrollTarget = $scrollTarget ?? null;
  $show = $show ?? 'true';
  $hasItems = $hasItems ?? null;
  $visibleCondition = $hasItems
    ? "($hasItems) && (($show) || ($loading))"
    : "($show) || ($loading)";
  $btnCondition = $hasItems
    ? "($hasItems) && !($loading) && ($show)"
    : "!($loading) && ($show)";
  $loadingCondition = $hasItems
    ? "($hasItems) && ($loading)"
    : "($loading)";
@endphp

<div x-show="{{ $visibleCondition }}" class="my-6 flex flex-col items-center justify-center">
  <button x-show="{{ $btnCondition }}"
          type="button"
          @click="{{ $action }}"
          class="group inline-flex items-center justify-center gap-2.5 px-8 py-3 bg-surface hover:border-primary/50 text-fg hover:text-primary rounded-xl text-xs font-semibold shadow-sm hover:shadow transition-all active:scale-[0.98] w-full sm:w-auto min-w-[240px]">
    <span>{{ $label }}</span>
    <svg class="w-4 h-4 text-fg-subtle group-hover:text-primary group-hover:translate-y-0.5 transition-all" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
    </svg>
  </button>

  <div x-show="{{ $loadingCondition }}" class="flex items-center justify-center gap-2.5 py-3 text-fg-muted text-xs">
    <div class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    <span>Загрузка...</span>
  </div>
</div>
