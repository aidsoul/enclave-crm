import { baseInit, withTmaBase } from '../shared.js';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    stats: { total_revenue: 0, total_purchase: 0, courier_payouts: 0, net_profit: 0 },
    orders: [],
    managersKpi: { summary: {}, managers: [] },
    couriersKpi: { summary: {}, couriers: [] },
    productsKpi: { summary: {}, products: [], pagination: { total: 0 } },
    activeKpiTab: 'managers',
    kpiLoading: false,
    dashboardLoading: false,
    period: 'today',

    get periodLabel() {
      return this.period === 'week' ? 'Неделя' : (this.period === 'month' ? 'Месяц' : 'Сегодня');
    },

    get greetingText() {
      const h = new Date().getHours();
      if (h < 6) return 'Доброй ночи';
      if (h < 12) return 'Доброе утро';
      if (h < 18) return 'Добрый день';
      return 'Добрый вечер';
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok || this.user?.role !== 'owner') return;
      await this.loadDashboard();
    },

    async loadDashboard() {
      this.dashboardLoading = true;
      this.kpiLoading = true;
      try {
        const [statsRes, ordersRes, managersRes, couriersRes, productsRes] = await Promise.all([
          this.api(DashboardController.stats({ query: { period: this.period } })).catch(() => ({})),
          this.api(DashboardController.recentOrders()).catch(() => ({})),
          this.api(DashboardController.managersKpi({ query: { period: this.period } })).catch(() => ({})),
          this.api(DashboardController.couriersKpi({ query: { period: this.period } })).catch(() => ({})),
          this.api(DashboardController.productsKpi({ query: { period: this.period, per_page: 5 } })).catch(() => ({})),
        ]);

        if (statsRes?.stats) {
          this.stats = statsRes.stats;
        }
        if (ordersRes?.orders) {
          this.orders = ordersRes.orders;
        }
        if (managersRes) {
          this.managersKpi = managersRes;
        }
        if (couriersRes) {
          this.couriersKpi = couriersRes;
        }
        if (productsRes) {
          this.productsKpi = productsRes;
        }
      } catch (e) {
        console.error('Failed to load dashboard:', e);
      } finally {
        this.dashboardLoading = false;
        this.kpiLoading = false;
      }
    },

    async setPeriod(value) {
      this.period = value;
      await this.loadDashboard();
    },
  });
}
