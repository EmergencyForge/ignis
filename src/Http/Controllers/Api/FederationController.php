<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Api\ApiResponse;
use App\Federation\FederationMiddleware;
use App\Federation\FederationPairingService;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use PDO;

/**
 * Federation-Endpoints — Server-to-Server-API für verlinkte intraRP-
 * Instanzen.
 *
 * Auth läuft NICHT über die Router-Middleware, sondern über
 * `FederationMiddleware::authenticate($pdo)` intern in den Methoden.
 * Der Grund: Federation nutzt X-Federation-Key-Header mit DB-gespeicherten
 * pro-Instanz-Keys (nicht den globalen `API_KEY`), und die Authentifizierung
 * liefert gleichzeitig das `$link`-Objekt mit den Capabilities der
 * anfragenden Instanz.
 *
 * Der bestehende ApiResponse-Wrapper wird hier weiterhin benutzt, weil
 * die Federation-Callers historisch exakt dessen Response-Shape erwarten.
 * Der Controller gibt am Ende `Response::empty()` zurück, damit die
 * Router-Pipeline nichts mehr ausgibt.
 */
final class FederationController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * GET /api/federation/handshake
     *
     * Instanz-Info für Verbindungs-Verifizierung.
     */
    public function handshake(Request $request): Response
    {
        $link = FederationMiddleware::authenticate($this->pdo);

        $instanceId   = FederationMiddleware::config('FEDERATION_INSTANCE_ID');
        $instanceName = FederationMiddleware::config('FEDERATION_INSTANCE_NAME')
            ?: FederationMiddleware::config('SYSTEM_NAME', 'intraRP');

        $capabilities = [];
        if ($link['provide_personnel']) $capabilities[] = 'personnel';
        if ($link['provide_enotf'])     $capabilities[] = 'enotf';
        if ($link['provide_fire'])      $capabilities[] = 'fire';

        ApiResponse::success([
            'instance_id'   => $instanceId,
            'instance_name' => $instanceName,
            'capabilities'  => $capabilities,
        ]);

        return Response::empty();
    }

    /**
     * POST /api/federation/pair
     *
     * Finalisiert einen Pairing-Handshake. Wird von der initiierenden
     * Instanz aufgerufen, nachdem sie unseren Connection-Token geparst hat.
     */
    public function pair(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            ApiResponse::error('Methode nicht erlaubt', 405);
            return Response::empty();
        }

        FederationMiddleware::requireEnabled();

        $input = $request->json();
        if (!is_array($input)) {
            ApiResponse::error('Ungültige JSON-Daten', 400);
            return Response::empty();
        }

        $requiredFields = ['instance_id', 'instance_name', 'instance_url', 'api_key_for_you', 'your_token_key'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                ApiResponse::error("Pflichtfeld fehlt: {$field}", 400);
                return Response::empty();
            }
        }

        $service = new FederationPairingService($this->pdo);

        try {
            // Generiere den Key, den der Initiator für Calls an UNS nutzen muss
            $keyForThem = FederationPairingService::generateApiKey();

            // Link erstellen:
            //   - outgoing = ihr Key für Calls an SIE
            //   - incoming = unser Key für Calls an UNS
            $service->createLink(
                [
                    'instance_id'   => $input['instance_id'],
                    'instance_name' => $input['instance_name'],
                    'url'           => $input['instance_url'],
                ],
                (string) $input['api_key_for_you'],
                $keyForThem
            );

            $instanceId   = $service->ensureInstanceId();
            $instanceName = FederationMiddleware::config('FEDERATION_INSTANCE_NAME')
                ?: FederationMiddleware::config('SYSTEM_NAME', 'intraRP');

            ApiResponse::success([
                'instance_id'     => $instanceId,
                'instance_name'   => $instanceName,
                'api_key_for_you' => $keyForThem,
            ]);
        } catch (\RuntimeException $e) {
            ApiResponse::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Logger::error('Federation: pair Fehler', ['error' => $e->getMessage()]);
            ApiResponse::error('Pairing fehlgeschlagen: ' . $e->getMessage(), 500);
        }

        return Response::empty();
    }

    /**
     * GET /api/federation/personnel?page=N&per_page=M
     *
     * Liefert Mitarbeiter-Liste für verlinkte Instanzen.
     */
    public function personnel(Request $request): Response
    {
        $link = FederationMiddleware::authenticate($this->pdo);
        FederationMiddleware::requireProvidePermission($link, 'personnel');

        $page    = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(500, max(1, (int) ($request->query['per_page'] ?? 100)));
        $offset  = ($page - 1) * $perPage;

        try {
            $total = (int) $this->pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter")->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT
                    m.id,
                    m.fullname,
                    m.dienstnr,
                    d.name AS dienstgrad_name,
                    d.badge AS dienstgrad_badge,
                    rd.name AS quali_rd,
                    rd.abkuerzung AS quali_rd_short,
                    fw.name AS quali_fw,
                    m.fachdienste AS quali_fd_json
                FROM intra_mitarbeiter m
                LEFT JOIN intra_mitarbeiter_dienstgrade d ON m.dienstgrad = d.id
                LEFT JOIN intra_mitarbeiter_rdquali rd ON m.qualird = rd.id
                LEFT JOIN intra_mitarbeiter_fwquali fw ON m.qualifw2 = fw.id
                ORDER BY m.fullname ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$perPage, $offset]);
            $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ApiResponse::success([
                'instance_id' => FederationMiddleware::config('FEDERATION_INSTANCE_ID'),
                'synced_at'   => date('c'),
                'data'        => $personnel,
                'pagination'  => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\PDOException $e) {
            Logger::error('Federation: personnel Fehler', ['error' => $e->getMessage()]);
            ApiResponse::error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        return Response::empty();
    }

    /**
     * GET /api/federation/enotf?since=...&page=N&per_page=M
     */
    public function enotf(Request $request): Response
    {
        $link = FederationMiddleware::authenticate($this->pdo);
        FederationMiddleware::requireProvidePermission($link, 'enotf');

        $since   = $request->query['since'] ?? null;
        $page    = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($request->query['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        try {
            $where  = "WHERE freigegeben = 1 AND hidden = 0 AND hidden_user = 0";
            $params = [];
            if ($since) {
                $where .= " AND updated_at > ?";
                $params[] = $since;
            }

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_edivi {$where}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT
                    id, enr, edatum, ezeit,
                    patname, pfname, patgebdat, geschlecht_pat,
                    einsatzort, elokation,
                    fzg_transp, fzg_na,
                    ziel_poi, ziel_adresse,
                    naca,
                    sendezeit, updated_at,
                    fahrername, fahrerquali,
                    beifahrername, beifahrerquali,
                    praktikantname, praktikantquali
                FROM intra_edivi
                {$where}
                ORDER BY updated_at ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $syncCursor = null;
            if (!empty($protocols)) {
                $syncCursor = end($protocols)['updated_at'];
            }

            ApiResponse::success([
                'instance_id' => FederationMiddleware::config('FEDERATION_INSTANCE_ID'),
                'synced_at'   => date('c'),
                'sync_cursor' => $syncCursor,
                'data'        => $protocols,
                'pagination'  => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\PDOException $e) {
            Logger::error('Federation: enotf Fehler', ['error' => $e->getMessage()]);
            ApiResponse::error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        return Response::empty();
    }

    /**
     * GET /api/federation/fire-incidents?since=...&page=N&per_page=M
     */
    public function fireIncidents(Request $request): Response
    {
        $link = FederationMiddleware::authenticate($this->pdo);
        FederationMiddleware::requireProvidePermission($link, 'fire');

        $since   = $request->query['since'] ?? null;
        $page    = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($request->query['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        try {
            $where  = "WHERE i.archived = 0";
            $params = [];
            if ($since) {
                $where .= " AND i.updated_at > ?";
                $params[] = $since;
            }

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_fire_incidents i {$where}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT
                    i.id, i.incident_number, i.keyword, i.location,
                    i.status, i.finalized,
                    i.leader_id, m.fullname AS leader_name,
                    i.owner_type, i.owner_name, i.owner_contact,
                    i.gta_x, i.gta_y, i.gta_z,
                    i.created_at, i.updated_at
                FROM intra_fire_incidents i
                LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id
                {$where}
                ORDER BY i.updated_at ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $syncCursor = null;
            if (!empty($incidents)) {
                $syncCursor = end($incidents)['updated_at'];
            }

            ApiResponse::success([
                'instance_id' => FederationMiddleware::config('FEDERATION_INSTANCE_ID'),
                'synced_at'   => date('c'),
                'sync_cursor' => $syncCursor,
                'data'        => $incidents,
                'pagination'  => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\PDOException $e) {
            Logger::error('Federation: fire-incidents Fehler', ['error' => $e->getMessage()]);
            ApiResponse::error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        return Response::empty();
    }
}
