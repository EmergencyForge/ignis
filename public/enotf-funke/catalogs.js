/**
 * eNOTF im Funke-Look: Kataloge, Schwellen, Pflichtangaben, Beispielprotokoll.
 *
 * Konventionen in den Optionslisten:
 *   "*frei"  normaler Befund (N), wird in Ok-Ton markiert
 *   "!keine" exklusive Option in Mehrfachauswahl
 * Gespeichert wird immer das Label ohne Präfix.
 *
 * Alle Namen, Fahrzeuge, Adressen und Codes sind erfunden.
 */

/* ── Allgemein ─────────────────────────────────────────────────── */

export const SECTIONS = [
    { key: 'rett', label: 'Rett. Daten', icon: 'fa-clipboard-list' },
    { key: 'erst', label: 'Erstbefund', icon: 'fa-stethoscope' },
    { key: 'anam', label: 'Anamnese', icon: 'fa-comment-medical' },
    { key: 'diag', label: 'Diagnose', icon: 'fa-file-medical' },
    { key: 'verlauf', label: 'Verlauf', icon: 'fa-chart-line' },
    { key: 'mass', label: 'Maßnahmen', icon: 'fa-syringe' },
    { key: 'absch', label: 'Abschluss', icon: 'fa-flag-checkered' },
];

export const PROT_TYPES = {
    NF: { code: 'NF', label: 'Notfallprotokoll', icon: 'fa-truck-medical' },
    NA: { code: 'NA', label: 'Notarztprotokoll', icon: 'fa-user-doctor' },
};

export const QUALIS = ['---', 'Rettungshelfer', 'Rettungssanitäter', 'Rettungsassistent', 'Notfallsanitäter', 'Notarzt', 'Praktikant'];

export const VEHICLES = [
    { id: 'RTW 3/83-1', type: 'RTW', wache: 'RW Lindenfurt-Nord' },
    { id: 'RTW 3/83-2', type: 'RTW', wache: 'RW Lindenfurt-Nord' },
    { id: 'RTW 4/83-1', type: 'RTW', wache: 'RW Brückheim' },
    { id: 'KTW 3/85-1', type: 'KTW', wache: 'RW Lindenfurt-Nord' },
    { id: 'NEF 3/82-1', type: 'NEF', wache: 'RW Lindenfurt-Mitte' },
    { id: 'RTH Falke 7', type: 'RTH', wache: 'Luftrettung Hohenau' },
];

/** Fahrzeug, auf dem bereits eine Besatzung angemeldet ist (für den Login-Hinweis). */
export const EXISTING_CREWS = {
    'RTW 3/83-2': {
        fahrer: { name: 'Pia Hollerbach', quali: 'Notfallsanitäter' },
        beifahrer: { name: 'Malte Ruhland', quali: 'Rettungssanitäter' },
        praktikant: { name: '', quali: '---' },
    },
};

export const QUICKLINKS = [
    { label: 'SOP Brustschmerz', icon: 'fa-heart-pulse', note: 'ÄLRD Lindenfurt' },
    { label: 'Medikamentenliste', icon: 'fa-pills', note: 'Stand 09/2026' },
    { label: 'Kliniken & Fachabteilungen', icon: 'fa-hospital', note: 'Kreis Lindenfurt' },
    { label: 'Desinfektionsplan', icon: 'fa-droplet', note: 'Hygiene' },
    { label: 'Funkrufnamen', icon: 'fa-tower-broadcast', note: 'Leitstelle Hohenau' },
    { label: 'Dienstplan', icon: 'fa-calendar', note: 'KW 43' },
];

export const POIS = [
    { name: 'Klinikum Lindenfurt', strasse: 'Am Klinikpark', hnr: '1', plz: '34512', ort: 'Lindenfurt', ortsteil: 'Mitte' },
    { name: 'St.-Annen-Krankenhaus Brückheim', strasse: 'Annenweg', hnr: '8', plz: '34520', ort: 'Brückheim', ortsteil: '' },
    { name: 'Seniorenresidenz Lindenhof', strasse: 'Lindenhofstraße', hnr: '22', plz: '34512', ort: 'Lindenfurt', ortsteil: 'Süd' },
    { name: 'Hausarztpraxis Dr. Velten', strasse: 'Marktgasse', hnr: '4', plz: '34512', ort: 'Lindenfurt', ortsteil: 'Altstadt' },
    { name: 'Sporthalle Am Wehr', strasse: 'Wehrstraße', hnr: '15', plz: '34514', ort: 'Lindenfurt', ortsteil: 'Nord' },
    { name: 'Gesamtschule Hohenau', strasse: 'Schulberg', hnr: '2', plz: '34530', ort: 'Hohenau', ortsteil: '' },
    { name: 'Bahnhof Lindenfurt', strasse: 'Bahnhofsplatz', hnr: '1', plz: '34512', ort: 'Lindenfurt', ortsteil: 'Mitte' },
    { name: 'Fachklinik Weidenbruch', strasse: 'Kurallee', hnr: '30', plz: '34541', ort: 'Weidenbruch', ortsteil: '' },
];

export const HOSPITALS = [
    { name: 'Klinikum Lindenfurt', items: [['Schockraum', 'ok'], ['Stroke Unit', 'ok'], ['Herzkatheterlabor', 'warn'], ['Intensiv', 'danger']] },
    { name: 'St.-Annen-Krankenhaus Brückheim', items: [['Schockraum', 'ok'], ['Stroke Unit', 'danger'], ['Herzkatheterlabor', 'ok'], ['Intensiv', 'ok']] },
    { name: 'Fachklinik Weidenbruch', items: [['Schockraum', 'danger'], ['Stroke Unit', 'danger'], ['Herzkatheterlabor', 'danger'], ['Intensiv', 'warn']] },
];

/* ── Rett. Daten ───────────────────────────────────────────────── */

export const GESCHLECHT = ['---', 'männlich', 'weiblich', 'divers', 'unbekannt'];

export const VERSORGUNG = [
    { code: '0', label: 'ambulante Versorgung vor Ort' },
    { code: '1', label: 'Transport ohne NA (oder mit TNA)', transport: true },
    { code: '2', label: 'Transport mit NA (bodengebunden)', transport: true },
    { code: '3', label: 'Transport mit NA (RTH)', transport: true },
    { code: '5', label: 'Übergabe anderes Rettungsmittel' },
    { code: '4', label: 'Fehleinsatz - kein Patient', fehl: true },
    { code: '6', label: 'Patient nicht transportfähig' },
    { code: '7', label: 'primäre Todesfeststellung (ohne CPR)' },
    { code: '9', label: 'Sonstige' },
];

export const EINSATZART = [
    'Notfallrettung - Primäreinsatz mit NA',
    'Notfallrettung - Primäreinsatz ohne NA',
    'Notfallrettung - Verlegung mit NA',
    'Notfallrettung - Verlegung ohne NA',
    'Intensivtransport',
    'Krankentransport',
];

