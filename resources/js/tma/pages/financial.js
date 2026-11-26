import { baseInit, withTmaBase } from '../shared.js';
import { createDateRangeMixin } from '../date-range-picker.js';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import {
  Chart,
  LineController,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Tooltip,
  Filler,
} from 'chart.js';

Chart.register(
  LineController,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Tooltip,
  Filler
);

const METRIC_LABELS = {
  total_revenue: 'Общий оборот',
  total_purchase: 'Закуп',
  courier_payouts: 'Выплаты',
  net_profit: 'Чистая прибыль',
};

const METRIC_COLORS = {
  total_revenue: '#0d9488',
  total_purchase: '#f59e0b',
  courier_payouts: '#f97316',
  net_profit: '#22c55e',
};

export function createPage(user = null, retentionDays = 0, botUsername = '') {
  const params = new URLSearchParams(window.location.search);
  const initialMetric = params.get('metric') || 'total_revenue';
  const initialDays = parseInt(params.get('days') || '30', 10);
  const initialFrom = params.get('from');
  const initialTo = params.get('to');

  return withTmaBase({
    user,
    retentionDays,
    metric: initialMetric,
    days: initialDays,
    labels: [],
    values: [],
    _loadToken: 0,

    get metricLabel() {
      return METRIC_LABELS[this.metric] || 'Общий оборот';
    },

    get total() {
      return this.values.reduce((sum, v) => sum + v, 0);
    },

    get trendText() {
      if (this.values.length < 2) return '';
      const recent = this.values.slice(-3);
      const older = this.values.slice(0, 3);
      const recentAvg = recent.reduce((a, b) => a + b, 0) / recent.length;
      const olderAvg = older.reduce((a, b) => a + b, 0) / older.length;
      if (olderAvg === 0) return recentAvg > 0 ? 'Рост' : '';
      const pct = ((recentAvg - olderAvg) / olderAvg) * 100;
      if (Math.abs(pct) < 1) return 'Без изменений';
      return pct > 0 ? `+${pct.toFixed(1)}%` : `${pct.toFixed(1)}%`;
    },

    get trendClass() {
      if (!this.trendText) return 'text-fg-muted';
      if (this.trendText.startsWith('+')) return 'text-success';
      if (this.trendText.startsWith('-')) return 'text-error';
      return 'text-fg-muted';
    },

    get reversed() {
      return [...this.items].reverse();
    },

    get items() {
      return this.labels.map((label, i) => ({ label, value: this.values[i] || 0 }));
    },

    async init() {
      if (initialFrom && initialTo) {
        this.customFrom = initialFrom;
        this.customTo = initialTo;
        this.pickerFrom = initialFrom;
        this.pickerTo = initialTo;
      }
      const ok = await baseInit.call(this);
      if (!ok || this.user?.role !== 'owner') return;
      await this.loadChart();
    },

    async loadChart() {
      const token = ++this._loadToken;
      try {
        const query = { metric: this.metric };
        if (this.isCustom && this.customFrom && this.customTo) {
          query.from = this.customFrom;
          query.to = this.customTo;
        } else {
          query.days = this.days;
        }
        const data = await this.api(DashboardController.financialHistory({ query }));
        if (token !== this._loadToken) return;
        this.labels = data.labels || [];
        this.values = data.values || [];
        this.$nextTick(() => this.renderChart());
      } catch (e) {
        console.error('Failed to load financial history:', e);
      }
    },

    renderChart() {
      const canvas = document.getElementById('financialChart');
      if (!canvas || !canvas.isConnected) return;

      const color = METRIC_COLORS[this.metric] || '#0d9488';
      const labels = [...this.labels];
      const values = [...this.values];

      if (canvas.__chart) {
        const chart = canvas.__chart;
        chart.data.labels = labels;
        const ds = chart.data.datasets[0];
        ds.label = this.metricLabel;
        ds.data = values;
        ds.borderColor = color;
        ds.backgroundColor = color + '20';
        ds.pointBackgroundColor = color;
        chart.update();
        return;
      }

      canvas.__chart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: this.metricLabel,
            data: values,
            borderColor: color,
            backgroundColor: color + '20',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointBackgroundColor: color,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => this.formatPrice(ctx.raw),
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: {
                color: '#9ca3af',
                font: { size: 10 },
                maxTicksLimit: 7,
              },
            },
            y: {
              grid: { color: '#374151' },
              ticks: {
                color: '#9ca3af',
                font: { size: 10 },
                callback: (v) => (v / 1000) + 'k',
              },
            },
          },
        },
      });
    },

    async setDays(value) {
      this.customFrom = null;
      this.customTo = null;
      this.days = value;
      await this.loadChart();
    },

    async setMetric(value) {
      this.metric = value;
      await this.loadChart();
    },
  },
  createDateRangeMixin({
    async onApply(from, to) {
      await this.loadChart();
    },
    async onClear() {
      this.days = 30;
      await this.loadChart();
    },
  }));
}
