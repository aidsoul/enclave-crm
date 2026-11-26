<div x-data="{
       show: false,
       title: 'Загрузка...',
       subtitle: '',
       progress: null,
       status: 'loading',
       timer: null,
       start(detail = {}) {
         if (this.timer) clearTimeout(this.timer);
         this.show = true;
         this.status = 'loading';
         this.title = detail.title || 'Загрузка...';
         this.subtitle = detail.subtitle || '';
         this.progress = detail.progress ?? null;
         try {
           window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
         } catch (_) {}
       },
       update(detail = {}) {
         if (detail.title) this.title = detail.title;
         if (detail.subtitle !== undefined) this.subtitle = detail.subtitle;
         if (detail.progress !== undefined) this.progress = detail.progress;
         if (detail.status) this.status = detail.status;
       },
       stop(detail = {}) {
         this.status = detail.status || 'success';
         if (detail.message) {
           this.subtitle = detail.message;
         }
         try {
           if (this.status === 'success') {
             window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
           } else if (this.status === 'error') {
             window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('error');
           }
         } catch (_) {}
         if (this.timer) clearTimeout(this.timer);
         this.timer = setTimeout(() => {
           this.show = false;
           this.progress = null;
         }, detail.delay || 900);
       }
     }"
     @upload-loading.window="
       if ($event.detail?.active === true) {
         start($event.detail);
       } else if ($event.detail?.active === false) {
         stop($event.detail);
       } else {
         update($event.detail);
       }
     "
     x-show="show"
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
     x-transition:leave-end="opacity-0 translate-y-4 scale-95"
     class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] pointer-events-none select-none max-w-[92%] w-auto mb-[env(safe-area-inset-bottom)]"
     style="display: none;">
  <div class="px-4 py-2.5 rounded-2xl bg-surface/95 border border-border shadow-2xl backdrop-blur-md flex items-center gap-3 pointer-events-auto">
    <template x-if="status === 'loading'">
      <div class="relative w-6 h-6 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 animate-spin text-primary" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
      </div>
    </template>

    <template x-if="status === 'success'">
      <div class="w-6 h-6 rounded-full bg-success/20 text-success flex items-center justify-center shrink-0">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
        </svg>
      </div>
    </template>

    <template x-if="status === 'error'">
      <div class="w-6 h-6 rounded-full bg-error/20 text-error flex items-center justify-center shrink-0">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </div>
    </template>

    <div class="min-w-[130px] max-w-[240px]">
      <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-semibold text-fg truncate" x-text="title"></p>
        <span x-show="progress !== null" class="text-[10px] font-mono text-primary font-medium" x-text="progress + '%'"></span>
      </div>
      <p x-show="subtitle" class="text-[11px] text-fg-muted truncate" x-text="subtitle"></p>
      <div x-show="progress !== null" class="w-full bg-surface2 rounded-full h-1 mt-1 overflow-hidden">
        <div class="bg-primary h-full transition-all duration-200" :style="'width: ' + progress + '%'"></div>
      </div>
    </div>
  </div>
</div>

