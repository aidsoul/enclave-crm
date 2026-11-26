@if(!$user || !$user->isCourier())
<div x-data="{
       show: false,
       loading: false,
       error: null,
       product: null,
       imgError: false,
       _abortController: null,
       async open(productId) {
         if (!productId || window.__tmaApp?.isCourier || window.__tmaApp?.user?.role === 'courier') return;
         if (this._abortController) {
           this._abortController.abort();
         }
         this._abortController = new AbortController();
         this.show = true;
         this.loading = true;
         this.error = null;
         this.product = null;
         this.imgError = false;

         try {
           window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
         } catch (_) {}

         try {
           const token = localStorage.getItem('tma_token');
           const headers = { 'Accept': 'application/json' };
           if (token) headers['Authorization'] = 'Bearer ' + token;

           const res = await fetch('/api/products/' + encodeURIComponent(productId), {
             headers,
             signal: this._abortController.signal
           });
           if (!res.ok) {
             if (res.status === 404) {
               throw new Error('Товар не найден в базе данных');
             }
             if (res.status === 403 || res.status === 401) {
               throw new Error('Недостаточно прав для просмотра товара');
             }
             throw new Error('Не удалось загрузить данные о товаре');
           }

           const data = await res.json();
           this.product = data.product || data;
         } catch (e) {
           if (e.name === 'AbortError') return;
           this.error = e.message || 'Ошибка загрузки данных о товаре';
         } finally {
           this.loading = false;
         }
       },
       close() {
         if (this._abortController) {
           this._abortController.abort();
         }
         this.show = false;
       },
       formatPrice(val) {
         const num = parseFloat(val);
         const symbol = window.__APP_CURRENCY_SYMBOL__ || 'Br';
         if (isNaN(num)) return '0 ' + symbol;
         return num.toLocaleString('ru-RU', { maximumFractionDigits: 2 }) + ' ' + symbol;
       }
     }"
     x-init="$watch('show', v => { if (!v && _abortController) { _abortController.abort(); } })"
     x-on:show-product-detail.window="!(window.__tmaApp?.isCourier || window.__tmaApp?.user?.role === 'courier') && open($event.detail?.productId || $event.detail)"
     class="contents">

  @component('tma.partials.modal', ['show' => 'show', 'maxWidth' => 'max-w-lg', 'padding' => 'p-4 sm:p-5', 'zIndex' => 'z-[80]'])
    <div class="flex items-center justify-between pb-3 mb-3 border-b border-border">
      <div class="min-w-0 pr-2">
        <h3 class="font-display font-bold text-base text-fg truncate">Карточка товара</h3>
        <p class="text-xs text-fg-subtle truncate" x-show="product?.sku" x-text="product?.sku"></p>
      </div>
      <button type="button" @click="close()" class="w-8 h-8 rounded-full bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg active:scale-95 transition-transform shrink-0" aria-label="Закрыть">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div x-show="loading" class="py-12 flex flex-col items-center justify-center gap-3">
      <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
      <p class="text-xs text-fg-muted">Загрузка информации о товаре...</p>
    </div>

    <div x-show="error && !loading" class="py-6 text-center">
      <div class="w-12 h-12 rounded-full bg-error/15 text-error flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
        </svg>
      </div>
      <p class="text-sm font-medium text-fg" x-text="error"></p>
    </div>

    <div x-show="product && !loading" class="space-y-4 max-h-[70vh] overflow-y-auto overscroll-contain pr-1">
      <div class="w-full h-44 sm:h-52 rounded-xl overflow-hidden bg-surface2 flex items-center justify-center relative">
        <template x-if="product?.image_url && !imgError">
          <img :src="product.image_url" :alt="product.name" class="w-full h-full object-contain p-2" x-on:error="imgError = true">
        </template>
        <div x-show="!product?.image_url || imgError" class="w-full h-full flex flex-col items-center justify-center text-fg-muted/40">
          <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
          </svg>
          <span class="text-xs mt-1 text-fg-subtle">Нет изображения</span>
        </div>
      </div>

      <div>
        <div class="flex items-center gap-2 flex-wrap mb-1.5">
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="product?.is_active ? 'bg-success/15 text-success border border-success/30' : 'bg-fg-muted/15 text-fg-muted border border-border'"
                x-text="product?.is_active ? 'Активен' : 'Архивирован'"></span>

          <template x-if="product?.site">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary border border-primary/20"
                  x-text="product.site.display_name || product.site.domain || product.site.name"></span>
          </template>

          <template x-if="product?.external_id">
            <span class="text-xs text-fg-subtle" x-text="'Внешний ID: ' + product.external_id"></span>
          </template>
        </div>

        <h4 class="font-display font-semibold text-base text-fg leading-snug" x-text="product?.name"></h4>
      </div>

      <div class="grid grid-cols-2 gap-2 bg-surface2/60 rounded-xl p-3 text-xs">
        <div>
          <span class="text-fg-subtle block mb-0.5">Цена продажи</span>
          <span class="font-bold text-sm text-fg" x-text="formatPrice(product?.price || 0)"></span>
        </div>

        <template x-if="product?.sku">
          <div>
            <span class="text-fg-subtle block mb-0.5">Артикул</span>
            <span class="font-mono font-medium text-fg" x-text="product.sku"></span>
          </div>
        </template>

        <template x-if="product?.cost_price !== null && product?.cost_price !== undefined">
          <div>
            <span class="text-fg-subtle block mb-0.5">Себестоимость</span>
            <span class="font-medium text-fg" x-text="formatPrice(product.cost_price)"></span>
          </div>
        </template>

        <template x-if="product?.margin !== null && product?.margin !== undefined">
          <div>
            <span class="text-fg-subtle block mb-0.5">Маржа</span>
            <span class="font-bold" :class="product.margin >= 0 ? 'text-success' : 'text-error'" x-text="formatPrice(product.margin)"></span>
          </div>
        </template>
      </div>

      <template x-if="product?.url">
        <div>
          <a :href="product.url" target="_blank" rel="noopener noreferrer"
             class="w-full py-2.5 px-3 bg-surface2 hover:bg-surface2/80 hover:border-primary/50 rounded-xl text-xs font-semibold text-fg flex items-center justify-center gap-2 active:scale-[0.99] transition-all">
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
            </svg>
            <span>Открыть страницу товара на сайте</span>
          </a>
        </div>
      </template>

      <template x-if="product?.description">
        <div class="bg-surface2/40/80 rounded-xl p-3">
          <span class="text-xs font-semibold text-fg-subtle block mb-1">Описание</span>
          <p class="text-xs text-fg leading-relaxed whitespace-pre-line" x-text="product.description"></p>
        </div>
      </template>

      <template x-if="product?.variants && product.variants.length > 0">
        <div class="space-y-2 pt-2 border-t border-border">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-fg">Варианты товара</span>
            <span class="text-xs text-fg-subtle" x-text="product.variants.length + ' ' + (product.variants.length === 1 ? 'вариант' : (product.variants.length < 5 ? 'варианта' : 'вариантов'))"></span>
          </div>

          <div class="space-y-2">
            <template x-for="variant in product.variants" :key="variant.id">
              <div class="bg-surface2/60 rounded-xl p-2.5 text-xs space-y-1.5">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <span class="font-semibold text-fg block truncate" x-text="variant.title || 'Вариант #' + variant.id"></span>
                    <span x-show="variant.sku" class="font-mono text-[11px] text-fg-muted" x-text="variant.sku"></span>
                  </div>
                  <div class="text-right shrink-0">
                    <span class="font-bold text-fg block" x-text="formatPrice(variant.price)"></span>
                    <span x-show="variant.purchase_price !== null && variant.purchase_price !== undefined" class="text-[10px] text-fg-subtle block" x-text="'Закупка: ' + formatPrice(variant.purchase_price)"></span>
                  </div>
                </div>

                <template x-if="variant.attributes && Object.keys(variant.attributes).length > 0">
                  <div class="flex flex-wrap gap-1 pt-1">
                    <template x-for="(val, key) in variant.attributes" :key="key">
                      <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-surface text-fg-muted">
                        <span class="text-fg-subtle mr-1" x-text="key + ':'"></span>
                        <span class="font-medium text-fg" x-text="val"></span>
                      </span>
                    </template>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>

    <div class="pt-3 border-t border-border mt-3 flex justify-end">
      <button type="button" @click="close()" class="px-5 py-2.5 bg-surface2 text-fg rounded-lg text-sm font-medium hover:bg-surface2/80 active:scale-95 transition-transform">
        Закрыть
      </button>
    </div>
  @endcomponent
</div>
@endif

