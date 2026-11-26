import { fetchWithTimeout } from './auth.js';

export const apiMixin = {
  async api(endpoint, options = {}) {
    if (!this.user) {
      throw new Error('Not authenticated');
    }
    let url = endpoint;
    const finalOptions = { ...options };
    if (endpoint && typeof endpoint === 'object' && endpoint.url) {
      url = endpoint.url;
      if (!finalOptions.method && endpoint.method) {
        finalOptions.method = endpoint.method.toUpperCase();
      }
    }
    const isFormData = typeof FormData !== 'undefined' && finalOptions.body instanceof FormData;
    const headers = {
      'Accept': 'application/json',
      ...finalOptions.headers,
    };
    let body = finalOptions.body;
    if (body && !isFormData && typeof body === 'object') {
      body = JSON.stringify(body);
    }
    if (!isFormData && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    const token = this.user?.session_token || localStorage.getItem('tma_token');
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    const response = await fetchWithTimeout(url, { ...finalOptions, body, headers });

    if (response.status === 401 && !finalOptions._retried) {
      await this.authenticate();
      if (this.user?.session_token) {
        return this.api(endpoint, { ...finalOptions, _retried: true });
      }
    }

    if (response.status === 403) {
      let errorData = null;
      try {
        errorData = await response.clone().json().catch(() => ({}));
      } catch (_) {}
      if (errorData?.error === 'Access blocked') {
        console.error('API 403: User access blocked', { endpoint: url, user: this.user });
        this.handlePanic();
        throw new Error('Blocked');
      }
      const text = errorData ? JSON.stringify(errorData) : await response.text().catch(() => '');
      console.warn('API 403 Forbidden', { endpoint: url, status: response.status, text, user: this.user });
      const err = new Error(errorData?.error || errorData?.message || 'Forbidden');
      err.status = 403;
      err.body = text;
      throw err;
    }

    if (!response.ok) {
      let errorMessage = 'API error: ' + response.status;
      let text = '';
      try {
        const data = await response.json();
        text = JSON.stringify(data);
        errorMessage = data?.message || data?.error || errorMessage;
      } catch {
        text = await response.text().catch(() => '');
      }
      console.error('API error', { endpoint: url, status: response.status, text });
      const err = new Error(errorMessage);
      err.status = response.status;
      err.body = text;
      throw err;
    }

    return response.json();
  },

  async apiFetch(endpoint, options = {}) {
    let url = endpoint;
    const finalOptions = { ...options };
    if (endpoint && typeof endpoint === 'object' && endpoint.url) {
      url = endpoint.url;
      if (!finalOptions.method && endpoint.method) {
        finalOptions.method = endpoint.method.toUpperCase();
      }
    }
    const isFormData = typeof FormData !== 'undefined' && finalOptions.body instanceof FormData;
    const headers = {
      'Accept': 'application/json',
      ...finalOptions.headers,
    };
    let body = finalOptions.body;
    if (body && !isFormData && typeof body === 'object') {
      body = JSON.stringify(body);
    }
    if (body && !isFormData && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    const token = this.user?.session_token || localStorage.getItem('tma_token');
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    const response = await fetchWithTimeout(url, { ...finalOptions, body, headers });
    if (response.status === 401 && !finalOptions._retried) {
      await this.authenticate();
      if (this.user?.session_token) {
        return this.apiFetch(endpoint, { ...finalOptions, _retried: true });
      }
    }
    return response;
  },
};
