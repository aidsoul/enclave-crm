import { baseInit, withTmaBase } from '../shared.js';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
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
    filter: 'accepted',
    orderSearch: '',
    orderSearchTimeout: null,
    statsTotal: 0,
    statsAccepted: 0,
    statsAssigned: 0,
    statsCompleted: 0,
    statsCancelled: 0,
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

    get ordersHasMore() {
      return this.ordersPage < this.ordersLastPage;
    },

    get defaultFilter() {
      if (this.user?.role === 'courier') {
        return 'assigned';
      }
      return 'created';
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;
      this.filter = this.defaultFilter;
      await Promise.all([
        this.loadOrders(true),
        this.loadStats(),
      ]);
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
        };
        if (this.filter) {
          query.status = this.filter;
        }
        if (this.orderSearch) {
          query.search = this.orderSearch;
        }
        if (this.user?.role !== 'owner' && this.user?.id) {
          query.manager_id = this.user.id;
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

    onOrderSearch() {
      clearTimeout(this.orderSearchTimeout);
      this.orderSearchTimeout = setTimeout(() => {
        this.clearSelection();
        this.loadOrders(true);
      }, 400);
    },

    async loadStats() {
      try {
        const baseQuery = { page: 1, per_page: 1 };
        if (this.user?.role !== 'owner' && this.user?.id) {
          baseQuery.manager_id = this.user.id;
        }

        const [allRes, acceptedRes, assignedRes, completedRes, cancelledRes] = await Promise.all([
          this.api(OrderController.index({ query: baseQuery })),
          this.api(OrderController.index({ query: { ...baseQuery, status: 'accepted' } })),
          this.api(OrderController.index({ query: { ...baseQuery, status: 'assigned' } })),
          this.api(OrderController.index({ query: { ...baseQuery, status: 'completed' } })),
          this.api(OrderController.index({ query: { ...baseQuery, status: 'cancelled' } })),
        ]);

        this.statsTotal = allRes?.total ?? 0;
        this.statsAccepted = acceptedRes?.total ?? 0;
        this.statsAssigned = assignedRes?.total ?? 0;
        this.statsCompleted = completedRes?.total ?? 0;
        this.statsCancelled = cancelledRes?.total ?? 0;
      } catch (e) {
        console.error('Failed to load stats:', e);
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
      this.loadStats?.();
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
        const query = { role: 'manager', limit: 5 };
        if (search) query.search = search;
        const res = await this.api(PersonnelController.search({ query }));
        this.managers = (res?.personnel || res?.managers || []).slice(0, 5);
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
        await Promise.all([
          this.loadOrders(true),
          this.loadStats?.(),
        ]);
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
        await Promise.all([
          this.loadOrders(true),
          this.loadStats?.(),
        ]);
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
        await Promise.all([
          this.loadOrders(true),
          this.loadStats?.(),
        ]);
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
        await Promise.all([
          this.loadOrders(true),
          this.loadStats?.(),
        ]);
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
        await Promise.all([
          this.loadOrders(true),
          this.loadStats?.(),
        ]);
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка отмены заказов', type: 'error' });
      } finally {
        this.bulkLoading = false;
      }
    },
  });
}
