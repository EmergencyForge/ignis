# Changelog

## Unveröffentlicht

Texte in der Oberfläche, Meldungen und Seitentitel kommen ohne Gedankenstriche aus. Leere Werte zeigen „-“ oder einen kurzen Hinweis wie „keine Angabe“, Bereiche stehen als „1 bis 25“.

## 2026.0.19-beta

Ein freigegebenes eNOTF-Protokoll wird wieder gesperrt angezeigt. Seit April brach das Sperr-Skript an einem leeren Selektor ab, Felder, Auswahllisten und Ankreuzfelder blieben bedienbar. Gespeichert wurde trotzdem nichts, der Server lehnt Änderungen an freigegebenen Protokollen ab.

## 2026.0.18-beta

Die eNOTF-Messwerte zeigen wieder große Kacheln. Seit einer Umstellung im April waren SpO₂, Puls, Blutdruck und die übrigen Werte auf normale Feldhöhe geschrumpft, Beschriftung und Einheit lagen über dem Wert, und leere Pflichtfelder waren nicht mehr rot markiert. Das galt auch für „Verlauf“ beim Hinzufügen von Werten. In den Protokollboxen, auf der eNOTF-Anmeldung und beim Anlegen haben Textfelder wieder ihre größere Schrift und flache Fläche, schreibgeschützte Felder freigegebener Protokolle lassen sich nicht mehr anklicken, und der Teilen-Dialog hat wieder Tablet-Größe. Den Seiten für Klinikcodes und Bettenverfügbarkeit fehlten Schrift, Icons und jQuery, sie laden wieder vollständig. eNOTF v2 sieht unverändert aus.

Die Fehlerseiten 404 und 403 sind neu: eine große Ziffernkontur, über die ein warmer Schein wandert, darunter die aufgerufene Adresse und der Weg zurück. „Vorherige Seite“ erscheint nur, wenn es im Tab eine vorherige Seite gibt. Ist ein Formular abgelaufen, heißt die Seite „Formular abgelaufen“.

Aktualisiert ist das UI-Paket auf 0.8.1.

## 2026.0.17-beta

Die eNOTF-Prüfliste ist neu aufgebaut wie die übrigen Listen. Sie lädt nicht mehr alle Protokolle auf einmal, sondern blättert auf dem Server, sucht über Einsatznummer, Patient und Protokollant und sortiert nach jeder Spalte. Die Filter „Alle“, „Unbearbeitet“ und „Nicht freigegeben“ zeigen weiter ihre Anzahl, der Link von der Dashboard-Kachel führt wie bisher auf die offenen Protokolle. Nach dem Löschen eines Protokolls bleibt die Liste bei derselben Suche, Sortierung und Seite. Protokolle aus dem Verbund stehen wie bisher nur lesend dabei.

Im QM-Dialog der Prüfliste ließ sich nichts speichern, der Dialog meldete jedes Mal einen Fehler. Status und Kommentar werden jetzt gespeichert. Speichern darf nur, wer Protokolle bearbeiten darf.

Kleine Eingabefelder sind wirklich klein: in Tabellenzeilen und neben kleinen Knöpfen 32 px hoch, auf Touch-Geräten 44 px. In normalen Formularen und Filterleisten haben Felder und Knöpfe dieselbe Höhe.

Auswahl-, Datums- und Zeitfelder öffnen überall die Listen und Kalender von ignis statt der Steuerelemente des Browsers, auch in den Plugins MANV, Mail, Wissensdatenbank, fireTab und in den eNOTF-Einstellungen. Zeitfelder nehmen Eingaben wie „800“ oder „8:5“ an und haben eine Auswahl nach Stunden und Minuten im 24-Stunden-Format. Die Hauptfarbe in der System-Konfiguration und die Farbe von Schlagwörtern in der Wissensdatenbank wählt man mit dem Farbwähler von ignis. In der Sichtung des MANV-Boards tragen SK1 bis SK5 ihren Farbpunkt. Im Profil speichern Geschlecht und Geburtsdatum, sobald ein Wert gewählt ist. Die eNOTF-Protokollseiten und die fireTab-App auf dem Tablet behalten ihre Felder, außer im Fahrtenbuch: Dort wählt man die Uhrzeit jetzt ebenfalls mit der Auswahl von ignis.

