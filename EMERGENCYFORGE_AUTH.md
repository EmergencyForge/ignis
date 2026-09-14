# Interne Anmeldung für Fabricas Instanzen

Dieser Sondermodus ist ausschließlich für die von EmergencyForge über Fabrica
betriebenen Instanzen vorgesehen. Er ist keine Nutzerfunktion und kein Angebot
für fremde Installationen. Nur Fabrica-Administratoren vergeben die nötigen Schlüssel.

Mit `AUTH_MODE=emergencyforge` meldet sich die Instanz über Fabrica an.
`AUTH_MODE=direct-discord` bleibt der Standard für eigenständige Installationen.
Der zentrale Modus verwendet bestehende lokale Konten und deren Rollen.

## Einrichtung

1. Auth-Dienst und Fabrica-OIDC einrichten. Die Deployment-Konfiguration
   liegt im Repository [EmergencyForge/Auth](https://github.com/EmergencyForge/Auth).
2. Fabrica und dieses Produkt mit ihren neuen Migrationen ausrollen.
3. Die Instanz in Fabrica einem Verwaltungsteam zuordnen. Normale
   Produktnutzer benötigen keine Teammitgliedschaft oder Freigabe in
   Fabrica. Das Produkt entscheidet über ihr lokales Konto und ihre Rollen.
4. Als Fabrica-Admin `POST /api/admin/instances/{instanceId}/login-client`
   aufrufen. Die Antwort enthält die Instanz-ID, die feste Callback-Adresse
   und einen nur einmal angezeigten Schlüssel.
5. Die Produktumgebung vorbereiten:

```dotenv
AUTH_MODE=emergencyforge
FABRICA_URL=https://console.emergencyforge.de
FABRICA_INSTANCE_ID=
FABRICA_INSTANCE_CREDENTIAL=
```

Die leeren Werte aus Fabrica übernehmen. Schlüssel nur serverseitig
speichern. Die Instanz muss über HTTPS direkt unter ihrer verwalteten
Subdomain erreichbar sein; Unterverzeichnis-Installationen werden hier
noch nicht unterstützt. Der feste Callback lautet `/auth/fabrica/callback`.

Vor dem Umschalten bestehende Konten zuordnen. Die Person hinter der
lokalen Benutzer-ID und der Fabrica-Konto-ID (`GET /api/admin/users`, Feld
`id`) vorher prüfen. Auf dem Server im Produktverzeichnis:

```sh
AUTH_MODE=emergencyforge php tools/fabrica-identity.php link LOCAL_USER_ID FABRICA_USER_UUID
AUTH_MODE=emergencyforge php tools/fabrica-identity.php unlink LOCAL_USER_ID
```

Die beiden großgeschriebenen IDs durch die geprüften Werte ersetzen.
Die vorangestellte Umgebungsvariable erlaubt die Vorbereitung, während der
Webbetrieb noch Discord verwendet. Eine vorhandene andere Zuordnung wird
abgewiesen. Für eine Korrektur zuerst ausdrücklich aufheben. Das Werkzeug
ändert keine Rollen und ist nur per CLI aufrufbar.

Erst dann die Webumgebung auf `emergencyforge` umstellen und den Prozess
neu laden. Eine noch bestehende direkte Discord-Sitzung wird im zentralen
Modus beendet. Der Login zeigt nun `Mit EmergencyForge anmelden`; direkte
Discord-Callbacks sind gesperrt.

## Verhalten

Der Browser erhält einen einmaligen, 60 Sekunden gültigen Code. State und
PKCE binden ihn an den begonnenen Login. Das Produkt tauscht ihn über eine
authentifizierte Serveranfrage ein. Die lokale Kontozuordnung verwendet
Fabrica-Origin und Konto-ID; E-Mail, Anzeigename und Discord-ID sind keine
automatischen Zusammenführungskriterien.

Spätestens nach 60 Sekunden werden die zentrale Sitzung und das lokale
Konto erneut geprüft. Eine abgelaufene Fabrica-Sitzung oder Kontosperre
beendet die lokale Benutzersitzung beim nächsten Zugriff. Das Entfernen
einer Fabrica-Teammitgliedschaft oder -Freigabe sperrt kein Produktkonto. Ist die Prüfung
nach Ablauf dieses Intervalls nicht erreichbar, wird der Zugang ebenfalls
beendet. Serveranfragen prüfen TLS, haben einen kurzen Timeout und folgen
keinen Weiterleitungen. Der Instanzschlüssel und die Sitzungsschlüssel
gehören niemals in Browser-Speicher, URLs oder Logs.

Anmelde-Callbacks bekommen `no-store` und `no-referrer`. Querystrings von
`/auth/fabrica/callback` im vorgeschalteten Access-Log ausnehmen. Ein
Produkt-Logout beendet nur die lokale Sitzung. Ein Fabrica-Logout wird
über die regelmäßige Freigabeprüfung wirksam; ein Logout allein bei
authentik wird derzeit nicht automatisch an Fabrica weitergereicht.

Ein erneuter `POST /api/admin/instances/{instanceId}/login-client` rotiert
den Instanzschlüssel und beendet die bisherige Anbindung. Den neuen
Schlüssel anschließend in der Produktumgebung hinterlegen.
`DELETE` auf derselben Adresse widerruft die Anbindung vollständig.

## Noch offen

Neue Produktkonten ohne vorherige lokale Anlage, die Oberfläche zur
Kontozuordnung und automatische Instanzschlüssel beim Provisionieren
sind noch nicht umgesetzt. Nicht zugeordnete oder deaktivierte Konten
werden abgewiesen; der erste zentrale Besucher erhält keine Adminrechte.
Die vollständige zentrale Anleitung liegt im Fabrica-Repository unter
`docs/betrieb/instanz-anmeldung.md`.

In ignis betrifft dies die persönliche Benutzersitzung. Fahrzeug-, Crew-,
Charakter-, PIN- und API-Schlüssel-Zugänge behalten ihre bisherigen Regeln.
Diese Zugänge auf persönliche Konten umzustellen, ist eine eigene Änderung.

Nach vorbereiteter Kontozuordnung kann die Fabrica-Administration mit
`POST /api/admin/instances/{instanceId}/login-client` und
`{"managed":true,"accountsLinked":true}` die Konfiguration dauerhaft übernehmen.
Ab dem nächsten Update setzt Fabrica die Variablen automatisch, auch beim Restore.
Das Image meldet seine Unterstützung mit `de.emergencyforge.fabrica.login="1"`.
Ohne diese ausdrückliche Freigabe bleibt der bisherige Login aktiv.
