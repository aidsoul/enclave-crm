@php
  $item = $item ?? 'item';
  $qty = $qty ?? "({$item}.qty || 1)";
  $size = $size ?? 'sm';
  $class = $class ?? '';
  $showSingle = $showSingle ?? true;

  $sizeClasses = match($size) {
    'xs' => 'text-[10px] px-1 py-0.2 rounded leading-tight',
    'md' => 'text-xs px-2 py-0.5 rounded-md leading-normal',
    default => 'text-[11px] px-1.5 py-0.5 rounded-md leading-normal',
  };
@endphp

<span x-show="{{ $showSingle ? "({$qty}) >= 1" : "({$qty}) > 1" }}"
      class="inline-flex items-center gap-0.5 border tabular-nums shrink-0 select-none transition-colors {{ $sizeClasses }} {{ $class }}"
      :class="({{ $qty }}) > 1 ? 'bg-primary/15 text-primary border-primary/30 font-semibold' : 'bg-surface2 text-fg-muted border-border font-medium'"
      :title="'Количество: ' + ({{ $qty }}) + ' шт.'">
  <span x-text="({{ $qty }}) + ' шт.'"></span>
</span>
