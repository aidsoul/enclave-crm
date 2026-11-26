import { baseInit, withTmaBase } from '../shared.js';
import { createDateRangeMixin } from '../date-range-picker.js';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  const urlParams = new URLSearchParams(window.location.search);
  const paramTab = urlParams.get('tab');
  const initialTab = paramTab === 'products' ? 'products' : (paramTab === 'couriers' ? 'couriers' : 'managers');
  const initialFrom = urlParams.get('from');
  const initialTo = urlParams.get('to');
  let initialPeriod = urlParams.get('period') || 'today';
  if (initialFrom && initialTo) {
    initialPeriod = 'custom';
  }

  let searchTimeout = null;

  return withTmaBase({
    user,
    retentionDays,
    activeTab: initialTab,
    period: initialPeriod,
    search: '',

    // Dropdown filter & sort states
    managerFilter: 'all',
    courierFilter: 'all',
    productFilter: 'all',
    managerSort: 'orders_desc',
    courierSort: 'deliveries_desc',
    productSort: 'revenue_desc',
    managerFilterDropdownOpen: false,
    courierFilterDropdownOpen: false,
    productFilterDropdownOpen: false,
    managerSortDropdownOpen: false,
    courierSortDropdownOpen: false,
    productSortDropdownOpen: false,

    getManagerFilterLabel() {
      const map = {
        all: 'Все',
        with_orders: 'С заказами',
        with_completed: 'С доставками',
        without_orders: 'Без заказов',
      };
      return map[this.managerFilter] || this.managerFilter;
    },

    getCourierFilterLabel() {
      const map = {
        all: 'Все',
        with_deliveries: 'С доставками',
        without_deliveries: 'Без доставок',
      };
      return map[this.courierFilter] || this.courierFilter;
    },

    getProductFilterLabel() {
      const map = {
        all: 'Все товары',
        with_sales: 'С продажами',
        without_sales: 'Без продаж',
      };
      return map[this.productFilter] || this.productFilter;
    },

    getManagerSortLabel() {
      const map = {
        orders_desc: 'По заказам (макс)',
        orders_asc: 'По заказам (мин)',
        conversion_desc: 'По конверсии (макс)',
        conversion_asc: 'По конверсии (мин)',
        speed_fast: 'По скорости (быстрее)',
        speed_slow: 'По скорости (медленнее)',
        name_asc: 'По имени (А-Я)',
        name_desc: 'По имени (Я-А)',
      };
      return map[this.managerSort] || this.managerSort;
    },

    getCourierSortLabel() {
      const map = {
        deliveries_desc: 'По доставкам (макс)',
        deliveries_asc: 'По доставкам (мин)',
        collected_desc: 'По оплатам (макс)',
        collected_asc: 'По оплатам (мин)',
        payout_desc: 'К выплате (макс)',
        name_asc: 'По имени (А-Я)',
        name_desc: 'По имени (Я-А)',
      };
      return map[this.courierSort] || this.courierSort;
    },

    getProductSortLabel() {
      const map = {
        revenue_desc: 'По выручке (макс)',
        profit_desc: 'По прибыли (макс)',
        units_desc: 'По проданным (макс)',
        orders_desc: 'По заказам (макс)',
        margin_desc: 'По марже % (макс)',
        buyout_desc: 'По выкупу % (макс)',
        buyout_asc: 'По выкупу % (мин)',
        name_asc: 'По названию (А-Я)',
      };
      return map[this.productSort] || this.productSort;
    },

    page: 1,
    perPage: 10,
    loading: true,
    loadingMore: false,
    managersData: { summary: {}, managers: [] },
    couriersData: { summary: {}, couriers: [] },
    productsData: { summary: {}, products: [] },

    visibleManagers: [],
    visibleCouriers: [],
    visibleProducts: [],
    hasMore: false,
    remainingCount: 0,

    async init() {
      if (initialFrom && initialTo) {
        this.customFrom = initialFrom;
        this.customTo = initialTo;
        this.pickerFrom = initialFrom;
        this.pickerTo = initialTo;
      }
      const ok = await baseInit.call(this);
      if (!ok || this.user?.role !== 'owner') return;
      await this.loadActiveData(1, false);
    },

    async loadActiveData(page = 1, append = false) {
      if (append) {
        this.loadingMore = true;
      } else {
        this.loading = true;
      }

      this.page = page;

      try {
        if (this.activeTab === 'managers') {
          const query = {
            period: this.period,
            search: this.search || undefined,
            filter: this.managerFilter,
            sort: this.managerSort,
            page,
            per_page: this.perPage,
          };

          if (this.period === 'custom' && this.customFrom && this.customTo) {
            query.from = this.customFrom;
            query.to = this.customTo;
          }

          const res = await this.api(DashboardController.managersKpi({ query }));
          if (res) {
            if (res.summary) {
              this.managersData.summary = res.summary;
            }
            const items = Array.isArray(res.managers) ? res.managers : [];
            this.visibleManagers = append ? [...this.visibleManagers, ...items] : items;
            this.hasMore = Boolean(res.pagination?.has_more);
            this.remainingCount = res.pagination?.remaining || 0;
          }
        } else if (this.activeTab === 'couriers') {
          const query = {
            period: this.period,
            search: this.search || undefined,
            filter: this.courierFilter,
            sort: this.courierSort,
            page,
            per_page: this.perPage,
          };

          if (this.period === 'custom' && this.customFrom && this.customTo) {
            query.from = this.customFrom;
            query.to = this.customTo;
          }

          const res = await this.api(DashboardController.couriersKpi({ query }));
          if (res) {
            if (res.summary) {
              this.couriersData.summary = res.summary;
            }
            const items = Array.isArray(res.couriers) ? res.couriers : [];
            this.visibleCouriers = append ? [...this.visibleCouriers, ...items] : items;
            this.hasMore = Boolean(res.pagination?.has_more);
            this.remainingCount = res.pagination?.remaining || 0;
          }
        } else if (this.activeTab === 'products') {
          const query = {
            period: this.period,
            search: this.search || undefined,
            filter: this.productFilter,
            sort: this.productSort,
            page,
            per_page: this.perPage,
          };

          if (this.period === 'custom' && this.customFrom && this.customTo) {
            query.from = this.customFrom;
            query.to = this.customTo;
          }

          const res = await this.api(DashboardController.productsKpi({ query }));
          if (res) {
            if (res.summary) {
              this.productsData.summary = res.summary;
            }
            const items = Array.isArray(res.products) ? res.products : [];
            this.visibleProducts = append ? [...this.visibleProducts, ...items] : items;
            this.hasMore = Boolean(res.pagination?.has_more);
            this.remainingCount = res.pagination?.remaining || 0;
          }
        }
      } catch (e) {
        console.error('Failed to load analytics data:', e);
      } finally {
        this.loading = false;
        this.loadingMore = false;
      }
    },

    async setTab(tab) {
      if (this.activeTab === tab) return;
      this.activeTab = tab;
      this.page = 1;
      this.search = '';
      clearTimeout(searchTimeout);
      this.managerFilterDropdownOpen = false;
      this.courierFilterDropdownOpen = false;
      this.productFilterDropdownOpen = false;
      this.managerSortDropdownOpen = false;
      this.courierSortDropdownOpen = false;
      this.productSortDropdownOpen = false;
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
      } catch (_) {}
      await this.loadActiveData(1, false);
    },

    async setPeriod(period) {
      if (period !== 'custom') {
        this.customFrom = null;
        this.customTo = null;
      }
      this.period = period;
      this.page = 1;
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('period', period);
        if (period !== 'custom') {
          url.searchParams.delete('from');
          url.searchParams.delete('to');
        }
        window.history.replaceState({}, '', url.toString());
      } catch (_) {}
      await this.loadActiveData(1, false);
    },

    async onFilterChange() {
      this.page = 1;
      await this.loadActiveData(1, false);
    },

    async onSortChange() {
      this.page = 1;
      await this.loadActiveData(1, false);
    },

    onSearchInput() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        this.page = 1;
        this.loadActiveData(1, false);
      }, 300);
    },

    clearSearch() {
      clearTimeout(searchTimeout);
      this.search = '';
      this.page = 1;
      this.loadActiveData(1, false);
    },

    async loadMore() {
      if (this.loadingMore || !this.hasMore) return;
      await this.loadActiveData(this.page + 1, true);
    },
  },
  createDateRangeMixin({
    async onApply(from, to) {
      this.period = 'custom';
      this.page = 1;
      await this.loadActiveData(1, false);
    },
    async onClear() {
      this.period = 'today';
      this.page = 1;
      await this.loadActiveData(1, false);
    },
  }));
}