export const ZEITEN = [
    { key: 'alarm', label: 'Alarm', mark: true },
    { key: 'aus', label: 'aus (3)' },
    { key: 'ean', label: 'E.-an (4)' },
    { key: 'patan', label: 'Pat.-an', mark: true },
    { key: 'eab', label: 'E.-ab (7)', mark: true },
    { key: 'khan', label: 'KH an (8)', mark: true },
    { key: 'frei', label: 'frei (1)' },
    { key: 'wache', label: 'Wache (2)' },
    { key: 'ende', label: 'Ende', mark: true },
];

/* ── Erstbefund ────────────────────────────────────────────────── */

const F = (path, label, options, extra = {}) => ({ path, label, options, type: 'single', ...extra });
const M = (path, label, options, extra = {}) => ({ path, label, options, type: 'multi', ...extra });

export const OPT = {
    awz: ['*frei', 'gefährdet', 'verlegt'],
    zyanose: ['*Nein', 'Ja'],
    atmung: ['*unauffällig', 'Dyspnoe', 'Schnappatmung', 'Apnoe', 'Beatmung', 'Hyperventilation', 'sonstige path. Atemmuster', 'nicht untersucht'],
    ausk: ['*unauffällig', 'Spastik', 'Stridor', 'Rasselgeräusche', 'Andere Pathologien', 'nicht beurteilbar', 'nicht untersucht'],
    pz: ['*stabil', 'instabil', 'nicht beurteilbar'],
    rekap: ['*< 2 s', '> 2 s'],
    puls: ['*regelmäßig', 'arrhythmisch'],
    radialis: ['*Radialispuls tastbar', 'Radialispuls nicht tastbar'],
    blutung: ['*nein', 'ja'],
    bwl: ['*wach', 'analgosediert / Narkose', 'reagiert auf Ansprache', 'reagiert auf Schmerzreiz', 'bewusstlos', 'Sonstige', 'nicht beurteilbar', 'nicht untersucht'],
    extr: ['*uneingeschränkt', 'leicht eingeschränkt', 'stark eingeschränkt', 'nicht beurteilbar'],
    pupille: ['weit', '*mittel', 'eng', 'entrundet', 'Nicht untersucht'],
    licht: ['*prompt', 'träge', 'keine', 'Nicht untersucht'],
    ekg: ['*Sinusrhythmus', 'Absolute Arrhythmie', 'AV-Block II', 'AV-Block III', 'Kammerflimmern,-flattern', 'Pulslose elektrische Aktivität', 'Asystolie', 'Schrittmacherrhythmus', 'Infarkt EKG / STEMI', 'Linksschenkelblock', 'Rechtsschenkelblock', 'Sonstige', 'Nicht beurteilbar', 'Kein EKG'],
    psych: ['*!unauffällig', 'aggressiv', 'depressiv', 'wahnhaft', 'verwirrt', 'verlangsamt', 'euphorisch', 'erregt', 'ängstlich', 'suizidal', 'motorisch unruhig', 'Sonstige', '!nicht beurteilbar', '!nicht untersucht'],
    nrs: ['*keine', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'nicht erhoben', 'nicht beurteilbar'],
    toleranz: ['*Tolerabel', 'Nicht tolerabel'],
};

export const GCS = [
    { key: 'gcsA', label: 'Augen öffnen', options: [['spontan', 4], ['auf Aufforderung', 3], ['auf Schmerzreiz', 2], ['kein Öffnen', 1]] },
    { key: 'gcsV', label: 'Beste verbale Reaktion', options: [['orientiert', 5], ['desorientiert', 4], ['inadäquate Äußerungen', 3], ['unverständliche Laute', 2], ['keine Reaktion', 1]] },
    { key: 'gcsM', label: 'Beste motorische Reaktion', options: [['folgt Aufforderung', 6], ['gezielte Abwehrbewegungen', 5], ['ungezielte Abwehrbewegungen', 4], ['Beugesynergismen', 3], ['Strecksynergismen', 2], ['keine Reaktion', 1]] },
];

export const REGIONS = [
    { key: 'schaedel', label: 'Schädel-Hirn' },
    { key: 'ws', label: 'Wirbelsäule' },
    { key: 'thorax', label: 'Thorax' },
    { key: 'abdomen', label: 'Abdomen' },
    { key: 'oex', label: 'Obere Extremitäten' },
    { key: 'uex', label: 'Untere Extremitäten' },
];
export const SEVERITY = ['Keine', 'Leicht', 'Mittel', 'Schwer'];
export const WUNDART = ['Offen', 'Geschlossen'];

/** Domänenfarben der Körperkarte (medizinische Bedeutung, daher Literale). */
export const SEVERITY_FILL = { Keine: '#9ca3af', Leicht: '#22c55e', Mittel: '#eab308', Schwer: '#ef4444' };

/**
 * Themen des Erstbefunds: Themen-Spalte → Unterthemen-Spalte → Antworten.
 * `quick` setzt den Normalbefund in einem Tipp.
 */