Vor jedem Dienstgrad steht sein Abzeichen, sofern eines hinterlegt ist: im Profil, in der Personalliste, in der Auswahl beim Anlegen und Filtern, in den Dienstgrad-Einstellungen und in den Regeln der Mail-Verteiler. Abzeichen laden jetzt auch, wenn ignis unter einem Unterpfad läuft.

Die Anmeldeseite zeigt rechts das ignis-Zeichen als leuchtende Kontur über aufsteigender Glut, darunter Organisation und Stadt. Die nachgebaute Vorschau der Anwendung entfällt. Wer im System weniger Bewegung eingestellt hat, sieht ein ruhiges Bild. Eine eigene Hauptfarbe aus der System-Konfiguration färbt jetzt auch die Anmeldeseite.

Die fireTab-Einsatzansicht und die QM-Liste brachen ab, wenn der Einsatzleiter keinen Namen hatte. Anträge übernahmen bei Männern die weibliche Dienstgradbezeichnung und umgekehrt. Die Farbe eines Schlagworts in der Wissensdatenbank wird beim Speichern geprüft, vorher ließ sich darüber CSS in die Seite schreiben. Das Profilprotokoll schreibt „Dienstgrad“ statt „Rank“.

Aktualisiert ist das UI-Paket auf 0.8.0.

## 2026.0.16-beta

Das Dashboard schnitt seinen Inhalt ab: Die helle Inhaltsfläche endete nach einer Fensterhöhe, und die Kacheln darunter liefen über ihren Rand hinaus. Eine alte Regel aus der ersten Version machte die Seite genau ein Fenster hoch. Jetzt wächst die Fläche mit dem Inhalt, der Footer steht wie auf allen Seiten unten in ihr.

Kacheln, Links in der Seitenleiste, Tabellenköpfe und das Logo wurden beim Überfahren unterstrichen. Unterstrichen werden jetzt nur noch Textlinks.

Kopfzeile, Footer und Anmeldung zeigen das ignis-Zeichen neben dem Schriftzug. Das Zeichen trägt die Hauptfarbe, der Schriftzug steht in der Textfarbe. Auf sehr schmalen Handys bleibt in der Kopfzeile nur das Zeichen. Ein eigenes Logo aus der System-Konfiguration bleibt, wie es ist.

Alle Verwaltungsseiten wurden am Rechner und am Handy in beiden Themes durchgesehen. Im hellen Theme waren die Überschriften im Ankündigungsfenster weiß auf weiß, sie sind wieder lesbar. Die eNOTF-Einstellungen und die Prüfliste stehen jetzt in derselben Spalte wie die übrigen Seiten, der Footer schließt bündig an. Auf den MANV-Seiten, in Profil, Kalender, Fahrtenbuch, Beladelisten, Systemprotokoll, Cronjobs, Telemetrie und Plugins sitzen Abstände, Knöpfe und Kopfzeilen wieder richtig, und am Handy läuft nichts mehr aus dem Bild. Native Auswahlfelder haben denselben Pfeil wie die übrigen Dropdowns, Mehrfachauswahl und Datumsfelder sind so hoch wie die anderen Felder.

Aktualisiert sind twig 3.30.0, monolog 3.12.1, league/oauth2-client 2.9.1 und phpstan 2.2.16, dazu das UI-Paket 0.7.2.

## 2026.0.15-beta

