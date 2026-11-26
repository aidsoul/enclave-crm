@extends('tma.layout')

@section('content')
{{-- Заголовок страницы --}}
<div class="flex items-center justify-between mb-4">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="font-display font-bold text-xl text-fg">Персонал</h1>
      <span class="min-w-5 h-5 px-1.5 rounded-full bg-surface2 text-fg-muted text-[11px] font-semibold flex items-center justify-center whitespace-nowrap"
            x-text="personnelTotal || 0"></span>
    </div>
    <p class="text-fg-subtle text-xs mt-0.5">Управление командой и ролями доступа</p>
  </div>
  <div class="flex items-center gap-2">
    <button type="button"
            @click="openInvitationsModal()"
            class="h-9 px-3 rounded-xl bg-surface flex items-center gap-1.5 text-xs font-medium hover:border-primary/50 active:scale-95 transition-all text-fg"
            title="Приглашения">
      <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
      </svg>
      <span>Приглашения</span>
      <span x-show="invitations.length > 0" class="px-1.5 py-0.2 rounded-full bg-primary/20 text-primary text-[10px] font-bold" x-text="invitations.length"></span>
    </button>
    <button type="button"
            @click="showCreateModal = true"
            class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center text-primary-fg hover:opacity-90 active:scale-95 transition-all shadow-sm"
            title="Добавить сотрудника">
      <svg class="w-4 h-4 text-primary-fg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
      </svg>
    </button>
  </div>
</div>

{{-- Поиск --}}
<div class="mb-3">
  <div class="relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-fg-muted pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
    </svg>
    <input
      type="text"
      x-model="personnelSearch"
      @input="onPersonnelSearch()"
      placeholder="Поиск по имени или Telegram ID..."
      class="w-full pl-9 pr-8 py-2 bg-surface rounded-xl text-xs sm:text-sm focus:border-primary focus:outline-none placeholder:text-fg-subtle"
    >
    <button
      type="button"
      x-show="personnelSearch"
      @click="personnelSearch = ''; onPersonnelSearch()"
      class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center text-fg-muted hover:text-fg rounded-md">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
</div>

{{-- Фильтры: Компактные и без полос прокрутки --}}
<div class="space-y-2 mb-4">
  {{-- Роли --}}
  <div class="flex gap-1.5 overflow-x-auto pb-0.5 items-center no-scrollbar">
    <button type="button" @click="setRoleFilter('all')"
            class="px-2.5 py-1 text-xs font-medium rounded-lg whitespace-nowrap transition-all"
            :class="roleFilter === 'all' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'bg-surface text-fg-muted hover:border-primary/40'">
      Все роли
    </button>
    <button type="button" @click="setRoleFilter('manager')"
            class="px-2.5 py-1 text-xs font-medium rounded-lg whitespace-nowrap transition-all"
            :class="roleFilter === 'manager' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'bg-surface text-fg-muted hover:border-primary/40'">
      Менеджеры
    </button>
    <button type="button" @click="setRoleFilter('courier')"
            class="px-2.5 py-1 text-xs font-medium rounded-lg whitespace-nowrap transition-all"
            :class="roleFilter === 'courier' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'bg-surface text-fg-muted hover:border-primary/40'">
      Курьеры
    </button>
    <button type="button" @click="setRoleFilter('owner')"
            class="px-2.5 py-1 text-xs font-medium rounded-lg whitespace-nowrap transition-all"
            :class="roleFilter === 'owner' ? 'bg-primary text-primary-fg font-semibold shadow-sm' : 'bg-surface text-fg-muted hover:border-primary/40'">
      Владельцы
    </button>
  </div>

  {{-- Статус --}}
  <div class="flex items-center justify-between gap-2">
    <div class="flex gap-1.5 overflow-x-auto pb-0.5 items-center no-scrollbar">
      <button type="button" @click="setStatusFilter(statusFilter === 'active' ? 'all' : 'active')"
              class="px-2.5 py-0.5 text-[11px] font-medium rounded-lg whitespace-nowrap transition-all flex items-center gap-1.5"
              :class="statusFilter === 'active' ? 'bg-success/15 text-success border border-success/30 font-semibold' : 'bg-surface text-fg-muted hover:text-fg'">
        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
        <span>Активные</span>
      </button>
      <button type="button" @click="setStatusFilter(statusFilter === 'blocked' ? 'all' : 'blocked')"
              class="px-2.5 py-0.5 text-[11px] font-medium rounded-lg whitespace-nowrap transition-all flex items-center gap-1.5"
              :class="statusFilter === 'blocked' ? 'bg-error/15 text-error border border-error/30 font-semibold' : 'bg-surface text-fg-muted hover:text-fg'">
        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
        <span>Заблокированные</span>
      </button>
    </div>

    <button type="button"
            x-show="hasActiveFilters"
            @click="resetFilters()"
            class="text-[11px] text-primary hover:underline whitespace-nowrap shrink-0 px-1 py-0.5">
      Сбросить
    </button>
  </div>