export const ERST_TOPICS = [
    {
        key: 'atemwege', label: 'Atemwege', icon: 'fa-wind',
        quick: { label: 'ohne path. Befund', set: { 'erst.awz': 'frei', 'erst.zyanose': 'Nein' } },
        subs: [{ key: 'atemwege', label: 'Atemwege', fields: [F('erst.awz', 'Atemwegszustand', OPT.awz), F('erst.zyanose', 'Zyanose', OPT.zyanose)] }],
    },
    {
        key: 'atmung', label: 'Atmung', icon: 'fa-lungs',
        quick: { label: 'ohne path. Befund', set: { 'erst.atmung': 'unauffällig', 'erst.ausk': 'unauffällig' } },
        subs: [{ key: 'atmung', label: 'Atmung', fields: [F('erst.atmung', 'Beurteilung Atmung', OPT.atmung), F('erst.ausk', 'Auskultation', OPT.ausk)] }],
    },
    {
        key: 'kreislauf', label: 'Kreislauf', icon: 'fa-heart-pulse',
        quick: { label: 'ohne path. Befund', set: { 'erst.pz': 'stabil', 'erst.radialis': 'Radialispuls tastbar', 'erst.puls': 'regelmäßig', 'erst.rekap': '< 2 s', 'erst.blutung': 'nein' } },
        subs: [
            { key: 'pz', label: 'Patientenzustand', fields: [F('erst.pz', 'Patientenzustand', OPT.pz)] },
            { key: 'rekap', label: 'Rekap. Zeit', fields: [F('erst.rekap', 'Rekap. Zeit', OPT.rekap)] },
            { key: 'puls', label: 'Puls', fields: [F('erst.puls', 'Pulsqualität', OPT.puls), F('erst.radialis', 'Radialispuls', OPT.radialis)] },
            { key: 'blutung', label: 'starke Blutung', fields: [F('erst.blutung', 'starke Blutung', OPT.blutung)] },
        ],
    },
    {
        key: 'neuro', label: 'Neurologie', icon: 'fa-brain',
        quick: { label: 'ohne path. Befund', set: { 'erst.bwl': 'wach', 'erst.extr': 'uneingeschränkt', 'erst.pupL': 'mittel', 'erst.pupR': 'mittel', 'erst.lichtL': 'prompt', 'erst.lichtR': 'prompt', 'erst.gcsA': 'spontan', 'erst.gcsV': 'orientiert', 'erst.gcsM': 'folgt Aufforderung' } },
        subs: [
            { key: 'bwl', label: 'Bewusstseinslage', fields: [F('erst.bwl', 'Bewusstseinslage', OPT.bwl)] },
            { key: 'extr', label: 'Extremitätenbewegung', fields: [F('erst.extr', 'Extremitätenbewegung', OPT.extr)] },
            { key: 'pupillen', label: 'Pupillen', custom: 'pupillen' },
            { key: 'gcs', label: 'GCS', custom: 'gcs' },
        ],
    },
    {
        key: 'erweitern', label: 'Erweitern', icon: 'fa-person',
        quick: { label: 'keine', set: { 'erst.verl.schaedel': { sev: 'Keine' }, 'erst.verl.ws': { sev: 'Keine' }, 'erst.verl.thorax': { sev: 'Keine' }, 'erst.verl.abdomen': { sev: 'Keine' }, 'erst.verl.oex': { sev: 'Keine' }, 'erst.verl.uex': { sev: 'Keine' } } },
        subs: [
            { key: 'verletzungen', label: 'Verletzungen', custom: 'bodymap' },
            { key: 'schmerzen', label: 'Schmerzen', fields: [F('erst.nrs', 'Intensität (NRS)', OPT.nrs, { cols: 'nrs' }), F('erst.toleranz', 'Toleranz', OPT.toleranz)] },
        ],
    },
    {
        key: 'ekg', label: 'EKG-Befund', icon: 'fa-wave-square',
        subs: [{ key: 'ekg', label: 'EKG-Befund', fields: [F('erst.ekg', 'EKG-Befund', OPT.ekg)] }],
    },
    {
        key: 'psych', label: 'psych. Zustand', icon: 'fa-head-side-virus',
        subs: [{ key: 'psych', label: 'psych. Zustand', fields: [M('erst.psych', 'Psychischer Zustand', OPT.psych)] }],
    },
    { key: 'messwerte', label: 'Messwerte', icon: 'fa-gauge', custom: 'keypad' },
];

/* ── Vitalparameter, Tastenfeld, Schwellen ─────────────────────── */

export const VITALS = [
    { key: 'spo2', label: 'SpO₂', long: 'SpO₂', unit: '%', ph: '96', req: true, scale: [70, 100], norm: '≥ 97 %' },
    { key: 'af', label: 'AF', long: 'Atemfrequenz', unit: '/min', ph: '16', req: true, scale: [0, 35], norm: '9–15 /min' },
    { key: 'etco2', label: 'etCO₂', long: 'etCO₂', unit: 'mmHg', ph: '35', scale: [0, 60], norm: '36–45 mmHg' },
    { key: 'hf', label: 'HF', long: 'Herzfrequenz', unit: '/min', ph: '80', req: true, scale: [20, 210], norm: '61–100 /min' },
    { key: 'rrs', label: 'RR sys', long: 'RR systolisch', unit: 'mmHg', ph: '120', req: true, scale: [0, 260], norm: '101–149 mmHg' },
    { key: 'rrd', label: 'RR dia', long: 'RR diastolisch', unit: 'mmHg', ph: '80', scale: [0, 140], norm: '61–100 mmHg' },
    { key: 'bz', label: 'BZ', long: 'Blutzucker', unit: 'mg/dl', ph: '90', req: true, scale: [0, 360], norm: '81–149 mg/dl' },
    { key: 'temp', label: 'Temp', long: 'Temperatur', unit: '°C', ph: '36,5', scale: [30, 44], norm: '36,1–38,0 °C' },
];
export const VITAL = Object.fromEntries(VITALS.map((v) => [v.key, v]));

/**
 * Bewertung wie im eNOTF: danger / warning / semiwarning / success.
 * Im Funke-Look: danger → danger, warning → warn (kräftig), semiwarning → warn (leise), success → ok.
 */
export function gradeVital(key, v) {
    if (v === null || v === undefined || Number.isNaN(v)) return null;
    switch (key) {
        case 'spo2': return v < 87 ? 'danger' : v < 92 ? 'warning' : v < 97 ? 'semi' : 'ok';
        case 'af': return (v < 5 || v > 25) ? 'danger' : (v <= 6 || v >= 21) ? 'warning' : (v <= 8 || v >= 16) ? 'semi' : 'ok';
        case 'etco2': return (v < 6 || v > 55) ? 'danger' : (v < 36 || v > 45) ? 'semi' : 'ok';
        case 'hf': return (v < 41 || v > 160) ? 'danger' : (v <= 50 || v > 130) ? 'warning' : (v <= 60 || v > 100) ? 'semi' : 'ok';
        case 'rrs': return (v < 80 || v > 199) ? 'danger' : (v < 90 || v >= 170) ? 'warning' : (v <= 100 || v >= 150) ? 'semi' : 'ok';
        case 'rrd': return (v <= 40 || v >= 121) ? 'danger' : (v <= 50 || v >= 111) ? 'warning' : (v <= 60 || v >= 101) ? 'semi' : 'ok';
        case 'bz': return (v < 40 || v > 250) ? 'danger' : (v <= 50 || v >= 180) ? 'warning' : (v <= 80 || v >= 150) ? 'semi' : 'ok';
        case 'temp': return (v <= 34 || v > 40) ? 'danger' : (v < 36.1 || v > 38) ? 'semi' : 'ok';
        default: return null;
    }
}

export const GRADE_META = {
    ok: { tone: 'ok', icon: 'fa-circle-check', word: 'Normbereich' },
    semi: { tone: 'warn', icon: 'fa-circle-half-stroke', word: 'leicht abweichend' },
    warning: { tone: 'warn', icon: 'fa-triangle-exclamation', word: 'abweichend' },
    danger: { tone: 'danger', icon: 'fa-circle-exclamation', word: 'kritisch' },
};

/* ── Anamnese ──────────────────────────────────────────────────── */

// Anamnese-Textbausteine wie in v1/v2: Überschrift plus Unterbausteine mit ganzen Sätzen.
export const TEXTBLOCKS = [
    { label: 'Vorerkrankungen', text: 'VORERKRANKUNGEN:', sub: [
        { label: 'keine bekannt', text: 'Beim Pat. sind keine Vorerkrankungen bekannt.' },
        { label: 'nicht ermittelbar', text: 'Es konnten keine Vorerkrankungen ermittelt werden.' },
    ] },
    { label: 'Medikation', text: 'MEDIKATION:', sub: [
        { label: 'keine Vormedikation', text: 'Beim Pat. ist keine Vormedikation bekannt.' },
        { label: 'nicht ermittelbar', text: 'Es konnte keine Vormedikation ermittelt werden.' },
    ] },
    { label: 'Allergien', text: 'ALLERGIEN:', sub: [
        { label: 'keine Allergien', text: 'Beim Pat. sind keine Allergien bekannt.' },
    ] },
    { label: 'Drogen / Abusus', text: 'DROGEN / ABUSUS:', sub: [
        { label: 'Nikotin', text: 'Bekannter Nikotinabusus' },
        { label: 'Alkohol', text: 'Bekannter Alkoholabusus' },
        { label: 'Opiate', text: 'Bekannter Opiatabusus' },
        { label: 'Benzodiazepine', text: 'Bekannter Benzodiazepinabusus' },
        { label: 'Foetor alcoholicus', text: 'Beim Pat. ist Alkoholgeruch wahrnehmbar.' },
    ] },
];