ignis sieht neu aus. Mit dem UI-Paket 0.7.1 wird das Grau kühler, die Ecken werden runder, und Farbe haben nur noch Daten, Status und die eine Hauptaktion einer Seite. Seitenleiste, Kopfzeile, Reiter und Filter sind grau, der aktive Eintrag in der Seitenleiste, aktive Reiter und der gewählte Mail-Ordner sind deshalb nicht mehr orange. Primärknöpfe sind orange mit dunkler Schrift, und jede Seite hat höchstens einen davon. Seitenleiste und Kopfzeile liegen ohne eigene Fläche auf einem dunkleren Grund und gehen ineinander über. Darauf liegt der Seiteninhalt als hellere Fläche mit runden Ecken und leichtem Schatten, rechts und unten bleibt ein schmaler Rand frei. Auf dem Handy reicht der Inhalt bis an den Bildschirmrand. Die Grundschrift ist 14 px groß. Knöpfe und Eingabefelder sind in der Dichte „Komfortabel“ 40 px hoch, in „Kompakt“ 36 px und auf Touch-Geräten immer 44 px.

Das Dashboard ist jetzt eine Übersicht über den Dienst. Oben stehen Kacheln für die Einsätze von heute, die einsatzbereiten Fahrzeuge und die eNOTF-Protokolle, die noch nicht freigegeben sind; ist eines offen, wird die Kachel rot, und ein Klick öffnet die Prüfliste mit genau diesen Protokollen. Darunter zeigen kleine Kurven die Einsätze je Stunde und je Tag, und die Fahrzeuge stehen mit ihrem Status in einer Liste. Rechts sammeln sich Hinweise, etwa zu offenen Protokollen oder zu Fahrzeugen in Status 6. Diagramme sind cyan, sandfarben und grau, Orange kommt darin nicht vor, damit nichts davon wie ein Alarm wirkt. Was jemand ohne die passenden Rechte nicht sehen darf, fehlt auf dem Dashboard.

Die Statusfilter in den Listen (Benutzer, Anträge, Fahrzeuge, Mängel, Posteingang, eNOTF-Prüfliste und fireTab-Verwaltung) zeigen, wie viele Einträge hinter jedem Filter stehen, die eNOTF-Prüfliste hat dazu den Filter „Nicht freigegeben“. Das MANV-Board ordnet die Patienten in Spalten nach Sichtungskategorie, jede Spalte in der Farbe ihrer Kategorie, und auch die Knöpfe der Schnell-Sichtung tragen die Farbe ihrer Kategorie. Fahrzeugtypen und die Arten der Beladelisten stehen in grauen Chips, Feuerwehrfahrzeuge sind also nicht mehr rot markiert. Die Suche in der Kopfzeile zeigt ihr Kürzel Strg K, die Glocke trägt einen Punkt, solange etwas ungelesen ist, und eine neue Meldung lässt einmal einen Lichtstreifen über sie laufen. Auf der Anmeldeseite steht das Formular in einem eigenen Rahmen.

Die eNOTF-Protokollseiten sehen absichtlich genauso aus wie vorher, ebenso die fireTab-App auf dem Tablet. Die eNOTF-Verwaltung mit Prüfliste, POIs, Medikamenten und Schnellzugriff hat dagegen den neuen Look, und ihre Statusfilter sind dieselben wie in den übrigen Listen.

Auf dem Handy laufen Listen nicht mehr über den rechten Rand, die Tabelle scrollt in ihrer Karte, und unter den Seitentiteln ist die große Lücke verschwunden. Im dreispaltigen Mail-Postfach hat der Betreff mehr Platz.

Eine eigene Hauptfarbe aus der System-Konfiguration (`SYSTEM_COLOR`) kam bisher nicht an, ignis blieb orange. Jetzt färbt sie im hellen wie im dunklen Modus Primärknöpfe, Fortschrittsbalken und den Fokusrahmen, die Schrift auf dem Knopf wählt ignis so, dass sie lesbar bleibt. Die eNOTF-Protokollseiten und die fireTab-App bleiben orange. Die System-Konfiguration warnt, wenn die Hauptfarbe so rot ist, dass Knöpfe und Fortschrittsbalken wie Warnungen aussehen.

