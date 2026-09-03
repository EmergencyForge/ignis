/**
 * ıgnıs UI — Color-Picker
 *
 * Drop-in für `<input type="color">`. Auto-Init via `data-ignis-colorpicker`:
 *
 *   <input type="text" data-ignis-colorpicker name="role_color" value="#ff4d00">
 *
 * Optionale Attribute:
 *   data-presets='["#ff4d00","#5783cf",...]'  → Preset-Swatch-Reihe
 *   data-allow-clear="true"                   → ×-Knopf zum Löschen
 *   data-format="hex"                          → derzeit nur HEX (Default)
 *
 * Events:
 *   `ignis:color-change`  detail: { hex, rgb: {r,g,b}, hsv: {h,s,v} }
 */

const INSTANCES = new WeakMap();

const DEFAULT_PRESETS = [
  '#FF4D00', '#FF6A33', '#CC3D00',  // Brand-Orange + Tints
  '#4A6FA5', '#3A7D44', '#B03A3A',  // Bootstrap-Equivalents
  '#C49A2A', '#5BB8CC', '#7C3AED',
  '#0F0F0F', '#2A2A2A', '#FFFFFF',
];

// ── HSV ↔ RGB ↔ HEX ─────────────────────────────────────────────

function hexToRgb(hex) {
  const m = /^#?([0-9a-f]{6}|[0-9a-f]{3})$/i.exec(hex);
  if (!m) return null;
  let h = m[1];
  if (h.length === 3) h = h.split('').map((c) => c + c).join('');
  return {
    r: parseInt(h.slice(0, 2), 16),
    g: parseInt(h.slice(2, 4), 16),
    b: parseInt(h.slice(4, 6), 16),
  };
}

function rgbToHex({ r, g, b }) {
  const toHex = (n) => Math.round(n).toString(16).padStart(2, '0');
  return ('#' + toHex(r) + toHex(g) + toHex(b)).toUpperCase();
}

function rgbToHsv({ r, g, b }) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const d = max - min;
  let h = 0;
  if (d !== 0) {
    if (max === r) h = ((g - b) / d) % 6;
    else if (max === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
    h *= 60;
    if (h < 0) h += 360;
  }
  const s = max === 0 ? 0 : d / max;
  const v = max;
  return { h, s, v };
}

function hsvToRgb({ h, s, v }) {
  const c = v * s;
  const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
  const m = v - c;
  let r = 0, g = 0, b = 0;
  if (h < 60)       { r = c; g = x; }
  else if (h < 120) { r = x; g = c; }
  else if (h < 180) { g = c; b = x; }
  else if (h < 240) { g = x; b = c; }
  else if (h < 300) { r = x; b = c; }
  else              { r = c; b = x; }
  return { r: (r + m) * 255, g: (g + m) * 255, b: (b + m) * 255 };
}

function hueToHex(h) {
  return rgbToHex(hsvToRgb({ h, s: 1, v: 1 }));
}

// ── Colorpicker ─────────────────────────────────────────────────

class Colorpicker {
  constructor(input) {
    this.input = input;
    this.name = input.name || '';
    this.allowClear = input.dataset.allowClear === 'true';

    let presets = DEFAULT_PRESETS;
    if (input.dataset.presets) {
      try { presets = JSON.parse(input.dataset.presets); } catch (e) {}
    }
    this.presets = presets;

    const initial = (input.value || '#FF4D00').trim();
    const rgb = hexToRgb(initial) || { r: 255, g: 77, b: 0 };
    this.hsv = rgbToHsv(rgb);
    this.hex = rgbToHex(rgb);

    this.isOpen = false;
    this._build();
    this._render();
    this._bindEvents();
  }