export const NACA = [
    'NACA 0 - Keine Erkrankung/Verletzung',
    'NACA I - geringfügige Störung',
    'NACA II - leichte Störung',
    'NACA III - mäßige Störung',
    'NACA IV - Lebensgefahr nicht auszuschließen',
    'NACA V - Akute Lebensgefahr',
    'NACA VI - Kreislaufstillstand',
    'NACA VII - Todesfeststellung',
];

export const AZ = [
    '*ohne Vorerkrankungen',
    'Vorerkrankungen ohne nennenswerte Einschränkung des tägl. Lebens',
    'Vorerkrankungen mit nennenswerter Einschränkung des tägl. Lebens',
    'normales tägl. Leben unmöglich',
    'Pat. wird in den nächsten 24h sterben, mit und ohne med. Hilfe',
    'unbekannt',
];

export const EINSATZORT = ['Wohnung', 'Arbeitsplatz', 'Altenheim', 'öffentlicher Raum', 'Arztpraxis', 'Straße', 'Krankenhaus', 'Massenveranstaltung', 'Bildungseinrichtung', 'Sportstätte', 'Geburtshaus/-einrichtung', 'Sonstige', 'nicht dokumentiert'];

/* ── Diagnose ──────────────────────────────────────────────────── */

export const DIAG_CATS = [
    { key: 'zns', label: 'ZNS', icon: 'fa-brain', items: ['Schlaganfall / TIA / ICB', 'ICB (klin. Diagn.)', 'SAB (klin. Diagn.)', 'Krampfanfall', 'Status Epilepticus', 'Meningitis / Encephalitis', 'sonstige Erkrankung ZNS'] },
    { key: 'hk', label: 'Herz-Kreislauf', icon: 'fa-heart-pulse', items: ['ACS / NSTEMI', 'ACS / STEMI', 'Kardiogener Schock', 'tachykarde Arrhythmie', 'bradykarde Arrhythmie', 'Schrittmacher-/ICD Fehlfunktion', 'Lungenembolie', 'Lungenödem', 'hypertensiver Notfall', 'Aortenaneurysma', 'Hypotonie', 'Synkope', 'Thrombose / art. Verschluss', 'Herz-Kreislauf-Stillstand', 'Schock unklarer Genese', 'unklarer Thoraxschmerz', 'orthostatische Fehlregulation', 'hypertensive Krise / Entgleisung', 'sonstige Erkrankung Herz-Kreislauf'] },
    { key: 'aw', label: 'Atemwege', icon: 'fa-lungs', items: ['Asthma (Anfall)', 'Status Asthmaticus', 'exacerbierte COPD', 'Aspiration', 'Pneumonie / Bronchitis', 'Hyperventilation', 'Spontanpneumothorax', 'Hämoptysis', 'Dyspnoe unklarer Ursache', 'sonstige Erkrankung Atmung'] },
    { key: 'abd', label: 'Abdomen', icon: 'fa-notes-medical', items: ['akutes Abdomen', 'obere GI-Blutung', 'untere GI-Blutung', 'Gallenkolik', 'Nierenkolik', 'Kolik allgemein', 'sonstige Erkrankung Abdomen'] },
    { key: 'psy', label: 'Psychiatrie', icon: 'fa-head-side-virus', items: ['psychischer Ausnahmezustand', 'Depression', 'Manie', 'Intoxikation', 'Entzug', 'Delir', 'Suizidalität', 'psychosoziale Krise', 'sonstige Erkrankung Psychiatrie'] },
    { key: 'stw', label: 'Stoffwechsel', icon: 'fa-droplet', items: ['Hypoglykämie', 'Hyperglykämie', 'Urämie / ANV', 'Exsikkose', 'bek. Dialysepflicht', 'sonstige Erkrankung Stoffwechsel'] },
    { key: 'son', label: 'Sonstige', icon: 'fa-kit-medical', items: ['Anaphylaxie Grad 1/2', 'Anaphylaxie Grad 3/4', 'Infekt / Sepsis / Schock', 'Hitzeerschöpfung / Hitzschlag', 'Unterkühlung / Erfrierung', 'akute Lumbago', 'Palliative Situation', 'medizinische Behandlungskompl.', 'Epistaxis / HNO-Erkrankung', 'urologische Erkrankung', 'unklare Erkrankung', 'akzidentelle Intoxikation', 'Augenerkrankung', 'Keine Erkrankung / Verletzung feststellbar'] },
    { key: 'trauma', label: 'Trauma', icon: 'fa-bandage', trauma: true },
];

export const TRAUMA_REGIONS = ['Schädel-Hirn', 'Gesicht', 'HWS', 'Thorax', 'Abdomen', 'BWS / LWS', 'Becken', 'obere Extremitäten', 'untere Extremitäten', 'Weichteile', 'spezielle'];
export const TRAUMA_SEV = ['leicht', 'mittel', 'schwer', 'tödlich'];
export const TRAUMA_SPECIAL = ['Verbrennung / Verbrühung', 'Inhalationstrauma', 'Elektrounfall', '(beinahe-) Ertrinken', 'Tauchunfall', 'Verätzung', 'Sonstige'];

/** Optionen einer Trauma-Region: tödlich nur für die ersten fünf. */
export function traumaOptions(region) {
    if (region === 'spezielle') return TRAUMA_SPECIAL.map((s) => `Trauma spezielle – ${s}`);
    const idx = TRAUMA_REGIONS.indexOf(region);
    const sev = idx < 5 ? TRAUMA_SEV : TRAUMA_SEV.slice(0, 3);
    return sev.map((s) => `Trauma ${region} – ${s}`);
}

export function allDiagnoses() {
    const out = [];
    DIAG_CATS.forEach((c) => {
        if (c.trauma) TRAUMA_REGIONS.forEach((r) => traumaOptions(r).forEach((d) => out.push({ label: d, cat: 'Trauma' })));
        else c.items.forEach((d) => out.push({ label: d, cat: c.label }));
    });
    return out;
}

/* ── Maßnahmen ─────────────────────────────────────────────────── */

