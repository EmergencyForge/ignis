# ignis installieren

Diese Anleitung führt dich durch die Installation von ignis. Es gibt drei Wege, such dir den aus, der zu deinem Hosting passt:

| Weg | Passt, wenn du … | Abschnitt |
|---|---|---|
| Installationspaket mit Assistent | einen Webspace bei einem Hoster hast (Plesk, cPanel, Confixx, …) und dich durch ein paar Formulare klicken willst | [Weg A](#weg-a-webspace-mit-installationsassistent) |
| Eigener Server | einen eigenen Server mit Apache oder nginx und PHP betreibst | [Weg B](#weg-b-eigener-server-mit-apache-oder-nginx) |
| Docker | Docker und Docker Compose auf deinem Server hast | [Weg C](#weg-c-docker) |

Egal welchen Weg du nimmst: Lies vorher [Was du brauchst](#was-du-brauchst) und leg die [Discord-Anwendung](#discord-anwendung-anlegen) an. Danach geht es mit der [Ersteinrichtung](#nach-der-installation) weiter.

Die Releases findest du unter <https://github.com/EmergencyForge/ignis/releases>. Empfohlen sind stabile Versionen, also die ohne `-beta` im Namen.


## Was du brauchst

**Webserver und PHP**

- PHP 8.3 oder 8.4
- Diese PHP-Erweiterungen: curl, fileinfo, gd, intl, json, mbstring, openssl, pdo, pdo_mysql, xml, zip. Bei den meisten Hostern sind sie schon aktiv.
- Apache mit `mod_rewrite` (und am besten `mod_headers`) oder nginx mit PHP-FPM
- Empfohlene PHP-Werte: `memory_limit` 256M, `upload_max_filesize` 50M, `post_max_size` 55M, `max_execution_time` 120
- HTTPS mit gültigem Zertifikat. Ohne HTTPS funktionieren die Anmeldung und die Tablets in FiveM nicht zuverlässig.

**Datenbank**

- MySQL ab 8.0 oder MariaDB ab 10.6
- Eine leere Datenbank mit Zeichensatz `utf8mb4` und eigenem Benutzer. ignis legt die Tabellen selbst an, die Datenbank selbst musst du vorher anlegen.

**Gut zu haben**

- Die PHP-Funktionen `exec` und `proc_open`. Die automatischen Hintergrundaufgaben (Warteschlange, Ankündigungen, Updates prüfen, Aufräumen) starten damit einen eigenen PHP-Prozess. Manche Webspaces schalten das ab, dann laufen diese Aufgaben nicht. ignis selbst läuft trotzdem.
- cURL oder `allow_url_fopen`, damit der Updater neue Versionen laden kann.
- Zugriff auf einen Cronjob oder einen externen Cron-Dienst (siehe [Cronjob einrichten](#cronjob-einrichten)).


## Discord-Anwendung anlegen

Die Anmeldung bei ignis läuft über Discord. Dafür brauchst du eine eigene Discord-Anwendung:

1. Öffne <https://discord.com/developers/applications> und leg mit **New Application** eine Anwendung an, zum Beispiel mit dem Namen deiner Organisation.
2. Öffne links **OAuth2**.
3. Kopier dir die **Client ID** und erzeuge mit **Reset Secret** ein **Client Secret**. Beides brauchst du gleich bei der Installation. Das Secret wird nur einmal angezeigt.
4. Trag unter **Redirects** diese Adresse ein und speichere:

   ```
   https://deine-domain.de/auth/callback.php
   ```

   Liegt ignis in einem Unterordner, gehört der Ordner mit hinein, zum Beispiel `https://deine-domain.de/ignis/auth/callback.php`. Die Adresse muss genau so geschrieben sein, inklusive `https` und `.php` am Ende.

Mehr Rechte als „identify“ fragt ignis bei Discord nicht an.


## Weg A: Webspace mit Installationsassistent

Für Webspaces gibt es zu jedem Release ein fertiges Installationspaket mit einem Assistenten. Composer, Node oder eine Kommandozeile brauchst du dafür nicht.

### 1. Paket herunterladen

Lade auf der [Release-Seite](https://github.com/EmergencyForge/ignis/releases) bei der gewünschten Version unter **Assets** die Datei `ignis-vVERSION-install.zip` herunter (nicht `ignis-vVERSION.zip`) und entpacke sie **auf deinem Rechner**. Darin liegen drei Dateien:

- `setup.php`, der Assistent
- `ignis-vVERSION.zip`, die eigentliche Anwendung
- `readme.txt`, eine Kurzfassung dieser Anleitung

### 2. Datenbank anlegen

Leg im Kundenmenü deines Hosters eine neue MySQL- oder MariaDB-Datenbank an und notier dir Servername, Port, Datenbankname, Benutzer und Passwort.

### 3. Hochladen

Lade `setup.php` und `ignis-vVERSION.zip` per FTP in den Ordner, in dem ignis laufen soll, also zum Beispiel ins Hauptverzeichnis deiner Domain oder in einen Unterordner `ignis/`.

> Das zweite ZIP **nicht** selbst entpacken. Das übernimmt der Assistent.

Stell im Hosting-Panel die Domain zunächst auf **genau diesen Ordner** ein, nicht auf einen Unterordner `public`. Den gibt es erst, wenn der Assistent fertig ist.

### 4. Assistent durchlaufen

Ruf im Browser `https://deine-domain.de/setup.php` auf (bei einem Unterordner `https://deine-domain.de/ignis/setup.php`). Der Assistent führt dich durch diese Schritte:

1. **Anforderungen:** PHP-Version, Erweiterungen und Schreibrechte werden geprüft. Ist hier etwas rot, musst du es im Hosting-Panel beheben, bevor es weitergeht.
2. **Quelle:** Normalerweise steht hier „Mitgeliefertes Paket“. Ohne das ZIP lädt der Assistent die neueste Version von GitHub.
3. **Datenbank:** Zugangsdaten aus Schritt 2 eintragen und mit **Verbindung testen** prüfen.
4. **System:** Domain, Basis-Pfad und Servername. Domain und Pfad sind vorausgefüllt, prüf sie trotzdem. Der Basis-Pfad ist `/` im Hauptverzeichnis und zum Beispiel `/ignis/` in einem Unterordner.
5. **Discord:** Client ID und Client Secret aus der [Discord-Anwendung](#discord-anwendung-anlegen). Der Assistent zeigt dir die Redirect-Adresse an, die im Discord-Portal stehen muss.
6. **Zusammenfassung:** Alles prüfen, dann **Setup durchführen**.

Der Assistent entpackt die Anwendung, schreibt die Zugangsdaten in eine `.env`, legt die Tabellen an und trägt Domain, Basis-Pfad und Servername in die Einstellungen ein. Zum Schluss löscht er `setup.php`, das ZIP und die `readme.txt` selbst.

### 5. Gleich anmelden

Melde dich direkt danach mit Discord an. **Wer sich als Erstes anmeldet, wird Administrator.** Lass die frische Installation also nicht offen herumstehen.

### 6. Hauptverzeichnis umstellen (empfohlen)

ignis liefert alles, was öffentlich erreichbar sein muss, aus dem Unterordner `public/` aus. Wenn dein Hoster es erlaubt, stell die Domain jetzt auf `<dein Ordner>/public` um. Das ist die sauberste Lösung.

Geht das nicht, ist das auch in Ordnung: Eine `.htaccess` im Hauptordner sperrt alle internen Ordner und leitet die Anfragen nach `public/` weiter. Dafür muss dein Hoster `.htaccess`-Dateien vollständig erlauben (bei Apache `AllowOverride All`). Das Dashboard zeigt dir einen Hinweis, solange ignis über diesen Umweg läuft.

Mach danach mit der [Ersteinrichtung](#nach-der-installation) weiter.

### Wenn der Assistent abbricht

- Bricht die Installation mittendrin ab, kannst du `setup.php` einfach noch einmal aufrufen. Das mitgelieferte ZIP bleibt liegen, bis die Installation geklappt hat.
- Meldet der Assistent „Setup bereits abgeschlossen“, gibt es schon eine `.env` oder eine Datei `.setup-locked`. Willst du wirklich neu installieren, lösch beide per FTP.
- Konnte der Assistent Domain, Basis-Pfad oder Servername nicht in die Datenbank schreiben, zeigt er dir an, was du nachtragen musst. Bei einem Unterordner gibt er dir dafür einen fertigen SQL-Befehl für phpMyAdmin.


## Weg B: Eigener Server mit Apache oder nginx

### 1. Anwendung herunterladen

Lade auf der [Release-Seite](https://github.com/EmergencyForge/ignis/releases) die Datei `ignis-vVERSION.zip` herunter und entpacke sie zum Beispiel nach `/var/www/ignis`. Das ZIP enthält alles, was ignis braucht, auch die PHP-Bibliotheken und die gebauten Styles. Composer und Node brauchst du nicht.

> Ein `git clone` des Repositorys reicht nicht. Für den Bau werden interne Pakete gebraucht, die nicht öffentlich sind.

Mit der zugehörigen `.sha256`-Datei kannst du den Download prüfen:

```bash
sha256sum -c ignis-vVERSION.zip.sha256
```

### 2. Datenbank anlegen

```sql
CREATE DATABASE ignis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ignis'@'localhost' IDENTIFIED BY 'ein-langes-passwort';
GRANT ALL PRIVILEGES ON ignis.* TO 'ignis'@'localhost';
```

### 3. Konfiguration

Kopier `.env.example` nach `.env` und trag die Werte ein:

```ini
APP_ENV=production

DB_HOST=localhost
DB_PORT=3306
DB_NAME=ignis
DB_USER=ignis
DB_PASS=ein-langes-passwort

DISCORD_CLIENT_ID=...
DISCORD_CLIENT_SECRET=...
```

`AUTH_MODE=direct-discord` bleibt so stehen. Die anderen Anmeldearten sind nur für Instanzen gedacht, die EmergencyForge selbst betreibt.

Statt einer `.env` kannst du die Werte auch als Umgebungsvariablen setzen. Dann muss `variables_order` in der php.ini ein `E` enthalten (zum Beispiel `EGPCS`), sonst sieht PHP die Discord-Daten nicht.

### 4. Rechte

Der Webserver-Benutzer (meist `www-data`) muss in diese Ordner schreiben dürfen:

- `storage/` mit allen Unterordnern
- `plugins/`, damit Plugins hochgeladen oder aus dem Katalog installiert werden können
- das Hauptverzeichnis selbst, wenn du den eingebauten Updater nutzen willst

```bash
chown -R www-data:www-data /var/www/ignis/storage /var/www/ignis/plugins
```

### 5a. Apache

Das Hauptverzeichnis (DocumentRoot) zeigt auf `public/`. `mod_rewrite` muss aktiv sein, `mod_headers` ist empfohlen.

```apache
<VirtualHost *:443>
    ServerName ignis.example.de
    DocumentRoot /var/www/ignis/public

    <Directory /var/www/ignis>
        AllowOverride None
        Require all denied
    </Directory>

    <Directory /var/www/ignis/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/ignis.example.de/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/ignis.example.de/privkey.pem
</VirtualHost>
```

```bash
a2enmod rewrite headers ssl
systemctl reload apache2
```

Die `.htaccess` in `public/` kümmert sich um die Weiterleitung an `index.php` und sorgt dafür, dass Browser nach einem Update sofort die neuen Skripte laden.

### 5b. nginx

Im Hauptordner liegt `nginx.conf.example` mit einer vollständigen Konfiguration für nginx mit PHP-FPM. Die wichtigsten Punkte:

- `root` zeigt auf `/var/www/ignis/public`
- alle Anfragen ohne passende Datei gehen an `index.php` (`try_files $uri /index.php$is_args$args;`)
- die beiden `map`-Blöcke für FiveM gehören in den `http`-Block. Ohne sie zeigen die Tablets in FiveM nur eine weiße Seite.
- Skripte und Styles mit `Cache-Control: no-cache`, sonst sehen Nutzer nach einem Update noch lange die alte Fassung
- `client_max_body_size 55M` passend zu den PHP-Upload-Grenzen

Pass in der Datei den Servernamen, die Zertifikatspfade und den Pfad zum PHP-FPM-Socket an und binde sie ein.

### 6. Erster Start

Ruf die Seite im Browser auf. Beim ersten Aufruf legt ignis die Tabellen selbst an und zeigt kurz „Datenbank erfolgreich initialisiert“. Alternativ geht das auf der Kommandozeile:

```bash
cd /var/www/ignis
sudo -u www-data php cli/intra.php migrate
```

Melde dich danach **sofort** mit Discord an. Wer sich als Erstes anmeldet, wird Administrator. Weiter geht es mit der [Ersteinrichtung](#nach-der-installation).

### ignis in einem Unterordner

Soll ignis unter `https://deine-domain.de/ignis/` laufen statt auf einer eigenen (Sub-)Domain, musst du den Basis-Pfad nach dem ersten Start einmal direkt in der Datenbank setzen, denn ohne ihn kannst du dich noch nicht anmelden:

```sql
UPDATE intra_config SET config_value = '/ignis/' WHERE config_key = 'BASE_PATH';
```

Der Pfad beginnt und endet mit `/`. Denk auch an die passende Redirect-Adresse im Discord-Portal. Der Installationsassistent aus Weg A erledigt das von selbst.


## Weg C: Docker

Für Docker gibt es ein fertiges Image und eine Compose-Datei mit MariaDB.

### 1. Dateien holen

Leg einen Ordner an und lade die Compose-Datei und die Vorlage für die Einstellungen herunter:

```bash
mkdir ignis && cd ignis
curl -fsSLO https://raw.githubusercontent.com/EmergencyForge/ignis/main/docker-compose.prod.yml
curl -fsSL -o .env https://raw.githubusercontent.com/EmergencyForge/ignis/main/docker/.env.example
```

### 2. Einstellungen eintragen

Öffne die `.env` und trag mindestens diese Werte ein:

```ini
IMAGE_TAG=
DB_NAME=ignis
DB_USER=ignis
DB_PASS=ein-langes-passwort
DISCORD_CLIENT_ID=...
DISCORD_CLIENT_SECRET=...
SYSTEM_URL=https://ignis.example.de
```

- `IMAGE_TAG` ist die ignis-Version. Lässt du es leer, holt Docker die neueste stabile Version (`latest`). Willst du bei einer Version bleiben, trag sie hier ein, zum Beispiel `v2026.1.0`. Die Versionen stehen auf der [Release-Seite](https://github.com/EmergencyForge/ignis/releases).
- Datenbank und Benutzer legt MariaDB beim ersten Start mit diesen Werten an.
- Enthält ein Passwort ein `$`, setz es in einfache Anführungszeichen, sonst liest Compose es als Variable.
- `SYSTEM_URL` ist die öffentliche Adresse. Steht sie in der `.env`, ist sie in den Einstellungen gesperrt.

### 3. Starten

```bash
docker compose -f docker-compose.prod.yml up -d
```

Beim Start wartet ignis auf die Datenbank und legt die Tabellen an. Nach etwa einer halben Minute meldet `docker compose -f docker-compose.prod.yml ps` den Container als `healthy`.

ignis lauscht jetzt auf `127.0.0.1:8080`, also nur auf dem Server selbst. Für den Zugriff von außen gehört ein Reverse Proxy mit HTTPS davor, siehe [Reverse Proxy und HTTPS](#reverse-proxy-und-https). Einen anderen Port stellst du mit `IGNIS_PORT` in der `.env` ein.

Melde dich danach **sofort** mit Discord an. Wer sich als Erstes anmeldet, wird Administrator.

### Was wo gespeichert wird

| Volume | Inhalt |
|---|---|
| `db` | die Datenbank |
| `storage` | Dokumente, Uploads, Profilbilder, Logs, Cache |
| `plugins` | Plugins aus dem Katalog. Die mitgelieferten Plugins kopiert ignis bei jedem Start aus dem Image dorthin, sie kommen also mit jedem Update mit. |

Diese drei Volumes gehören ins Backup, siehe [Backups](#backups).

### Updates mit Docker

Ohne `IMAGE_TAG` holt der erste Befehl die neueste stabile Version. Hast du eine Version eingetragen, setz sie vorher auf die neue. Dann:

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Die Datenbank wird beim Start automatisch angepasst. Der Updater in den Einstellungen zeigt im Container nur an, dass es eine neue Version gibt, installieren kann er dort nichts.


## Reverse Proxy und HTTPS

ignis braucht HTTPS. Bei Weg A kümmert sich dein Hoster darum (meist über Let's Encrypt im Kundenmenü). Bei Weg B konfigurierst du das Zertifikat im Webserver. Bei Docker oder wenn ignis hinter einem anderen Server steht, brauchst du einen Reverse Proxy.

Wichtig ist, dass der Proxy die ursprüngliche Adresse weiterreicht. ignis baut daraus Links und die Discord-Redirect-Adresse. Diese Header müssen gesetzt sein:

- `Host` bzw. `X-Forwarded-Host`
- `X-Forwarded-Proto` (also `https`)

**Caddy** setzt das von selbst und holt sich das Zertifikat automatisch:

```
ignis.example.de {
    reverse_proxy 127.0.0.1:8080
}
```

**nginx** als Proxy vor dem Container:

```nginx
server {
    listen 443 ssl http2;
    server_name ignis.example.de;

    ssl_certificate     /etc/letsencrypt/live/ignis.example.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ignis.example.de/privkey.pem;

    client_max_body_size 55M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

Ein gleiches Beispiel steht am Ende von `nginx.conf.example`.

Leitet Discord nach der Anmeldung auf eine falsche Adresse um (zum Beispiel `http://` statt `https://` oder den internen Port), stimmen die Header nicht. Als Notlösung kannst du die Adresse mit `DISCORD_REDIRECT_URI` in der `.env` fest vorgeben.

### Cloudflare

Läuft die Domain über Cloudflare, stell unter **Caching › Configuration** die **Browser Cache TTL** auf **Respect Existing Headers**. Sonst behalten Browser nach einem Update bis zu Stunden die alten Skripte, und neue Funktionen tauchen erst nach einem harten Neuladen auf. SSL/TLS sollte auf **Full (strict)** stehen.


## Nach der Installation

### Ersteinrichtung

Nach der ersten Anmeldung zeigt das Dashboard eine Checkliste. Unter **Einstellungen** führt dich der Knopf **Jetzt einrichten** zu allem, was noch offen ist. Pflicht sind:

- **System-URL:** die Adresse deiner Instanz ohne `https://`, zum Beispiel `ignis.example.de`
- **Servername:** der Name deines RP-Servers

Empfohlen sind außerdem Systemname, Adresse und Stadt deiner Organisation sowie eine eigene eNOTF-PIN statt `1234`.

Alles Weitere findest du unter **Einstellungen › System-Konfiguration**.

### Registrierung

Unter **Einstellungen › System-Konfiguration › Adresse und Anmeldung** legst du fest, wer sich ein Konto anlegen darf:

- **Offen für alle:** jeder mit Discord-Konto
- **Nur mit Einladungscode:** Einladungen erstellst du unter **Einstellungen › Zugang › Einladungen** oder direkt im Profil eines Mitarbeiters
- **Geschlossen:** keine neuen Konten

### Cronjob einrichten

ignis hat Hintergrundaufgaben, zum Beispiel Discord-Webhooks und Benachrichtigungen aus der Warteschlange, die Ankündigungen aus dem Forum, die Prüfung auf Updates und das Aufräumen alter Dateien. Ohne weitere Einrichtung laufen sie nebenbei mit, wenn jemand eine Seite aufruft. Auf einer wenig besuchten Instanz passiert dann aber lange nichts. Besser ist einer dieser Wege:

**Cronjob auf dem Server** (Weg B):

```cron
* * * * * cd /var/www/ignis && php cli/intra.php cron:tick > /dev/null 2>&1
```

Der Cronjob sollte als Webserver-Benutzer laufen (zum Beispiel in der Crontab von `www-data`), sonst gehören neue Dateien `root`.

**Docker:**

```cron
* * * * * cd /pfad/zu/ignis && docker compose -f docker-compose.prod.yml exec -T -u www-data app php cli/intra.php cron:tick > /dev/null 2>&1
```

**Cron-Dienst über das Web** (Webspace ohne Cronjobs): Lass einen Dienst wie cron-job.org jede Minute diese Adresse aufrufen:

```
https://deine-domain.de/cron.php?token=DEIN-TOKEN
```

Den Token findest du unter **Einstellungen › System-Konfiguration › Technik**. Die fertige Adresse steht auch unter **Einstellungen › Wartung und Diagnose › Cronjobs**, dort siehst du außerdem, wann welche Aufgabe zuletzt gelaufen ist.

Hat dein Hoster `proc_open` abgeschaltet, schlagen die meisten Aufgaben fehl. Das siehst du ebenfalls auf der Cronjob-Seite.

### Plugins

eNOTF, fireTab, Wissensdatenbank, MANV-Board und Mail sind mitgeliefert und eingeschaltet, eNOTF v2 ist mitgeliefert, aber aus. Unter **Einstellungen › Wartung und Diagnose › Plugins** schaltest du sie ein und aus und installierst weitere aus dem Katalog oder lädst ein Plugin-ZIP hoch. Plugins von Drittanbietern laufen mit vollen Rechten auf dem Server; vor der Installation fragt ignis deshalb ausdrücklich nach.

### ef_bridge für FiveM

Die FiveM-Ressource ef_bridge (vormals ignisTab) bringt eNOTF und fireTab als Tablets ins Spiel. Sie hat eine eigene Anleitung im Repository [EmergencyForge/ef_bridge](https://github.com/EmergencyForge/ef_bridge/blob/main/INSTALL.md). Auf der ignis-Seite brauchst du dafür den API-Schlüssel (**Einstellungen › System-Konfiguration › Technik**) und für die Anmeldung im Spiel die Option **Anmeldung über ef_bridge** unter **Funktionen**.

### Telemetrie

ignis schickt ab Werk regelmäßig Nutzungsdaten an EmergencyForge: eine zufällige Installations-ID, die ignis-, PHP- und Datenbankversion, Server- und Systemname, die Art der Organisation, Webserver und Zeitzone, Zählerstände (zum Beispiel Nutzer, Mitarbeiter, Fahrzeuge, Protokolle, Artikel) und welche Plugins aktiv sind. Namen einzelner Personen oder Inhalte werden nicht übertragen. Unter **Einstellungen › Wartung und Diagnose › Telemetrie** siehst du die Daten und kannst die Übertragung abschalten.


## Updates

**Weg A und B:** Unter **Einstellungen › Wartung und Diagnose › Updates** zeigt ignis neue Versionen an und installiert sie auf Knopfdruck. Der Updater lädt das Release von GitHub, prüft die Prüfsumme, sichert vorher jede Datei, die er ersetzt, nach `storage/backups/updates/` und passt danach die Datenbank an. Dafür braucht er Schreibrechte im Hauptverzeichnis, cURL oder `allow_url_fopen` und etwa 200 MB freien Platz.

Die Datenbank sichert der Updater **nicht**. Mach vorher ein [Backup](#backups).

Von Hand geht ein Update so: neues `ignis-vVERSION.zip` herunterladen, über die bestehenden Dateien entpacken, dabei `.env`, `storage/` und selbst installierte Plugins in `plugins/` nicht löschen. Beim nächsten Seitenaufruf passt ignis die Datenbank an, oder du rufst `php cli/intra.php migrate` auf.

**Docker:** siehe [Updates mit Docker](#updates-mit-docker).

Vor jedem Update lohnt ein Blick in das [Changelog](CHANGELOG.md).


## Backups

Sichere regelmäßig und auf jeden Fall vor einem Update:

- die Datenbank
- den Ordner `storage/` (Dokumente, Uploads, Profilbilder)
- die `.env`
- `plugins/`, wenn du Plugins aus dem Katalog installiert hast

Datenbank auf dem eigenen Server:

```bash
mysqldump --single-transaction -u ignis -p ignis > ignis-$(date +%F).sql
```

Datenbank mit Docker:

```bash
docker compose -f docker-compose.prod.yml exec -T db sh -c 'mariadb-dump --single-transaction -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > ignis-$(date +%F).sql
```

Die Volumes `storage` und `plugins` sicherst du zum Beispiel so:

```bash
docker run --rm -v ignis_storage:/data -v "$PWD":/backup alpine tar czf /backup/storage-$(date +%F).tar.gz -C /data .
```


## Hilfe bei Problemen

**Zustand prüfen:** `https://deine-domain.de/api/health` zeigt, ob Datenbank, PHP-Erweiterungen, Schreibrechte, URL-Weiterleitung und Hintergrundprozesse in Ordnung sind.

**Nur eine leere Seite oder 404 auf allen Unterseiten:** Die URL-Weiterleitung greift nicht. Bei Apache fehlt `mod_rewrite` oder `.htaccess`-Dateien sind nicht erlaubt (`AllowOverride`). Bei nginx fehlt die `try_files`-Zeile.

**HTTP 503 mit Hinweis auf die Datenbank:** In der `.env` fehlt ein Wert oder die Zugangsdaten stimmen nicht.

**Discord meldet „Invalid OAuth2 redirect_uri“:** Die Adresse im Discord-Portal stimmt nicht genau mit der überein, die ignis schickt. Achte auf `https`, den Unterordner und das `.php` am Ende.

**Nach einem Update sieht alles noch alt aus:** Browser-Cache. Mit Strg+F5 neu laden und bei Cloudflare die [Cache-Einstellung](#cloudflare) prüfen.

**Logs:** liegen in `storage/logs/`, bei Docker zusätzlich in `docker compose -f docker-compose.prod.yml logs app`.

**Admin-Zugang verloren** oder jemand Falsches war zuerst angemeldet: Auf der Kommandozeile machst du ein Konto zum Administrator.

```bash
php cli/intra.php bootstrap:admin --discord-id=123456789012345678 --username=deinname
```

Mit Docker steht davor `docker compose -f docker-compose.prod.yml exec -u www-data app`.

Fragen und Fehlermeldungen gerne als Issue unter <https://github.com/EmergencyForge/ignis/issues>.