  _build() {
    this.root = document.createElement('div');
    this.root.className = 'ignis-colorpicker';

    this.trigger = document.createElement('button');
    this.trigger.type = 'button';
    this.trigger.className = 'ignis-colorpicker__trigger';
    this.trigger.setAttribute('aria-haspopup', 'dialog');
    this.trigger.setAttribute('aria-expanded', 'false');

    this.swatch = document.createElement('span');
    this.swatch.className = 'ignis-colorpicker__swatch';
    this.swatch.setAttribute('aria-hidden', 'true');

    this.readout = document.createElement('span');
    this.readout.className = 'ignis-colorpicker__hex-readout';

    this.trigger.appendChild(this.swatch);
    this.trigger.appendChild(this.readout);

    this.panel = document.createElement('div');
    this.panel.className = 'ignis-colorpicker__panel';
    this.panel.setAttribute('role', 'dialog');
    this.panel.setAttribute('aria-label', 'Farbauswahl');

    this.svPlane = document.createElement('div');
    this.svPlane.className = 'ignis-colorpicker__sv';
    this.svPlane.setAttribute('role', 'application');
    this.svPlane.setAttribute('aria-label', 'Sättigung und Helligkeit');
    this.svPointer = document.createElement('span');
    this.svPointer.className = 'ignis-colorpicker__sv-pointer';
    this.svPlane.appendChild(this.svPointer);

    this.hueBar = document.createElement('div');
    this.hueBar.className = 'ignis-colorpicker__hue';
    this.hueBar.setAttribute('role', 'slider');
    this.hueBar.setAttribute('aria-label', 'Farbton');
    this.huePointer = document.createElement('span');
    this.huePointer.className = 'ignis-colorpicker__hue-pointer';
    this.hueBar.appendChild(this.huePointer);

    const hexWrap = document.createElement('div');
    hexWrap.className = 'ignis-colorpicker__hex';
    const hexLabel = document.createElement('label');
    hexLabel.textContent = 'Hex';
    this.hexInput = document.createElement('input');
    this.hexInput.type = 'text';
    this.hexInput.maxLength = 7;
    this.hexInput.spellcheck = false;
    hexWrap.appendChild(hexLabel);
    hexWrap.appendChild(this.hexInput);

    this.presetGrid = document.createElement('div');
    this.presetGrid.className = 'ignis-colorpicker__presets';
    this.presets.forEach((c) => {
      const sw = document.createElement('button');
      sw.type = 'button';
      sw.className = 'ignis-colorpicker__preset';
      sw.style.background = c;
      sw.dataset.color = c;
      sw.setAttribute('aria-label', c);
      this.presetGrid.appendChild(sw);
    });

    this.panel.appendChild(this.svPlane);
    this.panel.appendChild(this.hueBar);
    this.panel.appendChild(hexWrap);
    this.panel.appendChild(this.presetGrid);

    this.root.appendChild(this.trigger);
    this.root.appendChild(this.panel);

    // Hidden input synchronisiert mit Original (das versteckt wird).
    this.input.type = 'hidden';
    this.input.parentNode.insertBefore(this.root, this.input);
    this.root.appendChild(this.input);
  }

  _bindEvents() {
    this.trigger.addEventListener('click', (e) => {
      e.preventDefault();
      this.isOpen ? this._close() : this._open();
    });

    this._svDrag = this._svDrag.bind(this);
    this._svDragEnd = this._svDragEnd.bind(this);
    this.svPlane.addEventListener('mousedown', (e) => {
      this._svDrag(e);
      document.addEventListener('mousemove', this._svDrag);
      document.addEventListener('mouseup', this._svDragEnd);
    });

    this._hueDrag = this._hueDrag.bind(this);
    this._hueDragEnd = this._hueDragEnd.bind(this);
    this.hueBar.addEventListener('mousedown', (e) => {
      this._hueDrag(e);
      document.addEventListener('mousemove', this._hueDrag);
      document.addEventListener('mouseup', this._hueDragEnd);
    });

    this.hexInput.addEventListener('input', () => {
      const v = this.hexInput.value.trim();
      const rgb = hexToRgb(v.startsWith('#') ? v : '#' + v);
      if (rgb) {
        this.hsv = rgbToHsv(rgb);
        this.hex = rgbToHex(rgb);
        this._render(true);
      }
    });

    this.hexInput.addEventListener('blur', () => {
      this.hexInput.value = this.hex;
    });

    this.presetGrid.addEventListener('click', (e) => {
      const btn = e.target.closest('.ignis-colorpicker__preset');
      if (!btn) return;
      e.preventDefault();
      const rgb = hexToRgb(btn.dataset.color);
      if (!rgb) return;
      this.hsv = rgbToHsv(rgb);
      this.hex = rgbToHex(rgb);
      this._render();
    });

    this._outsideHandler = (e) => {
      if (!this.root.contains(e.target)) this._close();
    };
    document.addEventListener('mousedown', this._outsideHandler);

    document.addEventListener('keydown', (e) => {
      if (this.isOpen && e.key === 'Escape') {
        this._close();
        this.trigger.focus();
      }
    });
  }

