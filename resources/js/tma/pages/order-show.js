import { baseInit, withTmaBase } from '../shared.js';
import { compressImageFiles } from '../shared/image-compression.js';
import { validateOrderForm, hasErrors, extractServerErrors, clearError } from '../validation.js';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import PersonnelController from '@/actions/App/Http/Controllers/PersonnelController';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import OrderCommentController from '@/actions/App/Http/Controllers/OrderCommentController';
import tma from '@/routes/tma';

function isFilled(value) {
  return value !== undefined && value !== null && String(value).trim() !== '';
}

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  return withTmaBase({
    user,
    retentionDays,
    botUsername,
    order: null,
    loading: true,
    loadError: null,
    history: [],
    historyTotal: 0,
    historyLoading: false,
    comments: [],
    commentsLoading: false,
    commentsTotal: 0,
    showAllCommentsModal: false,
    modalComments: [],
    modalCommentsLoading: false,
    modalCommentsLoadingMore: false,
    modalCommentsPage: 1,
    modalCommentsLastPage: 1,
    commentSearch: '',
    commentPersonnelFilter: '',
    commentPersonnelList: [],
    _commentSearchTimer: null,
    deletingCommentId: null,
    cancellingOrder: false,
    completingOrder: false,
    movingAtWarehouse: false,
    showCourierCompleteModal: false,
    showItemsModal: false,
    showProofPhotoModal: false,
    proofPhotoModalUrl: '',
    proofUploading: false,
    deletingProof: false,
    showSignatureModal: false,
    signatureUploading: false,
    deletingSignature: false,
    acceptingOrder: false,
    showCameraModal: false,
    cameraLoading: false,
    cameraError: null,
    cameraFacingMode: 'environment',
    capturedBlob: null,
    capturedPreviewUrl: null,
    _cameraStream: null,
    isDraggingFile: false,
    _dragDepth: 0,
    newCommentBody: '',
    commentError: '',
    commentSubmitting: false,
    editing: false,
    saving: false,
    errors: {},
    form: {
      site_id: '',
      delivery_type: '',
      delivery_courier_id: '',
      delivery_address: '',
      delivery_courier_comment: '',
      delivery_pickup_point: '',
      delivery_date: '',
      delivery_slot: '',
      delivery_price: '',
      is_valuable: false,
      manager_id: '',
      client_name: '',
      client_phone: '',
      client_email: '',
      client_address: '',
      order_items: [],
      total_price: '',
      purchase_price: '',
      payment_method: '',
      payment_status: '',
      payment_transaction_id: '',
      payment_amount: '',
      status: '',
    },
    sites: [],
    couriers: [],
    managers: [],
    managerSearch: '',
    courierSearch: '',
    siteSearch: '',
    managerDropdownOpen: false,
    courierDropdownOpen: false,
    siteDropdownOpen: false,
    statusDropdownOpen: false,
    deliveryTypeDropdownOpen: false,
    paymentMethodDropdownOpen: false,
    paymentStatusDropdownOpen: false,
    commentPersonnelDropdownOpen: false,
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
    _managerSearchTimer: null,
    _courierSearchTimer: null,
    availableStatuses: [],
    deliveryTypes: [
      { value: 'courier', label: 'Курьер' },
      { value: 'pickup', label: 'Самовывоз' },
    ],
    paymentMethods: [
      { value: 'cash', label: 'Наличные' },
      { value: 'terminal', label: 'Терминал' },
      { value: 'erip', label: 'ЕРИП' },
      { value: 'credit', label: 'Кредит' },
      { value: 'installment', label: 'Рассрочка' },
    ],
    paymentStatuses: [
      { value: 'pending', label: 'Ожидает оплаты' },
      { value: 'paid', label: 'Оплачен' },
      { value: 'failed', label: 'Неудача' },
    ],
    accordions: {
      client: true,
      items: true,
      finances: false,
      participants: false,
      delivery: user?.role === 'courier',
      payment: false,
      comments: false,
      history: false,
    },

    toggleAccordion(section) {
      this.accordions[section] = !this.accordions[section];
      if (this.accordions[section]) {
        if (section === 'history' && this.isOwner && this.history.length === 0 && !this.historyLoading) {
          this.loadHistory();
        } else if (section === 'comments' && !this.isCourier && this.comments.length === 0 && !this.commentsLoading) {
          this.loadComments();
        }
      }
    },

    ensureErrorAccordionOpen() {
      const fieldToSection = {
        client_name: 'client',
        client_phone: 'client',
        client_email: 'client',
        client_address: 'client',
        order_items: 'items',
        total_price: 'finances',
        purchase_price: 'finances',
        site_id: 'participants',
        manager_id: 'participants',
        status: 'participants',
        delivery_type: 'delivery',
        delivery_courier_id: 'delivery',
        delivery_address: 'delivery',
        delivery_courier_comment: 'delivery',
        delivery_pickup_point: 'delivery',
        delivery_date: 'delivery',
        delivery_slot: 'delivery',
        payment_method: 'payment',
        payment_status: 'payment',
        payment_transaction_id: 'payment',
        payment_amount: 'payment',
      };

      for (const key of Object.keys(this.errors || {})) {
        if (fieldToSection[key]) {
          this.accordions[fieldToSection[key]] = true;
        } else if (key.startsWith('order_items.')) {
          this.accordions.items = true;
        }
      }
    },

    get canEdit() {
      if (typeof window !== 'undefined' && window.__tmaPage) {
        return Boolean(window.__tmaPage.canEdit);
      }
      return this.user?.role === 'owner' || this.user?.role === 'manager';
    },

    get orderId() {
      const match = window.location.pathname.match(/^\/tma\/orders\/(\d+)$/);
      return match ? parseInt(match[1], 10) : null;
    },

    get backUrl() {
      if (this.user?.role === 'courier') {
        return tma.role.url('courier');
      }
      return this.user?.role === 'manager' ? tma.manager.url() : tma.home.url();
    },

    get isOwner() {
      return this.user?.role === 'owner';
    },

    get isManager() {
      return this.user?.role === 'manager';
    },

    get isCourier() {
      return this.user?.role === 'courier';
    },

    get canCancel() {
      if (!this.order) return false;
      if (this.order.status === 'cancelled' || this.order.status === 'completed') return false;
      if (this.isOwner) return true;
      if (this.isManager && this.order.manager_id === this.user?.id) return true;
      return false;
    },

    get canComplete() {
      if (!this.order) return false;
      if (this.order.status === 'cancelled' || this.order.status === 'completed') return false;
      if (this.isOwner) return true;
      if (this.isManager && this.order.manager_id === this.user?.id) return true;
      return false;
    },

    get hasActiveOrder() {
      return typeof window !== 'undefined' && window.__tmaPage?.hasActiveOrder === true;
    },

    get profit() {
      if (!this.order) return 0;
      return parseFloat(this.order.total_price || 0) - parseFloat(this.order.purchase_price || 0);
    },

    get itemLines() {
      return (this.order?.items || []).map((item) => ({
        id: item.id,
        product_id: item.product_id || null,
        product_variant_id: item.product_variant_id || null,
        name: item.name || '',
        label: item.name || '',
        variant_title: item.variant_title || null,
        sku: item.sku || null,
        price: item.price !== null && item.price !== undefined ? parseFloat(item.price) : null,
        purchase_price: item.purchase_price !== null && item.purchase_price !== undefined ? parseFloat(item.purchase_price) : null,
        qty: item.qty || 1,
        subtotal: item.subtotal !== null && item.subtotal !== undefined ? parseFloat(item.subtotal) : ((parseFloat(item.price || 0)) * (parseInt(item.qty || 1))),
        image_url: item.image_url || null,
        url: item.url || null,
        external_product_id: item.external_product_id || null,
        external_variant_id: item.external_variant_id || null,
      }));
    },

    inputClass(field, extra = '') {
      const border = this.errors?.[field] ? 'border-error' : 'border-border focus:border-primary';
      return `w-full px-3 py-2.5 bg-surface2 border rounded-lg text-sm focus:outline-none ${border} ${extra}`;
    },

    clearFieldError(field) {
      clearError(this.errors, field);
    },

    _lastTapTime: 0,
    _lastTapX: 0,
    _lastTapY: 0,
    _touchStartX: 0,
    _touchStartY: 0,
    _touchStartTime: 0,
    _touchMoved: false,

    handleOrderTouchStart(e) {
      if (!this.canEdit || this.editing) return;
      const touch = e.touches[0];
      if (!touch) return;
      this._touchStartX = touch.clientX;
      this._touchStartY = touch.clientY;
      this._touchStartTime = Date.now();
      this._touchMoved = false;
    },

    handleOrderTouchMove(e) {
      if (!this.canEdit || this.editing || this._touchMoved) return;
      const touch = e.touches[0];
      if (!touch) return;
      const dx = Math.abs(touch.clientX - this._touchStartX);
      const dy = Math.abs(touch.clientY - this._touchStartY);
      if (dx > 12 || dy > 12) {
        this._touchMoved = true;
      }
    },

    handleOrderTouchEnd(e) {
      if (!this.canEdit || this.editing) return;
      if (this._touchMoved) {
        this._lastTapTime = 0;
        return;
      }
      const touch = e.changedTouches[0];
      if (!touch) return;

      const duration = Date.now() - this._touchStartTime;
      if (duration > 350) {
        this._lastTapTime = 0;
        return;
      }

      if (this.isInteractiveElement(e.target)) {
        this._lastTapTime = 0;
        return;
      }

      const now = Date.now();
      const timeSinceLastTap = now - this._lastTapTime;
      const distFromLastTap = Math.hypot(
        touch.clientX - this._lastTapX,
        touch.clientY - this._lastTapY
      );

      if (timeSinceLastTap > 40 && timeSinceLastTap < 400 && distFromLastTap < 40) {
        this._lastTapTime = 0;
        this.triggerEditMode();
      } else {
        this._lastTapTime = now;
        this._lastTapX = touch.clientX;
        this._lastTapY = touch.clientY;
      }
    },

    handleOrderDoubleTap(e) {
      if (!this.canEdit || this.editing) return;
      if (this.isInteractiveElement(e.target)) return;
      this.triggerEditMode();
    },

    isInteractiveElement(target) {
      if (!target || !(target instanceof Element)) return false;
      return Boolean(target.closest('a, button, input, textarea, select, [role="button"], .modal, summary, [data-no-edit]'));
    },

    triggerEditMode() {
      if (!this.canEdit || this.editing || !this.order) return;
      if (
        this.showAllCommentsModal ||
        this.showItemsModal ||
        this.showCourierCompleteModal ||
        this.showProofPhotoModal ||
        this.showCameraModal
      ) {
        return;
      }
      const tg = window.Telegram?.WebApp;
      tg?.HapticFeedback?.impactOccurred?.('light');
      this.startEdit();
    },

    startEdit() {
      if (!this.order) return;
      if (this.isOwner && this.sites.length === 0) {
        this.loadSites();
      }
      this.errors = {};
      this.managerSearch = '';
      this.courierSearch = '';
      this.managerDropdownOpen = false;
      this.courierDropdownOpen = false;
      const delivery = this.order.delivery?.[0] || this.order.delivery;
      if (this.order?.manager && !this.managers.some((m) => m.id === this.order.manager.id)) {
        this.managers = [this.order.manager];
      }
      if (delivery?.courier && !this.couriers.some((c) => c.id === delivery.courier.id)) {
        this.couriers = [delivery.courier];
      }
      const customer = this.order.customer || {};
      const payment = this.order.payment?.[0] || this.order.payment;
      this.form = {
        site_id: this.order?.site_id || '',
        delivery_type: delivery?.type?.value || '',
        delivery_city: delivery?.city || '',
        delivery_courier_id: delivery?.courier?.id || '',
        delivery_address: delivery?.delivery_address || customer.address || '',
        delivery_courier_comment: delivery?.courier_comment || '',
        delivery_pickup_point: delivery?.pickup_point || '',
        delivery_date: delivery?.delivery_date || '',
        delivery_slot: delivery?.delivery_slot || '',
        delivery_price: delivery?.delivery_price !== undefined && delivery?.delivery_price !== null ? String(delivery.delivery_price) : '',
        is_valuable: Boolean(this.order?.is_valuable),
        manager_id: this.order?.manager_id || '',
        client_name: customer.name || '',
        client_phone: customer.formatted_phone || this.formatPhone(customer.phone) || customer.phone || '',
        client_email: customer.email || '',
        client_address: customer.address || '',
        order_items: (this.order?.items || []).map((item) => ({
          id: item.id,
          product_id: item.product_id,
          product_variant_id: item.product_variant_id || null,
          variant_title: item.variant_title || '',
          name: item.name ?? '',
          sku: item.sku ?? '',
          qty: item.qty ?? 1,
          price: item.price ?? '',
          purchase_price: item.purchase_price ?? '',
          _customPurchasePrice: item.purchase_price !== null && item.purchase_price !== undefined && item.purchase_price !== '',
        })),
        total_price: this.order?.total_price ?? '',
        purchase_price: this.order?.purchase_price ?? '',
        payment_method: payment?.method?.value || '',
        payment_status: payment?.status?.value || '',
        payment_transaction_id: payment?.transaction_id || '',
        payment_amount: payment?.amount ?? '',
        status: this.order?.status || '',
      };
      this.availableStatuses = this.isOwner ? this.getAvailableStatuses(this.order?.status) : [];
      this.editing = true;
    },

    onCourierChange() {
      if (this.form.delivery_courier_id) {
        this.form.status = 'assigned';
      } else if (this.form.status === 'assigned') {
        this.form.status = 'created';
      }
      this.availableStatuses = this.getAvailableStatuses(this.form.status);
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

    get filteredSites() {
      if (!this.siteSearch) return this.sites || [];
      const q = this.siteSearch.toLowerCase().trim();
      return (this.sites || []).filter(s =>
        (s.domain && s.domain.toLowerCase().includes(q)) ||
        (s.name && s.name.toLowerCase().includes(q))
      );
    },

    closeAllDropdowns(except = null) {
      const list = [
        'siteDropdownOpen',
        'statusDropdownOpen',
        'managerDropdownOpen',
        'deliveryTypeDropdownOpen',
        'courierDropdownOpen',
        'paymentMethodDropdownOpen',
        'paymentStatusDropdownOpen',
        'commentPersonnelDropdownOpen',
        'quickProductDropdownOpen',
      ];
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

    openStatusDropdown() {
      if (!this.statusDropdownOpen) {
        this.closeAllDropdowns('statusDropdownOpen');
      }
      this.statusDropdownOpen = !this.statusDropdownOpen;
    },

    openDeliveryTypeDropdown() {
      if (!this.deliveryTypeDropdownOpen) {
        this.closeAllDropdowns('deliveryTypeDropdownOpen');
      }
      this.deliveryTypeDropdownOpen = !this.deliveryTypeDropdownOpen;
    },

    openPaymentMethodDropdown() {
      if (!this.paymentMethodDropdownOpen) {
        this.closeAllDropdowns('paymentMethodDropdownOpen');
      }
      this.paymentMethodDropdownOpen = !this.paymentMethodDropdownOpen;
    },

    openPaymentStatusDropdown() {
      if (!this.paymentStatusDropdownOpen) {
        this.closeAllDropdowns('paymentStatusDropdownOpen');
      }
      this.paymentStatusDropdownOpen = !this.paymentStatusDropdownOpen;
    },

    openCommentPersonnelDropdown() {
      if (!this.commentPersonnelDropdownOpen) {
        this.closeAllDropdowns('commentPersonnelDropdownOpen');
      }
      this.commentPersonnelDropdownOpen = !this.commentPersonnelDropdownOpen;
    },

    getCommentPersonnelFilterLabel() {
      if (!this.commentPersonnelFilter) return 'Все авторы / персонал';
      const roleLabels = {
        'role:owner': 'Администраторы (Owner)',
        'role:manager': 'Менеджеры',
        'role:courier': 'Курьеры',
        'role:client': 'Клиент',
        'role:system': 'Система',
      };
      if (roleLabels[this.commentPersonnelFilter]) return roleLabels[this.commentPersonnelFilter];
      const p = this.commentPersonnelList?.find(item => item.id === this.commentPersonnelFilter);
      return p ? (p.system_name + (p.role ? ' (' + p.role + ')' : '')) : this.commentPersonnelFilter;
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

    cancelEdit() {
      this.closeAllDropdowns();
      this.quickProductDropdownOpen = false;
      this.quickProductSearch = '';
      this.activeItemProductDropdown = null;
      this.itemProductSearch = '';
      this.editing = false;
      this.errors = {};
      this.commentError = '';
      this.availableStatuses = [];
    },

    getSitePurchasePercent() {
      const siteId = this.form.site_id || this.order?.site_id;
      if (siteId && this.sites) {
        const site = this.sites.find((s) => s.id == siteId);
        if (site && site.default_purchase_percent !== null && site.default_purchase_percent !== undefined && site.default_purchase_percent !== '') {
          const val = parseFloat(site.default_purchase_percent);
          if (!isNaN(val)) return val;
        }
      }
      return window.__DEFAULT_PURCHASE_PERCENT__ !== undefined ? Number(window.__DEFAULT_PURCHASE_PERCENT__) : 15;
    },

    onItemPriceChange(item) {
      const percent = this.getSitePurchasePercent();
      const price = parseFloat(item.price) || 0;
      if (!item._customPurchasePrice) {
        item.purchase_price = price > 0 ? Number((price * (percent / 100)).toFixed(2)) : '';
      }
      this.recalculatePrices();
    },

    getItemsTotal() {
      const items = (this.editing && this.form?.order_items?.length)
        ? this.form.order_items
        : (this.order?.items || []);
      return items.reduce((sum, item) => sum + (parseFloat(item.price) || 0) * (parseInt(item.qty, 10) || 0), 0);
    },

    onDeliveryPriceInput(event) {
      this.form.delivery_price = event?.target?.value ?? this.form.delivery_price;
      this.recalculatePrices();
      this.clearFieldError('delivery_price');
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
        const siteId = this.form.site_id || this.order?.site_id;
        if (siteId) {
          query.site_id = siteId;
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
        const siteId = this.form.site_id || this.order?.site_id;
        if (siteId) {
          query.site_id = siteId;
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
        newItem.id = this.form.order_items[targetItemIndex].id || undefined;
        newItem.qty = this.form.order_items[targetItemIndex].qty || 1;
        this.form.order_items[targetItemIndex] = newItem;
        this.clearFieldError(`order_items.${targetItemIndex}.name`);
        this.clearFieldError(`order_items.${targetItemIndex}.qty`);
        this.clearFieldError(`order_items.${targetItemIndex}.price`);
        this.clearFieldError(`order_items.${targetItemIndex}.purchase_price`);
      } else {
        if (this.form.order_items.length === 1 && !this.form.order_items[0].name && !this.form.order_items[0].price) {
          newItem.id = this.form.order_items[0].id || undefined;
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
      this.quickProductDropdownOpen = false;
      this.quickProductSearch = '';
      this.activeItemProductDropdown = null;
      this.itemProductSearch = '';
    },

    addItem() {
      this.form.order_items.push({
        name: '',
        sku: '',
        qty: 1,
        price: '',
        purchase_price: '',
        _customPurchasePrice: false,
      });
      this.recalculatePrices();
    },

    removeItem(index) {
      this.form.order_items.splice(index, 1);
      this.recalculatePrices();
    },

    keptRows() {
      return this.form.order_items.filter(
        (item) => isFilled(item.name) || isFilled(item.sku) || isFilled(item.qty) || isFilled(item.price) || isFilled(item.purchase_price)
      );
    },

    buildPayload(rows) {
      const current = this.order || {};
      const payload = {};
      const delivery = current.delivery?.[0] || current.delivery || {};
      const payment = current.payment?.[0] || current.payment || {};

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

      if (!eq(this.form.delivery_type, delivery.type?.value)) {
        payload.delivery_type = this.form.delivery_type || null;
      }

      if (!eq(this.form.delivery_city, delivery.city || '')) {
        payload.delivery_city = String(this.form.delivery_city ?? '').trim() || null;
      }

      if (!eq(this.form.delivery_courier_id, delivery.courier?.id)) {
        payload.delivery_courier_id = this.form.delivery_courier_id || null;
      }

      if (!eq(this.form.delivery_address, delivery.delivery_address || '')) {
        payload.delivery_address = String(this.form.delivery_address ?? '').trim() || null;
      }

      if (!eq(this.form.delivery_courier_comment, delivery.courier_comment || '')) {
        payload.delivery_courier_comment = String(this.form.delivery_courier_comment ?? '').trim() || null;
      }

      if (!eq(this.form.delivery_pickup_point, delivery.pickup_point || '')) {
        payload.delivery_pickup_point = String(this.form.delivery_pickup_point ?? '').trim() || null;
      }

      if (!eq(this.form.delivery_date, delivery.delivery_date || '')) {
        payload.delivery_date = this.form.delivery_date || null;
      }

      if (!eq(this.form.delivery_slot, delivery.delivery_slot || '')) {
        payload.delivery_slot = String(this.form.delivery_slot ?? '').trim() || null;
      }

      const newDelivPrice = normalizePrice(this.form.delivery_price);
      const oldDelivPrice = normalizePrice(delivery.delivery_price);
      if (newDelivPrice !== oldDelivPrice) {
        payload.delivery_price = newDelivPrice !== null ? Number(newDelivPrice.toFixed(2)) : 0;
      }

      if (Boolean(this.form.is_valuable) !== Boolean(current.is_valuable)) {
        payload.is_valuable = Boolean(this.form.is_valuable);
      }

      const customer = current.customer || {};
      const clientChanged =
        this.form.client_name !== (customer.name ?? '') ||
        this.getCleanPhone(this.form.client_phone) !== this.getCleanPhone(customer.phone ?? '') ||
        this.form.client_address !== (customer.address ?? '');

      if (clientChanged) {
        payload.client_name = String(this.form.client_name ?? '').trim();
        payload.client_phone = String(this.form.client_phone ?? '').trim();
        payload.client_address = String(this.form.client_address ?? '').trim();
      }

      const normalizedRows = rows.map((item) => ({
        product_id: item.product_id || null,
        product_variant_id: item.product_variant_id || null,
        name: String(item.name ?? '').trim(),
        sku: item.sku ? String(item.sku).trim() : null,
        qty: parseInt(item.qty, 10) || 1,
        price: isFilled(item.price) ? Number(String(item.price).replace(',', '.')) : null,
        purchase_price: isFilled(item.purchase_price) ? Number(String(item.purchase_price).replace(',', '.')) : null,
      }));

      const currentItems = (current.items || []).map((item) => ({
        product_id: item.product_id || null,
        product_variant_id: item.product_variant_id || null,
        name: item.name ?? '',
        sku: item.sku ?? '',
        qty: item.qty ?? 1,
        price: item.price !== null && item.price !== undefined && item.price !== '' ? parseFloat(Number(item.price).toFixed(2)) : null,
        purchase_price: item.purchase_price !== null && item.purchase_price !== undefined && item.purchase_price !== '' ? parseFloat(Number(item.purchase_price).toFixed(2)) : null,
      }));

      if (JSON.stringify(normalizedRows) !== JSON.stringify(currentItems)) {
        payload.order_items = normalizedRows;
      }

      if (this.isOwner) {
        if (!eq(this.form.manager_id, current.manager_id)) {
          payload.manager_id = this.form.manager_id || null;
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

        if (this.form.status && this.form.status !== current.status) {
          payload.status = this.form.status;
        }
      }

      if (!eq(this.form.payment_method, payment.method?.value)) {
        payload.payment_method = this.form.payment_method || null;
      }

      if (!eq(this.form.payment_status, payment.status?.value)) {
        payload.payment_status = this.form.payment_status || null;
      }

      if (!eq(this.form.payment_transaction_id, payment.transaction_id || '')) {
        payload.payment_transaction_id = String(this.form.payment_transaction_id ?? '').trim() || null;
      }

      const newPaymentAmount = normalizePrice(this.form.payment_amount);
      const oldPaymentAmount = normalizePrice(payment.amount);
      if (newPaymentAmount !== oldPaymentAmount) {
        payload.payment_amount = isFilled(this.form.payment_amount) ? newPaymentAmount : null;
      }

      return payload;
    },

    async saveEdit() {
      const rows = this.keptRows();
      const payload = this.buildPayload(rows);

      if (Object.keys(payload).length === 0) {
        this.editing = false;
        this.errors = {};
        this.$dispatch('notify', { message: 'Нет изменений для сохранения' });
        return;
      }

      this.errors = validateOrderForm(payload);
      if (hasErrors(this.errors)) {
        this.ensureErrorAccordionOpen();
        this.$dispatch('notify', { message: 'Проверьте правильность заполнения полей', type: 'error' });
        return;
      }

      this.saving = true;
      try {
        await this.api(OrderController.update(this.orderId), {
          body: JSON.stringify(payload),
        });
        await this.loadOrder();
        if (!this.isCourier) {
          await this.loadComments();
        }
        if (this.isOwner) {
          await this.loadHistory();
        }
        this.editing = false;
        this.errors = {};
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Изменения сохранены' });
      } catch (e) {
        this.hapticError?.();
        if (e.status === 422) {
          this.errors = extractServerErrors(e);
          this.ensureErrorAccordionOpen();
          this.$dispatch('notify', { message: 'Проверьте правильность заполнения полей', type: 'error' });
        } else {
          this.$dispatch('notify', { message: 'Ошибка сохранения', type: 'error' });
        }
        console.error('Failed to save order:', e);
      } finally {
        this.saving = false;
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

    formatDateTime(value) {
      return this.formatDate(value);
    },

    async init() {
      const ok = await baseInit.call(this);
      if (!ok) return;
      this.$watch('editing', (val) => {
        if (val) {
          this.enableClosingConfirmation('order-edit');
          document.body.classList.add('has-modal-open');
          window.dispatchEvent(new CustomEvent('modal-state-changed', { detail: { open: true } }));
        } else {
          this.disableClosingConfirmation('order-edit');
          document.body.classList.remove('has-modal-open');
          window.dispatchEvent(new CustomEvent('modal-state-changed', { detail: { open: false } }));
        }
      });

      this.$watch('newCommentBody', (val) => {
        if (this.commentError) {
          this.commentError = '';
        }
        if (val && String(val).trim().length > 0) {
          this.enableClosingConfirmation('order-comment');
        } else {
          this.disableClosingConfirmation('order-comment');
        }
      });

      await this.loadOrder();
      if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        if ((params.get('edit') === '1' || params.get('edit') === 'true') && this.canEdit && !this.editing) {
          this.startEdit();
        }
      }
    },

    handlePaste(e) {
      if (!this.canManageProofPhoto?.()) return;
      const target = e.target;
      if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
        return;
      }
      const items = e.clipboardData?.items;
      if (!items || items.length === 0) return;
      const imgFiles = [];
      for (let i = 0; i < items.length; i++) {
        if (items[i].type?.startsWith('image/')) {
          const file = items[i].getAsFile();
          if (file) imgFiles.push(file);
        }
      }
      if (imgFiles.length > 0) {
        e.preventDefault();
        this.uploadDeliveryProofFiles(imgFiles);
      }
    },

    goBack() {
      if (this.showAllCommentsModal) {
        this.showAllCommentsModal = false;
        return;
      }
      if (this.showItemsModal) {
        this.showItemsModal = false;
        return;
      }
      if (this.showCourierCompleteModal) {
        this.showCourierCompleteModal = false;
        return;
      }
      if (this.showProofPhotoModal) {
        this.showProofPhotoModal = false;
        return;
      }
      if (this.showCameraModal) {
        this.closeCameraModal();
        return;
      }
      if (this.editing) {
        this.cancelEdit();
        return;
      }
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = this.backUrl;
      }
    },

    async acceptOrder() {
      if (!this.orderId || this.acceptingOrder) return;

      this.acceptingOrder = true;
      try {
        const res = await this.api(OrderController.accept(this.orderId));
        this.order = res?.order || null;
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Заказ принят' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка принятия заказа', type: 'error' });
      } finally {
        this.acceptingOrder = false;
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

    async atWarehouse() {
      if (!this.orderId) return;

      try {
        const res = await this.api(OrderController.atWarehouse(this.orderId));
        this.order = res?.order || null;
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Статус обновлён: в пути' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка обновления статуса', type: 'error' });
      }
    },

    async promptAtWarehouse() {
      if (this.movingAtWarehouse) return;
      const ok = await window.confirmDialog({
        title: 'Подтвердить действие',
        message: 'Вы уверены, что хотите отметить заказ как «В путь»?',
        confirmText: 'Да',
        cancelText: 'Нет',
        variant: 'primary',
      });
      if (!ok) return;

      this.movingAtWarehouse = true;
      try {
        await this.atWarehouse();
      } finally {
        this.movingAtWarehouse = false;
      }
    },

    async completeOrder() {
      if (!this.orderId) return;

      try {
        const res = await this.api(OrderController.complete(this.orderId));
        this.order = res?.order || null;
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Заказ завершён' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка завершения заказа', type: 'error' });
      }
    },

    async confirmCourierCompleteOrder() {
      if (this.completingOrder) return;
      if (this.requiresSignature() && !this.hasSignature()) {
        this.hapticWarning?.();
        this.$dispatch('notify', { message: 'Для ценного отправления требуется подпись клиента', type: 'warn' });
        this.openSignaturePad();
        return;
      }

      const authOk = await this.requestBiometricAuth('Подтвердите завершение доставки и расчет');
      if (!authOk) return;

      this.completingOrder = true;
      try {
        await this.completeOrder();
        this.showCourierCompleteModal = false;
      } finally {
        this.completingOrder = false;
      }
    },

    async promptCompleteOrder() {
      if (this.completingOrder) return;
      const ok = await window.confirmDialog({
        title: 'Завершить заказ?',
        message: 'После завершения заказ можно будет вернуть только на склад.',
        confirmText: 'Да',
        cancelText: 'Нет',
        variant: 'primary',
      });
      if (!ok) return;

      const authOk = await this.requestBiometricAuth('Подтвердите завершение заказа');
      if (!authOk) return;

      this.completingOrder = true;
      try {
        await this.completeOrder();
      } finally {
        this.completingOrder = false;
      }
    },

    async cancelOrder() {
      if (!this.orderId) return;
      if (!this.canCancel) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Отмена доступна только менеджеру этого заказа', type: 'error' });
        return;
      }

      try {
        const res = await this.api(OrderController.cancel(this.orderId));
        this.order = res?.order || null;
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Заказ отменён' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: e?.message || 'Ошибка отмены', type: 'error' });
      }
    },

    async promptCancelOrder() {
      if (this.cancellingOrder) return;
      const ok = await window.confirmDialog({
        title: 'Отменить заказ?',
        message: 'Это действие нельзя будет отменить.',
        confirmText: 'Да, отменить',
        cancelText: 'Нет',
        variant: 'danger',
      });
      if (!ok) return;

      this.cancellingOrder = true;
      try {
        await this.cancelOrder();
      } finally {
        this.cancellingOrder = false;
      }
    },

    getPhotoProofUrls() {
      const d = this.order?.delivery;
      if (!d) return [];
      const item = Array.isArray(d) ? d[0] : d;
      if (Array.isArray(item?.photo_proof_urls) && item.photo_proof_urls.length > 0) {
        return item.photo_proof_urls;
      }
      if (item?.photo_proof_url) {
        return [item.photo_proof_url];
      }
      return [];
    },

    getPhotoProofUrl() {
      const urls = this.getPhotoProofUrls();
      return urls.length > 0 ? urls[0] : null;
    },

    async promptDeleteProof(url = null) {
      if (this.isCourier && (this.order?.status === 'completed' || this.order?.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя удалить фото для завершенного заказа', type: 'error' });
        return;
      }
      if (this.deletingProof) return;
      const ok = await window.confirmDialog({
        title: 'Удаление фотофиксации',
        message: url ? 'Удалить выбранную фотографию вручения? Это действие нельзя отменить.' : 'Удалить все прикрепленные фотографии вручения? Это действие нельзя отменить.',
        confirmText: 'Удалить',
        cancelText: 'Отмена',
        variant: 'danger',
      });
      if (!ok) return;

      this.deletingProof = true;
      try {
        await this.deleteDeliveryProof(url);
      } finally {
        this.deletingProof = false;
      }
    },

    getPhotoProofAt() {
      const d = this.order?.delivery;
      if (!d) return null;
      if (Array.isArray(d)) return d[0]?.photo_proof_at || null;
      return d.photo_proof_at || null;
    },

    canManageProofPhoto() {
      if (this.isOwner) return true;
      if (this.isCourier) {
        if (this.order?.status === 'completed' || this.order?.status === 'cancelled') {
          return false;
        }
        const d = this.order?.delivery;
        const courierId = Array.isArray(d) ? d[0]?.courier?.id : d?.courier?.id;
        return Number(courierId) === Number(this.user?.id);
      }
      return false;
    },

    canShowProofSection() {
      if (this.order?.status === 'completed' || this.order?.status === 'cancelled') {
        if (this.isCourier) return false;
        return this.getPhotoProofUrls().length > 0;
      }
      return true;
    },

    canShowSignatureSection() {
      if (this.order?.status === 'completed' || this.order?.status === 'cancelled') {
        if (this.isCourier) return false;
        return this.hasSignature();
      }
      return true;
    },

    openProofPhotoModal(url) {
      if (!url) return;
      this.proofPhotoModalUrl = url;
      this.showProofPhotoModal = true;
    },

    hasSignature() {
      const d = this.order?.delivery;
      if (!d) return false;
      const item = Array.isArray(d) ? d[0] : d;
      return !!(item?.signature_url || item?.has_signature);
    },

    getSignatureUrl() {
      const d = this.order?.delivery;
      if (!d) return null;
      const item = Array.isArray(d) ? d[0] : d;
      return item?.signature_url || null;
    },

    getSignatureAt() {
      const d = this.order?.delivery;
      if (!d) return null;
      const item = Array.isArray(d) ? d[0] : d;
      return item?.signature_at || null;
    },

    requiresSignature() {
      return !!(this.order?.requires_signature || this.order?.is_valuable);
    },

    openSignaturePad() {
      if (this.isCourier && (this.order?.status === 'completed' || this.order?.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя изменить подпись для завершенного заказа', type: 'error' });
        return;
      }
      this.showSignatureModal = true;
    },

    closeSignaturePad() {
      this.showSignatureModal = false;
    },

    async saveSignature(blob) {
      if (!this.orderId || !blob || this.signatureUploading) return;
      if (this.isCourier && (this.order?.status === 'completed' || this.order?.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя изменить подпись для завершенного заказа', type: 'error' });
        return;
      }

      this.signatureUploading = true;
      this.setUploadLoading(true, { title: 'Сохранение подписи...', subtitle: 'Передача на сервер' });

      const file = new File([blob], `signature_${this.orderId}_${Date.now()}.png`, { type: 'image/png' });
      const formData = new FormData();
      formData.append('signature', file);

      try {
        const response = await this.api(OrderController.uploadSignature(this.orderId), {
          body: formData,
        });

        if (response?.order) {
          this.order = response.order;
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Подпись сохранена' });
        this.$dispatch('notify', { message: 'Подпись клиента зафиксирована', type: 'success' });
        this.closeSignaturePad();
      } catch (e) {
        this.hapticError?.();
        this.setUploadLoading(false, { status: 'error', message: 'Ошибка сохранения' });
        this.$dispatch('notify', { message: e?.message || 'Ошибка сохранения подписи', type: 'error' });
      } finally {
        this.signatureUploading = false;
      }
    },

    async promptDeleteSignature() {
      if (this.deletingSignature) return;
      const ok = await window.confirmDialog({
        title: 'Удаление подписи',
        message: 'Удалить цифровую подпись клиента? Это действие нельзя отменить.',
        confirmText: 'Удалить',
        cancelText: 'Отмена',
        variant: 'danger',
      });
      if (!ok) return;

      this.deletingSignature = true;
      try {
        await this.deleteSignature();
      } finally {
        this.deletingSignature = false;
      }
    },

    async deleteSignature() {
      if (!this.orderId) return;

      this.signatureUploading = true;
      this.setUploadLoading(true, { title: 'Удаление подписи...', subtitle: 'Обновление данных' });

      try {
        const response = await this.api(OrderController.deleteSignature(this.orderId));

        if (response?.order) {
          this.order = response.order;
        }

        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Подпись удалена' });
        this.$dispatch('notify', { message: 'Подпись клиента удалена', type: 'info' });
      } catch (e) {
        this.hapticError?.();
        this.setUploadLoading(false, { status: 'error', message: 'Ошибка удаления' });
        this.$dispatch('notify', { message: e?.message || 'Ошибка удаления подписи', type: 'error' });
      } finally {
        this.signatureUploading = false;
      }
    },

    async openCamera() {
      if (this.isCourier && (this.order?.status === 'completed' || this.order?.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя добавить фото для завершенного заказа', type: 'error' });
        return;
      }

      this.capturedBlob = null;
      this.capturedPreviewUrl = null;
      this.cameraError = null;
      this.showCameraModal = true;
      await this.$nextTick();
      await this.initCameraStream();
    },

    async initCameraStream() {
      if (!navigator.mediaDevices?.getUserMedia) {
        this.cameraError = 'Камера не поддерживается вашим браузером. Загрузите фото файлом.';
        return;
      }

      this.cameraLoading = true;
      this.cameraError = null;
      this.stopCameraStream();

      try {
        const constraints = {
          video: {
            facingMode: { ideal: this.cameraFacingMode },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
          },
          audio: false,
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        this._cameraStream = stream;

        const videoEl = this.$refs.cameraVideo;
        if (videoEl) {
          videoEl.srcObject = stream;
          await videoEl.play();
        }
      } catch (err) {
        console.warn('Camera access error:', err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
          this.cameraError = 'Доступ к камере запрещен. Разрешите доступ в браузере или загрузите фото файлом.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
          this.cameraError = 'Камера на устройстве не найдена. Загрузите фото файлом.';
        } else {
          this.cameraError = 'Не удалось запустить камеру: ' + (err.message || 'ошибка оборудования') + '.';
        }
      } finally {
        this.cameraLoading = false;
      }
    },

    async switchCamera() {
      this.cameraFacingMode = this.cameraFacingMode === 'environment' ? 'user' : 'environment';
      await this.initCameraStream();
    },

    stopCameraStream() {
      if (this._cameraStream) {
        this._cameraStream.getTracks().forEach((track) => track.stop());
        this._cameraStream = null;
      }
      if (this.$refs.cameraVideo) {
        this.$refs.cameraVideo.srcObject = null;
      }
    },

    closeCameraModal() {
      this.stopCameraStream();
      if (this.capturedPreviewUrl) {
        URL.revokeObjectURL(this.capturedPreviewUrl);
        this.capturedPreviewUrl = null;
      }
      this.capturedBlob = null;
      this.showCameraModal = false;
    },

    takeSnapshot() {
      const videoEl = this.$refs.cameraVideo;
      if (!videoEl || !this._cameraStream) return;

      let width = videoEl.videoWidth || 1280;
      let height = videoEl.videoHeight || 720;
      const maxDim = 1920;

      if (width > maxDim || height > maxDim) {
        const ratio = Math.min(maxDim / width, maxDim / height);
        width = Math.round(width * ratio);
        height = Math.round(height * ratio);
      }

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(videoEl, 0, 0, width, height);

      canvas.toBlob((blob) => {
        if (!blob) return;
        this.capturedBlob = blob;
        this.capturedPreviewUrl = URL.createObjectURL(blob);
        this.stopCameraStream();
        this.hapticSuccess?.();
      }, 'image/jpeg', 0.82);
    },

    retakeSnapshot() {
      if (this.capturedPreviewUrl) {
        URL.revokeObjectURL(this.capturedPreviewUrl);
        this.capturedPreviewUrl = null;
      }
      this.capturedBlob = null;
      this.initCameraStream();
    },

    async confirmCapturedSnapshot() {
      if (!this.capturedBlob || !this.orderId) return;

      if (this.isCourier && (this.order?.status === 'completed' || this.order?.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя добавить фото для завершенного заказа', type: 'error' });
        this.closeCameraModal();
        return;
      }

      const currentCount = this.getPhotoProofUrls().length;
      if (currentCount >= 3) {
        this.$dispatch('notify', { message: 'Достигнут лимит: максимум 3 фото', type: 'warn' });
        this.closeCameraModal();
        return;
      }

      this.proofUploading = true;
      this.setUploadLoading(true, { title: 'Сохранение снимка...', subtitle: 'Отправка на сервер' });
      const file = new File([this.capturedBlob], `proof_${this.orderId}_${Date.now()}.jpg`, { type: 'image/jpeg' });
      const formData = new FormData();
      formData.append('photo', file);

      try {
        const response = await this.api(OrderController.uploadProof(this.orderId), {
          body: formData,
        });

        if (response?.order) {
          this.order = response.order;
        } else {
          await this.loadOrder(true);
        }
        this.hapticSuccess?.();
        this.closeCameraModal();
        this.setUploadLoading(false, { status: 'success', message: 'Снимок сохранен' });
        this.$dispatch('notify', { message: 'Фото вручения сохранено' });
        this.scrollToDeliverySection();
      } catch (e) {
        this.hapticError?.();
        this.setUploadLoading(false, { status: 'error', message: 'Ошибка сохранения' });
        this.$dispatch('notify', { message: e?.message || 'Ошибка сохранения фото', type: 'error' });
      } finally {
        this.proofUploading = false;
      }
    },

    handleDragEnter(event) {
      if (!this.canManageProofPhoto?.()) return;
      const types = event.dataTransfer?.types;
      if (!types || !Array.from(types).includes('Files')) return;
      this._dragDepth = (this._dragDepth || 0) + 1;
      this.isDraggingFile = true;
    },

    handleDragLeave(event) {
      this._dragDepth = Math.max(0, (this._dragDepth || 1) - 1);
      if (this._dragDepth === 0) {
        this.isDraggingFile = false;
      }
    },

    handleDrop(event) {
      this._dragDepth = 0;
      this.isDraggingFile = false;
      this.uploadDeliveryProof(event);
    },

    checkWindowSizeForUpload(event) {
      try {
        window.Telegram?.WebApp?.expand?.();
      } catch (_) { }

      const tgPlatform = (window.Telegram?.WebApp?.platform || '').toLowerCase();
      const isMobile = tgPlatform === 'android'
        || tgPlatform === 'ios'
        || /android|iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.maxTouchPoints > 1 && /macintosh/i.test(navigator.userAgent));

      if (isMobile) {
        return;
      }

      const w = window.innerWidth || document.documentElement.clientWidth;
      const h = window.innerHeight || document.documentElement.clientHeight;
      if (w < 400 || h < 300) {
        this.$dispatch('notify', {
          message: 'Окно Telegram слишком узкое (менее 400px). Потяните за край окна, сделав его чуть шире, чтобы открылся проводник файлов.',
          type: 'warn',
        });
      }
    },

    async uploadDeliveryProof(event) {
      const files = event.dataTransfer?.files || event.target?.files;
      if (!files || files.length === 0 || !this.orderId) return;
      await this.uploadDeliveryProofFiles(files);
      if (event.target && 'value' in event.target) {
        try {
          event.target.value = '';
        } catch (_) { }
      }
    },

    async uploadDeliveryProofFiles(files) {
      if (!files || files.length === 0 || !this.orderId) return;

      const rawList = Array.from(files);
      const uniqueFiles = [];
      const seenSignatures = new Set();
      let localDuplicates = 0;
      for (const file of rawList) {
        const sig = `${file.name || ''}_${file.size || 0}_${file.lastModified || 0}`;
        if (seenSignatures.has(sig)) {
          localDuplicates++;
          continue;
        }
        seenSignatures.add(sig);
        uniqueFiles.push(file);
      }

      if (localDuplicates > 0) {
        this.$dispatch('notify', { message: 'Одинаковые фото исключены из загрузки', type: 'info' });
      }

      if (uniqueFiles.length === 0) return;

      if (this.isCourier && (this.order?.status === 'completed' || this.order?.status === 'cancelled')) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Нельзя добавить фото для завершенного заказа', type: 'error' });
        return;
      }

      const currentCount = this.getPhotoProofUrls().length;
      if (currentCount >= 3) {
        this.$dispatch('notify', { message: 'Достигнут лимит: максимум 3 фото', type: 'warn' });
        return;
      }

      this.proofUploading = true;
      const remainingSlots = 3 - currentCount;
      const toUpload = uniqueFiles.slice(0, remainingSlots);

      if (uniqueFiles.length > remainingSlots) {
        this.$dispatch('notify', { message: `Выбрано больше лимита, загружено ${remainingSlots} фото`, type: 'info' });
      }

      this.setUploadLoading(true, {
        title: 'Загрузка фото...',
        subtitle: `Оптимизация и отправка (${toUpload.length})`,
      });

      let processedFiles = toUpload;
      try {
        processedFiles = await compressImageFiles(toUpload, { maxWidth: 1920, maxHeight: 1920, quality: 0.82 });
      } catch (err) {
        processedFiles = toUpload;
      }

      const formData = new FormData();
      if (processedFiles.length === 1) {
        formData.append('photo', processedFiles[0]);
      } else {
        processedFiles.forEach((f) => formData.append('photos[]', f));
      }

      try {
        const response = await this.api(OrderController.uploadProof(this.orderId), {
          body: formData,
        });

        if (response?.order) {
          this.order = response.order;
        } else {
          await this.loadOrder(true);
        }
        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Фото сохранено' });
        this.$dispatch('notify', { message: 'Фото вручения сохранено' });
        this.scrollToDeliverySection();
      } catch (e) {
        this.hapticError?.();
        this.setUploadLoading(false, { status: 'error', message: 'Ошибка сохранения' });
        this.$dispatch('notify', { message: e?.message || 'Ошибка загрузки фото', type: 'error' });
      } finally {
        this.proofUploading = false;
      }
    },

    async deleteDeliveryProof(targetUrl = null) {
      if (!this.orderId) return;

      this.proofUploading = true;
      this.setUploadLoading(true, { title: 'Удаление фото...', subtitle: 'Обновление данных' });
      try {
        const options = targetUrl ? { query: { path: targetUrl }, body: { path: targetUrl } } : undefined;
        const response = await this.api(OrderController.deleteProof(this.orderId, options));
        if (response?.order) {
          this.order = response.order;
        } else {
          await this.loadOrder(true);
        }
        this.hapticSuccess?.();
        this.setUploadLoading(false, { status: 'success', message: 'Фото удалено' });
        this.$dispatch('notify', { message: 'Фото вручения удалено' });
      } catch (e) {
        this.hapticError?.();
        this.setUploadLoading(false, { status: 'error', message: 'Ошибка удаления' });
        this.$dispatch('notify', { message: e?.message || 'Ошибка удаления фото', type: 'error' });
      } finally {
        this.proofUploading = false;
      }
    },

    scrollToDeliverySection() {
      this.accordions.delivery = true;
      this.$nextTick(() => {
        setTimeout(() => {
          const el = this.$refs.deliverySection || document.getElementById('delivery-section');
          if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }, 120);
      });
    },

    async loadOrder(silent = false) {
      if (!silent) {
        this.loading = true;
      }
      this.loadError = null;
      try {
        const res = await this.api(OrderController.show(this.orderId));
        this.order = res?.order || null;
      } catch (e) {
        this.loadError = e.status === 404 ? 'Заказ не найден' : 'Ошибка загрузки';
        console.error('Failed to load order:', e);
      } finally {
        if (!silent) {
          this.loading = false;
        }
      }
    },

    async loadHistory() {
      if (!this.isOwner || !this.orderId) return;
      this.historyLoading = true;
      try {
        const data = await this.api(AuditController.orderLogs(this.orderId));
        const logs = data.logs ?? [];
        this.historyTotal = logs.length;
        this.history = logs.slice(0, 5);
      } catch (e) {
        console.error('Failed to load history:', e);
        this.history = [];
        this.historyTotal = 0;
      } finally {
        this.historyLoading = false;
      }
    },

    async loadComments() {
      if (this.isCourier) return;
      this.commentsLoading = true;
      try {
        const data = await this.api(OrderCommentController.index(this.orderId));
        this.comments = data.comments ?? [];
        this.commentsTotal = data.total ?? this.comments.length;
      } catch (e) {
        console.error('Failed to load comments:', e);
        this.comments = [];
        this.commentsTotal = 0;
      } finally {
        this.commentsLoading = false;
      }
    },

    openAllCommentsModal() {
      if (this.isCourier) return;
      this.showAllCommentsModal = true;
      this.commentSearch = '';
      this.commentPersonnelFilter = '';
      this.modalCommentsPage = 1;
      this.modalCommentsLastPage = 1;
      this.modalComments = [];
      this.loadCommentPersonnel();
      this.fetchModalComments(1, true);
    },

    closeAllCommentsModal() {
      this.showAllCommentsModal = false;
    },

    async loadCommentPersonnel() {
      if (this.commentPersonnelList?.length > 0) return;
      try {
        if (this.isOwner) {
          const res = await this.api(PersonnelController.index({ query: { per_page: 100 } }));
          const list = res?.personnel || [];
          this.commentPersonnelList = list.map((p) => ({
            id: p.id,
            system_name: p.system_name || p.first_name || ('ID: ' + p.id),
            role: p.role,
          }));
        } else {
          const [couriersRes, managersRes] = await Promise.all([
            this.api(PersonnelController.search({ query: { role: 'courier', limit: 50 } })).catch(() => ({})),
            this.api(PersonnelController.search({ query: { role: 'manager', limit: 50 } })).catch(() => ({})),
          ]);
          const couriers = couriersRes?.couriers || [];
          const managers = managersRes?.managers || [];
          this.commentPersonnelList = [...couriers, ...managers];
        }
      } catch (e) {
        console.error('Failed to load personnel for comments filter:', e);
      }
    },

    onCommentSearchInput() {
      clearTimeout(this._commentSearchTimer);
      this._commentSearchTimer = setTimeout(() => {
        this.modalCommentsPage = 1;
        this.fetchModalComments(1, true);
      }, 250);
    },

    onCommentPersonnelChange() {
      this.modalCommentsPage = 1;
      this.fetchModalComments(1, true);
    },

    async fetchModalComments(page = 1, reset = false) {
      if (reset) {
        this.modalCommentsLoading = true;
      } else {
        this.modalCommentsLoadingMore = true;
      }

      try {
        const params = new URLSearchParams({
          page: String(page),
          per_page: '10',
        });
        if (this.commentSearch?.trim()) {
          params.set('search', this.commentSearch.trim());
        }
        if (this.commentPersonnelFilter) {
          if (this.commentPersonnelFilter.startsWith('role:')) {
            params.set('author_type', this.commentPersonnelFilter.replace('role:', ''));
          } else {
            params.set('user_id', this.commentPersonnelFilter);
          }
        }

        const data = await this.api(OrderCommentController.index(this.orderId, { query: Object.fromEntries(params) }));
        const items = data.comments ?? [];
        this.modalCommentsPage = data.current_page ?? page;
        this.modalCommentsLastPage = data.last_page ?? 1;

        if (reset) {
          this.modalComments = items;
        } else {
          const existingIds = new Set(this.modalComments.map((c) => c.id));
          const newItems = items.filter((c) => !existingIds.has(c.id));
          this.modalComments = [...this.modalComments, ...newItems];
        }

        if (!this.commentSearch?.trim() && !this.commentPersonnelFilter && data.total !== undefined) {
          this.commentsTotal = data.total;
        }
      } catch (e) {
        console.error('Failed to fetch modal comments:', e);
        if (reset) {
          this.modalComments = [];
        }
      } finally {
        this.modalCommentsLoading = false;
        this.modalCommentsLoadingMore = false;
      }
    },

    handleModalCommentsScroll(e) {
      const el = e.target;
      if (!el) return;
      if (this.modalCommentsLoading || this.modalCommentsLoadingMore) return;
      if (this.modalCommentsPage >= this.modalCommentsLastPage) return;

      const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
      if (nearBottom) {
        this.fetchModalComments(this.modalCommentsPage + 1, false);
      }
    },

    async addComment() {
      if (this.commentSubmitting) return;

      const body = this.newCommentBody?.trim();
      if (!body) {
        this.commentError = 'Введите текст комментария';
        this.hapticError?.();
        return;
      }

      if (body.length > 2000) {
        this.commentError = 'Комментарий не должен превышать 2000 символов';
        this.hapticError?.();
        return;
      }

      this.commentError = '';
      this.commentSubmitting = true;

      try {
        const data = await this.api(OrderCommentController.store(this.orderId), {
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ body }),
        });

        const comment = data?.comment;
        if (comment) {
          const existingIndex = this.comments.findIndex(
            (c) => c.id === comment.id
          );
          if (existingIndex >= 0) {
            this.comments[existingIndex] = comment;
          } else {
            this.comments.unshift(comment);
            this.commentsTotal = (this.commentsTotal || 0) + 1;
          }

          if (this.showAllCommentsModal) {
            const modalIndex = this.modalComments.findIndex(
              (c) => c.id === comment.id
            );
            if (modalIndex >= 0) {
              this.modalComments[modalIndex] = comment;
            } else {
              this.modalComments.unshift(comment);
            }
          }

          this.newCommentBody = '';
          this.commentError = '';
          this.hapticSuccess?.();
          this.$dispatch('notify', { message: 'Комментарий добавлен' });
        }
      } catch (e) {
        this.hapticError?.();
        if (e.status === 422) {
          const serverErrors = extractServerErrors(e);
          this.commentError = serverErrors.body || e.message || 'Ошибка валидации комментария';
          this.$dispatch('notify', { message: this.commentError, type: 'error' });
        } else {
          this.$dispatch('notify', { message: e?.message || 'Не удалось добавить комментарий', type: 'error' });
        }
        console.error('Failed to add comment:', e);
      } finally {
        this.commentSubmitting = false;
      }
    },

    async requestDeleteComment(comment) {
      if (!comment || this.deletingCommentId) return;
      const ok = await window.confirmDialog({
        title: 'Удалить комментарий?',
        message: 'Вы уверены, что хотите удалить этот комментарий?',
        confirmText: 'Удалить',
        cancelText: 'Отмена',
        variant: 'danger',
      });
      if (!ok) return;

      await this.deleteComment(comment);
    },

    async deleteComment(comment) {
      if (this.deletingCommentId) return;
      this.deletingCommentId = comment.id;
      try {
        await this.api(OrderCommentController.destroy([this.orderId, comment.id]));
        this.comments = this.comments.filter((c) => c.id !== comment.id);
        this.modalComments = this.modalComments.filter((c) => c.id !== comment.id);
        if (this.commentsTotal > 0) {
          this.commentsTotal--;
        }
        this.hapticSuccess?.();
        this.$dispatch('notify', { message: 'Комментарий удалён' });
      } catch (e) {
        this.hapticError?.();
        this.$dispatch('notify', { message: 'Ошибка удаления', type: 'error' });
      } finally {
        this.deletingCommentId = null;
      }
    },

    getCommentAuthorLabel(comment) {
      if (comment.user?.system_name) {
        return comment.user.system_name;
      }

      return comment.author_type === 'client' ? 'Клиент' : 'Система';
    },

    getActionLabel(type) {
      const labels = {
        create: 'Создан',
        update: 'Обновлён',
        delete: 'Удалён',
        status_change: 'Статус',
        assign: 'Назначение',
        panic: 'Паника',
      };
      return labels[type] || type;
    },

    getActionColor(type) {
      const colors = {
        create: 'text-success',
        update: 'text-warning',
        delete: 'text-error',
        status_change: 'text-primary',
        assign: 'text-secondary',
        panic: 'text-error',
      };
      return colors[type] || 'text-fg-muted';
    },

    getChatUnreadCount() {
      if (this.isCourier) {
        return this.order?.unread_manager_messages_count || (this.order?.has_unread_manager_message ? 1 : 0);
      }
      return this.order?.unread_driver_messages_count || (this.order?.has_unread_driver_message ? 1 : 0);
    },

    getAvailableStatuses(currentStatus) {
      return [
        { value: 'created', label: 'Новый' },
        { value: 'accepted', label: 'Принят' },
        { value: 'assigned', label: 'Назначен' },
        { value: 'at_warehouse', label: 'В пути' },
        { value: 'cancelled', label: 'Отменён' },
        { value: 'completed', label: 'Завершён' },
      ];
    },
  });
}