export const AW_TOGGLES = [
    { key: 'freim', label: 'Atemwege freim.' },
    { key: 'absaug', label: 'Absaugen' },
    { key: 'entl', label: 'Entlastungspunktion' },
    { key: 'hws', label: 'HWS-Immobilisation' },
];
export const AW_SICH = ['*keine', 'Endotrachealtubus', 'Wendl-Tubus', 'Larynxmaske', 'Larynxtubus', 'Guedeltubus', 'Sonstige'];
export const BEATMUNG = ['Spontanatmung', 'Assistierte Beatmung', 'Kontrollierte Beatmung', 'Maschinelle Beatmung', 'keine'];
export const O2 = ['keine', ...Array.from({ length: 15 }, (_, i) => `${i + 1} L/min`)];

export const PVK_SITES = [
    { key: 'Handrücken', sides: true }, { key: 'Unterarm', sides: true }, { key: 'Ellbeuge', sides: true },
    { key: 'Oberarm', sides: true }, { key: 'Hals', sides: true }, { key: 'Kopf', sides: false },
    { key: 'Bein', sides: true }, { key: 'Fuss', sides: true }, { key: 'Sonstige', sides: false },
];
export const IO_SITES = [
    { key: 'Tibia proximal', sides: true }, { key: 'Tibia distal', sides: true }, { key: 'Humerus proximal', sides: true },
    { key: 'Sternum', sides: false }, { key: 'anderer Ort', sides: false },
];
/** Kanülenfarben nach Norm: Domänenfarben, daher Literale. */
export const PVK_SIZES = [
    { key: '24 G', short: '24G', color: '#ffc107' },
    { key: '22 G', short: '22G', color: '#2563eb' },
    { key: '20 G', short: '20G', color: '#ec4899' },
    { key: '18 G', short: '18G', color: '#28a745' },
    { key: '18 G kurz', short: '18G kurz', color: '#28a745', ring: true },
    { key: '17 G', short: '17G', color: '#ffffff' },
    { key: '16 G', short: '16G', color: '#9ca3af' },
    { key: '14 G', short: '14G', color: '#f97316' },
];
export const IO_SIZES = [
    { key: '15mm', short: '15mm', color: '#dc3545' },
    { key: '25mm', short: '25mm', color: '#dc3545' },
    { key: '45mm', short: '45mm', color: '#dc3545' },
];

export const MEDIKAMENTE = [
    { label: 'Ratiopharm (Paracetamol)', unit: 'mg' },
    { label: 'Fentanyl-Janssen (Fentanyl)', unit: 'µg' },
    { label: 'Heparin-Natrium (Heparin)', unit: 'IE' },
    { label: 'ASS (Acetylsalicylsäure)', unit: 'mg' },
    { label: 'Vomex A (Dimenhydrinat)', unit: 'mg' },
    { label: 'Akrinor (Cafedrin/Theodrenalin)', unit: 'ml' },
    { label: 'Urbason (Methylprednisolon)', unit: 'mg' },
    { label: 'NaCl 0,9 %', unit: 'ml' },
    { label: 'Jonosteril (Vollelektrolyt)', unit: 'ml' },
];
export const APPLIKATION = ['i.v.', 'i.o.', 'i.m.', 's.c.', 's.l.', 'p.o.'];
export const EINHEITEN = ['µg', 'mg', 'g', 'ml', 'IE'];

export const WEITERE_TOGGLES = [
    { key: 'waerme', label: 'passiver Wärmeerhalt' },
    { key: 'repos', label: 'Reposition' },
    { key: 'verband', label: 'Verband' },
];
export const LAGERUNG = ['OK Hochlagerung', 'Flachlagerung', 'Schocklagerung', 'stabile Seitenlage', 'sitzender Transport', 'sonstige Lagerung', 'keine'];
export const RETTUNGSTECHNIK = ['Spineboard', 'KED-System', 'Beckenschlinge', 'Schaufeltrage', 'Vakuummatratze', 'SAMsplint', 'sonstige Immobilisation'];
export const SPEZIAL = ['aktiver Wärmeerhalt', 'Krisenintervention', 'Kühlung', 'Notfallnarkose', 'Tourniquet', 'CPR / HLW'];

/* ── Abschluss ─────────────────────────────────────────────────── */

export const PROT_DURCH = ['---', 'Transportmittel', 'Notarzt'];
export const BESONDERHEITEN = ['*!keine', 'nächste geeignete Klinik nicht aufnahmebereit', 'Patient lehnt indizierte Maßnahmen ab', 'bewusster Therapieverzicht', 'Patient lehnt Transport ab', 'Vorsorgliche Bereitstellung', 'Zwangsunterbringung', 'aufwändige technische Rettung', 'Einsatz mit LNA/OrgL', 'mehrere Patienten', 'MANV', 'kein Notarzt in angemessener Zeit verfügbar', 'erschwerter Patientenzugang', 'verzögerte Patientenübergabe', 'Sonstige'];
export const NACHF_NA = ['*nein', 'ja'];
export const REA = ['*keine Reanimationssituation', 'Reanimation durchgeführt', 'Reanimation nicht durchgeführt'];
export const REA_NICHT = ['weil sichere Todeszeichen', 'weil DNR-Order vorhanden', 'weil aussichtslose Grunderkrankung bekannt', 'weil aussichtslose sonstige Faktoren'];
export const REA_URSACHE = ['kardial', 'Trauma', 'Ertrinken', 'Hypoxie', 'Intoxikation', 'ICB / SAB', 'SIDS', 'Verbluten', 'Stroke', 'metabolisch', 'Sonstiges', 'Sepsis', 'nicht bekannt'];
export const REA_KOLLAPS = ['Ersthelfer', 'First Responder', 'KTW-Besatzung', 'RTW-Besatzung', 'NA-Rettungsmittel', 'Sonstige', 'nicht bekannt'];
export const REA_HDM = ['Ersthelfer', 'First Responder', 'KTW-Besatzung', 'RTW-Besatzung', 'NA-Rettungsmittel', 'Sonstige'];
export const REA_DEFI = ['nicht durchgeführt', 'durchgeführt'];
export const REA_ROSC = ['niemals ROSC', 'jemals ROSC'];
export const REA_AUFNAHME = ['Krankenhausaufnahme mit ROSC', 'Krankenhausaufnahme unter Rea', 'Keine Aufnahme', 'Tod'];
export const UEB_ORT = ['Schockraum', 'Praxis', 'ZNA / INA', 'Stroke Unit', 'Intensivstation', 'OP direkt', 'Hausarzt', 'Fachambulanz', 'Chest Pain Unit', 'Herzkatheterlabor', 'Allgemeinstation', 'Einsatzstelle', 'Sonstige'];
export const UEB_AN = ['Arzt', 'Pflegepersonal', 'Rettungssanitäter', 'Rettungsassistent', 'Notfallsanitäter', 'Polizei', 'Angehörige', 'Sonstige'];

export const KLINIK_URL = 'https://klinik.ignis.example/code';
export const DEMO_PIN = '1234';

/* ── Optionen lesen ────────────────────────────────────────────── */

export function parseOpt(raw) {
    let s = raw;
    let normal = false;
    let excl = false;
    while (s[0] === '*' || s[0] === '!') {
        if (s[0] === '*') normal = true;
        if (s[0] === '!') excl = true;
        s = s.slice(1);
    }
    return { label: s, normal, excl };
}

