<?php

/**
 * eNOTF v2 — DAS gemeinsame Layout (alle v2-Seiten rendern hierüber).
 *
 * Verwendung in einem Seiten-Template:
 *
 *     <?php
 *     $__title = 'Übersicht';
 *     $__crew  = $crew ?? null;              // aus EnotfV2Controller::crewContext()
 *     ob_start(); ?>
 *         ...Seiteninhalt (Tailwind + ignis-Komponenten)...
 *     <?php
 *     $__content = ob_get_clean();
 *     require __DIR__ . '/_layout.php';      // bzw. dirname(__DIR__) aus Unterordnern
 *
 * Layout-Vertrag (alle optionalen Variablen VOR dem require setzen):
 *   $__title         string       Seitentitel (Topbar + <title>)
 *   $__content       string       gepufferter Seiteninhalt
 *   $__crew          array|null   {vehicle, members[{position,name,quali}]}
 *   $__enr           string|null  aktiviert Autosave-Client + Sync-Status-Platzhalter
 *   $__sections      array|null   Section-Map (ProtokollController::SECTIONS) → Sidebar/Stepper
 *   $__activeSection string|null  Key der aktiven Section
 *   $__sectionStatus array|null   ConditionsService::sectionStatus() →
 *                                 Füllstands-Badges im Stepper (Initialwert;
 *                                 autosave.js aktualisiert nach jedem Batch
 *                                 über /api/enotf-v2/plausibility/{enr})
 *   $__istGesperrt   bool         Protokoll gesperrt → Autosave aus, Banner an
 *   $__bodyClass     string       Zusatzklassen für <body>
 *
 * Design: modernes Tailwind/ignis-Dark-Theme wie die Admin-Welt
 * (html data-theme="light" + body data-theme="dark" — v1-Konvention der
 * neuen Admin-Templates). KEIN Bootstrap, KEIN jQuery — Vanilla JS,
 * Dialoge über assets/js/ui/dialog.js (window.Dialog).
 */

use Plugin\EnotfV2\Helpers\EnotfV2Url;

$__title         = $__title         ?? 'eNOTF v2';
$__content       = $__content       ?? '';
$__crew          = $__crew          ?? null;
$__enr           = $__enr           ?? null;
$__sections      = $__sections      ?? null;
$__activeSection = $__activeSection ?? null;
$__istGesperrt   = $__istGesperrt   ?? false;
$__sectionStatus = $__sectionStatus ?? null;
$__bodyClass     = $__bodyClass     ?? '';
$__patname       = $__patname       ?? null;   // Topbar-Patient (nur Protokollseiten)
$__patSynced     = $__patSynced     ?? null;   // pat_synced: 0 offen / 2 angefordert / 1 synchron
$__protArt       = $__protArt       ?? null;   // Protokollart-Kürzel für die Topbar (RD/NA)

$__e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

