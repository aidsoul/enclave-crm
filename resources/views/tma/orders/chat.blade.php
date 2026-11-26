@extends('tma.layout')

@section('content')
<div class="flex flex-col h-[calc(100vh-32px)] max-h-[calc(100vh-32px)]">
  <!-- Chat Top Bar -->
  <div class="flex items-center justify-between pb-3 mb-2 border-b border-border shrink-0">
    <div class="flex items-center gap-3">
      <button type="button" @click="goBackToOrder()" class="w-9 h-9 rounded-full bg-surface flex items-center justify-center text-fg hover:text-primary active:scale-95 transition-all">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
      </button>
      <div>
        <div class="flex items-center gap-2">
          <h1 class="font-display font-bold text-base text-fg" x-text="isCourier ? 'Чат с диспетчером' : 'Чат по заказу #' + orderId"></h1>
          <span class="text-xs text-primary font-medium" x-show="isCourier" x-text="'#' + orderId"></span>
        </div>
        <p class="text-[11px] text-fg-subtle truncate max-w-[220px]" x-text="order?.customer?.name ? order.customer.name + (order.customer.address ? ' • ' + order.customer.address : '') : 'Загрузка...'"></p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a :href="'/tma/orders/' + orderId" class="text-xs text-fg-muted hover:text-primary px-2.5 py-1 rounded-lg bg-surface transition-colors">
        Карточка
      </a>
    </div>
  </div>

  <!-- Fast Order Switcher Bar -->
  <div class="flex items-center justify-between gap-2 pb-2 mb-2 border-b border-border/50 shrink-0 relative"
       @click.outside="closeOrderSearchDropdown()">
    <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none py-0.5 min-w-0 flex-1">
      <span class="text-[10px] uppercase font-bold text-fg-subtle shrink-0 tracking-wider">Чаты:</span>
      <template x-for="item in activeOrders" :key="item.id">
        <button type="button"
                @click="switchOrderChat(item.id)"
                class="shrink-0 px-2 py-0.5 rounded-lg text-xs font-semibold flex items-center gap-1 transition-all"
                :class="item.id === orderId ? 'bg-primary text-primary-fg shadow-sm' : 'bg-surface2/70 text-fg-muted hover:text-fg hover:bg-surface2 '">
          <span x-text="'#' + item.id"></span>
          <template x-if="item.unread_count > 0 && item.id !== orderId">
            <span class="w-2 h-2 rounded-full bg-error shrink-0"></span>
          </template>
        </button>
      </template>
    </div>

    <div class="relative shrink-0">
      <button type="button"
              @click="toggleOrderSearchDropdown()"
              class="h-7 px-2 rounded-lg bg-surface2/70 hover:bg-surface2/60 text-fg-muted hover:text-fg text-xs flex items-center gap-1 transition-all active:scale-95"
              title="Найти заказ по номеру">
        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <span class="font-mono text-[11px] text-primary">#</span>
        <svg class="w-3 h-3 text-fg-subtle transition-transform" :class="orderSearchOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
        </svg>
      </button>

      <div x-cloak
           x-show="orderSearchOpen"
           x-transition:enter="transition ease-out duration-150"
           x-transition:enter-start="opacity-0 scale-95 translate-y-1"
           x-transition:enter-end="opacity-100 scale-100 translate-y-0"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100 scale-100 translate-y-0"
           x-transition:leave-end="opacity-0 scale-95 translate-y-1"
           class="absolute right-0 top-full mt-1.5 w-64 sm:w-72 bg-surface rounded-xl shadow-xl z-50 p-2 overflow-hidden">
        
        <div class="relative mb-2">
          <input type="text"
                 x-ref="orderSearchInput"
                 x-model="orderSearchQuery"
                 @input.debounce.250ms="performOrderSearch()"
                 placeholder="№ заказа (напр. 178)..."
                 class="w-full bg-surface2 text-xs text-fg placeholder:text-fg-subtle rounded-lg pl-7 pr-7 py-1.5  focus:border-primary focus:outline-none transition-colors">
          <svg class="w-3.5 h-3.5 text-fg-subtle absolute left-2 top-2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
          </svg>
          <button type="button"
                  x-show="orderSearchQuery"
                  @click="clearOrderSearch()"
                  class="absolute right-2 top-2 text-fg-subtle hover:text-fg">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="max-h-48 overflow-y-auto space-y-1 scrollable-text pr-0.5">
          <template x-if="orderSearchLoading">
            <div class="py-4 text-center text-xs text-fg-subtle">
              <div class="w-4 h-4 rounded-full border-2 border-primary border-t-transparent animate-spin mx-auto mb-1"></div>
              <span>Поиск заказов...</span>
            </div>
          </template>

          <template x-if="!orderSearchLoading && orderSearchResults.length === 0 && orderSearchQuery">
            <div class="py-4 text-center text-xs text-fg-subtle">
              <span>Заказ не найден</span>
            </div>
          </template>

          <template x-for="res in orderSearchResults" :key="res.id">
            <button type="button"
                    @click="switchOrderChat(res.id)"
                    class="w-full text-left p-1.5 rounded-lg hover:bg-surface2 transition-colors flex items-center justify-between gap-2 group"
                    :class="res.id === orderId ? 'bg-surface2/80 border border-primary/40' : ''">
              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <span class="font-bold text-xs text-primary" x-text="'#' + res.id"></span>
                  <span class="text-[11px] text-fg truncate" x-text="res.customer_name || 'Клиент'"></span>
                </div>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <template x-if="res.unread_count > 0">
                  <span class="px-1.5 py-0.5 bg-error text-white text-[9px] font-bold rounded-full" x-text="res.unread_count"></span>
                </template>
                <svg class="w-3.5 h-3.5 text-fg-subtle group-hover:text-primary transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
              </div>
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>

  <!-- Channel Tabs for Manager / Owner -->
  <template x-if="!isCourier">
    <div class="flex items-center gap-1.5 p-1 bg-surface2/70 rounded-xl mb-2  shrink-0">
      <button type="button" @click="switchChatChannel('courier')" class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5" :class="chatChannel === 'courier' ? 'bg-surface text-primary shadow-sm ' : 'text-fg-subtle hover:text-fg'">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
        </svg>
        <span>С водителем</span>
        <template x-if="order && (order.unread_driver_messages_count > 0 || order.has_unread_driver_message)">
          <span class="min-w-[16px] h-4 px-1 bg-error text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none"
                x-text="order.unread_driver_messages_count > 99 ? '99+' : (order.unread_driver_messages_count || '!')"></span>
        </template>
      </button>
      <button type="button" @click="switchChatChannel('internal')" class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5" :class="chatChannel === 'internal' ? 'bg-surface text-primary shadow-sm ' : 'text-fg-subtle hover:text-fg'">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
        </svg>
        <span>Команда</span>
      </button>
    </div>
  </template>

  <!-- Full-Screen Messages Stream -->
  <div x-ref="chatContainer"
       @scroll.passive="onChatScroll"
       class="flex-1 bg-surface2/30 border border-border rounded-xl p-3 sm:p-4 overflow-y-auto overscroll-contain space-y-3 mb-2 scrollable-text">

    <div x-show="chatLoadingMore" class="py-2 text-center">
      <span class="text-xs text-fg-subtle animate-pulse">Загрузка предыдущих сообщений...</span>
    </div>

    <template x-if="chatLoading">
      <div class="h-full flex flex-col items-center justify-center gap-3 py-12">
        <div class="w-6 h-6 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
        <p class="text-xs text-fg-muted">Загрузка переписки...</p>
      </div>
    </template>

    <template x-if="!chatLoading && chatMessages.length === 0">
      <div class="h-full flex flex-col items-center justify-center text-center py-16 px-4">
        <div class="w-12 h-12 rounded-full bg-surface2 flex items-center justify-center mb-3 text-fg-subtle">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.755-.783 7.828 7.828 0 0 0 .753-2.008C3.896 16.592 3 14.417 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
          </svg>
        </div>
        <p class="text-sm font-medium text-fg mb-1">Здесь пока нет сообщений</p>
        <p class="text-xs text-fg-subtle" x-text="chatChannel === 'courier' ? (isCourier ? 'Напишите диспетчеру, если есть вопросы по доставке' : 'Напишите водителю инструкции или код от домофона') : 'Внутреннее обсуждение заказа среди команды'"></p>
      </div>
    </template>

    <template x-for="msg in chatMessages" :key="msg.id">
      <div class="flex flex-col mb-3" :class="msg.is_mine ? 'items-end' : 'items-start'">
        <div class="max-w-[85%] sm:max-w-[70%] rounded-2xl px-3.5 py-2.5 text-xs shadow-sm relative group"
             :class="msg.is_mine ? 'bg-primary text-primary-fg rounded-br-none' : 'bg-surface text-fg rounded-bl-none'">
          <div class="flex items-center justify-between gap-3 mb-1" :class="msg.is_mine ? 'text-primary-fg/80' : 'text-fg-subtle'">
            <div class="flex items-center gap-1.5 min-w-0">
              <template x-if="!msg.is_mine">
                <span class="font-semibold text-[11px] truncate text-primary" x-text="msg.sender?.name || 'Пользователь'"></span>
              </template>
              <span x-show="isOwner && msg.is_edited" class="text-[9px] opacity-75 italic" title="Сообщение отредактировано">ред.</span>
            </div>
            <div class="flex items-center gap-1 shrink-0 ml-auto">
              <span class="text-[10px] opacity-80" x-text="msg.formatted_time || ''"></span>
              <template x-if="msg.can_edit">
                <button type="button" @click.stop="startEditMessage(msg)" title="Редактировать" class="w-5 h-5 rounded flex items-center justify-center hover:bg-black/15 active:scale-90 transition-all ml-0.5" :class="msg.is_mine ? 'text-primary-fg hover:text-white' : 'text-fg-subtle hover:text-primary'">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                  </svg>
                </button>
              </template>
              <template x-if="msg.can_delete">
                <button type="button" @click.stop="openDeleteConfirm(msg)" title="Удалить" class="w-5 h-5 rounded flex items-center justify-center hover:bg-black/15 active:scale-90 transition-all" :class="msg.is_mine ? 'text-primary-fg hover:text-error' : 'text-fg-subtle hover:text-error'">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                  </svg>
                </button>
              </template>
            </div>
          </div>

          <div x-show="msg.message" class="whitespace-pre-wrap break-words leading-relaxed text-[13px]" x-text="msg.message"></div>
        </div>
      </div>
    </template>
  </div>

  <!-- Edit Message Strip -->
  <template x-if="chatEditingMessage">
    <div class="flex items-center justify-between px-3 py-1.5 bg-surface rounded-xl border border-primary/30 shadow-sm shrink-0">
      <div class="flex items-center gap-2 min-w-0">
        <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
        </svg>
        <div class="text-xs truncate">
          <span class="font-semibold text-fg">Редактирование</span>
          <span class="text-fg-subtle truncate ml-1" x-text="chatEditingMessage.message"></span>
        </div>
      </div>
      <button type="button" @click="cancelEditMessage" class="p-1 text-fg-subtle hover:text-fg transition-colors shrink-0 ml-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </template>

  <!-- Completed Order Read-Only Notice -->
  <template x-if="order && order.status === 'completed' && !chatEditingMessage">
    <div class="shrink-0 p-3 bg-surface rounded-2xl text-center shadow-sm">
      <div class="flex items-center justify-center gap-2 text-xs text-fg-muted font-medium">
        <svg class="w-4 h-4 text-fg-subtle shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
        </svg>
        <span>Заказ завершён. Чат доступен только для чтения.</span>
      </div>
    </div>
  </template>

  <!-- Bottom Message Input Bar -->
  <form x-show="!order || order.status !== 'completed' || chatEditingMessage"
        @submit.prevent="chatEditingMessage ? saveEditMessage() : sendChatMessage()"
        class="shrink-0 space-y-1">
    <div class="flex items-end gap-2 bg-surface p-1.5 sm:p-2 rounded-2xl border border-border shadow-sm">
      <div class="flex-1 min-w-0 bg-surface2/60 focus-within:bg-surface2/60 focus-within:border-primary/50 rounded-2xl px-3.5 py-2 transition-all flex items-center min-h-[40px]">
        <textarea x-ref="chatTextarea"
                  x-model="chatNewMessage"
                  maxlength="2000"
                  @input="adjustChatTextareaHeight()"
                  @keydown.enter.exact.prevent="chatEditingMessage ? saveEditMessage() : sendChatMessage()"
                  rows="1"
                  :placeholder="chatEditingMessage ? 'Редактировать сообщение...' : 'Написать сообщение...'"
                  class="w-full bg-transparent text-sm text-fg placeholder:text-fg-subtle focus:outline-none resize-none leading-5 block max-h-32 p-0 m-0 scrollable-text"></textarea>
      </div>
      <button type="submit"
              :disabled="chatSubmitting || !chatNewMessage?.trim()"
              :title="chatEditingMessage ? 'Сохранить изменения' : 'Отправить сообщение'"
              class="w-10 h-10 rounded-full bg-primary text-primary-fg flex items-center justify-center shrink-0 shadow-md hover:bg-primary/90 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
        <template x-if="!chatSubmitting">
          <div class="flex items-center justify-center">
            <template x-if="chatEditingMessage">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
              </svg>
            </template>
            <template x-if="!chatEditingMessage">
              <svg class="w-5 h-5 translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
              </svg>
            </template>
          </div>
        </template>
        <template x-if="chatSubmitting">
          <div class="w-4 h-4 rounded-full border-2 border-primary-fg border-t-transparent animate-spin"></div>
        </template>
      </button>
    </div>
    <p x-show="chatError" class="text-xs text-error px-2" x-text="chatError"></p>
  </form>
</div>
@endsection

