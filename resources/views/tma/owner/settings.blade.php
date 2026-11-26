@extends('tma.layout')

@section('content')
<div class="flex items-center justify-between mb-4">
  <div>
    <p class="text-fg-subtle text-xs">Настройки</p>
    <h1 class="font-display font-bold text-xl">Enclave CRM</h1>
  </div>
</div>

{{-- Переключатель вкладок --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 p-1 bg-surface rounded-xl mb-4">
  <button type="button"
          @click="setTab('interface')"
          class="w-full px-2.5 py-2 text-xs font-medium rounded-lg transition-all flex items-center justify-center gap-1.5"
          :class="activeTab === 'interface' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'text-fg-muted hover:text-fg hover:bg-surface2'">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
    </svg>
    <span>Интерфейс</span>
  </button>

  <button type="button"
          @click="setTab('notifications')"
          class="w-full px-2.5 py-2 text-xs font-medium rounded-lg transition-all flex items-center justify-center gap-1.5"
          :class="activeTab === 'notifications' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'text-fg-muted hover:text-fg hover:bg-surface2'">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
    </svg>
    <span>Уведомления</span>
  </button>

  <button type="button"
          @click="setTab('security')"
          class="w-full px-2.5 py-2 text-xs font-medium rounded-lg transition-all flex items-center justify-center gap-1.5"
          :class="activeTab === 'security' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'text-fg-muted hover:text-fg hover:bg-surface2'">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
    </svg>
    <span>Безопасность</span>
    <span x-show="sessions.length > 0"
          class="text-[10px] px-1.5 py-0.2 rounded-full font-mono transition-colors shrink-0"
          :class="activeTab === 'security' ? 'bg-primary-fg/20 text-primary-fg' : 'bg-surface2 text-fg-subtle'"
          x-text="sessions.length"></span>
  </button>

  <button type="button"
          @click="setTab('system')"
          class="w-full px-2.5 py-2 text-xs font-medium rounded-lg transition-all flex items-center justify-center gap-1.5"
          :class="activeTab === 'system' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'text-fg-muted hover:text-fg hover:bg-surface2'">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
    </svg>
    <span>Система</span>
  </button>
</div>

{{-- Вкладка 1: Интерфейс --}}
<div x-show="activeTab === 'interface'" x-cloak class="space-y-4">
  {{-- Размер шрифта --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/>
        </svg>
      </div>
      <div>
        <h2 class="font-display font-semibold text-sm">Размер шрифта</h2>
        <p class="text-fg-muted text-xs">Масштаб текста интерфейса для комфортного чтения</p>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-2">
      <button type="button"
              @click="setFontSize('small')"
              class="py-2.5 px-2 rounded-xl border text-center transition-all cursor-pointer"
              :class="fontSize === 'small' ? 'bg-primary/15 border-primary text-primary font-semibold shadow-sm' : 'bg-surface2 border-border text-fg-muted hover:text-fg hover:bg-surface2/80'">
        <div class="text-xs font-semibold mb-0.5">Мелкий</div>
        <div class="text-[11px] text-fg-subtle">Компактный</div>
      </button>
      <button type="button"
              @click="setFontSize('normal')"
              class="py-2.5 px-2 rounded-xl border text-center transition-all cursor-pointer"
              :class="fontSize === 'normal' ? 'bg-primary/15 border-primary text-primary font-semibold shadow-sm' : 'bg-surface2 border-border text-fg-muted hover:text-fg hover:bg-surface2/80'">
        <div class="text-sm font-semibold mb-0.5">Обычный</div>
        <div class="text-[11px] text-fg-subtle">Стандарт</div>
      </button>
      <button type="button"
              @click="setFontSize('large')"
              class="py-2.5 px-2 rounded-xl border text-center transition-all cursor-pointer"
              :class="fontSize === 'large' ? 'bg-primary/15 border-primary text-primary font-semibold shadow-sm' : 'bg-surface2 border-border text-fg-muted hover:text-fg hover:bg-surface2/80'">
        <div class="text-base font-semibold mb-0.5">Крупный</div>
        <div class="text-[11px] text-fg-subtle">Большой</div>
      </button>
    </div>
  </div>

  {{-- Версия для ПК --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Версия для ПК</h2>
          <p class="text-fg-muted text-xs">Широкий экран и боковая панель навигации</p>
        </div>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" :checked="isDesktopMode" @change="toggleDesktopMode($event.target.checked)" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>
  </div>

  {{-- Полноэкранный режим --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Полноэкранный режим</h2>
          <p class="text-fg-muted text-xs">Скрытие шапки Telegram для максимального пространства</p>
        </div>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" :checked="isFullscreen" @change="toggleFullscreen($event.target.checked)" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>
  </div>

  {{-- Плавающая иконка чата --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.755-.783 7.828 7.828 0 0 0 .753-2.008C3.896 16.592 3 14.417 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Плавающая иконка чата</h2>
          <p class="text-fg-muted text-xs">Быстрый доступ к сообщениям поверх всех окон с индикатором новых</p>
        </div>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" :checked="floatingChatEnabled" @change="toggleFloatingChatIcon($event.target.checked)" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>
  </div>

  {{-- Вход в браузере на ПК --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Вход в браузере на ПК</h2>
          <p class="text-fg-muted text-xs">Любой размер окна, ярлык на рабочий стол и вход без рамок Telegram</p>
        </div>
      </div>
      <button type="button" @click="openDesktopAuthModal()" class="px-3 py-1.5 bg-primary/10 text-primary text-xs font-semibold rounded-lg hover:bg-primary/20 shrink-0 transition-colors">
        Войти
      </button>
    </div>
  </div>

  {{-- Иконка на рабочий стол --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Иконка на рабочий стол</h2>
          <p class="text-fg-muted text-xs">Быстрый запуск CRM со смартфона</p>
        </div>
      </div>
      <template x-if="homeScreenStatus === 'added'">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-success-bg text-success text-xs font-semibold rounded-lg shrink-0">
          <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
          Добавлено
        </span>
      </template>
      <template x-if="homeScreenStatus !== 'added'">
        <button @click="addToHomeScreen()" class="px-3 py-1.5 bg-primary/10 text-primary text-xs font-semibold rounded-lg hover:bg-primary/20 shrink-0">
          Добавить
        </button>
      </template>
    </div>
  </div>
</div>

{{-- Вкладка 2: Уведомления --}}
<div x-show="activeTab === 'notifications'" x-cloak class="space-y-4">
  <div class="bg-surface rounded-xl p-4">
    <h2 class="font-display font-semibold text-sm mb-3">Каналы уведомлений</h2>
    <div class="flex items-center justify-between">
      <div class="pr-4">
        <p class="text-sm font-medium">Уведомления в приложении</p>
        <p class="text-fg-muted text-xs">Всплывающие уведомления на экране обо всех событиях</p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" x-model="notifyNewOrders" @change="saveNotifyNewOrders()" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>

    <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
      <div class="pr-4">
        <p class="text-sm font-medium">Сообщения в чат бота</p>
        <p class="text-fg-muted text-xs">Разрешить боту отправлять любые сообщения в Telegram чат</p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" x-model="notifyChatOrders" @change="saveNotifyChatOrders()" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>

    <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
      <div class="pr-4">
        <p class="text-sm font-medium">Уведомления о сообщениях в чатах</p>
        <p class="text-fg-muted text-xs">Получать в Telegram сообщения о новых репликах в чатах заказов</p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0" :class="{ 'opacity-50 cursor-not-allowed': !notifyChatOrders }">
        <input type="checkbox" x-model="notifyChatMessages" @change="saveNotifyChatMessages()" class="sr-only peer" :disabled="!notifyChatOrders">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>

    <div class="flex items-center justify-between pt-3 mt-3 border-t border-border" x-show="user?.role === 'owner' || user?.role === 'manager'">
      <div class="pr-4">
        <p class="text-sm font-medium">Оповещения о заявках с сайта</p>
        <p class="text-fg-muted text-xs">Получать сообщения о новых заявках в чат Telegram бота</p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0" :class="{ 'opacity-50 cursor-not-allowed': !notifyChatOrders }">
        <input type="checkbox" x-model="notifyChatLeads" @change="saveNotifyChatLeads()" class="sr-only peer" :disabled="!notifyChatOrders">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>

    <div class="flex items-center justify-between pt-3 mt-3 border-t border-border" x-show="user?.role === 'owner'">
      <div class="pr-4">
        <p class="text-sm font-medium">Удалять сообщения после принятия</p>
        <p class="text-fg-muted text-xs">Автоматически удалять сообщение с новым заказом в Telegram у всех пользователей, когда кто-то принимает заказ</p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" x-model="deleteChatMessagesOnAccept" @change="saveDeleteChatMessagesOnAccept()" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>

    <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
      <div class="pr-4">
        <p class="text-sm font-medium">Напоминания о зависших заказах</p>
        <p class="text-fg-muted text-xs">Получать напоминания в Telegram о заказах, зависших в статусе без движения</p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" x-model="notifyStaleOrders" @change="saveNotifyStaleOrders()" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>
  </div>
</div>

{{-- Вкладка 3: Безопасность --}}
<div x-show="activeTab === 'security'" x-cloak class="space-y-4">
  {{-- Облачный пароль (2FA) --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between gap-3 mb-2.5">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="font-display font-semibold text-sm text-fg">Облачный пароль</h2>
          <div class="mt-0.5">
            <span class="text-[10px] px-2 py-0.5 rounded-full font-medium inline-flex items-center gap-1.5 shrink-0"
                  :class="hasCloudPassword ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning'">
              <span class="w-1.5 h-1.5 rounded-full shrink-0"
                    :class="hasCloudPassword ? 'bg-success' : 'bg-warning'"></span>
              <span x-text="hasCloudPassword ? 'Включен' : 'Не задан'"></span>
            </span>
          </div>
        </div>
      </div>
      <button type="button"
              @click="openCloudPasswordModal()"
              class="px-3 py-1.5 text-xs font-medium rounded-lg active:scale-95 transition-all shrink-0"
              :class="hasCloudPassword ? 'bg-surface2 text-fg hover:bg-surface2/80' : 'bg-primary text-white hover:bg-primary-hover'">
        <span x-text="hasCloudPassword ? 'Изменить' : 'Задать'"></span>
      </button>
    </div>
    <p class="text-fg-muted text-xs leading-relaxed">
      Защита аккаунта при входе с новых IP и устройств (2FA)
    </p>
  </div>

  {{-- Биометрическая защита --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a1.5 1.5 0 0 1 1.5 1.5v.75m-6 3.75h9m-9 3h9"/>
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Биометрическая защита</h2>
          <p class="text-fg-muted text-xs">Подтверждение Face ID / Touch ID для критических операций</p>
        </div>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" :checked="biometricEnabled" @change="toggleBiometric($event.target.checked)" class="sr-only peer">
        <div class="w-11 h-6 bg-surface2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
      </label>
    </div>
    <div class="mt-3 pt-3 border-t border-border flex items-center justify-between text-xs text-fg-subtle">
      <span x-text="biometricAvailable ? ('Поддерживается: ' + biometricLabel) : 'Биометрия не поддерживается на данном устройстве'"></span>
      <button x-show="biometricAvailable" type="button" @click="openBiometricSettings()" class="text-primary hover:underline font-medium">
        Настройки Telegram
      </button>
    </div>
  </div>

  {{-- Активные сессии --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center justify-between gap-2.5 mb-2">
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
          </svg>
        </div>
        <div class="flex items-center gap-2 min-w-0">
          <h2 class="font-display font-semibold text-sm whitespace-nowrap">Активные сессии</h2>
          <span class="text-[11px] px-2 py-0.5 rounded-full bg-surface2 font-mono text-fg-subtle whitespace-nowrap shrink-0" x-text="sessions.length + ' / ' + maxSessions"></span>
        </div>
      </div>
      <button
        type="button"
        x-show="sessions.filter(s => !s.is_current).length > 0"
        x-cloak
        @click="revokeAllSessions()"
        :disabled="revokeAllLoading"
        title="Завершить все"
        aria-label="Завершить все"
        class="w-8 h-8 rounded-lg bg-error/10 text-error hover:bg-error/20 flex items-center justify-center shrink-0 transition-colors disabled:opacity-50">
        <svg x-show="revokeAllLoading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        <svg x-show="!revokeAllLoading" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
        </svg>
      </button>
    </div>

    <p class="text-fg-muted text-xs mb-3">Автоматически сохраняются до 5 последних сессий</p>

    <template x-if="sessionsLoading">
      <div class="flex items-center justify-center py-4 text-fg-muted text-xs gap-2">
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        Загрузка...
      </div>
    </template>

    <template x-if="!sessionsLoading && sessions.length === 0">
      <p class="text-fg-muted text-xs text-center py-3">Нет активных сессий</p>
    </template>

    <template x-if="!sessionsLoading && sessions.length > 0">
      <div class="space-y-2">
        <template x-for="(session, index) in sessions" :key="session.token">
          <div class="flex items-center justify-between p-2.5 rounded-lg border gap-2"
               :class="session.is_current ? 'bg-primary/5 border-primary/30' : 'bg-surface2 border-border'">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0"
                   :class="session.is_current ? 'bg-primary/15 text-primary' : 'bg-surface text-fg-muted'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                </svg>
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-mono text-fg truncate" x-text="session.token_hint"></span>
                  <span x-show="session.is_current" class="px-1.5 py-0.5 text-[10px] bg-primary/15 text-primary rounded-full font-medium shrink-0 whitespace-nowrap">Текущая</span>
                </div>
                <div class="text-[11px] text-fg-muted mt-0.5 whitespace-nowrap" x-show="session.expires_in">
                  Истекает через <span x-text="Math.round(session.expires_in / 86400) + ' дн.'"></span>
                </div>
              </div>
            </div>
            <button
              x-show="!session.is_current"
              type="button"
              @click="revokeSession(session.token)"
              :disabled="revokingToken === session.token"
              class="px-2.5 py-1.5 bg-surface text-xs font-medium rounded-lg hover:bg-error/10 hover:text-error hover:border-error/30 transition-colors shrink-0 disabled:opacity-50 whitespace-nowrap">
              <span x-text="revokingToken === session.token ? '...' : 'Завершить'"></span>
            </button>
          </div>
        </template>
      </div>
    </template>
  </div>

  {{-- Опасная зона (только владелец) --}}
  <div class="bg-surface border border-error/30 rounded-xl p-4" x-show="user?.role === 'owner'">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="font-display font-semibold text-sm text-error">Опасная зона</h2>
        <p class="text-fg-muted text-xs">Экстренное удаление всех данных и защита PIN-кодом</p>
      </div>
      <div class="w-8 h-8 rounded-lg bg-error/10 flex items-center justify-center text-error shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
      </div>
    </div>

    <div class="p-3 bg-surface2 rounded-lg mb-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-md bg-surface flex items-center justify-center text-fg-muted">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
          </div>
          <div>
            <div class="text-xs font-semibold text-fg flex items-center gap-1.5">
              <span>PIN-код паники</span>
              <span x-show="hasWipePin" class="px-1.5 py-0.5 text-[10px] bg-success/15 text-success rounded-full font-medium">Задан</span>
              <span x-show="!hasWipePin" class="px-1.5 py-0.5 text-[10px] bg-warning/15 text-warning rounded-full font-medium">Не задан</span>
            </div>
            <p class="text-[11px] text-fg-muted">Подтверждается через биометрию</p>
          </div>
        </div>
        <button @click="openPinModal()" class="px-2.5 py-1.5 bg-surface text-xs font-medium rounded-lg border border-border hover:bg-surface2 transition-colors">
          <span x-text="hasWipePin ? 'Изменить' : 'Задать PIN'"></span>
        </button>
      </div>
    </div>

    <p class="text-fg-muted text-xs mb-3">Действие нельзя отменить. Будут безвозвратно удалены все данные из всех таблиц.</p>

    <button @click="showWipeConfirm()" class="w-full px-4 py-3 bg-error text-white rounded-lg font-semibold text-sm active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
      <span>⚠️ ПАНИКА — СТЕРЕТЬ ВСЁ</span>
    </button>
  </div>
</div>

{{-- Вкладка 4: Система --}}
<div x-show="activeTab === 'system'" x-cloak class="space-y-4">
  {{-- Хранение данных (только владелец) --}}
  <div class="bg-surface rounded-xl p-4" x-show="user?.role === 'owner'">
    <h2 class="font-display font-semibold text-sm mb-3">Хранение данных</h2>
    <p class="text-fg-muted text-xs mb-4">Автоматическое удаление заказов старше указанного срока</p>

    <div class="space-y-2">
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 1 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="1" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 1 ? 'font-medium' : ''">1 день</span>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 3 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="3" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 3 ? 'font-medium' : ''">3 дня</span>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 7 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="7" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 7 ? 'font-medium' : ''">7 дней</span>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 30 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="30" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 30 ? 'font-medium' : ''">30 дней</span>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 60 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="60" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 60 ? 'font-medium' : ''">60 дней</span>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 90 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="90" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 90 ? 'font-medium' : ''">90 дней</span>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer hover:bg-surface2 transition-colors"
             :class="Number(retentionDays) === 0 ? 'bg-primary/10 border-primary' : ''">
        <input type="radio" name="retention" :value="0" x-model.number="retentionDays" @change="saveRetention()" class="w-4 h-4 accent-primary">
        <span class="text-sm" :class="Number(retentionDays) === 0 ? 'font-medium' : ''">Не удалять</span>
      </label>
    </div>
  </div>

  {{-- Сайты и API-токены (только владелец) --}}
  <div class="bg-surface rounded-xl p-4" x-show="user?.role === 'owner'">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/>
          </svg>
        </div>
        <div>
          <h2 class="font-display font-semibold text-sm">Сайты и API-токены</h2>
          <p class="text-fg-muted text-xs">Управление подключенными сайтами и токенами</p>
        </div>
      </div>
      <a href="/tma/sites" class="px-3 py-1.5 bg-primary/10 text-primary text-xs font-semibold rounded-lg hover:bg-primary/20 shrink-0">
        Открыть
      </a>
    </div>
  </div>

  {{-- Журнал аудита (только владелец) --}}
  <div class="bg-surface rounded-xl p-4" x-show="user?.role === 'owner'">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="font-display font-semibold text-sm">Журнал аудита</h2>
        <p class="text-fg-muted text-xs">История действий персонала и изменений заказов</p>
      </div>
      <a href="/tma/audit" class="px-3 py-1.5 bg-surface2 text-xs font-semibold rounded-lg hover:bg-surface2/80">
        Открыть
      </a>
    </div>
  </div>

  {{-- Блок информации об авторе и системе --}}
  <div class="bg-surface rounded-xl p-4">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
        </svg>
      </div>
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <span class="font-display font-semibold text-sm">AidSoul</span>
          <span class="px-1.5 py-0.5 text-[10px] bg-primary/15 text-primary rounded font-mono font-medium">Автор</span>
        </div>
        <a :href="getEmailLink('work-aidsoul@outlook.com')"
           @click.prevent="openEmail('work-aidsoul@outlook.com')"
           class="text-xs text-fg-muted hover:text-primary transition-colors flex items-center gap-1 mt-0.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
          </svg>
          <span class="truncate">work-aidsoul@outlook.com</span>
        </a>
      </div>
    </div>

    <div class="mt-3 pt-3 border-t border-border/60 text-xs text-fg-muted space-y-1">
      <div>Enclave CRM v1.0 • &copy; 2026 AidSoul • GNU AGPLv3</div>
      <div class="flex items-center gap-1.5">
        <span>Исходный код:</span>
        <a href="https://github.com/aidsoul/enclave-crm"
           target="_blank"
           rel="noopener noreferrer"
           @click.prevent="openExternalLink('https://github.com/aidsoul/enclave-crm')"
           class="text-primary hover:underline font-mono truncate">
          github.com/aidsoul/enclave-crm
        </a>
      </div>
    </div>
  </div>
</div>


@component('tma.partials.modal', ['show' => 'showPinModal'])
  <h3 class="font-display font-bold text-lg mb-1" x-text="hasWipePin ? 'Изменить PIN-код паники' : 'Придумать PIN-код паники'"></h3>
  <p class="text-fg-muted text-xs mb-4">
    PIN-код используется для подтверждения кнопки «Паника». Любая установка или изменение подтверждается биометрией (Face ID / отпечаток).
  </p>

  <template x-if="hasWipePin">
    <div class="mb-3">
      <label class="text-xs text-fg-subtle mb-1 block">Текущий PIN-код</label>
      <input type="password" inputmode="numeric" x-model="currentWipePin" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none" placeholder="Текущий PIN">
    </div>
  </template>

  <div class="mb-3">
    <label class="text-xs text-fg-subtle mb-1 block">Новый PIN-код</label>
    <input type="password" inputmode="numeric" x-model="newWipePin" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none" placeholder="Новый PIN (от 4 символов)">
  </div>

  <div class="mb-4">
    <label class="text-xs text-fg-subtle mb-1 block">Повторите новый PIN-код</label>
    <input type="password" inputmode="numeric" x-model="newWipePinConfirm" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none" placeholder="Повторите PIN">
  </div>

  <div x-show="pinError" class="mb-4 p-2.5 bg-error/10 border border-error/20 rounded-lg text-xs text-error" x-text="pinError"></div>

  @component('tma.partials.modal-actions')
    <button @click="showPinModal = false; pinError = ''" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium">Отмена</button>
    <button @click="saveWipePin()" :disabled="pinSaving || !newWipePin || !newWipePinConfirm" class="flex-1 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium disabled:opacity-50">
      <span x-text="pinSaving ? 'Проверка...' : 'Подтвердить'"></span>
    </button>
  @endcomponent
@endcomponent

@component('tma.partials.modal', ['show' => 'showWipeModal'])
  <div class="flex items-center gap-2 mb-2">
    <div class="w-7 h-7 rounded-full bg-error/15 flex items-center justify-center text-error shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
      </svg>
    </div>
    <h3 class="font-display font-bold text-lg text-error">Подтверждение ПАНИКИ</h3>
  </div>

  <p class="text-fg-muted text-sm mb-3">
    Это действие нельзя отменить. Будут удалены <span class="font-semibold text-fg">ВСЕ данные</span> из всех таблиц CRM.
  </p>

  <div class="p-3 bg-error/10 border border-error/20 rounded-lg mb-4 text-xs text-fg">
    После ввода PIN-кода потребуется подтверждение через <span class="font-semibold text-error" x-text="biometricLabel">биометрию</span>.
  </div>

  <div class="mb-4">
    <label class="text-xs text-fg-subtle mb-1 block">Введите ваш PIN-код</label>
    <input type="password" inputmode="numeric" x-model="wipePin" @keydown.enter="executeWipe()" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-error focus:outline-none" placeholder="••••">
  </div>

  <label class="flex items-start gap-3 p-3 bg-surface2 rounded-lg mb-4 cursor-pointer hover:border-error/40 transition-colors">
    <input type="checkbox" x-model="wipeBackups" class="mt-0.5 w-4 h-4 rounded border-border text-error focus:ring-error accent-error shrink-0">
    <div class="min-w-0">
      <span class="text-xs font-semibold text-fg block">Удалить все резервные копии</span>
      <span class="text-[11px] text-fg-muted block mt-0.5">Полное уничтожение локальных архивов и бэкапов в S3-хранилище</span>
    </div>
  </label>

  <div x-show="wipePinError" class="mb-4 p-2.5 bg-error/10 border border-error/20 rounded-lg text-xs text-error" x-text="wipePinError"></div>

  @component('tma.partials.modal-actions')
    <button @click="showWipeModal = false; wipePin = ''; wipePinError = ''" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-sm font-medium">Отмена</button>
    <button @click="executeWipe()" :disabled="!wipePin || wipeLoading" class="flex-1 px-4 py-2.5 bg-error text-white rounded-lg text-sm font-medium disabled:opacity-50">
      <span x-text="wipeLoading ? 'Проверка...' : 'Подтвердить'"></span>
    </button>
  @endcomponent
@endcomponent

@include('tma.partials.nav')
@endsection