</div>

{{-- Скелетон загрузки --}}
<div x-show="personnelLoading && personnel.length === 0" class="space-y-2.5 mb-4">
  <div class="bg-surface rounded-xl p-3 animate-pulse">
    <div class="h-3.5 bg-surface2 rounded w-32 mb-1.5"></div>
    <div class="h-2.5 bg-surface2 rounded w-24"></div>
  </div>
  <div class="bg-surface rounded-xl p-3 animate-pulse">
    <div class="h-3.5 bg-surface2 rounded w-32 mb-1.5"></div>
    <div class="h-2.5 bg-surface2 rounded w-24"></div>
  </div>
</div>

{{-- Пустой список --}}
<div x-show="!personnelLoading && personnel.length === 0" x-transition class="text-center py-10 mb-4 bg-surface/40 border border-dashed border-border rounded-xl">
  <div class="w-10 h-10 rounded-full bg-surface2 flex items-center justify-center mx-auto mb-2 text-fg-muted">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
    </svg>
  </div>
  <p class="text-xs font-medium text-fg mb-0.5">Сотрудники не найдены</p>
  <p class="text-[11px] text-fg-muted mb-3" x-show="hasActiveFilters">Попробуйте изменить параметры поиска или фильтров</p>
  <button type="button" x-show="hasActiveFilters" @click="resetFilters()" class="text-xs px-3 py-1.5 bg-surface2 text-fg rounded-lg font-medium hover:bg-surface2/80 transition-colors">
    Сбросить фильтры
  </button>
</div>

