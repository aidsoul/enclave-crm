export function imageZoomViewer() {
  return {
    zoom: 1,
    panX: 0,
    panY: 0,
    isDragging: false,
    dragStartX: 0,
    dragStartY: 0,
    touchStartDist: 0,
    touchStartZoom: 1,
    touchLastTapTime: 0,

    init() {
      this.$watch('showProofPhotoModal', (open) => {
        if (!open) this.reset();
      });
      this.$watch('proofPhotoModalUrl', () => {
        this.reset();
      });
    },

    reset() {
      this.zoom = 1;
      this.panX = 0;
      this.panY = 0;
      this.isDragging = false;
      this.touchStartDist = 0;
    },

    zoomIn(delta = 0.3) {
      this.setZoom(this.zoom + delta);
    },

    zoomOut(delta = 0.3) {
      this.setZoom(this.zoom - delta);
    },

    setZoom(val) {
      const clamped = Math.min(Math.max(Number(val.toFixed(2)), 1), 5);
      this.zoom = clamped;
      if (this.zoom <= 1) {
        this.panX = 0;
        this.panY = 0;
      } else {
        this.clampPan();
      }
    },

    clampPan() {
      const container = this.$refs.zoomContainer;
      if (!container) return;
      const w = container.clientWidth || 300;
      const h = container.clientHeight || 300;
      const maxPanX = Math.max(0, (w * (this.zoom - 1)) / 2);
      const maxPanY = Math.max(0, (h * (this.zoom - 1)) / 2);
      this.panX = Math.min(Math.max(this.panX, -maxPanX), maxPanX);
      this.panY = Math.min(Math.max(this.panY, -maxPanY), maxPanY);
    },

    handleKeydown(e) {
      if (!this.showProofPhotoModal) return;
      const isCtrl = e.ctrlKey || e.metaKey;

      if (isCtrl && (e.key === '+' || e.key === '=' || e.code === 'NumpadAdd')) {
        e.preventDefault();
        e.stopPropagation();
        this.zoomIn();
      } else if (isCtrl && (e.key === '-' || e.key === '_' || e.code === 'NumpadSubtract')) {
        e.preventDefault();
        e.stopPropagation();
        this.zoomOut();
      } else if (isCtrl && (e.key === '0' || e.code === 'Numpad0')) {
        e.preventDefault();
        e.stopPropagation();
        this.reset();
      } else if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        this.zoomIn();
      } else if (e.key === '-') {
        e.preventDefault();
        this.zoomOut();
      } else if (e.key === '0') {
        e.preventDefault();
        this.reset();
      }
    },

    handleWheel(e) {
      e.preventDefault();
      e.stopPropagation();
      if (e.deltaY < 0) {
        this.zoomIn(0.2);
      } else {
        this.zoomOut(0.2);
      }
    },

    handleTouchStart(e) {
      if (e.touches.length === 2) {
        const t1 = e.touches[0];
        const t2 = e.touches[1];
        this.touchStartDist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
        this.touchStartZoom = this.zoom;
        this.isDragging = false;
      } else if (e.touches.length === 1) {
        const now = Date.now();
        if (now - this.touchLastTapTime < 320) {
          e.preventDefault();
          e.stopPropagation();
          if (this.zoom > 1) {
            this.reset();
          } else {
            this.setZoom(2.5);
          }
          this.touchLastTapTime = 0;
          return;
        }
        this.touchLastTapTime = now;

        if (this.zoom > 1) {
          this.isDragging = true;
          this.dragStartX = e.touches[0].clientX - this.panX;
          this.dragStartY = e.touches[0].clientY - this.panY;
        }
      }
    },

    handleTouchMove(e) {
      if (e.touches.length === 2 && this.touchStartDist > 0) {
        e.preventDefault();
        e.stopPropagation();
        const t1 = e.touches[0];
        const t2 = e.touches[1];
        const curDist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
        const scale = curDist / this.touchStartDist;
        this.setZoom(this.touchStartZoom * scale);
      } else if (e.touches.length === 1 && this.isDragging && this.zoom > 1) {
        e.preventDefault();
        e.stopPropagation();
        this.panX = e.touches[0].clientX - this.dragStartX;
        this.panY = e.touches[0].clientY - this.dragStartY;
        this.clampPan();
      }
    },

    handleTouchEnd(e) {
      if (e.touches.length < 2) {
        this.touchStartDist = 0;
      }
      if (e.touches.length === 0) {
        this.isDragging = false;
        if (this.zoom <= 1) {
          this.panX = 0;
          this.panY = 0;
        }
      }
    },

    handleMouseDown(e) {
      if (this.zoom > 1 && e.button === 0) {
        e.preventDefault();
        this.isDragging = true;
        this.dragStartX = e.clientX - this.panX;
        this.dragStartY = e.clientY - this.panY;
      }
    },

    handleMouseMove(e) {
      if (this.isDragging && this.zoom > 1) {
        e.preventDefault();
        this.panX = e.clientX - this.dragStartX;
        this.panY = e.clientY - this.dragStartY;
        this.clampPan();
      }
    },

    handleMouseUp() {
      this.isDragging = false;
    },

    handleDoubleClick() {
      if (this.zoom > 1) {
        this.reset();
      } else {
        this.setZoom(2.5);
      }
    },

    async downloadPhoto(url) {
      const targetUrl = url || this.$root?.querySelector('img')?.getAttribute('src');
      if (!targetUrl) return;

      const fullUrl = new URL(targetUrl, window.location.origin).href;
      const filename = targetUrl.split('/').pop()?.split('?')[0] || `delivery_proof_${Date.now()}.jpg`;
      const downloadUrl = fullUrl + (fullUrl.includes('?') ? '&' : '?') + 'download=1';

      const tg = window.Telegram?.WebApp;

      if (tg && typeof tg.downloadFile === 'function') {
        try {
          tg.downloadFile({ url: fullUrl, file_name: filename }, (accepted) => {
            if (accepted) {
              tg.HapticFeedback?.notificationOccurred?.('success');
            }
          });
          return;
        } catch (_) {}
      }

      if (tg && typeof tg.openLink === 'function') {
        try {
          tg.HapticFeedback?.impactOccurred?.('light');
          tg.openLink(downloadUrl);
          return;
        } catch (_) {}
      }

      try {
        const res = await fetch(downloadUrl);
        const blob = await res.blob();
        const blobUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 15000);
      } catch (_) {
        window.location.href = downloadUrl;
      }
    },

    get imageStyle() {
      const transform = `translate3d(${this.panX}px, ${this.panY}px, 0) scale(${this.zoom})`;
      const cursor = this.zoom > 1 ? (this.isDragging ? 'grabbing' : 'grab') : 'zoom-in';
      const transition = this.isDragging ? 'none' : 'transform 140ms cubic-bezier(0.2, 0, 0, 1)';
      return `transform: ${transform}; cursor: ${cursor}; transition: ${transition}; transform-origin: center center; user-select: none; -webkit-user-drag: none; touch-action: none;`;
    },

    get zoomPercent() {
      return Math.round(this.zoom * 100) + '%';
    }
  };
}

if (typeof window !== 'undefined') {
  window.imageZoomViewer = imageZoomViewer;
}

