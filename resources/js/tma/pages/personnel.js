import { baseInit, withTmaBase } from '../shared.js';
import PersonnelController from '@/actions/App/Http/Controllers/PersonnelController';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    personnel: [],
    personnelPage: 1,
    personnelLastPage: 1,
    personnelTotal: 0,
    perPage: 15,
    personnelLoading: true,
    personnelSearch: '',
    personnelSearchTimeout: null,
    roleFilter: 'all',
    statusFilter: 'all',
    showEditModal: false,
    editingMember: null,
    editForm: { system_name: '', role: 'manager' },
    savingMember: false,
    invitations: [],
    invitationsLoading: false,
    showInvitationsSection: false,
    showInvitationsModal: false,
    inviteModalView: 'list',
    copiedToken: null,
    showCreateModal: false,
    showInviteModal: false,
    revokingInvite: false,
    unblocking: false,
    creatingPersonnel: false,
    creatingInvite: false,
    panickingMember: false,
    deletingMember: false,
    newUser: { system_name: '', role: 'manager', telegram_user_id: '' },
    newInvite: { role: 'manager', max_activations: 1, expires_at: '', system_name: '' },
    createRoleDropdownOpen: false,
    inviteRoleDropdownOpen: false,
    panicConfirmText: '',
    pendingPanicsMember: null,

    getRoleLabel(role) {
      const map = {
        manager: 'Менеджер',
        courier: 'Курьер',
        owner: 'Владелец',
      };
      return map[role] || role;
    },

    _personnelReqId: 0,

    get hasActiveFilters() {
      return this.roleFilter !== 'all' || this.statusFilter !== 'all' || (this.personnelSearch && this.personnelSearch.trim().length > 0);
    },

    get paginationPages() {
      const pages = [];
      const total = this.personnelLastPage;
      const current = this.personnelPage;
      if (total <= 5) {
        for (let i = 1; i <= total; i++) pages.push(i);
      } else {
        pages.push(1);
        let start = Math.max(2, current - 1);
        let end = Math.min(total - 1, current + 1);
        if (start > 2) pages.push('...');
        for (let i = start; i <= end; i++) pages.push(i);
        if (end < total - 1) pages.push('...');
        pages.push(total);
      }
      return pages;
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok || this.user?.role !== 'owner') return;
      await this.loadPersonnel();
    },

    openInvitationsModal() {
      this.showInvitationsModal = true;
      this.inviteModalView = 'list';
      if (this.invitations.length === 0 && !this.invitationsLoading) {
        this.loadInvitations();
      }
    },

    async loadPersonnel(targetPage = null) {
      if (targetPage !== null) {
        this.personnelPage = targetPage;
      }

      const reqId = ++this._personnelReqId;
      this.personnelLoading = true;

      try {
        const query = {
          page: this.personnelPage,
          per_page: this.perPage,
        };

        if (this.personnelSearch && this.personnelSearch.trim()) {
          query.search = this.personnelSearch.trim();
        }

        if (this.roleFilter && this.roleFilter !== 'all') {
          query.role = this.roleFilter;
        }

        if (this.statusFilter && this.statusFilter !== 'all') {
          query.status = this.statusFilter;
        }

        const res = await this.api(PersonnelController.index({ query }));

        if (reqId !== this._personnelReqId) return;

        this.personnel = res?.personnel || [];
        this.personnelPage = res?.current_page || 1;
        this.personnelLastPage = res?.last_page || 1;
        this.personnelTotal = res?.total ?? this.personnel.length;
      } catch (e) {
        if (reqId === this._personnelReqId) {
          console.error('Failed to load personnel:', e);
        }
      } finally {
        if (reqId === this._personnelReqId) {
          this.personnelLoading = false;
        }
      }
    },

    onPersonnelSearch() {
      clearTimeout(this.personnelSearchTimeout);
      this.personnelSearchTimeout = setTimeout(() => {
        this.personnelPage = 1;
        this.loadPersonnel();
      }, 350);
    },

    setRoleFilter(role) {
      if (this.roleFilter === role) return;
      this.roleFilter = role;
      this.personnelPage = 1;
      this.loadPersonnel();
    },

    setStatusFilter(status) {
      if (this.statusFilter === status) return;
      this.statusFilter = status;
      this.personnelPage = 1;
      this.loadPersonnel();
    },

    resetFilters() {
      this.roleFilter = 'all';
      this.statusFilter = 'all';
      this.personnelSearch = '';
      this.personnelPage = 1;
      this.loadPersonnel();
    },

    goToPage(page) {
      if (typeof page !== 'number') return;
      if (page < 1 || page > this.personnelLastPage || page === this.personnelPage) return;
      this.loadPersonnel(page);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    prevPage() {
      if (this.personnelPage > 1) {
        this.goToPage(this.personnelPage - 1);
      }
    },

    nextPage() {
      if (this.personnelPage < this.personnelLastPage) {
        this.goToPage(this.personnelPage + 1);
      }
    },

    canEditName(member) {
      if (!member) return false;
      if (this.user && member.id === this.user.id) return true;
      return member.role !== 'owner';
    },

    canEditRole(member) {
      if (!member) return false;
      return member.role !== 'owner';
    },

    canEdit(member) {
      return this.canEditName(member) || this.canEditRole(member);
    },

    openEditModal(member) {
      if (!member || !this.canEdit(member)) {
        this.$dispatch('notify', { message: 'Редактирование недоступно', type: 'error' });
        return;
      }
      this.editingMember = member;
      this.editForm = {
        system_name: member.system_name || '',
        role: member.role === 'courier' ? 'courier' : 'manager',
      };
      this.showEditModal = true;
    },

    async saveMember() {
      if (!this.editingMember) return;

      const payload = {};
      const newName = (this.editForm.system_name || '').trim();

      if (this.canEditName(this.editingMember)) {
        if (!newName) {
          this.$dispatch('notify', { message: 'Имя не может быть пустым', type: 'error' });
          return;
        }
        if (newName !== this.editingMember.system_name) {
          payload.system_name = newName;
        }
      }

      if (this.canEditRole(this.editingMember)) {
        if (this.editForm.role !== this.editingMember.role) {
          payload.role = this.editForm.role;
        }
      }

      if (Object.keys(payload).length === 0) {
        this.showEditModal = false;
        this.editingMember = null;
        return;
      }

      this.savingMember = true;
      try {
        await this.api(PersonnelController.update(this.editingMember.id), {
          body: JSON.stringify(payload),
        });

        if (this.user && this.editingMember.id === this.user.id && payload.system_name) {
          this.user.system_name = payload.system_name;
        }

        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Данные сотрудника обновлены' });
        this.showEditModal = false;
        setTimeout(() => {
          if (!this.showEditModal) this.editingMember = null;
        }, 200);
        await this.loadPersonnel();
      } catch (e) {
        let errMsg = 'Ошибка сохранения';
        try {
          if (e?.body) {
            const parsed = JSON.parse(e.body);
            if (parsed.error) errMsg = parsed.error;
            else if (parsed.message) errMsg = parsed.message;
          }
        } catch (_) {}
        this.$dispatch('notify', { message: errMsg, type: 'error' });
      } finally {
        this.savingMember = false;
      }
    },

    async createPersonnel() {
      const { system_name, role, telegram_user_id } = this.newUser;

      if (!system_name || !role || !telegram_user_id) {
        this.$dispatch('notify', { message: 'Заполните все поля', type: 'error' });
        return;
      }

      this.creatingPersonnel = true;
      try {
        await this.api(PersonnelController.store(), {
          body: JSON.stringify({
            system_name,
            role,
            telegram_user_id: parseInt(telegram_user_id, 10),
          }),
        });

        this.newUser = { system_name: '', role: 'manager', telegram_user_id: '' };
        this.showCreateModal = false;
        await this.loadPersonnel(1);
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Пользователь создан' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка создания', type: 'error' });
      } finally {
        this.creatingPersonnel = false;
      }
    },

    async loadInvitations() {
      if (this.invitationsLoading) return;
      this.invitationsLoading = true;

      try {
        const res = await this.api(InvitationController.index());
        this.invitations = res?.invitations || [];
      } catch (e) {
        console.error('Failed to load invitations:', e);
      } finally {
        this.invitationsLoading = false;
      }
    },

    openCreateInvite() {
      if (!this.newInvite.expires_at) {
        this.setInviteExpiry(24 * 7);
      }
      this.inviteModalView = 'create';
    },

    setInviteExpiry(hours) {
      const d = new Date();
      d.setHours(d.getHours() + hours);
      const pad = (n) => String(n).padStart(2, '0');
      this.newInvite.expires_at = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    },

    async createInvite() {
      const { role, max_activations, expires_at, system_name } = this.newInvite;

      if (!role || !max_activations || !expires_at) {
        this.$dispatch('notify', { message: 'Заполните обязательные поля', type: 'error' });
        return;
      }

      this.creatingInvite = true;
      try {
        const isoExpiresAt = new Date(expires_at + ':00').toISOString();
        await this.api(InvitationController.store(), {
          body: JSON.stringify({
            role,
            max_activations: parseInt(max_activations, 10),
            expires_at: isoExpiresAt,
            system_name: system_name || null,
          }),
        });

        this.newInvite = { role: 'manager', max_activations: 1, expires_at: '', system_name: '' };
        this.showInviteModal = false;
        this.inviteModalView = 'list';
        await this.loadInvitations();
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Приглашение создано' });
      } catch (e) {
        this.hapticError?.();
        const message = e?.body ? JSON.parse(e.body).error : 'Ошибка создания приглашения';
        this.$dispatch('notify', { message, type: 'error' });
      } finally {
        this.creatingInvite = false;
      }
    },

    async promptRevokeInvite(inv) {
      if (!inv || this.revokingInvite) return;
      const roleLabel = inv.role_label + (inv.system_name ? ` (${inv.system_name})` : '');
      const ok = await window.confirmDialog({
        title: 'Отозвать приглашение?',
        message: `Приглашение для роли ${roleLabel} будет аннулировано, и ссылка перестанет действовать.`,
        confirmText: 'Отозвать',
        variant: 'danger',
      });
      if (!ok) return;

      this.revokingInvite = true;
      try {
        await this.api(InvitationController.destroy(inv.id));
        await this.loadInvitations();
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Приглашение отозвано' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка отзыва', type: 'error' });
      } finally {
        this.revokingInvite = false;
      }
    },

    async revokeInvite(id) {
      try {
        await this.api(InvitationController.destroy(id));
        await this.loadInvitations();
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Приглашение отозвано' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка отзыва', type: 'error' });
      }
    },

    getInviteLink(token) {
      if (!token) return '';
      const bot = (this.botUsername || '').replace(/^@/, '');
      if (!bot) return `https://t.me/ВАШ_БОТ?start=${token}`;
      return `https://t.me/${bot}?start=${token}`;
    },

    async copyLink(token) {
      if (!token) return;
      const link = this.getInviteLink(token);
      if (!link) return;
      try {
        await navigator.clipboard.writeText(link);
        this.copiedToken = token;
        setTimeout(() => {
          if (this.copiedToken === token) {
            this.copiedToken = null;
          }
        }, 2000);
        this.$dispatch('notify', { message: 'Ссылка скопирована' });
      } catch (e) {
        this.$dispatch('notify', { message: 'Не удалось скопировать', type: 'error' });
      }
    },

    async showPanicFor(member) {
      if (!member || this.panickingMember) return;
      const name = member.system_name || '';
      const ok = await window.confirmDialog({
        title: 'Заблокировать сотрудника?',
        message: `Доступ сотрудника ${name} будет ограничен, а все активные сессии завершены.`,
        confirmText: 'Заблокировать',
        variant: 'danger',
      });
      if (!ok) return;

      this.panickingMember = true;
      try {
        await this.api(PersonnelController.update(member.id), {
          body: JSON.stringify({ is_active: false }),
        });
        await this.loadPersonnel();
        this.$dispatch('notify', { message: 'Доступ заблокирован', type: 'error' });
      } catch (_) {
        this.$dispatch('notify', { message: 'Ошибка блокировки', type: 'error' });
      } finally {
        this.panickingMember = false;
      }
    },

    async showUnblockFor(member) {
      if (!member || this.unblocking) return;
      const name = member.system_name || '';
      const ok = await window.confirmDialog({
        title: 'Разблокировать пользователя?',
        message: `${name} снова получит доступ к системе.`,
        confirmText: 'Разблокировать',
        variant: 'primary',
      });
      if (!ok) return;

      this.unblocking = true;
      try {
        await this.api(PersonnelController.update(member.id), {
          body: JSON.stringify({ is_active: true }),
        });
        await this.loadPersonnel();
        this.$dispatch('notify', { message: 'Пользователь разблокирован' });
      } catch (e) {
        this.$dispatch('notify', { message: 'Ошибка разблокировки', type: 'error' });
      } finally {
        this.unblocking = false;
      }
    },

    async deleteMember(id) {
      if (!id || this.deletingMember) return;
      const ok = await window.confirmDialog({
        title: 'Удалить пользователя?',
        message: 'Это действие необратимо. Записи пользователя будут удалены из системы.',
        confirmText: 'Удалить',
        variant: 'danger',
      });
      if (!ok) return;

      this.deletingMember = true;
      try {
        await this.api(PersonnelController.destroy(id));
        await this.loadPersonnel();
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Пользователь удалён' });
      } catch (_) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка удаления', type: 'error' });
      } finally {
        this.deletingMember = false;
      }
    },
  });
}