{{-- Список сотрудников в современном компактном дизайне --}}
<div class="space-y-2 mb-4" :class="personnelLoading ? 'opacity-60 pointer-events-none' : ''">
  <template x-for="member in personnel" :key="member.id">
    <div class="bg-surface rounded-xl p-3 transition-all">
      <div class="flex items-center justify-between gap-2.5">
        {{-- Информация о сотруднике --}}
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="font-semibold text-xs sm:text-sm text-fg truncate" x-text="member.system_name"></span>
            <span x-show="user && member.id === user.id" class="px-1 py-0.2 rounded bg-surface2 text-[9px] text-primary font-bold">Вы</span>
            
            {{-- Бейдж роли --}}
            <template x-if="member.role === 'owner'">
              <span class="text-[10px] font-medium px-2 py-0.2 rounded-full inline-flex items-center gap-1 shrink-0 bg-warning/15 text-warning border border-warning/30">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
                <span>Владелец</span>
              </span>
            </template>
            <template x-if="member.role !== 'owner'">
              <span class="text-[10px] font-medium px-2 py-0.2 rounded-full inline-flex items-center gap-1 shrink-0"
                    :class="member.role === 'manager' ? 'bg-primary/15 text-primary border border-primary/30' : 'bg-success/15 text-success border border-success/30'"
                    x-text="member.role_label">
              </span>
            </template>
          </div>

          {{-- Нижняя строчка: ID, профиль TG и статус --}}
          <div class="flex items-center gap-2 mt-0.5 text-[11px] text-fg-muted flex-wrap">
            <button type="button"
                    @click="copyText(member.telegram_user_id)"
                    class="flex items-center gap-1 font-mono hover:text-primary transition-colors"
                    title="Скопировать TG ID">
              <span class="text-fg-subtle">ID:</span>
              <span x-text="member.telegram_user_id"></span>
              <svg class="w-2.5 h-2.5 text-fg-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
              </svg>
            </button>

            <span class="text-fg-subtle/40">•</span>

            <a :href="member.telegram_username ? ('https://t.me/' + member.telegram_username) : ('tg://user?id=' + member.telegram_user_id)"
               @click.prevent="openTelegramProfile(member.telegram_user_id, member.telegram_username)"
               class="inline-flex items-center gap-1 text-[#2AABEE] hover:underline"
               title="Открыть профиль в Telegram">
              <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.63 3.73-.53.37-1.02.55-1.45.54-.48-.01-1.4-.27-2.09-.49-.84-.27-1.51-.42-1.45-.88.03-.24.38-.49 1.04-.75 4.07-1.77 6.78-2.94 8.14-3.51 3.86-1.61 4.67-1.89 5.2-1.9.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.19-.04.28z"/>
              </svg>
              <span x-text="member.telegram_username ? ('@' + member.telegram_username) : 'Профиль'"></span>
            </a>

            <span class="text-fg-subtle/40">•</span>

            <span class="inline-flex items-center gap-1" :class="member.is_active ? 'text-success' : 'text-error'">
              <span class="w-1.5 h-1.5 rounded-full" :class="member.is_active ? 'bg-success' : 'bg-error'"></span>
              <span x-text="member.is_active ? 'Активен' : 'Заблокирован'"></span>
            </span>
          </div>
        </div>

        {{-- Справа: Компактные кнопки действий --}}
        <div class="flex items-center gap-1 shrink-0">
          {{-- Открыть профиль в Telegram --}}
          <a :href="member.telegram_username ? ('https://t.me/' + member.telegram_username) : ('tg://user?id=' + member.telegram_user_id)"
             @click.prevent="openTelegramProfile(member.telegram_user_id, member.telegram_username)"
             class="w-7 h-7 rounded-lg bg-surface2/80 hover:bg-[#2AABEE]/20 hover:text-[#2AABEE] text-fg-muted flex items-center justify-center transition-all active:scale-95"
             title="Открыть профиль в Telegram">
            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.63 3.73-.53.37-1.02.55-1.45.54-.48-.01-1.4-.27-2.09-.49-.84-.27-1.51-.42-1.45-.88.03-.24.38-.49 1.04-.75 4.07-1.77 6.78-2.94 8.14-3.51 3.86-1.61 4.67-1.89 5.2-1.9.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.19-.04.28z"/>
            </svg>
          </a>

          {{-- Редактировать (если есть право) --}}
          <button type="button"
                  x-show="canEdit(member)"
                  @click="openEditModal(member)"
                  class="w-7 h-7 rounded-lg bg-surface2/80 hover:bg-primary/20 hover:text-primary text-fg-muted flex items-center justify-center transition-all active:scale-95"
                  title="Редактировать">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
            </svg>
          </button>

          {{-- Заблокировать / Разблокировать (для всех кроме себя) --}}
          <button type="button"
                  x-show="!user || member.id !== user.id"
                  @click="member.is_active ? showPanicFor(member) : showUnblockFor(member)"
                  class="w-7 h-7 rounded-lg flex items-center justify-center transition-all active:scale-95"
                  :class="member.is_active ? 'bg-surface2/80 hover:bg-error/20 text-fg-muted hover:text-error' : 'bg-error/20 text-error hover:bg-success/20 hover:text-success'"
                  :title="member.is_active ? 'Заблокировать доступ' : 'Разблокировать доступ'">
            <svg x-show="member.is_active" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
            <svg x-show="!member.is_active" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
          </button>

          {{-- Удалить (для всех кроме себя) --}}
          <button type="button"
                  x-show="!user || member.id !== user.id"
                  @click="deleteMember(member.id)"
                  class="w-7 h-7 rounded-lg bg-surface2/80 hover:bg-error/20 text-fg-muted hover:text-error flex items-center justify-center transition-all active:scale-95"
                  title="Удалить">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.061-.94-1.75-1.816-1.618l-3.043.715a2.25 2.25 0 0 1-1.916-1.915l.715-3.044c.132-.876.557-1.476 1.618-1.816h.916m-7.5 0a48.667 48.667 0 0 0-7.5 0"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </template>