Der Kalender öffnete sich seit der Umstellung auf das UI-Paket nicht mehr, die Seite blieb leer. Er lädt wieder. Im Posteingang landeten Benachrichtigungen zwischen Mitternacht und zwei Uhr unter „Gestern“, und die angezeigten Uhrzeiten lagen zwei Stunden daneben, wenn die Datenbank in UTC läuft. Beides richtet sich jetzt nach der Ortszeit.

Plugins mit eigenen Styles finden die Änderungen an Farben, Ecken und Bausteinen in der README des UI-Pakets im Abschnitt „Funke (0.7.0)“ unter „Beim Umstieg“.

## 2026.0.14-beta

Weitere Stellen, an denen ein präparierter Link oder ein eingebettetes Bild etwas auslösen konnte, gehen nur noch über ein Formular mit Sicherheitstoken: Benutzerkonten löschen und deaktivieren, eNOTF-Vitalwerte löschen und der EMD-Fahrzeugimport. Die ungenutzte Seite, die Vitalwerte endgültig löschen wollte, ist entfernt. Die eNOTF-Voranmeldung schrieb jedes abgeschickte Formular samt Diagnose und Freitext in eine Logdatei neben dem Template; das entfällt, und das Update löscht die alte Datei. Der Updater lehnt Download-Adressen mit `../` ab, über die sich vorher ein Paket aus einem fremden Repository laden ließ. Cron-Webhooks prüfen ihr Ziel jetzt auch für IPv6 und schicken den Request an genau die geprüfte Adresse, damit eine zwischendurch geänderte DNS-Antwort nicht doch ins interne Netz führt (cron-scheduler 0.1.1). Der Dokumenteditor bringt das Sicherheitsupdate von TipTap 3.31.4 mit (editor 0.3.1).

eNOTF v1 gibt Einsatznummern, Patientendaten, Freitexte und Seitentitel jetzt überall maskiert aus. Bisher ließ sich über einen präparierten Namen oder eine Einsatznummer Skript in Übersicht, Druckansicht, QM-Log oder Arrivalboard einschleusen. Die ENR-Brücke nimmt nur noch Einsatznummern aus Ziffern und Unterstrich mit höchstens 40 Zeichen an, die Voranmeldung prüft Priorität, Datum, Uhrzeit, Alter, GCS und Textlängen und weist ungültige Angaben ab. Aussehen und Ablauf von eNOTF bleiben, wie sie sind.

ignisTab kann sich ohne Discord-OAuth anmelden, das im FiveM-Browser nicht funktioniert: Der FiveM-Server holt mit dem API-Schlüssel einen Einmal-Token für die Discord-ID des Spielers, das Tablet meldet sich damit an. Das gilt nur für bestehende, aktive Benutzer, ein Konto entsteht dabei nie; teilen sich zwei aktive Konten eine Discord-ID, gibt es keinen Token. Eingeschaltet wird es unter Einstellungen › System-Konfiguration › Funktionen mit `TABLET_LOGIN_ENABLED`, ab Werk ist es aus. Die Einzelheiten stehen in der README.

Im Mail-Plugin verschickt ein Postfach höchstens eine Mail alle zehn Sekunden. Der Abstand lässt sich unter Einstellungen › Mail zwischen 0 (aus) und 3600 Sekunden einstellen, Entwürfe speichern bleibt frei. Dynamische Verteiler können jetzt auch nach Fachdiensten auswählen; ein Fachdienst, den niemand hat, trifft niemanden statt alle. Wer auf der Mail-Seite im Drawer sendet oder einen Entwurf verwirft, sieht die Änderung sofort in Liste, Ordnerzählern und Sidebar.

