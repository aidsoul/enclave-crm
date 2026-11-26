import { baseInit, withTmaBase } from '../shared.js';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import PersonnelController from '@/actions/App/Http/Controllers/PersonnelController';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    orders: [],
    ordersPage: 1,
    ordersLastPage: 1,
    ordersTotal: 0,
    ordersLoading: true,
    filter: 'created',
    orderSearch: '',
    orderSearchTimeout: null,
    acceptingOrderIds: new Set(),
    customerId: null,
    customerName: '',
    selectedOrderIds: [],
    selectionMode: false,
    couriers: [],
    couriersLoaded: false,
    couriersLoading: false,
    showBulkCourierModal: false,
    selectedBulkCourierId: '',
    courierDropdownOpen: false,
    bulkCourierSearch: '',
    managers: [],
    managersLoaded: false,
    managersLoading: false,
    showBulkManagerModal: false,
    selectedBulkManagerId: '',
    managerDropdownOpen: false,
    bulkManagerSearch: '',
    bulkLoading: false,
    sortBy: 'created_at',
    sortOrder: 'desc',
    sortDropdownOpen: false,
    showFilterModal: false,
    filterSiteId: '',
    filterSiteDropdownOpen: false,
    filterSiteSearch: '',
    filterManagerId: '',
    filterManagerDropdownOpen: false,
    filterManagerSearch: '',
    filterCourierId: '',
    filterCourierDropdownOpen: false,
    filterCourierSearch: '',
    filterDatePreset: '',
    filterDateFrom: '',
    filterDateTo: '',
    filterMinPrice: '',
    filterMaxPrice: '',
    sites: [],
    sitesLoaded: false,
    sitesLoading: false,

    get ordersHasMore() {
      return this.ordersPage < this.ordersLastPage;
    },

    get defaultFilter() {
      if (this.user?.role === 'courier') {
        return '';
      }
      return 'created';
    },

    get activeFiltersCount() {
      let count = 0;
      if (this.filterSiteId) count++;
      if (this.filterManagerId) count++;
      if (this.filterCourierId) count++;
      if (this.filterDatePreset || this.filterDateFrom || this.filterDateTo) count++;
      if (this.filterMinPrice || this.filterMaxPrice) count++;
      return count;
    },

    get hasActiveFilters() {
      return this.activeFiltersCount > 0;
    },

    get activeSiteName() {
      if (!this.filterSiteId) return '';
      const s = this.sites.find(item => String(item.id) === String(this.filterSiteId));
      return s ? (s.name || s.domain) : `Сайт #${this.filterSiteId}`;
    },

    get activeManagerName() {
      if (!this.filterManagerId) return '';
      if (this.filterManagerId === 'unassigned') return 'Без менеджера';
      const m = this.managers.find(item => String(item.id) === String(this.filterManagerId));
      return m ? m.system_name : `Менеджер #${this.filterManagerId}`;
    },

    get activeCourierName() {
      if (!this.filterCourierId) return '';
      if (this.filterCourierId === 'unassigned') return 'Без курьера';
      const c = this.couriers.find(item => String(item.id) === String(this.filterCourierId));
      return c ? c.system_name : `Курьер #${this.filterCourierId}`;
    },

    get filteredFilterSites() {
      const list = this.sites || [];
      if (!this.filterSiteSearch) return list.slice(0, 10);
      const term = this.filterSiteSearch.toLowerCase().trim();
      return list.filter(s =>
        (s.name && s.name.toLowerCase().includes(term)) ||
        (s.domain && s.domain.toLowerCase().includes(term)) ||
        String(s.id).includes(term)
      ).slice(0, 10);
    },

    get filteredFilterManagers() {
      const list = this.managers || [];
      if (!this.filterManagerSearch) return list.slice(0, 10);
      const term = this.filterManagerSearch.toLowerCase().trim();
      return list.filter(m =>
        (m.system_name && m.system_name.toLowerCase().includes(term)) ||
        (m.telegram_user_id && String(m.telegram_user_id).includes(term)) ||
        String(m.id).includes(term)
      ).slice(0, 10);
    },

    get filteredFilterCouriers() {
      const list = this.couriers || [];
      if (!this.filterCourierSearch) return list.slice(0, 10);
      const term = this.filterCourierSearch.toLowerCase().trim();
      return list.filter(c =>
        (c.system_name && c.system_name.toLowerCase().includes(term)) ||
        (c.telegram_user_id && String(c.telegram_user_id).includes(term)) ||
        String(c.id).includes(term)
      ).slice(0, 10);
    },

    selectFilterSite(siteId) {
      this.filterSiteId = siteId;
      this.filterSiteDropdownOpen = false;
      this.filterSiteSearch = '';
    },

    selectFilterManager(managerId) {
      this.filterManagerId = managerId;
      this.filterManagerDropdownOpen = false;
      this.filterManagerSearch = '';
    },

    selectFilterCourier(courierId) {
      this.filterCourierId = courierId;
      this.filterCourierDropdownOpen = false;
      this.filterCourierSearch = '';
    },

    get activeDateLabel() {
      if (this.filterDatePreset === 'today') return 'Сегодня';
      if (this.filterDatePreset === 'yesterday') return 'Вчера';
      if (this.filterDatePreset === 'week') return '7 дней';
      if (this.filterDatePreset === 'month') return '30 дней';
      if (this.filterDateFrom || this.filterDateTo) {
        if (this.filterDateFrom && this.filterDateTo) return `${this.filterDateFrom} — ${this.filterDateTo}`;
        if (this.filterDateFrom) return `с ${this.filterDateFrom}`;
        return `до ${this.filterDateTo}`;
      }
      return '';
    },

    get activePriceLabel() {
      if (this.filterMinPrice && this.filterMaxPrice) return `${this.filterMinPrice} - ${this.filterMaxPrice} ₽`;
      if (this.filterMinPrice) return `от ${this.filterMinPrice} ₽`;
      if (this.filterMaxPrice) return `до ${this.filterMaxPrice} ₽`;
      return '';
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;
      const urlParams = new URLSearchParams(window.location.search);
      const cId = urlParams.get('customer_id');
      if (cId) {
        this.customerId = cId;
        this.customerName = urlParams.get('customer_name') || `Клиент #${cId}`;
        this.filter = '';
      } else {
        this.filter = this.defaultFilter;
      }
      await this.loadOrders(true);
    },

    async clearCustomerFilter() {
      this.customerId = null;
      this.customerName = '';
      this.clearSelection();
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete('customer_id');
        url.searchParams.delete('customer_name');
        window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
      } catch (e) {}
      this.filter = this.defaultFilter;
      await this.loadOrders(true);
    },

    async loadSites() {
      if (this.sitesLoaded || this.sitesLoading) return;
      this.sitesLoading = true;
      try {
        const res = await this.api(SiteController.index({ query: { all: 1 } }));
        this.sites = res?.sites || [];
        this.sitesLoaded = true;
      } catch (e) {
        console.error('Failed to load sites:', e);
      } finally {
        this.sitesLoading = false;
      }
    },

    async openFilterModal() {
      this.showFilterModal = true;
      this.sortDropdownOpen = false;
      this.filterSiteDropdownOpen = false;
      this.filterSiteSearch = '';
      this.filterManagerDropdownOpen = false;
      this.filterManagerSearch = '';
      this.filterCourierDropdownOpen = false;
      this.filterCourierSearch = '';
      await Promise.all([
        this.loadSites(),
        this.loadManagersList(''),
        this.loadCouriersList(),
      ]);
    },

    async setSort(by, order) {
      if (this.sortBy === by && this.sortOrder === order) {
        this.sortDropdownOpen = false;
        return;
      }
      this.sortBy = by;
      this.sortOrder = order;
      this.sortDropdownOpen = false;
      this.clearSelection();
      await this.loadOrders(true);
    },

    setDatePreset(preset) {
      if (this.filterDatePreset === preset) {
        this.filterDatePreset = '';
      } else {
        this.filterDatePreset = preset;
        this.filterDateFrom = '';
        this.filterDateTo = '';
      }
    },

    onCustomDateChange() {
      if (this.filterDateFrom || this.filterDateTo) {
        this.filterDatePreset = '';
      }
    },

    async applyFilters() {
      this.filterSiteDropdownOpen = false;
      this.filterManagerDropdownOpen = false;
      this.filterCourierDropdownOpen = false;
      this.showFilterModal = false;
      this.clearSelection();
      await this.loadOrders(true);
    },

    async resetFilters() {
      this.filterSiteId = '';
      this.filterManagerId = '';
      this.filterCourierId = '';
      this.filterDatePreset = '';
      this.filterDateFrom = '';
      this.filterDateTo = '';
      this.filterMinPrice = '';
      this.filterMaxPrice = '';
      this.filterSiteDropdownOpen = false;
      this.filterSiteSearch = '';
      this.filterManagerDropdownOpen = false;
      this.filterManagerSearch = '';
      this.filterCourierDropdownOpen = false;
      this.filterCourierSearch = '';
      this.showFilterModal = false;
      this.clearSelection();
      await this.loadOrders(true);
    },

    async clearSiteFilter() {
      this.filterSiteId = '';
      await this.loadOrders(true);
    },

    async clearDateFilter() {
      this.filterDatePreset = '';
      this.filterDateFrom = '';
      this.filterDateTo = '';
      await this.loadOrders(true);
    },

    async clearManagerFilter() {
      this.filterManagerId = '';
      await this.loadOrders(true);
    },

    async clearCourierFilter() {
      this.filterCourierId = '';
      await this.loadOrders(true);
    },

    async clearPriceFilter() {
      this.filterMinPrice = '';
      this.filterMaxPrice = '';
      await this.loadOrders(true);
    },

    async loadOrders(reset = false) {
      if (this.ordersLoading && !reset) return;

      if (reset) {
        this.orders = [];
        this.ordersPage = 1;
        this.ordersLastPage = 1;
      } else {
        this.ordersPage += 1;
      }

      this.ordersLoading = true;
      try {
        const query = {
          page: this.ordersPage,
          per_page: this.isDesktopMode ? 24 : 12,
          sort_by: this.sortBy,
          sort_order: this.sortOrder,
        };
        if (this.filter) {
          query.status = this.filter;
        }
        if (this.customerId) {
          query.customer_id = this.customerId;
        }
        if (this.orderSearch) {
          query.search = this.orderSearch;
        }
        if (this.filterSiteId) {
          query.site_id = this.filterSiteId;
        }
        if (this.filterManagerId) {
          query.manager_id = this.filterManagerId;
        }
        if (this.filterCourierId) {
          query.courier_id = this.filterCourierId;
        }
        if (this.filterDatePreset) {
          query.date = this.filterDatePreset;
        }
        if (this.filterDateFrom) {
          query.date_from = this.filterDateFrom;
        }
        if (this.filterDateTo) {
          query.date_to = this.filterDateTo;
        }
        if (this.filterMinPrice) {
          query.min_price = this.filterMinPrice;
        }
        if (this.filterMaxPrice) {
          query.max_price = this.filterMaxPrice;
        }
        const res = await this.api(OrderController.index({ query }));
        const items = res?.orders || [];

        if (this.ordersPage === 1) {
          this.orders = items;
        } else {
          this.orders = [...this.orders, ...items];
        }

        this.ordersPage = res?.current_page || this.ordersPage;
        this.ordersLastPage = res?.last_page || this.ordersLastPage;
        this.ordersTotal = res?.total ?? this.ordersTotal;
      } catch (e) {
        console.error('Failed to load orders:', e);
      } finally {
        this.ordersLoading = false;
      }
    },

    async setFilter(status) {
      if (this.filter === status) return;
      this.clearSelection();
      this.filter = status;
      this.ordersTotal = 0;
      await this.loadOrders(true);
    },

    async acceptOrder(orderId) {
      if (!orderId || this.acceptingOrderIds.has(orderId)) return;

      this.acceptingOrderIds.add(orderId);
      try {
        const res = await this.api(OrderController.accept(orderId));

        const updated = res?.order;
        if (updated) {
          const idx = this.orders.findIndex(o => o.id === orderId);
          if (idx !== -1) {
            if (this.filter === 'created') {
              this.orders = this.orders.filter(o => o.id !== orderId);
            } else {
              this.orders = [...this.orders];
              this.orders[idx] = { ...this.orders[idx], ...updated };
            }
          }
        }
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Заказ принят' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка принятия заказа', type: 'error' });
      } finally {
        this.acceptingOrderIds.delete(orderId);
      }
    },

    onOrderSearch() {
      clearTimeout(this.orderSearchTimeout);
      this.orderSearchTimeout = setTimeout(() => {
        this.clearSelection();
        this.loadOrders(true);
      }, 400);
    },

    onLiveOrderCreated(order) {
      if (!Array.isArray(this.orders)) return;

      const exists = this.orders.some((o) => o.id === order.id);
      if (exists) return;

      const matchesFilter = !this.filter || this.filter === 'created' || this.filter === order.status;
      const term = (this.orderSearch || '').toLowerCase().trim();
      const matchesSearch = !term ||
        String(order.id).includes(term) ||
        (order.customer?.name && order.customer.name.toLowerCase().includes(term)) ||
        (order.customer?.phone && order.customer.phone.includes(term));

      if (matchesFilter && matchesSearch) {
        this.orders = [order, ...this.orders];
      }
      this.ordersTotal = (this.ordersTotal || 0) + 1;
    },

    onLiveOrderAssigned(order) {
      if (!Array.isArray(this.orders)) return;

      const orderId = order.id;
      const exists = this.orders.some((o) => o.id === orderId);
      if (exists) {
        const idx = this.orders.findIndex((o) => o.id === orderId);
        this.orders[idx] = { ...this.orders[idx], ...order };
        this.orders = [...this.orders];
        return;
      }

      const matchesFilter = !this.filter || this.filter === 'assigned' || this.filter === order.status;
      const term = (this.orderSearch || '').toLowerCase().trim();
      const matchesSearch = !term ||
        String(order.id).includes(term) ||
        (order.customer?.name && order.customer.name.toLowerCase().includes(term)) ||
        (order.customer?.phone && order.customer.phone.includes(term));

      if (matchesFilter && matchesSearch) {
        this.orders = [order, ...this.orders];
        this.ordersTotal = (this.ordersTotal || 0) + 1;
      }
    },

    onLiveOrderStatusChanged(data) {
      const orderId = data?.order_id;
      const status = data?.status;
      if (!orderId || !status || !Array.isArray(this.orders)) return;

      const idx = this.orders.findIndex((o) => o.id === orderId);
      if (idx !== -1) {
        if (this.filter && this.filter !== status) {
          this.orders = this.orders.filter((o) => o.id !== orderId);
          if (this.ordersTotal > 0) this.ordersTotal -= 1;
        } else {
          this.orders[idx] = { ...this.orders[idx], status };
          this.orders = [...this.orders];
        }
      }
    },

    formatDate(value) {
      if (!value) return '';
      return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },

    get isOwner() {
      return this.user?.role === 'owner';
    },

    get isManager() {
      return this.user?.role === 'manager';
    },

    get selectionState() {
      if (!this.selectedOrderIds.length) return null;
      const first = (this.orders || []).find(o => o.id === this.selectedOrderIds[0]);
      if (!first) return null;
      return first.manager_id ? 'with_manager' : 'no_manager';
    },

    get selectionAction() {
      if (!this.selectionState) return null;
      if (this.selectionState === 'no_manager') {
        return this.isOwner ? 'assign_manager' : 'accept';
      }
      return 'assign_courier';
    },

    get hasUnassignedManagerInSelection() {
      return this.selectionState === 'no_manager';
    },

    get canCancelSelected() {
      if (!this.selectedOrderIds.length) return false;
      const selected = (this.orders || []).filter(o => this.selectedOrderIds.includes(o.id));
      if (!selected.length) return false;
      return selected.every(o => {
        if (o.status === 'cancelled' || o.status === 'completed') return false;
        if (this.isOwner) return true;
        if (this.isManager && o.manager_id === this.user?.id) return true;
        return false;
      });
    },

    toggleSelectionMode() {
      if (this.user?.role === 'courier') return;
      this.selectionMode = !this.selectionMode;
      if (!this.selectionMode) {
        this.selectedOrderIds = [];
      }
      this.hapticImpact?.('light');
    },

    toggleSelectOrder(id) {
      if (this.user?.role === 'courier') return;
      const idx = this.selectedOrderIds.indexOf(id);
      if (idx > -1) {
        this.selectedOrderIds.splice(idx, 1);
        if (this.selectedOrderIds.length === 0) {
          this.selectionMode = false;
        }
        this.hapticImpact?.('light');
        return;
      }

      const targetOrder = (this.orders || []).find(o => o.id === id);
      if (!targetOrder) return;

      if (this.selectedOrderIds.length > 0) {
        const firstSelected = (this.orders || []).find(o => o.id === this.selectedOrderIds[0]);
        if (firstSelected) {
          const firstHasManager = Boolean(firstSelected.manager_id);
          const targetHasManager = Boolean(targetOrder.manager_id);
          if (firstHasManager !== targetHasManager) {
            const msg = targetHasManager
              ? 'Нельзя объединять заказы с менеджером и без него (у них разные действия)'
              : 'Нельзя объединять заказы без менеджера с уже распределенными заказами';
            this.hapticError?.();
            this.$dispatch('notify', { message: msg, type: 'error' });
            return;
          }
        }
      }

      this.selectedOrderIds.push(id);
      this.selectionMode = true;
      this.hapticImpact?.('light');
    },

    isOrderSelected(id) {
      return this.selectedOrderIds.includes(id);
    },

    toggleSelectAll() {
      if (this.user?.role === 'courier') return;
      if (!this.orders || !this.orders.length) return;

      const allSelected = this.orders.every(o => this.selectedOrderIds.includes(o.id));
      if (allSelected) {
        this.selectedOrderIds = [];
        this.hapticImpact?.('light');
        return;
      }

      const targetState = this.selectedOrderIds.length > 0
        ? Boolean((this.orders || []).find(o => o.id === this.selectedOrderIds[0])?.manager_id)
        : Boolean(this.orders[0]?.manager_id);

      const compatibleOrders = (this.orders || []).filter(o => Boolean(o.manager_id) === targetState);
      if (compatibleOrders.length < this.orders.length) {
        this.$dispatch('notify', {
          message: targetState
            ? 'Выбраны только заказы с назначенным менеджером'
            : 'Выбраны только заказы без менеджера',
          type: 'info',
        });
      }
      this.selectedOrderIds = compatibleOrders.map(o => o.id);
      this.selectionMode = true;
      this.hapticImpact?.('medium');
    },

    clearSelection() {
      this.selectedOrderIds = [];
      this.selectionMode = false;
      this.hapticImpact?.('light');
    },

    async loadCouriersList() {
      if (this.couriersLoaded || this.couriersLoading) return;
      this.couriersLoading = true;
      try {
        const res = await this.api(PersonnelController.couriers());
        this.couriers = res?.couriers || [];
        this.couriersLoaded = true;
      } catch (e) {
        console.error('Failed to load couriers:', e);
      } finally {
        this.couriersLoading = false;
      }
    },

    async loadManagersList(search = '') {
      this.managersLoading = true;
      try {
        const action = search && this.isOwner
          ? PersonnelController.search({ query: { role: 'manager', limit: 20, search } })
          : PersonnelController.managers();
        const res = await this.api(action);
        this.managers = res?.managers || res?.personnel || [];
        this.managersLoaded = true;
        if (this.managers.length > 0 && (!this.selectedBulkManagerId || !this.managers.some(m => m.id === this.selectedBulkManagerId))) {
          this.selectedBulkManagerId = this.managers[0].id;
        }
      } catch (e) {
        console.error('Failed to load managers:', e);
      } finally {
        this.managersLoading = false;
      }
    },

    searchBulkManagers() {
      clearTimeout(this._bulkManagerSearchTimer);
      this._bulkManagerSearchTimer = setTimeout(() => {
        this.loadManagersList(this.bulkManagerSearch || '');
      }, 250);
    },

    get filteredBulkManagers() {
      const list = this.managers || [];
      if (!this.bulkManagerSearch) return list.slice(0, 5);
      const term = this.bulkManagerSearch.toLowerCase().trim();
      return list.filter(m =>
        (m.system_name && m.system_name.toLowerCase().includes(term)) ||
        (m.telegram_user_id && String(m.telegram_user_id).includes(term)) ||
        String(m.id).includes(term)
      ).slice(0, 5);
    },

    toggleManagerDropdown() {
      this.managerDropdownOpen = !this.managerDropdownOpen;
      if (this.managerDropdownOpen) {
        this.bulkManagerSearch = '';
        this.loadManagersList('');
      }
    },

    async openBulkManagerModal() {
      if (!this.isOwner || !this.selectedOrderIds.length) return;
      if (this.selectionState !== 'no_manager') {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Назначение менеджера доступно для нераспределенных заказов', type: 'warn' });
        return;
      }
      this.managerDropdownOpen = false;
      this.bulkManagerSearch = '';
      await this.loadManagersList('');
      this.showBulkManagerModal = true;
    },

    async submitBulkAssignManager() {
      if (!this.selectedBulkManagerId || !this.selectedOrderIds.length || this.bulkLoading) return;
      this.bulkLoading = true;
      try {
        const res = await this.api(OrderController.bulkAssignManager(), {
          body: JSON.stringify({
            order_ids: this.selectedOrderIds,
            manager_id: this.selectedBulkManagerId,
          }),
        });
        this.showBulkManagerModal = false;
        this.hapticSuccess?.();
        this.$dispatch('notify', {
          message: `Менеджер назначен на ${res.count || this.selectedOrderIds.length} ${this.pluralize(res.count || this.selectedOrderIds.length, 'заказ', 'заказа', 'заказов')}`,
        });
        this.clearSelection();
        await this.loadOrders(true);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка назначения менеджера', type: 'error' });
      } finally {
        this.bulkLoading = false;
      }
    },

    get filteredBulkCouriers() {
      const list = this.couriers || [];
      if (!this.bulkCourierSearch) return list;
      const term = this.bulkCourierSearch.toLowerCase().trim();
      return list.filter(c =>
        (c.system_name && c.system_name.toLowerCase().includes(term)) ||
        (c.telegram_user_id && String(c.telegram_user_id).includes(term)) ||
        String(c.id).includes(term)
      );
    },

    toggleCourierDropdown() {
      this.courierDropdownOpen = !this.courierDropdownOpen;
      if (this.courierDropdownOpen) {
        this.bulkCourierSearch = '';
      }
    },

    async openBulkCourierModal() {
      if (this.user?.role === 'courier' || !this.selectedOrderIds.length) return;
      if (this.hasUnassignedManagerInSelection) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Сначала необходимо принять заказы без менеджера', type: 'warn' });
        return;
      }
      this.courierDropdownOpen = false;
      this.bulkCourierSearch = '';
      await this.loadCouriersList();
      this.selectedBulkCourierId = this.couriers?.[0]?.id || '';
      this.showBulkCourierModal = true;
    },

    async submitBulkAccept() {
      if (this.user?.role === 'courier' || !this.selectedOrderIds.length || this.bulkLoading) return;
      this.bulkLoading = true;
      try {
        const res = await this.api(OrderController.bulkAccept(), {
          body: JSON.stringify({
            order_ids: this.selectedOrderIds,
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', {
          message: `Принято в работу: ${res.count || this.selectedOrderIds.length} ${this.pluralize(res.count || this.selectedOrderIds.length, 'заказ', 'заказа', 'заказов')}`,
        });
        await this.loadOrders(true);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка принятия заказов', type: 'error' });
      } finally {
        this.bulkLoading = false;
      }
    },

    async submitBulkAssign() {
      if (!this.selectedBulkCourierId || !this.selectedOrderIds.length || this.bulkLoading) return;
      this.bulkLoading = true;
      try {
        const res = await this.api(OrderController.bulkAssign(), {
          body: JSON.stringify({
            order_ids: this.selectedOrderIds,
            delivery_courier_id: this.selectedBulkCourierId,
          }),
        });
        this.showBulkCourierModal = false;
        this.hapticSuccess?.();
        this.$dispatch('notify', {
          message: `Курьер назначен на ${res.count || this.selectedOrderIds.length} ${this.pluralize(res.count || this.selectedOrderIds.length, 'заказ', 'заказа', 'заказов')}`,
        });
        this.clearSelection();
        await this.loadOrders(true);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка назначения курьера', type: 'error' });
      } finally {
        this.bulkLoading = false;
      }
    },

    async openBulkWarehouseModal() {
      if (!this.selectedOrderIds.length || this.bulkLoading) return;
      const count = this.selectedOrderIds.length;
      const ok = await window.confirmDialog({
        title: 'Перевод в статус «На складе»',
        message: `Перевести выбранные заказы (${count} шт.) в статус «На складе»?`,
        confirmText: 'Подтвердить',
        variant: 'primary',
      });
      if (!ok) return;

      await this.submitBulkAtWarehouse();
    },

    async submitBulkAtWarehouse() {
      if (!this.selectedOrderIds.length || this.bulkLoading) return;
      this.bulkLoading = true;
      try {
        const res = await this.api(OrderController.bulkAtWarehouse(), {
          body: JSON.stringify({
            order_ids: this.selectedOrderIds,
          }),
        });
        this.hapticSuccess?.();
        this.$dispatch('notify', {
          message: `Переведено в «На складе»: ${res.count || this.selectedOrderIds.length} ${this.pluralize(res.count || this.selectedOrderIds.length, 'заказ', 'заказа', 'заказов')}`,
        });
        this.clearSelection();
        await this.loadOrders(true);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка обновления статуса', type: 'error' });
      } finally {
        this.bulkLoading = false;
      }
    },

    async openBulkCancelModal() {
      if (!this.canCancelSelected || this.bulkLoading) return;
      const count = this.selectedOrderIds.length;
      const ok = await window.confirmDialog({
        title: 'Отменить заказ?',
        message: `Вы уверены, что хотите отменить выбранный заказ (${count})?`,
        confirmText: 'Да, отменить',
        variant: 'danger',
      });
      if (!ok) return;

      await this.submitBulkCancel();
    },

    async submitBulkCancel() {
      if (!this.canCancelSelected || this.bulkLoading) return;
      this.bulkLoading = true;
      try {
        const res = await this.api(OrderController.bulkCancel(), {
          body: JSON.stringify({
            order_ids: this.selectedOrderIds,
          }),
        });
        const count = res?.count || 0;
        this.hapticSuccess?.();
        this.$dispatch('notify', {
          message: count === 1 ? 'Заказ отменён' : `Отменено заказов: ${count}`,
          type: 'success',
        });
        this.clearSelection();
        await this.loadOrders(true);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка отмены заказов', type: 'error' });
      } finally {
        this.bulkLoading = false;
      }
    },
  });
}
