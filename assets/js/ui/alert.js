/**
 * ıgnıs UI — Alert
 *
 * Pure-CSS-Komponente, lediglich Dismiss-Verhalten ist JS-getrieben:
 *
 *   <div class="ignis-alert ignis-alert--success">
 *     <i class="fa-solid fa-circle-check ignis-alert__icon"></i>
 *     <div class="ignis-alert__body">…</div>
 *     <button class="ignis-alert__close" aria-label="Schließen">&times;</button>
 *   </div>
 *
 * Klick auf `__close` entfernt das Alert mit Fade-Out und feuert
 * `ignis:alert-dismiss`.
 */

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ignis-alert__close');
  if (!btn) return;
  const alert = btn.closest('.ignis-alert');
  if (!alert) return;

  e.preventDefault();
  alert.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
  alert.style.opacity = '0';
  alert.style.transform = 'translateY(-4px)';

  setTimeout(() => {
    alert.dispatchEvent(new CustomEvent('ignis:alert-dismiss', { bubbles: true }));
    alert.remove();
  }, 200);
});