</div>

{{-- Пагинация --}}
<div x-show="personnelLastPage > 1" class="mb-5 bg-surface rounded-xl p-2.5 flex items-center justify-between gap-2">
  <div class="text-[11px] text-fg-muted font-medium">
    <span class="text-fg font-semibold" x-text="personnelPage"></span> из <span class="text-fg font-semibold" x-text="personnelLastPage"></span>
    <span class="text-fg-subtle" x-text="` (${personnelTotal})`"></span>
  </div>

  <div class="flex items-center gap-1">
    {{-- Кнопка Назад --}}
    <button type="button"
            @click="prevPage()"
            :disabled="personnelPage <= 1 || personnelLoading"
            class="px-2 py-1 rounded-lg bg-surface2 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:border-primary/50 transition-all flex items-center gap-1"
            title="Предыдущая страница">
      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
      </svg>
      <span class="hidden sm:inline">Назад</span>
    </button>

    {{-- Номера страниц --}}
    <template x-for="p in paginationPages" :key="p">
      <div>
        <template x-if="p === '...'">
          <span class="px-1 text-xs text-fg-subtle font-mono">...</span>
        </template>
        <template x-if="p !== '...'">
          <button type="button"
                  @click="goToPage(p)"
                  :disabled="personnelLoading"
                  class="w-6 h-6 rounded-md text-xs font-medium transition-all flex items-center justify-center"
                  :class="personnelPage === p ? 'bg-primary text-primary-fg font-bold shadow-sm' : 'bg-surface2 text-fg-muted hover:text-fg'"
                  x-text="p">
          </button>
        </template>
      </div>
    </template>

    {{-- Кнопка Вперёд --}}
    <button type="button"
            @click="nextPage()"
            :disabled="personnelPage >= personnelLastPage || personnelLoading"
            class="px-2 py-1 rounded-lg bg-surface2 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:border-primary/50 transition-all flex items-center gap-1"
            title="Следующая страница">
      <span class="hidden sm:inline">Вперёд</span>
      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
      </svg>
    </button>
  </div>
</div>

