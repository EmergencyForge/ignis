# Changelog

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
