/**
 * Vite-Entry für das Vendor-Bundle.
 *
 * Nach dem Bootstrap-Ausstieg bündelt das Bundle nur noch jQuery
 * + DataTables (Core ohne Bootstrap-Theme) + FontAwesome + den
 * hauseigenen Bootstrap-Modal-Compat-Shim.
 *
 * jQuery bleibt für DataTables und Legacy-Inline-Scripts; Bootstrap
 * (CSS + JS) ist komplett raus.
 */

import $ from 'jquery';
window.$      = $;
window.jQuery = $;

// Bootstrap-Modal-Compat-Shim: stellt window.bootstrap.Modal-API +
// data-bs-toggle/dismiss-Verhalten bereit, ohne das vollständige
// Bootstrap-Bundle zu laden.
import './bootstrap-compat.js';

// DataTables-Core ohne Bootstrap-Theme — Styling kommt aus admin.scss.
import 'datatables.net';

// CSS-Imports — Vite extrahiert automatisch nach vendor.css
import '@fortawesome/fontawesome-free/css/all.min.css';