/* ── Pflichtangaben ────────────────────────────────────────────── */

const isSet = (v) => v !== undefined && v !== null && v !== '' && v !== '---';
const has = (a) => Array.isArray(a) && a.length > 0;
const vitalSet = (v) => isSet(v);

export function versorgungOf(p) {
    return VERSORGUNG.find((v) => v.code === p.einsatz?.versorgung) || null;
}
export const isFehl = (p) => !!versorgungOf(p)?.fehl;
export const isTransport = (p) => !!versorgungOf(p)?.transport;
const med = (p) => !isFehl(p);

export function addressLine(a) {
    if (!a) return '';
    const left = [a.strasse, a.hnr].filter(Boolean).join(' ');
    const right = [a.ort, a.ortsteil].filter(Boolean).join('-');
    return [left, right].filter(Boolean).join(', ');
}

/**
 * Jede Pflichtangabe: Abschnitt, Thema (für Teilnavigation), Prüfung, Meldung.
 * `when` schaltet die Regel situationsabhängig (Versorgung, Protokollart, Reanimation).
 */
export const REQUIRED = [
    { s: 'rett', t: 'pat', when: med, ok: (p) => isSet(p.pat.geschlecht), msg: 'Patienten-Geschlecht ist nicht gesetzt.' },
    { s: 'rett', t: 'von', ok: (p) => isSet(addressLine(p.von)), msg: 'Einsatzort (Von Adresse) ist nicht gesetzt.' },
    { s: 'rett', t: 'einsatz', ok: (p) => isSet(p.einsatz.datum), msg: 'Einsatzdatum ist nicht gesetzt.' },
    { s: 'rett', t: 'einsatz', ok: (p) => isSet(p.einsatz.zeit), msg: 'Einsatzzeit ist nicht gesetzt.' },
    { s: 'rett', t: 'einsatz', ok: (p) => isSet(p.einsatz.versorgung), msg: 'Versorgung ist nicht gesetzt.' },
    { s: 'rett', t: 'einsatz', ok: (p) => isSet(p.einsatz.einsatzart), msg: 'Einsatzart ist nicht gesetzt.' },
    { s: 'rett', t: 'ziel', when: isTransport, ok: (p) => isSet(addressLine(p.ziel)), msg: 'Transportziel (Ziel Adresse) ist bei Transport erforderlich.' },
    { s: 'rett', t: 'zeiten', ok: (p) => isSet(p.zeiten.alarm?.t), msg: 'Zeitpunkt Alarm ist nicht gesetzt.' },
    { s: 'rett', t: 'zeiten', when: med, ok: (p) => isSet(p.zeiten.patan?.t), msg: 'Zeitpunkt Pat.-an ist nicht gesetzt.' },
    { s: 'rett', t: 'zeiten', when: isTransport, ok: (p) => isSet(p.zeiten.eab?.t), msg: 'Zeitpunkt E.-ab (7) ist bei Transport erforderlich.' },
    { s: 'rett', t: 'zeiten', when: isTransport, ok: (p) => isSet(p.zeiten.khan?.t), msg: 'Zeitpunkt KH an (8) ist bei Transport erforderlich.' },
    { s: 'rett', t: 'zeiten', ok: (p) => isSet(p.zeiten.ende?.t), msg: 'Zeitpunkt Ende ist nicht gesetzt.' },

    { s: 'erst', t: 'atemwege', when: med, ok: (p) => isSet(p.erst.awz), msg: 'Atemwegszustand ist nicht gesetzt.' },
    { s: 'erst', t: 'atemwege', when: med, ok: (p) => isSet(p.erst.zyanose), msg: 'Zyanose ist nicht gesetzt.' },
    { s: 'erst', t: 'atmung', when: med, ok: (p) => isSet(p.erst.atmung), msg: 'Beurteilung Atmung ist nicht gesetzt.' },
    { s: 'erst', t: 'atmung', when: med, ok: (p) => isSet(p.erst.ausk), msg: 'Auskultation ist nicht gesetzt.' },
    { s: 'erst', t: 'kreislauf', st: 'pz', when: med, ok: (p) => isSet(p.erst.pz), msg: 'Patientenzustand ist nicht gesetzt.' },
    { s: 'erst', t: 'kreislauf', st: 'puls', when: med, ok: (p) => isSet(p.erst.puls), msg: 'Pulsqualität ist nicht gesetzt.' },
    { s: 'erst', t: 'kreislauf', st: 'puls', when: med, ok: (p) => isSet(p.erst.radialis), msg: 'Radialispuls ist nicht gesetzt.' },
    { s: 'erst', t: 'neuro', st: 'bwl', when: med, ok: (p) => isSet(p.erst.bwl), msg: 'Bewusstseinslage ist nicht gesetzt.' },
    { s: 'erst', t: 'neuro', st: 'extr', when: med, ok: (p) => isSet(p.erst.extr), msg: 'Extremitätenbewegung ist nicht gesetzt.' },
    { s: 'erst', t: 'neuro', st: 'pupillen', when: med, ok: (p) => isSet(p.erst.pupL), msg: 'Pupillenweite links ist nicht gesetzt.' },
    { s: 'erst', t: 'neuro', st: 'pupillen', when: med, ok: (p) => isSet(p.erst.pupR), msg: 'Pupillenweite rechts ist nicht gesetzt.' },
    { s: 'erst', t: 'neuro', st: 'pupillen', when: med, ok: (p) => isSet(p.erst.lichtL), msg: 'Lichtreaktion links ist nicht gesetzt.' },
    { s: 'erst', t: 'neuro', st: 'pupillen', when: med, ok: (p) => isSet(p.erst.lichtR), msg: 'Lichtreaktion rechts ist nicht gesetzt.' },
    { s: 'erst', t: 'ekg', when: med, ok: (p) => isSet(p.erst.ekg), msg: 'EKG-Befund ist nicht gesetzt.' },
    { s: 'erst', t: 'psych', when: med, ok: (p) => has(p.erst.psych), msg: 'Psychischer Zustand ist nicht gesetzt.' },
    ...VITALS.filter((v) => v.req).map((v) => ({
        s: 'erst', t: 'messwerte', when: med, ok: (p) => vitalSet(p.erst.vitals?.[v.key]), msg: `Vitalparameter ${v.label} ist nicht erfasst.`,
    })),

    { s: 'anam', t: 'symptome', st: 'nacaI', when: med, ok: (p) => isSet(p.anam.nacaI), msg: 'NACA initial ist nicht gesetzt.' },
    { s: 'anam', t: 'ort', when: med, ok: (p) => isSet(p.anam.ort), msg: 'Einsatzort ist nicht gesetzt.' },

    { s: 'diag', t: 'fuehrend', when: med, ok: (p) => isSet(p.diag.fuehrend), msg: 'Führende Diagnose ist nicht gesetzt.' },

    { s: 'mass', t: 'aw', when: med, ok: (p) => isSet(p.mass.awsich), msg: 'Atemwegssicherung ist nicht gesetzt.' },
    { s: 'mass', t: 'atmung', when: med, ok: (p) => isSet(p.mass.beatmung), msg: 'Beatmung ist nicht gesetzt.' },
    { s: 'mass', t: 'atmung', when: med, ok: (p) => isSet(p.mass.o2), msg: 'O2-Gabe ist nicht gesetzt.' },
    { s: 'mass', t: 'zugang', when: med, ok: (p) => p.mass.zugangNone || has(p.mass.zugaenge), msg: 'Zugang ist nicht dokumentiert (oder „Kein Zugang“).' },
    { s: 'mass', t: 'meds', when: med, ok: (p) => p.mass.medsNone || has(p.mass.meds), msg: 'Medikamente sind nicht dokumentiert (oder „Keine Medikamente“).' },

    { s: 'absch', t: 'besond', ok: (p) => has(p.absch.besond), msg: 'Einsatzverlauf Besonderheiten ist nicht gesetzt.' },
    { s: 'absch', t: 'nachf', when: (p) => p.art !== 'NA', ok: (p) => isSet(p.absch.nachfNA), msg: 'Nachforderung NA ist nicht gesetzt.' },
    { s: 'absch', t: 'rea', when: med, ok: (p) => isSet(p.absch.rea), msg: 'Reanimationssituation ist nicht gesetzt.' },
    ...[
        ['ursache', 'Vermutete Ursache'], ['kollaps', 'Kollaps beobachtet'], ['hdmDurch', 'HDM gestartet durch'],
        ['hdmZeit', 'HDM Zeitpunkt'], ['rosc', 'ROSC'], ['aufnahme', 'Krankenhausaufnahme'],
    ].map(([k, l]) => ({
        s: 'absch', t: 'rea', when: (p) => med(p) && p.absch.rea === 'Reanimation durchgeführt', ok: (p) => isSet(p.absch.reaD?.[k]), msg: `Reanimation: ${l} ist nicht gesetzt.`,
    })),
    { s: 'absch', t: 'protokoll', ok: (p) => isSet(p.absch.protokollant), msg: 'Protokollant ist nicht eingetragen.' },
    { s: 'absch', t: 'protokoll', ok: (p) => isSet(p.absch.protDurch), msg: 'Protokoll durch ist nicht gesetzt.' },
];

