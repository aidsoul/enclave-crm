import { baseInit, withTmaBase } from '../shared.js';
import { validateOrderForm, hasErrors, extractServerErrors, clearError } from '../validation.js';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import PersonnelController from '@/actions/App/Http/Controllers/PersonnelController';
import orders from '@/routes/tma/orders';
import tma from '@/routes/tma';

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    order: null,
    form: {
      site_id: '',
      delivery_courier_id: '',
      manager_id: '',
      customer_id: '',
      client_name: '',
      client_phone: '',
      client_email: '',
      client_address: '',
      delivery_address: '',
      delivery_price: '',
      is_valuable: false,
      order_items: [],
      total_price: '',
      purchase_price: '',
    },
    sites: [],
    couriers: [],
    managers: [],
    customers: [],
    selectedCustomer: null,
    customerSearch: '',
    customerDropdownOpen: false,
    customersLoading: false,
    _customerDropdownSearchTimer: null,
    _customerFetchId: 0,
    foundCustomer: null,
    managerSearch: '',
    courierSearch: '',
    siteSearch: '',
    managerDropdownOpen: false,
    courierDropdownOpen: false,
    siteDropdownOpen: false,
    quickProductDropdownOpen: false,
    quickProductSearch: '',
    quickProducts: [],
    quickProductsLoading: false,
    _quickProductSearchTimer: null,
    _quickProductFetchId: 0,
    activeItemProductDropdown: null,
    itemProductSearch: '',
    itemProducts: [],
    itemProductsLoading: false,
    _itemProductSearchTimer: null,
    _itemProductFetchId: 0,
    showProductModal: false,
    catalogSearch: '',
    catalogProducts: [],
    catalogLoading: false,
    catalogSelected: {},
    _catalogSearchTimer: null,
    _catalogFetchId: 0,
    _managerSearchTimer: null,
    _courierSearchTimer: null,
    _customerSearchTimer: null,
    submitting: false,
    errors: {},

    get currentSiteLabel() {
      if (!this.form.site_id) return '';
      const site = (this.sites || []).find((s) => s.id == this.form.site_id);
      return site?.domain || site?.name || '';
    },

    get selectedCustomerLabel() {
      if (this.selectedCustomer) {
        const phone = this.selectedCustomer.formatted_phone || this.selectedCustomer.phone;
        return this.selectedCustomer.name + (phone ? ` (${phone})` : '');
      }
      if (this.form.customer_id) {
        const found = (this.customers || []).find((c) => c.id == this.form.customer_id);
        if (found) {
          const phone = found.formatted_phone || found.phone;
          return found.name + (phone ? ` (${phone})` : '');
        }
        return `Клиент #${this.form.customer_id}`;
      }
      return 'Выберите клиента из базы...';
    },

    get catalogSelectedCount() {
      return Object.keys(this.catalogSelected || {}).length;
    },

    get catalogSelectedTotalQty() {
      return Object.values(this.catalogSelected || {}).reduce((acc, item) => acc + (parseInt(item.qty, 10) || 1), 0);
    },

    get orderId() {
      const match = window.location.pathname.match(/^\/tma\/orders\/(\d+)\/edit$/);
      return match ? parseInt(match[1], 10) : null;
    },

    inputClass(field, extra = '') {
      const border = this.errors?.[field] ? 'border-error' : 'border-border focus:border-primary';
      return `w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm focus:outline-none ${border} ${extra}`;
    },

    clearFieldError(field) {
      clearError(this.errors, field);
    },

    get filteredSites() {
      if (!this.siteSearch) return this.sites || [];
      const q = this.siteSearch.toLowerCase().trim();
      return (this.sites || []).filter(s =>
        (s.domain && s.domain.toLowerCase().includes(q)) ||
        (s.name && s.name.toLowerCase().includes(q))
      );
    },

    closeAllDropdowns(except = null) {
      const list = ['siteDropdownOpen', 'courierDropdownOpen', 'managerDropdownOpen', 'customerDropdownOpen', 'quickProductDropdownOpen'];
      list.forEach(k => {
        if (k !== except) {
          this[k] = false;
        }
      });
      if (except !== 'activeItemProductDropdown') {
        this.activeItemProductDropdown = null;
      }
    },

    openSiteDropdown() {
      if (!this.siteDropdownOpen) {
        this.closeAllDropdowns('siteDropdownOpen');
        this.siteSearch = '';
      }
      this.siteDropdownOpen = !this.siteDropdownOpen;
    },

    searchManagers() {
      clearTimeout(this._managerSearchTimer);
      this._managerSearchTimer = setTimeout(() => {
        this.loadManagers(this.managerSearch || '');
      }, 300);
    },

    searchCouriers() {
      clearTimeout(this._courierSearchTimer);
      this._courierSearchTimer = setTimeout(() => {
        this.loadCouriers(this.courierSearch || '');
      }, 300);
    },

    openManagerDropdown() {
      if (!this.managerDropdownOpen) {
        this.closeAllDropdowns('managerDropdownOpen');
        this.managerSearch = '';
        this.loadManagers('');
      }
      this.managerDropdownOpen = !this.managerDropdownOpen;
    },

    openCourierDropdown() {
      if (!this.form.manager_id && !this.order?.manager_id) {
        return;
      }
      if (!this.courierDropdownOpen) {
        this.closeAllDropdowns('courierDropdownOpen');
        this.courierSearch = '';
        this.loadCouriers('');
      }
      this.courierDropdownOpen = !this.courierDropdownOpen;
    },

    openCustomerDropdown() {
      if (!this.customerDropdownOpen) {
        this.closeAllDropdowns('customerDropdownOpen');
        this.customerSearch = '';
        this.loadCustomers('');
      }
      this.customerDropdownOpen = !this.customerDropdownOpen;
    },

    searchCustomers() {
      clearTimeout(this._customerDropdownSearchTimer);
      this._customerDropdownSearchTimer = setTimeout(() => {
        this.loadCustomers(this.customerSearch || '');
      }, 300);
    },

    async loadCustomers(search = '') {
      this.customersLoading = true;
      const fetchId = ++this._customerFetchId;
      try {
        const query = { per_page: 5 };
        if (search) {
          query.search = search;
        }
        if (this.form.site_id) {
          query.site_id = this.form.site_id;
        }
        const res = await this.api(CustomerController.index({ query }));
        if (fetchId !== this._customerFetchId) return;
        this.customers = (res?.customers || []).slice(0, 5);
      } catch (e) {
        if (fetchId !== this._customerFetchId) return;
        console.error('Failed to load customers:', e);
        this.customers = [];
      } finally {
        if (fetchId === this._customerFetchId) {
          this.customersLoading = false;
        }
      }
    },

    selectCustomer(customer) {
      if (!customer) {
        this.selectedCustomer = null;
        this.form.customer_id = '';
        this.foundCustomer = null;
        this.customerDropdownOpen = false;
        this.customerSearch = '';
        this.syncClosingConfirmation();
        return;
      }

      this.selectedCustomer = customer;
      this.form.customer_id = customer.id;
      this.foundCustomer = customer;

      if (customer.name) {
        this.form.client_name = customer.name;
        this.clearFieldError('client_name');
      }
      if (customer.formatted_phone || customer.phone) {
        this.form.client_phone = customer.formatted_phone || customer.phone;
        this.clearFieldError('client_phone');
      }
      if (customer.email) {
        this.form.client_email = customer.email;
        this.clearFieldError('client_email');
      }
      if (customer.address) {
        this.form.client_address = customer.address;
        this.clearFieldError('client_address');
        if (!this.form.delivery_address) {
          this.form.delivery_address = customer.address;
        }
      }
      if (!this.form.site_id && customer.site_id) {
        this.form.site_id = customer.site_id;
        this.clearFieldError('site_id');
      }

      this.customerDropdownOpen = false;
      this.customerSearch = '';
      this.syncClosingConfirmation();
    },

    openQuickProductDropdown() {
      if (!this.quickProductDropdownOpen) {
        this.closeAllDropdowns('quickProductDropdownOpen');
        this.quickProductSearch = '';
        this.loadQuickProducts('');
      }
      this.quickProductDropdownOpen = !this.quickProductDropdownOpen;
    },

    searchQuickProducts() {
      clearTimeout(this._quickProductSearchTimer);
      this._quickProductSearchTimer = setTimeout(() => {
        this.loadQuickProducts(this.quickProductSearch || '');
      }, 300);
    },

    async loadQuickProducts(search = '') {
      this.quickProductsLoading = true;
      const fetchId = ++this._quickProductFetchId;
      try {
        const query = { is_active: 'true', limit: 5 };
        if (search) {
          query.search = search;
        }
        if (this.form.site_id) {
          query.site_id = this.form.site_id;
        }
        const res = await this.api(ProductController.index({ query }));
        if (fetchId !== this._quickProductFetchId) return;
        this.quickProducts = (res?.products || []).slice(0, 5);
      } catch (e) {
        if (fetchId !== this._quickProductFetchId) return;
        console.error('Failed to load products:', e);
        this.quickProducts = [];
      } finally {
        if (fetchId === this._quickProductFetchId) {
          this.quickProductsLoading = false;
        }
      }
    },

    openItemProductDropdown(index) {
      if (this.activeItemProductDropdown === index) {
        this.activeItemProductDropdown = null;
        return;
      }
      this.closeAllDropdowns('activeItemProductDropdown');
      this.activeItemProductDropdown = index;
      this.itemProductSearch = this.form.order_items[index]?.name || '';
      this.loadItemProducts(this.itemProductSearch);
    },

    searchItemProducts() {
      clearTimeout(this._itemProductSearchTimer);
      this._itemProductSearchTimer = setTimeout(() => {
        this.loadItemProducts(this.itemProductSearch || '');
      }, 300);
    },

    async loadItemProducts(search = '') {
      this.itemProductsLoading = true;
      const fetchId = ++this._itemProductFetchId;
      try {
        const query = { is_active: 'true', limit: 5 };
        if (search) {
          query.search = search;
        }
        if (this.form.site_id) {
          query.site_id = this.form.site_id;
        }
        const res = await this.api(ProductController.index({ query }));
        if (fetchId !== this._itemProductFetchId) return;
        this.itemProducts = (res?.products || []).slice(0, 5);
      } catch (e) {
        if (fetchId !== this._itemProductFetchId) return;
        console.error('Failed to load products:', e);
        this.itemProducts = [];
      } finally {
        if (fetchId === this._itemProductFetchId) {
          this.itemProductsLoading = false;
        }
      }
    },

    addProductToItems(product, variant = null, targetItemIndex = null) {
      if (!this.form.site_id && product.site_id) {
        this.form.site_id = product.site_id;
        this.clearFieldError('site_id');
      }

      const price = variant && variant.price !== null && variant.price !== undefined
        ? parseFloat(variant.price)
        : (product.price !== null && product.price !== undefined ? parseFloat(product.price) : 0);

      const hasCustomCost = variant
        ? (variant.purchase_price !== null && variant.purchase_price !== undefined && variant.purchase_price !== '')
        : (product.cost_price !== null && product.cost_price !== undefined && product.cost_price !== '');

      const customCost = variant ? variant.purchase_price : product.cost_price;
      const percent = this.getSitePurchasePercent();
      const cost = hasCustomCost ? customCost : (price > 0 ? Number((price * (percent / 100)).toFixed(2)) : '');

      const newItem = {
        product_id: product.id,
        product_variant_id: variant ? variant.id : null,
        variant_title: variant ? (variant.title || '') : '',
        sku: (variant && variant.sku) || product.sku || '',
        name: variant ? `${product.name} (${variant.title})` : product.name,
        qty: 1,
        price: price !== null && price !== undefined ? price : '',
        purchase_price: cost !== null && cost !== undefined ? cost : '',
        _customPurchasePrice: hasCustomCost,
      };

      if (targetItemIndex !== null && this.form.order_items[targetItemIndex]) {
        newItem.qty = this.form.order_items[targetItemIndex].qty || 1;
        this.form.order_items[targetItemIndex] = newItem;
        this.clearFieldError(`order_items.${targetItemIndex}.name`);
        this.clearFieldError(`order_items.${targetItemIndex}.qty`);
        this.clearFieldError(`order_items.${targetItemIndex}.price`);
        this.clearFieldError(`order_items.${targetItemIndex}.purchase_price`);
      } else {
        if (this.form.order_items.length === 1 && !this.form.order_items[0].name && !this.form.order_items[0].price) {
          this.form.order_items = [newItem];
        } else {
          const existing = (this.form.order_items || []).find((i) =>
            (newItem.product_variant_id && i.product_variant_id === newItem.product_variant_id) ||
            (!newItem.product_variant_id && !i.product_variant_id && newItem.product_id && i.product_id === newItem.product_id)
          );
          if (existing) {
            existing.qty = (parseInt(existing.qty, 10) || 0) + 1;
          } else {
            this.form.order_items.push(newItem);
          }
        }
      }

      this.recalculatePrices();
      this.syncClosingConfirmation();
      this.quickProductDropdownOpen = false;
      this.quickProductSearch = '';
      this.activeItemProductDropdown = null;
      this.itemProductSearch = '';
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;
      if (this.orderId) {
        await Promise.all([
          this.loadSites(),
          this.loadOrder(),
        ]);
        this.enableClosingConfirmation('order-edit');
      } else {
        await this.loadSites();
        this.$watch('form.order_items', () => {
          this.recalculatePrices();
        }, { deep: true });
      }

      this.$watch('form', () => {
        this.syncClosingConfirmation();
      }, { deep: true });

      this.$watch('form.client_phone', (val) => {
        this.checkCustomerByPhone(val);
      });
      this.$watch('form.site_id', () => {
        if (this.form.client_phone) {
          this.checkCustomerByPhone(this.form.client_phone);
        }
      });
    },

    goBack() {
      if (this.orderId) {
        if (window.history.length > 1) {
          window.history.back();
        } else {
          window.location.href = orders.show.url(this.orderId);
        }
      } else {
        if (window.history.length > 1) {
          window.history.back();
        } else {
          window.location.href = this.user?.role === 'manager' ? tma.manager.url() : orders.index.url();
        }
      }
    },

    isFormDirty() {
      if (this.orderId) return true;
      const f = this.form;
      if (!f) return false;
      if (
        (f.site_id && f.site_id !== '') ||
        (f.customer_id && f.customer_id !== '') ||
        (f.client_name && String(f.client_name).trim() !== '') ||
        (f.client_phone && String(f.client_phone).trim() !== '') ||
        (f.client_email && String(f.client_email).trim() !== '') ||
        (f.client_address && String(f.client_address).trim() !== '') ||
        (f.delivery_address && String(f.delivery_address).trim() !== '') ||
        (f.delivery_courier_id && f.delivery_courier_id !== '') ||
        (f.manager_id && f.manager_id !== '') ||
        Boolean(f.is_valuable)
      ) {
        return true;
      }
      if (Array.isArray(f.order_items) && f.order_items.some((item) => (item.name && String(item.name).trim() !== '') || item.price)) {
        return true;
      }
      return false;
    },

    syncClosingConfirmation() {
      if (this.isFormDirty()) {
        this.enableClosingConfirmation('order-form');
      } else {
        this.disableClosingConfirmation('order-form');
      }
    },

    formatPhoneInput(e) {
      const val = e.target.value;
      if (e.inputType && e.inputType.startsWith("delete")) {
        this.form.client_phone = val;
        this.clearFieldError("client_phone");
        this.checkCustomerByPhone(val);
        return;
      }
      let digits = val.replace(/\D/g, "");
      if (!digits) {
        this.form.client_phone = "";
        this.clearFieldError("client_phone");
        this.checkCustomerByPhone("");
        return;
      }
      if (digits.startsWith("80") && digits.length >= 4) {
        digits = "375" + digits.slice(2);
      } else if (digits.startsWith("89") || (digits.startsWith("8") && digits.length === 11)) {
        digits = "7" + digits.slice(1);
      }
      let formatted = "";
      if (digits.startsWith("375")) {
        formatted = "+375";
        if (digits.length > 3) formatted += " (" + digits.slice(3, Math.min(5, digits.length));
        if (digits.length >= 5) formatted += ") ";
        if (digits.length > 5) formatted += digits.slice(5, Math.min(8, digits.length));
        if (digits.length >= 8) formatted += "-" + digits.slice(8, Math.min(10, digits.length));
        if (digits.length >= 10) formatted += "-" + digits.slice(10, Math.min(12, digits.length));
      } else if (digits.startsWith("7")) {
        formatted = "+7";
        if (digits.length > 1) formatted += " (" + digits.slice(1, Math.min(4, digits.length));
        if (digits.length >= 4) formatted += ") ";
        if (digits.length > 4) formatted += digits.slice(4, Math.min(7, digits.length));
        if (digits.length >= 7) formatted += "-" + digits.slice(7, Math.min(9, digits.length));
        if (digits.length >= 9) formatted += "-" + digits.slice(9, Math.min(11, digits.length));
      } else {
        formatted = "+" + digits;
      }
      this.form.client_phone = formatted;
      this.clearFieldError("client_phone");
      this.checkCustomerByPhone(formatted);
    },

    checkCustomerByPhone(phone) {
      clearTimeout(this._customerSearchTimer);
      const digits = (phone || '').replace(/\D/g, '');
      if (digits.length < 6) {
        this.foundCustomer = null;
        return;
      }
      this._customerSearchTimer = setTimeout(async () => {
        try {
          const query = { phone };
          if (this.form.site_id) {
            query.site_id = this.form.site_id;
          }
          const res = await this.api(CustomerController.lookup({ query }));
          this.foundCustomer = res?.customer || null;
        } catch (_) {
          this.foundCustomer = null;
        }
      }, 350);
    },

    applyFoundCustomer() {
      if (!this.foundCustomer) return;
      if (!this.form.client_name && this.foundCustomer.name) {
        this.form.client_name = this.foundCustomer.name;
      }
      if (!this.form.client_email && this.foundCustomer.email) {
        this.form.client_email = this.foundCustomer.email;
      }
      if (!this.form.client_address && this.foundCustomer.address) {
        this.form.client_address = this.foundCustomer.address;
      }
      if (!this.form.delivery_address && this.foundCustomer.address) {
        this.form.delivery_address = this.foundCustomer.address;
      }
      if (!this.form.customer_id && this.foundCustomer.id) {
        this.form.customer_id = this.foundCustomer.id;
        this.selectedCustomer = this.foundCustomer;
      }
    },

    async pasteToField(fieldName) {
      try {
        const text = await this.readClipboardText();
        if (!text || !text.trim()) {
          window.notify?.('Буфер обмена пуст или доступ запрещён', 'warn');
          return;
        }

        let val = text.trim();
        if (fieldName === 'client_phone') {
          const cleanedPhone = val.replace(/[^\d+]/g, '');
          if (cleanedPhone.length >= 5) {
            val = cleanedPhone;
          }
        }

        this.form[fieldName] = val;
        this.clearFieldError(fieldName);
        this.syncClosingConfirmation?.();
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
        window.notify?.('Вставлено из буфера', 'success');

        if (fieldName === 'client_phone') {
          this.checkCustomerByPhone?.(val);
        }
      } catch (err) {
        window.notify?.('Не удалось прочитать буфер', 'error');
      }
    },

    async loadSites() {
      try {
        const res = await this.api(SiteController.index());
        this.sites = res?.sites || [];
      } catch (e) {
        console.error('Failed to load sites:', e);
      }
    },

    async loadCouriers(search = '') {
      try {
        const query = { role: 'courier', limit: 10 };
        if (search) query.search = search;
        const res = await this.api(PersonnelController.search({ query }));
        this.couriers = res?.couriers || [];
      } catch (e) {
        console.error('Failed to load couriers:', e);
      }
    },

    async loadManagers(search = '') {
      try {
        const query = { role: 'manager', limit: 10 };
        if (search) query.search = search;
        const res = await this.api(PersonnelController.search({ query }));
        this.managers = res?.managers || [];
      } catch (e) {
        console.error('Failed to load managers:', e);
      }
    },

    searchManagers() {
      clearTimeout(this._managerSearchTimer);
      this._managerSearchTimer = setTimeout(() => {
        this.loadManagers(this.managerSearch || '');
      }, 300);
    },

    searchCouriers() {
      clearTimeout(this._courierSearchTimer);
      this._courierSearchTimer = setTimeout(() => {
        this.loadCouriers(this.courierSearch || '');
      }, 300);
    },

    openManagerDropdown() {
      if (!this.managerDropdownOpen) {
        this.managerSearch = '';
        this.loadManagers('');
      }
      this.managerDropdownOpen = !this.managerDropdownOpen;
    },

    openCourierDropdown() {
      if (!this.form.manager_id && !this.order?.manager_id) {
        return;
      }
      if (!this.courierDropdownOpen) {
        this.courierSearch = '';
        this.loadCouriers('');
      }
      this.courierDropdownOpen = !this.courierDropdownOpen;
    },

    async loadOrder() {
      try {
        const res = await this.api(OrderController.show(this.orderId));
        const order = res?.order ?? res;
        this.order = order;
        this.courierSearch = '';
        this.managerSearch = '';
        this.managerDropdownOpen = false;
        this.courierDropdownOpen = false;
        const delivery = order.delivery?.[0] || order.delivery;
        this.form = {
          site_id: order.site_id || '',
          delivery_courier_id: delivery?.courier?.id || '',
          manager_id: order.manager_id || '',
          client_name: order.customer?.name || '',
          client_phone: order.customer?.formatted_phone || this.formatPhone(order.customer?.phone) || order.customer?.phone || '',
          client_email: order.customer?.email || '',
          client_address: order.customer?.address || '',
          delivery_address: delivery?.delivery_address || order.customer?.address || '',
          delivery_price: delivery?.delivery_price !== undefined && delivery?.delivery_price !== null ? String(delivery.delivery_price) : '',
          is_valuable: Boolean(order.is_valuable),
          order_items: (order.items || []).map((item) => ({
            id: item.id,
            product_id: item.product_id || null,
            product_variant_id: item.product_variant_id || null,
            variant_title: item.variant_title || '',
            sku: item.sku || '',
            name: item.name || '',
            qty: item.qty || 1,
            price: item.price ?? '',
            purchase_price: item.purchase_price ?? '',
            _customPurchasePrice: item.purchase_price !== null && item.purchase_price !== undefined && item.purchase_price !== '',
          })),
          total_price: order.total_price || '',
          purchase_price: order.purchase_price || '',
        };
      } catch (e) {
        console.error('Failed to load order:', e);
      }
    },

    getSitePurchasePercent() {
      if (this.form.site_id && this.sites) {
        const site = this.sites.find((s) => s.id == this.form.site_id);
        if (site && site.default_purchase_percent !== null && site.default_purchase_percent !== undefined && site.default_purchase_percent !== '') {
          const val = parseFloat(site.default_purchase_percent);
          if (!isNaN(val)) return val;
        }
      }
      return window.__DEFAULT_PURCHASE_PERCENT__ !== undefined ? Number(window.__DEFAULT_PURCHASE_PERCENT__) : 15;
    },

    selectSite(siteId) {
      const oldSiteId = this.form.site_id;
      this.form.site_id = siteId;
      this.siteDropdownOpen = false;
      this.clearFieldError('site_id');
      this.recalculateAllItemPurchasePrices();
      this.catalogProducts = [];
      this.catalogSelected = {};

      if (oldSiteId && String(oldSiteId) !== String(siteId) && Array.isArray(this.form.order_items)) {
        const hasCatalogItems = this.form.order_items.some((i) => i.product_id || i.product_variant_id);
        if (hasCatalogItems) {
          window.notify?.('При смене сайта проверьте товары из каталога', 'warn');
        }
      }
    },

    recalculateAllItemPurchasePrices() {
      const percent = this.getSitePurchasePercent();
      for (const item of this.form.order_items) {
        if (!item._customPurchasePrice) {
          const price = parseFloat(item.price) || 0;
          item.purchase_price = price > 0 ? Number((price * (percent / 100)).toFixed(2)) : '';
        }
      }
      this.recalculatePrices();
    },

    onItemPriceChange(item) {
      const percent = this.getSitePurchasePercent();
      const price = parseFloat(item.price) || 0;
      if (!item._customPurchasePrice) {
        item.purchase_price = price > 0 ? Number((price * (percent / 100)).toFixed(2)) : '';
      }
      this.recalculatePrices();
    },

    addItem() {
      this.form.order_items.push({
        product_id: null,
        product_variant_id: null,
        variant_title: '',
        sku: '',
        name: '',
        qty: 1,
        price: '',
        purchase_price: '',
        _customPurchasePrice: false,
      });
      this.recalculatePrices();
    },

    openProductSearchModal() {
      this.closeAllDropdowns();
      this.catalogSearch = '';
      this.showProductModal = true;
      if (this.form.site_id) {
        this.fetchCatalogProducts();
      } else {
        this.catalogProducts = [];
      }
      this.$nextTick?.(() => {
        const input = document.getElementById('catalog-search-input');
        input?.focus?.();
      });
    },

    closeProductModal() {
      this.showProductModal = false;
    },

    searchCatalog() {
      clearTimeout(this._catalogSearchTimer);
      this._catalogSearchTimer = setTimeout(() => {
        this.fetchCatalogProducts();
      }, 300);
    },

    async fetchCatalogProducts() {
      if (!this.form.site_id) {
        this.catalogProducts = [];
        return;
      }
      const fetchId = ++this._catalogFetchId;
      this.catalogLoading = true;
      try {
        const query = {
          site_id: this.form.site_id,
          is_active: 'true',
          per_page: 50,
        };
        if (this.catalogSearch && this.catalogSearch.trim()) {
          query.search = this.catalogSearch.trim();
        }
        const res = await this.api(ProductController.index({ query }));
        if (fetchId !== this._catalogFetchId) return;
        this.catalogProducts = res?.products || [];
      } catch (e) {
        if (fetchId === this._catalogFetchId) {
          console.error('Failed to load catalog products:', e);
          this.catalogProducts = [];
        }
      } finally {
        if (fetchId === this._catalogFetchId) {
          this.catalogLoading = false;
        }
      }
    },

    isCatalogItemSelected(key) {
      return Boolean(this.catalogSelected?.[key]);
    },

    getCatalogItemQty(key) {
      return this.catalogSelected?.[key]?.qty || 1;
    },

    toggleCatalogProduct(product) {
      const key = `p_${product.id}`;
      const next = { ...this.catalogSelected };
      if (next[key]) {
        delete next[key];
      } else {
        next[key] = {
          key,
          product_id: product.id,
          product_variant_id: null,
          name: product.name,
          variant_title: '',
          sku: product.sku || '',
          price: product.price ?? 0,
          purchase_price: product.cost_price ?? null,
          qty: 1,
        };
      }
      this.catalogSelected = next;
    },

    toggleCatalogVariant(product, variant) {
      const key = `v_${variant.id}`;
      const next = { ...this.catalogSelected };
      if (next[key]) {
        delete next[key];
      } else {
        next[key] = {
          key,
          product_id: product.id,
          product_variant_id: variant.id,
          name: product.name,
          variant_title: variant.title || '',
          sku: variant.sku || product.sku || '',
          price: variant.price !== null && variant.price !== undefined ? variant.price : (product.price ?? 0),
          purchase_price: variant.purchase_price !== null && variant.purchase_price !== undefined ? variant.purchase_price : (product.cost_price ?? null),
          qty: 1,
        };
      }
      this.catalogSelected = next;
    },

    isAllProductVariantsSelected(product) {
      if (!product.variants || product.variants.length === 0) return false;
      return product.variants.every((v) => this.isCatalogItemSelected(`v_${v.id}`));
    },

    toggleAllProductVariants(product) {
      if (!product.variants || product.variants.length === 0) return;
      const allSelected = this.isAllProductVariantsSelected(product);
      const next = { ...this.catalogSelected };
      for (const variant of product.variants) {
        const key = `v_${variant.id}`;
        if (allSelected) {
          delete next[key];
        } else {
          next[key] = {
            key,
            product_id: product.id,
            product_variant_id: variant.id,
            name: product.name,
            variant_title: variant.title || '',
            sku: variant.sku || product.sku || '',
            price: variant.price !== null && variant.price !== undefined ? variant.price : (product.price ?? 0),
            purchase_price: variant.purchase_price !== null && variant.purchase_price !== undefined ? variant.purchase_price : (product.cost_price ?? null),
            qty: next[key]?.qty || 1,
          };
        }
      }
      this.catalogSelected = next;
    },

    changeCatalogItemQty(key, delta) {
      if (!this.catalogSelected?.[key]) return;
      const current = parseInt(this.catalogSelected[key].qty, 10) || 1;
      const nextQty = Math.max(1, Math.min(10000, current + delta));
      this.catalogSelected = {
        ...this.catalogSelected,
        [key]: {
          ...this.catalogSelected[key],
          qty: nextQty,
        },
      };
    },

    setCatalogItemQty(key, value) {
      if (!this.catalogSelected?.[key]) return;
      if (value === '' || value === null || value === undefined) {
        this.catalogSelected[key].qty = '';
      } else {
        const parsed = parseInt(value, 10);
        this.catalogSelected[key].qty = isNaN(parsed) || parsed < 1 ? 1 : Math.min(10000, parsed);
      }
      this.catalogSelected = { ...this.catalogSelected };
    },

    onCatalogItemQtyBlur(key) {
      if (!this.catalogSelected?.[key]) return;
      const parsed = parseInt(this.catalogSelected[key].qty, 10);
      this.catalogSelected[key].qty = isNaN(parsed) || parsed < 1 ? 1 : Math.min(10000, parsed);
      this.catalogSelected = { ...this.catalogSelected };
    },

    selectAllCatalogItems() {
      const next = { ...this.catalogSelected };
      for (const product of this.catalogProducts || []) {
        if (product.variants && product.variants.length > 0) {
          for (const variant of product.variants) {
            const key = `v_${variant.id}`;
            next[key] = {
              key,
              product_id: product.id,
              product_variant_id: variant.id,
              name: product.name,
              variant_title: variant.title || '',
              sku: variant.sku || product.sku || '',
              price: variant.price !== null && variant.price !== undefined ? variant.price : (product.price ?? 0),
              purchase_price: variant.purchase_price !== null && variant.purchase_price !== undefined ? variant.purchase_price : (product.cost_price ?? null),
              qty: next[key]?.qty || 1,
            };
          }
        } else {
          const key = `p_${product.id}`;
          next[key] = {
            key,
            product_id: product.id,
            product_variant_id: null,
            name: product.name,
            variant_title: '',
            sku: product.sku || '',
            price: product.price ?? 0,
            purchase_price: product.cost_price ?? null,
            qty: next[key]?.qty || 1,
          };
        }
      }
      this.catalogSelected = next;
    },

    clearCatalogSelection() {
      this.catalogSelected = {};
    },

    addSelectedCatalogItems() {
      const selectedList = Object.values(this.catalogSelected || {});
      if (selectedList.length === 0) return;

      const currentItems = (this.form.order_items || []).filter(
        (i) => (i.name && String(i.name).trim() !== '') || i.price
      );

      const percent = this.getSitePurchasePercent();

      for (const item of selectedList) {
        const existing = currentItems.find((i) =>
          (item.product_variant_id && i.product_variant_id === item.product_variant_id) ||
          (!item.product_variant_id && !i.product_variant_id && item.product_id && i.product_id === item.product_id)
        );

        if (existing) {
          existing.qty = (parseInt(existing.qty, 10) || 0) + (parseInt(item.qty, 10) || 1);
        } else {
          const hasCustomCost = item.purchase_price !== null && item.purchase_price !== undefined && item.purchase_price !== '';
          const price = parseFloat(item.price) || 0;
          let cost = hasCustomCost ? item.purchase_price : (price > 0 ? Number((price * (percent / 100)).toFixed(2)) : '');

          currentItems.push({
            product_id: item.product_id,
            product_variant_id: item.product_variant_id || null,
            variant_title: item.variant_title || '',
            sku: item.sku || '',
            name: item.name || '',
            qty: parseInt(item.qty, 10) || 1,
            price: item.price !== null && item.price !== undefined ? item.price : '',
            purchase_price: cost !== null && cost !== undefined ? cost : '',
            _customPurchasePrice: hasCustomCost,
          });
        }
      }

      this.form.order_items = currentItems;
      this.recalculatePrices();
      this.syncClosingConfirmation();
      const count = selectedList.length;
      window.notify?.(`Добавлено позиций: ${count}`, 'success');
      this.catalogSelected = {};
      this.closeProductModal();
    },

    removeItem(index) {
      this.form.order_items.splice(index, 1);
      this.recalculatePrices();
    },

    getItemsTotal() {
      let total = 0;
      for (const item of this.form.order_items) {
        const price = parseFloat(item.price) || 0;
        const qty = parseInt(item.qty, 10) || 0;
        total += price * qty;
      }
      return Number(total.toFixed(2));
    },

    onDeliveryPriceInput(event) {
      this.form.delivery_price = event?.target?.value ?? this.form.delivery_price;
      this.recalculatePrices();
      this.clearFieldError('delivery_price');
      this.syncClosingConfirmation();
    },

    recalculatePrices() {
      let total = 0;
      let purchase = 0;
      const defaultPercent = this.getSitePurchasePercent();
      for (const item of this.form.order_items) {
        const price = parseFloat(item.price) || 0;
        const qty = parseInt(item.qty, 10) || 0;
        total += price * qty;

        let itemPurchase = parseFloat(item.purchase_price);
        if (isNaN(itemPurchase) || itemPurchase === null || item.purchase_price === '') {
          itemPurchase = price * (defaultPercent / 100);
        }
        purchase += itemPurchase * qty;
      }
      const deliveryPrice = parseFloat(this.form.delivery_price) || 0;
      this.form.total_price = Number((total + deliveryPrice).toFixed(2));
      this.form.purchase_price = Number(purchase.toFixed(2));
    },

    buildPayload() {
      if (!this.order) {
        return {
          site_id: this.form.site_id,
          customer_id: this.form.customer_id ? parseInt(this.form.customer_id, 10) : null,
          client_name: String(this.form.client_name ?? '').trim(),
          client_phone: String(this.form.client_phone ?? '').trim(),
          client_email: String(this.form.client_email ?? '').trim(),
          client_address: String(this.form.client_address ?? '').trim(),
          order_items: this.form.order_items
            .filter((item) => (item.name && item.name.trim() !== '') || item.qty || item.price)
            .map((item) => ({
              product_id: item.product_id || null,
              product_variant_id: item.product_variant_id || null,
              variant_title: item.variant_title ? String(item.variant_title).trim() : null,
              sku: item.sku ? String(item.sku).trim() : null,
              name: item.name && item.name !== null ? String(item.name).trim() : '',
              qty: item.qty && item.qty !== null ? (isNaN(item.qty) ? 1 : parseInt(item.qty, 10)) : 1,
              price: item.price === '' || item.price === null ? null : parseFloat(Number(item.price).toFixed(2)),
              purchase_price: item.purchase_price === '' || item.purchase_price === null ? null : parseFloat(Number(item.purchase_price).toFixed(2)),
            })),
          total_price: this.form.total_price === '' || this.form.total_price === null ? null : parseFloat(Number(this.form.total_price).toFixed(2)),
          purchase_price: this.form.purchase_price === '' || this.form.purchase_price === null ? null : parseFloat(Number(this.form.purchase_price).toFixed(2)),
          delivery_price: this.form.delivery_price === '' || this.form.delivery_price === null ? 0 : parseFloat(Number(this.form.delivery_price).toFixed(2)),
          delivery_courier_id: this.form.delivery_courier_id || null,
          manager_id: this.form.manager_id || null,
          is_valuable: Boolean(this.form.is_valuable),
        };
      }

      const current = this.order || {};
      const payload = {};

      const eq = (a, b) => {
        if (a === undefined || a === null || a === '') a = null;
        if (b === undefined || b === null || b === '') b = null;
        return a === b;
      };

      const normalizePrice = (value) => {
        if (value === '' || value === null || value === undefined) return null;
        const str = String(value).replace(',', '.').trim();
        const num = parseFloat(str);
        return isNaN(num) ? null : num;
      };

      if (!eq(this.form.site_id, current.site_id)) {
        payload.site_id = this.form.site_id;
      }

      if (!eq(this.form.delivery_courier_id, current.delivery?.courier?.id)) {
        payload.delivery_courier_id = this.form.delivery_courier_id || null;
      }

      if (!eq(this.form.manager_id, current.manager_id)) {
        payload.manager_id = this.form.manager_id || null;
      }

      const customer = current.customer || {};
      const clientChanged =
        this.form.client_name !== (customer.name ?? '') ||
        this.getCleanPhone(this.form.client_phone) !== this.getCleanPhone(customer.phone ?? '') ||
        this.form.client_email !== (customer.email ?? '') ||
        this.form.client_address !== (customer.address ?? '');

      if (clientChanged) {
        payload.client_name = String(this.form.client_name ?? '').trim();
        payload.client_phone = String(this.form.client_phone ?? '').trim();
        payload.client_address = String(this.form.client_address ?? '').trim();
      }

      if (this.form.delivery_address !== (current.delivery?.delivery_address || customer.address || '')) {
        payload.delivery_address = String(this.form.delivery_address ?? '').trim() || null;
      }

      const normalizedRows = this.form.order_items
        .filter((item) => (item.name && item.name.trim() !== '') || item.qty || item.price)
        .map((item) => ({
          product_id: item.product_id || null,
          product_variant_id: item.product_variant_id || null,
          variant_title: item.variant_title ? String(item.variant_title).trim() : null,
          sku: item.sku ? String(item.sku).trim() : null,
          name: item.name && item.name !== null ? String(item.name).trim() : '',
          qty: item.qty && item.qty !== null ? (isNaN(item.qty) ? 1 : parseInt(item.qty, 10)) : 1,
          price: item.price === '' || item.price === null ? null : parseFloat(Number(item.price).toFixed(2)),
          purchase_price: item.purchase_price === '' || item.purchase_price === null ? null : parseFloat(Number(item.purchase_price).toFixed(2)),
        }));

      const currentItems = (current.items || []).map((item) => ({
        product_id: item.product_id || null,
        product_variant_id: item.product_variant_id || null,
        variant_title: item.variant_title ?? null,
        sku: item.sku ?? null,
        name: item.name ?? '',
        qty: item.qty ?? 1,
        price: item.price !== null && item.price !== undefined && item.price !== '' ? parseFloat(Number(item.price).toFixed(2)) : null,
        purchase_price: item.purchase_price !== null && item.purchase_price !== undefined && item.purchase_price !== '' ? parseFloat(Number(item.purchase_price).toFixed(2)) : null,
      }));

      if (JSON.stringify(normalizedRows) !== JSON.stringify(currentItems)) {
        payload.order_items = normalizedRows;
      }

      const newTotal = normalizePrice(this.form.total_price);
      const oldTotal = normalizePrice(current.total_price);
      if (newTotal !== oldTotal) {
        payload.total_price = newTotal !== null ? Number(newTotal.toFixed(2)) : null;
      }

      const newPurchase = normalizePrice(this.form.purchase_price);
      const oldPurchase = normalizePrice(current.purchase_price);
      if (newPurchase !== oldPurchase) {
        payload.purchase_price = isFilled(this.form.purchase_price) && newPurchase !== null
          ? Number(newPurchase.toFixed(2))
          : null;
      }

      const delivery = current.delivery?.[0] || current.delivery || {};
      const newDelivPrice = normalizePrice(this.form.delivery_price);
      const oldDelivPrice = normalizePrice(delivery.delivery_price);
      if (newDelivPrice !== oldDelivPrice) {
        payload.delivery_price = newDelivPrice !== null ? Number(newDelivPrice.toFixed(2)) : 0;
      }

      if (Boolean(this.form.is_valuable) !== Boolean(current.is_valuable)) {
        payload.is_valuable = Boolean(this.form.is_valuable);
      }

      return payload;
    },

    validate(payload) {
      this.errors = validateOrderForm(payload);
      return !hasErrors(this.errors);
    },

    async submitOrder() {
      const payload = this.buildPayload();
      if (!this.validate(payload)) {
        this.$dispatch('notify', { message: 'Проверьте правильность заполнения полей', type: 'error' });
        return;
      }

      this.submitting = true;
      try {
        await this.api(OrderController.store(), {
          body: JSON.stringify(payload),
        });
        this.disableClosingConfirmation(true);
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Заказ создан' });
        window.location.href = this.user?.role === 'manager' ? tma.manager.url() : orders.index.url();
      } catch (e) {
        this.hapticError?.();
        if (e.status === 422) {
          this.errors = extractServerErrors(e);
          this.$dispatch('notify', { message: 'Проверьте правильность заполнения полей', type: 'error' });
        } else {
          this.$dispatch('notify', { message: 'Ошибка создания', type: 'error' });
        }
        console.error('Failed to create order:', e);
      } finally {
        this.submitting = false;
      }
    },

    async submitEdit() {
      const payload = this.buildPayload();

      if (Object.keys(payload).length === 0) {
        this.disableClosingConfirmation(true);
        this.$dispatch('notify', { message: 'Нет изменений для сохранения' });
        return;
      }

      if (!this.validate(payload)) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Проверьте правильность заполнения полей', type: 'error' });
        return;
      }

      this.submitting = true;
      try {
        await this.api(OrderController.update(this.orderId), {
          body: JSON.stringify(payload),
        });
        this.disableClosingConfirmation(true);
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Изменения сохранены' });
        window.location.href = orders.show.url(this.orderId);
      } catch (e) {
        this.hapticError?.();
        if (e.status === 422) {
          this.errors = extractServerErrors(e);
          this.$dispatch('notify', { message: 'Проверьте правильность заполнения полей', type: 'error' });
        } else {
          this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
        }
        console.error('Failed to save order:', e);
      } finally {
        this.submitting = false;
      }
    },
  });
}
