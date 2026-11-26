@php
  $role = $user?->role?->value ?? 'owner';
  $normalizedPath = '/' . ltrim(request()->path(), '/');
  $isDefaultEntry = in_array($normalizedPath, ['/', '/tma', '/tma/' . $role], true);
  $defaultHref = match($role) {
    'manager' => '/tma/manager',
    'courier' => '/tma/courier',
    default => '/tma',
  };

  $sidebarNavs = [
    'owner' => [
      ['href' => '/tma', 'label' => 'Дашборд', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1 13.5 18v-2.25Z"/>'],
      ['href' => '/tma/orders', 'label' => 'Заказы', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>'],
      ['href' => '/tma/customers', 'label' => 'Клиенты', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>'],
      ['href' => '/tma/leads', 'label' => 'Заявки', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>'],
      ['href' => '/tma/financial', 'label' => 'Финансы', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>'],
      ['href' => '/tma/analytics', 'label' => 'Аналитика', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>'],
      ['href' => '/tma/personnel', 'label' => 'Персонал', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>'],
      ['href' => '/tma/sites', 'label' => 'Сайты и API', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/>'],
      ['href' => '/tma/audit', 'label' => 'Журнал аудита', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>'],
      ['href' => '/tma/settings', 'label' => 'Настройки', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />'],
    ],
    'manager' => [
      ['href' => '/tma/manager', 'label' => 'Профиль', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'],
      ['href' => '/tma/orders', 'label' => 'Заказы', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>'],
      ['href' => '/tma/leads', 'label' => 'Заявки', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>'],
      ['href' => '/tma/customers', 'label' => 'Клиенты', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>'],
      ['href' => '/tma/settings', 'label' => 'Настройки', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />'],
    ],
    'courier' => [
      ['href' => '/tma/orders', 'label' => 'Заказы', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>'],
      ['href' => '/tma/courier', 'label' => 'Текущий', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>'],
      ['href' => '/tma/settings', 'label' => 'Настройки', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />'],
    ],
  ];

  $items = $sidebarNavs[$role] ?? $sidebarNavs['owner'];
@endphp

<aside x-show="isDesktopMode"
       x-cloak
       class="shrink-0 bg-surface border-r border-border sticky top-0 h-screen flex flex-col justify-between transition-all duration-200 ease-in-out z-30 overflow-y-auto no-scrollbar"
       :class="isSidebarCollapsed ? 'w-[72px] p-3' : 'w-64 p-4'">
  <div>
    <!-- Top Brand / User Info & Collapse Button -->
    <div class="pb-3 mb-3 border-b border-border">
      <div class="flex items-center" :class="isSidebarCollapsed ? 'justify-center' : 'justify-between'">
        <div class="min-w-0" x-show="!isSidebarCollapsed">
          <div>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider inline-block"
                  :class="{
                    'bg-primary/15 text-primary': (user?.role || '{{ $role }}') === 'owner',
                    'bg-warning/15 text-warning': (user?.role || '{{ $role }}') === 'manager',
                    'bg-success/15 text-success': (user?.role || '{{ $role }}') === 'courier'
                  }"
                  x-text="(user?.role || '{{ $role }}') === 'owner' ? 'Владелец' : ((user?.role || '{{ $role }}') === 'manager' ? 'Менеджер' : 'Курьер')">
              {{ $role === 'owner' ? 'Владелец' : ($role === 'manager' ? 'Менеджер' : 'Курьер') }}
            </span>
          </div>
          <p class="text-xs font-semibold text-fg truncate mt-1" x-text="user?.system_name || '{{ $user?->system_name ?? '' }}'">{{ $user?->system_name ?? '' }}</p>
        </div>

        <button type="button"
                @click="toggleSidebarCollapse()"
                class="w-7 h-7 rounded-lg text-fg-muted hover:text-fg hover:bg-surface2 flex items-center justify-center transition-colors shrink-0"
                :title="isSidebarCollapsed ? 'Развернуть панель' : 'Свернуть панель'">
          <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="isSidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Quick New Order Button -->
    @if(!$user || !$user->isCourier())
    <a href="/tma/new"
       class="mb-3 bg-primary text-primary-fg rounded-xl font-semibold flex items-center justify-center shadow-sm hover:opacity-90 active:scale-[0.98] transition-all"
       :class="isSidebarCollapsed ? 'w-10 h-10 mx-auto' : 'w-full px-3.5 py-2.5 text-xs gap-2'"
       title="Новый заказ">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
      </svg>
      <span x-show="!isSidebarCollapsed" class="truncate">Новый заказ</span>
    </a>
    @endif

    <!-- Menu Navigation Links -->
    <nav class="space-y-1">
      @foreach($items as $item)
        @php
          if ($isDefaultEntry) {
            $isActive = $item['href'] === $defaultHref;
          } elseif ($item['href'] === '/tma') {
            $isActive = in_array($normalizedPath, ['/', '/tma', '/tma/owner'], true);
          } else {
            $isActive = $normalizedPath === $item['href'] || str_starts_with($normalizedPath, $item['href'] . '/');
          }
        @endphp
        <a href="{{ $item['href'] }}"
           title="{{ $item['label'] }}"
           class="flex items-center rounded-xl text-xs font-medium transition-colors {{ $isActive ? 'bg-primary/15 text-primary border border-primary/20 font-semibold' : 'text-fg-muted hover:text-fg hover:bg-surface2' }}"
           :class="isSidebarCollapsed ? 'w-10 h-10 mx-auto justify-center p-0' : 'gap-3 px-3 py-2'">
          <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-primary' : 'text-fg-subtle' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            {!! $item['icon'] !!}
          </svg>
          <span x-show="!isSidebarCollapsed" class="truncate">{{ $item['label'] }}</span>
        </a>
      @endforeach
    </nav>
  </div>

  <!-- Bottom Section: Mode switch and Actions -->
  <div class="pt-3 mt-3 border-t border-border">
    <!-- Expanded view -->
    <div x-show="!isSidebarCollapsed" class="space-y-2">
      <div class="flex items-center gap-1.5">
        <button type="button"
                @click="openDesktopAuthModal()"
                class="flex-1 py-1.5 px-2 bg-primary/10 hover:bg-primary/20 text-primary rounded-lg text-xs font-medium flex items-center justify-center gap-1.5 transition-colors"
                title="Открыть CRM в обычном браузере с сохранением размера">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
          </svg>
          <span class="truncate">Вход на ПК</span>
        </button>

        <button type="button"
                @click="toggleFullscreen()"
                class="p-1.5 rounded-lg bg-surface2/80 hover:bg-surface2 text-fg-muted hover:text-fg transition-colors"
                :title="isFullscreen ? 'Свернуть' : 'Во весь экран'">
          <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
          </svg>
          <svg x-show="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display: none;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"/>
          </svg>
        </button>
      </div>

      <div class="flex items-center justify-between px-2 py-1.5 rounded-lg bg-surface2/60 text-xs">
        <div class="flex items-center gap-2 text-fg-muted">
          <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
          </svg>
          <span class="text-[11px] font-medium text-fg">Режим ПК</span>
        </div>
        <button type="button"
                @click="toggleDesktopMode(false)"
                class="text-[10px] text-fg-subtle hover:text-primary transition-colors underline"
                title="Переключить в мобильный вид">
          Мобильный
        </button>
      </div>
      <div class="text-[10px] text-fg-subtle text-center">
        Enclave CRM v1.0
      </div>
    </div>

    <!-- Collapsed view: compact actions -->
    <div x-show="isSidebarCollapsed" class="flex flex-col items-center gap-2">
      <button type="button"
              @click="openDesktopAuthModal()"
              class="w-10 h-10 rounded-xl text-primary bg-primary/10 hover:bg-primary/20 flex items-center justify-center transition-colors"
              title="Открыть CRM на ПК">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
        </svg>
      </button>

      <button type="button"
              @click="toggleFullscreen()"
              class="w-10 h-10 rounded-xl text-fg-muted hover:text-fg hover:bg-surface2 flex items-center justify-center transition-colors"
              :title="isFullscreen ? 'Свернуть' : 'Во весь экран'">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
        </svg>
      </button>

      <button type="button"
              @click="toggleDesktopMode(false)"
              class="w-10 h-10 rounded-xl text-fg-muted hover:text-primary hover:bg-surface2 flex items-center justify-center transition-colors"
              title="Переключить в мобильный вид">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
        </svg>
      </button>
    </div>
  </div>
</aside>
