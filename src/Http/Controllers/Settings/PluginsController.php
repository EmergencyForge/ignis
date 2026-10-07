<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Config\ConfigManager;
use App\Exceptions\UploadException;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Plugins\CatalogClient;
use App\Plugins\CatalogInstaller;
use App\Plugins\PluginLoader;
use App\Plugins\PluginRepository;
use App\Security\CsrfProtection;
use App\Support\FileUpload;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Request;
use EmergencyForge\Plugins\Plugin;
use EmergencyForge\Plugins\PluginManifest;
use EmergencyForge\Plugins\PluginRegistry;

/**
 * Plugin-Verwaltung: Liste der installierten Plugins mit Aktiv-Schalter,
 * Upload eines Plugin-ZIPs und Installation aus dem Hub-Katalog.
 *
 * Aktivieren prüft Kompatibilität und Abhängigkeiten, Deaktivieren
 * respektiert das removable-Flag und blockt, solange ein anderes aktives
 * Plugin das Modul braucht. Daten und Tabellen bleiben beim Deaktivieren
 * unangetastet. Nur Routen, Navigation und Listener verschwinden.
 *
 * Alles, was fremden Code ausführt oder ersetzt (Upload, Katalog-Download,
 * Installation, Update), läuft über eine Bestätigungsseite. Bei Plugins von
 * Drittanbietern verlangt der Server dort das Häkchen `accept_risk`; der
 * Browser kann es nicht überspringen.
 */
final class PluginsController extends Controller
{
    private const UPLOAD_FIELD = 'plugin_zip';
    private const UPLOAD_SESSION_KEY = 'plugin_uploads';
    private const ZIP_TYPES = [
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-zip' => 'zip',
    ];

