import { baseInit, withTmaBase } from '../shared.js';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    customers: [],
    customersPage: 1,
    customersLastPage: 1,
    customersTotal: 0,
    loading: true,
    search: '',
    searchTimeout: null,
    sort: 'latest',
    repeatOnly: false,
    blacklistedOnly: false,

    get hasMore() {
      return this.customersPage < this.customersLastPage;
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;

      await this.loadCustomers(1);
    },

    async loadCustomers(page = 1) {
      this.loading = true;
      try {
        const query = {
          page,
          per_page: this.isDesktopMode ? 24 : 12,
          sort: this.sort,
        };

        if (this.search && this.search.trim()) {
          query.search = this.search.trim();
        }

        if (this.repeatOnly) {
          query.min_orders = 2;
        }

        if (this.blacklistedOnly) {
          query.is_blacklisted = 1;
        }

        const res = await this.apiFetch(CustomerController.index({ query }));
        if (!res.ok) throw new Error('Ошибка загрузки клиентов');

        const data = await res.json();
        const items = data.customers || [];
        const pagination = data.pagination || {};

        if (page === 1) {
          this.customers = items;
        } else {
          this.customers.push(...items);
        }

        this.customersPage = pagination.current_page || page;
        this.customersLastPage = pagination.last_page || 1;
        this.customersTotal = pagination.total || this.customers.length;
      } catch (err) {
        console.error('loadCustomers error:', err);
        window.notify?.(err.message || 'Ошибка загрузки', 'error');
      } finally {
        this.loading = false;
      }
    },

    onSearch() {
      if (this.searchTimeout) clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.loadCustomers(1);
      }, 350);
    },

    setSort(val) {
      if (this.sort === val) return;
      this.sort = val;
      this.loadCustomers(1);
    },

    toggleRepeatOnly() {
      this.repeatOnly = !this.repeatOnly;
      this.loadCustomers(1);
    },

    toggleBlacklistedOnly() {
      this.blacklistedOnly = !this.blacklistedOnly;
      this.loadCustomers(1);
    },

    loadMore() {
      if (this.hasMore && !this.loading) {
        this.loadCustomers(this.customersPage + 1);
      }
    },
  });
}
