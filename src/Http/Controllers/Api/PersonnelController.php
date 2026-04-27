<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\UserHelper;
use App\Http\Request;
use App\Http\Requests\Personnel\UpdateProfileRequest;
use App\Http\Response;
use App\Logging\Logger;
use App\Personnel\PersonalLogManager;
use DateTime;
use PDO;

/**
 * Personnel-Admin-API — Dienstnummer-Checks, Invite-Codes, Profil-Updates,
 * Profilbild-Uploads. Alle Methoden setzen eingeloggten Admin voraus;
 * die konkrete Permission hängt pro Endpoint ab (`personnel.edit`,
 * `users.create`).
 */
final class PersonnelController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * POST /api/personnel/check-dienstnr
     *
     * JSON: { "dienstnr": "..." }
     * Antwort: { "available": bool, ... }
     */
    public function checkDienstnr(Request $request): Response
    {
        $input = $request->json();
        if (!is_array($input) || empty($input['dienstnr'])) {
            return Response::json(['error' => 'Keine Dienstnummer angegeben'], 200);
        }

        $dienstnr = trim((string) $input['dienstnr']);
        if (!preg_match('/^(?=.*[0-9])[A-Za-z0-9\-]+$/', $dienstnr)) {
            return Response::json([
                'error' => 'Ungültiges Format für Dienstnummer. Muss mindestens eine Zahl enthalten.',
            ], 200);
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM intra_mitarbeiter WHERE dienstnr = :dienstnr"
            );
            $stmt->execute(['dienstnr' => $dienstnr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $available = ((int) $row['count']) === 0;

            return Response::json([
                'available' => $available,
                'dienstnr'  => $dienstnr,
                'message'   => $available ? 'Dienstnummer verfügbar' : 'Dienstnummer bereits vergeben',
            ]);
        } catch (\Throwable $e) {
            Logger::error('Personnel: check-dienstnr Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Serverfehler beim Prüfen der Dienstnummer'], 500);
        }
    }

    /**
     * POST /api/personnel/check-dienstnr-legacy
     *
     * Liefert Plain-Text ("exists" | "not_exists" | "error"). Wird vom
     * Legacy-Frontend (direkt `fetch(...).then(r => r.text())`) genutzt.
     * Ein späteres Frontend-Update kann auf `check-dienstnr` umschwenken.
     */
    public function checkDienstnrLegacy(Request $request): Response
    {
        $dienstnr = trim((string) ($request->post['dienstnr'] ?? ''));
        if ($dienstnr === '' || !preg_match('/^(?=.*[0-9])[A-Za-z0-9\-]+$/', $dienstnr)) {
            return Response::text('error');
        }

        try {
            $excludeId = isset($request->post['exclude_id']) ? (int) $request->post['exclude_id'] : 0;

            if ($excludeId > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM intra_mitarbeiter WHERE dienstnr = :dienstnr AND id != :exclude_id"
                );
                $stmt->execute(['dienstnr' => $dienstnr, 'exclude_id' => $excludeId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM intra_mitarbeiter WHERE dienstnr = :dienstnr"
                );
                $stmt->execute(['dienstnr' => $dienstnr]);
            }

            return Response::text(((int) $stmt->fetchColumn()) > 0 ? 'exists' : 'not_exists');
        } catch (\Throwable $e) {
            Logger::error('Personnel: check-dienstnr-legacy Fehler', ['error' => $e->getMessage()]);
            return Response::text('error');
        }
    }

    /**
     * POST /api/personnel/generate-invite
     *
     * JSON: { "label": "..." }
     */
    public function generateInvite(Request $request): Response
    {
        $data  = $request->json();
        $label = isset($data['label']) ? trim((string) $data['label']) : '';

        if ($label === '') {
            return Response::json(['success' => false, 'message' => 'Label is required'], 400);
        }

        try {
            $code = bin2hex(random_bytes(8));
            $this->pdo->prepare(
                "INSERT INTO intra_registration_codes (code, label, created_by) VALUES (:code, :label, :created_by)"
            )->execute([
                'code'       => $code,
                'label'      => $label,
                'created_by' => $_SESSION['userid'] ?? null,
            ]);

            $sysUrl = (defined('SYSTEM_URL') && SYSTEM_URL !== '' && SYSTEM_URL !== 'CHANGE_ME')
                ? rtrim((string) SYSTEM_URL, '/') : '';
            if ($sysUrl && !preg_match('#^https?://#i', $sysUrl)) {
                $sysUrl = 'https://' . $sysUrl;
            }
            $baseUrl = $sysUrl ?: (
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            );
            $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
            $inviteUrl = $baseUrl . $base . 'invite?code=' . $code;

            return Response::json([
                'success'   => true,
                'inviteUrl' => $inviteUrl,
                'code'      => $code,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Personnel: generate-invite Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * POST /api/personnel/update-profile
     *
     * JSON-Body mit Mitarbeiter-Stammdaten. Legt Audit-Einträge bei
     * Dienstgrad-, Quali- und Basisdaten-Änderungen an.
     */
    public function updateProfile(Request $request): Response
    {
        // Deklarative Validation (Pflichtfelder, Format, Längen, Typ-Cast).
        // Dienstnr-Uniqueness bleibt unten im Controller, weil sie DB-Zugriff
        // und den aktuellen Datensatz-Kontext braucht.
        $data = UpdateProfileRequest::validate((array) $request->json());

        $id          = $data['id'];
        $fullname    = $data['fullname'];
        $gebdatum    = $data['gebdatum'];
        $dienstgrad  = $data['dienstgrad'];
        $discordtag  = $data['discordtag'];
        $telefonnr   = $data['telefonnr'];
        $dienstnr    = $data['dienstnr'];
        $qualird     = $data['qualird'];
        $qualifw2    = $data['qualifw2'];
        $geschlecht  = $data['geschlecht'];
        $zusatzqual  = $data['zusatzqual'];
        $pfp         = $data['pfp'];
        $charakterid = (defined('CHAR_ID') && CHAR_ID) ? $data['charakterid'] : '';

        if ($dienstnr !== '') {
            $checkStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM intra_mitarbeiter WHERE dienstnr = :dienstnr AND id != :id"
            );
            $checkStmt->execute(['dienstnr' => $dienstnr, 'id' => $id]);
            if ($checkStmt->fetchColumn() > 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'Diese Dienstnummer ist bereits vergeben',
                ], 400);
            }
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM intra_mitarbeiter WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                return Response::json(['success' => false, 'message' => 'Mitarbeiter nicht gefunden'], 404);
            }

            $userHelper = new UserHelper($this->pdo);
            $edituser   = $userHelper->getCurrentUserFullnameForAction();
            $logManager = new PersonalLogManager($this->pdo);
            $changes    = [];

            // Dienstgrad-Change mit Audit
            if ((int) $current['dienstgrad'] !== $dienstgrad) {
                $this->pdo->prepare("UPDATE intra_mitarbeiter SET dienstgrad = :dg WHERE id = :id")
                    ->execute(['dg' => $dienstgrad, 'id' => $id]);

                $oldDg = $this->pdo->prepare("SELECT name FROM intra_mitarbeiter_dienstgrade WHERE id = ?");
                $oldDg->execute([(int) $current['dienstgrad']]);
                $newDg = $this->pdo->prepare("SELECT name FROM intra_mitarbeiter_dienstgrade WHERE id = ?");
                $newDg->execute([$dienstgrad]);
                $logManager->logRankChange($id, $oldDg->fetchColumn(), $newDg->fetchColumn(), $edituser);
                $changes[] = 'dienstgrad';
            }

            // RD-Quali-Change
            if ((int) $current['qualird'] !== $qualird) {
                $this->pdo->prepare("UPDATE intra_mitarbeiter SET qualird = :q WHERE id = :id")
                    ->execute(['q' => $qualird, 'id' => $id]);

                $oldQ = $this->pdo->prepare("SELECT name FROM intra_mitarbeiter_rdquali WHERE id = ?");
                $oldQ->execute([(int) $current['qualird']]);
                $newQ = $this->pdo->prepare("SELECT name FROM intra_mitarbeiter_rdquali WHERE id = ?");
                $newQ->execute([$qualird]);
                $logManager->logQualificationChange($id, 'RD', $oldQ->fetchColumn(), $newQ->fetchColumn(), $edituser);
                $changes[] = 'qualird';
            }

            // FW-Quali-Change
            if ((int) $current['qualifw2'] !== $qualifw2) {
                $this->pdo->prepare("UPDATE intra_mitarbeiter SET qualifw2 = :q WHERE id = :id")
                    ->execute(['q' => $qualifw2, 'id' => $id]);

                $oldQ = $this->pdo->prepare("SELECT name FROM intra_mitarbeiter_fwquali WHERE id = ?");
                $oldQ->execute([(int) $current['qualifw2']]);
                $newQ = $this->pdo->prepare("SELECT name FROM intra_mitarbeiter_fwquali WHERE id = ?");
                $newQ->execute([$qualifw2]);
                $logManager->logQualificationChange($id, 'FW', $oldQ->fetchColumn(), $newQ->fetchColumn(), $edituser);
                $changes[] = 'qualifw2';
            }

            // PFP-Handling: leerer pfp-String bedeutet "nicht geändert"
            // (Legacy-Bug-Fix — Inline-Edit-Save würde sonst das Bild zurücksetzen)
            if ($pfp === '') {
                $pfp = $current['pfp'] ?? '';
            }
            if ($pfp === '') {
                $pfp = '/assets/img/empty_user.png';
            }

            $baseDataChanged = (
                $current['fullname'] !== $fullname ||
                $current['gebdatum'] !== $gebdatum ||
                $current['discordtag'] !== $discordtag ||
                $current['telefonnr'] !== $telefonnr ||
                $current['dienstnr'] !== $dienstnr ||
                (int) $current['geschlecht'] !== $geschlecht ||
                ($current['zusatz'] ?? '') !== $zusatzqual ||
                ($current['pfp'] ?? '') !== $pfp ||
                (defined('CHAR_ID') && CHAR_ID && ($current['charakterid'] ?? '') !== $charakterid)
            );

            if ($baseDataChanged) {
                $setClauses = [
                    'fullname = :fullname', 'gebdatum = :gebdatum', 'discordtag = :discordtag',
                    'telefonnr = :telefonnr', 'dienstnr = :dienstnr', 'geschlecht = :geschlecht',
                    'zusatz = :zusatzqual', 'pfp = :pfp',
                ];
                $params = [
                    'fullname'   => $fullname, 'gebdatum' => $gebdatum, 'discordtag' => $discordtag,
                    'telefonnr'  => $telefonnr, 'dienstnr' => $dienstnr, 'geschlecht' => $geschlecht,
                    'zusatzqual' => $zusatzqual, 'pfp' => $pfp, 'id' => $id,
                ];
                if (defined('CHAR_ID') && CHAR_ID) {
                    $setClauses[]        = 'charakterid = :charakterid';
                    $params['charakterid'] = $charakterid;
                }

                $this->pdo->prepare(
                    "UPDATE intra_mitarbeiter SET " . implode(', ', $setClauses) . " WHERE id = :id"
                )->execute($params);
                $logManager->logProfileModification($id, $edituser);
                $changes[] = 'basedata';
            }

            // Aktuelle Daten für die Response holen (mit Joins)
            $stmt = $this->pdo->prepare("
                SELECT m.*,
                    dg.name as dg_name, dg.name_m as dg_name_m, dg.name_w as dg_name_w, dg.badge as dg_badge,
                    rd.name as rd_name, rd.name_m as rd_name_m, rd.name_w as rd_name_w, rd.none as rd_none,
                    fw.shortname as fw_shortname, fw.none as fw_none
                FROM intra_mitarbeiter m
                LEFT JOIN intra_mitarbeiter_dienstgrade dg ON m.dienstgrad = dg.id
                LEFT JOIN intra_mitarbeiter_rdquali rd ON m.qualird = rd.id
                LEFT JOIN intra_mitarbeiter_fwquali fw ON m.qualifw2 = fw.id
                WHERE m.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);

            $g = (int) $updated['geschlecht'];
            $dgText = $g === 0 ? $updated['dg_name_m'] : ($g === 1 ? $updated['dg_name_w'] : $updated['dg_name']);
            $rdText = $g === 0 ? $updated['rd_name_m'] : ($g === 1 ? $updated['rd_name_w'] : $updated['rd_name']);
            $geschlechtText = $g === 0 ? 'Herr' : ($g === 1 ? 'Frau' : 'Divers');

            $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';

            return Response::json([
                'success' => true,
                'message' => empty($changes) ? 'Keine Änderungen' : 'Profil gespeichert',
                'changes' => $changes,
                'display' => [
                    'fullname'       => $updated['fullname'],
                    'gebdatum'       => (new DateTime($updated['gebdatum']))->format('d.m.Y'),
                    'discordtag'     => $updated['discordtag'] ?? 'N. hinterlegt',
                    'telefonnr'      => $updated['telefonnr'],
                    'dienstnr'       => $updated['dienstnr'],
                    'geschlechtText' => $geschlechtText,
                    'zusatz'         => $updated['zusatz'] ?? 'Keine',
                    'einstdatum'     => (new DateTime($updated['einstdatum']))->format('d.m.Y'),
                    'charakterid'    => $updated['charakterid'] ?? '',
                    'dgText'         => $dgText,
                    'dgBadge'        => $updated['dg_badge'] ?? '',
                    'rdText'         => $rdText,
                    'rdNone'         => (bool) $updated['rd_none'],
                    'fwShortname'    => $updated['fw_shortname'] ?? '',
                    'fwNone'         => (bool) $updated['fw_none'],
                    'pfp'            => !empty($updated['pfp']) ? $updated['pfp'] : $base . 'assets/img/empty_user.png',
                    'profileName'    => $geschlechtText . ' ' . $updated['fullname'],
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('Personnel: update-profile Fehler', ['error' => $e->getMessage(), 'id' => $id]);
            return Response::json(['success' => false, 'message' => 'Serverfehler: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/personnel/upload-pfp (multipart/form-data)
     */
    public function uploadPfp(Request $request): Response
    {
        $mitarbeiterId = (int) ($request->post['id'] ?? 0);
        if ($mitarbeiterId <= 0) {
            return Response::json(['success' => false, 'message' => 'Ungültige Mitarbeiter-ID'], 400);
        }

        $fileInfo = $request->files['pfp'] ?? null;
        if (!is_array($fileInfo) || ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return Response::json(['success' => false, 'message' => 'Keine Datei hochgeladen'], 400);
        }

        if (($fileInfo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return Response::json(['success' => false, 'message' => 'Upload-Fehler'], 400);
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ((int) $fileInfo['size'] > $maxSize) {
            return Response::json(['success' => false, 'message' => 'Datei zu groß (max. 2 MB)'], 400);
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $fileInfo['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            return Response::json([
                'success' => false,
                'message' => 'Ungültiger Dateityp. Erlaubt: PNG, JPG, WebP',
            ], 400);
        }

        $storagePath = dirname(__DIR__, 4) . '/storage/profile-pictures';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $ext = match ($mimeType) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $filename   = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $storagePath . '/' . $filename;

        if (!move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
            return Response::json([
                'success' => false,
                'message' => 'Datei konnte nicht gespeichert werden',
            ], 500);
        }

        // Altes Profilbild löschen falls vorhanden
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        try {
            $stmt = $this->pdo->prepare("SELECT pfp FROM intra_mitarbeiter WHERE id = :id");
            $stmt->execute(['id' => $mitarbeiterId]);
            $oldPfp = $stmt->fetchColumn();

            if ($oldPfp && str_starts_with((string) $oldPfp, $base . 'storage/profile-pictures/')) {
                $oldFile = dirname(__DIR__, 4) . '/' . str_replace($base, '', (string) $oldPfp);
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
        } catch (\Throwable) {
            // non-critical, weiter machen
        }

        $relativePath = $base . 'storage/profile-pictures/' . $filename;

        try {
            $this->pdo->prepare("UPDATE intra_mitarbeiter SET pfp = :pfp WHERE id = :id")
                ->execute(['pfp' => $relativePath, 'id' => $mitarbeiterId]);

            return Response::json([
                'success' => true,
                'message' => 'Profilbild aktualisiert',
                'url'     => $relativePath,
            ]);
        } catch (\Throwable $e) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            Logger::error('Personnel: upload-pfp DB-Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler'], 500);
        }
    }
}
