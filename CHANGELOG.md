# Changelog

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
