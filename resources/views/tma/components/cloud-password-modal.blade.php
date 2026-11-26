{{-- Единое модальное окно облачного пароля (Onboarding & Settings) --}}
<div x-show="showCloudPasswordModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
     @keydown.escape.window="closeCloudPasswordModal()">
  <div class="bg-surface rounded-2xl p-6 max-w-sm w-full shadow-2xl relative text-left"
       @click.away="closeCloudPasswordModal()">
    
    <div class="flex items-center gap-3 mb-2.5">
      <div class="w-10 h-10 rounded-xl bg-primary/15 text-primary flex items-center justify-center shrink-0 border border-primary/20">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
        </svg>
      </div>
      <div>
        <h3 class="font-display font-bold text-base text-fg"
            x-text="cloudPasswordMode === 'change' ? 'Смена облачного пароля' : 'Облачный пароль (2FA)'"></h3>
        <p class="text-xs font-medium"
           :class="cloudPasswordMode === 'change' ? 'text-fg-subtle' : 'text-primary'"
           x-text="cloudPasswordMode === 'change' ? 'Безопасность аккаунта' : 'Рекомендуется настроить сейчас'"></p>
      </div>
    </div>

    <p class="text-fg-muted text-xs leading-relaxed mb-4">
      Этот пароль запрашивается при входе в систему с новых IP-адресов и неизвестных устройств для защиты ваших данных.
    </p>

    <form @submit.prevent="submitCloudPassword()" class="space-y-3.5">
      <div x-show="cloudPasswordMode === 'change'" class="space-y-1">
        <label class="block text-xs font-medium text-fg-subtle">
          Текущий пароль
        </label>
        <input type="password"
               x-model="cloudCurrentPassword"
               autocomplete="current-password"
               placeholder="Текущий пароль"
               class="w-full px-3.5 py-2.5 bg-surface2 rounded-xl text-sm font-mono text-fg focus:border-primary focus:outline-none transition-colors">
      </div>

      <div class="space-y-1">
        <label class="block text-xs font-medium text-fg-subtle">
          <span x-text="cloudPasswordMode === 'change' ? 'Новый пароль' : 'Придумайте пароль'"></span>
          <span class="text-[11px] text-fg-muted font-normal">(мин. 4 символа)</span>
        </label>
        <input type="password"
               x-model="cloudNewPassword"
               autocomplete="new-password"
               placeholder="••••••••"
               class="w-full px-3.5 py-2.5 bg-surface2 rounded-xl text-sm font-mono text-fg focus:border-primary focus:outline-none transition-colors">
      </div>

      <div class="space-y-1">
        <label class="block text-xs font-medium text-fg-subtle">
          Повторите новый пароль
        </label>
        <input type="password"
               x-model="cloudNewPasswordConfirm"
               autocomplete="new-password"
               placeholder="••••••••"
               class="w-full px-3.5 py-2.5 bg-surface2 rounded-xl text-sm font-mono text-fg focus:border-primary focus:outline-none transition-colors">
      </div>

      <div x-show="cloudPasswordError"
           class="p-2.5 bg-error/10 border border-error/20 rounded-xl text-xs text-error font-medium"
           x-text="cloudPasswordError"></div>

      <div class="flex items-center gap-2.5 pt-2">
        <button type="button"
                @click="closeCloudPasswordModal()"
                class="flex-1 px-4 py-2.5 bg-surface2 text-fg rounded-xl text-sm font-medium hover:bg-surface2/80 active:scale-95 transition-all">
          <span x-text="cloudPasswordMode === 'change' ? 'Отмена' : 'Позже'"></span>
        </button>

        <button type="submit"
                :disabled="cloudPasswordSaving || !cloudNewPassword || !cloudNewPasswordConfirm || (cloudPasswordMode === 'change' && !cloudCurrentPassword)"
                class="flex-1 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-hover active:scale-95 transition-all shadow-md disabled:opacity-50 disabled:pointer-events-none">
          <span x-show="cloudPasswordSaving" class="inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span>Сохранение...</span>
          </span>
          <span x-show="!cloudPasswordSaving"
                x-text="cloudPasswordMode === 'change' ? 'Изменить' : 'Установить'"></span>
        </button>
      </div>
    </form>
  </div>
</div>