/* ── Beispielprotokolle ────────────────────────────────────────── */

const pad = (n) => String(n).padStart(2, '0');
export const fmtDate = (d) => `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`;
export const fmtTime = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
export const fmtTimeS = (d) => `${fmtTime(d)}:${pad(d.getSeconds())}`;

export function emptyProtocol(enr, art, vehicle) {
    const now = new Date();
    return {
        enr, art, vehicle, createdAt: now.toISOString(), createdBy: 'crew', released: null,
        pat: { vorname: '', nachname: '', geschlecht: '---', gebdat: '' },
        von: { poi: '', einrichtung: '', strasse: '', hnr: '', plz: '', ort: '', ortsteil: '', sr: '-' },
        ziel: { poi: '', einrichtung: '', strasse: '', hnr: '', plz: '', ort: '', ortsteil: '', sr: '-' },
        einsatz: { datum: fmtDate(now), zeit: fmtTime(now), versorgung: '', einsatzart: '' },
        zeiten: {},
        erst: { verl: {}, psych: [], vitals: {} },
        anam: { text: '' },
        diag: { fuehrend: '', weitere: [], text: '' },
        verlauf: [],
        mass: { aw: {}, zugaenge: [], meds: [], weitere: {}, rettung: [], spezial: [] },
        absch: { besond: [], reaD: {}, weitere: '' },
    };
}

/**
 * Beispiel: internistischer Notfall, Transport ohne NA, kurz vor Übergabe.
 * Absichtlich offen: Zeiten KH an / Ende, Protokollant, Reanimationssituation,
 * Übergabe. So zeigt die Leiste alle drei Zustände.
 */
