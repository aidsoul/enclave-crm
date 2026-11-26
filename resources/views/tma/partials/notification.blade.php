<div x-data="{
       show: false,
       message: '',
       type: 'success',
       url: null,
       timer: null,
       open() {
         if (this.url) {
           window.location.href = this.url;
         } else {
           this.show = false;
         }
       }
     }"
     @notify.window="
       if (timer) clearTimeout(timer);
       show = true;
       message = $event.detail.message;
       type = ($event.detail.type === 'warning' ? 'warn' : $event.detail.type) || 'success';
       url = $event.detail.url || null;
       const duration = Math.min(8500, Math.max(4000, String(message || '').length * 45));
       timer = setTimeout(() => show = false, duration);
       try {
         if (type === 'error') {
           window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('error');
           navigator.vibrate?.([100, 50, 100]);
         } else if (type === 'warn') {
           window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('warning');
           navigator.vibrate?.(120);
         } else {
           window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred?.('success');
           window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('heavy');
           navigator.vibrate?.(100);
         }
       } catch (_) {}
     "
     x-show="show"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-4"
     class="fixed top-4 left-1/2 -translate-x-1/2 z-50 w-full max-w-[95%] pointer-events-auto"
     style="display: none;">
  <div @click="open()"
       :class="[
         type === 'error' ? 'border-error/40' : (type === 'warn' ? 'border-warning/40' : (type === 'info' ? 'border-primary/40' : 'border-success/40')),
         url ? 'cursor-pointer active:scale-[0.98]' : 'cursor-pointer active:scale-[0.99]'
       ]"
       class="px-4 py-3 rounded-2xl bg-surface/95 border shadow-2xl backdrop-blur-md text-fg text-sm font-medium w-full flex items-center justify-between gap-3 transition-transform select-none">
    <div class="flex items-center gap-3 min-w-0 flex-1">
      <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
           :class="{
             'bg-error/20 text-error': type === 'error',
             'bg-warning/20 text-warning': type === 'warn',
             'bg-primary/20 text-primary': type === 'info',
             'bg-success/20 text-success': type !== 'error' && type !== 'warn' && type !== 'info'
           }">
        <!-- Error icon -->
        <svg x-show="type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
        <!-- Warning icon -->
        <svg x-show="type === 'warn'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <!-- Info icon -->
        <svg x-show="type === 'info'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0Zm-9-3.75h.008v.008H12V8.25Z"/>
        </svg>
        <!-- Success icon -->
        <svg x-show="type !== 'error' && type !== 'warn' && type !== 'info'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
        </svg>
      </div>
      <span class="break-words text-fg font-medium text-sm leading-snug line-clamp-4 flex-1" x-text="message"></span>
    </div>
    <template x-if="url">
      <div class="flex items-center gap-1 text-xs shrink-0 font-semibold text-primary pl-1 active:opacity-75 transition-opacity">
        <span>Открыть</span>
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
        </svg>
      </div>
    </template>
  </div>
</div>
