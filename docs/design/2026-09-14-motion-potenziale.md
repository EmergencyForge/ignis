# Motion-Potenziale für ignis, Lex und WebPackages

Stand: 14.09.2026. Analyse der aktuellen lokalen Quellen nach dem UI-Rollout 0.2.0. Dies sind Vorschläge, keine Änderungen am Produkt. Die interaktive Begleitseite zeigt vereinfachte Motion-Studien mit fiktiven Daten, keine Aufnahmen der Anwendungen.

Die stärksten Kandidaten sind **Vorschauwechsel, Suchergebnisse, mitwandernde Tab-Markierungen und Mehrfachauswahl**. Sie betreffen häufig genutzte Oberflächen und lassen sich im gemeinsamen UI-Paket umsetzen. Vor zusätzlichen Effekten lohnt sich die Abstimmung der bereits vorhandenen Drawer-Animation mit ihrem Lebenszyklus.

## Priorisierte Fundstellen

### M01 · Vorschau öffnet sich räumlich und wechselt ruhig

**Priorität: zuerst · Wirkung: sehr hoch · Aufwand: mittel · beide Produkte**

- **Heute:** `showPreview()` blendet die Vorschau unmittelbar ein, ersetzt ihren Inhalt zunächst durch Skeletons und anschließend durch HTML. Öffnen und Breitenwechsel ändern die Grid-Spalten direkt. Bei schnellem Wechsel per Pfeiltaste wird derselbe Ablauf erneut gestartet.
- **Konkrete Orte:** ignis Fahrzeugverwaltung; Lex Personen- und Fahrzeuglisten.
- **Vorschlag:** Beim ersten Öffnen wächst der reservierte Vorschauplatz in 220 ms von 0 auf die Zielbreite; Inhalt erscheint mit 6 px Bewegung und kurzem Fade. Beim Wechsel des Datensatzes bleibt der Rahmen stehen, nur der Inhalt blendet in 120 ms um. Skeletons erst bei tatsächlich wahrnehmbarer Wartezeit zeigen, ohne schnelle Antworten künstlich aufzuhalten. Die Kennung des angeforderten Objekts sofort anzeigen; veraltete Inhalte während des Ladens nicht bedienbar lassen.
- **Technik:** Identische Grid-Track-Struktur im offenen und geschlossenen Zustand vorbereiten; ein einfacher Wechsel von einer auf zwei Spalten genügt für Interpolation nicht. Lange Tabellen auf Reflow-Kosten prüfen. Auf kleinen Ansichten den bestehenden Drawer verwenden. AbortController und Schutz vor veralteten Antworten beibehalten.
- **Fundstellen:** [workbench.js:62](<F:/Github Projects/WebPackages/packages/ui/js/src/workbench.js:62>), [workbench.js:110](<F:/Github Projects/WebPackages/packages/ui/js/src/workbench.js:110>), [_system.scss:203](<F:/Github Projects/WebPackages/packages/ui/scss/_system.scss:203>), [ignis Fahrzeuge:90](<F:/Github Projects/intraRP/templates/settings/vehicles/vehicles/index.php:90>), [Lex Personen:74](<F:/Github Projects/Lex/templates/persons/index.php:74>), [Lex Fahrzeuge:107](<F:/Github Projects/Lex/templates/vehicles/index.php:107>).

### M02 · Globale Suche ohne Leerblitzen

**Priorität: zuerst · Wirkung: sehr hoch · Aufwand: mittel · beide Produkte**