Die Oberfläche kommt aus dem UI-Paket 0.6.0, die alten Klassennamen gibt es dort nicht mehr: `--success`, `--warning` und `--error` bei Hinweisen, Chips und Snackbars, `--accent`, `--soft-*`, `--outline-*`, `--success`, `--info` und `--warning` bei Knöpfen sowie `ignis-filter-links`. ignis selbst nutzt nur noch die neuen Namen (`ok`, `warn`, `danger`, `primary`, `secondary`, `segmented`). Plugins, die die alten Klassen setzen, verlieren dort ihr Aussehen und sollten umstellen. Der Tooltip am eNOTF-Session-Symbol zeigt jetzt den aktuellen Verbindungsstatus statt des Texts beim Laden der Seite, und eine Mehrfachauswahl schließt, sobald der Fokus sie verlässt.

Das Lexikon der Wissensdatenbank öffnet wieder, die Übersicht brach bisher ab, sobald sie einen Eintrag zeigte, die Detailseite immer. Aufträge in der Warteschlange scheiterten im Worker alle mit einem Typfehler; die Discord-Webhooks aus eNOTF und FireTab kommen jetzt an. Cron-Aufgaben vom Typ „Job“ meldeten Erfolg und scheiterten dann ebenfalls im Worker; sie laufen jetzt über denselben Weg.

Der Updater ist in einzelne Bausteine zerlegt (Release-Quelle, Versionsregeln, Archivprüfung, Sicherung, Dateikopie, `version.json`, Diagnose, Composer), das Verhalten beim Update bleibt gleich. Dabei sind einige Fehler aufgefallen und behoben: Ließ sich `version.json` nicht schreiben, meldete das Update trotzdem Erfolg und bot sich danach erneut an. Eine beschädigte `version.json` ergibt eine verständliche Meldung statt eines Typfehlers. Eine Branch-Installation zählte die Build-Nummer doppelt hoch. Die Diagnose suchte auf umgezogenen Installationen in den alten Ordnern und meldete deshalb immer einen Fehler, fand ihre eigenen Berichte nicht und hob sie unbegrenzt auf; jetzt bleiben die zehn neuesten. Ein unbegrenztes `memory_limit` gilt nicht mehr als zu wenig Speicher. Branches und Commits im Entwicklermodus laden wie Releases über cURL und mit `IGNIS_GITHUB_TOKEN`, auch ohne `allow_url_fopen`.

Für Entwickler: Die PHPStan-Baseline ist von rund 1.100 auf 166 Meldungen geschrumpft, vor allem durch typisierte Relationen, Scopes und Array-Typen; am Verhalten ändert das nichts. Die CI testet und baut gegen einen festen WebPackages-Tag aus `.github/webpackages-ref` statt gegen dessen `main`. Sie führt jetzt auch die Feature- und Integrationstests gegen eine Datenbank aus und schlägt fehl, wenn ein Test scheitert; bisher blieb sie grün, obwohl diese Tests gar nicht liefen.

## 2026.0.13-beta

Löschen geht nur noch über einen Knopf mit Rückfrage und Sicherheitstoken, nicht mehr über einen einfachen Link. Das betrifft Mitarbeiter, Kommentare im Profil und eNOTF-Protokolle. Vorher reichte ein präparierter Link oder ein eingebettetes Bild auf einer beliebigen Seite, um als angemeldeter Admin unbemerkt etwas zu löschen. Protokolle ließen sich außerdem ohne Rückfrage löschen.

In den eNOTF-Einstellungen funktionieren „Löschen“ bei Quicklinks und Kategorien wieder; die Knöpfe liefen bisher ins Leere. Die QM-Protokollliste zeigt Patientennamen und Freigeber jetzt sicher an, ein Name mit HTML darin konnte vorher Skript im Admin-Bereich ausführen.

## 2026.0.12-beta

Neu ist das mitgelieferte Plugin „Mail“: ein internes Postfach für jeden Mitarbeiter, das wie ein Mailprogramm funktioniert, aber nichts nach außen schickt. Adressen entstehen aus dem Namen (`m.mueller@ignis.ef` oder `max.mueller@ignis.ef`, einstellbar), das Postfach mit dem Mitarbeiter. Im Archiv-Dienstgrad oder nach dem Löschen wird es stillgelegt, alte Mails bleiben lesbar. Für den Bestand legt `php cli/intra.php mail:backfill` die Postfächer an.