{{-- Модалка редактирования сотрудника (Имя и/или Роль) --}}
@component('tma.partials.modal', ['show' => 'showEditModal'])
  <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-lg bg-primary/15 flex items-center justify-center text-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
        </svg>
      </div>
      <div>
        <h3 class="font-display font-bold text-base leading-tight">Редактировать сотрудника</h3>
        <p class="text-[11px] text-fg-subtle truncate max-w-[200px]" x-text="editingMember ? editingMember.system_name : ''"></p>
      </div>
    </div>
    <button type="button" @click="showEditModal = false" class="text-fg-muted hover:text-fg p-1.5 rounded-lg active:scale-95 transition-all">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div class="space-y-3.5">
    {{-- Telegram ID и ссылка на профиль --}}
    <div class="p-3 bg-surface2/40 rounded-xl space-y-2.5">
      <div class="flex items-center justify-between gap-2">
        <span class="text-[11px] text-fg-subtle font-medium">Telegram ID</span>
        <span class="font-mono text-xs font-semibold text-fg tracking-wide select-all whitespace-nowrap" x-text="editingMember ? editingMember.telegram_user_id : '—'"></span>
      </div>
      <div class="grid grid-cols-2 gap-2 pt-2 border-t border-border/50">
        <button type="button"
                @click="editingMember && copyText(editingMember.telegram_user_id)"
                class="px-2.5 py-1.5 rounded-lg bg-surface2 hover:bg-surface2/80 text-xs font-medium text-fg hover:text-primary transition-all active:scale-95 flex items-center justify-center gap-1.5 min-w-0"
                title="Скопировать TG ID">
          <svg class="w-3.5 h-3.5 text-fg-subtle shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
          </svg>
          <span class="truncate">Копировать</span>
        </button>
        <a x-show="editingMember?.telegram_user_id"
           :href="editingMember?.telegram_username ? ('https://t.me/' + editingMember?.telegram_username) : ('tg://user?id=' + editingMember?.telegram_user_id)"
           @click.prevent="openTelegramProfile(editingMember?.telegram_user_id, editingMember?.telegram_username)"
           class="px-2.5 py-1.5 rounded-lg bg-[#2AABEE]/15 hover:bg-[#2AABEE]/25 border border-[#2AABEE]/30 text-[#2AABEE] text-xs font-medium transition-all active:scale-95 flex items-center justify-center gap-1.5 min-w-0"
           title="Открыть профиль в Telegram">
          <svg class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.63 3.73-.53.37-1.02.55-1.45.54-.48-.01-1.4-.27-2.09-.49-.84-.27-1.51-.42-1.45-.88.03-.24.38-.49 1.04-.75 4.07-1.77 6.78-2.94 8.14-3.51 3.86-1.61 4.67-1.89 5.2-1.9.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.19-.04.28z"/>
          </svg>
          <span class="truncate" x-text="editingMember?.telegram_username ? ('@' + editingMember?.telegram_username) : 'Профиль'"></span>
        </a>
      </div>
    </div>
    {{-- Поле: Имя пользователя --}}
    <div>
      <div class="flex items-center justify-between mb-1">
        <label class="text-xs text-fg-subtle font-medium">Имя пользователя</label>
        <span x-show="editingMember && !canEditName(editingMember)" class="text-[10px] text-error">Недоступно</span>
      </div>
      
      <template x-if="editingMember && canEditName(editingMember)">
        <input type="text"
               x-model="editForm.system_name"
               placeholder="Введите имя..."
               class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm text-fg focus:border-primary focus:outline-none">
      </template>

      <template x-if="editingMember && !canEditName(editingMember)">
        <input type="text"
               :value="editingMember ? editingMember.system_name : ''"
               disabled
               class="w-full px-3 py-2 bg-surface2/50 rounded-lg text-sm text-fg-muted cursor-not-allowed">
      </template>
    </div>

    {{-- Поле: Роль --}}
    <div>
      <div class="mb-1.5">
        <label class="text-xs text-fg-subtle font-medium">Роль</label>
      </div>

      {{-- Выбор роли для менеджеров и курьеров --}}
      <template x-if="editingMember && canEditRole(editingMember)">
        <div class="space-y-2">
          {{-- Вариант: Менеджер --}}
          <label class="flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-all"
                 :class="editForm.role === 'manager' ? 'bg-primary/10 border-primary shadow-sm' : 'bg-surface2/60 border-border hover:border-primary/50'">
            <input type="radio" name="edit_user_role" value="manager" x-model="editForm.role" class="mt-0.5 text-primary focus:ring-0">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-fg">Менеджер</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-primary/20 text-primary font-medium">Заказы</span>
              </div>
              <p class="text-[11px] text-fg-muted mt-0.5 leading-tight">Создание заказов, назначение курьеров, работа с клиентами.</p>
            </div>
          </label>

          {{-- Вариант: Курьер --}}
          <label class="flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-all"
                 :class="editForm.role === 'courier' ? 'bg-primary/10 border-primary shadow-sm' : 'bg-surface2/60 border-border hover:border-primary/50'">
            <input type="radio" name="edit_user_role" value="courier" x-model="editForm.role" class="mt-0.5 text-primary focus:ring-0">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-fg">Курьер</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded bg-success/20 text-success font-medium">Доставка</span>
              </div>
              <p class="text-[11px] text-fg-muted mt-0.5 leading-tight">Приём заказов, перевод статусов доставки, вручение клиенту.</p>
            </div>
          </label>
        </div>
      </template>

      {{-- Роль владельца (без лишнего текста) --}}
      <template x-if="editingMember && !canEditRole(editingMember)">
        <div class="p-2.5 bg-surface2/60 rounded-xl flex items-center gap-2">
          <div class="w-6 h-6 rounded-md bg-warning/15 text-warning flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-fg">Владелец</p>
            <p class="text-[10px] text-fg-subtle">Роль владельца зафиксирована за создателем.</p>
          </div>
        </div>
      </template>
    </div>
  </div>

  @component('tma.partials.modal-actions')
    <button type="button" @click="showEditModal = false" class="flex-1 px-4 py-2.5 bg-surface2 rounded-lg text-xs sm:text-sm font-medium hover:bg-surface2/80 transition-colors">Отмена</button>
    <button type="button" @click="saveMember()" :disabled="savingMember" class="flex-1 px-4 py-2.5 bg-primary text-primary-fg rounded-lg text-xs sm:text-sm font-medium hover:opacity-90 active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed" x-text="savingMember ? 'Сохранение...' : 'Сохранить'"></button>
  @endcomponent