- **Heute:** `schedule()` ruft schon vor dem 160-ms-Debounce `render([], 'Suche läuft …')` auf. `render()` leert die Ergebnisliste mit `replaceChildren()`. Vorhandene Treffer verschwinden bei jeder weiteren Eingabe sofort.
- **Vorschlag:** Ergebnisfläche während einer neuen Anfrage in ihrer Höhe stabil halten. Alte Treffer als nicht mehr aktuell kennzeichnen und ihre Aktivierung sperren; bei Antwort den Trefferblock über 120 ms überblenden. Nur neue Gruppen bei einem deutlichen Scope-Wechsel leicht einblenden. Die aktive Tastaturauswahl bleibt unmittelbar sichtbar, ohne verzögertes Nachgleiten.
- **Nutzen:** Die Suche fühlt sich zusammenhängend an und verliert weniger visuelle Ruhe beim Tippen. Der größte Gewinn kommt aus dem stabilen Ladezustand.
- **Technik:** Sequenzprüfung und Abbruch bleiben maßgeblich. Fehler behalten Text und Wiederholen-Aktion; leere Ergebnisse behalten ihren eindeutigen Leerzustand; keine alte Ergebnisliste als aktuellen Erfolg darstellen.
- **Fundstellen:** [command-palette.js:52](<F:/Github Projects/WebPackages/packages/ui/js/src/command-palette.js:52>), [command-palette.js:79](<F:/Github Projects/WebPackages/packages/ui/js/src/command-palette.js:79>), [command-palette.js:98](<F:/Github Projects/WebPackages/packages/ui/js/src/command-palette.js:98>).

### M03 · Eine Aktivmarkierung wandert zwischen Tabs

**Priorität: zuerst · Wirkung: hoch · Aufwand: klein bis mittel · beide Produkte**

- **Heute:** Tab-Inhalte besitzen bereits einen 180-ms-Fade mit 4 px Aufwärtsbewegung. Die Unterkante bzw. aktive Segmentfläche wird dagegen pro Button umgeschaltet.
- **Konkrete Orte:** besonders Lex Personenakte und Fahrzeugakte; gemeinsame Tabs, Segmented Controls und Such-Scopes.
- **Vorschlag:** Eine gemeinsame Unterlinie gleitet in 160 ms zum neuen Tab und übernimmt dessen Breite. Bei Segmenten wandert die neutrale Hintergrundfläche. Den vorhandenen Inhalts-Fade fein abstimmen; keine zweite Eintrittsanimation stapeln. Die Höhe des Inhalts nur bei überschaubaren Panels ausgleichen.
- **Technik:** Indikator im gemeinsamen Header positionieren; nach Resize, Font-Load und horizontalem Scrollen neu vermessen. Schnelle Eingaben vom aktuell sichtbaren Zwischenstand fortsetzen. ARIA und Fokus unmittelbar aktualisieren.
- **Fundstellen:** [tabs.js:29](<F:/Github Projects/WebPackages/packages/ui/js/src/tabs.js:29>), [_system.scss:188](<F:/Github Projects/WebPackages/packages/ui/scss/_system.scss:188>), [ignis Tab-Fade:1853](<F:/Github Projects/intraRP/assets/css/ui.scss:1853>), [Lex Tab-Fade:2174](<F:/Github Projects/Lex/assets/css/lex.scss:2174>), [Lex Personenakte:130](<F:/Github Projects/Lex/templates/persons/show.php:130>), [Lex Fahrzeugakte:121](<F:/Github Projects/Lex/templates/vehicles/show.php:121>).

### M04 · Mehrfachauswahl wird zu einer klaren Aktionsebene

**Priorität: zuerst · Wirkung: hoch · Aufwand: klein bis mittel · beide Produkte**

- **Heute:** `updateBulk()` schaltet `bulkbar.hidden` unmittelbar um und setzt den Zähler als Text. Die Leiste belegt eine komplette Grid-Zeile und verändert beim Einblenden den Platzbedarf.
- **Vorschlag:** Erste Auswahl blendet die Aktionsleiste in 160 ms mit 6 px Bewegung ein. Dafür einen stabilen Platz vorsehen oder den vorhandenen Platz kontrolliert öffnen. Weitere Auswahlen ändern nur Zähler und Zeilenfarbe. Beim Leeren verschwindet die Leiste in 120 ms.
- **Nutzen:** Der Zusammenhang zwischen Checkbox und verfügbaren Sammelaktionen wird sofort verständlich; die Tabelle springt weniger.
- **Technik:** Keine Leiste über fokussierbare Inhalte legen. Beim letzten Abwählen Fokus nicht mit einem entfernten Button verlieren; Checkbox-Auswahl und Zähler ohne Animationswartezeit aktualisieren. Bestehende Bestätigungen bleiben erhalten.
- **Fundstellen:** [workbench.js:186](<F:/Github Projects/WebPackages/packages/ui/js/src/workbench.js:186>), [ignis Workbench-Layout:619](<F:/Github Projects/intraRP/assets/css/_components.scss:619>), [Lex Personenliste:74](<F:/Github Projects/Lex/templates/persons/index.php:74>).