Geschrieben wird im Drawer mit An, CC und BCC samt Vorschlägen aus dem Adressbuch, formatiertem Text mit Links, Anhängen (Bilder, PDF, Text, bis 10 MB je Mail) und Signatur. Entwürfe speichern sich beim Tippen. Die Ordner heißen Posteingang, Gesendet, Entwürfe, Archiv und Papierkorb; ab 1200 px stehen Ordner, Liste und Lesebereich nebeneinander. Neue Mails zeigen die Glocke und ein Zähler am Eintrag „Mail“, die globale Suche findet die eigenen unter „Mails“.

Verteiler gibt es als feste Liste oder als Regel nach Rolle, Dienstgrad oder RD-/FW-Qualifikation; je Verteiler lässt sich festlegen, ob alle oder nur die Verteiler-Verwaltung daran schreiben. Die Postfachverwaltung korrigiert Adressen, sperrt Postfächer und wechselt Domains, sieht aber keine Mails. Neue Rechte: `mail.use`, `mail.lists.manage`, `mail.domain.choose`, `mail.admin`. Die Einstellungen (Domain, Muster, erlaubte Domains, Standard-Signatur) liegen unter Einstellungen › Mail, weil sie dort geprüft werden. Die allgemeine System-Konfiguration zeigt nur noch Werte, die sich dort auch bearbeiten lassen.

Ein Postfach gehört fest einem Benutzerkonto. Passt genau ein Konto zum Mitarbeiter, wird es automatisch zugeordnet, bestehende Postfächer bei der Migration. Ein später geändertes Discord-Tag in der Personalakte verschiebt das Postfach nicht. Umhängen geht nur über „Konto zuordnen“ in der Postfachverwaltung, nie auf das eigene Konto; das bisherige Konto bekommt einen Hinweis, das Audit-Log hält es fest. Anhänge tragen immer die Endung ihres erkannten Typs, doppeltes Senden stellt nicht doppelt zu, und eine Mail ohne erreichbaren Empfänger wird nicht abgeschickt. Antworten zitieren höchstens zehn Ebenen. `mail:backfill` läuft jede Nacht.

Die Anmeldeseite ist aufgeräumter: links nur noch ein Logo, die Begrüßung und die Anmeldung, rechts eine animierte Vorschau der Anwendung. Die Zahlen darin sind erfunden, die Anmeldeseite fragt nichts aus der Datenbank ab. Auf schmalen Bildschirmen entfällt die Vorschau.

## 2026.0.11-beta

Die Bausteine der Oberfläche (Knöpfe, Formulare, Dialoge, Tabellen, Karten, Hinweise und mehr) kommen jetzt aus dem gemeinsamen UI-Paket, das auch Lex nutzt. ignis bringt dadurch rund 5.500 Zeilen eigenes CSS weniger mit. Die Kontraste im hellen Theme übernehmen die Korrekturen aus Lex: Text auf farbigen Flächen bleibt überall gut lesbar.

Knöpfe gibt es jetzt in vier Stufen: Primär, Sekundär, Ghost und Gefahr. Die bisherigen Sonderformen (grün, gelb, umrandet, getönt) erscheinen als Sekundär-Knopf, Erfolg-Knöpfe als Primär. Die kleine Zeile über dem Seitentitel ist gedämpft statt orange in Großbuchstaben. Auf der Anmeldeseite ist die rechte Bildspalte mit Logo und Text wieder zu sehen, auf der Einstellungsübersicht stehen Titel und Beschreibung der Kacheln einheitlich untereinander, und im Suchfeld der Mitarbeiterliste überdeckt die Lupe nicht mehr den Text.

## 2026.0.10-beta

