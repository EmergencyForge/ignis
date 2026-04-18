/**
 * Vite-Entry für das Tailwind-Bundle.
 *
 * Der Import der SCSS-Datei reicht — Vite extrahiert sie via PostCSS
 * ins CSS-Bundle (public/assets/dist/tailwind.css). Die resultierende
 * tailwind.js ist leer und wird nicht in Templates eingebunden.
 */

import '../css/tailwind.scss';