  _svDrag(e) {
    e.preventDefault();
    const rect = this.svPlane.getBoundingClientRect();
    const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
    const y = Math.max(0, Math.min(rect.height, e.clientY - rect.top));
    this.hsv.s = x / rect.width;
    this.hsv.v = 1 - y / rect.height;
    this.hex = rgbToHex(hsvToRgb(this.hsv));
    this._render();
  }
  _svDragEnd() {
    document.removeEventListener('mousemove', this._svDrag);
    document.removeEventListener('mouseup', this._svDragEnd);
  }

  _hueDrag(e) {
    e.preventDefault();
    const rect = this.hueBar.getBoundingClientRect();
    const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
    this.hsv.h = (x / rect.width) * 360;
    this.hex = rgbToHex(hsvToRgb(this.hsv));
    this._render();
  }
  _hueDragEnd() {
    document.removeEventListener('mousemove', this._hueDrag);
    document.removeEventListener('mouseup', this._hueDragEnd);
  }

  _open() {
    this.isOpen = true;
    this.root.classList.add('is-open');
    this.trigger.setAttribute('aria-expanded', 'true');
  }

  _close() {
    if (!this.isOpen) return;
    this.isOpen = false;
    this.root.classList.remove('is-open');
    this.trigger.setAttribute('aria-expanded', 'false');
  }

  _render(skipHexInput = false) {
    const hueHex = hueToHex(this.hsv.h);
    this.svPlane.style.setProperty('--ignis-cp-hue', hueHex);
    this.svPlane.style.backgroundImage =
      'linear-gradient(to top, #000, transparent), ' +
      'linear-gradient(to right, #fff, ' + hueHex + ')';
    this.hueBar.style.setProperty('--ignis-cp-hue', hueHex);
    this.huePointer.style.background = hueHex;

    this.svPointer.style.left = (this.hsv.s * 100) + '%';
    this.svPointer.style.top = ((1 - this.hsv.v) * 100) + '%';
    this.huePointer.style.left = ((this.hsv.h / 360) * 100) + '%';

    this.swatch.style.setProperty('--ignis-cp-color', this.hex);
    this.readout.textContent = this.hex;
    if (!skipHexInput && document.activeElement !== this.hexInput) {
      this.hexInput.value = this.hex;
    }

    Array.from(this.presetGrid.children).forEach((sw) => {
      sw.classList.toggle('is-active',
        (sw.dataset.color || '').toUpperCase() === this.hex);
    });

    this.input.value = this.hex;
    this._dispatchChange();
  }

  _dispatchChange() {
    const rgb = hsvToRgb(this.hsv);
    this.root.dispatchEvent(new CustomEvent('ignis:color-change', {
      bubbles: true,
      detail: {
        hex: this.hex,
        rgb: { r: Math.round(rgb.r), g: Math.round(rgb.g), b: Math.round(rgb.b) },
        hsv: { ...this.hsv },
      },
    }));
  }

  getValue() { return this.hex; }
  setValue(hex) {
    const rgb = hexToRgb(hex);
    if (!rgb) return;
    this.hsv = rgbToHsv(rgb);
    this.hex = rgbToHex(rgb);
    this._render();
  }
}

function init(root = document) {
  root.querySelectorAll('input[data-ignis-colorpicker]').forEach((input) => {
    if (INSTANCES.has(input)) return;
    INSTANCES.set(input, new Colorpicker(input));
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init());
} else {
  init();
}

const observer = new MutationObserver((mutations) => {
  for (const m of mutations) {
    for (const node of m.addedNodes) {
      if (node.nodeType !== 1) continue;
      if (node.matches?.('input[data-ignis-colorpicker]')) {
        init(node.parentNode || document);
      } else {
        init(node);
      }
    }
  }
});
observer.observe(document.body, { childList: true, subtree: true });

export function getColorpicker(input) {
  return INSTANCES.get(input) || null;
}

export { Colorpicker };
