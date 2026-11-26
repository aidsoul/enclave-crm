export function signaturePad({ minPoints = 12, strokeColor = '#0f172a', strokeWidth = 2.5 } = {}) {
  return {
    isDrawing: false,
    strokes: [],
    currentStroke: [],
    pointsCount: 0,
    hasSignature: false,
    submitting: false,
    canvas: null,
    ctx: null,
    dpr: 1,
    cssWidth: 0,
    cssHeight: 0,

    initPad() {
      this.canvas = this.$refs.signatureCanvas;
      if (!this.canvas) return;

      this.ctx = this.canvas.getContext('2d', { willReadFrequently: false });
      this.dpr = Math.max(window.devicePixelRatio || 1, 1);

      this.setupCanvasSize();
      this.bindResizeObserver();

      try {
        window.Telegram?.WebApp?.disableVerticalSwipes?.();
      } catch (_) {}
    },

    destroyPad() {
      try {
        window.Telegram?.WebApp?.enableVerticalSwipes?.();
      } catch (_) {}
      if (this.resizeObserver) {
        this.resizeObserver.disconnect();
      }
    },

    setupCanvasSize() {
      if (!this.canvas || !this.ctx) return;
      const rect = this.canvas.getBoundingClientRect();
      const width = Math.floor(rect.width) || 300;
      const height = Math.floor(rect.height) || 160;

      if (this.cssWidth === width && this.cssHeight === height) return;

      this.cssWidth = width;
      this.cssHeight = height;

      this.canvas.width = Math.floor(width * this.dpr);
      this.canvas.height = Math.floor(height * this.dpr);

      this.redrawAll();
    },

    bindResizeObserver() {
      if (typeof ResizeObserver === 'function' && this.canvas) {
        this.resizeObserver = new ResizeObserver(() => {
          this.setupCanvasSize();
        });
        this.resizeObserver.observe(this.canvas);
      }
    },

    configureContext() {
      if (!this.ctx) return;
      this.ctx.setTransform(1, 0, 0, 1, 0, 0);
      this.ctx.scale(this.dpr, this.dpr);
      this.ctx.strokeStyle = strokeColor;
      this.ctx.lineWidth = strokeWidth;
      this.ctx.lineCap = 'round';
      this.ctx.lineJoin = 'round';
    },

    getPoint(e) {
      const rect = this.canvas.getBoundingClientRect();
      return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
      };
    },

    handlePointerDown(e) {
      if (e.button !== undefined && e.button !== 0) return;
      if (!this.canvas || !this.ctx || this.submitting) return;

      try {
        this.canvas.setPointerCapture?.(e.pointerId);
      } catch (_) {}

      this.isDrawing = true;
      const pt = this.getPoint(e);
      this.currentStroke = [pt];
      this.pointsCount += 1;

      this.configureContext();
      this.ctx.beginPath();
      this.ctx.moveTo(pt.x, pt.y);

      try {
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
      } catch (_) {}
    },

    handlePointerMove(e) {
      if (!this.isDrawing || !this.ctx) return;
      const pt = this.getPoint(e);

      const lastPt = this.currentStroke[this.currentStroke.length - 1];
      if (lastPt) {
        const dist = Math.hypot(pt.x - lastPt.x, pt.y - lastPt.y);
        if (dist < 1.5) return;
      }

      this.currentStroke.push(pt);
      this.pointsCount += 1;

      if (this.currentStroke.length >= 3) {
        const len = this.currentStroke.length;
        const p1 = this.currentStroke[len - 2];
        const p2 = this.currentStroke[len - 1];
        const midX = (p1.x + p2.x) / 2;
        const midY = (p1.y + p2.y) / 2;
        this.ctx.quadraticCurveTo(p1.x, p1.y, midX, midY);
        this.ctx.stroke();
      } else {
        this.ctx.lineTo(pt.x, pt.y);
        this.ctx.stroke();
      }
    },

    handlePointerUp(e) {
      if (!this.isDrawing) return;
      this.isDrawing = false;

      try {
        this.canvas.releasePointerCapture?.(e.pointerId);
      } catch (_) {}

      if (this.currentStroke.length > 0) {
        if (this.currentStroke.length === 1) {
          const pt = this.currentStroke[0];
          this.configureContext();
          this.ctx.beginPath();
          this.ctx.arc(pt.x, pt.y, strokeWidth / 2, 0, Math.PI * 2);
          this.ctx.fillStyle = strokeColor;
          this.ctx.fill();
        }
        this.strokes.push([...this.currentStroke]);
      }
      this.currentStroke = [];
      this.hasSignature = this.hasValidSignature();
    },

    hasValidSignature() {
      if (this.pointsCount < minPoints || this.strokes.length === 0) {
        return false;
      }
      return this.hasValidBoundingBox();
    },

    hasValidBoundingBox() {
      if (this.strokes.length === 0) return false;
      let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      for (const stroke of this.strokes) {
        for (const pt of stroke) {
          if (pt.x < minX) minX = pt.x;
          if (pt.y < minY) minY = pt.y;
          if (pt.x > maxX) maxX = pt.x;
          if (pt.y > maxY) maxY = pt.y;
        }
      }
      return (maxX - minX) >= 15 || (maxY - minY) >= 15;
    },

    clear() {
      this.strokes = [];
      this.currentStroke = [];
      this.pointsCount = 0;
      this.hasSignature = false;
      this.redrawAll();
      try {
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
      } catch (_) {}
    },

    undo() {
      if (this.strokes.length === 0) return;
      this.strokes.pop();
      this.pointsCount = this.strokes.reduce((sum, s) => sum + s.length, 0);
      this.hasSignature = this.hasValidSignature();
      this.redrawAll();
      try {
        window.Telegram?.WebApp?.HapticFeedback?.impactOccurred?.('light');
      } catch (_) {}
    },

    redrawAll() {
      if (!this.canvas || !this.ctx) return;
      this.ctx.setTransform(1, 0, 0, 1, 0, 0);
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

      if (this.strokes.length === 0) return;

      this.configureContext();
      for (const stroke of this.strokes) {
        if (stroke.length === 0) continue;
        if (stroke.length === 1) {
          this.ctx.beginPath();
          this.ctx.arc(stroke[0].x, stroke[0].y, strokeWidth / 2, 0, Math.PI * 2);
          this.ctx.fillStyle = strokeColor;
          this.ctx.fill();
          continue;
        }

        this.ctx.beginPath();
        this.ctx.moveTo(stroke[0].x, stroke[0].y);
        for (let i = 1; i < stroke.length - 1; i++) {
          const midX = (stroke[i].x + stroke[i + 1].x) / 2;
          const midY = (stroke[i].y + stroke[i + 1].y) / 2;
          this.ctx.quadraticCurveTo(stroke[i].x, stroke[i].y, midX, midY);
        }
        const last = stroke[stroke.length - 1];
        this.ctx.lineTo(last.x, last.y);
        this.ctx.stroke();
      }
    },

    async getBlob(type = 'image/png') {
      if (!this.canvas || !this.hasSignature) {
        throw new Error('Подпись отсутствует или слишком короткая');
      }

      const exportCanvas = document.createElement('canvas');
      exportCanvas.width = this.canvas.width;
      exportCanvas.height = this.canvas.height;
      const exportCtx = exportCanvas.getContext('2d');

      exportCtx.fillStyle = '#ffffff';
      exportCtx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
      exportCtx.drawImage(this.canvas, 0, 0);

      return new Promise((resolve, reject) => {
        exportCanvas.toBlob((blob) => {
          exportCanvas.width = 0;
          exportCanvas.height = 0;
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('Не удалось сформировать изображение подписи'));
          }
        }, type);
      });
    },
  };
}

if (typeof window !== 'undefined') {
  window.signaturePad = signaturePad;
}