@endcomponent

{{-- Модалка создания сотрудника --}}
@component('tma.partials.modal', ['show' => 'showCreateModal'])
  <div class="flex items-center justify-between pb-3 border-b border-white/[0.06] mb-4">
    <h3 class="font-display font-bold text-base">Новый сотрудник</h3>
    <button type="button" @click="showCreateModal = false" class="text-fg-muted hover:text-fg p-1.5 rounded-lg active:scale-95 transition-all" title="Закрыть">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
  <div class="space-y-4">
    <div>
      <label class="text-xs text-fg-subtle mb-1 block">Имя</label>
      <input type="text" x-model="newUser.system_name" placeholder="Иван Иванов" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none">
    </div>
    <div>
      <label class="text-xs text-fg-subtle mb-1.5 block">Роль</label>
      <div class="grid grid-cols-2 p-1 bg-surface2 rounded-xl gap-1">
        <button type="button" @click="newUser.role = 'manager'" class="py-2 text-xs font-semibold rounded-lg transition-all" :class="newUser.role === 'manager' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'">Менеджер</button>
        <button type="button" @click="newUser.role = 'courier'" class="py-2 text-xs font-semibold rounded-lg transition-all" :class="newUser.role === 'courier' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'">Курьер</button>
      </div>
    </div>
    <div>
      <label class="text-xs text-fg-subtle mb-1 block">Telegram ID</label>
      <input type="number" x-model="newUser.telegram_user_id" placeholder="123456789" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none">
    </div>
    <div class="pt-2">
      <button @click="createPersonnel()" :disabled="creatingPersonnel" class="w-full py-3 bg-primary text-primary-fg rounded-xl text-sm font-semibold disabled:opacity-50 active:scale-[0.98] transition-all shadow-sm" x-text="creatingPersonnel ? 'Создание...' : 'Создать сотрудника'"></button>
    </div>
  </div>
@endcomponent

