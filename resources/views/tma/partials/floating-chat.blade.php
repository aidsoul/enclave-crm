<div data-floating-chat
     x-cloak
     x-show="floatingChatEnabled && !isChatPage && !isDesktopMode"
     class="fixed left-0 right-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom,0px))] pointer-events-none"
     style="z-index: 35;">
  <div class="max-w-[480px] mx-auto px-4 flex justify-end">
    <button type="button"
            @click="openFloatingChat()"
            class="pointer-events-auto relative w-12 h-12 min-w-[48px] min-h-[48px] rounded-full bg-surface2/90 backdrop-blur-md text-primary border border-white/10 shadow-lg shadow-black/20 flex items-center justify-center hover:scale-105 active:scale-95 transition-all focus:outline-none"
            title="Открыть чат"
            aria-label="Открыть чат">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.755-.783 7.828 7.828 0 0 0 .753-2.008C3.896 16.592 3 14.417 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
      </svg>
      <template x-if="floatingChatUnreadCount > 0">
        <span class="absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 bg-error text-white text-[11px] font-bold rounded-full flex items-center justify-center leading-none ring-2 ring-surface shadow-sm pointer-events-none tabular-nums"
              x-text="floatingChatUnreadCount > 99 ? '99+' : floatingChatUnreadCount"></span>
      </template>
    </button>
  </div>
</div>

<div data-floating-chat
     x-cloak
     x-show="floatingChatEnabled && !isChatPage && isDesktopMode"
     class="fixed right-6 bottom-6"
     style="z-index: 90;">
  <button type="button"
          @click="openFloatingChat()"
          class="relative w-16 h-16 min-w-[64px] min-h-[64px] rounded-full bg-primary text-primary-fg shadow-md shadow-primary/25 flex items-center justify-center hover:scale-105 active:scale-95 transition-all focus:outline-none ring-2 ring-bg/50"
          title="Открыть чат"
          aria-label="Открыть чат">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.755-.783 7.828 7.828 0 0 0 .753-2.008C3.896 16.592 3 14.417 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
    </svg>
    <template x-if="floatingChatUnreadCount > 0">
      <span class="absolute -top-1 -right-1 min-w-[22px] h-[22px] px-1.5 bg-error text-white text-xs font-bold rounded-full flex items-center justify-center leading-none ring-2 ring-surface shadow-md pointer-events-none"
            x-text="floatingChatUnreadCount > 99 ? '99+' : floatingChatUnreadCount"></span>
    </template>
  </button>
</div>
