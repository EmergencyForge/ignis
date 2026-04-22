/**
 * Vite-Entry für das Vendor-Bundle.
 *
 * Bündelt jQuery + Bootstrap + DataTables + FontAwesome in je ein
 * CSS- und JS-Artefakt unter public/assets/dist/vendor.{css,js}.
 *
 * jQuery wird vor Bootstrap global auf `window` gelegt, damit sowohl
 * Bootstraps interner Plugin-Check als auch der bestehende jQuery-
 * basierte App-Code (DataTables, Ajax-Handler, Inline-Scripts in
 * Templates) wie bisher funktionieren.
 *
 * Reihenfolge wichtig: jQuery → window-Exports → Bootstrap/DataTables.
 */

import $ from 'jquery';
window.$      = $;
window.jQuery = $;

// Bootstrap als Namespace auf window.bootstrap legen, damit Legacy-Inline-
// Scripts `bootstrap.Modal.getOrCreateInstance(...)`, `bootstrap.Tooltip`
// etc. weiterhin funktionieren. Ohne diesen Export wäre Bootstrap zwar
// gebundled, aber nur intern für jQuery-Plugins erreichbar.
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import 'datatables.net-bs5';

// CSS-Imports — Vite extrahiert automatisch nach vendor.css
import 'bootstrap/dist/css/bootstrap.min.css';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
