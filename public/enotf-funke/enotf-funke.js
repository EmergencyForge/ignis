/**
 * eNOTF im Funke-Look: Routing, Zustand, Ansichten, Interaktion.
 *
 * Reines ES-Modul ohne Build und ohne Abhängigkeiten außer den gebauten
 * Funke-Modulen aus ignis (Dialog, Snackbar, Tooltip). Hash-Routing:
 *
 *   #/login  #/overview  #/create  #/lock
 *   #/p/<enr>/<abschnitt>[/<thema>[/<unterthema>]]
 *
 * Der Zustand liegt komplett im localStorage unter "enotf-funke:state".
 * Mutationen laufen über commit() (speichern + neu zeichnen) oder bei
 * Texteingaben über persist() + updateChrome() (Fokus bleibt erhalten).
 */

import { Dialog } from '../assets/js/ui/dialog.js';
import { snack } from '../assets/js/ui/snackbar.js';
import { hideFloater } from '../assets/js/ui/tooltip.js';
import { initAll as initDropdowns } from '../assets/js/ui/dropdown.js';
import * as C from './catalogs.js';

const KEY = 'enotf-funke:state';
const THEME_KEY = 'enotf-funke:theme';
const app = document.getElementById('app');

/* ══ Hilfen ═════════════════════════════════════════════════════ */

const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
// Trennstellen für lange Wörter in schmalen Kacheln. hyphens: auto greift im
// Tablet-Browser nicht, ohne diese Stellen bräche das Wort an beliebiger Stelle.
const SHY = Object.fromEntries(['Rassel|geräusche', 'Sinus|rhythmus', 'Kammer|flimmern', 'Schritt|macher|rhythmus', 'Links|schenkel|block', 'Rechts|schenkel|block',
    'Massen|veranstaltung', 'Bildungs|einrichtung', 'ein|richtung', 'Geburts|haus', 'Entlastungs|punktion', 'Immobili|sation', 'Endo|tracheal|tubus', 'Zwangs|unter|bringung',
    'Patienten|zugang', 'Patienten|übergabe', 'Abwehr|bewegungen', 'Beuge|synergismen', 'Streck|synergismen', 'unver|ständliche', 'Auf|forderung', 'Extremitäten|bewegung',
    'Bewusstseins|lage', 'Schmerz|reiz', 'un|eingeschränkt', 'Notarzt|zubringer', 'Medi|kamenten|liste', 'Fach|abteilungen', 'Des|infektions|plan', 'Funkruf|namen', 'Brust|schmerz', 'Reanimations|situation', 'Transport|führer', 'Fahrzeug|führer', 'Protokol|lant']
    .map((w) => [w.replaceAll('|', ''), w.replaceAll('|', '&shy;')]));
const hy = (v) => esc(v).replace(/[A-Za-zÄÖÜäöüß]{9,}/g, (w) => SHY[w] ?? w);
const get = (o, path) => path.split('.').reduce((a, k) => (a == null ? undefined : a[k]), o);
function set(o, path, v) {
    const ks = path.split('.');
    let cur = o;
    ks.slice(0, -1).forEach((k) => {
        if (cur[k] == null || typeof cur[k] !== 'object') cur[k] = {};
        cur = cur[k];
    });
    cur[ks[ks.length - 1]] = v;
}
const isSet = (v) => v !== undefined && v !== null && v !== '' && v !== '---' && !(Array.isArray(v) && v.length === 0);
const href = (...parts) => '#/' + parts.filter((x) => x !== undefined && x !== null && x !== '').map((x) => encodeURIComponent(x)).join('/');
const uid = () => Math.random().toString(36).slice(2, 9);
const fa = (icon, extra = '') => `<i class="fa-solid ${icon}${extra ? ' ' + extra : ''}" aria-hidden="true"></i>`;
const num = (v) => (v === undefined || v === null || v === '' || v === 'ng' || v === 'nm') ? null : parseFloat(String(v).replace(',', '.'));
const pad = (n) => String(n).padStart(2, '0');

function parseDate(d, t) {
    const m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(d || '');
    if (!m) return null;
    const tm = /^(\d{2}):(\d{2})/.exec(t || '00:00') || [0, '00', '00'];
    return new Date(+m[3], +m[2] - 1, +m[1], +tm[1], +tm[2]);
}
function ageOf(gebdat) {
    const d = parseDate(gebdat);
    if (!d) return '';
    const now = new Date();
    let a = now.getFullYear() - d.getFullYear();
    if (now.getMonth() < d.getMonth() || (now.getMonth() === d.getMonth() && now.getDate() < d.getDate())) a--;
    return a >= 0 && a < 130 ? `${a} J.` : '';
}
function initials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}
const fmtDT = (iso) => { const d = new Date(iso); return `${C.fmtDate(d)} ${C.fmtTime(d)}`; };

/* ══ Zustand ════════════════════════════════════════════════════ */

function freshState() {
    const crew = structuredClone(C.SAMPLE_CREW);
    const list = C.sampleProtocols(crew.vehicle);
    return {
        v: 1,
        session: crew,
        protocols: Object.fromEntries(list.map((p) => [p.enr, p])),
        order: list.map((p) => p.enr),
        conn: { lst: true, session: true },
        failSave: false,
        readonly: false,
        quicklinksEmpty: false,
    };
}
function load() {
    try {
        const raw = localStorage.getItem(KEY);
        if (raw) {
            const s = JSON.parse(raw);
            if (s && s.v === 1 && s.protocols) return s;
        }
    } catch (e) { /* defekter oder gesperrter Speicher: frisch anfangen */ }
    return freshState();
}
let S = load();

/** Flüchtiger UI-Zustand (Entwürfe, Auswahl, Tastenfeld), nicht gespeichert. */
const ui = {
    route: [],
    autosave: 'idle',
    savedAt: null,
    kp: null,
    draft: null,
    login: null,
    create: { enr: '', type: '', err: '' },
    diagQ: '',
    bodySel: 'thorax',
    zugType: 'pvk',
    zugSel: null,
    med: null,
    hidden: new Set(),
    pin: '',
    pinErr: false,
    lockReturn: '#/overview',
    topbarMenu: false,
};

function persist() {
    try { localStorage.setItem(KEY, JSON.stringify(S)); } catch (e) { /* Speicher voll oder gesperrt */ }
}
let saveTimer = null;
function flashAutosave() {
    if (!currentProtocol()) return;
    ui.autosave = 'saving';
    paintAutosave();
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        ui.autosave = S.failSave ? 'error' : 'saved';
        ui.savedAt = new Date();
        paintAutosave();
    }, 420);
}
function commit() {
    persist();
    flashAutosave();
    render();
}
function soft() {
    persist();
    flashAutosave();
    updateChrome();
}

/* ══ Routing ════════════════════════════════════════════════════ */

