# Changelog

## 2026.0.42-beta

Die Discord-Webhooks zu freigegebenen eNOTF- und fireTab-Protokollen und zu Voranmeldungen gehen wieder raus. Seit April blieben sie in der Warteschlange liegen, weil der Cronjob „queue.work“ nur die Standard-Warteschlange abgearbeitet hat. Jetzt nimmt er auch die Warteschlange der Benachrichtigungen mit.

Damit nach dem Update nicht monatealte Meldungen auf einmal in euren Discord-Kanälen landen, verwirft die Aktualisierung alle liegengebliebenen Webhooks, die älter als eine Stunde sind.

## 2026.0.41-beta

ignis bringt einen eigenen Discord-Bot mit. Eingerichtet wird er unter Einstellungen → System → Discord-Bot: Token aus dem Discord Developer Portal eintragen, Name und Profilbild festlegen, die Seite schickt beides direkt an Discord. Über „Zum Server hinzufügen“ holt ihr den Bot auf euren Server, eine Testnachricht an dich zeigt, ob alles passt. Das Token liegt verschlüsselt in der Datenbank. Den Schlüssel legt ignis beim ersten Mal unter storage/private/secret.key an, er gehört ins Backup; wer möchte, setzt ihn stattdessen als APP_KEY in der .env.

Der Bot schickt Benachrichtigungen zusätzlich als Direktnachricht, voreingestellt neue Mails und Systemmeldungen; welche Arten, legt ihr auf derselben Seite fest. Erreicht wird jedes Konto mit einer Discord-ID am Konto oder am verknüpften Mitarbeiter, sofern es einen Server mit dem Bot teilt. Wer keine Direktnachrichten will, schaltet sie im Kontomenü oben rechts ab.

Im Mitarbeiterprofil erstellt „Einladen“ mit einem Klick die Einladung, kopiert den Link und zeigt danach „Einladung ausstehend“ mit „Link kopieren“, auch nach dem Neuladen. Ein zweiter Klick liefert die offene Einladung statt einer neuen. Hat der Mitarbeiter eine Discord-ID und läuft der Bot, schickt „Per Discord einladen“ den Link direkt als Direktnachricht. Einladen geht jetzt auch bei offener Registrierung: das neue Konto wird dann gleich mit dem Mitarbeiter verknüpft.

Auf der Modulseite tragen beide Kacheln ihre Abhängigkeit, etwa „Braucht eNOTF“ bei eNOTF v2 und „Gebraucht von eNOTF v2“ bei eNOTF. Schaltet ein Schalter einen anderen mit, steht in der Leiste unten, welchen und warum.

## 2026.0.40-beta

Die Seite Einstellungen → System → Module zeigt die Module als Kacheln, je nach Bildschirmbreite bis zu drei nebeneinander, mit dem Schalter oben rechts. Vorher stand jedes Modul in einer eigenen Zeile über die ganze Breite und ließ viel Platz leer.

## 2026.0.39-beta

Anträge und Kalender sind jetzt Plugins und lassen sich einzeln abschalten. Fest dabei bleiben Personal, Benutzer, Dokumente und Fahrzeuge. Beide Module werden mitgeliefert und sind eingeschaltet; Einträge, Rechte und die Plätze in der Seitenleiste bleiben wie bisher. Ist der Kalender aus, verschwinden seine Hinweise in den Urlaubsanträgen, sind die Anträge aus, fehlen sie auf dem Dashboard und in den offenen Aufgaben. Genehmigte Urlaubsanträge erscheinen weiter als Abwesenheit im Kalender, solange beide an sind.

Unter Einstellungen → System → Module steht jetzt, welche Module ihr benutzt: eNOTF, eNOTF v2, fireTab, MANV-Board und Fahrtenbuch für den Einsatz, Anträge, Kalender, Mail und Wissensdatenbank für die Verwaltung, jedes mit einem Schalter. Bei einer neuen Installation ist „Module auswählen“ der erste Schritt der Einrichtung auf dem Dashboard. Installationen, die schon Mitarbeiter haben, sehen den Schritt nicht.

Wer ignis über die eingebaute Aktualisierung einspielt, bekommt die alten Dateien von Anträgen und Kalender aus dem Kern entfernt.

## 2026.0.38-beta