    /**
     * GET/POST /settings/system/plugins
     *
     * GET mit `confirm=upload|catalog|update|install` zeigt die
     * Bestätigungsseite für den jeweiligen Schritt.
     */
    public function index(?Request $request = null): void
    {
        $this->requireAuth();
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $registry   = PluginRegistry::fromDirectory(PluginLoader::pluginsDir());
        $repository = new PluginRepository();
        $repository->syncDiscovered($registry->all());
        $catalogClient = $this->catalogClient();
        $installer = $this->catalogInstaller();

        $message     = '';
        $messageType = '';

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!CsrfProtection::validateToken((string) ($_POST['csrf_token'] ?? ''))) {
                $message     = 'Sitzung abgelaufen. Bitte Seite neu laden und erneut versuchen.';
                $messageType = 'danger';
            } else {
                $action = (string) ($_POST['plugin_action'] ?? 'toggle');
                $pluginId = (string) ($_POST['plugin_id'] ?? '');
                [$message, $messageType] = match ($action) {
                    'upload' => $this->handleUpload($request, $installer),
                    'upload_commit' => $this->handleUploadCommit($installer, $repository),
                    'upload_discard' => $this->handleUploadDiscard($installer),
                    'install' => $this->handleInstall($pluginId, $registry, $repository),
                    'catalog_install', 'catalog_stage' => $this->handleCatalogStage($pluginId, false, $catalogClient, $installer, $repository),
                    'catalog_update' => $this->handleCatalogStage($pluginId, true, $catalogClient, $installer, $repository),
                    'remove' => $this->handleRemove($pluginId, $registry, $repository, $installer),
                    default => $this->handleToggle($pluginId, $registry, $repository),
                };
            }
        } elseif (isset($_GET['confirm'])) {
            $confirm = $this->confirmation((string) $_GET['confirm'], $registry, $catalogClient, $installer);
            if (is_array($confirm)) {
                $this->renderView('settings/system/plugins-confirm', ['confirm' => $confirm]);
                return;
            }
            [$message, $messageType] = [$confirm, 'warn'];
        }

        // Aktueller Zustand nach eventueller Änderung
        $registry = PluginRegistry::fromDirectory(PluginLoader::pluginsDir());
        $repository->syncDiscovered($registry->all());
        $enabledIds = $repository->enabledIds();
        $registry->resolve($enabledIds, null);

        $activeIds = [];
        foreach ($registry->active() as $plugin) {
            $activeIds[$plugin->id()] = true;
        }
        $skipReasons = [];
        foreach ($registry->skipped() as $skip) {
            $skipReasons[$skip['id']] = $skip['reason'];
        }

        $rows = [];
        foreach ($registry->all() as $id => $plugin) {
            $rows[] = [
                'id'         => $id,
                'manifest'   => $plugin->manifest,
                'installed'  => PluginLoader::isInstalled($plugin),
                'bundled'    => PluginLoader::isBundled($id),
                'enabled'    => in_array($id, $enabledIds, true),
                'active'     => isset($activeIds[$id]),
                'skipReason' => $skipReasons[$id] ?? null,
                'requiredBy' => $this->enabledDependents($id, $registry, $enabledIds),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['manifest']->name, $b['manifest']->name));

        $installedVersions = [];
        $installedStates = [];
        foreach ($registry->all() as $id => $plugin) {
            $installedVersions[$id] = $plugin->manifest->version;
            $installedStates[$id] = PluginLoader::isInstalled($plugin);
        }
        $catalog = $catalogClient->catalog();
        $catalogRows = [];
        foreach ($catalog['plugins'] as $entry) {
            $installedVersion = $installedVersions[$entry['slug']] ?? null;
            $entry['installed_version'] = $installedVersion;
            $entry['installed'] = $installedStates[$entry['slug']] ?? false;
            $entry['update_available'] = $installedVersion !== null && $entry['installed']
                && version_compare((string) $entry['version'], $installedVersion, '>');
            $entry['third_party'] = CatalogClient::isThirdParty($entry);
            $catalogRows[] = $entry;
        }

        $this->renderView('settings/system/plugins', [
            'rows'        => $rows,
            'message'     => $message,
            'messageType' => $messageType,
            'catalogRows' => $catalogRows,
            'catalogStale' => $catalog['stale'],
            'catalogFetchedAt' => $catalog['fetched_at'],
            'catalogError' => $catalog['error'],
            'maxUploadBytes' => $this->phpUploadLimit(),
        ]);
    }

    // ── Bestätigungsseite ─────────────────────────────────────────────

    /**
     * Daten für die Bestätigungsseite, oder ein Hinweistext, wenn es nichts
     * (mehr) zu bestätigen gibt.
     *
     * @return array<string,mixed>|string
     */
    private function confirmation(string $kind, PluginRegistry $registry, CatalogClient $catalog, CatalogInstaller $installer): array|string
    {
        if ($kind === 'upload') {
            $token = (string) ($_GET['token'] ?? '');
            if (!$this->ownsUpload($token)) return 'Der Upload ist nicht mehr vorhanden. Bitte die Datei erneut hochladen.';
            try {
                $pending = $installer->pendingUpload($token);
            } catch (\Throwable $e) {
                $this->forgetUpload($token);
                return 'Upload kann nicht installiert werden: ' . $e->getMessage();
            }
            return $this->manifestConfirmation($pending['manifest'], [
                'kind' => 'upload',
                'action' => 'upload_commit',
                'fields' => ['upload_token' => $token, 'expect_update' => $pending['update'] ? '1' : '0'],
                'update' => $pending['update'],
                'installed_version' => $pending['installed_version'],
                'sha256' => $pending['sha256'],
                'bytes' => $pending['bytes'],
                'origin' => 'Hochgeladene Datei',
                'third_party' => true,
                'trust' => null,
                'offer_stage_only' => !$pending['update'],
            ]);
        }

        $pluginId = (string) ($_GET['plugin'] ?? '');

        if ($kind === 'install') {
            $plugin = $registry->get($pluginId);
            if ($plugin === null) return 'Unbekanntes Plugin.';
            if (PluginLoader::isInstalled($plugin)) return "„{$plugin->manifest->name}“ ist bereits installiert.";
            return $this->manifestConfirmation($plugin->manifest, [
                'kind' => 'install',
                'action' => 'install',
                'fields' => ['plugin_id' => $plugin->id()],
                'update' => false,
                'installed_version' => null,
                'sha256' => '',
                'bytes' => 0,
                'origin' => 'Liegt in plugins/' . basename($plugin->directory) . '/',
                'third_party' => true,
                'trust' => null,
                'offer_stage_only' => false,
            ]);
        }

        if ($kind === 'catalog' || $kind === 'update') {
            $entry = $catalog->find($pluginId);
            if ($entry === null) return 'Plugin wurde im aktuellen Katalog nicht gefunden.';
            if (!($entry['installable'] ?? false)) return 'Installation ist gesperrt: Download oder SHA256-Pin fehlt.';
            $existing = $registry->get($pluginId);
            $isUpdate = $kind === 'update';
            if ($isUpdate && $existing === null) return 'Zu aktualisierendes Plugin ist nicht installiert.';
            if (!$isUpdate && $existing !== null) return "„{$entry['name']}“ liegt bereits in plugins/.";

            return [
                'kind' => $kind,
                'action' => $isUpdate ? 'catalog_update' : 'catalog_install',
                'fields' => ['plugin_id' => (string) $entry['slug']],
                'id' => (string) $entry['slug'],
                'name' => (string) $entry['name'],
                'version' => (string) $entry['version'],
                'vendor' => (string) (($entry['publisher'] ?? '') !== '' ? $entry['publisher'] : 'Nicht angegeben'),
                'description' => (string) ($entry['description'] ?? ''),
                'requires' => null,
                'depends' => [],
                'permissions' => [],
                'update' => $isUpdate,
                'installed_version' => $existing?->manifest->version,
                'sha256' => (string) $entry['sha256'],
                'bytes' => 0,
                'origin' => $this->sourceLabel((string) ($entry['source_url'] ?? ''), (string) $entry['zip_url']),
                'source_url' => $this->httpsUrl((string) (($entry['source_url'] ?? '') !== '' ? $entry['source_url'] : $entry['zip_url'])),
                'third_party' => CatalogClient::isThirdParty($entry),
                'trust' => (string) $entry['trust'],
                'offer_stage_only' => !$isUpdate,
            ];
        }

        return 'Unbekannter Bestätigungsschritt.';
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function manifestConfirmation(PluginManifest $manifest, array $extra): array
    {
        return $extra + [
            'id' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'vendor' => $manifest->vendor,
            'description' => '',
            'requires' => $manifest->hostRequire,
            'depends' => $manifest->depends,
            'permissions' => $manifest->permissions,
            'source_url' => '',
        ];
    }

    /** Nur HTTPS-Links landen im href, alles andere bleibt Text. */
    private function httpsUrl(string $url): string
    {
        return preg_match('#^https://[^\s"<>]+$#i', $url) === 1 ? $url : '';
    }

    private function sourceLabel(string $sourceUrl, string $zipUrl): string
    {
        $url = $sourceUrl !== '' ? $sourceUrl : $zipUrl;
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = explode('/', $path);
        if ($host === 'github.com' && count($segments) >= 2) {
            return 'GitHub · ' . $segments[0] . '/' . $segments[1];
        }
        return $host !== '' ? $host : 'Unbekannt';
    }

    // ── Upload ────────────────────────────────────────────────────────

    /** @return array{0:string,1:string} */
    private function handleUpload(?Request $request, CatalogInstaller $installer): array
    {
        $file = $request?->files[self::UPLOAD_FIELD] ?? $_FILES[self::UPLOAD_FIELD] ?? null;
        if (!is_array($file)) return ['Bitte ein Plugin-ZIP auswählen.', 'warn'];

        $uploadDir = dirname(__DIR__, 4) . '/storage/cache/plugin-uploads';
        try {
            $stored = FileUpload::store($file, $uploadDir, CatalogInstaller::MAX_DOWNLOAD_BYTES, self::ZIP_TYPES);
        } catch (UploadException $e) {
            return ['Upload abgelehnt: ' . $e->getMessage() . '. Erwartet wird ein ZIP-Archiv bis 50 MB.', 'danger'];
        }

        try {
            $pending = $installer->stageUpload($stored['pfad']);
        } catch (\Throwable $e) {
            return ['Plugin-ZIP abgelehnt: ' . $e->getMessage(), 'danger'];
        } finally {
            @unlink($stored['pfad']);
        }

        $_SESSION[self::UPLOAD_SESSION_KEY][$pending['token']] = time();
        $this->audit('Plugin hochgeladen', $pending['manifest']->name . ' ' . $pending['manifest']->version . ', wartet auf Bestätigung', [
            'plugin_id' => $pending['manifest']->id,
            'version' => $pending['manifest']->version,
            'source' => 'upload',
            'sha256' => $pending['sha256'],
        ]);

        $this->redirect('settings/system/plugins?confirm=upload&token=' . rawurlencode($pending['token']));
    }

    /** @return array{0:string,1:string} */
    private function handleUploadCommit(CatalogInstaller $installer, PluginRepository $repository): array
    {
        $token = (string) ($_POST['upload_token'] ?? '');
        if (!$this->ownsUpload($token)) return ['Der Upload ist nicht mehr vorhanden. Bitte die Datei erneut hochladen.', 'warn'];
        if (!$this->riskAccepted()) {
            return ['Bitte bestätige den Hinweis zu Plugins von Drittanbietern, bevor du installierst.', 'warn'];
        }
        $update = ($_POST['expect_update'] ?? '0') === '1';
        $installNow = ($_POST['install_now'] ?? '') === '1';

        try {
            $pending = $installer->pendingUpload($token);
            $plugin = $installer->commitUpload($token, $update);
        } catch (\Throwable $e) {
            return ['Installation abgebrochen: ' . $e->getMessage(), 'danger'];
        } finally {
            $this->forgetUpload($token);
        }

        $context = [
            'plugin_id' => $plugin->id(),
            'version' => $plugin->manifest->version,
            'source' => 'upload',
            'sha256' => $pending['sha256'],
        ];
        if ($update) {
            $this->audit('Plugin aktualisiert', $plugin->manifest->name . ' auf ' . $plugin->manifest->version . ' (Upload)', $context);
            return $this->afterUpdate($plugin);
        }
        $this->audit('Plugin bereitgestellt', $plugin->manifest->name . ' ' . $plugin->manifest->version . ' (Upload)', $context);
        if (!$installNow) {
            return ["„{$plugin->manifest->name}“ wurde geprüft und liegt inaktiv bereit. Installieren kannst du es in der Liste.", 'ok'];
        }
        return $this->installPlugin($plugin, $repository, 'upload');
    }

    /** @return array{0:string,1:string} */
    private function handleUploadDiscard(CatalogInstaller $installer): array
    {
        $token = (string) ($_POST['upload_token'] ?? '');
        if ($this->ownsUpload($token)) {
            try {
                $pending = $installer->pendingUpload($token);
                $this->audit('Plugin-Upload verworfen', $pending['manifest']->name . ' ' . $pending['manifest']->version, [
                    'plugin_id' => $pending['manifest']->id,
                    'version' => $pending['manifest']->version,
                    'source' => 'upload',
                ]);
            } catch (\Throwable) {
                // ungültig geworden, wird trotzdem entfernt
            }
            try {
                $installer->discardUpload($token);
            } catch (\Throwable) {
                // schon weg; der Aufräumlauf erwischt Reste
            }
        }
        $this->forgetUpload($token);
        return ['Der Upload wurde verworfen. Es wurde nichts installiert.', 'ok'];
    }

    private function ownsUpload(string $token): bool
    {
        $uploads = $_SESSION[self::UPLOAD_SESSION_KEY] ?? [];
        return $token !== '' && is_array($uploads) && isset($uploads[$token]);
    }

    private function forgetUpload(string $token): void
    {
        if (isset($_SESSION[self::UPLOAD_SESSION_KEY]) && is_array($_SESSION[self::UPLOAD_SESSION_KEY])) {
            unset($_SESSION[self::UPLOAD_SESSION_KEY][$token]);
        }
    }

    private function riskAccepted(): bool
    {
        return ($_POST['accept_risk'] ?? '') === '1';
    }

    /**
     * Kleinere Grenze aus upload_max_filesize und post_max_size in Bytes,
     * damit die Dropzone nicht mehr verspricht, als PHP annimmt.
     */
    private function phpUploadLimit(): int
    {
        $limit = CatalogInstaller::MAX_DOWNLOAD_BYTES;
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $value = trim((string) ini_get($key));
            if ($value === '' || $value === '0') continue;
            $number = (int) $value;
            $bytes = match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 * 1024 * 1024,
                'm' => $number * 1024 * 1024,
                'k' => $number * 1024,
                default => $number,
            };
            if ($bytes > 0) $limit = min($limit, $bytes);
        }
        return $limit;
    }

    // ── Katalog ───────────────────────────────────────────────────────

    /** @return array{0:string,1:string} */
    private function handleCatalogStage(
        string $pluginId,
        bool $update,
        CatalogClient $catalog,
        CatalogInstaller $installer,
        PluginRepository $repository,
    ): array {
        $entry = $catalog->find($pluginId);
        if ($entry === null) return ['Plugin wurde im aktuellen Katalog nicht gefunden.', 'danger'];
        if (!($entry['installable'] ?? false)) return ['Installation ist gesperrt: Download oder SHA256-Pin fehlt.', 'warn'];
        if (CatalogClient::isThirdParty($entry) && !$this->riskAccepted()) {
            return ['Bitte bestätige den Hinweis zu Plugins von Drittanbietern, bevor du installierst.', 'warn'];
        }
        $installNow = !$update && ($_POST['install_now'] ?? '') === '1';

        try {
            $plugin = $installer->stage($entry, $update);
        } catch (\Throwable $e) {
            return ['Katalog-Installation abgebrochen: ' . $e->getMessage(), 'danger'];
        }

        $context = [
            'plugin_id' => $plugin->id(),
            'version' => $plugin->manifest->version,
            'source' => 'catalog',
            'sha256' => (string) $entry['sha256'],
            'trust' => (string) $entry['trust'],
        ];
        if ($update) {
            $this->audit('Plugin aktualisiert', $plugin->manifest->name . ' auf ' . $plugin->manifest->version . ' (Katalog)', $context);
            return $this->afterUpdate($plugin);
        }
        $this->audit('Plugin bereitgestellt', $plugin->manifest->name . ' ' . $plugin->manifest->version . ' (Katalog)', $context);
        if (!$installNow) {
            return ["„{$plugin->manifest->name}“ wurde geprüft und heruntergeladen. Installieren kannst du es in der Liste.", 'ok'];
        }
        return $this->installPlugin($plugin, $repository, 'catalog');
    }

    /**
     * Nach einem Update: Migrationen eines installierten Plugins direkt
     * ausführen, Aktivierungszustand bleibt.
     *
     * @return array{0:string,1:string}
     */
    private function afterUpdate(Plugin $plugin): array
    {
        if (PluginLoader::isInstalled($plugin) && ($error = $this->runMigrations()) !== null) {
            return ["„{$plugin->manifest->name}“ wurde auf {$plugin->manifest->version} aktualisiert, aber der Migrationslauf meldete: {$error}", 'warn'];
        }
        return ["„{$plugin->manifest->name}“ wurde auf {$plugin->manifest->version} aktualisiert. Der Aktivierungszustand bleibt erhalten.", 'ok'];
    }

    /** @return array{0:string,1:string} */
    private function handleRemove(
        string $pluginId,
        PluginRegistry $registry,
        PluginRepository $repository,
        CatalogInstaller $installer,
    ): array {
        $plugin = $registry->get($pluginId);
        if ($plugin === null) return ['Unbekanntes Plugin.', 'danger'];
        if (PluginLoader::isBundled($pluginId)) return ['Mitgelieferte Plugins können nicht entfernt werden.', 'warn'];
        // Ein nie installiertes Plugin lief nie, auch wenn sein Manifest
        // default_enabled setzt; das darf ohne Deaktivieren weg.
        if (PluginLoader::isInstalled($plugin) && in_array($pluginId, $repository->enabledIds(), true)) {
            return ['Plugin muss vor dem Entfernen deaktiviert werden.', 'warn'];
        }
        try {
            $installer->remove($pluginId);
        } catch (\Throwable $e) {
            return ['Plugin konnte nicht entfernt werden: ' . $e->getMessage(), 'danger'];
        }
        $this->audit('Plugin entfernt', $plugin->manifest->name . ' ' . $plugin->manifest->version . ', Tabellen und Daten bleiben', [
            'plugin_id' => $pluginId,
            'version' => $plugin->manifest->version,
        ]);
        return ["Plugin-Dateien von „{$plugin->manifest->name}“ wurden entfernt. Tabellen und Daten bleiben erhalten.", 'ok'];
    }

    private function catalogClient(): CatalogClient
    {
        $override = trim((string) ($_ENV['HUB_PLUGINS_URL'] ?? getenv('HUB_PLUGINS_URL') ?: ''));
        $hubUrl = trim((string) (new ConfigManager())->get('HUB_URL', 'https://hub.emergencyforge.de'));
        if ($hubUrl === '') $hubUrl = 'https://hub.emergencyforge.de';
        $endpoint = $override !== '' ? $override : rtrim($hubUrl, '/') . '/v1/plugins';
        $cache = dirname(__DIR__, 4) . '/storage/cache/plugin-catalog.json';
        return new CatalogClient($endpoint, $cache);
    }

    private function catalogInstaller(): CatalogInstaller
    {
        $root = dirname(__DIR__, 4);
        return new CatalogInstaller(
            PluginLoader::pluginsDir(),
            $root . '/storage/cache',
            PluginLoader::ignisVersion(),
        );
    }

    // ── Installation und Aktivierung ──────────────────────────────────

    /**
     * Startet die manuelle Installation eines nicht mitgelieferten Plugins,
     * das bereits inert in plugins/ liegt. Die Bestätigungsseite verlangt
     * dafür das Häkchen zum Drittanbieter-Hinweis.
     *
     * @return array{0: string, 1: string}
     */
    private function handleInstall(string $pluginId, PluginRegistry $registry, PluginRepository $repository): array
    {
        $plugin = $registry->get($pluginId);
        if ($plugin === null) {
            return ['Unbekanntes Plugin.', 'danger'];
        }
        if (PluginLoader::isInstalled($plugin)) {
            return ["„{$plugin->manifest->name}\u{201c} ist bereits installiert.", 'warn'];
        }
        if (!$this->riskAccepted()) {
            return ['Bitte bestätige den Hinweis zu Plugins von Drittanbietern, bevor du installierst.', 'warn'];
        }
        return $this->installPlugin($plugin, $repository, 'manual');
    }

    /**
     * Marker schreiben, Migrationen anstoßen, aktivieren. Der Aufruf ist
     * die bewusste Admin-Entscheidung, fremden Code auszuführen. Vorher
     * bleibt ein hochkopiertes Plugin vollständig inert.
     *
     * @return array{0: string, 1: string}
     */
    private function installPlugin(Plugin $plugin, PluginRepository $repository, string $source): array
    {
        if (!PluginLoader::markInstalled($plugin)) {
            return ['Installations-Marker konnte nicht geschrieben werden. Bitte Schreibrechte im Plugin-Verzeichnis prüfen.', 'danger'];
        }
        $this->audit('Plugin installiert', $plugin->manifest->name . ' ' . $plugin->manifest->version . ' von ' . $plugin->manifest->vendor, [
            'plugin_id' => $plugin->id(),
            'version' => $plugin->manifest->version,
            'source' => $source,
        ]);

        // Migrationen des frisch installierten Plugins direkt ausführen,
        // statt auf den nächsten Request zu warten.
        if (($error = $this->runMigrations()) !== null) {
            return [
                "„{$plugin->manifest->name}\u{201c} wurde installiert, aber der Migrationslauf meldete: " . $error,
                'warn',
            ];
        }

        $repository->syncDiscovered([$plugin->id() => $plugin]);
        $repository->setEnabled($plugin->id(), true);
        return ["„{$plugin->manifest->name}\u{201c} wurde installiert und aktiviert.", 'ok'];
    }

    private function runMigrations(): ?string
    {
        try {
            (new \App\Database\AutoMigrator(app(\PDO::class)))->runIfNeeded();
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Schaltet ein Plugin um und liefert [Meldung, Typ] für die Anzeige.
     *
     * @return array{0: string, 1: string}
     */
    private function handleToggle(string $pluginId, PluginRegistry $registry, PluginRepository $repository): array
    {
        $plugin = $registry->get($pluginId);
        if ($plugin === null) {
            return ['Unbekanntes Plugin.', 'danger'];
        }
        if (!PluginLoader::isInstalled($plugin)) {
            return ["„{$plugin->manifest->name}\u{201c} ist noch nicht installiert. Bitte zuerst die Installation starten.", 'warn'];
        }

        $enabledIds = $repository->enabledIds();
        $isEnabled  = in_array($pluginId, $enabledIds, true);
        $context    = ['plugin_id' => $pluginId, 'version' => $plugin->manifest->version];

        if ($isEnabled) {
            if (!$plugin->manifest->removable) {
                return ["„{$plugin->manifest->name}\u{201c} ist fester Bestandteil und kann nicht deaktiviert werden.", 'warn'];
            }
            $dependents = $this->enabledDependents($pluginId, $registry, $enabledIds);
            if ($dependents !== []) {
                return [
                    "„{$plugin->manifest->name}\u{201c} wird noch benötigt von: " . implode(', ', $dependents) . '. Bitte zuerst dort deaktivieren.',
                    'warn',
                ];
            }
            $repository->setEnabled($pluginId, false);
            $this->audit('Plugin deaktiviert', $plugin->manifest->name, $context);
            return ["„{$plugin->manifest->name}\u{201c} wurde deaktiviert. Daten und Tabellen bleiben erhalten.", 'ok'];
        }

        // Aktivieren: fehlende Abhängigkeiten benennen statt still zu scheitern.
        $missing = [];
        foreach ($plugin->manifest->depends as $dep) {
            if (!in_array($dep, $enabledIds, true)) {
                $depPlugin = $registry->get($dep);
                $missing[] = $depPlugin?->manifest->name ?? $dep;
            }
        }
        if ($missing !== []) {
            return [
                "„{$plugin->manifest->name}\u{201c} benötigt zuerst: " . implode(', ', $missing) . '.',
                'warn',
            ];
        }

        $repository->setEnabled($pluginId, true);
        $this->audit('Plugin aktiviert', $plugin->manifest->name, $context);
        return ["„{$plugin->manifest->name}\u{201c} wurde aktiviert.", 'ok'];
    }

    /**
     * Namen aller AKTIVIERTEN Plugins, die auf $pluginId angewiesen sind.
     *
     * @param list<string> $enabledIds
     * @return list<string>
     */
    private function enabledDependents(string $pluginId, PluginRegistry $registry, array $enabledIds): array
    {
        $names = [];
        foreach ($registry->all() as $id => $plugin) {
            if ($id === $pluginId || !in_array($id, $enabledIds, true)) {
                continue;
            }
            if (in_array($pluginId, $plugin->manifest->depends, true)) {
                $names[] = $plugin->manifest->name;
            }
        }
        return $names;
    }

    /** @param array<string,scalar|null> $context */
    private function audit(string $action, string $details, array $context): void
    {
        (new AuditLogger())->log((int) ($_SESSION['userid'] ?? 0), $action, $details, 'Plugins', 1, $context);
    }
}