// Initialen für die Crew-Kreise in der Topbar ("Max Mustermann" → "MM")
$__initials = static function (string $name): string {
    $parts = array_values(array_filter(
        preg_split('/\s+/', trim($name)) ?: [],
        static fn ($p) => $p !== ''
    ));
    if ($parts === []) {
        return '?';
    }
    $ini = mb_substr($parts[0], 0, 1);
    if (count($parts) > 1) {
        $ini .= mb_substr($parts[count($parts) - 1], 0, 1);
    }
    return mb_strtoupper($ini);
};
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $__e($__title) ?> &rsaquo; eNOTF v2</title>

    <link rel="preload" href="<?= BASE_PATH ?>assets/fonts/geist/fonts/geist-v4-latin-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('public/assets/dist/vendor.css') ?>">
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist-mono/css/all.min.css" />
    <link rel="stylesheet" href="<?= asset('public/assets/dist/style.css') ?>" />
    <link rel="stylesheet" href="<?= asset('public/assets/dist/ui.css') ?>" />
    <link rel="stylesheet" href="<?= asset('public/assets/dist/tailwind.css') ?>">

    <!-- Vanilla-UI-Module (kein jQuery, kein Bootstrap) -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/dialog.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/dropdown.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/snackbar.js"></script>
    <!-- Custom-Select: FiveM-CEF zeigt native <select>-Popups teils nicht —
         die Komponente enhanced ALLE select.ignis-input (auch in Dialogen),
         das native Select bleibt Quelle der Wahrheit (Opt-out: data-ev2-native) -->
    <script type="module" src="<?= asset('plugins/enotf-v2/assets/ev2-select.js') ?>"></script>
    <?php if ($__enr !== null): ?>
    <script type="module" src="<?= asset('plugins/enotf-v2/assets/autosave.js') ?>"></script>
    <?php endif; ?>

    <!-- CitizenFX: Session-ID an den FiveM-Client durchreichen (CEF-Embed) -->
    <script>
        (function () {
            if (!navigator.userAgent.includes('CitizenFX')) return;
            fetch('<?= BASE_PATH ?>api/character/get-session-id')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.session_id) {
                        var target = (window.parent !== window) ? window.parent : window;
                        target.postMessage({ type: 'ignis_session', session_id: data.session_id }, '*');
                    }
                })
                .catch(function () {});
        })();
    </script>

    <style>
        /* ── Basis-Reset ─────────────────────────────────────────────
           Die v2-Seiten laden weder Bootstrap-Reboot noch Tailwind-
           Preflight — Body-Margin-Reset und box-sizing kommen deshalb
           hier. Ohne border-box rechnen Sidebar-Breite und Kontroll-
           höhen content-box: Inputs geraten zu hoch, die Sidebar zu
           breit (Status-Indikatoren kleben an der Kante) und um die
           Topbar bleibt der 8px-Default-Margin des Bodys sichtbar.
           Scope body[data-page] statt .ev2-app, damit auch die per
           dialog.js an <body> gehängten Dialoge erfasst sind. */
        body[data-page="enotf-v2"] { margin: 0; padding: 0; background: #0f0e12; }
        body[data-page="enotf-v2"] *,
        body[data-page="enotf-v2"] *::before,
        body[data-page="enotf-v2"] *::after { box-sizing: border-box; }

        /* ── Farb-Tokens ─────────────────────────────────────────────
           Orange (Akzent) nur für Primäraktionen, aktive Navigation und
           ausgewählte Chips/Segmente. Rot ist echten Fehlern vorbehalten.
           Statusfarben bewusst gedeckt: kein Signalrot für "noch leer". */
        .ev2-app {
            --ev2-accent:    var(--main-color, #ff4d00);
            --ev2-on-accent: #1c1108;   /* dunkle Schrift auf Orange */
            --ev2-done:      #4c9e6e;   /* gedecktes Grün: vollständig */
            --ev2-partial:   #b3924b;   /* gedämpftes Amber: teilweise */
            --ev2-error:     #ef4444;   /* NUR echte Fehler */
            --ev2-muted:     var(--text-dimmed, #818189);
            /* Hierarchie-Stufen: Seite deutlich dunkler als Karten/Kacheln,
               Auswahl-Zustand eine Stufe heller als bg-tertiary */
            --ev2-page:      #0f0e12;   /* Seitenhintergrund */
            --ev2-card:      #1c1a21;   /* Karten, Kacheln, Sidebar */
            --ev2-card-hi:   #211f28;   /* Fokus-Ansicht: eine Stufe heller */
            --ev2-sel-bg:    #413d4c;   /* Auswahl: bg-tertiary aufgehellt */
            min-height: 100vh; display: flex; flex-direction: column;
            background: var(--ev2-page);
        }

        /* ── Topbar ─────────────────────────────────────────────────
           Full-bleed, feste Höhe (56px), DREI Zonen:
           links Brand · Divider · Protokollkontext (#enr mono + RD/NA-Tag),
           Mitte Patient (nur Protokollseiten, dezenter Text statt Pill),
           rechts funktionaler Cluster mit feinen Dividern:
           Uhr · Leitstelle · Speicherstatus · Fahrzeug · Crew · Logout.
           Farbe eine Spur dunkler als die Sidebar (#232128) + weicher
           Schatten = leichte Elevation; feine Border unten bleibt. */
        .ev2-topbar {
            display: flex; align-items: center; gap: .75rem;
            height: 3.5rem; padding: 0 1rem 0 .75rem;
            flex-wrap: nowrap; min-width: 0;
            margin: 0; border-radius: 0;
            background: #1e1d23;
            border-bottom: 1px solid var(--border-color, #37343e);
            box-shadow: 0 1px 10px rgba(0, 0, 0, .35);
            position: sticky; top: 0; z-index: 100;
        }
        .ev2-topbar__left {
            display: flex; align-items: center; gap: .6rem;
            flex: 0 1 auto; min-width: 0;
        }
        .ev2-topbar__brand {
            font-weight: 700; letter-spacing: .02em; white-space: nowrap;
            font-size: 1.02rem; color: var(--text-title, #e8e6f3);
            text-decoration: none;
            display: inline-flex; align-items: baseline; gap: .25rem;
        }
        .ev2-topbar__brand:hover { color: var(--ev2-accent, #ff4d00); }
        .ev2-topbar__brand small { opacity: .55; font-weight: 500; font-size: .72em; }
        /* Feiner vertikaler Divider (links wie im rechten Cluster) */
        .ev2-topbar__sep {
            width: 1px; height: 1.1rem; flex: 0 0 auto;
            background: var(--border-color, #37343e);
        }
        /* Protokollkontext: ENR mono + kleiner Protokollart-Tag, muted */
        .ev2-topbar__ctx {
            display: inline-flex; align-items: center; gap: .45rem;
            min-width: 0; color: var(--ev2-muted);
        }
        .ev2-topbar__enr {
            font-size: .82rem; font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .ev2-tag {
            font-size: .62rem; font-weight: 700; letter-spacing: .05em;
            line-height: 1; padding: .18rem .32rem; border-radius: 4px;
            border: 1px solid var(--border-color, #4a4652);
            color: var(--ev2-muted); white-space: nowrap;
        }
        .ev2-topbar__title {
            font-size: .85rem; color: var(--ev2-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            min-width: 0;
        }
        /* Rechter Cluster: Items mit feinen Dividern statt Pill-Parade */
        .ev2-topbar__tools {
            display: flex; align-items: center; gap: .6rem;
            flex: 0 0 auto; min-width: 0;
        }
        /* Live-Uhr (HH:MM, tickend per Layout-Script): mono, muted */
        .ev2-clock {
            font-size: .82rem; font-variant-numeric: tabular-nums;
            color: var(--ev2-muted); white-space: nowrap;
        }
        /* Leitstellen-Verbindung: muted = unbekannt, grün = Sync < 120s,
           rot = kein Sync (Klassen togglet das Poll-Script, keine
           hartkodierten Farben im JS) */
        .ev2-conn { display: inline-flex; align-items: center; color: var(--ev2-muted); font-size: .85rem; }
        .ev2-conn--ok   { color: var(--ev2-done); }
        .ev2-conn--down { color: var(--ev2-error); }
        /* Patient PROMINENT in der Topbar-Mitte (v1-Vorbild): Name groß
           mit Person-Icon + Sync-Statuspunkt (weiß 0 / gelb 2 / grün 1);
           Klick = Patientendaten anfordern/senden (POST patient-sync
           → pat_synced=2). Hover zeigt die Klickbarkeit. */
        .ev2-pat {
            display: inline-flex; align-items: center; gap: .55rem;
            height: 2.4rem; padding: 0 .8rem; border-radius: 8px;
            font-size: 1rem; font-weight: 600; font-family: inherit;
            color: var(--text-title, #e8e6f3);
            background: transparent; border: 0;
            cursor: pointer; min-width: 0; max-width: 24rem;
        }
        .ev2-pat i { color: var(--ev2-muted); font-size: .88rem; }
        .ev2-pat:disabled { cursor: default; opacity: .55; }
        .ev2-pat:not(:disabled):hover { background: var(--bg-tertiary, #37343e); }
        .ev2-pat__name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ev2-pat__dot {
            width: .55rem; height: .55rem; border-radius: 50%; flex: 0 0 auto;
            background: var(--text-title, #e8e6f3);
            transition: background .2s ease;
        }
        .ev2-pat[data-state="2"] .ev2-pat__dot { background: var(--ev2-partial); }
        .ev2-pat[data-state="1"] .ev2-pat__dot { background: var(--ev2-done); }
        /* Drucken-Aktion (Link auf die v1-Druckansicht, neues Tab):
           Icon + Label; das Label weicht auf schmalen Desktops (≤1500px),
           damit die Topbar bei 1366 overflow-frei bleibt */
        .ev2-topbar__print { white-space: nowrap; }
        @media (max-width: 1500px) { .ev2-topbar__print .ev2-topbar__printlabel { display: none; } }
        /* Crew als Initialen-Kreise (26px, leicht überlappend); Tooltip
           trägt Position, Name und Quali. Bei mehr als zwei Mitgliedern
           fasst ein "+N"-Kreis den Rest zusammen. */
        .ev2-crew { display: inline-flex; align-items: center; flex: 0 0 auto; }
        .ev2-crew__avatar {
            width: 26px; height: 26px; border-radius: 50%; flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .6rem; font-weight: 700; letter-spacing: .02em;
            background: var(--bg-tertiary, #37343e);
            color: var(--text-secondary, #b9b6c4);
            border: 1.5px solid #1e1d23;
            position: relative;
        }
        .ev2-crew__avatar + .ev2-crew__avatar { margin-left: -6px; }
        .ev2-crew__avatar:hover { z-index: 1; background: var(--bg-quaternary, #4a4652); color: var(--text-title, #e8e6f3); }
        .ev2-chip {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .78rem; padding: .15rem .55rem; border-radius: 999px;
            background: var(--bg-tertiary, #37343e); border: 1px solid var(--border-color, #4a4652);
            white-space: nowrap;
        }
        /* Entfernen-Kreuz in Auswahl-Chips (z. B. Diagnose-Zusammenfassung) */
        .ev2-chip__x {
            border: 0; background: transparent; color: inherit;
            opacity: .55; cursor: pointer; padding: 0 0 0 .1rem;
            font-size: .7rem; line-height: 1;
        }
        .ev2-chip__x:hover { opacity: 1; color: var(--ev2-error); }
        /* Akzent-Rahmen (z. B. Hauptdiagnose-Chip) */
        .ev2-chip--accent { border-color: var(--ev2-accent); }
        /* Sync-Status: klein und muted; "Gespeichert" blinkt kurz grün
           auf (autosave.js entfernt --saved nach ~2s wieder) */
        .ev2-sync { display: inline-flex; align-items: center; gap: .4rem; font-size: .75rem; color: var(--ev2-muted); }
        .ev2-sync__dot { width: .5rem; height: .5rem; border-radius: 50%; background: #6b7280; transition: background .2s ease; }
        .ev2-sync--saving  .ev2-sync__dot { background: var(--ev2-partial); }
        .ev2-sync--saved   .ev2-sync__dot { background: var(--ev2-done); }
        .ev2-sync--saved   { color: var(--ev2-done); }
        .ev2-sync--error   .ev2-sync__dot { background: var(--ev2-error); }
        .ev2-sync--error   { color: var(--ev2-error); }

        /* ── Grundgerüst ────────────────────────────────────────────── */
        .ev2-main { display: flex; flex: 1 1 auto; min-height: 0; align-items: flex-start; }
        /* Sidebar als „schwebende" Karte: abgesetzt mit Margin, gerundet,
           Elevation; sticky unter der Topbar. Einklappbar auf Icon-Rail
           (Toggle unten, Zustand in localStorage — Script am Seitenende). */
        .ev2-sidebar {
            width: 14.5rem; flex: 0 0 auto; padding: .75rem .625rem;
            margin: 1rem 0 1rem 1rem;
            overflow-y: auto; overflow-x: hidden; scrollbar-width: thin;
            background: var(--ev2-card, #1c1a21);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .35), 0 8px 24px rgba(0, 0, 0, .25);
            position: sticky; top: 4.5rem;
            max-height: calc(100vh - 5.5rem);
        }
        .ev2-sidebar__toggle {
            display: flex; align-items: center; justify-content: center; gap: .45rem;
            width: 100%; margin-top: .35rem; padding: .4rem .5rem;
            border: 0; border-radius: 8px; background: transparent;
            color: var(--ev2-muted); font-size: .72rem; font-family: inherit;
            cursor: pointer;
        }
        .ev2-sidebar__toggle:hover { background: var(--bg-tertiary, #37343e); color: var(--text-secondary, #b9b6c4); }
        .ev2-sidebar__toggle .fa-angles-right { display: none; }
        @media (min-width: 821px) {
            .ev2-sidebar.is-collapsed { width: 3.6rem; padding: .6rem .4rem; }
            .ev2-sidebar.is-collapsed .ev2-step__label,
            .ev2-sidebar.is-collapsed .ev2-progress__text,
            .ev2-sidebar.is-collapsed .ev2-sidebar__toggle span { display: none; }
            .ev2-sidebar.is-collapsed .ev2-sidebar__toggle .fa-angles-left { display: none; }
            .ev2-sidebar.is-collapsed .ev2-sidebar__toggle .fa-angles-right { display: inline; }
            /* Icon-Rail: Nummern-Badge oben, Status-Punkt darunter;
               das Label wandert in den title-Tooltip (steht ohnehin dran) */
            .ev2-sidebar.is-collapsed .ev2-step {
                flex-direction: column; gap: .25rem; padding: .45rem .2rem;
                align-items: center;
            }
            .ev2-sidebar.is-collapsed .ev2-state { margin-left: 0; width: .6rem; height: .6rem; }
            .ev2-sidebar.is-collapsed .ev2-state-partial::after { width: .22rem; height: .22rem; }
            .ev2-sidebar.is-collapsed .ev2-state-done::after {
                left: .13rem; top: .15rem; width: .28rem; height: .14rem;
            }
            /* Fortschritt kollabiert: nur Mini-Bar */
            .ev2-sidebar.is-collapsed .ev2-progress { padding: .1rem .15rem .55rem; }
        }
        .ev2-content { flex: 1 1 auto; min-width: 0; padding: 1.25rem; }
        /* Headings im Content: Element-Defaults aus style.css (h1 = 2rem
           + Margins) gewinnen sonst gegen die gelayerten Tailwind-
           Utilities — hier neutralisieren, Größen kommen von ev2-Klassen. */
        .ev2-content h1, .ev2-content h2, .ev2-content h3, .ev2-content h4 { margin: 0; }
        .ev2-content h3.ev2-group-label { margin: 1.25rem 0 .5rem; }

        /* Seitenkopf: Sectionname + Meta-Chips — kompakt, Chips dezent */
        .ev2-page-head { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin: 0 0 1rem; }
        .ev2-page-title { font-size: 1.6rem; font-weight: 600; line-height: 1.25; margin: 0; }
        .ev2-page-head .ev2-chip { background: transparent; color: var(--ev2-muted); }

        /* ── Gesamtfortschritt (Sidebar-Kopf) ───────────────────────
           Zahlen: initial serverseitig (ConditionsService::sectionStatus),
           danach autosave.js aus der Plausibility-Antwort. */
        .ev2-progress { display: block; padding: .25rem .65rem .75rem; }
        .ev2-progress__text { display: block; font-size: .72rem; color: var(--ev2-muted); margin-bottom: .4rem; white-space: nowrap; }
        .ev2-progress__short { display: none; font-size: .72rem; color: var(--ev2-muted); }
        .ev2-progress__bar {
            display: block; height: 4px; border-radius: 999px;
            background: var(--bg-tertiary, #37343e); overflow: hidden;
        }
        .ev2-progress__fill {
            display: block; height: 100%; border-radius: 999px;
            background: var(--ev2-done); transition: width .25s ease;
        }

        /* ── Section-Navigation ─────────────────────────────────────
           Aktiv: dezenter Hintergrund + 3px Akzentbalken links + orangenes
           Nummern-Badge. Kein vollflächiger Orange-Block. */
        .ev2-step {
            display: flex; align-items: center; gap: .55rem; width: 100%;
            padding: .5rem .65rem; border-radius: 8px; font-size: .85rem;
            color: inherit; text-decoration: none; margin-bottom: .2rem;
        }
        .ev2-step:hover { background: var(--bg-tertiary, #37343e); }
        .ev2-step.is-active {
            background: var(--bg-tertiary, #37343e);
            box-shadow: inset 3px 0 0 var(--ev2-accent);
        }
        .ev2-step__num {
            width: 1.4rem; height: 1.4rem; border-radius: 50%; flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .72rem; font-weight: 700;
            background: var(--bg-tertiary, #37343e);
        }
        .ev2-step.is-active .ev2-step__num { background: var(--ev2-accent); color: var(--ev2-on-accent); }
        .ev2-step__label {
            flex: 1 1 auto; min-width: 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* Section-Status: leer = grauer Kreis-Outline, teilweise =
           Amber-Kreis mit Punkt, vollständig = gedecktes Grün mit Häkchen,
           ohne Pflichtfelder = unsichtbar (Platz bleibt für die Ausrichtung).
           autosave.js togglet dieselben Klassen nach jedem Save-Batch. */
        .ev2-state { width: .85rem; height: .85rem; border-radius: 50%; flex: 0 0 auto; margin-left: auto; position: relative; }
        .ev2-state-empty   { border: 1.5px solid var(--border-color, #4a4652); }
        .ev2-state-partial { border: 1.5px solid var(--ev2-partial); }
        .ev2-state-partial::after {
            content: ''; position: absolute; inset: 0; margin: auto;
            width: .3rem; height: .3rem; border-radius: 50%;
            background: var(--ev2-partial);
        }
        .ev2-state-done { background: var(--ev2-done); }
        .ev2-state-done::after {
            content: ''; position: absolute; left: .2rem; top: .22rem;
            width: .42rem; height: .22rem;
            border-left: 1.5px solid rgba(255, 255, 255, .92);
            border-bottom: 1.5px solid rgba(255, 255, 255, .92);
            transform: rotate(-45deg);
        }
        .ev2-state-none { visibility: hidden; }

        /* ── Feld-Komponenten ───────────────────────────────────────
           EINE Kontrollhöhe (2.5rem/40px) für Inputs, Selects und
           Segmented — Scope body[data-page] statt .ev2-content, damit
           auch Login-Seite und Dialoge (an <body> gemountet) die Regel
           bekommen. padding-block 0: die Höhe zentriert den Text selbst,
           das ignis-Padding darf sie nicht aufblähen. Textareas wachsen
           weiter frei. */
        body[data-page="enotf-v2"] input.ignis-input,
        body[data-page="enotf-v2"] select.ignis-input {
            height: 2.5rem; padding-top: 0; padding-bottom: 0;
        }

        /* ── Custom-Select (ev2-select.js) ──────────────────────────
           Ersatz für die nativen <select>-Popups (FiveM-CEF zeigt sie
           teils nicht an). Das native Select bleibt als Quelle der
           Wahrheit im DOM, nur visuell versteckt; daneben Trigger-Button
           in ignis-input-Optik. Das Panel mountet an <body> mit
           position:fixed (kein Clipping durch Karten-/Dialog-Overflow,
           z-index über den Dialogen bei ~2000). Alle Farben mit
           Literal-Fallbacks, weil das Panel außerhalb von .ev2-app
           hängt und die --ev2-*-Tokens dort nicht gelten. */
        .ev2-select { position: relative; min-width: 0; }
        .ev2-select__native {
            position: absolute !important;
            width: 1px !important; height: 1px !important;
            margin: 0 !important; padding: 0 !important; border: 0 !important;
            opacity: 0; overflow: hidden; pointer-events: none;
            clip: rect(0 0 0 0);
        }
        body[data-page="enotf-v2"] .ev2-select__trigger {
            display: flex; align-items: center; gap: .5rem;
            height: 2.5rem; padding-top: 0; padding-bottom: 0;
            text-align: left; cursor: pointer;
        }
        .ev2-select__trigger:disabled { opacity: .55; cursor: default; }
        .ev2-select.is-open .ev2-select__trigger {
            border-color: var(--main-color, #ff4d00);
            box-shadow: 0 0 0 3px rgba(var(--main-color-rgb, 255, 77, 0), .15);
        }
        .ev2-select__label {
            flex: 1 1 auto; min-width: 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .ev2-select__label.is-placeholder { color: var(--text-dimmed, #818189); }
        .ev2-select__chev {
            flex: 0 0 auto; font-size: .65rem;
            color: var(--text-dimmed, #818189);
            transition: transform .15s ease;
        }
        .ev2-select.is-open .ev2-select__chev { transform: rotate(180deg); }

        .ev2-select-panel {
            position: fixed; z-index: 3200;
            display: flex; flex-direction: column;
            background: var(--bg-secondary, #232128);
            border: 1px solid var(--border-color, #4a4652);
            border-radius: var(--radius-md, 6px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .45);
            overflow: hidden;
        }
        .ev2-select-panel__search { padding: .45rem; border-bottom: 1px solid rgba(255, 255, 255, .07); }
        body[data-page="enotf-v2"] .ev2-select-panel__search .ignis-input {
            height: 2.1rem; padding-top: 0; padding-bottom: 0;
        }
        .ev2-select-panel__list {
            overflow-y: auto; overscroll-behavior: contain;
            padding: .25rem; scrollbar-width: thin;
        }
        .ev2-select-panel__option {
            padding: .45rem .65rem; border-radius: 6px;
            font-size: .88rem; color: var(--text-primary, #d9d7e4);
            cursor: pointer; user-select: none;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ev2-select-panel__option.is-active { background: var(--bg-tertiary, #37343e); }
        .ev2-select-panel__option.is-selected { color: var(--main-color, #ff4d00); font-weight: 600; }
        .ev2-select-panel__option.is-disabled { opacity: .45; cursor: default; }
        .ev2-select-panel__option.is-filtered { display: none; }
        .ev2-select-panel__empty,
        .ev2-select-panel__noresult {
            padding: .5rem .65rem; font-size: .82rem;
            color: var(--text-dimmed, #818189);
        }

        .ev2-field { min-width: 0; }
        /* Feld-Label: Satzschreibung, einheitliche Größe/Farbe, knapper
           Abstand zum Feld */
        .ev2-label {
            display: block; font-size: .78rem; line-height: 1.2;
            color: var(--text-secondary, #b9b6c4);
            margin-bottom: .3rem;
        }
        /* Gruppen-Label (KREISLAUF, AUSKULTATION, …): klein, uppercase, muted */
        .ev2-group-label {
            display: block; font-size: .68rem; text-transform: uppercase;
            letter-spacing: .07em; color: var(--ev2-muted); font-weight: 600;
            margin-bottom: .45rem;
        }
        /* Hinweistext unter/über Feldgruppen */
        .ev2-hint { font-size: .75rem; color: var(--ev2-muted); }
        /* Pflicht-Marker: Akzentfarbe, nie rot */
        .ev2-req { color: var(--ev2-accent); font-weight: 600; margin-left: .1rem; }

        /* Feld-Raster in Kartenkörpern (Messwerte, Select-Paare, …) */
        .ev2-fieldrow { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
        /* Karten-Raster einer Section */
        .ev2-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }

        /* Einheiten-Suffix (mmHg, %, /min, …): Input + Suffix-Box als
           EINE visuelle Einheit mit gemeinsamem Focus-Ring */
        .ev2-measure { display: flex; align-items: stretch; border-radius: var(--radius-md, 6px); }
        .ev2-measure .ignis-input {
            flex: 1 1 auto; min-width: 0;
            border-top-right-radius: 0; border-bottom-right-radius: 0;
        }
        .ev2-measure__unit {
            display: inline-flex; align-items: center; padding: 0 .6rem;
            font-size: .75rem; white-space: nowrap; color: var(--ev2-muted);
            background: var(--bg-tertiary, #37343e);
            border: 1px solid var(--darkgray, #2a2a2a); border-left: 0;
            border-radius: 0 var(--radius-md, 6px) var(--radius-md, 6px) 0;
        }
        .ev2-measure:focus-within .ev2-measure__unit { border-color: var(--ev2-accent); }

        /* Auswahl-Chips (Radio/Checkbox): eine Größe, ein Padding.
           AUSWAHL-ZUSTAND dezent (kein Orange-Fill): hellerer Hintergrund
           + 1px Akzent-Border + Häkchen, Textfarbe bleibt normal —
           überall identisch (Chips, Option-Zeilen, Skalen). */
        .ev2-choices { display: flex; flex-wrap: wrap; gap: .45rem; }
        .ev2-choice input { position: absolute; opacity: 0; pointer-events: none; }
        .ev2-choice span {
            display: inline-flex; align-items: center; gap: .4rem;
            height: 2.25rem; padding: 0 .85rem;
            border-radius: 999px; font-size: .85rem; cursor: pointer; user-select: none;
            background: var(--bg-tertiary, #37343e); border: 1px solid var(--border-color, #4a4652);
        }
        .ev2-choice span:hover { border-color: rgba(255, 255, 255, .25); }
        .ev2-choice input:checked + span {
            background: var(--ev2-sel-bg, #413d4c);
            border-color: var(--ev2-accent);
            font-weight: 600;
        }
        .ev2-choice input:checked + span::before {
            content: '\2713'; /* Häkchen */
            font-size: .72em; line-height: 1;
            color: var(--ev2-accent);
        }
        .ev2-choice input:focus-visible + span { outline: 2px solid var(--ev2-accent); outline-offset: 2px; }
        .ev2-choice input:disabled + span { opacity: .5; cursor: not-allowed; }

        /* Option-Zeilen (volle Breite, Wert + Klartext) — für Radio-Gruppen
           mit vielen Optionen (>8) in Fokus-Ansichten, nach dem Vorbild der
           v1-Radio-Seiten. Optionaler Wert-Badge (ev2-optionrow__val, z. B.
           GCS-Punktzahl) vor dem Klartext; Häkchen rechts bei Auswahl. */
        .ev2-optionrows { display: flex; flex-direction: column; gap: .45rem; }
        .ev2-optionrow { display: block; margin: 0; }
        .ev2-optionrow input { position: absolute; opacity: 0; pointer-events: none; }
        .ev2-optionrow span.ev2-optionrow__body {
            display: flex; align-items: center; gap: .75rem;
            width: 100%; min-height: 2.75rem; padding: .5rem .9rem;
            border-radius: 8px; font-size: .92rem; cursor: pointer; user-select: none;
            background: var(--bg-tertiary, #37343e);
            border: 1px solid var(--border-color, #4a4652);
        }
        .ev2-optionrow span.ev2-optionrow__body:hover { border-color: rgba(255, 255, 255, .25); }
        .ev2-optionrow__val {
            flex: 0 0 auto; min-width: 2rem; text-align: center;
            font-weight: 700; font-variant-numeric: tabular-nums;
            padding: .15rem .35rem; border-radius: 6px;
            background: rgba(255, 255, 255, .06); font-size: .88rem;
        }
        .ev2-optionrow__text { flex: 1 1 auto; min-width: 0; }
        .ev2-optionrow__check {
            flex: 0 0 auto; color: var(--ev2-accent); font-size: .85rem;
            visibility: hidden;
        }
        .ev2-optionrow__check::before { content: '\2713'; }
        .ev2-optionrow input:checked + .ev2-optionrow__body {
            background: var(--ev2-sel-bg, #413d4c);
            border-color: var(--ev2-accent);
        }
        .ev2-optionrow input:checked + .ev2-optionrow__body .ev2-optionrow__text { font-weight: 600; }
        .ev2-optionrow input:checked + .ev2-optionrow__body .ev2-optionrow__check { visibility: visible; }
        .ev2-optionrow input:focus-visible + .ev2-optionrow__body { outline: 2px solid var(--ev2-accent); outline-offset: 2px; }
        .ev2-optionrow input:disabled + .ev2-optionrow__body { opacity: .5; cursor: not-allowed; }

        /* ── Themen-Kacheln (Kachel-Drilldown, v1-Bedienmodell) ─────────
           Übersichtsmodus einer Section: große klickbare Kacheln mit
           Icon-Quadrat, Titel, Status-Indikator (ev2-state-Logik),
           Wert-Zusammenfassung (max. 2 Zeilen, ellipsis) und Chevron.
           Grid 2–3 Spalten desktop, gleiche Höhen (kein Masonry). */
        .ev2-tiles {
            display: grid; gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            align-items: stretch;
        }
        .ev2-tile {
            display: flex; flex-direction: column; gap: .65rem;
            min-height: 7.25rem; padding: 1rem 1.1rem;
            background: var(--ev2-card, #1c1a21);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .35), 0 6px 18px rgba(0, 0, 0, .2);
            color: inherit; text-decoration: none;
            transition: border-color .12s ease, background .12s ease;
        }
        .ev2-tile:hover { border-color: rgba(255, 255, 255, .22); background: var(--ev2-card-hi, #211f28); }
        .ev2-tile:focus-visible { outline: 2px solid var(--ev2-accent); outline-offset: 2px; }
        .ev2-tile__top { display: flex; align-items: center; gap: .7rem; min-width: 0; }
        .ev2-tile__icon {
            width: 2.5rem; height: 2.5rem; border-radius: 9px; flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--bg-tertiary, #37343e); color: var(--text-secondary, #b9b6c4);
            font-size: 1rem;
        }
        .ev2-tile__title {
            flex: 1 1 auto; min-width: 0; margin: 0;
            font-size: 1.02rem; font-weight: 600; line-height: 1.25;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            hyphens: none;
        }
        .ev2-tile__top .ev2-state { margin-left: 0; }
        .ev2-tile__chev { flex: 0 0 auto; color: var(--ev2-muted); font-size: .8rem; }
        .ev2-tile__summary {
            font-size: .82rem; line-height: 1.4; color: var(--ev2-muted);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; overflow-wrap: anywhere;
        }
        .ev2-tile__summary--empty { font-style: italic; opacity: .75; }

        /* Fokus-Header einer Themen-Ansicht: „Zurück zur Übersicht" +
           Thementitel + Weiter/Zurück-Kette zwischen den Themen. */
        .ev2-focus-head {
            display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
            margin: 0 0 1rem;
        }
        .ev2-focus-head__back {
            display: inline-flex; align-items: center; gap: .45rem;
            font-size: .85rem; color: var(--ev2-muted); text-decoration: none;
            padding: .4rem .6rem; border-radius: 8px;
        }
        .ev2-focus-head__back:hover { background: var(--bg-tertiary, #37343e); color: var(--text-secondary, #b9b6c4); }
        .ev2-focus-head__title { font-size: 1.35rem; font-weight: 600; margin: 0; hyphens: none; }
        .ev2-focus-head__nav { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }

        /* Segmented Control (Tri-States wie Sonderrechte, ja/nein):
           zusammenhängende Leiste in Kontrollhöhe; aktives Segment Orange,
           aktives "k. A."-Segment (data-val="") nur bg-tertiary. */
        .ev2-seg {
            display: inline-flex; align-items: stretch; height: 2.5rem;
            border: 1px solid var(--border-color, #4a4652);
            border-radius: var(--radius-md, 6px); overflow: hidden;
            background: var(--input-bg, #161616);
        }
        .ev2-seg button {
            padding: 0 .9rem; font-size: .85rem; background: transparent; border: 0;
            color: inherit; cursor: pointer;
            border-right: 1px solid var(--border-color, #4a4652);
        }
        .ev2-seg button:last-child { border-right: 0; }
        .ev2-seg button:hover:not(:disabled):not(.is-active) { background: var(--bg-tertiary, #37343e); }
        .ev2-seg button.is-active { background: var(--ev2-accent); color: var(--ev2-on-accent); font-weight: 600; }
        .ev2-seg button.is-active[data-val=""] { background: var(--bg-tertiary, #37343e); color: inherit; font-weight: 400; opacity: .85; }
        .ev2-seg button:disabled { opacity: .5; cursor: default; }

        /* Segment-Skala (radio-basiert): zusammenhängende Werteleiste für
           Wertereihen wie GCS-Punkte, Schmerz-NRS 0–10 oder Bodymap-
           Schweregrade. Verstecktes Radio + sichtbares Segment — Autosave
           läuft über das name-Attribut wie bei ev2-choice. Aktiv = Orange;
           Leer-Segment (value="") aktiv = nur bg-tertiary. Bei Platznot
           scrollt die Leiste horizontal statt zu brechen. */
        .ev2-scale {
            display: inline-flex; align-items: stretch; height: 2.5rem;
            max-width: 100%; overflow-x: auto;
            border: 1px solid var(--border-color, #4a4652);
            border-radius: var(--radius-md, 6px);
            background: var(--input-bg, #161616);
            scrollbar-width: thin;
        }
        .ev2-scale label { display: flex; margin: 0; flex: 0 0 auto; }
        .ev2-scale input { position: absolute; opacity: 0; pointer-events: none; }
        .ev2-scale span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.1rem; padding: 0 .55rem; font-size: .85rem;
            cursor: pointer; user-select: none; white-space: nowrap;
            border-right: 1px solid var(--border-color, #4a4652);
        }
        .ev2-scale label:last-child span { border-right: 0; }
        .ev2-scale span:hover { background: var(--bg-tertiary, #37343e); }
        /* Auswahl dezent wie bei den Chips: hellerer Hintergrund + Akzent-
           Kontur (inset, weil die Segmente aneinanderstoßen) */
        .ev2-scale input:checked + span {
            background: var(--ev2-sel-bg, #413d4c);
            box-shadow: inset 0 0 0 1px var(--ev2-accent);
            font-weight: 600;
        }
        .ev2-scale input[value=""]:checked + span {
            background: var(--bg-tertiary, #37343e); color: inherit;
            font-weight: 400; opacity: .85;
        }
        .ev2-scale input:focus-visible + span { outline: 2px solid var(--ev2-accent); outline-offset: -2px; }
        .ev2-scale input:disabled + span { opacity: .45; cursor: not-allowed; background: transparent; }
        .ev2-scale input:disabled:checked + span { background: var(--bg-tertiary, #37343e); }
        .ev2-scale--sm { height: 2rem; }
        .ev2-scale--sm span { min-width: 1.8rem; padding: 0 .45rem; font-size: .74rem; }
        /* Grow-Variante: Segmente teilen sich die volle Breite (z. B.
           Schmerz-NRS 0–10 in schmalen Karten) statt zu überlaufen */
        .ev2-scale--grow { display: flex; width: 100%; overflow-x: hidden; }
        .ev2-scale--grow label { flex: 1 1 0; min-width: 0; }
        .ev2-scale--grow span { width: 100%; min-width: 0; padding: 0 .1rem; }

        /* Accordion (zusammenklappbare Katalog-Gruppen, z. B. Diagnose-
           Kategorien): natives <details>, standardmäßig zu; Kopf mit Titel,
           Auswahl-Punkt (Akzent, wenn in der Gruppe etwas gewählt ist),
           Anzahl-Badge und Chevron. */
        .ev2-acc {
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: var(--radius-md, 6px);
            background: var(--input-bg, #161616);
            margin-bottom: .45rem;
        }
        .ev2-acc summary {
            list-style: none; display: flex; align-items: center; gap: .5rem;
            padding: .5rem .75rem; cursor: pointer; user-select: none;
            font-size: .85rem; border-radius: inherit;
        }
        .ev2-acc summary::-webkit-details-marker { display: none; }
        .ev2-acc summary:hover { background: var(--bg-tertiary, #37343e); }
        .ev2-acc__dot { width: .45rem; height: .45rem; border-radius: 50%; background: var(--ev2-accent); flex: 0 0 auto; }
        .ev2-acc__count {
            margin-left: auto; flex: 0 0 auto;
            font-size: .7rem; color: var(--ev2-muted);
            background: var(--bg-tertiary, #37343e);
            border-radius: 999px; padding: .05rem .45rem;
        }
        .ev2-acc__chev { flex: 0 0 auto; color: var(--ev2-muted); font-size: .7rem; transition: transform .15s ease; }
        .ev2-acc[open] summary { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        .ev2-acc[open] .ev2-acc__chev { transform: rotate(90deg); }
        .ev2-acc__body { padding: .45rem .75rem .7rem; border-top: 1px solid rgba(255, 255, 255, .05); }

        /* Gruppen-Trenner in einer Karte (z. B. Maßnahmen „Weitere"):
           feine Linie zwischen den Feldgruppen statt einer Chip-Wand */
        .ev2-divided > * + * { border-top: 1px solid rgba(255, 255, 255, .06); padding-top: .9rem; }

        /* Zeit-Feldgruppe (Zeitleiste): gemeinsamer Rahmen, Label oben,
           Zeit groß — das Datum ist standardmäßig eingeklappt und wird beim
           Setzen der Zeit automatisch mit dem Einsatzdatum befüllt; der
           Kalender-Toggle blendet es bei Bedarf ein (automatisch offen,
           wenn ein gespeichertes Datum vom Einsatzdatum abweicht). */
        .ev2-timegrid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        .ev2-timegroup {
            min-width: 0; padding: .45rem .6rem .35rem;
            background: var(--input-bg, #161616);
            border: 1px solid var(--darkgray, #2a2a2a);
            border-radius: var(--radius-md, 6px);
            transition: border-color .12s ease, box-shadow .12s ease;
        }
        .ev2-timegroup:focus-within {
            border-color: var(--ev2-accent);
            box-shadow: 0 0 0 3px rgba(var(--main-color-rgb, 255, 77, 0), .15);
        }
        .ev2-timegroup__head { display: flex; align-items: center; gap: .3rem; }
        .ev2-timegroup__label {
            display: block; flex: 1 1 auto; min-width: 0;
            font-size: .66rem; text-transform: uppercase;
            letter-spacing: .06em; color: var(--ev2-muted); margin-bottom: .1rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        /* Kalender-Toggle: unauffällig, blendet das Datumsfeld ein/aus */
        .ev2-timegroup__toggle {
            flex: 0 0 auto; border: 0; background: transparent;
            color: var(--ev2-muted); opacity: .5; cursor: pointer;
            padding: 0 .1rem; font-size: .7rem; line-height: 1;
        }
        .ev2-timegroup__toggle:hover { opacity: 1; }
        .ev2-timegroup.is-dateopen .ev2-timegroup__toggle { color: var(--ev2-accent); opacity: .9; }
        .ev2-timegroup input {
            display: block; width: 100%; border: 0; background: transparent;
            color: var(--input-text, #fff); font-family: inherit; padding: 0;
        }
        .ev2-timegroup input:focus { outline: none; }
        .ev2-timegroup input[type="time"] { font-size: 1rem; font-weight: 600; }
        .ev2-timegroup input[type="date"] { display: none; font-size: .72rem; opacity: .6; margin-top: .1rem; }
        .ev2-timegroup.is-dateopen input[type="date"] { display: block; }

        /* Readonly-/Anzeigewert (Alter, Besatzung): kein Input-Rahmen,
           stiller Wert mit gestricheltem Underline. padding-bottom hebt
           den Wert von der Linie ab (sonst schneidet sie ihn an). */
        .ev2-readonly {
            display: flex; align-items: center; height: 2.5rem;
            padding: 0 .1rem .3rem; line-height: 1.2;
            font-size: .9rem; color: var(--text-secondary, #b9b6c4);
            border-bottom: 1px dashed var(--border-color, #4a4652);
        }
        .ev2-readonly--center { justify-content: center; }

        /* ── Karten ─────────────────────────────────────────────────
           Deutlich vom dunklen Seitenhintergrund abgesetzt: Karten-Ton
           + Border + leichte Elevation. Kartenabstand kommt
           aus den Grid-Gaps (1rem). */
        body[data-page="enotf-v2"] .ignis-card {
            background: var(--ev2-card, #1c1a21);
            border-color: rgba(255, 255, 255, .08);
            box-shadow: 0 1px 2px rgba(0, 0, 0, .35), 0 6px 18px rgba(0, 0, 0, .2);
        }
        /* Fokus-Ansicht: Karte eine Stufe heller als die Übersichts-Kacheln */
        body[data-page="enotf-v2"] .ignis-card.ev2-focus-card { background: var(--ev2-card-hi, #211f28); }
        .ev2-card-head {
            display: flex; align-items: center; gap: .6rem;
            padding: .6rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }
        .ev2-card-head__icon {
            width: 1.75rem; height: 1.75rem; border-radius: 6px; flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--bg-tertiary, #37343e); color: var(--ev2-muted);
            font-size: .8rem; font-weight: 700;
        }
        .ev2-card-head__icon--a { color: #6fb0a2; }
        .ev2-card-head__icon--b { color: #7d9fc4; }
        .ev2-card-head__icon--c { color: #c48b8b; }
        .ev2-card-head__icon--d { color: #a992c4; }
        .ev2-card-head__icon--e { color: #c4ab7d; }
        .ev2-card-head__title { font-size: .95rem; font-weight: 600; margin: 0; }
        /* style.scss setzt global hyphens:auto — Titel sollen bei schmalen
           Karten umbrechen, aber nie silbengetrennt werden. */
        .ev2-card-head__title,
        .ev2-page-title { hyphens: none; -webkit-hyphens: none; overflow-wrap: normal; }
        .ev2-card-head__aside { margin-left: auto; font-size: .75rem; color: var(--ev2-muted); flex: 0 0 auto; min-width: 0; }
        /* Schmale Karten: Titel darf schrumpfen/ellipsen, damit die
           Kopf-Aktion (z. B. "Gabe hinzufügen") nie aus der Karte läuft. */
        .ev2-card-head { flex-wrap: wrap; row-gap: .35rem; }
        .ev2-card-head__title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Fehler-Markierung (echte Fehler, nicht "leer") ─────────── */
        .ev2-field-error { outline: 2px solid var(--ev2-error, #ef4444) !important; outline-offset: 1px; }
        /* Selects kennen kein :placeholder-shown — ohne diese Ausnahme stünden
           Pflicht-Selects (leere erste Option + required) schon beim ersten
           Laden rot da. Fehler-Markierung läuft hier über .is-invalid. */
        select.ignis-input:invalid:not(:focus):not(.is-invalid) {
            border-color: var(--darkgray, #2a2a2a);
        }

        /* ── Mobile: Stepper als kompakte Pill-Zeile ────────────────── */
        @media (max-width: 820px) {
            .ev2-main { flex-direction: column; }
            /* Topbar darf mehrzeilig werden; Uhr + Divider weichen —
               die Crew-Kreise sind kompakt genug für eine Zeile */
            .ev2-topbar { height: auto; min-height: 3rem; flex-wrap: wrap; padding: .4rem .75rem; gap: .5rem; }
            .ev2-topbar__title, .ev2-clock, .ev2-topbar__sep { display: none; }
            .ev2-topbar__tools { gap: .5rem; flex-wrap: wrap; }
            .ev2-pat { max-width: 12rem; }
            /* Stepper: eine Zeile, horizontal scrollbar mit Snap statt
               Silbentrennung über drei Zeilen — Floating-Karte und Collapse
               gelten nur auf Desktop */
            .ev2-sidebar {
                width: 100%; display: flex; align-items: center; gap: .25rem;
                margin: 0; padding: .4rem .5rem;
                overflow-x: auto; overflow-y: hidden;
                position: static; max-height: none;
                background: var(--bg-secondary, #232128);
                border: 0; border-radius: 0; box-shadow: none;
                border-bottom: 1px solid var(--border-color, #37343e);
                scroll-snap-type: x proximity;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .ev2-sidebar::-webkit-scrollbar { display: none; }
            .ev2-sidebar__toggle { display: none; }
            /* Fortschritt kompakt: nur "N/M", Bar ausblenden */
            .ev2-progress { flex: 0 0 auto; padding: .3rem .5rem; }
            .ev2-progress__text, .ev2-progress__bar { display: none; }
            .ev2-progress__short { display: inline; }
            .ev2-step {
                margin-bottom: 0; flex: 0 0 auto; max-width: 13rem;
                padding: .45rem .65rem;
                white-space: nowrap; scroll-snap-align: center;
            }
            /* Aktive Pill: dezenter Hintergrund + Orange-Unterstreichung
               statt Akzentbalken links */
            .ev2-step.is-active {
                box-shadow: inset 0 -2px 0 var(--ev2-accent);
                border-radius: 6px 6px 2px 2px;
            }
        }
    </style>
</head>

<body data-theme="dark" data-page="enotf-v2" class="<?= $__e($__bodyClass) ?>">
    <div class="ev2-app"
        <?php if ($__enr !== null): ?>
        data-enotf-autosave
        data-enr="<?= $__e($__enr) ?>"
        data-save-url="<?= $__e(EnotfV2Url::api('save-fields')) ?>"
        data-plausibility-url="<?= $__e(EnotfV2Url::api('plausibility/' . rawurlencode((string) $__enr))) ?>"
        <?= $__istGesperrt ? 'data-locked="1"' : '' ?>
        <?php endif; ?>
    >
        <header class="ev2-topbar">
            <!-- Zone links: Brand · Divider · Protokollkontext (bzw.
                 Seitentitel auf Seiten ohne Protokoll) -->
            <div class="ev2-topbar__left">
                <a class="ev2-topbar__brand" href="<?= $__e(EnotfV2Url::page('overview')) ?>">
                    eNOTF <small>v2</small>
                </a>
                <span class="ev2-topbar__sep"></span>
                <?php if ($__enr !== null): ?>
                    <span class="ev2-topbar__ctx">
                        <span class="ev2-topbar__enr font-mono">#<?= $__e($__enr) ?></span>
                        <?php if ($__protArt !== null): ?>
                            <span class="ev2-tag" title="Protokollart: <?= $__protArt === 'NA' ? 'Notarzt' : 'Rettungsdienst' ?>"><?= $__e($__protArt) ?></span>
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="ev2-topbar__title"><?= $__e($__title) ?></span>
                <?php endif; ?>
            </div>

            <span class="grow"></span>

            <?php if ($__enr !== null): ?>
                <!-- Zone Mitte: Patient als dezenter Text + Sync-Punkt; Klick
                     markiert die Patientendaten zum Senden an die Leitstelle
                     (POST patient-sync → pat_synced=2, v1-Parität) -->
                <button type="button" class="ev2-pat" data-ev2-pat
                        data-state="<?= (int) ($__patSynced ?? 0) ?>"
                        <?= ($__patname === null || $__patname === '') ? 'disabled' : '' ?>
                        title="Patientendaten anfordern/senden">
                    <i class="fa-solid fa-user-injured"></i>
                    <span class="ev2-pat__name"><?= ($__patname !== null && $__patname !== '') ? $__e($__patname) : 'Kein Patient' ?></span>
                    <span class="ev2-pat__dot" title="Patientendaten-Sync"></span>
                </button>
            <?php endif; ?>

            <span class="grow"></span>

            <!-- Zone rechts: funktionaler Cluster mit feinen Dividern -->
            <div class="ev2-topbar__tools">
                <!-- Live-Uhr (tickt über das Layout-Script) -->
                <span class="ev2-clock" data-ev2-clock title="Uhrzeit">--:--</span>

                <?php if ($__enr !== null): ?>
                    <span class="ev2-topbar__sep"></span>
                    <!-- Leitstellen-Verbindung: Poll über /api/enotf-v2/sync-status -->
                    <span class="ev2-conn" data-ev2-emd title="Verbindung zur Leitstelle unbekannt">
                        <i class="fa-solid fa-tower-broadcast"></i>
                    </span>
                    <!-- Sync-Status-Platzhalter: Autosave-Client schreibt hierauf -->
                    <span class="ev2-sync" data-autosave-status>
                        <span class="ev2-sync__dot"></span>
                        <span data-autosave-status-text><?= $__istGesperrt ? 'Gesperrt' : 'Bereit' ?></span>
                    </span>
                    <span class="ev2-topbar__sep"></span>
                    <!-- Druckansicht: v1-Print-Seite in neuem Tab (v1-Parität
                         „Protokoll"-Aktion) — für alle, auch bei Gesperrt -->
                    <?php
                    $__printUrl = class_exists(\Plugin\Enotf\Helpers\EnotfUrl::class)
                        ? \Plugin\Enotf\Helpers\EnotfUrl::print((string) $__enr)
                        : BASE_PATH . 'enotf/print/index.php?enr=' . rawurlencode((string) $__enr);
                    ?>
                    <a class="ignis-btn ignis-btn--sm ignis-btn--ghost ev2-topbar__print"
                       href="<?= $__e($__printUrl) ?>" target="_blank" rel="noopener"
                       title="Protokoll-Druckansicht öffnen">
                        <i class="fa-solid fa-print"></i><span class="ev2-topbar__printlabel"> Drucken</span>
                    </a>
                <?php endif; ?>

                <?php if ($__crew !== null && $__crew['vehicle'] !== ''): ?>
                    <span class="ev2-topbar__sep"></span>
                    <span class="ev2-chip" title="Angemeldetes Fahrzeug (<?= $__e($__crew['vehicle']) ?>)">
                        <i class="fa-solid fa-truck-medical"></i>
                        <?= $__e($__crew['vehicle_label'] ?? $__crew['vehicle']) ?>
                    </span>
                    <?php if ($__crew['members'] !== []): ?>
                        <?php
                        // Crew als Initialen-Kreise: die ersten zwei einzeln,
                        // ab dem dritten Mitglied ein "+N"-Kreis (Tooltip
                        // listet die restlichen Namen)
                        $__crewShown = array_slice($__crew['members'], 0, 2);
                        $__crewRest  = array_slice($__crew['members'], 2);
                        ?>
                        <span class="ev2-crew" aria-label="Besatzung">
                            <?php foreach ($__crewShown as $__member): ?>
                                <span class="ev2-crew__avatar"
                                      title="<?= $__e(ucfirst($__member['position'])) ?>: <?= $__e($__member['name']) ?><?= $__member['quali'] !== '' ? ' (' . $__e($__member['quali']) . ')' : '' ?>">
                                    <?= $__e($__initials((string) $__member['name'])) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if ($__crewRest !== []): ?>
                                <span class="ev2-crew__avatar"
                                      title="<?= $__e(implode(', ', array_map(static fn ($m) => ucfirst($m['position']) . ': ' . $m['name'] . ($m['quali'] !== '' ? ' (' . $m['quali'] . ')' : ''), $__crewRest))) ?>">
                                    +<?= count($__crewRest) ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                    <span class="ev2-topbar__sep"></span>
                    <a class="ignis-btn ignis-btn--sm ignis-btn--ghost" href="<?= $__e(EnotfV2Url::page('loggedout')) ?>" title="Abmelden">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="ev2-main">
            <?php if (is_array($__sections) && $__sections !== []): ?>
                <nav class="ev2-sidebar" aria-label="Protokoll-Abschnitte">
                    <?php
                    // Status → Anzeigeklasse (identisches Mapping in autosave.js)
                    $__stateClass = [
                        'filled'     => 'ev2-state-done',
                        'partfilled' => 'ev2-state-partial',
                        'unfilled'   => 'ev2-state-empty',
                        'nocheck'    => 'ev2-state-none',
                    ];

                    // Gesamtfortschritt über alle Sections mit Pflichtangaben —
                    // Initialwert serverseitig, danach hält autosave.js die
                    // Anzeige über die Plausibility-Antwort aktuell.
                    if (is_array($__sectionStatus)):
                        $__pflichtGesamt = 0;
                        $__pflichtErledigt = 0;
                        foreach ($__sectionStatus as $__s) {
                            if (($__s['status'] ?? 'nocheck') === 'nocheck') {
                                continue;
                            }
                            $__pflichtGesamt   += (int) ($__s['total'] ?? 0);
                            $__pflichtErledigt += (int) ($__s['filled'] ?? 0);
                        }
                        $__pflichtProzent = $__pflichtGesamt > 0
                            ? (int) round($__pflichtErledigt / $__pflichtGesamt * 100)
                            : 0;
                    ?>
                        <div class="ev2-progress" title="Gesamtfortschritt Pflichtangaben">
                            <span class="ev2-progress__text" data-ev2-progress-text><?= $__pflichtErledigt ?> von <?= $__pflichtGesamt ?> Pflichtangaben</span>
                            <span class="ev2-progress__short" data-ev2-progress-short><?= $__pflichtErledigt ?>/<?= $__pflichtGesamt ?></span>
                            <span class="ev2-progress__bar"><span class="ev2-progress__fill" data-ev2-progress-fill style="width: <?= $__pflichtProzent ?>%"></span></span>
                        </div>
                    <?php endif; ?>

                    <?php $__i = 0; foreach ($__sections as $__key => $__section): $__i++; ?>
                        <a class="ev2-step <?= $__key === $__activeSection ? 'is-active' : '' ?>"
                           href="<?= $__e(EnotfV2Url::protokoll((string) $__enr, (string) $__key)) ?>"
                           title="<?= $__e($__section['label']) ?>"
                           <?= $__key === $__activeSection ? 'aria-current="page"' : '' ?>>
                            <span class="ev2-step__num"><?= $__i ?></span>
                            <span class="ev2-step__label"><?= $__e($__section['label']) ?></span>
                            <?php
                            // Status-Indikator: Initialstatus serverseitig aus
                            // ConditionsService::sectionStatus(); autosave.js
                            // aktualisiert nach jedem Save-Batch (data-section-fill).
                            // Auch bei "nocheck" rendern — die Section kann durch
                            // eine Versorgungsart-Änderung Pflichtfelder bekommen.
                            $__fill = is_array($__sectionStatus) ? ($__sectionStatus[$__key] ?? null) : null;
                            if ($__fill !== null):
                                $__fillTitle = $__fill['status'] === 'nocheck'
                                    ? 'Keine Pflichtangaben'
                                    : $__fill['filled'] . ' von ' . $__fill['total'] . ' Pflichtangaben';
                            ?>
                                <span class="ev2-state <?= $__stateClass[$__fill['status']] ?? 'ev2-state-none' ?>"
                                      data-section-fill="<?= $__e($__key) ?>"
                                      title="<?= $__e($__fillTitle) ?>"></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>

                    <!-- Collapse-Toggle: klappt die Sidebar auf eine Icon-Rail
                         (Nummern + Status-Punkte, Labels als title-Tooltips);
                         Zustand überlebt in localStorage -->
                    <button type="button" class="ev2-sidebar__toggle" data-ev2-sidebar-toggle
                            title="Seitenleiste ein-/ausklappen">
                        <i class="fa-solid fa-angles-left"></i>
                        <i class="fa-solid fa-angles-right"></i>
                        <span>Einklappen</span>
                    </button>
                </nav>
                <script>
                    // Collapse-Zustand VOR dem ersten Paint anwenden (kein Flackern)
                    (function () {
                        try {
                            if (localStorage.getItem('ev2SidebarCollapsed') === '1') {
                                document.querySelector('.ev2-sidebar').classList.add('is-collapsed');
                            }
                        } catch (e) { /* localStorage gesperrt → Standard: offen */ }
                    })();
                </script>
            <?php endif; ?>

            <main class="ev2-content">
                <?php \App\Helpers\Flash::render(); ?>
                <?= $__content ?>
            </main>
        </div>
    </div>

    <script>
        // Mobile-Stepper: aktive Section in den sichtbaren Bereich rücken
        // (nur wenn die Section-Leiste tatsächlich horizontal scrollt)
        (function () {
            var sidebar = document.querySelector('.ev2-sidebar');
            var active = sidebar ? sidebar.querySelector('.ev2-step.is-active') : null;
            if (!sidebar || !active) return;
            if (sidebar.scrollWidth <= sidebar.clientWidth + 4) return;
            active.scrollIntoView({ inline: 'center', block: 'nearest' });
        })();

        // Sidebar-Collapse: Toggle + localStorage-Persistenz
        (function () {
            var sidebar = document.querySelector('.ev2-sidebar');
            var toggle = document.querySelector('[data-ev2-sidebar-toggle]');
            if (!sidebar || !toggle) return;
            toggle.addEventListener('click', function () {
                var collapsed = sidebar.classList.toggle('is-collapsed');
                try { localStorage.setItem('ev2SidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
            });
        })();

        // Topbar: Live-Uhr (HH:MM)
        (function () {
            var el = document.querySelector('[data-ev2-clock]');
            if (!el) return;
            function tick() {
                var d = new Date();
                el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>

    <?php if ($__enr !== null): ?>
    <script>
        // Topbar: Leitstellen-Sync-Status + Patienten-Sync (Protokollseiten).
        // Poll alle 10s wie die v1-Topbar; Anzeige läuft über Klassen bzw.
        // data-state (Farben stehen im Styleblock, nicht hier).
        (function () {
            var SYNC_TIMEOUT = 120;   // Sekunden ohne EMD-Sync = Verbindung rot
            var POLL_INTERVAL = 10000;
            var connEl = document.querySelector('[data-ev2-emd]');
            var patEl = document.querySelector('[data-ev2-pat]');
            var statusUrl = <?= json_encode(EnotfV2Url::api('sync-status', ['enr' => (string) $__enr])) ?>;
            var patientSyncUrl = <?= json_encode(EnotfV2Url::api('patient-sync')) ?>;
            var enr = <?= json_encode((string) $__enr) ?>;

            function poll() {
                fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (patEl && data.pat_synced !== null && data.pat_synced !== undefined) {
                            patEl.dataset.state = String(data.pat_synced);
                        }
                        if (!connEl) return;
                        connEl.classList.remove('ev2-conn--ok', 'ev2-conn--down');
                        if (data.last_emd_sync) {
                            var diff = (Date.now() - new Date(data.last_emd_sync.replace(' ', 'T')).getTime()) / 1000;
                            if (diff <= SYNC_TIMEOUT) {
                                connEl.classList.add('ev2-conn--ok');
                                connEl.title = 'Verbindung zur Leitstelle aktiv';
                            } else {
                                connEl.classList.add('ev2-conn--down');
                                connEl.title = 'Keine Verbindung zur Leitstelle';
                            }
                        } else {
                            connEl.title = 'Verbindung zur Leitstelle unbekannt';
                        }
                    })
                    .catch(function () { /* Anzeige behält den letzten Stand */ });
            }
            poll();
            setInterval(poll, POLL_INTERVAL);

            if (patEl && !patEl.disabled) {
                patEl.addEventListener('click', function () {
                    var frage = 'Patientendaten an die Leitstelle senden/anfordern?';
                    var entscheidung = window.Dialog
                        ? window.Dialog.confirm(frage, { title: 'Patientendaten', confirmText: 'Senden' })
                        : Promise.resolve(window.confirm(frage));
                    entscheidung.then(function (ok) {
                        if (!ok) return;
                        fetch(patientSyncUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ enr: enr })
                        })
                            .then(function (r) { return r.json().catch(function () { return {}; }); })
                            .then(function (data) {
                                if (data && data.success) patEl.dataset.state = String(data.pat_synced || 2);
                            })
                            .catch(function () {});
                    });
                });
            }
        })();
    </script>
    <?php endif; ?>
</body>

</html>
