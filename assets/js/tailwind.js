/**
 * Vite-Entry für das Tailwind-Bundle.
 *
 * Der Import der CSS-Datei reicht — Vite extrahiert sie über das offizielle
 * Tailwind-Vite-Plugin
 * ins CSS-Bundle (public/assets/dist/tailwind.css). Die resultierende
 * tailwind.js ist leer und wird nicht in Templates eingebunden.
 */

import '../css/tailwind.css';