### M05 · Drawer bis zum letzten Frame sauber schließen

**Priorität: zuerst · Wirkung: hoch · Aufwand: klein bis mittel · beide Produkte**

- **Heute:** Drawer bewegen sich bereits über 300 ms; das Backdrop über 250 ms. `drawer.js` entfernt das Backdrop nach 200 ms. Formular- und Vorschauadapter zerstören den Drawer ebenfalls nach 200 ms. Beim mobilen Vorschau-Schließen wird der Inhalt schon beim Close-Event zurück in die Liste versetzt. Damit endet die Lebensdauer früher als die visuelle Bewegung.
- **Vorschlag:** Sofort logisch schließen und Interaktion deaktivieren; die sichtbare Hülle und ihr Inhalt bleiben bis zum tatsächlichen Ende der Ausblendung bestehen. Separates Ereignis für „vollständig geschlossen“ einführen. Beim Formularladen Header und Footer stabil halten, den Körper in 120 ms einblenden.
- **Technik:** Abschluss über Animation-Fertigmeldung bzw. gefiltertes `transitionend`, zusätzlich begrenzter Fallback. Reduzierte Bewegung beendet sofort. Wiederöffnen während des Schließens muss alte Cleanup-Aufträge entwerten. Der vorhandene Dirty-Guard bleibt vor dem Schließen.
- **Fundstellen:** [drawer.js:10](<F:/Github Projects/WebPackages/packages/ui/js/src/drawer.js:10>), [drawer-form.js:64](<F:/Github Projects/WebPackages/packages/ui/js/src/drawer-form.js:64>), [workbench.js:71](<F:/Github Projects/WebPackages/packages/ui/js/src/workbench.js:71>), [ignis Drawer-CSS:3415](<F:/Github Projects/intraRP/assets/css/ui.scss:3415>), [Lex Drawer-CSS:3810](<F:/Github Projects/Lex/assets/css/lex.scss:3810>).

### M06 · Kalenderansichten mit Richtung und ruhigem Rahmen

**Priorität: danach · Wirkung: hoch · Aufwand: mittel, Monatsnavigation Lex größer · beide Produkte**

- **Heute:** ignis verwendet FullCalendar; Lex rendert den Kalender serverseitig. Lex schaltet Monat/Agenda per `hidden` um, die Monatsnavigation sind normale Links.
- **Vorschlag:** Monat → Agenda über 180 ms überblenden, Toolbar stehen lassen. Vor-/Zurück-Navigation bewegt ausschließlich den Kalenderinhalt um etwa 12 px in die passende Richtung. Ereignisse sind sofort lesbar; keine Staffelung jedes Tagesfeldes.
- **Technik:** Gemeinsame Bewegungsregeln, getrennte Adapter. Bei ignis an den Render-Zyklus von FullCalendar anbinden. Bei Lex ist Monat/Agenda lokal möglich; Monatsnavigation braucht eine eigene progressive Navigationstransition oder einen separat geplanten Fragmentablauf. Nicht als identische Implementierung kalkulieren.
- **Fundstellen:** [ignis calendar.js:47](<F:/Github Projects/intraRP/assets/js/pages/calendar.js:47>), [Lex Monatslinks:93](<F:/Github Projects/Lex/templates/calendar/index.php:93>), [Lex Ansichtswechsel:221](<F:/Github Projects/Lex/templates/calendar/index.php:221>).

