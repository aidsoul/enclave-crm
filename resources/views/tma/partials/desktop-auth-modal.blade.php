@component('tma.partials.modal', ['show' => 'desktopAuthModalOpen', 'maxWidth' => 'max-w-md', 'padding' => 'p-6'])
  <div class="flex items-center justify-between pb-4 mb-4 border-b border-border">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-primary/20 text-primary flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
        </svg>
      </div>
      <div>
        <h3 class="font-display font-semibold text-fg text-base">Вход на компьютере</h3>
        <p class="text-xs text-fg-muted">Браузер с сохранением любого размера окна</p>
      </div>
    </div>
    <button @click="desktopAuthModalOpen = false" class="text-fg-subtle hover:text-fg p-1.5 rounded-lg transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div x-show="desktopAuthLoading" class="py-8 flex flex-col items-center justify-center gap-3">
    <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
    <p class="text-xs text-fg-muted">Генерация защищенной одноразовой ссылки...</p>
  </div>

  <div x-show="!desktopAuthLoading" class="space-y-4">
    <p class="text-xs text-fg-muted leading-relaxed">
      Откройте CRM в Chrome, Edge или Яндекс.Браузере. Окно можно растянуть под любой размер, а браузер навсегда запомнит его.
    </p>

    <div class="p-3 bg-bg rounded-xl border border-border flex items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-[11px] text-fg-subtle uppercase tracking-wider font-semibold">Срок действия ссылки</p>
        <div class="flex items-center gap-2">
          <p class="text-sm font-semibold font-mono" :class="desktopAuthTimer === 0 ? 'text-danger' : 'text-primary'" x-text="desktopAuthTimerFormatted"></p>
          <span x-show="desktopAuthTimer === 0" class="text-xs text-danger font-medium">(истекла)</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button x-show="desktopAuthTimer === 0"
                type="button"
                @click="generateDesktopLink(false)"
                class="text-[11px] px-2.5 py-1 rounded-lg bg-primary/10 text-primary hover:bg-primary/20 font-medium transition-colors">
          Обновить
        </button>
        <span x-show="desktopAuthTimer > 0" class="text-[11px] px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium">Одноразовая</span>
      </div>
    </div>

    <div class="space-y-2 pt-1">
      <div class="relative flex items-center">
        <input type="text" readonly :value="desktopAuthUrl" class="w-full bg-bg rounded-xl px-3 py-2 text-xs text-fg-muted font-mono truncate select-all pr-24 focus:outline-none" @click="$event.target.select()">
        <button type="button" @click="copyDesktopLink()" class="absolute right-1 top-1 bottom-1 px-3 bg-surface2 text-fg text-xs font-medium rounded-lg hover:bg-surface2/80 transition-colors">
          Копировать
        </button>
      </div>

      <button @click="generateDesktopLink(true)" 
              class="w-full py-2.5 px-4 bg-primary text-white text-xs font-semibold rounded-xl hover:bg-primary-hover active:scale-[0.99] transition-all flex items-center justify-center gap-2 shadow-sm">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 0 0-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
        </svg>
        <span x-text="desktopAuthSent ? 'Отправить в чат повторно' : 'Отправить ссылку в чат Telegram'"></span>
      </button>
    </div>

    <div class="p-3 bg-surface-alt/50 rounded-xl  text-[11px] text-fg-subtle flex items-start gap-2">
      <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
      </svg>
      <span>После открытия в браузере сессия сохранится на 30 дней. Также можно нажать в меню браузера <b>«Установить приложение»</b>, чтобы запускать CRM с ярлыка.</span>
    </div>
  </div>
@endcomponent
