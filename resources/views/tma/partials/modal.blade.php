@php
  $show = $show ?? 'false';
  $maxWidth = $maxWidth ?? 'max-w-sm';
  $padding = $padding ?? 'p-6';
  $overlayClass = $overlayClass ?? '';
  $zIndex = $zIndex ?? 'z-50';
  $extraClass = $extraClass ?? '';
@endphp

<div x-show="{{ $show }}"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-init="
       let locked = false;
       const closeModal = () => { {{ $show }} = false; };
       $watch('{{ $show }}', v => {
         if (v && !locked) {
           locked = true;
           window.registerModal ? window.registerModal(closeModal) : window.lockScroll?.();
         } else if (!v && locked) {
           locked = false;
           window.unregisterModal ? window.unregisterModal(closeModal) : window.unlockScroll?.();
         }
       });
       if ({{ $show }} && !locked) {
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
     @click.self="if ($el._backdropDown) { {{ $show }} = false; } $el._backdropDown = false"
     @pointerup="$el._backdropDown = false"
     @mouseup="$el._backdropDown = false"
     class="fixed inset-0 bg-black/60 {{ $zIndex }} flex items-center justify-center p-4 {{ $overlayClass }}"
     style="display: none;">
  <div class="bg-surface rounded-2xl shadow-2xl ring-1 ring-white/10 {{ $padding }} {{ $maxWidth }} w-full max-h-[90vh] overflow-y-auto overscroll-contain {{ $extraClass }}" @click.stop>
    {{ $slot }}
  </div>
</div>