Das Fahrtenbuch ist jetzt ein Plugin und lässt sich unter Einstellungen → System → Plugins abschalten. Es wird mitgeliefert und ist eingeschaltet, Einträge, Rechte und der Platz unter „Fahrzeuge“ in der Seitenleiste bleiben wie bisher. Ist es aus, verschwindet der Fahrtenbuch-Link im eNOTF und in fireTab, beide laufen ohne es weiter.

Wer ignis über die eingebaute Aktualisierung einspielt, bekommt die alten Dateien des Fahrtenbuchs aus dem Kern entfernt.

## 2026.0.37-beta

In Signaturen fällt eine Zeile ohne Wert jetzt auch dann weg, wenn die Platzhalter mit Umschalt+Enter untereinander in einem Absatz stehen. Bisher blieb dort eine Leerzeile, etwa zwischen Dienstgrad und Organisation, wenn jemand keine Position hat. Die Vorschau unter dem Editor zeigt es genauso.

In Formularen im Seitenfenster steht „Ungespeicherte Änderungen“ jetzt auf einer Linie mit den Knöpfen und ist gelb. Scheitert das Speichern, steht dort rot „Nicht gespeichert“, nach dem Speichern grün „Gespeichert“.

Aktualisiert ist das UI-Paket auf 0.9.2.

## 2026.0.36-beta

Wie lange das eNOTF ohne Eingabe offen bleibt, lässt sich jetzt einstellen. Unter Einstellungen → System → eNOTF steht bei „Sperren nach“ eine Auswahl von 2 bis 60 Minuten, vorher waren es fest fünf. Das Feld erscheint nur, solange „PIN abfragen“ an ist. Die Warnung kommt weiterhin eine Minute vor der Sperre.

Ein deaktiviertes oder gelöschtes Konto ist beim nächsten Seitenaufruf abgemeldet. Bisher blieb ein deaktiviertes Konto mit allen Rechten angemeldet, bis es sich selbst abmeldete oder die Sitzung ablief, und ein gelöschtes kam weiter auf Seiten, die nur eine Anmeldung verlangen. Eine eNOTF- oder FireTab-Anmeldung im selben Browser bleibt bestehen. Verliert ein Konto seine Rolle, verschwinden auch Rollenname und Rang aus der Sitzung, und ein geänderter Benutzername kommt ohne neue Anmeldung an.

## 2026.0.35-beta

Rollen lassen sich wieder anlegen, bearbeiten und löschen. Seit der Umstellung auf englische Adressen im Mai endete jedes Speichern auf „Seite nicht gefunden“, auch wenn die Änderung gespeichert war. Dieselbe Ursache hatten weitere Fehler: Speichern im FireTab-Fahrtenbuch endete ebenso auf dieser Fehlerseite, die alte Zielverwaltung im eNOTF und der Hinweis für Konten ohne Adminrecht in den eNOTF-Einstellungen führten ins Leere, und Aufrufe von außen auf alte Adressen mit .php, etwa von älteren ignisTab-Versionen, kamen nicht mehr an. Im eNOTF fragt die Übersicht wieder nach Anfragen zum Teilen von Protokollen, nach „Alle löschen“ und beim Abmelden aus der Klinikansicht bleibt man auf der Seite statt auf dem Dashboard zu landen, und der Schnelllink „Fahrzeuginfo“ funktioniert auch im eNOTF v2.

Plugins lassen sich als ZIP auf die Plugin-Seite ziehen. Vor jeder Installation, jedem Update und jedem Upload steht eine Bestätigungsseite mit Herausgeber, Quelle, Prüfsumme, Abhängigkeiten und Berechtigungen. Bei Uploads und Plugins, die nicht offiziell sind, muss der Admin bestätigen, dass der Code mit vollen Serverrechten läuft. Installation, Update, Entfernen und Ein- oder Ausschalten stehen im Audit-Log. Im Katalog erscheinen die mitgelieferten Module als „Mitgeliefert“ statt mit „SHA256 fehlt“.

Gruppenpostfächer gehören einer Wache, Abteilung oder einem Team. Alle Mitglieder sehen dieselben Ordner, denselben Lesestand und dieselben Entwürfe und schreiben unter der Adresse der Gruppe; in gesendeten Mails steht, wer geschrieben hat. Mitglieder trägt ein anderer Mail-Admin ein, niemand sich selbst. Wer mehrere Postfächer lesen darf, wechselt oben in Mail zwischen ihnen. Eigene Signaturen können jetzt Platzhalter wie Name, Dienstgrad oder Postfachname enthalten und haben einen Editor mit Vorschau.

