/**
 * Vite-Entry für das Vendor-Bundle.
 *
 * Nach dem Bootstrap-Ausstieg bündelt das Bundle nur noch jQuery
 * + DataTables (Core ohne Bootstrap-Theme) + FontAwesome.
 *
 * jQuery bleibt für DataTables und Legacy-Inline-Scripts; Bootstrap
 * (CSS + JS) ist komplett raus.
 */

import $ from 'jquery';
window.$      = $;
window.jQuery = $;

// DataTables-Core ohne Bootstrap-Theme — Styling kommt aus admin.scss.
import 'datatables.net';

// CSS-Imports — Vite extrahiert automatisch nach vendor.css
import '@fortawesome/fontawesome-free/css/all.min.css';