function parseRoute() {
    return location.hash.replace(/^#\/?/, '').split('/').filter(Boolean).map((x) => { try { return decodeURIComponent(x); } catch (e) { return x; } });
}
function go(h) {
    if (location.hash === h) render();
    else location.hash = h;
}
function currentProtocol() {
    const r = ui.route;
    return r[0] === 'p' ? S.protocols[r[1]] || null : null;
}
const P = currentProtocol;
const ro = (p = P()) => !!(p && (p.released || S.readonly));
function guardRO() {
    if (!ro()) return false;
    const p = P();
    snack.info(p.released ? 'Das Protokoll ist freigegeben und kann nicht mehr bearbeitet werden.' : 'Nur Ansicht. Bearbeiten kann nur die Crew des eingetragenen Fahrzeugs.');
    return true;
}

/* ══ Pflichtangaben ═════════════════════════════════════════════ */

const reqActive = (p) => C.REQUIRED.filter((r) => !r.when || r.when(p));
const missingOf = (p) => reqActive(p).filter((r) => !r.ok(p));
function stateOf(p, filter) {
    const list = reqActive(p).filter(filter);
    if (!list.length) return { state: 'none', open: 0, total: 0 };
    const done = list.filter((r) => r.ok(p)).length;
    return { state: done === 0 ? 'unfilled' : done === list.length ? 'filled' : 'partfilled', open: list.length - done, total: list.length };
}
const secState = (p, s) => (s === 'verlauf' ? { state: 'none', open: 0, total: 0 } : stateOf(p, (r) => r.s === s));
const topicState = (p, s, t) => stateOf(p, (r) => r.s === s && r.t === t);
function subState(p, s, t, st) {
    const list = reqActive(p).filter((r) => r.s === s && r.t === t);
    const withSub = list.filter((r) => r.st);
    if (!withSub.length) return topicState(p, s, t);
    return stateOf(p, (r) => r.s === s && r.t === t && r.st === st);
}

// Ampel wie in v1/v2: ein farbiger Strich am Menüpunkt. Rot = nichts, gelb = teilweise,
// grün = alle Pflichtangaben. Themen ohne Pflichtangaben werden grün, sobald etwas eingetragen ist.
const CUSTOM_PATHS = {
    pupillen: ['erst.pupL', 'erst.pupR', 'erst.lichtL', 'erst.lichtR'], gcs: ['erst.gcsA', 'erst.gcsV', 'erst.gcsM'],
    bodymap: ['erst.verl'], keypad: ['erst.vitals'], freitext: ['anam.text'], symptom: ['anam.symDatum', 'anam.symZeit', 'anam.symNicht'],
    diagtext: ['diag.text'], zugang: ['mass.zugaenge'], meds: ['mass.meds', 'mass.medsNone'],
    transport: ['absch.fzgTransp', 'absch.tf', 'absch.ff', 'absch.prakt', 'absch.fzgNA', 'absch.na', 'absch.naff', 'absch.naprakt', 'absch.weitere'],
    protokoll: ['absch.protokollant', 'absch.protDurch'],
};
function pathsOf(node) {
    if (!node) return [];
    if (node.custom === 'diag') return [];
    if (node.custom) return CUSTOM_PATHS[node.custom] || [];
    if (node.subs && node.subs[0]?.custom === 'diag') return [node.mode === 'single' ? 'diag.fuehrend' : 'diag.weitere'];
    return (node.subs || [node]).flatMap((x) => (x.custom ? CUSTOM_PATHS[x.custom] || [] : (x.fields || []).map((f) => f.base || f.path)));
}
const hasValue = (v) => (v && typeof v === 'object' && !Array.isArray(v) ? Object.values(v).some(hasValue) : v === true || (isSet(v) && v !== false));
const hasContent = (p, paths) => paths.some((path) => hasValue(get(p, path)));
function fillOf(p, st, paths) {
    if (st.state !== 'none') return st.state;
    return hasContent(p, paths) ? 'filled' : '';
}
function secFill(p, s) {
    if (s === 'verlauf') return p.verlauf.length ? 'filled' : '';
    return fillOf(p, secState(p, s), topicsOf(s, p).flatMap(pathsOf));
}
function topicFill(p, s, t) {
    return fillOf(p, topicState(p, s, t), pathsOf(topicsOf(s, p).find((x) => x.key === t)));
}
const FILL_WORD = { unfilled: 'Pflichtangaben offen', partfilled: 'teilweise ausgefüllt', filled: 'vollständig' };
// Protokollart: NF neutral, NA rot, später KT grün.
const ART_TONE = { NA: 'danger', KT: 'ok' };
const artTone = (art) => (ART_TONE[art] ? ` data-tone="${ART_TONE[art]}"` : '');
const fillAttr = (f) => (f ? ` data-state="${f}"` : '');

const MEDICAL = /^(erst|anam|diag|mass)\.|^absch\.rea|^pat\.geschlecht/;
const STAR = new Set(['pat.geschlecht', 'einsatz.datum', 'einsatz.zeit', 'einsatz.versorgung', 'einsatz.einsatzart',
    'erst.awz', 'erst.zyanose', 'erst.atmung', 'erst.ausk', 'erst.pz', 'erst.puls', 'erst.radialis', 'erst.bwl', 'erst.extr',
    'erst.pupL', 'erst.pupR', 'erst.lichtL', 'erst.lichtR', 'erst.ekg', 'erst.psych', 'anam.nacaI', 'anam.ort', 'diag.fuehrend',
    'mass.awsich', 'mass.beatmung', 'mass.o2', 'absch.besond', 'absch.nachfNA', 'absch.rea', 'absch.reaD.ursache',
    'absch.reaD.kollaps', 'absch.reaD.hdmDurch', 'absch.reaD.hdmZeit', 'absch.reaD.rosc', 'absch.reaD.aufnahme',
    'absch.protokollant', 'absch.protDurch']);
function starred(p, path) {
    if (!STAR.has(path)) return false;
    if (C.isFehl(p) && MEDICAL.test(path)) return false;
    if (path === 'absch.nachfNA' && p.art === 'NA') return false;
    if (path.startsWith('absch.reaD.') && p.absch.rea !== 'Reanimation durchgeführt') return false;
    return true;
}
const missChip = (txt = 'Pflichtangabe fehlt') => `<i class="fa-solid fa-circle-exclamation ignis-tablet-miss" role="img" aria-label="${esc(txt)}" data-ignis-tooltip="${esc(txt)}"></i>`;

/* ══ Themenbäume der Abschnitte ═════════════════════════════════ */

const F = (path, label, options, extra = {}) => ({ path, label, options, type: 'single', ...extra });
const M = (path, label, options, extra = {}) => ({ path, label, options, type: 'multi', ...extra });

function topicsOf(sec, p) {
    switch (sec) {
        case 'erst': return C.ERST_TOPICS;
        case 'anam': return [
            { key: 'anamnese', label: 'Anamnese', icon: 'fa-comment-medical', custom: 'freitext' },
            {
                key: 'symptome', label: 'Symptome', icon: 'fa-clock', subs: [
                    { key: 'beginn', label: 'Symptombeginn', custom: 'symptom' },
                    { key: 'nacaI', label: 'NACA initial', fields: [F('anam.nacaI', 'NACA initial', C.NACA, { cols: 1 })] },
                    { key: 'nacaU', label: 'NACA bei Übergabe', fields: [F('anam.nacaU', 'NACA bei Übergabe', C.NACA, { cols: 1 })] },
                ],
            },
            { key: 'az', label: 'AZ vor Ereignis', icon: 'fa-person', subs: [{ key: 'az', label: 'AZ vor Ereignis', fields: [F('anam.az', 'AZ vor Ereignis', C.AZ, { cols: 1 })] }] },
            { key: 'ort', label: 'Einsatzort', icon: 'fa-location-dot', subs: [{ key: 'ort', label: 'Einsatzort', fields: [F('anam.ort', 'Einsatzort', C.EINSATZORT)] }] },
        ];
        case 'diag': {
            const cats = C.DIAG_CATS.map((c) => ({ key: c.key, label: c.label, icon: c.icon, custom: 'diag' }));
            return [
                { key: 'fuehrend', label: 'Diagnose (führend)', icon: 'fa-file-medical', subs: cats, mode: 'single' },
                { key: 'weitere', label: 'Diagnose (weitere)', icon: 'fa-layer-group', subs: cats, mode: 'multi' },
                { key: 'text', label: 'Diagnose Text', icon: 'fa-pen', custom: 'diagtext' },
            ];
        }
        case 'mass': return [
            { key: 'aw', label: 'Atemwege', icon: 'fa-wind', subs: [{ key: 'aw', label: 'Atemwege', fields: [{ type: 'bools', base: 'mass.aw', label: 'Maßnahmen Atemwege', items: C.AW_TOGGLES }, F('mass.awsich', 'Atemwegssicherung', C.AW_SICH)] }] },
            {
                key: 'atmung', label: 'Atmung', icon: 'fa-lungs', subs: [
                    { key: 'beatmung', label: 'Beatmung', fields: [F('mass.beatmung', 'Beatmung', C.BEATMUNG)] },
                    { key: 'o2', label: 'O2-Gabe', fields: [F('mass.o2', 'O2-Gabe', C.O2, { cols: 'nrs' })] },
                ],
            },
            { key: 'zugang', label: 'Zugang', icon: 'fa-syringe', custom: 'zugang' },
            { key: 'meds', label: 'Medikamente', icon: 'fa-pills', custom: 'meds' },
            {
                key: 'weitere', label: 'Weitere', icon: 'fa-kit-medical', subs: [
                    { key: 'allg', label: 'Allgemein', fields: [{ type: 'bools', base: 'mass.weitere', label: 'Weitere Maßnahmen', items: C.WEITERE_TOGGLES }] },
                    { key: 'lagerung', label: 'Lagerung', fields: [F('mass.lagerung', 'Lagerung', C.LAGERUNG)] },
                    { key: 'rettung', label: 'Rettungstechnik', fields: [M('mass.rettung', 'Rettungstechnik', C.RETTUNGSTECHNIK)] },
                    { key: 'spezial', label: 'spezielle Maßnahmen', fields: [M('mass.spezial', 'spezielle Maßnahmen', C.SPEZIAL)] },
                ],
            },
        ];
        case 'absch': {
            const durch = p.absch.rea === 'Reanimation durchgeführt';
            const nicht = p.absch.rea === 'Reanimation nicht durchgeführt';
            const reaSubs = [
                { key: 'situation', label: 'Situation', fields: [F('absch.rea', 'Reanimationssituation', C.REA, { cols: 1 }), ...(nicht ? [F('absch.reaNicht', 'Reanimation nicht durchgeführt …', C.REA_NICHT, { cols: 1 })] : [])] },
            ];
            if (durch) {
                reaSubs.push(
                    { key: 'ursache', label: 'Ursache', fields: [F('absch.reaD.ursache', 'Vermutete Ursache', C.REA_URSACHE), F('absch.reaD.sport', 'Zusammenhang mit sportlicher Aktivität', ['ja', 'nein'])] },
                    { key: 'ablauf', label: 'Ablauf', fields: [{ type: 'time', path: 'absch.reaD.frZeit', label: 'Eintreffen First Responder' }, F('absch.reaD.kollaps', 'Kollaps beobachtet', C.REA_KOLLAPS), F('absch.reaD.hdmDurch', 'HDM gestartet durch', C.REA_HDM), { type: 'time', path: 'absch.reaD.hdmZeit', label: 'HDM Zeitpunkt' }, F('absch.reaD.defi', 'Defibrillation', C.REA_DEFI)] },
                    { key: 'ergebnis', label: 'Ergebnis', fields: [F('absch.reaD.rosc', 'ROSC', C.REA_ROSC), F('absch.reaD.aufnahme', 'Krankenhausaufnahme', C.REA_AUFNAHME, { cols: 2 }), ...(p.absch.reaD?.aufnahme === 'Tod' ? [{ type: 'time', path: 'absch.reaD.todZeit', label: 'Todeszeitpunkt' }, { type: 'bools', base: 'absch.reaD', label: 'Tod', items: [{ key: 'erfolglos', label: 'Reanimation erfolglos' }] }] : [])] },
                );
            }
            return [
                { key: 'transport', label: 'Transportdaten', icon: 'fa-truck-medical', custom: 'transport' },
                { key: 'protokoll', label: 'Protokolldaten', icon: 'fa-pen-to-square', custom: 'protokoll' },
                { key: 'besond', label: 'Besonderheiten', icon: 'fa-list-check', subs: [{ key: 'besond', label: 'Besonderheiten', fields: [M('absch.besond', 'Einsatzverlauf Besonderheiten', C.BESONDERHEITEN)] }] },
                ...(p.art !== 'NA' ? [{ key: 'nachf', label: 'Nachforderung NA', icon: 'fa-user-doctor', subs: [{ key: 'nachf', label: 'Nachforderung NA', fields: [F('absch.nachfNA', 'Nachforderung NA', C.NACHF_NA)] }] }] : []),
                { key: 'rea', label: 'Reanimation', icon: 'fa-heart-pulse', subs: reaSubs },
                {
                    key: 'uebergabe', label: 'Übergabe', icon: 'fa-hospital-user', subs: [
                        { key: 'ort', label: 'Übergabe Ort', fields: [F('absch.uebOrt', 'Übergabe Ort', C.UEB_ORT)] },
                        { key: 'an', label: 'Übergabe An', fields: [F('absch.uebAn', 'Übergabe An', C.UEB_AN)] },
                    ],
                },
            ];
        }
        default: return [];
    }
}

/* ══ Bausteine ══════════════════════════════════════════════════ */

const dis = (p) => (ro(p) ? ' disabled' : '');

function optButton({ act, path, val, label, pressed, normal, score, multi, num, extraAttr = '' }) {
    return `<button type="button" class="ignis-tablet-option${normal ? ' ignis-tablet-option--normal' : ''}${multi ? ' ignis-tablet-option--multi' : ''}${num ? ' nf-opt--num' : ''}" aria-pressed="${pressed ? 'true' : 'false'}"
        data-act="${act}" data-path="${esc(path)}" data-val="${esc(val)}" ${extraAttr}>
        <span class="ignis-tablet-option__mark" aria-hidden="true"></span>
        <span class="ignis-tablet-option__label">${hy(label)}</span>
        ${score !== undefined ? `<span class="ignis-tablet-option__score ignis-mono">${score}</span>` : ''}
        ${normal ? '<span class="ignis-sr-only">(Normalbefund)</span>' : ''}
    </button>`;
}

function fieldGroup(p, f) {
    if (f.type === 'time') {
        const v = get(p, f.path) || '';
        const req = starred(p, f.path);
        return `<div class="ignis-tablet-group">
            <div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">${esc(f.label)}</span>${req && !v ? missChip() : ''}</div>
            <div class="nf-inline">
                <input class="ignis-input nf-input-time ignis-mono" type="text" inputmode="numeric" placeholder="HH:MM" maxlength="5" data-mask="time" data-bind="p:${esc(f.path)}" value="${esc(v)}"${dis(p)}>
                <button type="button" class="ignis-btn ignis-btn--secondary" data-act="now" data-path="${esc(f.path)}"${dis(p)}>${fa('fa-clock')}jetzt</button>
            </div>
        </div>`;
    }
    if (f.type === 'bools') {
        return `<div class="ignis-tablet-group">
            <div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">${esc(f.label)}</span></div>
            <div class="ignis-tablet-options">${f.items.map((it) => optButton({ act: 'bool', path: `${f.base}.${it.key}`, val: '1', label: it.label, pressed: !!get(p, `${f.base}.${it.key}`), multi: true })).join('')}</div>
        </div>`;
    }
    const val = get(p, f.path);
    const req = starred(p, f.path);
    const missing = req && !isSet(val);
    const opts = f.options.map(C.parseOpt);
    const cols = f.cols === 1 ? ' ignis-tablet-options--list' : f.cols === 2 ? ' nf-opts--two' : '';
    const button = (o) => optButton({
        act: f.type === 'multi' ? 'multi' : 'pick', path: f.path, val: o.label, label: o.label, normal: o.normal, multi: f.type === 'multi',
        pressed: f.type === 'multi' ? (Array.isArray(val) && val.includes(o.label)) : val === o.label,
        extraAttr: o.excl ? 'data-excl="1"' : '', num: f.cols === 'nrs' && /^\d/.test(o.label),
    });
    // Skalen (NRS, O2): Zahlen als eine schmale Reihe, Wörter wie „keine“ darunter.
    const body = f.cols === 'nrs'
        ? `<div class="nf-scale"${opts.some((o) => /^\d/.test(o.label) && o.label.length > 3) ? ' style="--nf-scale-min:84px"' : ''}>${opts.filter((o) => /^\d/.test(o.label)).map(button).join('')}</div>
           <div class="ignis-tablet-options">${opts.filter((o) => !/^\d/.test(o.label)).map(button).join('')}</div>`
        : `<div class="ignis-tablet-options${cols}">${opts.map(button).join('')}</div>`;
    return `<div class="ignis-tablet-group" role="group" aria-label="${esc(f.label)}">
        <div class="ignis-tablet-group__head">
            <span class="ignis-tablet-group__label">${esc(f.label)}</span>
            ${missing ? missChip() : ''}
        </div>
        ${body}
    </div>`;
}

function kv(label, value, { req = false, mono = false, empty = '—' } = {}) {
    const has = isSet(value);
    return `<div class="nf-kv"><span class="nf-kv__k">${esc(label)}</span>
        <span class="nf-kv__v${mono ? ' ignis-mono' : ''}">${has ? esc(Array.isArray(value) ? value.join(', ') : value) : (req ? missChip() : `<span class="nf-dim">${esc(empty)}</span>`)}</span></div>`;
}

function tile({ title, icon, link, rows, fill, wide }) {
    return `<a class="ignis-bezel nf-tile ignis-tablet-stripe${wide ? ' nf-tile--wide' : ''}" href="${link}"${fillAttr(fill)}>
        <div class="ignis-bezel__head">
            <span class="ignis-glyph ignis-glyph--sm">${fa(icon)}</span>
            <h3 class="ignis-bezel__title">${esc(title)}</h3>
            <i class="fa-solid fa-chevron-right nf-tile__go" aria-hidden="true"></i>
        </div>
        <div class="ignis-bezel__well nf-tile__well">${rows}</div>
    </a>`;
}

function emptyBlock({ icon, title, text, actions = '', sm = true, pattern = false, tone }) {
    return `<div class="ignis-empty${sm ? ' ignis-empty--sm' : ''}${pattern ? ' nf-empty-pattern' : ''}" ${tone ? `data-tone="${tone}"` : ''}>
        ${pattern ? '<div class="ignis-empty--pattern nf-empty-pattern__dots" aria-hidden="true"></div>' : ''}
        <span class="ignis-empty__glyph">${fa(icon)}</span>
        <p class="ignis-empty__title">${esc(title)}</p>
        ${text ? `<p class="ignis-empty__text">${esc(text)}</p>` : ''}
        ${actions ? `<div class="ignis-empty__actions">${actions}</div>` : ''}
    </div>`;
}

function bar(left, right = '') {
    return `<div class="ignis-tablet-bar">${left}<span class="ignis-tablet-bar__spacer"></span>${right}</div>`;
}

/* ══ Kopfzeile, Leiste, Hinweise ═══════════════════════════════ */

function clockHTML() {
    const d = new Date();
    return `<span class="ignis-tablet-clock" id="ignis-tablet-clock" data-ignis-tooltip="Uhrzeit"><span class="ignis-tablet-clock__time ignis-mono">${C.fmtTime(d)}</span><span class="ignis-tablet-clock__date">${C.fmtDate(d)}</span></span>`;
}

function crewButton() {
    const s = S.session;
    if (!s) return '';
    const members = [s.fahrer, s.beifahrer, s.praktikant].filter((m) => m && m.name);
    return `<button type="button" class="nf-crew" data-act="crew-menu" aria-haspopup="true" aria-expanded="${ui.topbarMenu ? 'true' : 'false'}" aria-label="Besatzung" ${ui.topbarMenu ? '' : 'data-ignis-tooltip="Besatzung"'}>
        <span class="ignis-avatars">${members.map((m) => `<span class="ignis-avatar ignis-avatar--sm">${esc(initials(m.name))}</span>`).join('')}</span>
        <i class="fa-solid fa-chevron-down nf-crew__caret" aria-hidden="true"></i>
    </button>
    ${ui.topbarMenu ? crewMenu() : ''}`;
}
function crewMenu() {
    const s = S.session;
    const row = (role, m) => `<div class="ignis-menu__identity">
        <span class="ignis-avatar ignis-avatar--sm">${esc(initials(m?.name))}</span>
        <span class="ignis-menu__identity-text"><span class="ignis-menu__identity-name">${m?.name ? esc(m.name) : '<span class="nf-dim">nicht besetzt</span>'}</span>
        <span class="ignis-menu__identity-role">${esc(role)}${m?.name && m.quali && m.quali !== '---' ? ' · ' + esc(m.quali) : ''}</span></span></div>`;
    return `<div class="ignis-menu__panel nf-crew__panel" role="menu">
        <div class="ignis-menu__heading">${esc(s.vehicle)}</div>
        ${row('Fahrer', s.fahrer)}${row('Beifahrer', s.beifahrer)}${row('Praktikant', s.praktikant)}
        <div class="ignis-menu__sep"></div>
        <button type="button" class="ignis-menu__item" role="menuitem" data-act="proto">${fa('fa-flask')}Prototyp-Einstellungen</button>
        <button type="button" class="ignis-menu__item" role="menuitem" data-act="logout">${fa('fa-right-from-bracket')}Abmelden</button>
    </div>`;
}

const SAVE_META = {
    idle: { tone: '', tip: 'Autosave: keine Änderungen' },
    saving: { tone: 'info', tip: 'Autosave: speichert …' },
    saved: { tone: 'ok', tip: 'Autosave: gespeichert' },
    error: { tone: 'danger', tip: 'Autosave: Speichern fehlgeschlagen. Tippen zum Wiederholen.' },
};
function autosaveHTML() {
    const m = SAVE_META[ui.autosave];
    const tip = ui.autosave === 'saved' && ui.savedAt ? `Autosave: gespeichert um ${C.fmtTime(ui.savedAt)} Uhr` : m.tip;
    return `<button type="button" class="ignis-tablet-sync__btn" id="nf-autosave" data-act="autosave" data-state="${ui.autosave}" data-ignis-tooltip="${esc(tip)}" aria-label="${esc(tip)}">
        ${fa('fa-floppy-disk')}<span class="ignis-dot" ${m.tone ? `data-tone="${m.tone}"` : ''}></span></button>`;
}
function paintAutosave() {
    const el = document.getElementById('nf-autosave');
    if (el) el.outerHTML = autosaveHTML();
}

function syncCluster(p) {
    const lst = S.conn.lst;
    const ses = S.conn.session;
    const ps = p.patSync || 'open';
    const pmeta = {
        open: { tone: 'warn', tip: 'Patientendaten nicht synchronisiert. Tippen zum Senden.' },
        requested: { tone: 'info', tip: 'Patientendaten angefordert, warte auf Leitstelle' },
        synced: { tone: 'ok', tip: 'Patientendaten mit der Leitstelle synchronisiert' },
    }[ps];
    const btn = (act, icon, tone, tip, extra = '') => `<button type="button" class="ignis-tablet-sync__btn" data-act="${act}" ${extra} data-ignis-tooltip="${esc(tip)}" aria-label="${esc(tip)}">${fa(icon)}<span class="ignis-dot" ${tone ? `data-tone="${tone}"` : ''}></span></button>`;
    return `<div class="ignis-tablet-sync" role="group" aria-label="Verbindungsstatus">
        ${btn('conn-lst', 'fa-tower-broadcast', lst ? 'ok' : 'danger', lst ? 'Verbindung zur Leitstelle aktiv' : 'Keine Verbindung zur Leitstelle')}
        ${btn('conn-session', 'fa-network-wired', ses ? 'ok' : 'danger', ses ? 'Session aktiv' : 'Session-Verbindung unterbrochen')}
        ${btn('patsync', 'fa-up-down', pmeta.tone, pmeta.tip, `data-state="${ps}"`)}
        ${autosaveHTML()}
    </div>`;
}

function protoTopbar(p) {
    const name = [p.pat.nachname, p.pat.vorname].filter(Boolean).join(', ');
    const age = ageOf(p.pat.gebdat);
    const g = { männlich: 'm', weiblich: 'w', divers: 'd', unbekannt: '?' }[p.pat.geschlecht] || '';
    const pre = C.isTransport(p) && p.ziel.einrichtung;
    const tb = (act, icon, label, extra = '') => `<button type="button" class="ignis-btn ignis-btn--ghost ignis-tablet-top__btn" data-act="${act}" data-ignis-tooltip="${esc(label)}" aria-label="${esc(label)}" ${extra}>${fa(icon)}<span class="ignis-tablet-top__label">${esc(label)}</span></button>`;
    return `<header class="ignis-tablet-top nf-top--proto">
        <div class="ignis-tablet-top__group nf-top__ctx">
            <a class="ignis-btn ignis-btn--secondary ignis-tablet-top__btn nf-tbtn--home" href="#/overview" data-ignis-tooltip="Übersicht" aria-label="Übersicht">${fa('fa-table-list')}<span class="ignis-tablet-top__label">Übersicht</span></a>
            <span class="nf-ctx">
                <span class="nf-ctx__line">
                    <span class="nf-ctx__enr ignis-mono">#${esc(p.enr)}</span>
                    <span class="ignis-chip nf-tag"${artTone(p.art)} data-ignis-tooltip="${esc(C.PROT_TYPES[p.art].label)}">${esc(p.art)}</span>
                </span>
                <span class="nf-ctx__veh" data-ignis-tooltip="Angemeldetes Fahrzeug">${fa('fa-truck-medical')}${esc(p.vehicle)}</span>
            </span>
            <span class="ignis-tablet-top__sep" aria-hidden="true"></span>
            <a class="ignis-tablet-context" href="${href('p', p.enr, 'rett')}" data-ignis-tooltip="Patientendaten">
                ${fa('fa-user')}
                <span class="ignis-tablet-context__text">
                    <span class="ignis-tablet-context__title">${name ? esc(name) : '<span class="nf-dim">Kein Patient</span>'}</span>
                    <span class="ignis-tablet-context__meta">${age || g ? esc([age, g].filter(Boolean).join(' · ')) : 'Alter, Geschlecht offen'}</span>
                </span>
            </a>
        </div>
        <div class="ignis-tablet-top__group nf-top__tools">
            ${pre ? tb('anmeldung', 'fa-house-medical', 'Anmeldung') : ''}
            ${tb('art', 'fa-sync', 'Art ändern', ro(p) ? 'disabled' : '')}
            ${tb('share', 'fa-share-nodes', 'Teilen')}
            ${tb('print', 'fa-file-waveform', 'Protokoll')}
            ${tb('availability', 'fa-hospital', 'Verfügbarkeit')}
        </div>
        <div class="ignis-tablet-top__group nf-top__right">
            <div class="nf-crew-wrap">${crewButton()}</div>
            <span class="ignis-tablet-top__sep" aria-hidden="true"></span>
            ${syncCluster(p)}
            <span class="ignis-tablet-top__sep" aria-hidden="true"></span>
            ${clockHTML()}
            <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon nf-tbtn--icon" data-act="lock" data-ignis-tooltip="Sperren" aria-label="Sperren">${fa('fa-lock')}</button>
            <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon nf-tbtn--icon nf-tbtn--proto" data-act="proto" data-ignis-tooltip="Prototyp-Einstellungen" aria-label="Prototyp-Einstellungen">${fa('fa-flask')}</button>
        </div>
    </header>`;
}

function plainTopbar(title, tools = '') {
    const s = S.session;
    return `<header class="ignis-tablet-top">
        <div class="ignis-tablet-top__group nf-top__ctx">
            <span class="nf-brand"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-truck-medical')}</span>eNOTF</span>
            <span class="ignis-tablet-top__sep" aria-hidden="true"></span>
            <span class="ignis-tablet-top__title">${esc(title)}</span>
            ${s ? `<span class="ignis-chip nf-veh" data-ignis-tooltip="Angemeldetes Fahrzeug">${fa('fa-truck-medical')}${esc(s.vehicle)}</span>` : ''}
        </div>
        <div class="ignis-tablet-top__group nf-top__tools">${tools}</div>
        <div class="ignis-tablet-top__group nf-top__right">
            ${s ? `<div class="nf-crew-wrap">${crewButton()}</div><span class="ignis-tablet-top__sep" aria-hidden="true"></span>` : ''}
            ${clockHTML()}
            ${s ? `<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon nf-tbtn--icon" data-act="lock" data-ignis-tooltip="Sperren" aria-label="Sperren">${fa('fa-lock')}</button>` : ''}
            <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon nf-tbtn--icon" data-act="proto" data-ignis-tooltip="Prototyp-Einstellungen" aria-label="Prototyp-Einstellungen">${fa('fa-flask')}</button>
        </div>
    </header>`;
}

function rail(p, active) {
    return `<nav class="ignis-tablet-rail" aria-label="Protokoll-Abschnitte">
        ${C.SECTIONS.map((s, i) => {
            const fill = secFill(p, s.key);
            const meta = s.key === 'verlauf' ? `${p.verlauf.length} Zeitpunkte` : '';
            return `<a class="ignis-tablet-rail__item" href="${href('p', p.enr, s.key)}" ${active === s.key ? 'aria-current="page"' : ''}${fillAttr(fill)} data-ignis-tooltip="${esc(s.label)}" data-placement="right">
                <span class="ignis-tablet-rail__num ignis-mono" aria-hidden="true">${i + 1}</span>
                <i class="fa-solid ${s.icon} ignis-tablet-rail__icon" aria-hidden="true"></i>
                <span class="ignis-tablet-rail__text"><span class="ignis-tablet-rail__label">${esc(s.label)}</span>${meta ? `<span class="ignis-tablet-rail__meta">${esc(meta)}</span>` : ''}</span>
                ${fill ? `<span class="ignis-sr-only">${FILL_WORD[fill]}</span>` : ''}
            </a>`;
        }).join('')}
    </nav>`;
}

function notice(p) {
    if (p.released) {
        const d = new Date(p.released.at);
        return `<div class="ignis-alert ignis-alert--info ignis-tablet-frame__notice" role="status">${fa('fa-lock', 'ignis-alert__icon')}
            <div class="ignis-alert__body">Das Protokoll wurde durch ${esc(p.released.by)} am ${C.fmtDate(d)} ${C.fmtTime(d)} Uhr freigegeben. Es kann nicht mehr bearbeitet werden.</div></div>`;
    }
    if (S.readonly) {
        return `<div class="ignis-alert ignis-alert--warn ignis-tablet-frame__notice" role="status">${fa('fa-eye', 'ignis-alert__icon')}
            <div class="ignis-alert__body">Nur Ansicht. Bearbeiten kann nur die Crew des eingetragenen Fahrzeugs.</div></div>`;
    }
    return '';
}

/** Aktualisiert Kopfzeile und Leiste ohne den Inhalt neu zu zeichnen (Fokus bleibt). */
function updateChrome() {
    const p = P();
    if (!p) return;
    const top = app.querySelector('.ignis-tablet-top');
    const r = app.querySelector('.ignis-tablet-rail');
    if (top) top.outerHTML = protoTopbar(p);
    if (r) r.outerHTML = rail(p, ui.route[2] || 'rett');
    app.querySelectorAll('[data-live-state]').forEach((el) => {
        const [s, t] = el.dataset.liveState.split(':');
        const f = t ? topicFill(p, s, t) : secFill(p, s);
        if (f) el.dataset.state = f; else delete el.dataset.state;
    });
}

/* ══ Abschnittsgerüst: Themen-Spalte → Unterthemen → Antworten ═══ */

function navItem({ link, label, icon, fill, current }) {
    return `<a class="ignis-tablet-nav${icon ? '' : ' ignis-tablet-nav--noicon'}" href="${link}" ${current ? 'aria-current="true"' : ''}${fillAttr(fill)}>
        ${icon ? `<i class="fa-solid ${icon} ignis-tablet-nav__icon" aria-hidden="true"></i>` : ''}
        <span class="ignis-tablet-nav__text"><span class="ignis-tablet-nav__label">${hy(label)}</span></span>
        ${fill ? `<span class="ignis-sr-only">${FILL_WORD[fill]}</span>` : ''}
    </a>`;
}

function topicColumn(p, sec, topics, topicKey) {
    return `<aside class="ignis-tablet-col nf-col--topics" aria-label="Themen" data-scroll="topics">
        ${navItem({ link: href('p', p.enr, sec), label: 'Übersicht', icon: 'fa-table-cells-large', current: !topicKey })}
        <div class="ignis-tablet-col__sep"></div>
        ${topics.map((t) => {
            return navItem({ link: href('p', p.enr, sec, t.key), label: t.label, icon: t.icon, fill: fillOf(p, topicState(p, sec, t.key), pathsOf(t)), current: t.key === topicKey });
        }).join('')}
    </aside>`;
}

function subColumn(p, sec, topic, subKey) {
    const quick = topic.quick ? quickTile(p, topic) : '';
    if (!topic.subs || (topic.subs.length < 2 && !(sec === 'diag'))) return '';
    return `<aside class="ignis-tablet-col ignis-tablet-col--subs" aria-label="Unterthemen" data-scroll="subs">
        ${quick}
        ${topic.subs.map((s) => {
            const fill = sec === 'diag' ? '' : fillOf(p, subState(p, sec, topic.key, s.key), pathsOf(s));
            return navItem({ link: href('p', p.enr, sec, topic.key, s.key), label: s.label, icon: s.icon, fill, current: s.key === subKey });
        }).join('')}
    </aside>`;
}

function quickTile(p, topic) {
    const keys = Object.keys(topic.quick.set);
    const applied = keys.every((k) => JSON.stringify(get(p, k)) === JSON.stringify(topic.quick.set[k]));
    return `<button type="button" class="ignis-tablet-quick" data-act="quick" data-topic="${esc(topic.key)}" aria-pressed="${applied ? 'true' : 'false'}"${dis(p)}>
        <span class="ignis-tablet-quick__label">${esc(topic.quick.label)}</span>
    </button>`;
}

function renderSectionShell(p, sec, topicKey, subKey) {
    const topics = topicsOf(sec, p);
    if (!topicKey) {
        return `<div class="ignis-tablet-section">${topicColumn(p, sec, topics, null)}<section class="ignis-tablet-pane" data-scroll="pane">${sectionOverview(p, sec)}</section></div>`;
    }
    const topic = topics.find((t) => t.key === topicKey);
    if (!topic) return `<div class="ignis-tablet-section">${topicColumn(p, sec, topics, null)}<section class="ignis-tablet-pane">${emptyBlock({ icon: 'fa-circle-question', title: 'Thema nicht gefunden' })}</section></div>`;
    const subs = topic.subs || [];
    const sub = subs.find((s) => s.key === subKey) || subs[0];
    const subCol = subColumn(p, sec, topic, sub?.key);
    let pane;
    if (topic.custom === 'keypad') return keypadView(p, { mode: 'erst', topics: topicColumn(p, sec, topics, topicKey) });
    if (topic.custom) pane = customPane(p, topic.custom, topic, null);
    else if (sub?.custom) pane = customPane(p, sub.custom, topic, sub);
    else pane = paneFields(p, topic, sub);
    return `<div class="ignis-tablet-section${subCol ? ' ignis-tablet-section--subs' : ''}">${topicColumn(p, sec, topics, topicKey)}${subCol}<section class="ignis-tablet-pane" data-scroll="pane">${pane}</section></div>`;
}

function paneHead(title, extra = '') {
    return `<div class="ignis-tablet-pane__head"><h2 class="ignis-tablet-pane__title">${esc(title)}</h2>${extra}</div>`;
}

function paneFields(p, topic, sub) {
    return `${paneHead(sub && sub.label !== topic.label ? `${topic.label} · ${sub.label}` : topic.label)}
        ${(sub?.fields || []).map((f) => fieldGroup(p, f)).join('')}`;
}

function customPane(p, kind, topic, sub) {
    switch (kind) {
        case 'pupillen': return pupillenPane(p);
        case 'gcs': return gcsPane(p);
        case 'bodymap': return bodyMapPane(p);
        case 'freitext': return freitextPane(p);
        case 'symptom': return symptomPane(p);
        case 'diag': return diagPane(p, topic, sub);
        case 'diagtext': return diagTextPane(p);
        case 'zugang': return zugangPane(p);
        case 'meds': return medsPane(p);
        case 'transport': return transportPane(p);
        case 'protokoll': return protokollPane(p);
        default: return '';
    }
}

/* ══ Abschnittsübersichten ═════════════════════════════════════ */

function vitalText(key, v) {
    if (v === 'ng') return 'nicht gemessen';
    if (v === 'nm') return 'nicht messbar';
    if (!isSet(v)) return '';
    return `${v} ${C.VITAL[key].unit}`;
}
// Messwerte: nur die Farbe zeigt die Abweichung, keine Bewertung in Worten daneben.
function vitalVal(key, v, text = vitalText(key, v)) {
    const g = C.gradeVital(key, num(v));
    return `<span class="ignis-tablet-reading"${g ? ` data-grade="${g}"` : ''}>${esc(text)}</span>`;
}

function gcsSum(e) {
    const s = C.GCS.map((g) => g.options.find((o) => o[0] === e[g.key])?.[1]);
    return s.every((x) => x) ? { sum: s.reduce((a, b) => a + b, 0), parts: s } : null;
}

function sectionOverview(p, sec) {
    const req = (path) => starred(p, path);
    const L = (t, s) => href('p', p.enr, sec, t, s);
    const e = p.erst;
    if (sec === 'erst') {
        const g = gcsSum(e);
        const vit = C.VITALS.map((v) => {
            const val = e.vitals?.[v.key];
            const r = v.req && !C.isFehl(p);
            return `<div class="nf-kv"><span class="nf-kv__k">${esc(v.label)}</span><span class="nf-kv__v ignis-mono">${isSet(val) ? vitalVal(v.key, val) : (r ? missChip() : '<span class="nf-dim">—</span>')}</span></div>`;
        }).join('');
        return `${paneHead('Erstbefund · Übersicht')}
        <div class="nf-tiles">
            ${tile({ title: 'Atemwege', icon: 'fa-wind', link: L('atemwege'), fill: topicFill(p, 'erst', 'atemwege'), rows: kv('Atemwegszustand', e.awz, { req: req('erst.awz') }) + kv('Zyanose', e.zyanose, { req: req('erst.zyanose') }) })}
            ${tile({ title: 'Atmung', icon: 'fa-lungs', link: L('atmung'), fill: topicFill(p, 'erst', 'atmung'), rows: kv('Beurteilung Atmung', e.atmung, { req: req('erst.atmung') }) + kv('Auskultation', e.ausk, { req: req('erst.ausk') }) })}
            ${tile({ title: 'Kreislauf', icon: 'fa-heart-pulse', link: L('kreislauf'), fill: topicFill(p, 'erst', 'kreislauf'), rows: kv('Patientenzustand', e.pz, { req: req('erst.pz') }) + kv('Rekap. Zeit', e.rekap) + kv('EKG-Befund', e.ekg, { req: req('erst.ekg') }) + kv('Pulsqualität', e.puls, { req: req('erst.puls') }) + kv('Radialispuls', e.radialis, { req: req('erst.radialis') }) + kv('starke Blutung', e.blutung) })}
            ${tile({ title: 'Neurologie', icon: 'fa-brain', link: L('neuro'), fill: topicFill(p, 'erst', 'neuro'), rows: kv('Bewusstseinslage', e.bwl, { req: req('erst.bwl') }) + kv('Extremitätenbewegung', e.extr, { req: req('erst.extr') }) + kv('GCS', g ? `${g.sum} (A${g.parts[0]} V${g.parts[1]} M${g.parts[2]})` : '') + kv('Pupillenweite li / re', isSet(e.pupL) || isSet(e.pupR) ? `${e.pupL || '—'} / ${e.pupR || '—'}` : '', { req: req('erst.pupL') }) + kv('Lichtreaktion li / re', isSet(e.lichtL) || isSet(e.lichtR) ? `${e.lichtL || '—'} / ${e.lichtR || '—'}` : '', { req: req('erst.lichtL') }) })}
            ${tile({ title: 'Verletzungen', icon: 'fa-person', link: L('erweitern', 'verletzungen'), fill: fillOf(p, { state: 'none' }, ['erst.verl']), rows: C.REGIONS.map((r) => {
                const v = e.verl?.[r.key];
                return `<div class="nf-kv"><span class="nf-kv__k">${esc(r.label)}</span><span class="nf-kv__v">${v?.sev ? `<span class="ignis-tablet-severity" data-severity="${C.SEVERITY_KEY[v.sev]}"><span class="ignis-tablet-severity__dot"></span>${esc(v.sev)}${v.wund ? ` · ${esc(v.wund)}` : ''}</span>` : '<span class="nf-dim">—</span>'}</span></div>`;
            }).join('') })}
            ${tile({ title: 'Schmerzen', icon: 'fa-face-frown', link: L('erweitern', 'schmerzen'), fill: fillOf(p, { state: 'none' }, ['erst.nrs', 'erst.toleranz']), rows: kv('Intensität', isSet(e.nrs) ? (/^\d+$/.test(e.nrs) ? `NRS ${e.nrs}` : e.nrs) : '') + kv('Toleranz', e.toleranz) })}
            ${tile({ title: 'Psyche', icon: 'fa-head-side-virus', link: L('psych'), fill: topicFill(p, 'erst', 'psych'), rows: kv('Psychischer Zustand', e.psych, { req: req('erst.psych') }) })}
            ${tile({ title: 'Vitalparameter', icon: 'fa-gauge', link: L('messwerte'), fill: topicFill(p, 'erst', 'messwerte'), wide: true, rows: `<div class="nf-kv-grid">${vit}</div>` })}
        </div>`;
    }
    if (sec === 'anam') {
        const a = p.anam;
        const lines = (a.text || '').split('\n').filter(Boolean);
        const sym = a.symNicht ? 'nicht feststellbar' : [a.symDatum, a.symZeit].filter(Boolean).join(' ') + (a.symGeschaetzt && a.symDatum ? ' (geschätzt)' : '');
        return `${paneHead('Anamnese · Übersicht')}
        <div class="nf-tiles">
            ${tile({ title: 'Anamnese', icon: 'fa-comment-medical', link: L('anamnese'), fill: topicFill(p, 'anam', 'anamnese'), wide: true, rows: lines.length ? `<p class="nf-prose">${lines.slice(0, 5).map(esc).join('<br>')}${lines.length > 5 ? ' …' : ''}</p>` : '<span class="nf-dim">Kein Freitext</span>' })}
            ${tile({ title: 'Symptome', icon: 'fa-clock', link: L('symptome'), fill: topicFill(p, 'anam', 'symptome'), rows: kv('Symptombeginn', sym.trim()) + kv('NACA initial', a.nacaI, { req: req('anam.nacaI') }) + kv('NACA bei Übergabe', a.nacaU) })}
            ${tile({ title: 'AZ vor Ereignis', icon: 'fa-person', link: L('az'), fill: topicFill(p, 'anam', 'az'), rows: kv('AZ', a.az) })}
            ${tile({ title: 'Einsatzort', icon: 'fa-location-dot', link: L('ort'), fill: topicFill(p, 'anam', 'ort'), rows: kv('Einsatzort', a.ort, { req: req('anam.ort') }) })}
        </div>`;
    }
    if (sec === 'diag') {
        const d = p.diag;
        return `${paneHead('Diagnose · Übersicht')}
        <div class="nf-tiles">
            ${tile({ title: 'Diagnose (führend)', icon: 'fa-file-medical', link: L('fuehrend'), fill: topicFill(p, 'diag', 'fuehrend'), rows: kv('Führende Diagnose', d.fuehrend, { req: req('diag.fuehrend') }) })}
            ${tile({ title: 'Diagnose (weitere)', icon: 'fa-layer-group', link: L('weitere'), fill: topicFill(p, 'diag', 'weitere'), rows: d.weitere.length ? `<div class="nf-chips">${d.weitere.map((x) => `<span class="ignis-chip">${esc(x)}</span>`).join('')}</div>` : '<span class="nf-dim">Keine weiteren Diagnosen</span>' })}
            ${tile({ title: 'Diagnose Text', icon: 'fa-pen', link: L('text'), fill: topicFill(p, 'diag', 'text'), wide: true, rows: d.text ? `<p class="nf-prose">${esc(d.text)}</p>` : '<span class="nf-dim">Kein Freitext</span>' })}
        </div>`;
    }
    if (sec === 'mass') {
        const m = p.mass;
        const aw = C.AW_TOGGLES.filter((t) => m.aw?.[t.key]).map((t) => t.label);
        const zug = m.zugangNone ? 'Kein Zugang' : m.zugaenge.map(zugLabel);
        const meds = m.medsNone ? 'Keine Medikamente' : m.meds.map((x) => `${x.t.slice(0, 5)} ${x.wirkstoff} ${x.dosis} ${x.einheit} ${x.appl}`);
        const weit = [...C.WEITERE_TOGGLES.filter((t) => m.weitere?.[t.key]).map((t) => t.label)];
        return `${paneHead('Maßnahmen · Übersicht')}
        <div class="nf-tiles">
            ${tile({ title: 'Atemwege', icon: 'fa-wind', link: L('aw'), fill: topicFill(p, 'mass', 'aw'), rows: kv('Maßnahmen', aw) + kv('Atemwegssicherung', m.awsich, { req: req('mass.awsich') }) })}
            ${tile({ title: 'Atmung', icon: 'fa-lungs', link: L('atmung'), fill: topicFill(p, 'mass', 'atmung'), rows: kv('Beatmung', m.beatmung, { req: req('mass.beatmung') }) + kv('O2-Gabe', m.o2, { req: req('mass.o2') }) })}
            ${tile({ title: 'Zugang', icon: 'fa-syringe', link: L('zugang'), fill: topicFill(p, 'mass', 'zugang'), rows: kv('Zugänge', zug, { req: !C.isFehl(p) }) })}
            ${tile({ title: 'Medikamente', icon: 'fa-pills', link: L('meds'), fill: topicFill(p, 'mass', 'meds'), rows: Array.isArray(meds) && meds.length ? meds.map((x) => `<div class="nf-kv nf-kv--one ignis-mono">${esc(x)}</div>`).join('') : kv('Medikamente', meds, { req: !C.isFehl(p) }) })}
            ${tile({ title: 'Weitere', icon: 'fa-kit-medical', link: L('weitere'), fill: topicFill(p, 'mass', 'weitere'), rows: kv('Allgemein', weit) + kv('Lagerung', m.lagerung) + kv('Rettungstechnik', m.rettung) + kv('spezielle Maßnahmen', m.spezial) })}
        </div>`;
    }
    if (sec === 'absch') {
        const a = p.absch;
        return `${paneHead('Abschluss · Übersicht', `<div class="ignis-tablet-pane__actions">
                <button type="button" class="ignis-btn ignis-btn--secondary" data-act="send-lst"${dis(p)}>${fa('fa-tower-broadcast')}An Leitstelle senden</button>
                <button type="button" class="ignis-btn ignis-btn--secondary" data-act="klinikcode">${fa('fa-key')}Klinikcode</button>
            </div>`)}
        ${a.lstSent ? `<div class="ignis-statusline nf-mb"><span class="ignis-chip"><i class="fa-solid fa-circle-check" data-tone="ok"></i>An Leitstelle gesendet ${esc(a.lstSent)} Uhr</span></div>` : ''}
        <div class="nf-tiles">
            ${tile({ title: 'Transportdaten', icon: 'fa-truck-medical', link: L('transport'), fill: topicFill(p, 'absch', 'transport'), rows: kv('Fzg. Transp.', a.fzgTransp) + kv('Transportführer', a.tf) + kv('Fahrzeugführer', a.ff) + kv('Fzg. NA', a.fzgNA) + kv('Notarzt', a.na) })}
            ${tile({ title: 'Protokolldaten', icon: 'fa-pen-to-square', link: L('protokoll'), fill: topicFill(p, 'absch', 'protokoll'), rows: kv('Protokollant', a.protokollant, { req: true }) + kv('Protokoll durch', a.protDurch, { req: true }) })}
            ${tile({ title: 'Besonderheiten', icon: 'fa-list-check', link: L('besond'), fill: topicFill(p, 'absch', 'besond'), rows: kv('Einsatzverlauf', a.besond, { req: true }) })}
            ${p.art !== 'NA' ? tile({ title: 'Nachforderung NA', icon: 'fa-user-doctor', link: L('nachf'), fill: topicFill(p, 'absch', 'nachf'), rows: kv('Nachforderung NA', a.nachfNA, { req: true }) }) : ''}
            ${tile({ title: 'Reanimation', icon: 'fa-heart-pulse', link: L('rea'), fill: topicFill(p, 'absch', 'rea'), rows: kv('Reanimationssituation', a.rea, { req: req('absch.rea') }) + (a.rea === 'Reanimation durchgeführt' ? kv('ROSC', a.reaD?.rosc, { req: true }) + kv('Krankenhausaufnahme', a.reaD?.aufnahme, { req: true }) : '') })}
            ${tile({ title: 'Übergabe', icon: 'fa-hospital-user', link: L('uebergabe'), fill: topicFill(p, 'absch', 'uebergabe'), rows: kv('Ort', a.uebOrt) + kv('An', a.uebAn) })}
        </div>
        <div class="nf-fab-space"></div>
        <a class="ignis-btn ignis-btn--primary ignis-btn--lg nf-fab" href="${href('p', p.enr, 'absch', 'freigabe')}">${fa('fa-flag-checkered')}Abschließen</a>`;
    }
    return '';
}

/* ══ Rett. Daten ═══════════════════════════════════════════════ */

function input(p, path, { label, ph = '', mask, req, readonly, mono, id, type = 'text', inputmode } = {}) {
    const v = get(p, path) ?? '';
    const r = req ?? starred(p, path);
    const miss = r && !isSet(v);
    return `<label class="ignis-field nf-field">
        <span class="ignis-field__label">${esc(label)}</span>
        <input class="ignis-input${mono ? ' ignis-mono' : ''}${miss ? ' nf-input--missing' : ''}" type="${type}" ${inputmode ? `inputmode="${inputmode}"` : ''} ${id ? `id="${id}"` : ''} placeholder="${esc(ph)}"
            ${mask ? `data-mask="${mask}" maxlength="${mask === 'date' ? 10 : 5}"` : ''} ${readonly ? 'readonly' : `data-bind="p:${esc(path)}"`} value="${esc(v)}"${readonly ? '' : dis(p)}>
    </label>`;
}
function select(p, path, options, { label, req } = {}) {
    const v = get(p, path) ?? '';
    const r = req ?? starred(p, path);
    const miss = r && !isSet(v);
    return `<label class="ignis-field nf-field">
        <span class="ignis-field__label">${esc(label)}</span>
        <select data-custom-dropdown="true" class="ignis-input${miss ? ' nf-input--missing' : ''}" data-bind="p:${esc(path)}"${dis(p)}>
            ${options.map((o) => {
                const [val, lab] = Array.isArray(o) ? o : [o, o];
                return `<option value="${esc(val)}" ${val === v ? 'selected' : ''}>${esc(lab)}</option>`;
            }).join('')}
        </select>
    </label>`;
}

function rettOverview(p) {
    const vonReq = true;
    const zielReq = C.isTransport(p);
    const addrBox = (key, title, icon, reqd) => {
        const a = p[key];
        const line = C.addressLine(a);
        const pre = key === 'von' ? 'Von' : 'Ziel';
        return `<div class="ignis-bezel nf-box ignis-tablet-stripe" data-live-state="rett:${key}"${fillAttr(topicFill(p, 'rett', key))}>
            <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa(icon)}</span><h3 class="ignis-bezel__title">${esc(title)}</h3>
                <div class="ignis-bezel__actions"><a class="ignis-btn ignis-btn--secondary ignis-btn--sm" href="${href('p', p.enr, 'rett', key)}">${fa('fa-pen')}Adresse</a></div></div>
            <a class="ignis-bezel__well nf-addr-well" href="${href('p', p.enr, 'rett', key)}">
                ${kv(`${pre} Einrichtung`, a.einrichtung)}
                <div class="nf-kv"><span class="nf-kv__k">${pre} Adresse</span><span class="nf-kv__v">${line ? esc(line) : (reqd ? missChip() : '<span class="nf-dim">Straße HNR, Ort-Ortsteil</span>')}</span></div>
                ${kv(key === 'von' ? 'SR Anfahrt' : 'SR Transport', a.sr && a.sr !== '-' ? a.sr : '')}
            </a>
        </div>`;
    };
    const vs = [['', '---'], ...C.VERSORGUNG.map((v) => [v.code, v.label])];
    return `<div class="nf-rett" data-scroll="pane">
        <div class="nf-rett__grid">
            <div class="ignis-bezel nf-box ignis-tablet-stripe" data-live-state="rett:pat"${fillAttr(topicFill(p, 'rett', 'pat'))}>
                <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-user')}</span><h3 class="ignis-bezel__title">Patientendaten</h3></div>
                <div class="ignis-bezel__well nf-form">
                    <div class="nf-form__row">${input(p, 'pat.vorname', { label: 'Vorname', ph: 'Max' })}${input(p, 'pat.nachname', { label: 'Nachname', ph: 'Mustermann' })}</div>
                    <div class="nf-form__row nf-form__row--3">
                        ${select(p, 'pat.geschlecht', C.GESCHLECHT, { label: 'Geschlecht' })}
                        ${input(p, 'pat.gebdat', { label: 'Geburtsdatum', ph: 'TT.MM.JJJJ', mask: 'date', mono: true, inputmode: 'numeric' })}
                        <label class="ignis-field nf-field"><span class="ignis-field__label">Alter</span><input class="ignis-input ignis-mono" id="nf-age" readonly value="${esc(ageOf(p.pat.gebdat))}" placeholder="berechnet"></label>
                    </div>
                </div>
            </div>
            <div class="ignis-bezel nf-box ignis-tablet-stripe" data-live-state="rett:einsatz"${fillAttr(topicFill(p, 'rett', 'einsatz'))}>
                <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-clipboard-list')}</span><h3 class="ignis-bezel__title">Einsatzdaten</h3></div>
                <div class="ignis-bezel__well nf-form">
                    <div class="nf-form__row nf-form__row--3">
                        ${input(p, 'enr', { label: 'Einsatznummer', readonly: true, mono: true, req: false })}
                        ${input(p, 'einsatz.datum', { label: 'Einsatzdatum', ph: 'TT.MM.JJJJ', mask: 'date', mono: true, inputmode: 'numeric' })}
                        ${input(p, 'einsatz.zeit', { label: 'Einsatzzeit', ph: 'HH:MM', mask: 'time', mono: true, inputmode: 'numeric' })}
                    </div>
                    ${select(p, 'einsatz.versorgung', vs, { label: 'Versorgung' })}
                    ${select(p, 'einsatz.einsatzart', ['', ...C.EINSATZART].map((x) => [x, x || '---']), { label: 'Einsatzart' })}
                    ${C.isFehl(p) ? `<div class="ignis-alert ignis-alert--info">${fa('fa-circle-info', 'ignis-alert__icon')}<div class="ignis-alert__body">Fehleinsatz: Patienten- und Befundangaben sind keine Pflicht.</div></div>` : ''}
                    ${C.isTransport(p) ? `<div class="ignis-alert ignis-alert--info">${fa('fa-circle-info', 'ignis-alert__icon')}<div class="ignis-alert__body">Transport: Ziel Adresse, E.-ab (7) und KH an (8) sind Pflicht.</div></div>` : ''}
                </div>
            </div>
            ${addrBox('von', 'Transport von / Einsatzort', 'fa-location-dot', vonReq)}
            ${addrBox('ziel', 'Transportziel', 'fa-hospital', zielReq)}
        </div>
        <div class="ignis-bezel nf-box ignis-tablet-stripe" data-live-state="rett:zeiten"${fillAttr(topicFill(p, 'rett', 'zeiten'))}>
            <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-clock')}</span><h3 class="ignis-bezel__title">Zeiten</h3></div>
            <div class="ignis-bezel__well ignis-tablet-times">${C.ZEITEN.map((z) => timeCell(p, z)).join('')}</div>
        </div>
    </div>`;
}

const ZEIT_REQ = {
    alarm: () => true, ende: () => true,
    patan: (p) => !C.isFehl(p), eab: (p) => C.isTransport(p), khan: (p) => C.isTransport(p),
};
function timeCell(p, z) {
    const v = p.zeiten[z.key];
    const req = ZEIT_REQ[z.key]?.(p) || false;
    const empty = !v?.t;
    return `<button type="button" class="ignis-tablet-time${empty ? ' is-empty' : ''}${empty && req ? ' is-missing' : ''}" data-act="time" data-key="${z.key}" aria-label="${esc(z.label)}: ${empty ? 'leer, antippen setzt jetzt' : esc(v.t + ' ' + v.d)}">
        <span class="ignis-tablet-time__label">${esc(z.label)}</span>
        <span class="ignis-tablet-time__date">${empty ? '&nbsp;' : esc(v.d)}</span>
        <span class="ignis-tablet-time__value ignis-mono">${empty ? '--:--' : esc(v.t)}</span>
        ${empty && z.mark ? `<span class="ignis-tablet-time__mark">${req ? missChip(`${z.label} fehlt`) : '<span class="ignis-dot"></span>leer'}</span>` : `<span class="ignis-tablet-time__mark nf-time__mark--ghost">${empty ? 'jetzt' : '&nbsp;'}</span>`}
    </button>`;
}

function addressView(p, key) {
    if (!ui.draft || ui.draft.ctx !== `${p.enr}:${key}`) ui.draft = { ctx: `${p.enr}:${key}`, a: structuredClone(p[key]), poiOpen: false };
    const a = ui.draft.a;
    const q = (a.poi || '').toLowerCase();
    const hits = ui.draft.poiOpen && q.length ? C.POIS.filter((x) => (x.name + ' ' + x.strasse + ' ' + x.ort).toLowerCase().includes(q)).slice(0, 6) : [];
    const d = (path, label, ph = '', extra = '') => `<label class="ignis-field nf-field"><span class="ignis-field__label">${esc(label)}</span>
        <input class="ignis-input" data-bind="d:a.${path}" value="${esc(a[path] || '')}" placeholder="${esc(ph)}" ${extra}${dis(p)}></label>`;
    const srLabel = key === 'von' ? 'SR Anfahrt (von)' : 'SR Transport (ziel)';
    const sr = a.sr || '-';
    return `<div class="ignis-tablet-view">
        <div class="ignis-tablet-view__body" data-scroll="pane">
            ${paneHead(key === 'von' ? 'Transport von / Einsatzort' : 'Transportziel')}
            <div class="ignis-bezel nf-box nf-box--narrow">
                <div class="ignis-bezel__well nf-form">
                    <div class="nf-poi">
                        <label class="ignis-field nf-field"><span class="ignis-field__label">Objekt / POI / Einrichtung</span>
                            <span class="ignis-input-group"><i class="fa-solid fa-magnifying-glass ignis-input-group__icon" aria-hidden="true"></i>
                            <input class="ignis-input" id="nf-poi" autocomplete="off" data-bind="d:a.poi" data-live="poi" value="${esc(a.poi || '')}" placeholder="Name suchen …" role="combobox" aria-expanded="${hits.length ? 'true' : 'false'}" aria-controls="nf-poi-list"${dis(p)}></span>
                        </label>
                        <div class="nf-poi__list" id="nf-poi-list" role="listbox" ${hits.length ? '' : 'hidden'}>${poiHits(hits)}</div>
                    </div>
                    <div class="nf-form__row">${d('strasse', 'Straße', 'Straße')}<div class="nf-form__row nf-form__row--tight">${d('hnr', 'HNR', '12')}${d('plz', 'Postleitzahl', '34512', 'inputmode="numeric" maxlength="5"')}</div></div>
                    <div class="nf-form__row">${d('ort', 'Ort', 'Ort')}${d('ortsteil', 'Ortsteil', 'Ortsteil')}</div>
                    <div class="nf-form__row">
                        <div class="ignis-field nf-field"><span class="ignis-field__label">${esc(srLabel)}</span>
                            <button type="button" class="nf-tri" data-act="sr" data-state="${esc(sr)}"${dis(p)} aria-label="${esc(srLabel)}: ${esc(sr)}. Tippen wechselt - → nein → ja">
                                <span class="nf-tri__steps" aria-hidden="true">${['-', 'nein', 'ja'].map((s) => `<span class="nf-tri__step${s === sr ? ' is-on' : ''}">${s}</span>`).join('')}</span>
                                <span class="nf-tri__hint">${sr === '-' ? 'nicht angegeben' : sr === 'ja' ? 'mit Sonderrechten' : 'ohne Sonderrechte'}</span>
                            </button>
                        </div>
                        <label class="ignis-field nf-field"><span class="ignis-field__label">${key === 'von' ? 'Von' : 'Ziel'} Einrichtung</span>
                            <input class="ignis-input" data-bind="d:a.einrichtung" value="${esc(a.einrichtung || '')}" placeholder="optional"${dis(p)}></label>
                    </div>
                    <div class="nf-kv nf-kv--preview"><span class="nf-kv__k">${key === 'von' ? 'Von' : 'Ziel'} Adresse</span><span class="nf-kv__v" id="nf-addr-preview">${esc(C.addressLine(a)) || '<span class="nf-dim">Straße HNR, Ort-Ortsteil</span>'}</span></div>
                </div>
            </div>
        </div>
        ${bar(`<a class="ignis-btn ignis-btn--secondary ignis-btn--lg" href="${href('p', p.enr, 'rett')}" data-act="addr-back">${fa('fa-arrow-left')}zurück</a>`,
            `<button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="addr-save" data-key="${key}"${dis(p)}>${fa('fa-floppy-disk')}speichern</button>`)}
    </div>`;
}
function poiHits(hits) {
    return hits.map((h, i) => `<button type="button" class="nf-poi__hit" role="option" data-act="poi" data-i="${C.POIS.indexOf(h)}" id="nf-poi-${i}">
        <b>${esc(h.name)}</b><span>${esc(`${h.strasse} ${h.hnr}, ${h.plz} ${h.ort}${h.ortsteil ? '-' + h.ortsteil : ''}`)}</span></button>`).join('');
}

/* ══ Erstbefund: Sonderansichten ═══════════════════════════════ */

function pupillenPane(p) {
    const col = (path, title, opts) => `<div class="nf-pcol"><div class="nf-pcol__title">${esc(title)} ${starred(p, path) && !isSet(get(p, path)) ? missChip() : ''}</div>
        <div class="ignis-tablet-options ignis-tablet-options--list">${opts.map(C.parseOpt).map((o) => optButton({ act: 'pick', path, val: o.label, label: o.label, normal: o.normal, pressed: get(p, path) === o.label })).join('')}</div></div>`;
    return `${paneHead('Neurologie · Pupillen')}
        <div class="ignis-tablet-group"><div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">Pupillenweite</span></div>
            <div class="nf-pcols">${col('erst.pupL', 'links', C.OPT.pupille)}${col('erst.pupR', 'rechts', C.OPT.pupille)}</div></div>
        <div class="ignis-tablet-group"><div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">Lichtreaktion</span></div>
            <div class="nf-pcols">${col('erst.lichtL', 'links', C.OPT.licht)}${col('erst.lichtR', 'rechts', C.OPT.licht)}</div></div>`;
}

function gcsPane(p) {
    const g = gcsSum(p.erst);
    const partial = C.GCS.map((c) => c.options.find((o) => o[0] === p.erst[c.key])?.[1] || 0).reduce((a, b) => a + b, 0);
    const sev = g ? (g.sum <= 8 ? ['danger', 'fa-circle-exclamation', 'schwer'] : g.sum <= 12 ? ['warn', 'fa-triangle-exclamation', 'mittel'] : ['ok', 'fa-circle-check', 'leicht / normal']) : null;
    return `${paneHead('Neurologie · GCS', `<div class="nf-gcs-sum" aria-live="polite">
            <span class="nf-gcs-sum__label">GCS</span>
            <span class="nf-gcs-sum__value ignis-mono">${g ? g.sum : partial || '–'}</span><span class="nf-gcs-sum__max">/ 15</span>
            ${sev ? `<span class="ignis-chip" data-tone="${sev[0]}">${fa(sev[1])}${sev[2]}</span>` : '<span class="ignis-chip">unvollständig</span>'}
        </div>`)}
        <div class="nf-gcs">${C.GCS.map((c) => `<div class="nf-pcol">
            <div class="nf-pcol__title">${esc(c.label)} <span class="nf-dim ignis-mono">${p.erst[c.key] ? c.options.find((o) => o[0] === p.erst[c.key])[1] : '–'}</span></div>
            <div class="ignis-tablet-options ignis-tablet-options--list">${c.options.map(([lab, sc], i) => optButton({ act: 'pick', path: `erst.${c.key}`, val: lab, label: lab, score: sc, normal: i === 0, pressed: p.erst[c.key] === lab })).join('')}</div>
        </div>`).join('')}</div>`;
}

const BODY = {
    head: '<ellipse cx="80" cy="32" rx="21" ry="25"/>',
    neck: '<rect x="71" y="54" width="18" height="14" rx="5"/>',
    thorax: '<path d="M48 70 Q80 61 112 70 Q121 74 121 86 L117 146 L43 146 L39 86 Q39 74 48 70 Z"/>',
    abdomen: '<path d="M43 150 L117 150 L116 206 Q80 220 44 206 Z"/>',
    armL: '<path d="M35 76 Q25 80 23 95 L15 196 Q14 209 23 211 Q31 212 32 201 L41 104 Z"/>',
    armR: '<path d="M125 76 Q135 80 137 95 L145 196 Q146 209 137 211 Q129 212 128 201 L119 104 Z"/>',
    legL: '<path d="M46 212 Q62 223 78 221 L74 340 Q73 351 64 351 L57 351 Q50 350 50 340 Z"/>',
    legR: '<path d="M114 212 Q98 223 82 221 L86 340 Q87 351 96 351 L103 351 Q110 350 110 340 Z"/>',
    spine: '<rect x="73" y="62" width="14" height="150" rx="7"/>',
};
function zone(p, key, shapes, label) {
    const v = p.erst.verl?.[key];
    const sev = v?.sev ? C.SEVERITY_KEY[v.sev] : '';
    const sel = ui.bodySel === key;
    return `<g class="ignis-tablet-zone${sel ? ' is-selected' : ''}" data-act="zone" data-key="${key}" role="button" tabindex="0" aria-pressed="${sel ? 'true' : 'false'}" aria-label="${esc(label)}: ${esc(v?.sev || 'nicht erhoben')}" ${sev ? `data-severity="${sev}"` : ''}>${shapes}</g>`;
}
function bodyMapPane(p) {
    const lab = Object.fromEntries(C.REGIONS.map((r) => [r.key, r.label]));
    const v = p.erst.verl?.[ui.bodySel] || {};
    const front = `<svg class="ignis-tablet-bodymap__svg" viewBox="0 0 160 360" role="group" aria-label="Körper Vorderseite">
        <g class="ignis-tablet-bodymap__base">${BODY.neck}</g>
        ${zone(p, 'schaedel', BODY.head, lab.schaedel)}${zone(p, 'thorax', BODY.thorax, lab.thorax)}${zone(p, 'abdomen', BODY.abdomen, lab.abdomen)}
        ${zone(p, 'oex', BODY.armL + BODY.armR, lab.oex)}${zone(p, 'uex', BODY.legL + BODY.legR, lab.uex)}
    </svg>`;
    const back = `<svg class="ignis-tablet-bodymap__svg" viewBox="0 0 160 360" role="group" aria-label="Körper Rückseite">
        <g class="ignis-tablet-bodymap__base">${BODY.head}${BODY.neck}${BODY.thorax}${BODY.abdomen}${BODY.armL}${BODY.armR}${BODY.legL}${BODY.legR}</g>
        ${zone(p, 'ws', BODY.spine, lab.ws)}
    </svg>`;
    const sevBtn = (s) => `<button type="button" class="ignis-tablet-option" aria-pressed="${v.sev === s}" data-act="sev" data-val="${s}"${dis(p)}><span class="ignis-tablet-severity" data-severity="${C.SEVERITY_KEY[s]}"><span class="ignis-tablet-severity__dot"></span></span><span class="ignis-tablet-option__label">${s}</span></button>`;
    return `${paneHead('Erweitern · Verletzungen')}
    <div class="ignis-tablet-bodymap">
        <div class="ignis-tablet-bodymap__maps">
            <figure class="ignis-tablet-bodymap__figure">${front}<figcaption>Vorderseite</figcaption></figure>
            <figure class="ignis-tablet-bodymap__figure">${back}<figcaption>Rückseite</figcaption></figure>
        </div>
        <div class="ignis-tablet-bodymap__panel">
            <div class="ignis-bezel nf-box">
                <div class="ignis-bezel__head"><h3 class="ignis-bezel__title">${esc(lab[ui.bodySel])}</h3></div>
                <div class="ignis-bezel__well nf-form">
                    <div><div class="ignis-micro">SCHWEREGRAD</div><div class="nf-sevs" role="group" aria-label="Schweregrad">${C.SEVERITY.map(sevBtn).join('')}</div></div>
                    ${v.sev && v.sev !== 'Keine' ? `<div><div class="ignis-micro">WUNDART</div><div class="nf-sevs" role="group" aria-label="Wundart">${C.WUNDART.map((w) => `<button type="button" class="ignis-tablet-option" aria-pressed="${v.wund === w}" data-act="wund" data-val="${w}"${dis(p)}><span class="ignis-tablet-option__mark"></span><span class="ignis-tablet-option__label">${w}</span></button>`).join('')}</div></div>` : ''}
                </div>
            </div>
            <div class="ignis-tablet-bodymap__list" role="list">
                ${C.REGIONS.map((r) => {
                    const rv = p.erst.verl?.[r.key];
                    return `<button type="button" class="ignis-tablet-bodymap__row" role="listitem" data-act="zone" data-key="${r.key}" aria-pressed="${ui.bodySel === r.key}">
                        <span class="ignis-tablet-severity"${rv?.sev ? ` data-severity="${C.SEVERITY_KEY[rv.sev]}"` : ''}><span class="ignis-tablet-severity__dot"></span></span>
                        <span class="nf-bm__name">${esc(r.label)}</span><span class="ignis-tablet-bodymap__value">${rv?.sev ? esc(rv.sev + (rv.wund ? ' · ' + rv.wund : '')) : '—'}</span></button>`;
                }).join('')}
            </div>
        </div>
    </div>`;
}

/* ══ Tastenfeld (Messwerte, Verlauf) ════════════════════════════ */

function kpInit(p, mode) {
    const ctx = `${mode}:${p.enr}`;
    if (ui.kp && ui.kp.ctx === ctx) return ui.kp;
    const now = new Date();
    ui.kp = {
        ctx, mode, active: null,
        values: mode === 'erst' ? structuredClone(p.erst.vitals || {}) : {},
        date: C.fmtDate(now), time: C.fmtTime(now),
    };
    return ui.kp;
}

function vbox(p, kp, key, { inner = false } = {}) {
    const v = C.VITAL[key];
    const val = kp.values[key] ?? '';
    const g = C.gradeVital(key, num(val));
    const req = kp.mode === 'erst' && v.req && !C.isFehl(p);
    const active = (kp.active || 'spo2') === key && kp.active !== null;
    const display = val === 'ng' ? 'nicht gemessen' : val === 'nm' ? 'nicht messbar' : val;
    return `<button type="button" class="ignis-tablet-value${inner ? ' ignis-tablet-value--inner' : ''}${active ? ' is-active' : ''}${req && !isSet(val) ? ' is-missing' : ''}${val === 'ng' || val === 'nm' ? ' is-word' : ''}"
        data-act="kp-focus" data-key="${key}" aria-pressed="${active}" ${g ? `data-grade="${g}"` : ''} aria-label="${esc(v.long)} ${esc(display || 'leer')} ${esc(v.unit)}">
        <span class="ignis-tablet-value__head"><span class="ignis-tablet-value__label">${esc(v.label)}</span>${inner ? '' : `<span class="ignis-tablet-value__unit">${esc(v.unit)}</span>`}</span>
        <span class="ignis-tablet-value__value ignis-mono">${display ? esc(display) : `<span class="ignis-tablet-value__placeholder">${esc(v.ph)}</span>`}</span>
        <span class="ignis-tablet-value__foot">${req && !isSet(val) ? missChip(`${v.long} fehlt`) : '<span class="nf-dim">&nbsp;</span>'}<span class="ignis-tablet-value__norm">Norm ${esc(v.norm.replace(` ${v.unit}`, ''))}</span></span>
    </button>`;
}

function strip(kp) {
    const key = kp.active || 'spo2';
    const v = C.VITAL[key];
    const [lo, hi] = v.scale;
    const segs = [];
    const steps = 140;
    for (let i = 0; i < steps; i++) {
        const x = lo + ((hi - lo) * (i + 0.5)) / steps;
        const g = C.gradeVital(key, key === 'temp' ? Math.round(x * 10) / 10 : Math.round(x));
        if (segs.length && segs[segs.length - 1].g === g) segs[segs.length - 1].n++;
        else segs.push({ g, n: 1 });
    }
    const val = num(kp.values[key]);
    const pos = val === null ? null : Math.max(0, Math.min(1, (val - lo) / (hi - lo)));
    const g = val === null ? null : C.gradeVital(key, val);
    return `<div class="ignis-tablet-range" aria-hidden="true">
        <span class="ignis-tablet-range__title">${esc(v.label)}</span>
        <span class="ignis-tablet-range__hi ignis-mono">${hi}</span>
        <div class="ignis-tablet-range__track">
            <div class="ignis-tablet-range__bar">${segs.reverse().map((s) => `<span class="ignis-tablet-range__seg" data-grade="${s.g}" style="flex:${s.n}"></span>`).join('')}</div>
            ${pos !== null ? `<span class="ignis-tablet-range__indicator" data-grade="${g}" style="bottom:${(pos * 100).toFixed(1)}%"><span class="ignis-tablet-range__value ignis-mono">${esc(kp.values[key])}</span></span>` : ''}
        </div>
        <span class="ignis-tablet-range__lo ignis-mono">${lo}</span>
    </div>`;
}

function keypad() {
    const k = (val, label, cls = '') => `<button type="button" class="ignis-tablet-key${cls}" data-act="kp" data-k="${val}">${label}</button>`;
    return `<div class="ignis-tablet-pad" role="group" aria-label="Tastenfeld">
        ${['7', '8', '9', '4', '5', '6', '1', '2', '3'].map((d) => k(d, d)).join('')}
        ${k(',', ',')}${k('0', '0')}${k('back', `${fa('fa-delete-left')}<span class="ignis-sr-only">Rücktaste</span>`, ' ignis-tablet-key--fn')}
        ${k('clear', 'Löschen', ' ignis-tablet-key--wide ignis-tablet-key--fn')}
        ${k('ng', 'nicht gemessen', ' ignis-tablet-key--wide ignis-tablet-key--word')}
        ${k('nm', 'nicht messbar', ' ignis-tablet-key--wide ignis-tablet-key--word')}
    </div>`;
}

function keypadView(p, { mode, topics }) {
    const kp = kpInit(p, mode);
    const head = mode === 'erst'
        ? paneHead('Erstbefund · Messwerte')
        : paneHead('Verlauf · Werte hinzufügen', `<div class="nf-inline">
            <label class="ignis-field nf-field nf-field--inline"><span class="ignis-field__label">Datum</span><input class="ignis-input ignis-mono nf-input-date" data-bind="k:date" data-mask="date" maxlength="10" inputmode="numeric" value="${esc(kp.date)}"></label>
            <label class="ignis-field nf-field nf-field--inline"><span class="ignis-field__label">Uhrzeit</span><input class="ignis-input ignis-mono nf-input-time" data-bind="k:time" data-mask="time" maxlength="5" inputmode="numeric" value="${esc(kp.time)}"></label>
        </div>`);
    const body = `<div class="ignis-tablet-view">
        <div class="ignis-tablet-view__body nf-kp-body">
            ${head}
            <div class="nf-kp">
                <div class="nf-kp__grid">
                    ${vbox(p, kp, 'spo2')}${vbox(p, kp, 'af')}${vbox(p, kp, 'etco2')}
                    ${vbox(p, kp, 'hf')}
                    <div class="ignis-tablet-value-pair${['rrs', 'rrd'].includes(kp.active) ? ' is-active' : ''}">
                        <span class="ignis-tablet-value-pair__title">NIBP / RR <span class="ignis-tablet-value__unit">mmHg</span></span>
                        <div class="ignis-tablet-value-pair__pair">${vbox(p, kp, 'rrs', { inner: true })}<span class="ignis-tablet-value-pair__slash" aria-hidden="true">/</span>${vbox(p, kp, 'rrd', { inner: true })}</div>
                    </div>
                    ${vbox(p, kp, 'bz')}${vbox(p, kp, 'temp')}
                </div>
                ${strip(kp)}
                ${keypad()}
            </div>
        </div>
        ${bar(`<button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--lg" data-act="kp-cancel">${fa('fa-xmark')}Abbrechen</button>`,
            `<button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="kp-save"${dis(p)}>${fa('fa-floppy-disk')}Speichern</button>`)}
    </div>`;
    if (mode === 'erst') return `<div class="ignis-tablet-section">${topics}<section class="ignis-tablet-pane ignis-tablet-pane--flush">${body}</section></div>`;
    return body;
}

function kpPress(k) {
    const kp = ui.kp;
    if (!kp) return;
    if (ro()) { guardRO(); return; }
    if (!kp.active) kp.active = 'spo2';
    const key = kp.active;
    let v = kp.values[key] ?? '';
    if (v === 'ng' || v === 'nm') v = '';
    let ok = true;
    if (/^\d$/.test(k)) {
        const [a, b] = v.split(',');
        if (b === undefined) {
            if (a === '0') v = k;
            else if (a.length >= 3) ok = false;
            else v = a + k;
        } else if (b.length >= 2) ok = false;
        else v += k;
    } else if (k === ',') {
        if (v === '') v = '0,';
        else if (v.includes(',')) ok = false;
        else v += ',';
    } else if (k === 'back') v = v.slice(0, -1);
    else if (k === 'clear') v = '';
    else if (k === 'ng' || k === 'nm') v = k;
    if (!ok) {
        const el = app.querySelector(`.ignis-tablet-value[data-key="${key}"]`);
        if (el) { el.classList.remove('is-deny'); void el.offsetWidth; el.classList.add('is-deny'); }
        return;
    }
    kp.values[key] = v;
    render();
}

/* ══ Anamnese ═══════════════════════════════════════════════════ */

function lineCount(t) {
    return (t || '').split('\n').reduce((n, l) => n + Math.max(1, Math.ceil(l.length / 95)), 0);
}
function freitextPane(p) {
    const t = p.anam.text || '';
    const many = lineCount(t) > 22;
    return `${paneHead('Anamnese · Freitext', `<span class="ignis-tablet-pane__sub ignis-mono" id="nf-lines">${lineCount(t)} / 22 Zeilen</span>`)}
    <div class="nf-free">
        <div class="nf-free__main">
            <textarea class="ignis-textarea nf-free__text" id="nf-freitext" data-bind="p:anam.text" data-live="lines" rows="14" placeholder="Anamnese, Situation bei Eintreffen, Angaben von Angehörigen …"${dis(p)}>${esc(t)}</textarea>
            <div class="ignis-alert ignis-alert--warn" id="nf-lines-warn" ${many ? '' : 'hidden'}>${fa('fa-triangle-exclamation', 'ignis-alert__icon')}<div class="ignis-alert__body">Der Text ist länger als 22 Zeilen – ggf. wird nicht der komplette Text im Protokoll-Ausdruck sichtbar sein.</div></div>
            <div class="nf-inline nf-inline--end"><button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="anam-ok"${dis(p)}>${fa('fa-check')}OK</button></div>
        </div>
        <div class="nf-free__blocks">
            <div class="nf-tb" role="group" aria-label="Textbausteine">${C.TEXTBLOCKS.map((b, bi) => `<button type="button" class="nf-tb__btn" data-act="textblock" data-b="${bi}" aria-expanded="${ui.tbOpen === bi}"${dis(p)}><span>${esc(b.label)}</span>${fa('fa-chevron-right', 'nf-tb__go')}</button>`).join('')}</div>
            ${C.TEXTBLOCKS.map((b, bi) => `<div class="nf-tb nf-tb--sub" data-tb-sub="${bi}" role="group" aria-label="${esc(b.label)}" ${ui.tbOpen === bi ? '' : 'hidden'}>${b.sub.map((x, xi) => `<button type="button" class="nf-tb__btn" data-act="textsub" data-b="${bi}" data-i="${xi}"${dis(p)}>${esc(x.label)}</button>`).join('')}</div>`).join('')}
        </div>
    </div>`;
}

/** Fügt Text an der Cursorposition der Anamnese ein. newline: 0 ohne, 1 davor, 2 davor und danach. */
function insertAtCursor(text, newline) {
    const ta = document.getElementById('nf-freitext');
    if (!ta) return;
    const pos = ta.selectionStart ?? ta.value.length;
    const before = ta.value.slice(0, pos);
    const ins = (newline >= 1 && before.length && !before.endsWith('\n') ? '\n' : '') + text + (newline >= 2 ? '\n' : '');
    ta.value = before + ins + ta.value.slice(ta.selectionEnd ?? pos);
    ta.selectionStart = ta.selectionEnd = pos + ins.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', { bubbles: true }));
}

function symptomPane(p) {
    const a = p.anam;
    return `${paneHead('Symptome · Symptombeginn')}
    <div class="ignis-bezel nf-box nf-box--narrow">
        <div class="ignis-bezel__well nf-form">
            <div class="nf-form__row">
                <label class="ignis-field nf-field"><span class="ignis-field__label">Datum</span>
                    <input class="ignis-input ignis-mono" data-bind="p:anam.symDatum" data-mask="date" maxlength="10" inputmode="numeric" placeholder="TT.MM.JJJJ" value="${esc(a.symDatum || '')}"${dis(p)}></label>
                <div class="ignis-field nf-field"><span class="ignis-field__label">&nbsp;</span>
                    <button type="button" class="ignis-tablet-option ignis-tablet-option--multi" aria-pressed="${!!a.symGeschaetzt}" data-act="bool" data-path="anam.symGeschaetzt"${dis(p)}><span class="ignis-tablet-option__mark"></span><span class="ignis-tablet-option__label">geschätzt</span></button></div>
            </div>
            <div class="nf-form__row">
                <label class="ignis-field nf-field"><span class="ignis-field__label">Zeit</span>
                    <input class="ignis-input ignis-mono" data-bind="p:anam.symZeit" data-live="symzeit" data-mask="time" maxlength="5" inputmode="numeric" placeholder="HH:MM" value="${esc(a.symNicht ? '' : a.symZeit || '')}"${a.symNicht ? ' disabled' : dis(p)}></label>
                <div class="ignis-field nf-field"><span class="ignis-field__label">&nbsp;</span>
                    <button type="button" class="ignis-tablet-option ignis-tablet-option--multi" aria-pressed="${!!a.symNicht}" data-act="symnicht"${dis(p)}><span class="ignis-tablet-option__mark"></span><span class="ignis-tablet-option__label">nicht feststellbar</span></button></div>
            </div>
            <div class="nf-inline nf-inline--end"><button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="sym-save"${dis(p)}>${fa('fa-floppy-disk')}Speichern</button></div>
        </div>
    </div>`;
}

/* ══ Diagnose ═══════════════════════════════════════════════════ */

function diagSelected(p, topic) {
    if (topic.mode === 'single') return p.diag.fuehrend ? `<div class="nf-diag-sel"><span class="ignis-micro">führend</span><span class="ignis-chip ignis-chip--lg">${fa('fa-star')}${esc(p.diag.fuehrend)}</span></div>` : `<div class="nf-diag-sel"><span class="ignis-micro">führend</span>${missChip('keine Auswahl')}</div>`;
    return `<div class="nf-diag-sel"><span class="ignis-micro">weitere (${p.diag.weitere.length})</span>${p.diag.weitere.map((d) => `<span class="ignis-chip ignis-chip--lg ignis-chip--removable">${esc(d)}<button type="button" class="ignis-chip__remove" data-act="diag-remove" data-val="${esc(d)}" aria-label="${esc(d)} entfernen"${dis(p)}>×</button></span>`).join('') || '<span class="nf-dim">keine</span>'}</div>`;
}
function diagOption(p, topic, label) {
    const single = topic.mode === 'single';
    const pressed = single ? p.diag.fuehrend === label : p.diag.weitere.includes(label);
    const isLead = !single && p.diag.fuehrend === label;
    return optButton({ act: 'diag', path: topic.key, val: label, label: isLead ? `${label} (führend)` : label, pressed, multi: !single, extraAttr: isLead ? 'disabled' : '' });
}
function diagResults(p, topic) {
    const q = ui.diagQ.trim().toLowerCase();
    const hits = C.allDiagnoses().filter((d) => d.label.toLowerCase().includes(q)).slice(0, 20);
    if (!hits.length) return emptyBlock({ icon: 'fa-magnifying-glass', title: 'Keine Diagnose gefunden.', text: `Kein Treffer für „${ui.diagQ}“.` });
    return `<div class="ignis-tablet-group"><div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">${hits.length === 20 ? 'Die ersten 20 Treffer' : `${hits.length} Treffer`}</span></div>
        <div class="ignis-tablet-options nf-opts--two">${hits.map((h) => diagOption(p, topic, h.label)).join('')}</div></div>`;
}
function diagPane(p, topic, sub) {
    const cat = C.DIAG_CATS.find((c) => c.key === sub?.key) || C.DIAG_CATS[0];
    let list;
    if (cat.trauma) {
        list = C.TRAUMA_REGIONS.map((r) => `<div class="nf-trauma">
            <span class="nf-trauma__region">${esc(r)}</span>
            <div class="ignis-tablet-options ${r === 'spezielle' ? 'nf-opts--two' : 'ignis-tablet-options--row'}">${C.traumaOptions(r).map((label) => {
                const short = label.split(' – ')[1];
                const single = topic.mode === 'single';
                const pressed = single ? p.diag.fuehrend === label : p.diag.weitere.includes(label);
                return optButton({ act: 'diag', path: topic.key, val: label, label: short, pressed, multi: !single, extraAttr: !single && p.diag.fuehrend === label ? 'disabled' : '' });
            }).join('')}</div></div>`).join('');
    } else {
        list = `<div class="ignis-tablet-options nf-opts--two">${cat.items.map((d) => diagOption(p, topic, d)).join('')}</div>`;
    }
    return `${paneHead(`${topic.label} · ${cat.label}`)}
        ${diagSelected(p, topic)}
        <label class="ignis-input-group nf-search"><i class="fa-solid fa-magnifying-glass ignis-input-group__icon" aria-hidden="true"></i>
            <input class="ignis-input" type="search" id="nf-diag-q" placeholder="Suchen…" value="${esc(ui.diagQ)}" data-live="diagq" autocomplete="off" aria-label="Diagnose suchen"></label>
        <div id="nf-diag-results">${ui.diagQ.trim() ? diagResults(p, topic) : `<div class="ignis-tablet-group">${list}</div>`}</div>`;
}
function diagTextPane(p) {
    return `${paneHead('Diagnose Text')}
    <div class="ignis-bezel nf-box">
        <div class="ignis-bezel__head"><h3 class="ignis-bezel__title">Freitext Diagnose</h3></div>
        <div class="ignis-bezel__well"><textarea class="ignis-textarea nf-free__text" rows="8" data-bind="p:diag.text" placeholder="z. B. Verdachtsdiagnose, Differenzialdiagnosen"${dis(p)}>${esc(p.diag.text || '')}</textarea></div>
    </div>`;
}

/* ══ Verlauf ════════════════════════════════════════════════════ */

const SERIES = [
    { key: 'rrs', label: 'RR systolisch', sym: 'circle' },
    { key: 'rrd', label: 'RR diastolisch', sym: 'square' },
    { key: 'hf', label: 'Herzfrequenz', sym: 'triangle' },
    { key: 'bz', label: 'Blutzucker', sym: 'diamond' },
    { key: 'etco2', label: 'etCO₂', sym: 'tdown' },
    { key: 'spo2', label: 'SpO₂', sym: 'ring' },
    { key: 'af', label: 'Atemfrequenz', sym: 'sqopen' },
    { key: 'temp', label: 'Temperatur', sym: 'cross' },
];
function symbol(type, x, y, s = 4.5) {
    const f = (n) => n.toFixed(1);
    switch (type) {
        case 'circle': return `<circle cx="${f(x)}" cy="${f(y)}" r="${s}"/>`;
        case 'square': return `<rect x="${f(x - s)}" y="${f(y - s)}" width="${2 * s}" height="${2 * s}" rx="1"/>`;
        case 'triangle': return `<path d="M${f(x)} ${f(y - s * 1.25)} L${f(x + s * 1.2)} ${f(y + s * 0.85)} L${f(x - s * 1.2)} ${f(y + s * 0.85)} Z"/>`;
        case 'tdown': return `<path d="M${f(x)} ${f(y + s * 1.25)} L${f(x + s * 1.2)} ${f(y - s * 0.85)} L${f(x - s * 1.2)} ${f(y - s * 0.85)} Z"/>`;
        case 'diamond': return `<path d="M${f(x)} ${f(y - s * 1.35)} L${f(x + s * 1.35)} ${f(y)} L${f(x)} ${f(y + s * 1.35)} L${f(x - s * 1.35)} ${f(y)} Z"/>`;
        case 'ring': return `<circle class="is-open" cx="${f(x)}" cy="${f(y)}" r="${s}"/>`;
        case 'sqopen': return `<rect class="is-open" x="${f(x - s)}" y="${f(y - s)}" width="${2 * s}" height="${2 * s}" rx="1"/>`;
        case 'cross': return `<path class="is-stroke" d="M${f(x - s)} ${f(y)} H${f(x + s)} M${f(x)} ${f(y - s)} V${f(y + s)}"/>`;
        default: return '';
    }
}
const legendSwatch = (s, i) => `<svg class="nf-swatch nf-s${i + 1}" width="22" height="14" viewBox="0 0 22 14" aria-hidden="true"><line x1="1" y1="7" x2="21" y2="7"/>${symbol(s.sym, 11, 7, 4)}</svg>`;

function verlaufOverview(p) {
    const entries = [...p.verlauf].sort((a, b) => new Date(a.at) - new Date(b.at));
    const values = entries.reduce((n, e) => n + Object.values(e.values).filter(isSet).length, 0);
    const head = `<div class="ignis-tablet-pane__head"><h2 class="ignis-tablet-pane__title">Verlauf</h2>
        <div class="ignis-tablet-pane__actions">
            <a class="ignis-btn ignis-btn--secondary" href="${href('p', p.enr, 'verlauf', 'liste')}">${fa('fa-pen')}Verlauf bearbeiten</a>
            ${ro(p) ? '' : `<a class="ignis-btn ignis-btn--primary" href="${href('p', p.enr, 'verlauf', 'neu')}">${fa('fa-plus')}Werte hinzufügen</a>`}
        </div></div>
        <div class="ignis-statusline nf-mb">
            <span class="ignis-chip"><i class="fa-solid fa-list-ol" data-tone="info"></i>${values} Einzelwerte erfasst</span>
            <span class="ignis-chip"><i class="fa-solid fa-clock" data-tone="info"></i>${entries.length} Zeitpunkte dokumentiert</span>
        </div>`;
    if (!entries.length) {
        return `<div class="ignis-tablet-pane ignis-tablet-pane--solo" data-scroll="pane">${head}
            <div class="ignis-bezel"><div class="ignis-bezel__well">${emptyBlock({ icon: 'fa-chart-line', title: 'Noch keine Vitalparameter dokumentiert', sm: false, actions: ro(p) ? '' : `<a class="ignis-btn ignis-btn--primary" href="${href('p', p.enr, 'verlauf', 'neu')}">${fa('fa-plus')}Werte hinzufügen</a>` })}</div></div></div>`;
    }
    const legend = SERIES.map((s, i) => {
        const has = entries.some((e) => num(e.values[s.key]) !== null);
        return `<button type="button" class="nf-legend__item" data-act="series" data-key="${s.key}" aria-pressed="${!ui.hidden.has(s.key)}" ${has ? '' : 'disabled'}>${legendSwatch(s, i)}<span>${esc(s.label)}</span></button>`;
    }).join('');
    const table = [...entries].reverse().map((e) => {
        const keys = C.VITALS.map((v) => v.key).filter((k) => isSet(e.values[k]));
        return `<tbody><tr class="nf-tgroup"><th colspan="3" scope="rowgroup">${esc(fmtDT(e.at))} Uhr (${keys.length} Parameter)</th></tr>
            ${keys.map((k) => `<tr><td>${esc(C.VITAL[k].long)}</td><td class="ignis-table__num ignis-mono">${vitalVal(k, e.values[k], e.values[k] === 'ng' ? 'nicht gemessen' : e.values[k] === 'nm' ? 'nicht messbar' : e.values[k])}</td><td class="nf-dim">${esc(C.VITAL[k].unit)}</td></tr>`).join('')}</tbody>`;
    }).join('');
    return `<div class="ignis-tablet-pane ignis-tablet-pane--solo" data-scroll="pane">${head}
        <div class="ignis-bezel nf-box">
            <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-chart-line')}</span><h3 class="ignis-bezel__title">Alle Vitalparameter</h3></div>
            <div class="nf-legend" role="group" aria-label="Reihen ein- und ausblenden">${legend}</div>
            <div class="ignis-bezel__well ignis-bezel__well--flush"><div class="nf-chart" id="nf-chart" data-enr="${esc(p.enr)}"></div></div>
        </div>
        <div class="ignis-bezel nf-box">
            <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-table')}</span><h3 class="ignis-bezel__title">Werte im Detail</h3></div>
            <div class="ignis-bezel__well ignis-bezel__well--flush"><table class="ignis-table nf-vtable"><thead><tr><th scope="col">Parameter</th><th scope="col" class="ignis-table__num">Wert</th><th scope="col">Einheit</th></tr></thead>${table}</table></div>
        </div>
    </div>`;
}

function drawChart() {
    const box = document.getElementById('nf-chart');
    if (!box) return;
    const p = S.protocols[box.dataset.enr];
    const entries = [...p.verlauf].sort((a, b) => new Date(a.at) - new Date(b.at));
    const W = Math.max(320, box.clientWidth);
    const H = 300;
    const m = { l: 46, r: 18, t: 16, b: 34 };
    const vis = SERIES.map((s, i) => ({ ...s, i })).filter((s) => !ui.hidden.has(s.key) && entries.some((e) => num(e.values[s.key]) !== null));
    const all = vis.flatMap((s) => entries.map((e) => num(e.values[s.key])).filter((v) => v !== null));
    const maxV = all.length ? Math.max(...all) : 100;
    const step = maxV > 150 ? 50 : maxV > 60 ? 20 : maxV > 20 ? 10 : 5;
    const top = Math.ceil((maxV * 1.08) / step) * step;
    const t0 = new Date(entries[0].at).getTime();
    const t1 = new Date(entries[entries.length - 1].at).getTime();
    const span = Math.max(t1 - t0, 60000);
    const pad0 = entries.length === 1 ? 0.5 : 0;
    const X = (t) => m.l + ((W - m.l - m.r) * (pad0 || (t - t0) / span));
    const Y = (v) => m.t + (H - m.t - m.b) * (1 - v / top);
    let svg = `<svg class="nf-chart__svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" role="img" aria-label="Verlauf aller Vitalparameter">`;
    for (let v = 0; v <= top; v += step) {
        svg += `<line class="nf-chart__grid" x1="${m.l}" x2="${W - m.r}" y1="${Y(v).toFixed(1)}" y2="${Y(v).toFixed(1)}"/>`;
        svg += `<text class="nf-chart__tick" x="${m.l - 8}" y="${(Y(v) + 4).toFixed(1)}" text-anchor="end">${v}</text>`;
    }
    const xs = entries.map((e) => X(new Date(e.at).getTime()));
    let lastX = -999;
    entries.forEach((e, i) => {
        if (xs[i] - lastX < 46 && i !== entries.length - 1) return;
        lastX = xs[i];
        const anchor = entries.length > 1 && i === 0 ? 'start' : entries.length > 1 && i === entries.length - 1 ? 'end' : 'middle';
        svg += `<text class="nf-chart__tick" x="${xs[i].toFixed(1)}" y="${H - 12}" text-anchor="${anchor}">${C.fmtTime(new Date(e.at))}</text>`;
    });
    svg += `<line class="nf-chart__cross" id="nf-cross" x1="0" x2="0" y1="${m.t}" y2="${H - m.b}" visibility="hidden"/>`;
    vis.forEach((s) => {
        const pts = entries.map((e, i) => [xs[i], num(e.values[s.key])]).filter((d) => d[1] !== null);
        const d = pts.map((pt, i) => `${i ? 'L' : 'M'}${pt[0].toFixed(1)} ${Y(pt[1]).toFixed(1)}`).join(' ');
        svg += `<g class="nf-series nf-s${s.i + 1}"><path class="nf-series__line" d="${d}"/>${pts.map((pt) => symbol(s.sym, pt[0], Y(pt[1]))).join('')}</g>`;
    });
    svg += `<rect class="nf-chart__hit" x="${m.l}" y="${m.t}" width="${W - m.l - m.r}" height="${H - m.t - m.b}"/></svg>`;
    if (!vis.length) svg = `<div class="nf-chart__none">${emptyBlock({ icon: 'fa-eye-slash', title: 'Alle Reihen ausgeblendet' })}</div>`;
    box.innerHTML = svg + '<div class="ignis-tooltip ignis-tooltip--chart nf-chart__tip" id="nf-tip" role="status"></div>';
    const hit = box.querySelector('.nf-chart__hit');
    if (!hit) return;
    const tip = box.querySelector('#nf-tip');
    const cross = box.querySelector('#nf-cross');
    const show = (clientX) => {
        const r = box.getBoundingClientRect();
        const x = clientX - r.left;
        let best = 0;
        xs.forEach((xx, i) => { if (Math.abs(xx - x) < Math.abs(xs[best] - x)) best = i; });
        const e = entries[best];
        cross.setAttribute('x1', xs[best]); cross.setAttribute('x2', xs[best]); cross.setAttribute('visibility', 'visible');
        box.querySelectorAll('.nf-series [data-hl]').forEach((n) => n.remove());
        const rows = vis.filter((s) => isSet(e.values[s.key])).map((s) => `<div class="ignis-tooltip__row">${legendSwatch(s, s.i)}<span>${esc(s.label)}</span><b>${esc(e.values[s.key] === 'ng' ? 'n. gem.' : e.values[s.key] === 'nm' ? 'n. messb.' : e.values[s.key])} ${esc(C.VITAL[s.key].unit)}</b></div>`).join('');
        tip.innerHTML = `<div class="ignis-tooltip__head">${esc(fmtDT(e.at))} Uhr</div>${rows || '<span class="nf-dim">keine sichtbaren Werte</span>'}`;
        tip.classList.add('is-visible');
        const tw = tip.offsetWidth;
        const left = xs[best] + 14 + tw > W ? xs[best] - 14 - tw : xs[best] + 14;
        tip.style.left = `${Math.max(4, left)}px`;
        tip.style.top = `${m.t}px`;
    };
    const hide = () => { tip.classList.remove('is-visible'); cross.setAttribute('visibility', 'hidden'); };
    hit.addEventListener('mousemove', (ev) => show(ev.clientX));
    hit.addEventListener('mouseleave', hide);
    hit.addEventListener('touchstart', (ev) => show(ev.touches[0].clientX), { passive: true });
    hit.addEventListener('touchmove', (ev) => show(ev.touches[0].clientX), { passive: true });
}

function verlaufList(p) {
    const entries = [...p.verlauf].sort((a, b) => new Date(b.at) - new Date(a.at));
    const items = entries.map((e) => {
        const keys = C.VITALS.map((v) => v.key).filter((k) => isSet(e.values[k]));
        return `<div class="ignis-bezel__item nf-vlist__item">
            <div class="nf-vlist__time"><span class="ignis-mono">${esc(C.fmtTime(new Date(e.at)))}</span><span class="nf-dim">${esc(C.fmtDate(new Date(e.at)))} · ${keys.length} Parameter</span></div>
            <div class="nf-vlist__vals">${keys.map((k) => `<span class="nf-vchip"><span class="nf-vchip__k">${esc(C.VITAL[k].label)}</span><span class="ignis-mono">${vitalVal(k, e.values[k])}</span>
                ${ro(p) ? '' : `<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon nf-vchip__del" data-act="vdel" data-id="${e.id}" data-key="${k}" aria-label="${esc(C.VITAL[k].label)} löschen">${fa('fa-trash-can')}</button>`}</span>`).join('')}</div>
        </div>`;
    }).join('');
    return `<div class="ignis-tablet-view"><div class="ignis-tablet-view__body" data-scroll="pane">
        <div class="ignis-tablet-pane__head"><h2 class="ignis-tablet-pane__title">Verlauf bearbeiten</h2>
            <div class="ignis-tablet-pane__actions"><a class="ignis-btn ignis-btn--secondary" href="${href('p', p.enr, 'verlauf')}">${fa('fa-arrow-left')}Zurück zur Übersicht</a>
            ${ro(p) ? '' : `<a class="ignis-btn ignis-btn--primary" href="${href('p', p.enr, 'verlauf', 'neu')}">${fa('fa-plus')}Neue Werte hinzufügen</a>`}</div></div>
        <div class="ignis-bezel"><div class="ignis-bezel__well ignis-bezel__well--list">${items || emptyBlock({ icon: 'fa-chart-line', title: 'Noch keine Vitalparameter dokumentiert' })}</div></div>
    </div></div>`;
}

/* ══ Maßnahmen ══════════════════════════════════════════════════ */

const zugLabel = (z) => `${z.type === 'pvk' ? 'PVK' : 'i.o.'} ${z.size} ${z.site}${z.side ? ' ' + z.side : ''}`;
const sizeMeta = (type, key) => (type === 'pvk' ? C.PVK_SIZES : C.IO_SIZES).find((s) => s.key === key);
const gaugeDot = (s) => `<span class="ignis-tablet-swatch${s.ring ? ' ignis-tablet-swatch--ring' : ''}" style="--swatch:${s.color}" aria-hidden="true"></span>`;

function zugangPane(p) {
    const m = p.mass;
    const type = ui.zugType;
    const sites = type === 'pvk' ? C.PVK_SITES : C.IO_SITES;
    const sizes = type === 'pvk' ? C.PVK_SIZES : C.IO_SIZES;
    const sel = ui.zugSel && ui.zugSel.type === type ? ui.zugSel : null;
    const find = (site, side) => m.zugaenge.find((z) => z.type === type && z.site === site && (z.side || '') === (side || ''));
    const cell = (site, side) => {
        const z = find(site, side);
        const s = z && sizeMeta(type, z.size);
        const isSel = sel && sel.site === site && (sel.side || '') === (side || '');
        return `<button type="button" class="nf-zcell${z ? ' is-set' : ''}" aria-pressed="${!!isSel}" data-act="zcell" data-site="${esc(site)}" data-side="${esc(side || '')}"${dis(p)}
            aria-label="${esc(site)}${side ? ' ' + side : ''}: ${z ? esc(z.size) : 'kein Zugang'}">
            ${z ? `${gaugeDot(s)}<span class="ignis-mono">${esc(s.short)}</span>` : `<i class="fa-solid fa-plus nf-dim" aria-hidden="true"></i><span class="nf-dim">${side ? esc(side) : 'setzen'}</span>`}
        </button>`;
    };
    const selZ = sel && find(sel.site, sel.side);
    return `${paneHead('Maßnahmen · Zugang')}
    <div class="nf-inline nf-mb">
        <button type="button" class="ignis-tablet-option ignis-tablet-option--multi nf-opt--auto" aria-pressed="${!!m.zugangNone}" data-act="zug-none"${dis(p)}><span class="ignis-tablet-option__mark"></span><span class="ignis-tablet-option__label">Kein Zugang</span></button>
        <div class="ignis-segmented ignis-segmented--switch nf-seg" role="group" aria-label="Zugangsart">
            <button type="button" aria-pressed="${type === 'pvk'}" data-act="zug-type" data-val="pvk">PVK</button>
            <button type="button" aria-pressed="${type === 'io'}" data-act="zug-type" data-val="io">intraossär</button>
        </div>
        ${m.zugaenge.length ? `<button type="button" class="ignis-btn ignis-btn--danger-soft" data-act="zug-clear"${dis(p)}>${fa('fa-trash-can')}Alle entfernen</button>` : ''}
    </div>
    <div class="nf-zug">
        <div class="nf-ztable" role="grid" aria-label="Punktionsorte">
            <div class="nf-ztable__row nf-ztable__row--head" role="row"><span role="columnheader">Ort</span><span role="columnheader">links</span><span role="columnheader">rechts</span></div>
            ${sites.map((s) => `<div class="nf-ztable__row" role="row"><span class="nf-ztable__site" role="rowheader">${esc(s.key)}</span>
                ${s.sides ? cell(s.key, 'links') + cell(s.key, 'rechts') : `<span class="nf-ztable__span">${cell(s.key, '')}</span>`}</div>`).join('')}
        </div>
        <div class="nf-zsize">
            ${sel ? `<div class="ignis-bezel nf-box">
                <div class="ignis-bezel__head"><h3 class="ignis-bezel__title">${esc(sel.site)}${sel.side ? ' ' + esc(sel.side) : ''}</h3></div>
                <div class="ignis-bezel__well nf-form">
                    <div class="ignis-micro">${type === 'pvk' ? 'Kanülengröße' : 'Nadellänge'}</div>
                    <div class="nf-sizes">${sizes.map((s) => `<button type="button" class="nf-size" aria-pressed="${selZ?.size === s.key}" data-act="zsize" data-val="${esc(s.key)}"${dis(p)}>${gaugeDot(s)}<span class="ignis-mono">${esc(s.key)}</span></button>`).join('')}</div>
                    ${selZ ? `<button type="button" class="ignis-btn ignis-btn--danger-soft ignis-btn--block" data-act="zdel"${dis(p)}>${fa('fa-trash-can')}Zugang entfernen</button>` : ''}
                </div></div>`
            : `<div class="ignis-bezel"><div class="ignis-bezel__well">${emptyBlock({ icon: 'fa-hand-pointer', title: 'Ort wählen' })}</div></div>`}
            ${m.zugaenge.length ? `<div class="nf-zlist"><div class="ignis-micro">Dokumentiert (${m.zugaenge.length})</div>${m.zugaenge.map((z) => `<div class="nf-zlist__item">${gaugeDot(sizeMeta(z.type, z.size))}<span>${esc(zugLabel(z))}</span></div>`).join('')}</div>` : ''}
        </div>
    </div>`;
}

function medsPane(p) {
    const m = p.mass;
    return `${paneHead('Maßnahmen · Medikamente')}
    <div class="nf-inline nf-mb">
        <button type="button" class="ignis-tablet-option ignis-tablet-option--multi nf-opt--auto" aria-pressed="${!!m.medsNone}" data-act="meds-none"${dis(p)}><span class="ignis-tablet-option__mark"></span><span class="ignis-tablet-option__label">Keine Medikamente</span></button>
        ${ro(p) ? '' : `<a class="ignis-btn ignis-btn--primary ignis-btn--lg" href="${href('p', p.enr, 'mass', 'meds', 'maske')}">${fa('fa-plus')}Medikament erfassen</a>`}
    </div>
    <div class="ignis-bezel">
        <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-pills')}</span><h3 class="ignis-bezel__title">Gegebene Medikamente</h3><span class="ignis-count">${m.meds.length}</span></div>
        <div class="ignis-bezel__well ignis-bezel__well--list">
            ${m.meds.length ? m.meds.map((x) => medRow(x)).join('') : emptyBlock({ icon: 'fa-pills', title: 'Keine Medikamente eingetragen', text: m.medsNone ? '„Keine Medikamente“ ist gesetzt.' : 'Über „Medikament erfassen“ hinzufügen oder „Keine Medikamente“ wählen.' })}
        </div>
    </div>`;
}
function medRow(x, { selectable = false, selected = false } = {}) {
    const inner = `<span class="ignis-glyph ignis-glyph--sm">${fa('fa-syringe')}</span>
        <span class="ignis-entry__title">${esc(x.wirkstoff)}</span>
        <span class="ignis-entry__meta"><span class="ignis-mono">${esc(x.t)}</span><span>${esc(x.dosis)} ${esc(x.einheit)}</span><span class="ignis-chip">${esc(x.appl)}</span></span>`;
    if (!selectable) return `<div class="ignis-bezel__item ignis-entry">${inner}</div>`;
    return `<button type="button" class="ignis-bezel__item ignis-entry nf-medrow" aria-pressed="${selected}" data-act="med-pick" data-id="${x.id}">${inner}</button>`;
}

function medMask(p) {
    if (!ui.med || ui.med.enr !== p.enr) ui.med = { enr: p.enr, id: null, wirkstoff: '', t: C.fmtTimeS(new Date()), appl: '', dosis: '', einheit: '' };
    const d = ui.med;
    const seg = (act, list, cur) => `<div class="ignis-tablet-options ignis-tablet-options--chips">${list.map((x) => `<button type="button" class="ignis-tablet-option ignis-tablet-option--chip" aria-pressed="${cur === x}" data-act="${act}" data-val="${esc(x)}"><span class="ignis-tablet-option__label">${esc(x)}</span></button>`).join('')}</div>`;
    return `<div class="ignis-tablet-view">
        <div class="ignis-tablet-view__body" data-scroll="pane">
            ${paneHead(d.id ? 'Medikament bearbeiten' : 'Medikament erfassen')}
            <div class="nf-medmask">
                <div class="ignis-bezel nf-box">
                    <div class="ignis-bezel__head"><h3 class="ignis-bezel__title">Eingetragen</h3><span class="ignis-count">${p.mass.meds.length}</span>
                        <div class="ignis-bezel__actions"><button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-act="med-new">${fa('fa-plus')}Neu</button></div></div>
                    <div class="ignis-bezel__well ignis-bezel__well--list">${p.mass.meds.length ? p.mass.meds.map((x) => medRow(x, { selectable: true, selected: d.id === x.id })).join('') : emptyBlock({ icon: 'fa-pills', title: 'Keine Medikamente eingetragen' })}</div>
                </div>
                <div class="ignis-bezel nf-box">
                    <div class="ignis-bezel__well nf-form">
                        <div class="ignis-tablet-group nf-group--flat"><div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">Wirkstoff</span></div>
                            <div class="ignis-tablet-options nf-opts--two">${C.MEDIKAMENTE.map((x) => `<button type="button" class="ignis-tablet-option" aria-pressed="${d.wirkstoff === x.label}" data-act="med-wirk" data-val="${esc(x.label)}"><span class="ignis-tablet-option__mark"></span><span class="ignis-tablet-option__label">${esc(x.label)}</span></button>`).join('')}</div></div>
                        <div class="nf-form__row">
                            <label class="ignis-field nf-field"><span class="ignis-field__label">Zeit</span>
                                <span class="nf-inline"><input class="ignis-input ignis-mono nf-input-times" data-bind="m:t" data-mask="times" maxlength="8" inputmode="numeric" placeholder="HH:MM:SS" value="${esc(d.t)}">
                                <button type="button" class="ignis-btn ignis-btn--secondary" data-act="med-now">${fa('fa-clock')}jetzt</button></span></label>
                            <label class="ignis-field nf-field"><span class="ignis-field__label">Dosierung</span>
                                <input class="ignis-input ignis-mono" id="nf-dosis" data-bind="m:dosis" data-live="dosis" inputmode="decimal" placeholder="z. B. 500 mg" value="${esc(d.dosis)}"></label>
                        </div>
                        <div class="ignis-tablet-group nf-group--flat"><div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">Applikationsart</span></div>${seg('med-appl', C.APPLIKATION, d.appl)}</div>
                        <div class="ignis-tablet-group nf-group--flat"><div class="ignis-tablet-group__head"><span class="ignis-tablet-group__label">Einheit</span></div><div id="nf-units">${seg('med-unit', C.EINHEITEN, d.einheit)}</div></div>
                    </div>
                </div>
            </div>
        </div>
        ${bar(`<a class="ignis-btn ignis-btn--secondary ignis-btn--lg" href="${href('p', p.enr, 'mass', 'meds')}">${fa('fa-arrow-left')}zurück</a>`,
            `<button type="button" class="ignis-btn ignis-btn--danger-soft ignis-btn--lg" data-act="med-del"${dis(p)}>${fa('fa-trash-can')}Löschen</button>
             <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="med-save"${dis(p)}>${fa('fa-floppy-disk')}Speichern</button>`)}
    </div>`;
}

/* ══ Abschluss ══════════════════════════════════════════════════ */

function transportPane(p) {
    const vs = ['', ...C.VEHICLES.map((v) => v.id)].map((x) => [x, x || '---']);
    const na = ['', ...C.VEHICLES.filter((v) => v.type === 'NEF' || v.type === 'RTH').map((v) => v.id)].map((x) => [x, x || '---']);
    return `${paneHead('Abschluss · Transportdaten', `<button type="button" class="ignis-btn ignis-btn--secondary" data-act="crew-fill"${dis(p)}>${fa('fa-users')}Besatzung übernehmen</button>`)}
    <div class="nf-rett__grid">
        <div class="ignis-bezel nf-box"><div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-truck-medical')}</span><h3 class="ignis-bezel__title">Besatzung Transportmittel</h3></div>
            <div class="ignis-bezel__well nf-form">
                ${select(p, 'absch.fzgTransp', vs, { label: 'Fzg. Transp.' })}
                ${input(p, 'absch.tf', { label: 'Transportführer RTW/KTW', ph: 'Name' })}
                ${input(p, 'absch.ff', { label: 'Fahrzeugführer RTW/KTW', ph: 'Name' })}
                ${input(p, 'absch.prakt', { label: 'Praktikant RTW/KTW', ph: 'Name' })}
            </div></div>
        <div class="ignis-bezel nf-box"><div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-user-doctor')}</span><h3 class="ignis-bezel__title">Besatzung Notarztzubringer</h3></div>
            <div class="ignis-bezel__well nf-form">
                ${select(p, 'absch.fzgNA', na, { label: 'Fzg. NA' })}
                ${input(p, 'absch.na', { label: 'Notarzt', ph: 'Name' })}
                ${input(p, 'absch.naff', { label: 'Fahrzeugführer NEF/HEMS-TC', ph: 'Name' })}
                ${input(p, 'absch.naprakt', { label: 'Praktikant NEF/HEMS-TC', ph: 'Name' })}
            </div></div>
    </div>
    <div class="ignis-bezel nf-box"><div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-car-side')}</span><h3 class="ignis-bezel__title">Sonstige Fahrzeuge</h3></div>
        <div class="ignis-bezel__well nf-form">${input(p, 'absch.weitere', { label: 'Weitere Rettungsmittel', ph: 'z. B. HLF 3/46-1, RTW 4/83-1' })}</div></div>`;
}

function protokollPane(p) {
    const s = S.session;
    const names = [s?.fahrer?.name, s?.beifahrer?.name, s?.praktikant?.name].filter(Boolean);
    return `${paneHead('Abschluss · Protokolldaten')}
    <div class="ignis-bezel nf-box nf-box--narrow"><div class="ignis-bezel__well nf-form">
        ${input(p, 'absch.protokollant', { label: 'Protokollant', ph: 'Name' })}
        <div class="nf-inline">${names.map((n) => `<button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-act="set" data-path="absch.protokollant" data-val="${esc(n)}"${dis(p)}><span class="ignis-avatar ignis-avatar--xs">${esc(initials(n))}</span>${esc(n)}</button>`).join('')}</div>
        ${select(p, 'absch.protDurch', C.PROT_DURCH, { label: 'Protokoll durch' })}
    </div></div>`;
}

function summaryBlocks(p) {
    const a = p.absch;
    const name = [p.pat.vorname, p.pat.nachname].filter(Boolean).join(' ');
    const row = (k, v) => `<div class="nf-kv"><span class="nf-kv__k">${esc(k)}</span><span class="nf-kv__v">${isSet(v) ? esc(v) : '<span class="nf-dim">—</span>'}</span></div>`;
    const block = (title, icon, rows) => `<div class="ignis-bezel nf-box"><div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa(icon)}</span><h3 class="ignis-bezel__title">${esc(title)}</h3></div><div class="ignis-bezel__well">${rows}</div></div>`;
    const von = [p.von.einrichtung, C.addressLine(p.von)].filter(Boolean).join(', ');
    const nach = [p.ziel.einrichtung, C.addressLine(p.ziel)].filter(Boolean).join(', ');
    return `<div class="nf-sum">
        ${block('Patient', 'fa-user', row('Name', name) + row('Geburtsdatum', p.pat.gebdat) + row('Geschlecht', p.pat.geschlecht))}
        ${block('Transport von/nach', 'fa-route', row('von', von) + row('nach', nach))}
        ${block('Besatzung', 'fa-users', row('Transportmittel', a.fzgTransp) + row('Transportführer', a.tf) + row('Fahrzeugführer', a.ff) + row('Notarztzubringer', a.fzgNA) + row('Notarzt', a.na) + row('Protokoll durch', a.protDurch))}
        ${block('Sonstige Fahrzeuge', 'fa-car-side', row('Weitere', a.weitere))}
        ${block('Einsatzdaten', 'fa-clipboard-list', `<div class="nf-kv nf-kv--one">Einsatz-Nr.: <span class="ignis-mono">#${esc(p.enr)}</span></div><div class="nf-kv nf-kv--one">Beginn: <span class="ignis-mono">${esc([p.einsatz.datum, p.einsatz.zeit].filter(Boolean).join(' ') || '—')}</span></div><div class="nf-kv nf-kv--one">Protokollart: ${esc(C.PROT_TYPES[p.art].code + ' ' + C.PROT_TYPES[p.art].label)}</div>`)}
        ${block('Protokollant und Freigabe', 'fa-signature', row('Protokollant', a.protokollant) + row('Freigabe durch', S.session?.fahrer?.name))}
    </div>`;
}

function plausi(p) {
    const miss = missingOf(p);
    const secLabel = Object.fromEntries(C.SECTIONS.map((s) => [s.key, s.label]));
    if (!miss.length) return `<div class="ignis-alert ignis-alert--ok">${fa('fa-circle-check', 'ignis-alert__icon')}<div class="ignis-alert__body"><div class="ignis-alert__title">Plausibilitätsprüfung</div>Alle Pflichtangaben sind ausgefüllt.</div></div>`;
    return `<div class="ignis-alert ignis-alert--danger nf-plausi">${fa('fa-circle-exclamation', 'ignis-alert__icon')}
        <div class="ignis-alert__body"><div class="ignis-alert__title">Plausibilitätsprüfung: ${miss.length} offene Pflichtangabe${miss.length === 1 ? '' : 'n'}</div>
        <ol class="nf-plausi__list">${miss.map((r, i) => `<li><a href="${href('p', p.enr, r.s, r.s === 'rett' ? '' : r.t, r.st || '')}"><span class="ignis-mono">[${i + 1}]</span> ${esc(secLabel[r.s])}: ${esc(r.msg)}</a></li>`).join('')}</ol></div></div>`;
}

function freigabeView(p) {
    return `<div class="ignis-tablet-view">
        <div class="ignis-tablet-view__body" data-scroll="pane">
            ${paneHead('Freigabe', `<span class="ignis-tablet-pane__sub">Protokoll <span class="ignis-mono">#${esc(p.enr)}</span></span>`)}
            ${plausi(p)}
            ${summaryBlocks(p)}
        </div>
        ${bar(`<a class="ignis-btn ignis-btn--secondary ignis-btn--lg" href="${href('p', p.enr, 'absch')}">${fa('fa-arrow-left')}zurück</a>`,
            p.released ? `<span class="ignis-chip" data-tone="ok">${fa('fa-lock')}freigegeben</span>` : `<button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="release"${S.readonly ? ' disabled' : ''}>${fa('fa-flag-checkered')}Abschließen!</button>`)}
    </div>`;
}

/* ══ Protokoll-Rahmen ═══════════════════════════════════════════ */

function protocolView(p, sec, topic, sub) {
    let main;
    let full = false;
    if (sec === 'rett') {
        if (topic === 'von' || topic === 'ziel') { main = addressView(p, topic); full = true; } else main = `<div class="ignis-tablet-pane ignis-tablet-pane--solo">${paneHead('Rett. Daten')}${rettOverview(p)}</div>`;
    } else if (sec === 'verlauf') {
        if (topic === 'liste') main = verlaufList(p);
        else if (topic === 'neu') { main = keypadView(p, { mode: 'verlauf' }); full = true; } else main = verlaufOverview(p);
    } else if (sec === 'mass' && topic === 'meds' && sub === 'maske') { main = medMask(p); full = true; } else if (sec === 'absch' && topic === 'freigabe') { main = freigabeView(p); full = true; } else main = renderSectionShell(p, sec, topic, sub);
    return `<div class="ignis-tablet-frame${ro(p) ? ' is-ro' : ''}">
        ${protoTopbar(p)}
        ${notice(p)}
        <div class="ignis-tablet-frame__body">
            ${rail(p, sec)}
            <main class="ignis-tablet-frame__main${full ? ' nf-main--full' : ''}" id="ignis-tablet-frame__main">${main}</main>
        </div>
    </div>`;
}

/* ══ Übersicht, Anlegen, Anmeldung, Sperre ═════════════════════ */

function overviewView() {
    const s = S.session;
    // Freigegebene Protokolle sind abgeschlossen und stehen nicht mehr in der Liste.
    const list = S.order.map((e) => S.protocols[e]).filter((p) => p && p.vehicle === s.vehicle && !p.released);
    const rows = list.map((p, i) => {
        const d = new Date(p.createdAt);
        const name = [p.pat.vorname, p.pat.nachname].filter(Boolean).join(' ');
        const ziel = p.ziel.einrichtung || C.addressLine(p.ziel);
        const miss = missingOf(p).length;
        const total = reqActive(p).length;
        const fill = miss === 0 ? 'filled' : miss === total ? 'unfilled' : 'partfilled';
        return `<div class="ignis-bezel__item nf-prow ignis-tablet-stripe" data-state="${fill}">
            <a class="ignis-entry nf-prow__main" href="${href('p', p.enr, 'rett')}">
                <span class="ignis-glyph ignis-glyph--md nf-letter">${String.fromCharCode(65 + i)}</span>
                <span class="ignis-entry__title"><span class="ignis-mono">#${esc(p.enr)}</span><span class="ignis-sr-only">${FILL_WORD[fill]}</span>
                    <span class="ignis-chip nf-tag"${artTone(p.art)}>${esc(p.art)}</span>
                    <span class="nf-prow__when">${esc(C.fmtDate(d))} · ${esc(C.fmtTime(d))} Uhr</span></span>
                <span class="ignis-entry__meta">
                    <span>Patient: ${name ? esc(name) : '<span class="nf-dim">unbekannt</span>'}${p.pat.gebdat ? ` * ${esc(p.pat.gebdat)}` : ''}</span>
                    <span>${fa('fa-location-dot')} ${ziel ? esc(ziel) : '<span class="nf-dim">kein Transportziel</span>'}</span>
                </span>
            </a>
            <div class="nf-prow__side">
                ${p.createdBy === 'leitstelle' ? `<span class="ignis-chip" data-tone="info" data-ignis-tooltip="Von der Leitstelle angelegt">${fa('fa-bell')}Leitstelle</span>` : ''}
                <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon nf-del" data-act="pdel" data-enr="${esc(p.enr)}" aria-label="Protokoll #${esc(p.enr)} löschen" data-ignis-tooltip="Löschen">${fa('fa-trash-can')}</button>
            </div>
        </div>`;
    }).join('');
    const links = S.quicklinksEmpty
        ? emptyBlock({ icon: 'fa-link', title: 'Keine Links verfügbar.', pattern: true })
        : `<div class="nf-links">${C.QUICKLINKS.map((l) => `<button type="button" class="nf-link" data-act="qlink" data-val="${esc(l.label)}"><span class="ignis-glyph ignis-glyph--sm">${fa(l.icon)}</span><span class="nf-link__text"><span class="nf-link__label">${hy(l.label)}</span><span class="nf-link__note">${esc(l.note)}</span></span></button>`).join('')}</div>`;
    const tools = `<button type="button" class="ignis-btn ignis-btn--ghost ignis-tablet-top__btn" data-act="vinfo" data-ignis-tooltip="Fahrzeuginfo" aria-label="Fahrzeuginfo">${fa('fa-circle-info')}<span class="ignis-tablet-top__label">Fahrzeuginfo</span></button>
        <button type="button" class="ignis-btn ignis-btn--ghost ignis-tablet-top__btn" data-act="logbook" data-ignis-tooltip="Fahrtenbuch" aria-label="Fahrtenbuch">${fa('fa-book')}<span class="ignis-tablet-top__label">Fahrtenbuch</span></button>
        <button type="button" class="ignis-btn ignis-btn--ghost ignis-tablet-top__btn" data-act="logout" data-ignis-tooltip="Abmelden" aria-label="Abmelden">${fa('fa-right-from-bracket')}<span class="ignis-tablet-top__label">Abmelden</span></button>`;
    return `<div class="ignis-tablet-frame">
        ${plainTopbar('Übersicht', tools)}
        <main class="ignis-tablet-frame__page" id="ignis-tablet-frame__main" data-scroll="page">
            <div class="nf-overview">
                <section class="ignis-bezel">
                    <div class="ignis-bezel__head">
                        <h1 class="ignis-bezel__title nf-h1">Einsatzprotokolle</h1><span class="ignis-count">${list.length}</span>
                        <div class="ignis-bezel__actions">
                            <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--icon" data-act="reload" data-ignis-tooltip="Neu laden" aria-label="Neu laden">${fa('fa-rotate-right')}</button>
                            <a class="ignis-btn ignis-btn--primary" href="#/create">${fa('fa-plus')}Neuen Einsatz erstellen</a>
                        </div>
                    </div>
                    <div class="ignis-bezel__well ignis-bezel__well--list">${rows || emptyBlock({ icon: 'fa-clipboard-list', title: 'Keine offenen Protokolle', actions: `<a class="ignis-btn ignis-btn--primary" href="#/create">${fa('fa-plus')}Neuen Einsatz erstellen</a>` })}</div>
                    ${list.length ? `<div class="ignis-bezel__foot"><span>${fa('fa-truck-medical')} ${esc(s.vehicle)}</span><button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm nf-ml-auto" data-act="pdel-all">${fa('fa-trash-can')}alle löschen</button></div>` : ''}
                </section>
                <aside class="ignis-bezel">
                    <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-link')}</span><h2 class="ignis-bezel__title">Quicklinks</h2></div>
                    <div class="ignis-bezel__well">${links}</div>
                </aside>
            </div>
        </main>
    </div>`;
}

function createView() {
    const c = ui.create;
    const tileBtn = (code) => {
        const t = C.PROT_TYPES[code];
        return `<button type="button" class="nf-type" aria-pressed="${c.type === code}" data-act="create-type" data-val="${code}">
            <span class="ignis-glyph ignis-glyph--lg">${fa(t.icon)}</span>
            <span class="nf-type__code ignis-mono">${code}</span><span class="nf-type__label">${esc(t.label)}</span>
            <span class="nf-type__check">${fa('fa-circle-check')}</span>
        </button>`;
    };
    return `<div class="ignis-tablet-frame">
        ${plainTopbar('Neuer Einsatz', `<a class="ignis-btn ignis-btn--ghost ignis-tablet-top__btn" href="#/overview" aria-label="Übersicht">${fa('fa-table-list')}<span class="ignis-tablet-top__label">Übersicht</span></a>`)}
        <main class="ignis-tablet-frame__page" id="ignis-tablet-frame__main">
            <section class="ignis-bezel nf-create">
                <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-plus')}</span><h1 class="ignis-bezel__title">Neuen Einsatz erstellen</h1></div>
                <div class="ignis-bezel__well nf-form">
                    <label class="ignis-field nf-field ${c.err === 'enr' ? 'is-invalid' : ''}"><span class="ignis-field__label">Einsatznummer</span>
                        <input class="ignis-input ignis-mono nf-input-xl" id="nf-create-enr" data-bind="c:enr" inputmode="numeric" autocomplete="off" placeholder="z. B. 2610201530" value="${esc(c.enr)}" ${c.err === 'enr' || c.err === 'enrInvalid' ? 'aria-invalid="true"' : ''}>
                        ${c.err === 'enr' ? '<span class="ignis-field__error">Bitte gib eine Einsatznummer ein.</span>' : ''}
                        ${c.err === 'enrInvalid' ? '<span class="ignis-field__error">Ungültige Einsatznummer. Erlaubt sind nur Ziffern und Unterstriche.</span>' : ''}
                        <span class="ignis-field__hint">Nur Ziffern und Unterstriche.</span>
                    </label>
                    <div class="ignis-field nf-field"><span class="ignis-field__label">Protokollart</span>
                        <div class="nf-types" role="group" aria-label="Protokollart">${tileBtn('NF')}${tileBtn('NA')}</div>
                        ${c.err === 'type' ? '<span class="ignis-field__error">Bitte wähle ein Protokoll aus (RD oder NA).</span>' : ''}
                    </div>
                </div>
                <div class="ignis-bezel__foot nf-foot-actions">
                    <a class="ignis-btn ignis-btn--secondary ignis-btn--lg" href="#/overview">Abbrechen</a>
                    <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="create">${fa('fa-check')}Erstellen</button>
                </div>
            </section>
        </main>
    </div>`;
}

function loginInit() {
    if (!ui.login) {
        const s = S.session || C.SAMPLE_CREW;
        ui.login = { vehicle: '', newCrew: false, errs: {}, fahrer: { ...s.fahrer }, beifahrer: { ...s.beifahrer }, praktikant: { ...s.praktikant } };
    }
    return ui.login;
}
function loginView() {
    const L = loginInit();
    const existing = L.vehicle && C.EXISTING_CREWS[L.vehicle] && !L.newCrew ? C.EXISTING_CREWS[L.vehicle] : null;
    const person = (role, label, req) => {
        const m = L[role];
        const err = L.errs[role];
        return `<div class="nf-person">
            <label class="ignis-field nf-field ${err === 'name' ? 'is-invalid' : ''}"><span class="ignis-field__label">${esc(label)}-Name</span>
                <input class="ignis-input" data-bind="l:${role}.name" value="${esc(m.name)}" placeholder="Vorname Nachname" autocomplete="off" ${err === 'name' ? 'aria-invalid="true"' : ''}>
                ${err === 'name' ? '<span class="ignis-field__error">Bitte Namen eintragen.</span>' : ''}</label>
            <label class="ignis-field nf-field ${err === 'quali' ? 'is-invalid' : ''}"><span class="ignis-field__label">Qualifikation</span>
                <select data-custom-dropdown="true" class="ignis-input" data-bind="l:${role}.quali">${C.QUALIS.map((q) => `<option ${q === m.quali ? 'selected' : ''}>${esc(q)}</option>`).join('')}</select>
                ${err === 'quali' ? '<span class="ignis-field__error">Bitte Qualifikation wählen.</span>' : ''}</label>
        </div>`;
    };
    return `<div class="ignis-tablet-frame">
        ${plainTopbar('Anmeldung')}
        <main class="ignis-tablet-frame__page nf-login" id="ignis-tablet-frame__main">
            <section class="ignis-bezel nf-login__card">
                <div class="ignis-bezel__head"><span class="ignis-glyph ignis-glyph--sm">${fa('fa-users')}</span><h1 class="ignis-bezel__title">Besatzung anmelden</h1>
                    <div class="ignis-bezel__actions"><button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-act="theme">${fa(document.documentElement.dataset.theme === 'light' ? 'fa-moon' : 'fa-sun')}${document.documentElement.dataset.theme === 'light' ? 'Dunkel' : 'Hell'}</button></div></div>
                <div class="ignis-bezel__well nf-form">
                    <label class="ignis-field nf-field ${L.errs.vehicle ? 'is-invalid' : ''}"><span class="ignis-field__label">Fahrzeug</span>
                        <select data-custom-dropdown="true" class="ignis-input" data-bind="l:vehicle" ${L.errs.vehicle ? 'aria-invalid="true"' : ''}><option value="">Fahrzeug wählen</option>${C.VEHICLES.map((v) => `<option value="${esc(v.id)}" ${v.id === L.vehicle ? 'selected' : ''}>${esc(v.id)} · ${esc(v.wache)}</option>`).join('')}</select>
                        ${L.errs.vehicle ? '<span class="ignis-field__error">Bitte ein Fahrzeug wählen.</span>' : ''}</label>
                    ${existing ? `<div class="ignis-alert ignis-alert--info nf-existing">${fa('fa-users', 'ignis-alert__icon')}
                        <div class="ignis-alert__body"><div class="ignis-alert__title">Es ist bereits eine Besatzung auf diesem Fahrzeug angemeldet</div>
                            <div class="nf-existing__names">${['fahrer', 'beifahrer', 'praktikant'].filter((r) => existing[r].name).map((r) => `<span class="nf-existing__name"><span class="ignis-avatar ignis-avatar--xs">${esc(initials(existing[r].name))}</span>${esc(existing[r].name)} <span class="nf-dim">${esc(existing[r].quali)}</span></span>`).join('')}</div>
                            <div class="nf-inline"><button type="button" class="ignis-btn ignis-btn--primary" data-act="login-join">${fa('fa-right-to-bracket')}Beitreten</button><button type="button" class="ignis-btn ignis-btn--secondary" data-act="login-new">${fa('fa-user-plus')}Neue Besatzung</button></div>
                        </div></div>` : ''}
                    ${person('fahrer', 'Fahrer', true)}
                    ${person('beifahrer', 'Beifahrer', false)}
                    ${person('praktikant', 'Praktikant', false)}
                </div>
                <div class="ignis-bezel__foot nf-foot-actions">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-act="login-clear">${fa('fa-eraser')}Besatzung löschen</button>
                    <button type="button" class="ignis-btn ignis-btn--secondary" data-act="login-swap">${fa('fa-arrows-left-right')}Fahrer / Beifahrer tauschen</button>
                    <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--lg" data-act="login-ok">${fa('fa-check')}OK</button>
                </div>
            </section>
        </main>
    </div>`;
}

function lockView() {
    const k = (v, label, cls = '') => `<button type="button" class="ignis-tablet-key nf-key--pin${cls}" data-act="pin" data-k="${v}">${label}</button>`;
    return `<div class="nf-lock">
        <div class="nf-lock__card${ui.pinErr ? ' is-wrong' : ''}">
            <span class="ignis-glyph ignis-glyph--lg">${fa('fa-lock')}</span>
            <h1 class="nf-lock__title">System gesperrt</h1>
            <p class="nf-lock__sub">Bitte PIN eingeben</p>
            <div class="nf-pin" aria-label="${ui.pin.length} von 4 Stellen eingegeben" role="status">${[0, 1, 2, 3].map((i) => `<span class="nf-pin__dot${i < ui.pin.length ? ' is-on' : ''}"></span>`).join('')}</div>
            <p class="nf-lock__err" role="alert">${ui.pinErr ? `${fa('fa-circle-exclamation')} Falsche PIN` : '&nbsp;'}</p>
            <div class="nf-pinpad">
                ${['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((d) => k(d, d)).join('')}
                ${k('back', `${fa('fa-delete-left')}<span class="ignis-sr-only">Rücktaste</span>`, ' ignis-tablet-key--fn')}${k('0', '0')}${k('ok', `${fa('fa-check')}<span class="ignis-sr-only">Bestätigen</span>`, ' ignis-tablet-key--fn')}
            </div>
            <p class="nf-hint">Demo-PIN: <span class="ignis-kbd">1</span> <span class="ignis-kbd">2</span> <span class="ignis-kbd">3</span> <span class="ignis-kbd">4</span></p>
        </div>
    </div>`;
}

/* ══ Zeichnen ═══════════════════════════════════════════════════ */

function focusSig(el) {
    if (!el || el === document.body) return null;
    return ['act', 'path', 'val', 'key', 'bind', 'k', 'site', 'side', 'id'].map((k) => el.dataset?.[k] || '').join('|') + '|' + (el.id || '');
}

function render() {
    ui.route = parseRoute();
    const r = ui.route;
    hideFloater();
    const prevFocus = focusSig(document.activeElement);
    const scrolls = {};
    app.querySelectorAll('[data-scroll]').forEach((el) => { scrolls[el.dataset.scroll + location.hash] = el.scrollTop; });
    const sameRoute = render.last === location.hash;
    render.last = location.hash;

    let html;
    if (r[0] === 'lock') html = lockView();
    else if (!S.session || r[0] === 'login') html = loginView();
    else if (r[0] === 'create') html = createView();
    else if (r[0] === 'p') {
        const p = S.protocols[r[1]];
        if (!p) { location.hash = '#/overview'; return; }
        if (!r[2]) { location.hash = href('p', p.enr, 'rett'); return; }
        html = protocolView(p, r[2], r[3], r[4]);
    } else html = overviewView();

    app.innerHTML = html;
    initDropdowns(app);
    // Das Dropdown versteckt das Select; die Pflicht-Markierung gehört an den sichtbaren Knopf.
    app.querySelectorAll('select.nf-input--missing').forEach((el) => el.closest('.ignis-dropdown')?.classList.add('nf-dd--missing'));

    if (sameRoute) {
        app.querySelectorAll('[data-scroll]').forEach((el) => {
            const v = scrolls[el.dataset.scroll + location.hash];
            if (v) el.scrollTop = v;
        });
        if (prevFocus) {
            const t = [...app.querySelectorAll('button, a, input, select, textarea, [tabindex]')].find((el) => focusSig(el) === prevFocus);
            if (t) t.focus({ preventScroll: true });
        }
    }
    drawChart();
}

/* ══ Aktionen ═══════════════════════════════════════════════════ */

function toggleExclusive(arr, val, excl, exclusives) {
    let out = Array.isArray(arr) ? [...arr] : [];
    if (out.includes(val)) return out.filter((x) => x !== val);
    if (excl) return [val];
    out = out.filter((x) => !exclusives.includes(x));
    out.push(val);
    return out;
}
function exclusivesFor(path) {
    const map = { 'erst.psych': C.OPT.psych, 'absch.besond': C.BESONDERHEITEN };
    return (map[path] || []).map(C.parseOpt).filter((o) => o.excl).map((o) => o.label);
}

function timeNow() { const d = new Date(); return { t: C.fmtTime(d), d: C.fmtDate(d) }; }

function dialogField(label, inner) { return `<label class="ignis-field nf-field"><span class="ignis-field__label">${esc(label)}</span>${inner}</label>`; }

const ACT = {
    pick(el) {
        if (guardRO()) return;
        const p = P();
        const cur = get(p, el.dataset.path);
        set(p, el.dataset.path, cur === el.dataset.val ? '' : el.dataset.val);
        commit();
    },
    multi(el) {
        if (guardRO()) return;
        const p = P();
        const path = el.dataset.path;
        set(p, path, toggleExclusive(get(p, path), el.dataset.val, el.dataset.excl === '1', exclusivesFor(path)));
        commit();
    },
    bool(el) {
        if (guardRO()) return;
        const p = P();
        set(p, el.dataset.path, !get(p, el.dataset.path));
        commit();
    },
    set(el) {
        if (guardRO()) return;
        set(P(), el.dataset.path, el.dataset.val);
        commit();
    },
    now(el) {
        if (guardRO()) return;
        set(P(), el.dataset.path, C.fmtTime(new Date()));
        commit();
    },
    quick(el) {
        if (guardRO()) return;
        const p = P();
        const t = C.ERST_TOPICS.find((x) => x.key === el.dataset.topic);
        Object.entries(t.quick.set).forEach(([k, v]) => set(p, k, structuredClone(v)));
        snack.success(`${t.label}: ${t.quick.label} gesetzt.`);
        commit();
    },
    zone(el) { ui.bodySel = el.dataset.key; render(); },
    sev(el) {
        if (guardRO()) return;
        const p = P();
        const cur = p.erst.verl[ui.bodySel] || {};
        const sev = el.dataset.val;
        p.erst.verl[ui.bodySel] = cur.sev === sev ? {} : { sev, wund: sev === 'Keine' ? undefined : cur.wund };
        commit();
    },
    wund(el) {
        if (guardRO()) return;
        const v = P().erst.verl[ui.bodySel];
        if (!v) return;
        v.wund = v.wund === el.dataset.val ? undefined : el.dataset.val;
        commit();
    },
    time(el) {
        if (guardRO()) return;
        const p = P();
        const key = el.dataset.key;
        const z = C.ZEITEN.find((x) => x.key === key);
        const cur = p.zeiten[key];
        if (!cur?.t) {
            p.zeiten[key] = timeNow();
            snack.success(`${z.label}: ${p.zeiten[key].t} Uhr gesetzt`);
            commit();
            return;
        }
        const dlg = new Dialog({
            title: `Zeit ${z.label}`, size: 'sm',
            body: `<div class="nf-form">${dialogField('Uhrzeit', `<input class="ignis-input ignis-mono" id="nf-dt" data-mask="time" maxlength="5" inputmode="numeric" value="${esc(cur.t)}">`)}${dialogField('Datum', `<input class="ignis-input ignis-mono" id="nf-dd" data-mask="date" maxlength="10" inputmode="numeric" value="${esc(cur.d)}">`)}</div>`,
            actions: [
                { label: 'Leeren', variant: 'ghost-danger', onClick: (d) => { delete p.zeiten[key]; d.close(); commit(); } },
                { label: 'Jetzt', variant: 'secondary', onClick: (d) => { p.zeiten[key] = timeNow(); d.close(); commit(); } },
                { label: 'Übernehmen', variant: 'primary', primary: true, onClick: (d) => {
                    const t = d.element.querySelector('#nf-dt').value;
                    const dd = d.element.querySelector('#nf-dd').value;
                    if (!/^\d{2}:\d{2}$/.test(t) || !/^\d{2}\.\d{2}\.\d{4}$/.test(dd)) { snack.warning('Bitte Uhrzeit HH:MM und Datum TT.MM.JJJJ eintragen.'); return; }
                    p.zeiten[key] = { t, d: dd }; d.close(); commit();
                } },
            ],
        });
        dlg.open();
    },
    sr() {
        if (guardRO()) return;
        const order = ['-', 'nein', 'ja'];
        const a = ui.draft.a;
        a.sr = order[(order.indexOf(a.sr || '-') + 1) % 3];
        render();
    },
    poi(el) {
        const h = C.POIS[+el.dataset.i];
        Object.assign(ui.draft.a, { poi: h.name, einrichtung: h.name, strasse: h.strasse, hnr: h.hnr, plz: h.plz, ort: h.ort, ortsteil: h.ortsteil });
        ui.draft.poiOpen = false;
        render();
    },
    'addr-save'(el) {
        if (guardRO()) return;
        const p = P();
        p[el.dataset.key] = structuredClone(ui.draft.a);
        ui.draft = null;
        persist(); flashAutosave();
        snack.success('Adresse gespeichert');
        go(href('p', p.enr, 'rett'));
    },
    'addr-back'(el, e) { e.preventDefault(); ui.draft = null; go(href('p', P().enr, 'rett')); },

    'kp-focus'(el) { ui.kp.active = el.dataset.key; render(); },
    kp(el) { kpPress(el.dataset.k); },
    'kp-cancel'() {
        const p = P();
        const mode = ui.kp?.mode;
        ui.kp = null;
        go(mode === 'verlauf' ? href('p', p.enr, 'verlauf') : href('p', p.enr, 'erst'));
    },
    'kp-save'() {
        if (guardRO()) return;
        const p = P();
        const kp = ui.kp;
        const clean = Object.fromEntries(Object.entries(kp.values).map(([k, v]) => [k, String(v).replace(/,$/, '')]).filter(([, v]) => v !== ''));
        if (kp.mode === 'erst') {
            p.erst.vitals = clean;
            ui.kp = null;
            persist(); flashAutosave();
            snack.success('Messwerte gespeichert.');
            go(href('p', p.enr, 'erst'));
            return;
        }
        if (!Object.keys(clean).length) { snack.warning('Keine Werte zum Speichern eingetragen.'); return; }
        const at = parseDate(kp.date, kp.time);
        if (!at) { snack.warning('Bitte Datum TT.MM.JJJJ und Uhrzeit HH:MM eintragen.'); return; }
        p.verlauf.push({ id: uid(), at: at.toISOString(), values: clean });
        ui.kp = null;
        persist(); flashAutosave();
        snack.success(`${Object.keys(clean).length} Werte für ${kp.time} Uhr gespeichert.`);
        go(href('p', p.enr, 'verlauf'));
    },
    series(el) {
        const k = el.dataset.key;
        if (ui.hidden.has(k)) ui.hidden.delete(k); else ui.hidden.add(k);
        el.setAttribute('aria-pressed', String(!ui.hidden.has(k)));
        drawChart();
    },
    async vdel(el) {
        if (guardRO()) return;
        const p = P();
        const e = p.verlauf.find((x) => x.id === el.dataset.id);
        const k = el.dataset.key;
        const v = C.VITAL[k];
        const ok = await Dialog.confirm(`Parameter '${v.label} (${e.values[k]} ${v.unit})' löschen?`, { title: 'Wert löschen', confirmText: 'Löschen', danger: true });
        if (!ok) return;
        delete e.values[k];
        if (!Object.keys(e.values).length) p.verlauf = p.verlauf.filter((x) => x !== e);
        snack.success('Wert gelöscht.');
        commit();
    },

    // Wie v1/v2: Der Hauptbaustein schreibt seine Überschrift an die Cursorposition und öffnet
    // das Untermenü, ein erneuter Klick schließt es. Unterbausteine fügen ganze Sätze ein.
    textblock(el) {
        if (guardRO()) return;
        const bi = +el.dataset.b;
        if (ui.tbOpen === bi) { ui.tbOpen = null; } else {
            insertAtCursor(C.TEXTBLOCKS[bi].text, 2);
            ui.tbOpen = bi;
        }
        app.querySelectorAll('[data-tb-sub]').forEach((x) => { x.hidden = +x.dataset.tbSub !== ui.tbOpen; });
        app.querySelectorAll('.nf-tb__btn[data-act="textblock"]').forEach((x) => x.setAttribute('aria-expanded', String(+x.dataset.b === ui.tbOpen)));
    },
    textsub(el) {
        if (guardRO()) return;
        insertAtCursor(C.TEXTBLOCKS[+el.dataset.b].sub[+el.dataset.i].text, 0);
    },
    'anam-ok'() { persist(); flashAutosave(); snack.success('Anamnese gespeichert.'); },
    symnicht() {
        if (guardRO()) return;
        const a = P().anam;
        a.symNicht = !a.symNicht;
        if (a.symNicht) a.symZeit = '';
        commit();
    },
    'sym-save'() { persist(); flashAutosave(); snack.success('Symptombeginn gespeichert.'); },

    diag(el) {
        if (guardRO()) return;
        const p = P();
        const v = el.dataset.val;
        if (el.dataset.path === 'fuehrend') {
            p.diag.fuehrend = p.diag.fuehrend === v ? '' : v;
            p.diag.weitere = p.diag.weitere.filter((x) => x !== p.diag.fuehrend);
        } else p.diag.weitere = p.diag.weitere.includes(v) ? p.diag.weitere.filter((x) => x !== v) : [...p.diag.weitere, v];
        commit();
    },
    'diag-remove'(el) {
        if (guardRO()) return;
        const p = P();
        p.diag.weitere = p.diag.weitere.filter((x) => x !== el.dataset.val);
        commit();
    },

    'zug-type'(el) { ui.zugType = el.dataset.val; ui.zugSel = null; render(); },
    'zug-none'() {
        if (guardRO()) return;
        const m = P().mass;
        if (!m.zugangNone && m.zugaenge.length) { snack.info('Erst die dokumentierten Zugänge entfernen.'); return; }
        m.zugangNone = !m.zugangNone;
        commit();
    },
    zcell(el) {
        const s = { type: ui.zugType, site: el.dataset.site, side: el.dataset.side };
        const cur = ui.zugSel;
        ui.zugSel = cur && cur.type === s.type && cur.site === s.site && cur.side === s.side ? null : s;
        render();
    },
    zsize(el) {
        if (guardRO()) return;
        const p = P();
        const sel = ui.zugSel;
        const m = p.mass;
        const match = (z) => z.type === sel.type && z.site === sel.site && (z.side || '') === (sel.side || '');
        const existing = m.zugaenge.find(match);
        if (existing) existing.size = el.dataset.val;
        else m.zugaenge.push({ id: uid(), type: sel.type, site: sel.site, side: sel.side, size: el.dataset.val });
        m.zugangNone = false;
        const s = sizeMeta(sel.type, el.dataset.val);
        snack.success(`${sel.type === 'pvk' ? 'PVK' : 'i.o.'} ${s.short} an ${sel.site}${sel.side ? ' ' + sel.side : ''} gespeichert`);
        commit();
    },
    zdel() {
        if (guardRO()) return;
        const sel = ui.zugSel;
        const m = P().mass;
        m.zugaenge = m.zugaenge.filter((z) => !(z.type === sel.type && z.site === sel.site && (z.side || '') === (sel.side || '')));
        snack.success('Zugang entfernt');
        commit();
    },
    async 'zug-clear'() {
        if (guardRO()) return;
        const ok = await Dialog.confirm('Alle dokumentierten Zugänge entfernen?', { title: 'Zugänge entfernen', confirmText: 'Alle entfernen', danger: true });
        if (!ok) return;
        P().mass.zugaenge = [];
        ui.zugSel = null;
        snack.success('Alle Zugänge entfernt');
        commit();
    },

    'meds-none'() {
        if (guardRO()) return;
        const m = P().mass;
        if (!m.medsNone && m.meds.length) { snack.info('Es sind bereits Medikamente eingetragen.'); return; }
        m.medsNone = !m.medsNone;
        commit();
    },
    'med-wirk'(el) {
        ui.med.wirkstoff = el.dataset.val;
        const def = C.MEDIKAMENTE.find((x) => x.label === el.dataset.val);
        if (def && !ui.med.einheit) ui.med.einheit = def.unit;
        render();
    },
    'med-appl'(el) { ui.med.appl = ui.med.appl === el.dataset.val ? '' : el.dataset.val; render(); },
    'med-unit'(el) { ui.med.einheit = ui.med.einheit === el.dataset.val ? '' : el.dataset.val; render(); },
    'med-now'() { ui.med.t = C.fmtTimeS(new Date()); render(); },
    'med-new'() { ui.med = null; render(); },
    'med-pick'(el) {
        const x = P().mass.meds.find((m) => m.id === el.dataset.id);
        ui.med = ui.med?.id === x.id ? null : { enr: P().enr, ...structuredClone(x) };
        render();
    },
    'med-save'() {
        if (guardRO()) return;
        const p = P();
        const d = ui.med;
        if (!d.wirkstoff || !/^\d{2}:\d{2}:\d{2}$/.test(d.t) || !d.appl || !String(d.dosis).trim() || !d.einheit) { snack.warning('Bitte füllen Sie alle Felder aus.'); return; }
        const rec = { id: d.id || uid(), t: d.t, wirkstoff: d.wirkstoff, dosis: String(d.dosis).trim(), einheit: d.einheit, appl: d.appl };
        const i = p.mass.meds.findIndex((m) => m.id === rec.id);
        if (i >= 0) p.mass.meds[i] = rec; else p.mass.meds.push(rec);
        p.mass.meds.sort((a, b) => a.t.localeCompare(b.t));
        p.mass.medsNone = false;
        ui.med = null;
        snack.success(i >= 0 ? 'Medikament gespeichert.' : 'Medikament erfolgreich hinzugefügt.');
        commit();
    },
    async 'med-del'() {
        if (guardRO()) return;
        const p = P();
        if (!ui.med?.id) { snack.warning('Bitte wählen Sie ein Medikament zum Löschen aus.'); return; }
        const ok = await Dialog.confirm(`${ui.med.wirkstoff} (${ui.med.t}) löschen?`, { title: 'Medikament löschen', confirmText: 'Löschen', danger: true });
        if (!ok) return;
        p.mass.meds = p.mass.meds.filter((m) => m.id !== ui.med.id);
        ui.med = null;
        snack.success('Medikament gelöscht.');
        commit();
    },

    'crew-fill'() {
        if (guardRO()) return;
        const p = P();
        const s = S.session;
        Object.assign(p.absch, { fzgTransp: s.vehicle, tf: s.fahrer.name, ff: s.beifahrer.name, prakt: s.praktikant.name });
        snack.success('Besatzung übernommen.');
        commit();
    },
    async 'send-lst'() {
        if (guardRO()) return;
        if (!S.conn.lst) { Dialog.alert('Keine Verbindung zur Leitstelle. Bitte später erneut senden.', { type: 'error', title: 'Senden nicht möglich' }); return; }
        const ok = await Dialog.confirm('Protokolldaten jetzt an die Leitstelle senden?', { title: 'An Leitstelle senden', confirmText: 'Senden' });
        if (!ok) return;
        P().absch.lstSent = C.fmtTime(new Date());
        snack.success('Protokoll an die Leitstelle gesendet.');
        commit();
    },
    klinikcode() {
        const p = P();
        const code = (p.enr.slice(-3) + 'K' + p.enr.slice(2, 4)).toUpperCase().replace(/(\w{3})(\w{3})/, '$1-$2');
        new Dialog({
            title: 'Klinikcode', size: 'sm',
            body: `<p class="ignis-dialog__text">Code für Protokoll <span class="ignis-mono">#${esc(p.enr)}</span></p>
                <div class="nf-code ignis-mono" aria-label="Code ${esc(code.split('').join(' '))}">${esc(code)}</div>
                <p class="ignis-dialog__text">Gültig für eine Stunde unter <span class="ignis-mono">${esc(C.KLINIK_URL)}</span></p>`,
            actions: [
                { label: 'Schließen', variant: 'ghost', onClick: (d) => d.close() },
                { label: 'Code kopieren', variant: 'primary', primary: true, onClick: (d) => {
                    const done = () => snack.success('Code kopiert.');
                    try { navigator.clipboard.writeText(code).then(done, () => snack.info(`Code: ${code}`)); } catch (e) { snack.info(`Code: ${code}`); }
                    d.close();
                } },
            ],
        }).open();
    },
    async release() {
        const p = P();
        if (S.readonly) { guardRO(); return; }
        if (!isSet(p.absch.protokollant)) { Dialog.alert('Freigabe erst möglich, wenn ein Protokollant eingetragen ist.', { type: 'warning', title: 'Freigabe' }); return; }
        const miss = missingOf(p).length;
        if (miss) { Dialog.alert(`${miss} offene Pflichtangabe(n). Freigabe noch nicht möglich.`, { type: 'warning', title: 'Freigabe' }); return; }
        const ok = await Dialog.confirm(`Protokoll #${p.enr} wirklich abschließen? Es kann danach nicht mehr bearbeitet werden.`, { title: 'Protokoll abschließen', confirmText: 'Abschließen' });
        if (!ok) return;
        p.released = { by: p.absch.protokollant, at: new Date().toISOString() };
        snack.success(`Protokoll #${p.enr} freigegeben.`);
        commit();
    },

    /* Kopfzeile */
    'crew-menu'() { ui.topbarMenu = !ui.topbarMenu; render(); },
    async logout() {
        ui.topbarMenu = false;
        const choice = await new Dialog({
            title: 'Abmelden', size: 'sm',
            body: '<p class="ignis-dialog__text">Wie möchten Sie sich abmelden?</p>',
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: (d) => d.close(null) },
                { label: 'Alle abmelden', variant: 'secondary', onClick: (d) => d.close('all') },
                { label: 'Mich abmelden', variant: 'primary', primary: true, onClick: (d) => d.close('me') },
            ],
        }).open();
        if (!choice) { render(); return; }
        if (choice === 'all') { S.session = null; ui.login = null; snack.info('Alle Besatzungsmitglieder abgemeldet.'); } else {
            const s = S.session;
            ui.login = { vehicle: s.vehicle, newCrew: true, errs: {}, fahrer: { ...s.beifahrer }, beifahrer: { ...s.praktikant }, praktikant: { name: '', quali: '---' } };
            S.session = null;
            snack.info(`${s.fahrer.name} abgemeldet.`);
        }
        persist();
        go('#/login');
    },
    'conn-lst'() { S.conn.lst = !S.conn.lst; persist(); render(); snack.info(S.conn.lst ? 'Verbindung zur Leitstelle aktiv' : 'Keine Verbindung zur Leitstelle (Demo)'); },
    'conn-session'() { S.conn.session = !S.conn.session; persist(); render(); snack.info(S.conn.session ? 'Session aktiv' : 'Session-Verbindung unterbrochen (Demo)'); },
    async patsync() {
        const p = P();
        if ((p.patSync || 'open') === 'synced') { snack.info('Patientendaten sind synchronisiert.'); return; }
        const ok = await Dialog.confirm('Patientendaten an die Leitstelle senden/anfordern?', { title: 'Patientendaten-Sync', confirmText: 'Senden' });
        if (!ok) return;
        p.patSync = 'requested';
        commit();
        setTimeout(() => { p.patSync = 'synced'; persist(); if (P() === p) updateChrome(); snack.success('Patientendaten synchronisiert.'); }, 1600);
    },
    autosave() {
        if (ui.autosave === 'error') { S.failSave = false; persist(); flashAutosave(); snack.success('Erneut gespeichert.'); } else snack.info(SAVE_META[ui.autosave].tip);
    },
    lock() { ui.lockReturn = location.hash || '#/overview'; ui.pin = ''; ui.pinErr = false; ui.topbarMenu = false; go('#/lock'); },
    art() {
        if (guardRO()) return;
        const p = P();
        const tileHTML = (code) => `<button type="button" class="nf-type nf-type--dlg" data-code="${code}" aria-pressed="${p.art === code}"><span class="ignis-glyph ignis-glyph--md">${fa(C.PROT_TYPES[code].icon)}</span><span class="nf-type__code ignis-mono">${code}</span><span class="nf-type__label">${C.PROT_TYPES[code].label}</span>${p.art === code ? '<span class="ignis-chip">aktuell</span>' : ''}</button>`;
        new Dialog({
            title: 'Protokollart ändern', size: 'md',
            body: `<div class="nf-types">${tileHTML('NF')}${tileHTML('NA')}</div>`,
            actions: [{ label: 'Abbrechen', variant: 'ghost', onClick: (d) => d.close() }],
            onOpen: (d) => d.element.querySelectorAll('.nf-type').forEach((b) => b.addEventListener('click', () => {
                const code = b.dataset.code;
                d.close();
                if (code === p.art) return;
                p.art = code;
                snack.success(`Protokollart: ${C.PROT_TYPES[code].label}`);
                commit();
            })),
        }).open();
    },
    share() {
        const p = P();
        new Dialog({
            title: 'Protokoll teilen', size: 'sm',
            body: `<div class="nf-form">${dialogField('Zielfahrzeug', `<select data-custom-dropdown="true" class="ignis-input" id="nf-share">${C.VEHICLES.filter((v) => v.id !== p.vehicle).map((v) => `<option>${esc(v.id)}</option>`).join('')}</select>`)}
                <div class="ignis-alert ignis-alert--info">${fa('fa-circle-info', 'ignis-alert__icon')}<div class="ignis-alert__body">Das ausgewählte Fahrzeug erhält eine Anfrage und kann entscheiden, ob es die Daten in ein bestehendes Protokoll übernehmen oder ein neues Protokoll erstellen möchte.</div></div></div>`,
            actions: [
                { label: 'Abbrechen', variant: 'ghost', onClick: (d) => d.close() },
                { label: 'Teilen', variant: 'primary', primary: true, onClick: (d) => { const v = d.element.querySelector('#nf-share').value; d.close(); snack.success(`Anfrage an ${v} gesendet.`); } },
            ],
        }).open();
    },
    print() {
        const p = P();
        new Dialog({ title: `Protokoll #${p.enr}`, size: 'lg', body: `<div class="nf-dlg-sum">${plausi(p)}${summaryBlocks(p)}</div>`, actions: [{ label: 'Schließen', variant: 'primary', primary: true, onClick: (d) => d.close() }] }).open();
    },
    availability() {
        new Dialog({
            title: 'Verfügbarkeit', size: 'md',
            body: `<div class="nf-avail">${C.HOSPITALS.map((h) => `<div class="nf-avail__row"><div class="nf-avail__name">${fa('fa-hospital')} ${esc(h.name)}</div><div class="ignis-statusline">${h.items.map(([l, t]) => `<span class="ignis-chip" data-tone="${t}"><span class="ignis-dot"></span>${esc(l)} ${t === 'ok' ? 'frei' : t === 'warn' ? 'eingeschränkt' : 'abgemeldet'}</span>`).join('')}</div></div>`).join('')}</div>
                <p class="nf-hint">Stand ${C.fmtTime(new Date())} Uhr · Demo-Daten</p>`,
            actions: [{ label: 'Schließen', variant: 'primary', primary: true, onClick: (d) => d.close() }],
        }).open();
    },
    anmeldung() {
        const p = P();
        new Dialog({
            title: 'Anmeldung in der Klinik', size: 'sm',
            body: `<p class="ignis-dialog__text">Voranmeldung für <b>${esc(p.ziel.einrichtung)}</b> mit Kurzbefund und ETA senden?</p>`,
            actions: [{ label: 'Abbrechen', variant: 'ghost', onClick: (d) => d.close() }, { label: 'Anmelden', variant: 'primary', primary: true, onClick: (d) => { d.close(); snack.success('Voranmeldung gesendet.'); } }],
        }).open();
    },
    proto() { ui.topbarMenu = false; protoDialog(); render(); },
    theme() { setTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light'); render(); },

    /* Übersicht */
    reload() { snack.info(`Liste aktualisiert (${C.fmtTime(new Date())} Uhr).`); render(); },
    async pdel(el) {
        const p = S.protocols[el.dataset.enr];
        if (p.createdBy === 'leitstelle') { Dialog.alert('Protokolle der Leitstelle können nicht gelöscht werden.', { type: 'warning', title: 'Löschen nicht möglich' }); return; }
        const ok = await Dialog.confirm('Möchten Sie dieses Protokoll wirklich löschen?', { title: 'Protokoll löschen', confirmText: 'Löschen', danger: true });
        if (!ok) return;
        delete S.protocols[p.enr];
        S.order = S.order.filter((e) => e !== p.enr);
        snack.success(`Protokoll #${p.enr} gelöscht.`);
        persist(); render();
    },
    async 'pdel-all'() {
        const ok = await Dialog.confirm('Wirklich ALLE offenen Protokolle dieses Fahrzeugs löschen? Sie werden als vom Benutzer gelöscht markiert.', { title: 'Alle löschen', confirmText: 'Alle löschen', danger: true });
        if (!ok) return;
        const keep = (p) => p.vehicle !== S.session.vehicle || p.released || p.createdBy === 'leitstelle';
        const del = S.order.filter((e) => !keep(S.protocols[e]));
        del.forEach((e) => delete S.protocols[e]);
        S.order = S.order.filter((e) => S.protocols[e]);
        snack.success(`${del.length} Protokoll(e) gelöscht. Freigegebene und Leitstellen-Protokolle bleiben.`);
        persist(); render();
    },
    qlink(el) { snack.info(`${el.dataset.val}: im Prototyp ohne Ziel.`); },
    vinfo() {
        const v = C.VEHICLES.find((x) => x.id === S.session.vehicle) || {};
        new Dialog({ title: 'Fahrzeuginfo', size: 'sm', body: `<div class="nf-form">${kv('Funkrufname', v.id, { mono: true })}${kv('Typ', v.type)}${kv('Wache', v.wache)}${kv('Kennzeichen', 'LF-RD 831')}${kv('Nächste HU', '03/2027')}${kv('Kilometerstand', '184.312 km', { mono: true })}</div>`, actions: [{ label: 'Schließen', variant: 'primary', primary: true, onClick: (d) => d.close() }] }).open();
    },
    logbook() {
        const rows = [['07:02', 'Dienstbeginn, Fahrzeugcheck', '184.188'], ['09:14', 'Einsatz #2610200912', '184.231'], ['11:40', 'Tanken, RW Lindenfurt-Nord', '184.266'], [C.fmtTime(new Date(Date.now() - 55 * 60000)), 'Einsatz #2610201437', '184.290']];
        new Dialog({ title: 'Fahrtenbuch', size: 'md', body: `<table class="ignis-table"><thead><tr><th>Zeit</th><th>Eintrag</th><th class="ignis-table__num">km</th></tr></thead><tbody>${rows.map((r) => `<tr><td class="ignis-mono">${r[0]}</td><td>${esc(r[1])}</td><td class="ignis-table__num ignis-mono">${r[2]}</td></tr>`).join('')}</tbody></table>`, actions: [{ label: 'Schließen', variant: 'primary', primary: true, onClick: (d) => d.close() }] }).open();
    },

    /* Anlegen */
    'create-type'(el) { ui.create.type = el.dataset.val; if (ui.create.err === 'type') ui.create.err = ''; render(); },
    async create() {
        const c = ui.create;
        const enr = c.enr.trim();
        if (!enr) { c.err = 'enr'; render(); return; }
        if (!/^[0-9_]+$/.test(enr)) { c.err = 'enrInvalid'; render(); return; }
        if (!c.type) { c.err = 'type'; render(); return; }
        let finalEnr = enr;
        if (S.protocols[enr]) {
            const ok = await new Dialog({
                title: 'Protokoll bereits vorhanden', size: 'sm',
                body: `<p class="ignis-dialog__text">Für die Einsatznummer <span class="ignis-mono">#${esc(enr)}</span> gibt es auf diesem Fahrzeug bereits ein Protokoll. Ein weiteres Protokoll bekommt eine angehängte Nummer.</p>`,
                actions: [{ label: 'Abbrechen', variant: 'ghost', onClick: (d) => d.close(false) }, { label: 'Trotzdem erstellen', variant: 'primary', primary: true, onClick: (d) => d.close(true) }],
            }).open();
            if (!ok) return;
            let n = 2;
            while (S.protocols[`${enr}_${n}`]) n++;
            finalEnr = `${enr}_${n}`;
        }
        const p = C.emptyProtocol(finalEnr, c.type, S.session.vehicle);
        p.zeiten.alarm = timeNow();
        p.absch.fzgTransp = S.session.vehicle;
        S.protocols[finalEnr] = p;
        S.order.unshift(finalEnr);
        ui.create = { enr: '', type: '', err: '' };
        persist();
        snack.success(`Protokoll #${finalEnr} erstellt.`);
        go(href('p', finalEnr, 'rett'));
    },

    /* Anmeldung */
    'login-join'() {
        const L = ui.login;
        S.session = { vehicle: L.vehicle, ...structuredClone(C.EXISTING_CREWS[L.vehicle]) };
        if (L.fahrer.name && !Object.values(C.EXISTING_CREWS[L.vehicle]).some((m) => m.name === L.fahrer.name)) S.session.praktikant = { ...L.fahrer };
        ui.login = null;
        persist();
        snack.success(`Besatzung ${S.session.vehicle} beigetreten.`);
        go('#/overview');
    },
    'login-new'() { ui.login.newCrew = true; render(); },
    'login-clear'() {
        const L = ui.login;
        ['fahrer', 'beifahrer', 'praktikant'].forEach((r) => { L[r] = { name: '', quali: '---' }; });
        L.errs = {};
        render();
    },
    'login-swap'() { const L = ui.login; [L.fahrer, L.beifahrer] = [L.beifahrer, L.fahrer]; render(); },
    'login-ok'() {
        const L = ui.login;
        L.errs = {};
        if (!L.vehicle) L.errs.vehicle = true;
        if (!L.fahrer.name.trim()) L.errs.fahrer = 'name';
        else if (!L.fahrer.quali || L.fahrer.quali === '---') L.errs.fahrer = 'quali';
        if (Object.keys(L.errs).length) { render(); return; }
        S.session = { vehicle: L.vehicle, fahrer: { ...L.fahrer }, beifahrer: { ...L.beifahrer }, praktikant: { ...L.praktikant } };
        ui.login = null;
        persist();
        snack.success(`Angemeldet auf ${S.session.vehicle}.`);
        go('#/overview');
    },

    /* Sperre */
    pin(el) { pinPress(el.dataset.k); },
};

function pinPress(k) {
    if (k === 'back') ui.pin = ui.pin.slice(0, -1);
    else if (k === 'ok') return pinCheck();
    else if (ui.pin.length < 4) ui.pin += k;
    ui.pinErr = false;
    render();
    if (ui.pin.length === 4) setTimeout(pinCheck, 120);
}
function pinCheck() {
    if (ui.pin === C.DEMO_PIN) {
        ui.pin = '';
        ui.pinErr = false;
        const back = ui.lockReturn && ui.lockReturn !== '#/lock' ? ui.lockReturn : '#/overview';
        go(back);
        return;
    }
    ui.pin = '';
    ui.pinErr = true;
    render();
}

function setTheme(t) {
    document.documentElement.dataset.theme = t;
    try { localStorage.setItem(THEME_KEY, t); } catch (e) { /* egal */ }
}

function protoDialog() {
    const theme = document.documentElement.dataset.theme;
    const sw = (id, label, on) => `<label class="ignis-switch nf-switch"><input type="checkbox" id="${id}" ${on ? 'checked' : ''}><span>${esc(label)}</span></label>`;
    new Dialog({
        title: 'Prototyp', size: 'md',
        body: `<div class="nf-form">
            <div><div class="ignis-micro">Darstellung</div>
                <div class="ignis-segmented ignis-segmented--switch nf-seg-block" role="group" aria-label="Thema">
                    <button type="button" data-theme-set="dark" aria-pressed="${theme !== 'light'}">${fa('fa-moon')}Dunkel</button>
                    <button type="button" data-theme-set="light" aria-pressed="${theme === 'light'}">${fa('fa-sun')}Hell</button>
                </div></div>
            <div><div class="ignis-micro">Zustände simulieren</div>
                <div class="nf-switches">
                    ${sw('nf-sw-lst', 'Verbindung zur Leitstelle', S.conn.lst)}
                    ${sw('nf-sw-ses', 'Session aktiv', S.conn.session)}
                    ${sw('nf-sw-fail', 'Autosave schlägt fehl', S.failSave)}
                    ${sw('nf-sw-ro', 'Nur Ansicht (fremdes Fahrzeug)', S.readonly)}
                    ${sw('nf-sw-ql', 'Quicklinks leer', S.quicklinksEmpty)}
                </div></div>
            <div class="ignis-alert ignis-alert--info">${fa('fa-circle-info', 'ignis-alert__icon')}<div class="ignis-alert__body">Alle Daten sind erfunden und liegen nur in diesem Browser (localStorage, Präfix <span class="ignis-mono">enotf-funke:</span>). Sperr-PIN: <span class="ignis-mono">1234</span>.</div></div>
        </div>`,
        actions: [
            { label: 'Beispiel zurücksetzen', variant: 'ghost-danger', pullLeft: true, onClick: async (d) => {
                d.close();
                const ok = await Dialog.confirm('Alle Protokolle und die Anmeldung auf das Beispiel zurücksetzen?', { title: 'Beispiel zurücksetzen', confirmText: 'Zurücksetzen', danger: true });
                if (!ok) return;
                S = freshState();
                Object.assign(ui, { kp: null, draft: null, login: null, med: null, zugSel: null, diagQ: '', create: { enr: '', type: '', err: '' } });
                persist();
                snack.success('Beispiel zurückgesetzt.');
                go(href('p', '2610201437', 'rett'));
            } },
            { label: 'Fertig', variant: 'primary', primary: true, onClick: (d) => d.close() },
        ],
        onOpen: (d) => {
            d.element.querySelectorAll('[data-theme-set]').forEach((b) => b.addEventListener('click', () => {
                setTheme(b.dataset.themeSet);
                d.element.querySelectorAll('[data-theme-set]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                render();
            }));
            const bindSw = (id, fn) => d.element.querySelector(id).addEventListener('change', (e) => { fn(e.target.checked); persist(); if (S.failSave && P()) flashAutosave(); render(); });
            bindSw('#nf-sw-lst', (v) => { S.conn.lst = v; });
            bindSw('#nf-sw-ses', (v) => { S.conn.session = v; });
            bindSw('#nf-sw-fail', (v) => { S.failSave = v; if (!v && ui.autosave === 'error') ui.autosave = 'saved'; });
            bindSw('#nf-sw-ro', (v) => { S.readonly = v; });
            bindSw('#nf-sw-ql', (v) => { S.quicklinksEmpty = v; });
        },
    }).open();
}

/* ══ Eingaben ═══════════════════════════════════════════════════ */

function mask(v, kind) {
    const d = v.replace(/\D/g, '');
    if (kind === 'date') return d.length > 4 ? `${d.slice(0, 2)}.${d.slice(2, 4)}.${d.slice(4, 8)}` : d.length > 2 ? `${d.slice(0, 2)}.${d.slice(2)}` : d;
    if (kind === 'time') return d.length > 2 ? `${d.slice(0, 2)}:${d.slice(2, 4)}` : d;
    if (kind === 'times') return d.length > 4 ? `${d.slice(0, 2)}:${d.slice(2, 4)}:${d.slice(4, 6)}` : d.length > 2 ? `${d.slice(0, 2)}:${d.slice(2)}` : d;
    return v;
}
function scopeObj(scope) {
    return { p: P(), d: ui.draft, l: ui.login, c: ui.create, m: ui.med, k: ui.kp }[scope];
}
const LIVE = {
    poi(el) {
        ui.draft.poiOpen = true;
        const q = el.value.toLowerCase();
        const hits = q ? C.POIS.filter((x) => (x.name + ' ' + x.strasse + ' ' + x.ort).toLowerCase().includes(q)).slice(0, 6) : [];
        const list = document.getElementById('nf-poi-list');
        list.innerHTML = poiHits(hits);
        list.hidden = !hits.length;
        el.setAttribute('aria-expanded', String(!!hits.length));
    },
    lines(el) {
        const n = lineCount(el.value);
        const c = document.getElementById('nf-lines');
        if (c) c.textContent = `${n} / 22 Zeilen`;
        const w = document.getElementById('nf-lines-warn');
        if (w) w.hidden = n <= 22;
    },
    diagq(el) {
        ui.diagQ = el.value;
        const p = P();
        const topic = topicsOf('diag', p).find((t) => t.key === ui.route[3]);
        const sub = topic.subs.find((s) => s.key === ui.route[4]) || topic.subs[0];
        const box = document.getElementById('nf-diag-results');
        if (!ui.diagQ.trim()) { render(); document.getElementById('nf-diag-q')?.focus(); return; }
        box.innerHTML = diagResults(p, topic, sub);
    },
    dosis(el) {
        const m = /^\s*([\d.,]+)\s*(µg|ug|mcg|mg|g|ml|IE|I\.E\.)\s*$/i.exec(el.value);
        if (m) {
            const u = m[2].toLowerCase();
            ui.med.einheit = u === 'ug' || u === 'mcg' || u === 'µg' ? 'µg' : u === 'ie' || u === 'i.e.' ? 'IE' : u === 'ml' ? 'ml' : u === 'mg' ? 'mg' : 'g';
            ui.med.dosis = m[1];
            el.value = m[1];
            document.querySelectorAll('[data-act="med-unit"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.val === ui.med.einheit)));
            snack.info(`Einheit erkannt: ${ui.med.einheit}`, { duration: 1800 });
        }
    },
    symzeit(el) {
        if (el.value && P().anam.symNicht) { P().anam.symNicht = false; }
    },
};

document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-act]');
    if (ui.topbarMenu && !e.target.closest('.nf-crew-wrap')) { ui.topbarMenu = false; if (!el) render(); }
    if (!el || el.disabled) return;
    const fn = ACT[el.dataset.act];
    if (!fn) return;
    if (el.tagName === 'BUTTON' || el.getAttribute('role') === 'button') e.preventDefault();
    fn(el, e);
});

