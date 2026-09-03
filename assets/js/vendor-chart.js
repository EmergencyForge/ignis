/**
 * Vite-Entry: Chart.js als lokales Bundle für die eNOTF-Vitalwerte-Charts.
 *
 * Ersetzt die früheren CDN-Loads (jsdelivr/cdnjs) in den v1-Templates
 * enotf/print und enotf/protokoll/verlauf — auf FiveM-Servern ohne
 * Außenanbindung kamen die CDN-Scripts nie an und die Charts blieben leer.
 *
 * `chart.js/auto` registriert alle Controller/Scales/Plugins, damit sich
 * das Bundle wie das komplette UMD-Build vom CDN verhält. Die bestehenden
 * Inline-Scripts und enotf-print.js greifen auf das globale `Chart` zu,
 * deshalb wird die Klasse explizit an window gehängt (IIFE-Build ohne
 * eigene Exports, siehe vite.config.js).
 */

import Chart from 'chart.js/auto';

window.Chart = Chart;
