export function formatDateIso(date) {
  if (!date) return '';
  const d = typeof date === 'string' ? new Date(date) : date;
  if (isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function formatDateRu(isoStr) {
  if (!isoStr) return '';
  const parts = String(isoStr).split('-');
  if (parts.length !== 3) return isoStr;
  return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

if (typeof window !== 'undefined') {
  window.formatDateRu = formatDateRu;
  window.formatDateIso = formatDateIso;
}

const MONTH_NAMES = [
  'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
  'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];

export function createDateRangeMixin(options = {}) {
  const now = new Date();

  return {
    showDatePicker: false,
    customFrom: null,
    customTo: null,
    pickerMonth: now.getMonth(),
    pickerYear: now.getFullYear(),
    pickerFrom: null,
    pickerTo: null,

    formatDateRu(isoStr) {
      return formatDateRu(isoStr);
    },

    formatDateIso(date) {
      return formatDateIso(date);
    },

    get isCustom() {
      return Boolean(this.customFrom && this.customTo);
    },

    get customDaysCount() {
      if (!this.customFrom || !this.customTo) return 0;
      const d1 = new Date(this.customFrom);
      const d2 = new Date(this.customTo);
      return Math.max(1, Math.round(Math.abs(d2 - d1) / 86400000) + 1);
    },

    get customRangeText() {
      if (!this.customFrom || !this.customTo) return '';
      return `${formatDateRu(this.customFrom)} — ${formatDateRu(this.customTo)}`;
    },

    get pickerMonthName() {
      return MONTH_NAMES[this.pickerMonth] || '';
    },

    get calendarDays() {
      const year = this.pickerYear;
      const month = this.pickerMonth;
      const todayStr = formatDateIso(new Date());

      const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7;
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const daysInPrevMonth = new Date(year, month, 0).getDate();

      const days = [];

      // Previous month padding
      for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const prevDate = new Date(year, month - 1, d);
        const iso = formatDateIso(prevDate);
        days.push({
          day: d,
          date: iso,
          isCurrentMonth: false,
          isToday: iso === todayStr,
        });
      }

      // Current month days
      for (let d = 1; d <= daysInMonth; d++) {
        const curDate = new Date(year, month, d);
        const iso = formatDateIso(curDate);
        days.push({
          day: d,
          date: iso,
          isCurrentMonth: true,
          isToday: iso === todayStr,
        });
      }

      // Next month padding to fill full weeks
      const remaining = (7 - (days.length % 7)) % 7;
      for (let d = 1; d <= remaining; d++) {
        const nextDate = new Date(year, month + 1, d);
        const iso = formatDateIso(nextDate);
        days.push({
          day: d,
          date: iso,
          isCurrentMonth: false,
          isToday: iso === todayStr,
        });
      }

      return days;
    },

    openDatePicker() {
      if (this.customFrom) {
        this.pickerFrom = this.customFrom;
        this.pickerTo = this.customTo || this.customFrom;
        const parsed = new Date(this.customFrom);
        if (!isNaN(parsed.getTime())) {
          this.pickerYear = parsed.getFullYear();
          this.pickerMonth = parsed.getMonth();
        }
      } else {
        const todayIso = formatDateIso(new Date());
        this.pickerFrom = todayIso;
        this.pickerTo = todayIso;
        const cur = new Date();
        this.pickerYear = cur.getFullYear();
        this.pickerMonth = cur.getMonth();
      }
      this.showDatePicker = true;
    },

    closeDatePicker() {
      this.showDatePicker = false;
    },

    prevMonth() {
      if (this.pickerMonth === 0) {
        this.pickerMonth = 11;
        this.pickerYear--;
      } else {
        this.pickerMonth--;
      }
    },

    nextMonth() {
      if (this.pickerMonth === 11) {
        this.pickerMonth = 0;
        this.pickerYear++;
      } else {
        this.pickerMonth++;
      }
    },

    selectDate(dateStr) {
      if (!this.pickerFrom || (this.pickerFrom && this.pickerTo)) {
        this.pickerFrom = dateStr;
        this.pickerTo = null;
      } else {
        if (dateStr < this.pickerFrom) {
          this.pickerTo = this.pickerFrom;
          this.pickerFrom = dateStr;
        } else {
          this.pickerTo = dateStr;
        }
      }
    },

    applyPreset(type) {
      const now = new Date();
      let fromDate, toDate;

      switch (type) {
        case 'today':
          fromDate = new Date(now);
          toDate = new Date(now);
          break;
        case 'yesterday':
          fromDate = new Date(now);
          fromDate.setDate(now.getDate() - 1);
          toDate = new Date(fromDate);
          break;
        case '7days':
          toDate = new Date(now);
          fromDate = new Date(now);
          fromDate.setDate(now.getDate() - 6);
          break;
        case '30days':
          toDate = new Date(now);
          fromDate = new Date(now);
          fromDate.setDate(now.getDate() - 29);
          break;
        case 'thisMonth':
          fromDate = new Date(now.getFullYear(), now.getMonth(), 1);
          toDate = new Date(now);
          break;
        case 'lastMonth':
          fromDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
          toDate = new Date(now.getFullYear(), now.getMonth(), 0);
          break;
        default:
          return;
      }

      this.pickerFrom = formatDateIso(fromDate);
      this.pickerTo = formatDateIso(toDate);
      this.pickerYear = fromDate.getFullYear();
      this.pickerMonth = fromDate.getMonth();
    },

    async applyPicker() {
      if (!this.pickerFrom) return;
      if (!this.pickerTo) {
        this.pickerTo = this.pickerFrom;
      }

      if (this.pickerFrom > this.pickerTo) {
        const tmp = this.pickerFrom;
        this.pickerFrom = this.pickerTo;
        this.pickerTo = tmp;
      }

      this.customFrom = this.pickerFrom;
      this.customTo = this.pickerTo;
      this.showDatePicker = false;

      if (options.onApply) {
        await options.onApply.call(this, this.customFrom, this.customTo);
      }
    },

    async clearCustomRange() {
      this.customFrom = null;
      this.customTo = null;
      this.pickerFrom = null;
      this.pickerTo = null;

      if (options.onClear) {
        await options.onClear.call(this);
      }
    },

    onFromInputChange(e) {
      const val = e.target.value;
      this.pickerFrom = val;
      if (val) {
        const d = new Date(val);
        if (!isNaN(d.getTime())) {
          this.pickerYear = d.getFullYear();
          this.pickerMonth = d.getMonth();
        }
      }
    },

    onToInputChange(e) {
      this.pickerTo = e.target.value;
    },
  };
}