{{-- Модалка списка приглашений --}}
@component('tma.partials.modal', ['show' => 'showInvitationsModal', 'maxWidth' => 'max-w-md', 'padding' => 'p-4 sm:p-5', 'zIndex' => 'z-50'])
  <div x-show="inviteModalView === 'list'">
    <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-primary/15 flex items-center justify-center text-primary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
          </svg>
        </div>
        <div>
          <h3 class="font-display font-bold text-base leading-tight">Приглашения</h3>
          <p class="text-[11px] text-fg-subtle">Ссылки для входа сотрудников</p>
        </div>
      </div>
      <button type="button" @click="showInvitationsModal = false" class="text-fg-muted hover:text-fg p-1.5 rounded-lg active:scale-95 transition-all">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="flex items-center justify-between mb-3">
      <span class="text-xs text-fg-muted" x-text="invitations.length ? `Всего: ${invitations.length}` : ''"></span>
      <button type="button" @click="openCreateInvite()" class="text-xs px-3 py-1.5 bg-primary text-primary-fg rounded-lg font-medium hover:opacity-90 active:scale-95 transition-all flex items-center gap-1.5 ml-auto">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        <span>Создать</span>
      </button>
    </div>

    <div x-show="invitations.length === 0" class="text-center py-8">
      <div class="w-12 h-12 rounded-full bg-surface2 flex items-center justify-center mx-auto mb-2.5 text-fg-muted">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
        </svg>
      </div>
      <p class="text-sm font-medium text-fg mb-1">Нет активных приглашений</p>
      <p class="text-xs text-fg-muted mb-4">Создайте ссылку и отправьте её менеджеру или курьеру</p>
      <button type="button" @click="openCreateInvite()" class="text-xs px-4 py-2 bg-primary text-primary-fg rounded-lg font-medium hover:opacity-90 active:scale-95 transition-all">
        Создать приглашение
      </button>
    </div>

    <div x-show="invitations.length > 0" class="max-h-[58vh] overflow-y-auto space-y-2.5 pr-0.5">
      <template x-for="inv in invitations" :key="inv.id">
        <div class="bg-surface2/60 rounded-xl p-3">
          <div class="flex items-start justify-between gap-2 mb-1.5">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-surface" x-text="inv.role_label"></span>
              <span class="text-xs text-fg-muted" x-text="`${inv.used_count}/${inv.max_activations} исп.`"></span>
              <span x-show="inv.system_name" class="text-xs text-primary font-medium" x-text="inv.system_name"></span>
            </div>
            <button @click="promptRevokeInvite(inv)" x-show="inv.is_active" class="w-7 h-7 rounded-lg bg-error/15 hover:bg-error/25 text-error flex items-center justify-center shrink-0 transition-colors" title="Отозвать">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <p class="text-[11px] text-fg-subtle mb-1.5" x-text="`Истекает: ${new Date(inv.expires_at).toLocaleDateString('ru-RU')} ${new Date(inv.expires_at).toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit'})}`"></p>
          <div class="relative flex items-center">
            <input type="text" :value="getInviteLink(inv.token)" readonly class="w-full text-xs px-2.5 py-1.5 bg-surface rounded-xl text-fg-muted font-mono pr-9 select-all focus:outline-none">
            <button @click="copyLink(inv.token)" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center bg-primary text-primary-fg rounded-md hover:opacity-90 active:scale-95 transition-all" title="Копировать">
              <template x-if="copiedToken === inv.token">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
              </template>
              <template x-if="copiedToken !== inv.token">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/>
                </svg>
              </template>
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>

  <div x-show="inviteModalView === 'create'">
    <div class="flex items-center justify-between pb-3 border-b border-white/[0.06] mb-3">
      <div class="flex items-center gap-2">
        <button type="button" @click="inviteModalView = 'list'" class="text-fg-muted hover:text-fg p-1 -ml-1 rounded-lg transition-colors" title="Назад к списку">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
          </svg>
        </button>
        <h3 class="font-display font-bold text-base">Новое приглашение</h3>
      </div>
      <button type="button" @click="showInvitationsModal = false; inviteModalView = 'list'" class="text-fg-muted hover:text-fg p-1.5 rounded-lg active:scale-95 transition-all" title="Закрыть">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="space-y-3.5">
      <div>
        <label class="text-xs text-fg-subtle mb-1.5 block">Роль</label>
        <div class="grid grid-cols-2 p-1 bg-surface2 rounded-xl gap-1">
          <button type="button" @click="newInvite.role = 'manager'" class="py-2 text-xs font-semibold rounded-lg transition-all" :class="newInvite.role === 'manager' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'">Менеджер</button>
          <button type="button" @click="newInvite.role = 'courier'" class="py-2 text-xs font-semibold rounded-lg transition-all" :class="newInvite.role === 'courier' ? 'bg-primary text-primary-fg shadow-sm' : 'text-fg-muted hover:text-fg'">Курьер</button>
        </div>
      </div>

      <div>
        <label class="text-xs text-fg-subtle mb-1.5 block">Количество активаций</label>
        <div class="flex items-center gap-2">
          <button type="button" @click="newInvite.max_activations = Math.max(1, (parseInt(newInvite.max_activations) || 1) - 1)" class="w-9 h-9 rounded-lg bg-surface2 text-fg flex items-center justify-center font-bold text-base active:scale-95 transition-all">−</button>
          <input type="number" x-model.number="newInvite.max_activations" min="1" class="w-16 text-center py-2 bg-surface2 rounded-lg text-sm font-semibold tabular-nums focus:outline-none">
          <button type="button" @click="newInvite.max_activations = (parseInt(newInvite.max_activations) || 1) + 1" class="w-9 h-9 rounded-lg bg-surface2 text-fg flex items-center justify-center font-bold text-base active:scale-95 transition-all">+</button>
          <div class="flex gap-1 ml-auto">
            <template x-for="cnt in [1, 5, 10]" :key="cnt">
              <button type="button" @click="newInvite.max_activations = cnt" class="px-2.5 py-1.5 text-xs rounded-lg transition-colors" :class="newInvite.max_activations === cnt ? 'bg-primary/20 text-primary font-semibold' : 'bg-surface2 text-fg-subtle hover:text-fg'" x-text="cnt"></button>
            </template>
          </div>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="text-xs text-fg-subtle">Срок действия</label>
          <div class="flex gap-1">
            <button type="button" @click="setInviteExpiry(24)" class="px-2 py-0.5 text-[11px] rounded-md bg-surface2 text-fg-subtle hover:text-fg transition-colors">24 ч</button>
            <button type="button" @click="setInviteExpiry(24 * 7)" class="px-2 py-0.5 text-[11px] rounded-md bg-surface2 text-fg-subtle hover:text-fg transition-colors">7 дн</button>
            <button type="button" @click="setInviteExpiry(24 * 30)" class="px-2 py-0.5 text-[11px] rounded-md bg-surface2 text-fg-subtle hover:text-fg transition-colors">30 дн</button>
          </div>
        </div>
        <input type="datetime-local" x-model="newInvite.expires_at" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none text-fg">
      </div>

      <div>
        <label class="text-xs text-fg-subtle mb-1 block">Имя сотрудника (опционально)</label>
        <input type="text" x-model="newInvite.system_name" placeholder="Например: Иван Иванов" class="w-full px-3 py-2 bg-surface2 rounded-lg text-sm focus:border-primary focus:outline-none">
      </div>

      <div class="pt-2">
        <button type="button" @click="createInvite()" :disabled="creatingInvite" class="w-full py-3 bg-primary text-primary-fg rounded-xl text-sm font-semibold hover:opacity-90 active:scale-[0.98] transition-all shadow-sm disabled:opacity-50" x-text="creatingInvite ? 'Создание...' : 'Создать приглашение'"></button>
      </div>
    </div>
  </div>
@endcomponent

@include('tma.partials.nav')
@endsection
