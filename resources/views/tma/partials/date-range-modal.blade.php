<div x-show="showDatePicker"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-init="
       let locked = false;
       const closeModal = () => closeDatePicker();
       $watch('showDatePicker', v => {
         if (v && !locked) {
           locked = true;
           window.registerModal ? window.registerModal(closeModal) : window.lockScroll?.();
         } else if (!v && locked) {
           locked = false;
           window.unregisterModal ? window.unregisterModal(closeModal) : window.unlockScroll?.();
         }
       });
       if (showDatePicker && !locked) {
         locked = true;
         window.registerModal ? window.registerModal(closeModal) : window.lockScroll?.();
       }
       $cleanup?.(() => {
         if (locked) {
           locked = false;
           window.unregisterModal ? window.unregisterModal(closeModal) : window.unlockScroll?.();
         }
       });
     "
     @touchmove.self.prevent
     @wheel.self.prevent
     @pointerdown.self="$el._backdropDown = true"
     @mousedown.self="$el._backdropDown = true"
     @click.self="if ($el._backdropDown) { closeDatePicker(); } $el._backdropDown = false"
     @pointerup="$el._backdropDown = false"
     @mouseup="$el._backdropDown = false"
     class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-3 sm:p-4"
     style="display: none;">

  <div class="bg-surface rounded-2xl p-4 w-full max-w-sm max-h-[92vh] overflow-y-auto overscroll-contain shadow-2xl flex flex-col gap-3.5"
       @click.stop>

    {{-- Заголовок --}}
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
          </svg>
        </div>
        <div>
          <h3 class="font-display font-semibold text-sm text-fg leading-none">Выбор периода</h3>
          <p class="text-[11px] text-fg-subtle mt-0.5" x-text="pickerFrom && pickerTo ? (formatDateRu(pickerFrom) + ' — ' + formatDateRu(pickerTo)) : (pickerFrom ? ('С ' + formatDateRu(pickerFrom)) : 'Нажмите дату начала и конца')"></p>
        </div>
      </div>
      <button type="button" @click="closeDatePicker()" class="w-7 h-7 flex items-center justify-center rounded-lg text-fg-muted hover:text-fg hover:bg-surface2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    {{-- Быстрые пресеты --}}
    <div class="flex flex-wrap gap-1.5 pb-1 border-b border-border/60">
      <button type="button" @click="applyPreset('today')" class="px-2 py-1 text-[11px] font-medium rounded-md bg-surface2 hover:bg-surface text-fg-muted hover:text-fg transition-colors">Сегодня</button>
      <button type="button" @click="applyPreset('yesterday')" class="px-2 py-1 text-[11px] font-medium rounded-md bg-surface2 hover:bg-surface text-fg-muted hover:text-fg transition-colors">Вчера</button>
      <button type="button" @click="applyPreset('7days')" class="px-2 py-1 text-[11px] font-medium rounded-md bg-surface2 hover:bg-surface text-fg-muted hover:text-fg transition-colors">7 дней</button>
      <button type="button" @click="applyPreset('30days')" class="px-2 py-1 text-[11px] font-medium rounded-md bg-surface2 hover:bg-surface text-fg-muted hover:text-fg transition-colors">30 дней</button>
      <button type="button" @click="applyPreset('thisMonth')" class="px-2 py-1 text-[11px] font-medium rounded-md bg-surface2 hover:bg-surface text-fg-muted hover:text-fg transition-colors">Этот месяц</button>
      <button type="button" @click="applyPreset('lastMonth')" class="px-2 py-1 text-[11px] font-medium rounded-md bg-surface2 hover:bg-surface text-fg-muted hover:text-fg transition-colors">Прошлый месяц</button>
    </div>

    {{-- Навигация месяца --}}
    <div class="flex items-center justify-between px-1">
      <button type="button" @click="prevMonth()" class="w-7 h-7 flex items-center justify-center rounded-lg bg-surface2 text-fg-muted hover:text-fg transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <span class="font-display font-semibold text-xs tracking-wide text-fg" x-text="pickerMonthName + ' ' + pickerYear"></span>
      <button type="button" @click="nextMonth()" class="w-7 h-7 flex items-center justify-center rounded-lg bg-surface2 text-fg-muted hover:text-fg transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
      </button>
    </div>

    {{-- Дни недели --}}
    <div class="grid grid-cols-7 text-center">
      <span class="text-[11px] font-medium text-fg-subtle">Пн</span>
      <span class="text-[11px] font-medium text-fg-subtle">Вт</span>
      <span class="text-[11px] font-medium text-fg-subtle">Ср</span>
      <span class="text-[11px] font-medium text-fg-subtle">Чт</span>
      <span class="text-[11px] font-medium text-fg-subtle">Пт</span>
      <span class="text-[11px] font-medium text-fg-subtle">Сб</span>
      <span class="text-[11px] font-medium text-fg-subtle">Вс</span>
    </div>

    {{-- Сетка календаря --}}
    <div class="grid grid-cols-7 gap-y-1">
      <template x-for="(item, idx) in calendarDays" :key="item.date + '-' + idx">
        <div class="relative py-0.5 flex items-center justify-center">
          {{-- Подсветка полосы диапазона --}}
          <div x-show="pickerFrom && pickerTo && item.date >= pickerFrom && item.date <= pickerTo && pickerFrom !== pickerTo"
               class="absolute inset-y-0.5 bg-primary/20"
               :class="{
                 'left-0 right-0': item.date > pickerFrom && item.date < pickerTo,
                 'left-1/2 right-0': item.date === pickerFrom,
                 'left-0 right-1/2': item.date === pickerTo
               }">
          </div>

          {{-- Кнопка дня --}}
          <button type="button"
                  @click="selectDate(item.date)"
                  class="relative z-10 w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-colors"
                  :class="{
                    'bg-primary text-primary-fg font-bold shadow-sm': item.date === pickerFrom || item.date === pickerTo,
                    'text-fg hover:bg-surface2': item.isCurrentMonth && item.date !== pickerFrom && item.date !== pickerTo,
                    'text-fg-subtle/40': !item.isCurrentMonth && item.date !== pickerFrom && item.date !== pickerTo,
                    'ring-1 ring-primary/60 font-semibold text-primary': item.isToday && item.date !== pickerFrom && item.date !== pickerTo
                  }"
                  x-text="item.day">
          </button>
        </div>
      </template>
    </div>

    {{-- Ручной ввод дат --}}
    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-border/60">
      <div>
        <label class="block text-[10px] text-fg-subtle uppercase tracking-wider mb-1">С даты</label>
        <input type="date"
               :value="pickerFrom"
               @change="onFromInputChange($event)"
               class="w-full bg-surface2 rounded-lg px-2.5 py-1.5 text-xs text-fg focus:outline-none focus:border-primary">
      </div>
      <div>
        <label class="block text-[10px] text-fg-subtle uppercase tracking-wider mb-1">По дату</label>
        <input type="date"
               :value="pickerTo"
               @change="onToInputChange($event)"
               class="w-full bg-surface2 rounded-lg px-2.5 py-1.5 text-xs text-fg focus:outline-none focus:border-primary">
      </div>
    </div>

    {{-- Кнопки действий --}}
    <div class="flex items-center gap-2 pt-1">
      <button type="button"
              @click="closeDatePicker()"
              class="px-3 py-2 text-xs font-medium rounded-lg bg-surface2 text-fg-muted hover:text-fg transition-colors">
        Отмена
      </button>
      <button type="button"
              @click="applyPicker()"
              :disabled="!pickerFrom"
              class="flex-1 py-2 text-xs font-semibold rounded-lg bg-primary text-primary-fg shadow-sm hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed">
        Применить
      </button>
    </div>

  </div>
</div>
