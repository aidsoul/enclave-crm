@php
  $item = $item ?? 'item';
  $showPrice = $showPrice ?? true;
  $showSku = $showSku ?? true;
  $showId = $showId ?? true;
  $showPurchasePrice = $showPurchasePrice ?? true;
  $imageSize = $imageSize ?? 'md';
  $interactive = $interactive ?? true;

  $imgBoxClasses = $imageSize === 'sm' ? 'w-7 h-7 rounded' : 'w-11 h-11 rounded-lg';
  $imgIconClasses = $imageSize === 'sm' ? 'w-3.5 h-3.5' : 'w-5 h-5';
  $textSize = $imageSize === 'sm' ? 'text-xs' : 'text-sm';
@endphp

<li @if($interactive)
      :class="(!isCourier && {{ $item }}.product_id) ? 'hover:bg-surface2/60 cursor-pointer active:scale-[0.99] transition-all' : ''"
      :title="(!isCourier && {{ $item }}.product_id) ? 'Нажмите для просмотра карточки товара' : ''"
      @click="!isCourier && {{ $item }}.product_id && $dispatch('show-product-detail', { productId: {{ $item }}.product_id })"
    @endif
    class="flex items-start justify-between gap-2.5 {{ $textSize }} p-1.5 rounded-lg select-none">
  <div class="flex items-start gap-2.5 min-w-0 flex-1">
    <div class="{{ $imgBoxClasses }} shrink-0 overflow-hidden bg-surface2 flex items-center justify-center relative mt-0.5">
      <template x-if="{{ $item }}.image_url">
        <img :src="{{ $item }}.image_url" :alt="{{ $item }}.name" class="w-full h-full object-cover" x-on:error="$el.style.display='none'">
      </template>
      <div x-show="!{{ $item }}.image_url" class="w-full h-full flex items-center justify-center text-fg-muted/50 bg-surface2">
        <svg class="{{ $imgIconClasses }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
        </svg>
      </div>
    </div>
    <div class="min-w-0 flex-1">
      <span class="text-fg font-medium block leading-snug break-words"
            @if($interactive) :class="(!isCourier && {{ $item }}.product_id) ? 'hover:text-primary transition-colors' : ''" @endif
            x-text="{{ $item }}.name"></span>
      <div class="mt-0.5 space-y-0.5 text-[11px] leading-tight">
        <div x-show="{{ $item }}.variant_title" class="text-primary font-medium" x-text="{{ $item }}.variant_title"></div>
        @if($showSku)
          <div x-show="{{ $item }}.sku" class="font-mono text-fg-muted" x-text="{{ $item }}.sku"></div>
        @endif
        @if($showId)
          <div x-show="{{ $item }}.external_product_id" class="text-fg-subtle truncate font-mono" x-text="'ID: ' + {{ $item }}.external_product_id" :title="{{ $item }}.external_product_id"></div>
        @endif
        @if($showPurchasePrice)
          <div x-show="{{ $item }}.purchase_price !== null && {{ $item }}.purchase_price !== undefined" class="text-fg-subtle tabular-nums" x-text="'Закупка: ' + formatPrice({{ $item }}.purchase_price)"></div>
        @endif
      </div>
    </div>
  </div>

  <div class="text-right shrink-0 flex flex-col items-end justify-start pt-0.5">
    @if($showPrice)
      <span class="text-fg font-semibold text-xs block tabular-nums" x-show="{{ $item }}.price !== null" x-text="formatPrice({{ $item }}.price)"></span>
      <div class="flex items-center justify-end gap-1 mt-0.5">
        @include('tma.partials.order-item-quantity', ['size' => 'xs', 'item' => $item])
        <span class="text-fg-subtle text-[10px] tabular-nums" x-show="{{ $item }}.qty && {{ $item }}.qty > 1 && {{ $item }}.price !== null" x-text="'(' + formatPrice({{ $item }}.price * {{ $item }}.qty) + ')'"></span>
      </div>
    @else
      @include('tma.partials.order-item-quantity', ['size' => 'sm', 'item' => $item])
    @endif
  </div>
</li>
