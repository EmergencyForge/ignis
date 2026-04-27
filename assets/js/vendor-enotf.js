/**
 * Vite-Entry: Bootstrap-only-Bundle exklusiv für den eNOTF-Kontext.
 *
 * Der eNOTF ist als legacy-PHP-Modul stark Bootstrap-5-getrieben (Modal,
 * Accordion, Form-Klassen, .row/.col-Grid, Utility-Klassen wie d-flex,
 * me-1, fw-bold, text-muted etc.). Eine vollständige Migration auf
 * Tailwind + ıgnıs würde hunderte Markup-Stellen anfassen — stattdessen
 * laden wir Bootstrap nur dort, wo es tatsächlich gebraucht wird.
 *
 * Wird zusätzlich zu assets/js/vendor.js (jQuery + FontAwesome +
 * DataTables-Core) in assets/components/enotf/_head.php eingebunden.
 * Bootstrap 5 hat keine jQuery-Abhängigkeit; Popper liegt als bundled
 * Dependency in `bootstrap`.
 */

import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