document.addEventListener('keydown', (e) => {
    const t = e.target;
    if ((e.key === 'Enter' || e.key === ' ') && t.matches && t.matches('g[data-act]')) { e.preventDefault(); ACT[t.dataset.act](t, e); return; }
    if (document.querySelector('.ignis-dialog')) return;
    if (ui.route[0] === 'lock') {
        if (/^\d$/.test(e.key)) pinPress(e.key);
        else if (e.key === 'Backspace') pinPress('back');
        else if (e.key === 'Enter') pinPress('ok');
        return;
    }
    if (ui.kp && app.querySelector('.nf-kp') && !(t.matches && t.matches('input, textarea, select'))) {
        if (/^\d$/.test(e.key)) { e.preventDefault(); kpPress(e.key); } else if (e.key === ',' || e.key === '.') { e.preventDefault(); kpPress(','); } else if (e.key === 'Backspace') { e.preventDefault(); kpPress('back'); } else if (e.key === 'Delete') { e.preventDefault(); kpPress('clear'); }
    }
    if (e.key === 'Escape' && ui.topbarMenu) { ui.topbarMenu = false; render(); }
});

document.addEventListener('input', (e) => {
    const el = e.target;
    if (!el.dataset || !el.dataset.bind || el.tagName === 'SELECT' || el.type === 'checkbox') {
        if (el.dataset?.live && !el.dataset.bind) LIVE[el.dataset.live]?.(el);
        return;
    }
    const [scope, path] = el.dataset.bind.split(':');
    if (el.dataset.mask) {
        const v = mask(el.value, el.dataset.mask);
        if (v !== el.value) el.value = v;
    }
    if (scope === 'p' && ro()) return;
    const obj = scopeObj(scope);
    if (!obj) return;
    set(obj, path, el.value);
    if (el.dataset.live) LIVE[el.dataset.live]?.(el);
    if (scope === 'p') {
        soft();
        if (path === 'pat.gebdat') { const a = document.getElementById('nf-age'); if (a) a.value = ageOf(el.value); }
    }
    if (scope === 'd') { const pv = document.getElementById('nf-addr-preview'); if (pv) pv.textContent = C.addressLine(ui.draft.a); }
    if (scope === 'c' && ui.create.err && ui.create.err !== 'type') { ui.create.err = ''; el.removeAttribute('aria-invalid'); el.closest('.ignis-field')?.querySelector('.ignis-field__error')?.remove(); }
});

document.addEventListener('change', (e) => {
    const el = e.target;
    if (!el.dataset?.bind || el.tagName !== 'SELECT') return;
    const [scope, path] = el.dataset.bind.split(':');
    if (scope === 'p' && guardRO()) { render(); return; }
    const obj = scopeObj(scope);
    set(obj, path, el.value);
    if (scope === 'l') { if (path === 'vehicle') { ui.login.newCrew = false; ui.login.errs.vehicle = false; } render(); return; }
    if (scope === 'p') commit(); else render();
});

window.addEventListener('hashchange', () => {
    ui.topbarMenu = false;
    const r = parseRoute();
    if (r[0] !== 'p' || r[2] !== 'diag') ui.diagQ = '';
    if (r[0] === 'p' && r[1] !== ui.route[1]) { ui.kp = null; ui.draft = null; ui.med = null; ui.zugSel = null; ui.autosave = 'idle'; }
    render();
});

let resizeT = null;
window.addEventListener('resize', () => { clearTimeout(resizeT); resizeT = setTimeout(drawChart, 120); });

setInterval(() => {
    const c = document.getElementById('ignis-tablet-clock');
    if (c) c.outerHTML = clockHTML();
}, 15000);

if (!location.hash) location.hash = '#/overview';
render();
