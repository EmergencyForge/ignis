/**
 * ıgnıs UI — File-Input
 *
 * Pure-CSS-Komponente mit kleinem JS-Helper, der den Datei-Namen ins Label
 * spiegelt:
 *
 *   <label class="ignis-file" data-ignis-file>
 *     <i class="fa-solid fa-paperclip ignis-file__icon"></i>
 *     <span class="ignis-file__label">Datei wählen…</span>
 *     <input type="file" name="upload" accept=".pdf,.png">
 *   </label>
 *
 * Nach Auswahl wird der Datei-Name (oder „N Dateien") ins `__label`
 * geschrieben und `.has-file` an den Wrapper gesetzt.
 *
 * Drag-and-Drop für `--dropzone`-Variante: Drop fügt Dateien dem Input
 * zu und feuert `change`, sodass nachgelagerte Listener Bescheid wissen.
 */

const DEFAULT_PLACEHOLDER = 'Datei wählen…';

function bindFileInput(wrap) {
  if (wrap.dataset.ignisFileBound === '1') return;
  wrap.dataset.ignisFileBound = '1';

  const input = wrap.querySelector('input[type="file"]');
  if (!input) return;

  const labelEl = wrap.querySelector('.ignis-file__label');
  const placeholder = labelEl ? labelEl.textContent.trim() : DEFAULT_PLACEHOLDER;

  const updateLabel = () => {
    if (!labelEl) return;
    const files = input.files;
    if (!files || !files.length) {
      labelEl.textContent = placeholder;
      wrap.classList.remove('has-file');
      return;
    }
    if (files.length === 1) {
      labelEl.textContent = files[0].name;
    } else {
      labelEl.textContent = `${files.length} Dateien ausgewählt`;
    }
    wrap.classList.add('has-file');
  };

  input.addEventListener('change', updateLabel);
  updateLabel();

  // Drag-and-Drop für Dropzone-Variante
  if (wrap.classList.contains('ignis-file--dropzone')) {
    ['dragenter', 'dragover'].forEach((ev) => {
      wrap.addEventListener(ev, (e) => {
        e.preventDefault();
        wrap.classList.add('is-dragging');
      });
    });
    ['dragleave', 'drop'].forEach((ev) => {
      wrap.addEventListener(ev, (e) => {
        e.preventDefault();
        wrap.classList.remove('is-dragging');
      });
    });
    wrap.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      if (!dt || !dt.files.length) return;
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }
}

function init(root = document) {
  root.querySelectorAll('[data-ignis-file]').forEach(bindFileInput);
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
      if (node.matches?.('[data-ignis-file]')) {
        init(node.parentNode || document);
      } else {
        init(node);
      }
    }
  }
});
observer.observe(document.body, { childList: true, subtree: true });