export function sampleProtocols(vehicle) {
    const now = new Date();
    const at = (min) => new Date(now.getTime() - min * 60000);
    const t = (min) => ({ t: fmtTime(at(min)), d: fmtDate(at(min)) });
    const iso = (min) => at(min).toISOString();

    const a = emptyProtocol('2610201437', 'NF', vehicle);
    Object.assign(a, {
        createdAt: iso(52),
        pat: { vorname: 'Jonas', nachname: 'Brenner', geschlecht: 'männlich', gebdat: '14.03.1958' },
        von: { poi: '', einrichtung: '', strasse: 'Kastanienallee', hnr: '12', plz: '34514', ort: 'Lindenfurt', ortsteil: 'Nord', sr: 'nein' },
        ziel: { poi: 'Klinikum Lindenfurt', einrichtung: 'Klinikum Lindenfurt', strasse: 'Am Klinikpark', hnr: '1', plz: '34512', ort: 'Lindenfurt', ortsteil: 'Mitte', sr: '-' },
        einsatz: { datum: fmtDate(at(55)), zeit: fmtTime(at(55)), versorgung: '1', einsatzart: 'Notfallrettung - Primäreinsatz ohne NA' },
        zeiten: { alarm: t(55), aus: t(54), ean: t(46), patan: t(44), eab: t(18) },
        erst: {
            awz: 'frei', zyanose: 'Nein', atmung: 'Dyspnoe', ausk: 'unauffällig',
            pz: 'stabil', rekap: '< 2 s', puls: 'arrhythmisch', radialis: 'Radialispuls tastbar', blutung: 'nein',
            bwl: 'wach', extr: 'uneingeschränkt', pupL: 'mittel', pupR: 'mittel', lichtL: 'prompt', lichtR: 'prompt',
            gcsA: 'spontan', gcsV: 'orientiert', gcsM: 'folgt Aufforderung',
            verl: { schaedel: { sev: 'Keine' }, ws: { sev: 'Keine' }, thorax: { sev: 'Keine' }, abdomen: { sev: 'Keine' }, oex: { sev: 'Leicht', wund: 'Geschlossen' }, uex: { sev: 'Keine' } },
            nrs: '5', toleranz: 'Tolerabel',
            ekg: 'Absolute Arrhythmie', psych: ['ängstlich'],
            vitals: { spo2: '93', af: '22', etco2: 'ng', hf: '118', rrs: '162', rrd: '94', bz: '', temp: '36,8' },
        },
        anam: {
            text: 'Pat. klagt seit ca. 1 h über Herzrasen und Luftnot bei leichter Belastung. Kein Thoraxschmerz. Ehefrau hat RD alarmiert.\nVORERKRANKUNGEN: arterielle Hypertonie, Vorhofflimmern bekannt\nMEDIKATION: Bisoprolol, Apixaban\nALLERGIEN: keine Allergien',
            symDatum: fmtDate(at(110)), symGeschaetzt: true, symZeit: fmtTime(at(110)), symNicht: false,
            nacaI: 'NACA III - mäßige Störung', nacaU: '', az: 'Vorerkrankungen ohne nennenswerte Einschränkung des tägl. Lebens', ort: 'Wohnung',
        },
        diag: { fuehrend: 'tachykarde Arrhythmie', weitere: ['Dyspnoe unklarer Ursache'], text: 'V. a. Tachyarrhythmia absoluta bei bekanntem VHF' },
        verlauf: [
            { id: 'v1', at: iso(42), values: { spo2: '93', af: '22', hf: '124', rrs: '168', rrd: '96', temp: '36,8' } },
            { id: 'v2', at: iso(30), values: { spo2: '95', af: '20', hf: '112', rrs: '154', rrd: '90', bz: '128' } },
            { id: 'v3', at: iso(16), values: { spo2: '96', af: '18', hf: '104', rrs: '146', rrd: '88', etco2: '37' } },
            { id: 'v4', at: iso(6), values: { spo2: '97', af: '16', hf: '96', rrs: '138', rrd: '84' } },
        ],
        mass: {
            aw: {}, awsich: 'keine', beatmung: 'Spontanatmung', o2: '4 L/min',
            zugangNone: false, zugaenge: [{ id: 'z1', type: 'pvk', site: 'Handrücken', side: 'links', size: '20 G' }],
            medsNone: false,
            meds: [{ id: 'm1', t: fmtTimeS(at(28)), wirkstoff: 'Jonosteril (Vollelektrolyt)', dosis: '500', einheit: 'ml', appl: 'i.v.' }],
            weitere: { waerme: true }, lagerung: 'OK Hochlagerung', rettung: [], spezial: [],
        },
        absch: {
            fzgTransp: vehicle, tf: 'Lena Marquardt', ff: 'Tobias Kranich', prakt: 'Mia Oswald',
            fzgNA: '', na: '', naff: '', naprakt: '', weitere: '',
            protokollant: '', protDurch: 'Transportmittel', besond: ['keine'], nachfNA: 'nein', rea: '', reaD: {},
            uebOrt: '', uebAn: '',
        },
    });

    const b = emptyProtocol('2610201502', 'NA', vehicle);
    Object.assign(b, {
        createdAt: iso(9), createdBy: 'leitstelle',
        von: { poi: 'Sporthalle Am Wehr', einrichtung: 'Sporthalle Am Wehr', strasse: 'Wehrstraße', hnr: '15', plz: '34514', ort: 'Lindenfurt', ortsteil: 'Nord', sr: 'ja' },
        einsatz: { datum: fmtDate(at(9)), zeit: fmtTime(at(9)), versorgung: '', einsatzart: 'Notfallrettung - Primäreinsatz mit NA' },
        zeiten: { alarm: t(9), aus: t(8) },
    });

    const c = emptyProtocol('2610200912', 'NF', vehicle);
    Object.assign(c, {
        createdAt: iso(290),
        released: { by: 'Lena Marquardt', at: iso(225) },
        pat: { vorname: 'Hilde', nachname: 'Sommerkamp', geschlecht: 'weiblich', gebdat: '02.11.1941' },
        von: { poi: 'Seniorenresidenz Lindenhof', einrichtung: 'Seniorenresidenz Lindenhof', strasse: 'Lindenhofstraße', hnr: '22', plz: '34512', ort: 'Lindenfurt', ortsteil: 'Süd', sr: 'nein' },
        ziel: { poi: 'St.-Annen-Krankenhaus Brückheim', einrichtung: 'St.-Annen-Krankenhaus Brückheim', strasse: 'Annenweg', hnr: '8', plz: '34520', ort: 'Brückheim', ortsteil: '', sr: 'nein' },
        einsatz: { datum: fmtDate(at(290)), zeit: fmtTime(at(290)), versorgung: '1', einsatzart: 'Notfallrettung - Primäreinsatz ohne NA' },
        zeiten: { alarm: t(290), aus: t(289), ean: t(281), patan: t(279), eab: t(262), khan: t(248), frei: t(232), wache: t(222), ende: t(226) },
        erst: {
            awz: 'frei', zyanose: 'Nein', atmung: 'unauffällig', ausk: 'unauffällig', pz: 'stabil', rekap: '< 2 s', puls: 'regelmäßig', radialis: 'Radialispuls tastbar', blutung: 'nein',
            bwl: 'wach', extr: 'leicht eingeschränkt', pupL: 'mittel', pupR: 'mittel', lichtL: 'prompt', lichtR: 'prompt', gcsA: 'spontan', gcsV: 'desorientiert', gcsM: 'folgt Aufforderung',
            verl: { schaedel: { sev: 'Leicht', wund: 'Offen' }, ws: { sev: 'Keine' }, thorax: { sev: 'Keine' }, abdomen: { sev: 'Keine' }, oex: { sev: 'Keine' }, uex: { sev: 'Mittel', wund: 'Geschlossen' } },
            nrs: '6', toleranz: 'Nicht tolerabel', ekg: 'Sinusrhythmus', psych: ['verwirrt'],
            vitals: { spo2: '96', af: '16', etco2: 'ng', hf: '88', rrs: '142', rrd: '78', bz: '112', temp: '36,4' },
        },
        anam: { text: 'Sturz im Zimmer, Pat. lag ca. 30 min am Boden. Schmerzen rechte Hüfte.', nacaI: 'NACA III - mäßige Störung', nacaU: 'NACA III - mäßige Störung', az: 'Vorerkrankungen mit nennenswerter Einschränkung des tägl. Lebens', ort: 'Altenheim' },
        diag: { fuehrend: 'Trauma untere Extremitäten – mittel', weitere: ['Trauma Schädel-Hirn – leicht'], text: 'V. a. Schenkelhalsfraktur rechts' },
        mass: { aw: {}, awsich: 'keine', beatmung: 'Spontanatmung', o2: 'keine', zugangNone: false, zugaenge: [{ id: 'z1', type: 'pvk', site: 'Unterarm', side: 'links', size: '20 G' }], medsNone: false, meds: [{ id: 'm1', t: fmtTimeS(at(270)), wirkstoff: 'Fentanyl-Janssen (Fentanyl)', dosis: '50', einheit: 'µg', appl: 'i.v.' }], weitere: { waerme: true }, lagerung: 'Flachlagerung', rettung: ['Vakuummatratze', 'Schaufeltrage'], spezial: [] },
        absch: { fzgTransp: vehicle, tf: 'Lena Marquardt', ff: 'Tobias Kranich', prakt: '', fzgNA: '', na: '', naff: '', naprakt: '', weitere: '', protokollant: 'Lena Marquardt', protDurch: 'Transportmittel', besond: ['keine'], nachfNA: 'nein', rea: 'keine Reanimationssituation', reaD: {}, uebOrt: 'ZNA / INA', uebAn: 'Arzt' },
    });

    return [a, b, c];
}

export const SAMPLE_CREW = {
    vehicle: 'RTW 3/83-1',
    fahrer: { name: 'Lena Marquardt', quali: 'Notfallsanitäter' },
    beifahrer: { name: 'Tobias Kranich', quali: 'Rettungssanitäter' },
    praktikant: { name: 'Mia Oswald', quali: 'Praktikant' },
};