### M07 · Speichern und Kopieren sichtbar abschließen

**Priorität: danach · Wirkung: mittel bis hoch · Aufwand: klein bis mittel · beide Produkte**

- **Heute:** Die Dokumenteditoren aktualisieren ihren Speicherstatus per Text. Formular-Drawer setzen einen Busy-Status und deaktivieren Submit. Kopieren ersetzt die Beschriftung für 1.800 ms.
- **Vorschlag:** Stabile Icon- und Textplätze für „Geändert → Speichern → Gespeichert“. Nach bestätigtem Erfolg wechselt das Icon über 120 ms zum Häkchen; manuelles Speichern darf einmal etwas stärker bestätigen. Autosave bleibt im ruhigen Statusbereich. Beim Kopieren dieselbe kurze Bestätigung ohne Breitenwechsel des Buttons.
- **Technik:** Gemeinsamer visueller Statusbaustein in UI; Autosave, CSRF und Redirects bleiben in Produktadaptern. Keine zusätzliche Pause vor Navigation und kein Erfolg bei 422/Netzfehler. Spinner nur während einer echten Anfrage; bei reduzierter Bewegung statisches Statusicon und Text.
- **Fundstellen:** [ignis Editor:176](<F:/Github Projects/intraRP/assets/js/pages/document-editor.js:176>), [Lex Editor:183](<F:/Github Projects/Lex/assets/js/pages/document-editor.js:183>), [drawer-form.js:96](<F:/Github Projects/WebPackages/packages/ui/js/src/drawer-form.js:96>), [copy.js:12](<F:/Github Projects/WebPackages/packages/ui/js/src/copy.js:12>).

### M08 · Mobile Zeilendetails öffnen sich am Datensatz

**Priorität: danach · Wirkung: hoch auf Mobile · Aufwand: mittel · beide Produkte**

- **Heute:** Alle zusätzlichen Originalzellen wechseln gemeinsam per `hidden`; der Zeileninhalt und nachfolgende Datensätze springen in ihre neue Position.
- **Vorschlag:** Nach „Details“ öffnet sich der zusätzliche Bereich über 180 ms, ein Chevron dreht sich mit. Die primäre Kennung bleibt als visueller Anker stehen; „Weniger Details“ schließt in 140 ms.
- **Technik:** Originalzellen, Links und Formularfelder erhalten. Animation im mobilen Kartenlayout anbringen; kein generisches `max-height: 1000px` und kein ungültiger Wrapper zwischen `tr` und `td`. Nachfolgende Zeilen können per gemessener Positionsänderung ruhig nachrücken.
- **Fundstellen:** [list-details.js:10](<F:/Github Projects/WebPackages/packages/ui/js/src/list-details.js:10>), [Lex mobile Personenliste:87](<F:/Github Projects/Lex/templates/persons/index.php:87>).

### M09 · Auswahl-Chips wachsen ein und schließen ihre Lücke

**Priorität: danach · Wirkung: mittel · Aufwand: mittel · gemeinsames UI**

- **Heute:** `_renderChips()` löscht die gesamte Chip-Liste und erstellt alle ausgewählten Chips neu.
- **Vorschlag:** Nur der neue Chip erscheint über 140 ms mit leichtem Fade. Beim Entfernen schließt sich seine Lücke in 160 ms; vorhandene Chips rücken weich nach. Auch ein Zeilenumbruch soll räumlich nachvollziehbar bleiben.
- **Technik:** Chips nach ihrem Wert wiederverwenden. Positionsdifferenzen mit FLIP abbilden, Text nicht breit skalieren. Fokus nach Entfernen auf nächsten vorhandenen Chip oder Eingabefeld setzen. Kein erneutes Animieren sämtlicher Chips bei jeder Auswahl.
- **Fundstelle:** [multi-select.js:159](<F:/Github Projects/WebPackages/packages/ui/js/src/multi-select.js:159>).

### M10 · Lex-Vorgangsindex zeigt, wohin man gewechselt ist

**Priorität: danach · Wirkung: mittel · Aufwand: klein bis mittel · Lex**

