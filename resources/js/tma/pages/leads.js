import { baseInit, withTmaBase } from '../shared.js';
import LeadController from '@/actions/App/Http/Controllers/LeadController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    leads: [],
    leadsPage: 1,
    leadsLastPage: 1,
    leadsTotal: 0,
    loading: true,
    search: '',
    searchTimeout: null,
    statusFilter: '',
    customerIdFilter: null,
    convertingId: null,
    createdOrderModal: false,
    createdOrderId: null,

    get hasMore() {
      return this.leadsPage < this.leadsLastPage;
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;

      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('customer_id')) {
        this.customerIdFilter = urlParams.get('customer_id');
      }
      if (urlParams.has('status')) {
        this.statusFilter = urlParams.get('status');
      }

      await this.loadLeads(1);
    },

    async loadLeads(page = 1) {
      this.loading = true;
      try {
        const query = {
          page,
          per_page: this.isDesktopMode ? 24 : 12,
        };

        if (this.search && this.search.trim()) {
          query.search = this.search.trim();
        }

        if (this.statusFilter) {
          query.status = this.statusFilter;
        }

        if (this.customerIdFilter) {
          query.customer_id = this.customerIdFilter;
        }

        const res = await this.apiFetch(LeadController.index({ query }));
        if (!res.ok) throw new Error('Ошибка загрузки заявок');

        const data = await res.json();
        const items = data.data || [];

        if (page === 1) {
          this.leads = items;
        } else {
          this.leads = [...this.leads, ...items];
        }

        this.leadsPage = data.current_page || 1;
        this.leadsLastPage = data.last_page || 1;
        this.leadsTotal = data.total || 0;
      } catch (err) {
        console.error('[TMA] Failed to load leads:', err);
        if (this.showAlert) {
          this.showAlert(err.message || 'Ошибка загрузки заявок');
        }
      } finally {
        this.loading = false;
      }
    },

    async loadMore() {
      if (this.loading || !this.hasMore) return;
      await this.loadLeads(this.leadsPage + 1);
    },

    onSearch() {
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
      }
      this.searchTimeout = setTimeout(() => {
        this.loadLeads(1);
      }, 350);
    },

    setStatusFilter(status) {
      this.statusFilter = status;
      this.loadLeads(1);
    },

    clearCustomerFilter() {
      this.customerIdFilter = null;
      const url = new URL(window.location);
      url.searchParams.delete('customer_id');
      window.history.replaceState({}, '', url);
      this.loadLeads(1);
    },

    async updateStatus(lead, newStatus) {
      try {
        const res = await this.apiFetch(LeadController.update(lead.id), {
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ status: newStatus }),
        });

        if (!res.ok) throw new Error('Не удалось обновить статус');
        const data = await res.json();

        lead.status = newStatus;
        if (data.lead) {
          lead.status_label = data.lead.status_label;
          lead.status_color = data.lead.status_color;
        }
      } catch (err) {
        console.error('[TMA] Status update failed:', err);
        if (this.showAlert) {
          this.showAlert(err.message || 'Ошибка обновления статуса');
        }
      }
    },

    async convertToOrder(lead) {
      if (this.convertingId) return;
      this.convertingId = lead.id;

      try {
        const res = await this.apiFetch(LeadController.convertToOrder(lead.id), {
          headers: { 'Content-Type': 'application/json' },
        });

        if (!res.ok) {
          const errData = await res.json().catch(() => ({}));
          throw new Error(errData.message || 'Ошибка конвертации в заказ');
        }

        const data = await res.json();
        lead.converted_order_id = data.order_id;
        lead.status = 'converted';
        lead.status_label = 'Конвертирован в заказ';

        this.createdOrderId = data.order_id;
        this.createdOrderModal = true;
      } catch (err) {
        console.error('[TMA] Convert to order failed:', err);
        if (this.showAlert) {
          this.showAlert(err.message || 'Не удалось создать заказ');
        } else {
          alert(err.message || 'Не удалось создать заказ');
        }
      } finally {
        this.convertingId = null;
      }
    },

    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },

    pluralize(n, one, two, five) {
      const num = Math.abs(n) % 100;
      const num10 = num % 10;
      if (num > 10 && num < 20) return five;
      if (num10 > 1 && num10 < 5) return two;
      if (num10 === 1) return one;
      return five;
    },
  });
}
