import CloudPasswordController from '@/actions/App/Http/Controllers/Auth/CloudPasswordController';

/**
 * Unified Cloud Password Manager Mixin for Alpine.js
 * Handles initial onboarding popup & settings management without code duplication.
 */
export const cloudPasswordMixin = {
  showCloudPasswordModal: false,
  cloudPasswordMode: 'setup', // 'setup' | 'change'
  cloudCurrentPassword: '',
  cloudNewPassword: '',
  cloudNewPasswordConfirm: '',
  cloudPasswordSaving: false,
  cloudPasswordError: '',

  /**
   * Open the cloud password modal in setup or change mode.
   * @param {'setup'|'change'|null} mode
   */
  openCloudPasswordModal(mode = null) {
    const isConfigured = Boolean(
      this.hasCloudPassword ||
      this.user?.has_cloud_password ||
      localStorage.getItem('has_cloud_password') === '1'
    );

    this.cloudPasswordMode = mode || (isConfigured ? 'change' : 'setup');
    this.cloudCurrentPassword = '';
    this.cloudNewPassword = '';
    this.cloudNewPasswordConfirm = '';
    this.cloudPasswordError = '';
    this.showCloudPasswordModal = true;
  },

  /**
   * Close the cloud password modal and clear inputs.
   */
  closeCloudPasswordModal() {
    this.showCloudPasswordModal = false;
    this.cloudPasswordError = '';
    this.cloudCurrentPassword = '';
    this.cloudNewPassword = '';
    this.cloudNewPasswordConfirm = '';
  },

  /**
   * Check if onboarding modal should be displayed on role's initial landing page.
   */
  checkCloudPasswordOnboarding() {
    if (!this.user) return;

    if (this.user.has_cloud_password || localStorage.getItem('has_cloud_password') === '1') {
      return;
    }

    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    const role = this.user.role;

    // Check if user is on their role's initial landing page
    const isInitialPage = (
      (role === 'owner' && (path === '/' || path === '/tma')) ||
      (role === 'manager' && (path === '/tma/manager' || path === '/tma/orders' || path === '/tma' || path === '/')) ||
      (role === 'courier' && (path === '/tma/courier' || path === '/tma/orders' || path === '/tma' || path === '/'))
    );

    if (isInitialPage) {
      this.openCloudPasswordModal('setup');
    }
  },

  /**
   * Submit setup or change cloud password form.
   */
  async submitCloudPassword() {
    this.cloudPasswordError = '';
    const newPwd = (this.cloudNewPassword || '').trim();
    const confirmPwd = (this.cloudNewPasswordConfirm || '').trim();
    const isChange = this.cloudPasswordMode === 'change';

    if (!newPwd || newPwd.length < 4) {
      this.cloudPasswordError = 'Пароль должен содержать минимум 4 символа';
      this.hapticNotification?.('error');
      return;
    }

    if (newPwd !== confirmPwd) {
      this.cloudPasswordError = 'Введенные пароли не совпадают';
      this.hapticNotification?.('error');
      return;
    }

    if (isChange && !this.cloudCurrentPassword) {
      this.cloudPasswordError = 'Введите текущий облачный пароль';
      this.hapticNotification?.('error');
      return;
    }

    this.cloudPasswordSaving = true;

    try {
      const action = isChange ? CloudPasswordController.change() : CloudPasswordController.setup();
      const payload = isChange
        ? {
            current_password: this.cloudCurrentPassword,
            new_password: newPwd,
            new_password_confirmation: confirmPwd,
          }
        : {
            password: newPwd,
            password_confirmation: confirmPwd,
          };

      const res = await this.api(action, {
        body: payload,
      });

      if (this.user) {
        this.user.has_cloud_password = true;
      }
      this.hasCloudPassword = true;

      try {
        localStorage.setItem('has_cloud_password', '1');
      } catch (_) {}

      this.closeCloudPasswordModal();
      this.hapticSuccess?.();
      this.$dispatch('notify', { message: res?.message || 'Облачный пароль успешно сохранен' });
    } catch (e) {
      this.cloudPasswordError = e.message || 'Ошибка сохранения пароля';
      this.hapticNotification?.('error');
    } finally {
      this.cloudPasswordSaving = false;
    }
  },

  // Backwards compatibility aliases
  saveOnboardingCloudPassword() {
    return this.submitCloudPassword();
  },
  dismissOnboardingCloudPassword() {
    this.closeCloudPasswordModal();
  },
  saveCloudPassword() {
    return this.submitCloudPassword();
  },
};
