import { baseInit, withTmaBase } from '../shared.js';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import customers from '@/routes/tma/customers';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    customerId: null,
    customer: null,
    leads: [],
    leadsTotal: 0,
    loading: false,
    loadingLeads: false,
    showEditModal: false,
    saving: false,
    editForm: {
      name: '',
      phone: '',
      email: '',
      address: '',
      is_blacklisted: false,
      blacklist_reason: '',
      tags: [],
      tagInput: '',
    },

    goBack() {
      if (this.showEditModal) {
        this.showEditModal = false;
        return;
      }
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = customers.index.url();
      }
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;

      const match = window.location.pathname.match(/\/tma\/customers\/(\d+)/);
      if (match) {
        this.customerId = parseInt(match[1], 10);
        await this.loadCustomer();
      }
    },

    async loadCustomer() {
      if (!this.customerId) return;
      this.loading = true;
      try {
        const res = await this.apiFetch(CustomerController.show(this.customerId));
        if (!res.ok) throw new Error('Клиент не найден');
        const data = await res.json();
        this.customer = data.customer;
        await this.loadCustomerLeads();
      } catch (err) {
        console.error('loadCustomer error:', err);
        window.notify?.(err.message || 'Ошибка загрузки', 'error');
      } finally {
        this.loading = false;
      }
    },

    async loadCustomerLeads() {
      if (!this.customerId) return;
      this.loadingLeads = true;
      try {
        const res = await this.apiFetch(LeadController.customerLeads(this.customerId));
        if (!res.ok) return;
        const data = await res.json();
        this.leads = data.leads || [];
        this.leadsTotal = data.total_count || 0;
      } catch (err) {
        console.error('loadCustomerLeads error:', err);
      } finally {
        this.loadingLeads = false;
      }
    },

    openEditModal() {
      if (!this.customer) return;
      this.editForm = {
        name: this.customer.name || '',
        phone: this.customer.phone || '',
        email: this.customer.email || '',
        address: this.customer.address || '',
        is_blacklisted: !!this.customer.is_blacklisted,
        blacklist_reason: this.customer.blacklist_reason || '',
        tags: Array.isArray(this.customer.tags) ? [...this.customer.tags] : [],
        tagInput: '',
      };
      this.showEditModal = true;
    },

    toggleTag(tag) {
      if (!this.editForm.tags) this.editForm.tags = [];
      const idx = this.editForm.tags.indexOf(tag);
      if (idx >= 0) {
        this.editForm.tags.splice(idx, 1);
      } else {
        this.editForm.tags.push(tag);
      }
    },

    addCustomTag() {
      const tag = (this.editForm.tagInput || '').trim();
      if (!tag) return;
      if (!this.editForm.tags) this.editForm.tags = [];
      if (!this.editForm.tags.includes(tag)) {
        this.editForm.tags.push(tag);
      }
      this.editForm.tagInput = '';
    },

    async saveCustomer() {
      this.saving = true;
      try {
        const payload = {
          name: this.editForm.name,
          phone: this.editForm.phone,
          email: this.editForm.email,
          address: this.editForm.address,
          is_blacklisted: this.editForm.is_blacklisted,
          blacklist_reason: this.editForm.is_blacklisted ? this.editForm.blacklist_reason : null,
          tags: this.editForm.tags,
        };
        const res = await this.apiFetch(CustomerController.update(this.customerId), {
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        if (!res.ok) {
          const err = await res.json();
          throw new Error(err.message || 'Ошибка сохранения');
        }

        const data = await res.json();
        this.customer = data.customer;
        this.showEditModal = false;
        window.notify?.('Данные сохранены', 'success');
      } catch (err) {
        window.notify?.(err.message || 'Ошибка сохранения', 'error');
      } finally {
        this.saving = false;
      }
    },
  });
}
