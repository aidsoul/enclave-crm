@component('tma.partials.modal', ['show' => 'showProductModal', 'maxWidth' => 'max-w-lg', 'padding' => 'p-4 sm:p-5', 'zIndex' => 'z-50'])
  <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
    <div class="min-w-0 pr-2">
      <h3 class="font-bold text-base text-fg">Выбор товаров из каталога</h3>
      <div class="flex items-center gap-1.5 mt-0.5">
        <template x-if="form.site_id">
          <span class="text-xs text-fg-subtle truncate flex items-center gap-1">
            <span>Сайт:</span>
            <span class="font-medium text-primary" x-text="currentSiteLabel"></span>
          </span>
        </template>
        <template x-if="!form.site_id">
          <span class="text-xs text-error font-medium">Сайт не выбран</span>
        </template>
      </div>
    </div>
    <button type="button" @click="closeProductModal()" class="w-8 h-8 rounded-lg bg-surface2 flex items-center justify-center text-fg-muted hover:text-fg transition-colors shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <template x-if="!form.site_id">
    <div class="text-center py-6">
      <div class="w-12 h-12 rounded-full bg-primary/10 text-primary mx-auto flex items-center justify-center mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/>
        </svg>
      </div>
      <h4 class="font-bold text-sm text-fg mb-1">Сначала выберите сайт</h4>
      <p class="text-xs text-fg-subtle mb-4">Поиск и добавление товаров осуществляются только по товарам выбранного сайта.</p>
      <div class="max-h-48 overflow-y-auto space-y-1 text-left max-w-xs mx-auto border border-border rounded-lg p-1 bg-surface2">
        <template x-for="site in sites" :key="site.id">
          <button type="button" @click="selectSite(site.id); fetchCatalogProducts()" class="w-full text-left px-3 py-2 rounded text-sm hover:bg-surface text-fg flex items-center justify-between transition-colors">
            <span x-text="site.domain || site.name"></span>
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
          </button>
        </template>
      </div>
    </div>
  </template>

  <template x-if="form.site_id">
    <div>
      <div class="relative mb-3">
        <input type="text"
               id="catalog-search-input"
               x-model="catalogSearch"
               @input="searchCatalog()"
               placeholder="Поиск по названию, артикулу, варианту..."
               class="w-full pl-9 pr-8 py-2 bg-surface2 rounded-lg text-sm text-fg placeholder:text-fg-subtle focus:outline-none focus:border-primary">
        <svg class="w-4 h-4 text-fg-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <button type="button" x-show="catalogSearch" @click="catalogSearch = ''; fetchCatalogProducts()" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-fg-muted hover:text-fg p-0.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="flex items-center justify-between text-xs mb-2 text-fg-subtle px-0.5">
        <div>
          <span x-text="'Найдено: ' + (catalogProducts ? catalogProducts.length : 0)"></span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button"
                  x-show="catalogProducts && catalogProducts.length > 0"
                  @click="selectAllCatalogItems()"
                  class="text-primary hover:underline font-medium">
            Выбрать все
          </button>
          <button type="button"
                  x-show="catalogSelectedCount > 0"
                  @click="clearCatalogSelection()"
                  class="text-fg-muted hover:text-error transition-colors">
            Сбросить
          </button>
        </div>
      </div>

      <div class="max-h-[50vh] overflow-y-auto space-y-2 pr-1 -mr-1">
        <template x-if="catalogLoading">
          <div class="py-8 text-center text-fg-muted space-y-2">
            <svg class="w-6 h-6 animate-spin mx-auto text-primary" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <div class="text-xs">Загрузка товаров...</div>
          </div>
        </template>

        <template x-if="!catalogLoading && catalogProducts && catalogProducts.length === 0">
          <div class="py-8 text-center text-fg-muted text-xs">
            Товары не найдены
          </div>
        </template>

        <template x-if="!catalogLoading">
          <div class="space-y-2">
            <template x-for="product in catalogProducts" :key="product.id">
              <div class="border border-border rounded-lg p-2.5 bg-surface2/50 transition-colors">
                <template x-if="product.variants && product.variants.length > 0">
                  <div>
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                      <div class="flex items-start gap-2.5 min-w-0 flex-1">
                        <template x-if="product.image_url">
                          <img :src="product.image_url" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0">
                        </template>
                        <div class="min-w-0 flex-1">
                          <div class="font-medium text-sm text-fg leading-snug" x-text="product.name"></div>
                          <div class="flex items-center gap-2 mt-0.5 text-xs text-fg-subtle">
                            <span x-show="product.sku" x-text="'SKU: ' + product.sku"></span>
                            <span class="text-[11px] px-1.5 py-0.5 rounded bg-surface" x-text="product.variants.length + ' вар.'"></span>
                          </div>
                        </div>
                      </div>
                      <button type="button"
                              @click="toggleAllProductVariants(product)"
                              class="text-xs text-primary hover:underline shrink-0 font-medium"
                              x-text="isAllProductVariantsSelected(product) ? 'Снять все' : 'Все вар.'">
                      </button>
                    </div>

                    <div class="space-y-1.5 mt-2 pt-2 border-t border-border/60">
                      <template x-for="variant in product.variants" :key="variant.id">
                        <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-surface border transition-colors"
                             :class="isCatalogItemSelected('v_' + variant.id) ? 'border-primary/50 bg-primary/5' : 'border-border/60'">
                          <label class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer">
                            <input type="checkbox"
                                   :checked="isCatalogItemSelected('v_' + variant.id)"
                                   @change="toggleCatalogVariant(product, variant)"
                                   class="rounded border-border text-primary focus:ring-primary h-4 w-4 shrink-0">
                            <div class="min-w-0 flex-1">
                              <div class="text-xs font-medium text-fg truncate" x-text="variant.title || 'Вариант #' + variant.id"></div>
                              <div class="text-[11px] text-fg-subtle flex items-center gap-2">
                                <span x-show="variant.sku" x-text="variant.sku"></span>
                                <span class="text-fg font-semibold" x-text="(variant.price !== null && variant.price !== undefined ? variant.price : product.price) + ' ' + (window.__APP_CURRENCY_SYMBOL__ || 'Br')"></span>
                              </div>
                            </div>
                          </label>
                          <div class="flex items-center gap-1 shrink-0" x-show="isCatalogItemSelected('v_' + variant.id)">
                            <button type="button"
                                    @click="changeCatalogItemQty('v_' + variant.id, -1)"
                                    class="w-6 h-6 rounded bg-surface2 flex items-center justify-center text-xs text-fg hover:border-primary">
                              -
                            </button>
                            <input type="number"
                                   min="1"
                                   max="10000"
                                   :value="getCatalogItemQty('v_' + variant.id)"
                                   @input="setCatalogItemQty('v_' + variant.id, $event.target.value)"
                                   @blur="onCatalogItemQtyBlur('v_' + variant.id)"
                                   class="w-10 h-6 text-center text-xs bg-surface2 rounded focus:outline-none focus:border-primary p-0">
                            <button type="button"
                                    @click="changeCatalogItemQty('v_' + variant.id, 1)"
                                    class="w-6 h-6 rounded bg-surface2 flex items-center justify-center text-xs text-fg hover:border-primary">
                              +
                            </button>
                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </template>

                <template x-if="!product.variants || product.variants.length === 0">
                  <div class="flex items-center justify-between gap-2">
                    <label class="flex items-center gap-2.5 min-w-0 flex-1 cursor-pointer">
                      <input type="checkbox"
                             :checked="isCatalogItemSelected('p_' + product.id)"
                             @change="toggleCatalogProduct(product)"
                             class="rounded border-border text-primary focus:ring-primary h-4 w-4 shrink-0">
                      <template x-if="product.image_url">
                        <img :src="product.image_url" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0">
                      </template>
                      <div class="min-w-0 flex-1">
                        <div class="font-medium text-sm text-fg leading-snug truncate" x-text="product.name"></div>
                        <div class="flex items-center gap-2 mt-0.5 text-xs text-fg-subtle">
                          <span x-show="product.sku" x-text="'SKU: ' + product.sku"></span>
                          <span class="text-fg font-semibold" x-text="(product.price || 0) + ' ' + (window.__APP_CURRENCY_SYMBOL__ || 'Br')"></span>
                        </div>
                      </div>
                    </label>
                    <div class="flex items-center gap-1 shrink-0" x-show="isCatalogItemSelected('p_' + product.id)">
                      <button type="button"
                              @click="changeCatalogItemQty('p_' + product.id, -1)"
                              class="w-6 h-6 rounded bg-surface2 flex items-center justify-center text-xs text-fg hover:border-primary">
                        -
                      </button>
                      <input type="number"
                             min="1"
                             max="10000"
                             :value="getCatalogItemQty('p_' + product.id)"
                             @input="setCatalogItemQty('p_' + product.id, $event.target.value)"
                             @blur="onCatalogItemQtyBlur('p_' + product.id)"
                             class="w-10 h-6 text-center text-xs bg-surface2 rounded focus:outline-none focus:border-primary p-0">
                      <button type="button"
                              @click="changeCatalogItemQty('p_' + product.id, 1)"
                              class="w-6 h-6 rounded bg-surface2 flex items-center justify-center text-xs text-fg hover:border-primary">
                        +
                      </button>
                    </div>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </template>
      </div>

      <div class="pt-3 border-t border-border mt-3 flex items-center justify-between gap-3">
        <div class="text-xs text-fg-subtle truncate">
          <template x-if="catalogSelectedCount > 0">
            <span>Выбрано: <strong class="text-fg" x-text="catalogSelectedCount"></strong> поз. (<span x-text="catalogSelectedTotalQty"></span> шт.)</span>
          </template>
          <template x-if="catalogSelectedCount === 0">
            <span>Ничего не выбрано</span>
          </template>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button type="button"
                  @click="closeProductModal()"
                  class="px-3 py-2 bg-surface2 rounded-lg text-xs font-medium text-fg hover:bg-surface transition-colors">
            Отмена
          </button>
          <button type="button"
                  @click="addSelectedCatalogItems()"
                  :disabled="catalogSelectedCount === 0"
                  class="px-4 py-2 bg-primary text-primary-fg rounded-lg text-xs font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-primary-hover active:scale-[0.98] transition-all flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            <span x-text="catalogSelectedCount > 0 ? 'Добавить (' + catalogSelectedCount + ')' : 'Добавить'"></span>
          </button>
        </div>
      </div>
    </div>
  </template>
@endcomponent

