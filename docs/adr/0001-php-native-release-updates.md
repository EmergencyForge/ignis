# ADR-0001: Produktionsupdates werden PHP-nativ aus vorbereiteten Release-Artefakten installiert

- **Status:** Accepted
- **Datum:** 2026-08-17
- **Entscheidungskontext:** Automatische Release-Erkennung und Installation von ıgnıs auf selbst verwalteten und eingeschränkten Managed-Hosting-Umgebungen

## Context

ıgnıs wird auch auf Hosting-Angeboten betrieben, bei denen kein SSH-Zugang vorhanden ist und Prozessfunktionen wie `proc_open` oder `exec` deaktiviert sind. Der bisherige Updater konnte auf den Quellcode-Zipball eines GitHub-Releases zurückfallen und benötigte danach einen lokalen Composer-Lauf. Außerdem wurde das gesamte Archiv vor dem Speichern in den PHP-Arbeitsspeicher geladen. Hohe Anforderungen an `memory_limit`, Upload-Limits und Ausführungszeit machten den Ablauf dadurch gerade in den Umgebungen unzuverlässig, die auf den Web-Updater angewiesen sind.

Die Release-Pipeline erstellt bereits ein Produktionsarchiv einschließlich der aufgelösten Composer-Abhängigkeiten sowie einen von GitHub ausgewiesenen SHA-256-Digest. Die Anwendung besitzt außerdem einen PHP-nativen Piggyback-Scheduler, der nach regulären Webanfragen fällige Aufgaben ausführen kann. Nicht Ziel dieser Entscheidung ist es, klassische Deployment-Pipelines für selbst verwaltete Server zu ersetzen oder beliebige Quellcode-Branches als gleichwertigen Produktionskanal zu behandeln.

## Considered Options

### Quellcode-Zipball mit anschließendem Composer-Lauf

Dieser Ansatz hält Release-Artefakte klein und entspricht einem üblichen Entwickler-Workflow. Im vorliegenden Kontext ist sein wesentlicher Nachteil, dass Composer, ein PHP-CLI-Prozess und aktivierte Prozessfunktionen auf dem Zielsystem vorausgesetzt werden. Damit scheidet er als verlässlicher Standardpfad für Managed Hosting aus und bleibt nur ein expliziter Entwicklungs-Fallback.

### Ausschließlich externe Deployment-Automation

Eine CI/CD-Pipeline oder ein Hosting-spezifischer Deployment-Agent kann Updates atomarer ausrollen und eignet sich gut für kontrollierte Serverflotten. Er setzt jedoch Zugangsdaten, unterstützte Provider-Schnittstellen oder Shell-Zugriff voraus. Da ıgnıs auf unabhängig verwalteten Einzelinstallationen läuft, würde diese Wahl einen wesentlichen Teil der Zielumgebungen vom automatischen Update ausschließen.

### PHP-native Installation vorbereiteter Release-Artefakte

Das Release enthält den vollständigen Produktionsstand einschließlich `vendor/`. Die Anwendung lädt es per HTTPS direkt in ein lokales temporäres Verzeichnis, verifiziert den von der Release-API gelieferten SHA-256-Digest, prüft die Archivpfade und installiert es mit PHP-Dateioperationen. Der Ansatz passt zu Shared Hosting und nutzt die bereits vorhandene Release-Pipeline, verlangt dafür aber zusätzliche Sorgfalt bei Artefaktbau, Backup, Wiederanlauf und Integritätsprüfung.

## Decision

Produktionsupdates von ıgnıs werden standardmäßig PHP-nativ aus vorbereiteten GitHub-Release-Artefakten einschließlich aller Laufzeitabhängigkeiten installiert. Automatische Release-Erkennung läuft über den installationslokalen Cache und den PHP-nativen Piggyback-Scheduler; Shell- und Composer-Aufrufe sind keine Voraussetzung für den Produktionspfad.

Das Archiv wird gestreamt statt vollständig im Arbeitsspeicher gehalten. Vor dem Entpacken werden der GitHub-SHA-256-Digest, das ZIP-Format sowie alle Archivpfade geprüft. Persistente Daten und lokale Konfiguration bleiben vom Kopieren ausgeschlossen, Backups liegen innerhalb des beschreibbaren `storage`-Bereichs. Quellcode-Zipballs und Branch-Updates bleiben als bewusst gekennzeichnete Entwicklungsoption erhalten und dürfen einen Composer-Lauf erfordern.

Der stärkste Einwand ist, dass ein Update innerhalb eines Webprozesses weiterhin durch besonders kurze Provider-Timeouts unterbrochen werden kann. Dieser Nachteil wird für den ersten tragfähigen Managed-Hosting-Pfad akzeptiert, weil Streaming, vorbereitete Abhängigkeiten und PHP-native Erkennung die häufigsten Blockaden beseitigen. Ein über mehrere kurze Requests fortsetzbarer Installationsjournal-Ablauf wird als getrennte Folgeentscheidung behandelt.

## Consequences

### Positive Folgen

- Produktionsupdates funktionieren ohne SSH, Composer-Binary, Queue-Worker oder aktivierte Prozessfunktionen.
- Der Speicherbedarf des Downloads bleibt weitgehend unabhängig von der Archivgröße; Upload- und POST-Limits sind für Server-Downloads irrelevant.
- Ein beschädigtes oder unerwartetes Archiv wird vor dem Überschreiben von Anwendungsdateien abgewiesen.
- Die tägliche Erkennung funktioniert auch auf Installationen, die ausschließlich durch normale Webanfragen getaktet werden.

### Bewusst akzeptierte Folgen

- Release-Artefakte werden größer, weil alle Produktionsabhängigkeiten enthalten sind, und die Release-Pipeline wird zu einem betriebsrelevanten Bestandteil des Updatewegs.
- SHA-256 schützt gegen Beschädigung und abweichende Artefakte, ersetzt aber keine unabhängig verwaltete kryptografische Release-Signatur.
- Sehr kurze harte Web-Timeouts können weiterhin einen mehrstufigen, fortsetzbaren Installationsablauf erforderlich machen.

### Auswirkungen auf Folgeentscheidungen

Die Release-Pipeline muss das Produktionsarchiv weiterhin reproduzierbar mit Produktionsabhängigkeiten erzeugen. Eine unabhängige Signatur mit in der Anwendung verankertem öffentlichen Schlüssel sowie ein journalbasierter, über mehrere Requests fortsetzbarer Installations- und Rollback-Ablauf werden separat entschieden. Hosting-spezifische CI/CD-Deployments dürfen zusätzlich angeboten werden, ändern aber nicht den portablen Standardpfad.
