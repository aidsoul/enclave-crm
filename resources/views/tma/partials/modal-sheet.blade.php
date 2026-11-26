@php
  $show = $show ?? 'false';
  $maxWidth = $maxWidth ?? 'max-w-[480px]';
  $padding = $padding ?? 'px-4 py-3';
  $sheetClass = $sheetClass ?? '';
@endphp

<div x-show="{{ $show }}"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="fixed bottom-[60px] left-1/2 -translate-x-1/2 w-full {{ $maxWidth }} z-40 {{ $padding }} {{ $sheetClass }}">
  {{ $slot }}
</div>
