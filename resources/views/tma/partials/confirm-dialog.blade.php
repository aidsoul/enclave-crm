<div data-confirm-dialog
     x-data="{
       show: false,
       title: '',
       message: '',
       confirmText: 'Подтвердить',
       cancelText: 'Отмена',
       variant: 'danger',
       _resolve: null,
       _closeModal: null,
       init() {
         this._closeModal = () => this.cancel();
       },
       open(detail) {
         if (this._resolve) {
           this._resolve(false);
         }
         this.title = detail.title || 'Подтверждение';
         this.message = detail.message || '';
         this.confirmText = detail.confirmText || 'Подтвердить';
         this.cancelText = detail.cancelText || 'Отмена';
         this.variant = detail.variant || 'danger';
         this._resolve = detail.resolve;
         this.show = true;
         window.registerModal ? window.registerModal(this._closeModal) : window.lockScroll?.();
         try {
           if (this.variant === 'danger') {
             window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('warning');
           } else {
             window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('medium');
           }
         } catch (_) {}
       },
       confirm() {
         const resolve = this._resolve;
         this.cleanup();
         resolve?.(true);
       },
       cancel() {
         const resolve = this._resolve;
         this.cleanup();
         resolve?.(false);
       },
       cleanup() {
         if (this.show) {
           this.show = false;
           window.unregisterModal ? window.unregisterModal(this._closeModal) : window.unlockScroll?.();
           this._resolve = null;
         }
       },
       get confirmButtonClass() {
         if (this.variant === 'danger') {
           return 'bg-error text-white hover:bg-error/90';
         }
         if (this.variant === 'warning') {
           return 'bg-warning text-black hover:bg-warning/90';
         }
         return 'bg-primary text-white hover:bg-primary-hover';
       }
     }"
     @confirm-dialog.window="open($event.detail)"
     @keydown.escape.window="if (show) cancel()"
     @touchmove.self.prevent
     @wheel.self.prevent
     @pointerdown.self="$el._backdropDown = true"
     @mousedown.self="$el._backdropDown = true"
     @click.self="if ($el._backdropDown) { cancel(); } $el._backdropDown = false"
     @pointerup="$el._backdropDown = false"
     @mouseup="$el._backdropDown = false"
     x-show="show"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4"
     style="display: none;"
     x-cloak>
  
  <div class="bg-surface rounded-2xl ring-1 ring-white/10 p-6 max-w-sm w-full text-center shadow-2xl"
       x-show="show"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       @click.stop>
    
    <!-- Иконка статуса -->
    <template x-if="variant === 'danger'">
      <div class="mx-auto w-12 h-12 rounded-full bg-error/10 text-error flex items-center justify-center mb-4 border border-error/20">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.002A11.959 11.959 0 0 1 12 2.714zm0 14.286a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
        </svg>
      </div>
    </template>
    
    <template x-if="variant === 'warning'">
      <div class="mx-auto w-12 h-12 rounded-full bg-warning/10 text-warning flex items-center justify-center mb-4 border border-warning/20">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
      </div>
    </template>

    <template x-if="variant !== 'danger' && variant !== 'warning'">
      <div class="mx-auto w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4 border border-primary/20">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
        </svg>
      </div>
    </template>

    <h3 class="font-display text-lg font-semibold text-fg mb-2" x-text="title"></h3>
    <p class="text-fg-muted text-xs leading-relaxed mb-6" x-text="message"></p>

    <div class="flex items-center gap-3">
      <button type="button"
              @click="cancel()"
              class="flex-1 py-2.5 px-4 bg-surface2 text-fg text-sm font-medium rounded-xl hover:bg-surface3 active:scale-95 transition-all"
              x-text="cancelText">
      </button>
      <button type="button"
              @click="confirm()"
              :class="confirmButtonClass"
              class="flex-1 py-2.5 px-4 text-sm font-medium rounded-xl active:scale-95 transition-all shadow-md"
              x-text="confirmText">
      </button>
    </div>
  </div>
</div>