- **Heute:** Der Abschnittsindex markiert Klicks und vom IntersectionObserver gemeldete Abschnitte per `aria-current`.
- **Vorschlag:** Dezente Markierung gleitet beim Wechsel mit. Bei einem bewussten Sprung erhält die Zielüberschrift einen kurzen, auslaufenden Hintergrundakzent. So ist etwa „Beweismittel“ unmittelbar wiederzufinden. Während normalen Scrollens keine wiederholten Aufmerksamkeitsimpulse.
- **Technik:** Direkte Hash-Links, Browser-Historie und Reload-Ziele erhalten. Weiches Scrollen nur bei erlaubter Bewegung; lange Sprünge nicht mit einer langen Kamerafahrt inszenieren. Aktivmarkierung darf bei Observer-Ereignissen nicht hektisch zwischen Abschnitten pendeln.
- **Fundstellen:** [Abschnittsindex:100](<F:/Github Projects/Lex/templates/cases/show.php:100>), [Index-Verhalten:247](<F:/Github Projects/Lex/templates/cases/show.php:247>).

### M11 · Benachrichtigungen rücken als Stapel ruhig nach

**Priorität: später · Wirkung: mittel · Aufwand: klein bis mittel · beide Produkte**

- **Heute:** Einzelne Snackbars gleiten bereits ein und aus. Nach dem Ausblenden wird ein Element aus dem Stapel entfernt; das verbleibende Layout wird nicht eigens animiert.
- **Vorschlag:** Den bestehenden Eintritt beibehalten. Wenn eine Meldung verschwindet, rücken nur die übrigen Meldungen über 160 ms an ihre neue Position. Den bereits vorhandenen Wechsel Fortschritt → abgeschlossen im selben Element beibehalten.
- **Technik:** Positionsanimation auf einem Wrapper ausführen, damit sie den vorhandenen Transform der Snackbar nicht überschreibt. Hover-/Fokusbedienung und Aktionstasten erhalten. Statusansagen nicht bei jeder visuellen Zwischenstufe wiederholen.
- **Fundstellen:** [snackbar.js:152](<F:/Github Projects/WebPackages/packages/ui/js/src/snackbar.js:152>), [snackbar.js:184](<F:/Github Projects/WebPackages/packages/ui/js/src/snackbar.js:184>).

### M12 · Liste → Detail als zusammenhängender Ortswechsel

**Priorität: gezielter Pilot · Wirkung: sehr hoch möglich · Aufwand: größer · beide Produkte**

- **Heute:** Die volle Detailansicht wird aus der Workbench über normale Navigation geöffnet. Shell und neuer Seiteninhalt werden neu dargestellt.
- **Vorschlag:** Sidebar und Topbar visuell ruhig halten; nur die Inhaltsfläche blendet kurz um. In einem engeren Pilot kann die gewählte Kennung aus der Liste in den Objektkopf übergehen. Keine Seitenschieberei bei jeder Navigation.
- **Technik:** Progressive Cross-Document View Transitions für passende gleichursprüngliche Seiten prüfen, mit gewöhnlicher Navigation als Fallback. Beide Dokumente müssen teilnehmen; unterschiedliche Browser-/CEF-Versionen gezielt prüfen. Ein eindeutiger gemeinsamer Elementname muss auf Quell- und Zielseite passen. Einstieg an Lex Personenliste → Personenakte, danach ignis Fahrzeuge. Auth, Druck und eNOTF ausnehmen.
- **Fundstellen:** [workbench.js:127](<F:/Github Projects/WebPackages/packages/ui/js/src/workbench.js:127>), [Lex Personenliste:74](<F:/Github Projects/Lex/templates/persons/index.php:74>), [Lex Personenakte:130](<F:/Github Projects/Lex/templates/persons/show.php:130>).

## Gemeinsamer Rahmen