Dokumente zeigen über der Seite unter „Noch offen“ die leeren Pflichtfelder und die Platzhalter ohne Wert, ein Klick springt hin. Wer trotzdem ausstellt, wird vorher gefragt; leere Platzhalter bleiben im PDF leer statt als {{name}} zu erscheinen. Gesperrte Abschnitte stehen im Editor ohne Rahmen da, Platzhalter mit ihrem Wert, und ein Schalter zeigt die Vorschau.

Die FiveM-Ressource heißt jetzt ef_bridge statt ignisTab. Die Tablet-Anmeldung steht im Audit-Log als „Anmeldung über ef_bridge“.

Der Eintrag „Posteingang“ ist aus der Seitenleiste verschwunden, die Glocke oben zeigt dasselbe. In den Reanimationsdetails im eNOTF ist „erfolglos“ ein direkter Schalter statt eines Links auf eine eigene Spalte.

Aktualisiert ist das Editor-Paket auf 0.5.0.

## 2026.0.34-beta

Filter über den Listen stehen als kompakte Knöpfe mit ihrem Wert, etwa „Dienstgrad Alle“. Eine Auswahl filtert sofort, ein gesetzter Filter ist hervorgehoben. Das gilt für die Mitarbeiter, das Audit Log, die Defekt-Meldungen und das Fahrtenbuch. Im Fahrtenbuch bleibt der Knopf „Filtern“, weil sich der Zeitraum sonst nicht übernehmen lässt.

Mitarbeiter und Benutzer zeigen den Namen mit den Initialen davor. Unter den Listen steht „26 bis 50 von 60 Mitarbeitern“, die aktuelle Seite ist grau statt orange hervorgehoben, und auch der Pfeil der sortierten Spalte ist nicht mehr orange.

Auf dem Dashboard heißen die Links zu den vollen Listen einheitlich „Alle anzeigen“, ebenso bei den offenen Mängeln in der Fahrzeugvorschau. Lange Hinweislisten scrollen in ihrem Kasten und blenden unten aus, solange weitere folgen. Die Kachel „Fahrzeuge einsatzbereit“ trägt ihr Symbol vor der Beschriftung. In der eingeklappten Seitenleiste trennt eine kurze Linie die Gruppen.

Aktualisiert ist das UI-Paket auf 0.9.1, neu dabei ist das Listen-Paket list-query 0.1.0.

## 2026.0.33-beta

Mitarbeiter können einen Titel wie „Dr.“ oder „Prof.“ tragen. Die Auswahl pflegt der Admin unter Einstellungen, Personal, Titel, mitgeliefert sind Dr., Dr. med., Prof. und Prof. Dr. Gesetzt wird der Titel beim Anlegen oder in der Personalakte. Er steht dann im Kopf der Akte, in Mail-Signaturen, in Dokumenten („Sehr geehrter Herr Dr. Max Muster“) und im Absendernamen. Listen, Suche und die Mail-Adresse bleiben ohne Titel. Ein Titel, den noch jemand trägt, lässt sich nicht löschen.

## 2026.0.32-beta

Die Charakter-ID eines Mitarbeiters bleibt beim Bearbeiten des Profils erhalten. Bisher löschte jede Änderung im Profil und jedes Speichern der Qualifikationen sie, wenn Charakter-IDs eingeschaltet sind.

## 2026.0.31-beta

Über die Fahrzeuginfo im eNOTF v1 lassen sich Mängel wieder ohne ignis-Konto melden, solange „Nur mit ignis-Konto“ ausgeschaltet ist. Bisher lehnte der Server die Meldung ohne Konto ab. Die Mängelliste und das Bearbeiten bleiben Konten mit den passenden Rechten vorbehalten.

## 2026.0.30-beta

Im eNOTF v1 speichert eine Crew wieder ohne ignis-Konto, solange „Nur mit ignis-Konto“ ausgeschaltet ist. Seit dem Frühjahr verlangte der Server dafür immer ein Konto: Das Protokoll ließ sich öffnen, aber Eingaben, Teilen, Patienten-Sync und Klinikcode schlugen fehl. Ganz ohne Anmeldung bleibt das Speichern gesperrt.

