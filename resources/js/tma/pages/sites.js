import { baseInit, withTmaBase } from '../shared.js';
import SiteController from '@/actions/App/Http/Controllers/SiteController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    sites: [],
    sitesLoading: true,
    savingSite: false,
    actionLoading: false,

    search: '',
    searchTimeout: null,
    sitesPage: 1,
    sitesLastPage: 1,
    sitesTotal: 0,
    perPage: 10,

    showCreateSiteModal: false,
    showEditSiteModal: false,

    targetSite: null,
    newSite: { name: '', domain: '', default_purchase_percent: '', is_active: true },
    editingSite: { id: null, name: '', domain: '', default_purchase_percent: '', is_active: true },
    copiedSiteId: null,

    get hasMore() {
      return this.sitesPage < this.sitesLastPage;
    },

    get paginationPages() {
      const pages = [];
      const total = this.sitesLastPage;
      const current = this.sitesPage;
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

      await this.loadSites(1);
    },

    onSearchInput() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.loadSites(1);
      }, 300);
    },

    clearSearch() {
      this.search = '';
      this.loadSites(1);
    },

    async goToPage(page) {
      if (page === '...' || page === this.sitesPage || page < 1 || page > this.sitesLastPage) return;
      await this.loadSites(page);
    },

    async prevPage() {
      if (this.sitesPage > 1) {
        await this.loadSites(this.sitesPage - 1);
      }
    },

    async nextPage() {
      if (this.sitesPage < this.sitesLastPage) {
        await this.loadSites(this.sitesPage + 1);
      }
    },

    async loadMore() {
      if (this.hasMore && !this.sitesLoading) {
        await this.loadSites(this.sitesPage + 1, true);
      }
    },

    async loadSites(page = 1, append = false) {
      this.sitesLoading = true;
      try {
        const query = {
          page,
          per_page: this.perPage,
        };

        if (this.search && this.search.trim()) {
          query.search = this.search.trim();
        }

        const data = await this.api(SiteController.index({ query }));
        const items = data.sites || [];
        const pagination = data.pagination || {};

        if (append) {
          this.sites.push(...items);
        } else {
          this.sites = items;
        }

        this.sitesPage = pagination.current_page || page;
        this.sitesLastPage = pagination.last_page || 1;
        this.sitesTotal = pagination.total ?? (append ? this.sites.length : items.length);
      } catch (err) {
        console.error('loadSites error:', err);
        window.notify?.(err.message || 'Ошибка загрузки сайтов', 'error');
      } finally {
        this.sitesLoading = false;
      }
    },

    async createSite() {
      if (!this.newSite.name || !this.newSite.domain) {
        window.notify?.('Заполните название и домен', 'error');
        return;
      }

      this.savingSite = true;
      try {
        const payload = {
          name: this.newSite.name,
          domain: this.newSite.domain,
          is_active: this.newSite.is_active,
          default_purchase_percent: this.newSite.default_purchase_percent !== '' && this.newSite.default_purchase_percent !== null
            ? Number(this.newSite.default_purchase_percent)
            : null,
        };

        const res = await this.api(SiteController.store(), {
          body: JSON.stringify(payload),
        });

        const createdSite = res?.site;
        const plainToken = res?.plain_token || createdSite?.api_token;

        await this.loadSites(1);
        if (createdSite && plainToken && !plainToken.includes('•')) {
          const matching = this.sites.find(s => s.id === createdSite.id);
          if (matching) {
            matching.api_token = plainToken;
          }
        }
        this.showCreateSiteModal = false;
        this.newSite = { name: '', domain: '', default_purchase_percent: '', is_active: true };
        this.hapticSuccess?.();
        window.notify?.('Сайт успешно добавлен. Скопируйте API-токен!', 'success');
      } catch (err) {
        window.notify?.(err.message || 'Ошибка создания сайта', 'error');
      } finally {
        this.savingSite = false;
      }
    },

    openEditSite(site) {
      this.editingSite = {
        id: site.id,
        name: site.name,
        domain: site.domain,
        default_purchase_percent: site.default_purchase_percent ?? '',
        is_active: Boolean(site.is_active),
      };
      this.showEditSiteModal = true;
    },

    async updateSite() {
      if (!this.editingSite.id) return;
      this.savingSite = true;
      try {
        await this.api(SiteController.update(this.editingSite.id), {
          body: JSON.stringify({
            name: this.editingSite.name,
            domain: this.editingSite.domain,
            default_purchase_percent: this.editingSite.default_purchase_percent !== '' && this.editingSite.default_purchase_percent !== null
              ? Number(this.editingSite.default_purchase_percent)
              : null,
            is_active: this.editingSite.is_active,
          }),
        });

        await this.loadSites(this.sitesPage);
        this.showEditSiteModal = false;
        this.hapticSuccess?.();
        window.notify?.('Сайт обновлен', 'success');
      } catch (err) {
        window.notify?.(err.message || 'Ошибка сохранения', 'error');
      } finally {
        this.savingSite = false;
      }
    },

    async openRotateConfirm(site) {
      if (!site || this.actionLoading) return;
      const ok = await window.confirmDialog({
        title: 'Обновить API-токен?',
        message: `Сгенерировать новый API-токен для сайта "${site.name}"? Предыдущий токен немедленно перестанет работать.`,
        confirmText: 'Обновить токен',
        variant: 'primary',
      });
      if (!ok) return;

      this.targetSite = site;
      await this.confirmRotateToken();
    },

    async confirmRotateToken() {
      if (!this.targetSite) return;
      const authOk = await this.requestBiometricAuth('Подтвердите выпуск нового API-токена');
      if (!authOk) return;
      this.actionLoading = true;
      try {
        const data = await this.api(SiteController.rotateToken(this.targetSite.id));

        this.targetSite.api_token = data.api_token;
        this.targetSite = null;
        this.hapticSuccess?.();
        window.notify?.('API-токен обновлен', 'success');
      } catch (err) {
        window.notify?.(err.message || 'Ошибка обновления токена', 'error');
      } finally {
        this.actionLoading = false;
      }
    },

    async openRevokeConfirm(site) {
      if (!site || this.actionLoading) return;
      const ok = await window.confirmDialog({
        title: 'Отозвать API-токен?',
        message: `Прием заказов с сайта "${site.name}" будет заблокирован до момента генерации нового токена.`,
        confirmText: 'Отозвать',
        variant: 'danger',
      });
      if (!ok) return;

      this.targetSite = site;
      await this.confirmRevokeToken();
    },

    async confirmRevokeToken() {
      if (!this.targetSite) return;
      const authOk = await this.requestBiometricAuth('Подтвердите отзыв API-токена');
      if (!authOk) return;
      this.actionLoading = true;
      try {
        await this.api(SiteController.revokeToken(this.targetSite.id));

        this.targetSite.api_token = null;
        this.targetSite = null;
        this.hapticSuccess?.();
        window.notify?.('API-токен успешно отозван', 'warn');
      } catch (err) {
        window.notify?.(err.message || 'Ошибка отзыва токена', 'error');
      } finally {
        this.actionLoading = false;
      }
    },

    async openDeleteConfirm(site) {
      if (!site || this.actionLoading) return;
      const ok = await window.confirmDialog({
        title: 'Удалить сайт?',
        message: `Сайт "${site.name}" будет удален из системы. Это действие нельзя отменить.`,
        confirmText: 'Удалить сайт',
        variant: 'danger',
      });
      if (!ok) return;

      this.targetSite = site;
      await this.confirmDeleteSite();
    },

    async confirmDeleteSite() {
      if (!this.targetSite) return;
      const authOk = await this.requestBiometricAuth(`Подтвердите удаление сайта «${this.targetSite.name}»`);
      if (!authOk) return;
      this.actionLoading = true;
      try {
        await this.api(SiteController.destroy(this.targetSite.id));

        const targetPage = (this.sites.length === 1 && this.sitesPage > 1) ? this.sitesPage - 1 : this.sitesPage;
        await this.loadSites(targetPage);
        this.targetSite = null;
        this.hapticSuccess?.();
        window.notify?.('Сайт удален', 'success');
      } catch (err) {
        window.notify?.(err.message || 'Ошибка удаления сайта', 'error');
      } finally {
        this.actionLoading = false;
      }
    },

    async copySiteToken(site) {
      if (!site.api_token) return;
      if (site.api_token.includes('•')) {
        window.notify?.('Токен защищен SHA-256 хешированием. Чтобы получить ключ, сгенерируйте новый.', 'warn');
        return;
      }
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(site.api_token);
        } else {
          const textarea = document.createElement('textarea');
          textarea.value = site.api_token;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.focus();
          textarea.select();
          document.execCommand('copy');
          document.body.removeChild(textarea);
        }
        this.copiedSiteId = site.id;
        setTimeout(() => { this.copiedSiteId = null; }, 2000);
        window.notify?.('API-токен скопирован в буфер', 'success');
      } catch (e) {
        window.notify?.('Не удалось скопировать', 'error');
      }
    },
  });
}
