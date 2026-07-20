/*
 * Diagnosebaum-Daten für die UI-Mockups. Die Labels und Optionen sind 1:1 aus
 * den bestehenden Templates übernommen (plugins/enotf/templates/enotf/protokoll/diagnose).
 * Knoten ohne "options"/"children" sind im Mockup bewusst nicht hinterlegt —
 * inhaltlich ändert sich am Bestand nichts.
 */

const SECTIONS = [
  { label: "Rett. Daten", validation: "green" },
  { label: "Erstbefund", validation: "green" },
  { label: "Anamnese", validation: "yellow" },
  { label: "Diagnose", validation: "red", active: true },
  { label: "Verlauf" },
  { label: "Maßnahmen", validation: "red" },
  { label: "Abschluss" },
];

const DIAGNOSE_ROOT = [
  { label: "Diagnose (führend)", key: "haupt" },
  { label: "Diagnose (weitere)", key: "weitere" },
  { label: "Diagnose Text", key: "text" },
];

const DIAGNOSE_TREE = [
  {
    label: "ZNS",
    options: [
      "Schlaganfall / TIA / ICB",
      "ICB (klin. Diagn.)",
      "SAB (klin. Diagn.)",
      "Krampfanfall",
      "Status Epilepticus",
      "Meningitis / Encephalitis",
      "sonstige Erkrankung ZNS",
    ],
  },
  {
    label: "Herz-Kreislauf",
    options: [
      "ACS / NSTEMI",
      "ACS / STEMI",
      "Kardiogener Schock",
      "tachykarde Arrhythmie",
      "bradykarde Arrhythmie",
      "Schrittmacher-/ICD Fehlfunktion",
      "Lungenembolie",
      "Lungenödem",
      "hypertensiver Notfall",
      "Aortenaneurysma",
      "Hypotonie",
      "Synkope",
      "Thrombose / art. Verschluss",
      "Herz-Kreislauf-Stillstand",
      "Schock unklarer Genese",
      "unklarer Thoraxschmerz",
      "orthostatische Fehlregulation",
      "hypertensive Krise / Entgleisung",
      "sonstige Erkrankung Herz-Kreislauf",
    ],
  },
  { label: "Atemwege" },
  { label: "Abdomen" },
  { label: "Psychiatrie" },
  { label: "Stoffwechsel" },
  { label: "Sonstige" },
  {
    label: "Trauma",
    children: [
      { label: "Schädel-Hirn", options: ["leicht", "mittel", "schwer", "tödlich"] },
      { label: "Gesicht" },
      { label: "HWS" },
      { label: "Thorax" },
      { label: "Abdomen" },
      { label: "BWS / LWS" },
      { label: "Becken" },
      { label: "obere Extremitäten" },
      { label: "untere Extremitäten" },
      { label: "Weichteile" },
      {
        label: "spezielle",
        options: [
          "Verbrennung / Verbrühung",
          "Inhalationstrauma",
          "Elektrounfall",
          "(beinahe-) Ertrinken",
          "Tauchunfall",
          "Verätzung",
          "Sonstige",
        ],
      },
    ],
  },
];

const MOCK_NOTE =
  "Dieser Zweig ist im Mockup nicht hinterlegt. Im echten Umbau bleiben alle " +
  "Inhalte unverändert — die Mockups zeigen nur die Navigation.";

/* Gemeinsame Topbar + Sektionsnav für alle Varianten */
function renderChrome(subtitle) {
  const now = new Date();
  const time = now.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
  const date = now.toLocaleDateString("de-DE");

  document.getElementById("chrome").innerHTML = `
    <div class="topbar">
      <div class="topbar__left">
        <a class="topbar__iconlink topbar__home" href="index.html" title="Zur Übersicht"><span class="icon">▦</span></a>
        <a class="topbar__iconlink" href="#"><span class="icon">⌂</span><small>Anmeldung</small></a>
        <a class="topbar__iconlink" href="#"><span class="icon">⇄</span><small>Art ändern</small></a>
        <a class="topbar__iconlink" href="#"><span class="icon">⤴</span><small>Teilen</small></a>
        <a class="topbar__iconlink" href="#"><span class="icon">≣</span><small>Protokoll</small></a>
      </div>
      <div class="topbar__right">
        <div class="topbar__crew">
          <span>J. Hartmann</span>
          <span>M. Weber</span>
          <small>Anmelden</small>
        </div>
        <div class="topbar__sync" title="Leitstelle / Session / Pat.-Sync">
          <span class="ok">⇅ sync</span>
          <span class="ok">⛓ session</span>
        </div>
        <div class="topbar__clock">
          <span class="time">${time}</span>
          <span class="date">${date}</span>
        </div>
        <div class="topbar__enr">#2024-0815${subtitle ? " · " + subtitle : ""}</div>
      </div>
    </div>`;

  document.getElementById("sectionnav").innerHTML = SECTIONS.map(
    (s) =>
      `<a href="#" class="${s.active ? "active" : ""} ${s.validation ? "val-" + s.validation : ""}"><span>${s.label}</span></a>`
  ).join("");
}

/* Autosave-Toast — deutet die Feld-für-Feld-Speicherung aus Konzept C an */
let toastTimer = null;
function showSaveToast(fieldLabel) {
  const toast = document.getElementById("save-toast");
  const time = new Date().toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
  toast.innerHTML = `Gespeichert: <strong>${fieldLabel}</strong> <span class="muted">· ${time}</span>`;
  toast.classList.add("visible");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("visible"), 2200);
}