**Bewegungscharakter:** direkt, präzise und ruhig. Als vorläufige Startwerte: 120 ms für Feedback/Inhaltswechsel, 160 ms für Indikatoren und kleine Elemente, 220 ms für räumliche Panels. Die bereits vorhandene Kurve `--ease-out-quint` verwenden, räumliche Ankunft bei Bedarf `--ease-out-expo`. Kein zusätzlicher Delay, außer einem bewusst verzögerten Ladeindikator; die Antwort selbst wird niemals verzögert. Diese Werte sind Vorschläge zum visuellen Abstimmen.

**Zuständigkeit:** Wiederverwendbare Motion-Tokens und CSS-Rezepte in `WebPackages/packages/ui/scss/_system.scss`; kleine abbrechbare Helfer in einem neuen `packages/ui/js/src/motion.js`. Ignis und Lex liefern Objektidentität, Render-Ereignisse und Serverzustände. Das Editor-Paket bleibt für Dokumentstruktur zuständig. Die Kernoberfläche bleibt auf `body[data-ui-skin="core"]` begrenzt; eNOTF hat eigene Abläufe.

**Technik:** Für diese UI-Bewegungen zunächst CSS und die native Web Animations API verwenden. Eine zusätzliche Animationsbibliothek ist aus den identifizierten Anforderungen nicht nötig. [MDN: Element.animate()](https://developer.mozilla.org/en-US/docs/Web/API/Element/animate) beschreibt das native API; [MDN: View Transition API](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API) beschreibt lokale und dokumentübergreifende Ansichtswechsel.

**Reduzierte Bewegung:** Beide Produktstyles besitzen bereits eine globale CSS-Regel. Neue JS-Animationen müssen die Präferenz ebenfalls beachten und bei einem Wechsel laufende Bewegungen sauber beenden. Alle Vorschläge erhalten ohne Translation und ohne Skalierung denselben sofort sichtbaren Endzustand; Status bleibt lesbarer Text. [MDN: prefers-reduced-motion](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion).

| Zustand | Gemeinsame Regel |
|---|---|
| Normal | Inhalt ist sofort lesbar; keine dauerhafte Bewegung. |
| Hover | Vorhandene Farb-/Flächenrückmeldung erhalten, keine Bewegung statischer Karten. |
| Fokus | Sofortiger stabiler Fokusrahmen; Fokus darf nie auf eine Animation warten. |
| Aktiv | Fachlicher Zustand und ARIA sofort aktualisieren, dekorativer Indikator darf folgen. |
| Deaktiviert | Keine Erfolgsanimation; bestehende Sperre erkennbar lassen. |
| Laden | Echte Anfrage kennzeichnen, Geometrie möglichst stabil halten. |
| Fehler | Text, Wiederholen oder Feldhinweis; kein Wackeln und kein Erfolgszeichen. |
| Leer | Eindeutiger Leerzustand mit nächster Handlung; keine Endlosskeletons. |

**Prüfung bei Umsetzung:** schnelle Wiederholung, Richtungswechsel mitten in der Animation, Tastatur, kleine Ansichten, reduzierte Bewegung, langsame/fehlgeschlagene Antwort und große Listen. Für Drawer insbesondere Close-Guard, Fokus-Rückkehr und verspätetes Cleanup. HTML-Studien ersetzen keine Integrationstests oder Performance-Messung im Produkt.

**Auswahlvorschlag:** M05 als kleine Grundlage, dann M01–M04. Zweite Runde M06–M10. M11 bei Gelegenheit; M12 als eigener Pilot. Keine Umsetzungsfrist vereinbart.

## Bereits vorhanden / bewusst nachrangig

Sidebar-Breitenwechsel, Burger, Dialog-Eintritt, Drawer-Slide, Tab-Fade, Accordions, Dropdowns und einzelne Snackbars haben bereits Motion. Die dezent vereinbarte Sidebar-Aktivmarkierung bleibt dezent. Zusätzliche Dashboard-Stagger, Count-up-Effekte, pulsierende Statusbadges und großflächige Theme-Wipes würden für diese Arbeitsoberflächen weniger Nutzen bringen als die Übergänge oben.