Die Ankunftstafel der Klinik aktualisiert sich auch ohne Anmeldung wieder alle 15 Sekunden. Bisher zeigte sie neue Voranmeldungen erst nach einem Neuladen der Seite.

## 2026.0.29-beta

In der Reanimationssituation im eNOTF erscheinen die Begründungen erst nach „Reanimation nicht durchgeführt,“ und die Detailfragen erst, wenn „Reanimation durchgeführt“ gewählt ist. Bisher waren beide Spalten von Anfang an offen. Das gilt für v1 und v2.

## 2026.0.28-beta

Die Freigabeseite im eNOTF v1 lässt sich wieder öffnen. Seit 2026.0.22-beta brach sie mit einem Fehler ab, weil die Prüfung der Reanimationsdetails mit dem gespeicherten Protokoll nicht zurechtkam.

## 2026.0.27-beta

Neben der Dienstnummer in der Mitarbeiterliste und im Profilkopf gibt es keinen Kopieren-Knopf mehr.

Das Docker-Image ist auf GitHub jetzt mit dem Repository verknüpft und erscheint dort unter Packages.

## 2026.0.26-beta

Es gibt jetzt eine ausführliche Installationsanleitung in der [INSTALL.md](https://github.com/EmergencyForge/ignis/blob/main/INSTALL.md). Sie beschreibt die Installation auf einem Webspace mit dem Assistenten, auf einem eigenen Server mit Apache oder nginx und mit Docker, dazu Reverse Proxy und HTTPS, Cloudflare, Cronjobs, Updates und Backups.

Docker wird offiziell unterstützt. Mit `docker-compose.prod.yml` startet ignis aus dem fertigen Image zusammen mit MariaDB, Daten und Plugins liegen in eigenen Volumes und überstehen ein Neuanlegen des Containers. Das Image prüft seinen Zustand selbst. Updates kommen als neues Image, der Updater in den Einstellungen zeigt im Container nur noch an, wie das geht.

Der Installationsassistent im Installationspaket ist überarbeitet. Er verlangt PHP 8.3, trägt Domain, Basis-Pfad und den neuen Servernamen direkt in die Einstellungen ein und startet kein Composer mehr. Die Fortschrittsanzeige läuft jetzt wirklich mit, und bricht die Installation ab, lässt sich der Assistent erneut aufrufen. Ohne mitgeliefertes Paket lädt er die neueste Version, auch wenn sie eine Beta ist. Am Ende erklärt er, dass die erste Discord-Anmeldung Administrator wird und wie es weitergeht.

Die eNOTF-Abrechnung aus ignisTab funktioniert wieder. Seit April lehnte ignis die Anfragen des Gameservers ab, weil die Schnittstelle eine Browser-Anmeldung verlangte. Jetzt reicht der API-Schlüssel.

Ein abweichender Datenbank-Port (`DB_PORT`) wird jetzt überall beachtet, auch beim Einrichten der Datenbank. Das nginx-Beispiel speichert Skripte und Styles nicht mehr tagelang zwischen und erlaubt die Zertifikatsprüfung von Let's Encrypt.

## 2026.0.25-beta

Seitliche Fenster gleiten jetzt ruhig herein, ohne über den Fensterrand hinauszuschießen und zurückzufedern. Das gilt auch beim Vergrößern und Verkleinern.

Im Editor schließen Schnellleiste, Menüs und das Link-Fenster mit einer kurzen Animation. Wer weniger Bewegung eingestellt hat, sieht weder beim Öffnen noch beim Schließen eine Animation.

Nach einem Update lädt der Browser Skripte und Styles sofort neu. Vorher konnte er bis zu Stunden die alte Fassung verwenden, sodass neue Funktionen erst nach einem harten Neuladen sichtbar waren.

Bilder, die aus keinem Artikel der Wissensdatenbank mehr genutzt werden, entfernt ein täglicher Cron-Job. Bilder, die gerade erst hochgeladen wurden, bleiben einen Tag lang erhalten.

In der eingeklappten Seitenleiste ist die Versionsangabe ausgeblendet, sie steht weiterhin im Footer.

Aktualisiert sind das UI-Paket auf 0.8.6 und das Editor-Paket auf 0.4.1.

## 2026.0.24-beta

Der Editor für Mails, Signaturen, Dokumente und Vorlagen ist neu. Die Werkzeugleiste ordnet die Knöpfe in Gruppen und nutzt dieselben Symbole wie der Rest von ignis. Aktive Formate sind mit einem Strich darunter markiert. Wer Text markiert, bekommt eine Schnellleiste mit Fett, Kursiv, Unterstrichen, Durchgestrichen, Link und „Formatierung entfernen“. Links setzt man in einem kleinen Fenster statt in einer Abfrage, auch mit Strg+K. Neu sind Unterstreichen und Zitate. Jede Stelle bietet nur die Funktionen an, die dort passen, und was dort nicht erlaubt ist, lässt sich auch nicht einfügen.

Die Wissensdatenbank nutzt jetzt denselben Editor. Artikel können Bilder enthalten, die direkt hochgeladen werden. Bestehende Artikel bleiben, wie sie sind, und lassen sich normal weiter bearbeiten. Der bisherige Editor ist entfernt, das macht ignis um rund 34 MB kleiner. Im hellen Theme waren Artikel, Liste und Formular der Wissensdatenbank kaum lesbar, das ist behoben. Außerdem werden Artikel jetzt beim Speichern und Anzeigen gefiltert: Vorher konnte jemand mit Schreibrechten Skripte in einen Artikel einbauen, die bei allen Lesern liefen.

Die Standard-Signatur in den Mail-Einstellungen hat jetzt den Editor und Platzhalter für Name, Dienstgrad, Position, Fachdienste, Dienstnummer, Mailadresse und Organisation. Platzhalter fügt man mit „{{“ oder über die Knöpfe unter dem Editor ein. Beim Schreiben einer Mail setzt ignis die Daten des Absenders ein, vor dem Dienstgrad steht das Dienstgradabzeichen. Ohne gespeicherte Standard-Signatur gilt die eingebaute Vorlage.

Geänderte Rollen und Rechte gelten jetzt beim nächsten Neuladen der Seite, vorher erst nach bis zu fünf Minuten oder nach erneuter Anmeldung.

Einladungen ließen sich nicht löschen, und in der Spalte „Aktionen“ stand Code als Text. Das ist behoben.

Das Profilfoto ändert man jetzt in einem Dialog mit Ablagefläche und dem Hinweis zu Dateityp und Größe. Der Dialog „Über ignis“ in der Fußzeile ist überarbeitet und passt in beiden Themes.

Seitliche Fenster gleiten beim Vergrößern und Verkleinern weich auf die neue Breite.

Aktualisiert sind das UI-Paket auf 0.8.5, das Editor-Paket auf 0.4.0 und Font Awesome auf 7.3.1.

## 2026.0.23-beta

Die Ankündigungen aus dem Forum erschienen auf der Startseite nie. Im Docker-Image schlug jeder Cron-Job fehl, der einen eigenen PHP-Prozess startet, weil der Webserver keinen Pfad zur PHP-Kommandozeile kannte. Betroffen waren auch Warteschlange, Telemetrie, Instanzvernetzung und Speicherbereinigung. Diese Jobs laufen jetzt wieder. Klappt der Abruf der Ankündigungen nicht, nennt die Karte den Grund und verweist auf die Cron-Jobs.

Neue Mails beginnen mit einer Signatur aus dem Mitarbeiterprofil: Name, Dienstgrad, Position, Fachdienste und Organisation. Zwischen Text und Signatur steht eine Leerzeile, die Signatur lässt sich wie normaler Text ändern oder löschen. Eine eigene Signatur und die Standardsignatur aus den Mail-Einstellungen gehen weiterhin vor. Bei Antworten steht die Signatur jetzt über der zitierten Mail. Im Mail-Editor war der Text auf dunklem Hintergrund kaum zu sehen.

Seitliche Fenster wie „Neue Mail“ lassen sich über einen Knopf neben dem Schließen-Kreuz auf die volle Bildschirmbreite vergrößern.

Das Mitarbeiterprofil zeigt das Foto oder einen Platzhalter und darunter „Foto ändern“. Ein Bild lässt sich auch direkt auf das Foto ziehen. Nach dem Kopieren der Dienstnummer stand das Kopier-Symbol neben „Kopiert“, das ist behoben.

Bei der eNOTF-Anmeldung setzt die Auswahl eines Namens aus der Personalliste die hinterlegte RD-Qualifikation daneben, in v1 und v2. Sie lässt sich danach noch ändern.

Aktualisiert ist das UI-Paket auf 0.8.4.

## 2026.0.22-beta

Konten aus der zentralen Anmeldung haben keine Discord-ID und fanden deshalb ihren Mitarbeiter nicht: Eigene Anträge, Dokumente, Termine, das Postfach und die Listen auf der Startseite blieben leer. Konto und Mitarbeiter sind jetzt fest verknüpft. Beim Update verknüpft ignis bestehende Konten über die Discord-ID, wenn genau ein Konto und genau ein Mitarbeiter zusammenpassen. Neue Konten werden bei der Anmeldung über Discord verknüpft oder über eine Einladung, die einem Mitarbeiter gilt. Die Einladung aus dem Mitarbeiterprofil gilt automatisch diesem Mitarbeiter, unter „Einladungen“ lässt er sich auswählen. Von Hand verknüpfen und lösen geht in der Benutzerbearbeitung und im Kontostatus des Profils. Die Discord-ID ist beim Anlegen eines Mitarbeiters keine Pflicht mehr, und das Tablet-Login findet ein Konto auch über den Mitarbeiter mit dieser Discord-ID. Auf der Startseite konnten Konten ohne Discord-ID vorher Dokumente und Anträge anderer sehen.

Die Einstellungen sind neu sortiert in Personal, Zugang, Inhalte, eNOTF, Mail und System und haben ein Suchfeld. Die System-Konfiguration ist in Abschnitte geteilt, jedes Feld hat einen Hinweis. Solange System-URL oder Servername fehlen, weisen Startseite und Einstellungen darauf hin. „Jetzt einrichten“ öffnet die Konfiguration, markiert die offenen Felder und nennt Empfehlungen, etwa eine andere eNOTF-PIN als 1234. Der Einrichtungsschritt auf der Startseite gilt als erledigt, sobald die System-URL gesetzt ist, auch wenn sie im Container aus der Umgebung kommt. Aus „Registrierungscodes“ wurden „Einladungen“.

Das eNOTF fragt in v1 und v2 in der Anamnese den Allgemeinzustand vor dem Ereignis ab (optional) und im Abschluss die Reanimation. Ob reanimiert wurde, ist Pflicht. Wenn ja, sind auch Ursache, Kollaps, Laienreanimation, Defibrillation, ROSC, Klinikaufnahme und die Zeiten Pflicht.

In eNOTF v2 zeigt die Messwert-Maske im Erstbefund die Skala mit den Normbereichen, färbt die Werte und markiert leere Pflichtfelder rot, wie in v1. In der Übersicht stehen bei fehlenden Pflicht-Messwerten wieder die Ausrufezeichen. Der Verlauf rutscht nicht mehr unter die Navigation, wenn das Fenster schmaler wird. eNOTF v2 hat jetzt das Favicon und die Tooltips von ignis.

Ein Serverfehler zeigt jetzt dieselbe Fehlerseite wie 404 und 403, mit einem Fehlercode für die Verwaltung. Vorher fehlten der Seite die Styles.

Aktualisiert ist das UI-Paket auf 0.8.3.

## 2026.0.21-beta

Ein im Panel eingeschaltetes oder abgeschaltetes Plugin wirkte bis zum Neustart des Containers nicht richtig: Seiten wie /enotf-v2/ antworteten mit 404, andere landeten bei einer falschen Aktion und zeigten Fehler 500, etwa die Mail-Einstellungen. Der Routen-Cache wurde zwar neu geschrieben, PHP las aber weiter die alte Fassung aus dem OPcache. Jetzt verwirft ignis diese Fassung beim Neuschreiben.

## 2026.0.20-beta

Texte in der Oberfläche, Meldungen und Seitentitel kommen ohne Gedankenstriche aus. Leere Werte zeigen „-“ oder einen kurzen Hinweis wie „keine Angabe“, Bereiche stehen als „1 bis 25“.

Aktualisiert ist das UI-Paket auf 0.8.2.

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