Die globale Suche (Strg K) findet Mitarbeiter, Fahrzeuge, Dokumente, Mängel und Vorlagen jetzt auch bei einem Tippfehler oder vertauschten Umlaut, markiert diese Treffer dezent mit „ähnlich" und lässt Kennungen wie Dienstnummer oder Kennzeichen bewusst unangetastet.

## 2026.0.9-beta

Das System-Logo auf der System-Konfiguration lässt sich jetzt per Drag & Drop oder Dateiauswahl hochladen, statt nur über einen Pfad oder eine URL im Textfeld gepflegt zu werden. Erlaubt sind PNG, JPEG und WebP bis 2 MB; SVG nimmt der Upload bewusst nicht an, weil eine hochgeladene SVG-Datei Skript enthalten könnte. Ein neuer Upload löscht die vorher hochgeladene Datei, „Logo entfernen" setzt wieder auf die ignis-Wortmarke zurück. Das Textfeld für Pfad oder URL bleibt als Alternative hinter einer Ausklapp-Zeile erhalten, bestehende Installationen mit eigenem Pfad laufen unverändert weiter.

## 2026.0.8-beta

Die eNOTF-Verwaltungsseiten (POIs, Medikamente, Schnellzugriff, Kategorien, Prüfliste) hatten seit dem letzten Umbau der Topleiste eine unformatierte Bar, weil ihre Shell den neuen Skin nie gesetzt hat. Die Sidebar hatte sich mit rund zwanzig „Einstellungen“-Zeilen vollgestellt, die man selten braucht; die meisten davon ziehen jetzt auf eine eigene Übersichtsseite mit gruppierten Kacheln um, erreichbar über eine neue „Verwaltung“-Gruppe. Das Profilbild im Mitarbeiter-Profil lässt sich jetzt per Drag & Drop oder Dateiauswahl ändern statt nur über den kleinen Kamera-Knopf.

## 2026.0.7-beta

Läuft ignis unter einem Unterpfad wie `/intra/`, leitet die eNOTF-Sperre nach Inaktivität wieder auf den Sperrbildschirm des Plugins weiter statt auf eine nicht vorhandene Seite. Der Footer steht wieder am unteren Rand und in derselben Spalte wie der Inhalt; sein Logo lädt auch unter einem Unterpfad.

Die Navigationspunkte der Sidebar zeigen ihren Tooltip nur noch eingeklappt; die Schnellaktionen (+) haben einen eigenen Tooltip statt des Browser-Titels. Die eingeklappte Leiste ist etwas breiter und ohne Scrollbalken. Mit dem UI-Paket 0.4.1 hat das Suchfeld der Befehlspalette keinen zusätzlichen Fokusring mehr, seine Fokuslinie ist deutlicher, und die Bereichs-Chips behalten ihre Unterkante. In seitlich aufklappenden Formularen (Drawer) wird der Fokusring der Felder nicht mehr abgeschnitten.

Das Dashboard zeigt statt der Neuigkeiten und des Blogs die Ankündigungen aus dem Forum (forum.emergencyforge.de), angepinnte Themen zuerst. Der Blog-Abruf ist entfernt: das Blog-Widget entfällt, eine Migration löscht seinen Cron-Eintrag und die Blog-Cache-Tabellen. Eine zweite Migration leert den bisherigen Neuigkeiten-Cache, der nächste Abruf füllt ihn mit den Ankündigungen. Die Forum-Adresse lässt sich optional über den Konfigurationsschlüssel `FORUM_URL` ändern, Vorgabe ist `https://forum.emergencyforge.de`.

Logo, Wortmarke und Favicons sind durch die neue ignis-Marke (eckiges i) ersetzt.

## 2026.0.6-beta

