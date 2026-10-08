/**
 * Vite-Entry für das Vendor-Bundle.
 *
 * Das Bundle enthält nur noch jQuery und FontAwesome. jQuery bleibt für
 * Inline-Skripte älterer Seiten. Listen sortieren, filtern und blättern
 * über App\Support\ListQuery, DataTables ist raus (Guard:
 * tests/Unit/Templates/DataTablesUsageTest.php).
 */

import $ from 'jquery';
window.$      = $;
window.jQuery = $;

// CSS-Imports: Vite extrahiert automatisch nach vendor.css
import '@fortawesome/fontawesome-free/css/all.min.css';