ignis übernimmt den gemeinsamen Look „Temper" aus dem UI-Paket: die Sidebar schwebt jetzt frei über dem Hintergrund statt am Rand zu kleben, Leerzustände sehen in der ganzen Anwendung gleich aus, und ein Wechsel zwischen hell und dunkel blendet über statt hart umzuschalten. Wer noch nie eine Dichte gewählt hat, sieht jetzt „Luftig“, auch in bestehenden Konten; wer „Kompakt“ gewählt hat, behält es. Die Wahl liegt pro Browser und Konto im Browser, auf einem neuen Gerät gilt deshalb zuerst „Luftig“.

Die mitgelieferten Plugins firetab, knowledge-base und manv-board (jetzt 1.1.0) brauchen diese ignis-Version, weil ihre Leerzustände den gemeinsamen Baustein `templates/partials/empty.php` nutzen.

Bei einer eigenen Systemfarbe leitet ignis den zweiten Verlaufston für Wortmarke, Primärknöpfe und die aktive Sidebar-Marke automatisch daraus ab, ohne zusätzliche Einstellung.

## 2026.0.5-beta

Die Sync-Anmeldung respektiert jetzt offene, einladungsgebundene und deaktivierte Registrierung. Neue Benutzer erhalten die Standardrolle; bestehende Zuordnungen und Kontosperren bleiben wirksam. Einladung, Benutzerkonto und Sync-Zuordnung werden gemeinsam gespeichert.

Vor diesem Produktupdate Fabrica aktualisieren. Die neue Datenbankmigration erlaubt Sync-Konten ohne Discord-ID. Bestehende Discord-Konten müssen zur Übernahme ihrer Rollen und Daten weiterhin ausdrücklich zugeordnet werden.

## 2026.0.4-beta

Der zentrale Login heißt jetzt „Mit Sync anmelden“ und zeigt das EmergencyForge-Logo. Die direkte Discord-Anmeldung eigenständiger Installationen bleibt unverändert.

## 2026.0.3-beta

Der interne EmergencyForge-Login ist für unsere Fabrica-Instanzen verfügbar.
Die Anmeldung läuft zentral über Fabrica und auth.emergencyforge.de;
Zugänge und fachliche Rollen bleiben in ignis.

- Einmalcodes mit PKCE und State verbinden die Anmeldung mit der jeweiligen Instanz.
- Bestehende lokale Konten werden ausdrücklich über `tools/fabrica-identity.php` zugeordnet. Der Login legt keine Konten oder Adminrechte automatisch an.
- Zentrale Sitzungen und Kontozuordnungen werden spätestens nach 60 Sekunden erneut geprüft. Eine abgelehnte oder nicht erreichbare Prüfung beendet die lokale Benutzersitzung.
- Das Container-Image trägt `de.emergencyforge.fabrica.login=1`, damit Fabrica die Unterstützung vor einem Update oder Restore prüfen kann.

Der Modus ist ausschließlich für EmergencyForge-eigene, durch Fabrica verwaltete
Instanzen vorgesehen. Andere Installationen verwenden weiterhin ihren direkten
Discord-Login. Ein Update allein schaltet den Anmeldemodus nicht um.

Vor der Aktivierung die Migration `20260914000005_fabrica_identities.php`
ausführen, vorhandene Konten zuordnen und den Modus in Fabrica ausdrücklich
aktivieren. Die Schritte stehen in [EMERGENCYFORGE_AUTH.md](EMERGENCYFORGE_AUTH.md).

Diese Version bleibt eine Vorabversion. Ein vollständiger Login-Test gegen
die eingerichtete Auth-Instanz gehört zur Aktivierung auf dem Zielsystem.

Seit der vorherigen Beta sind außerdem die gemeinsame Oberfläche, der
Dokumenteditor und die Paketmigration enthalten. Weitere Korrekturen betreffen
CSRF-Prüfungen schreibender Routen, Dashboard-Links, Uploads, Fahrzeugstationierung
und die Darstellung der eNOTF-v1-Module.

Der neue Login-Integrationstest verwendet die von PHPStan erkannte Query-API
für die Kontoanlage. Diese Fassung ersetzt den Release-Entwurf 2026.0.2-beta.
