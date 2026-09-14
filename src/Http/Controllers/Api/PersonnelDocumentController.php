<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Logging\Logger;
use App\Models\PersonnelDocument;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Was von den Dokumenten des abgelösten Canvas-Systems noch gebraucht
 * wird: sie ansehen und archivieren.
 *
 * Neue Dokumente entstehen hier nicht mehr — das macht der Editor
 * (siehe {@see \App\Http\Controllers\EditorDocumentController}). Die alten
 * Zeilen bleiben samt ihrer PDFs auf der Platte stehen, weil eine
 * ausgestellte Urkunde nicht verschwinden darf, nur weil das Werkzeug
 * gewechselt hat.
 */
final class PersonnelDocumentController
{
    /** GET /api/documents/get-document?docid=… */
    public function getDocument(Request $request): Response
    {
        if (!isset($_SESSION['userid'])) {
            return Response::json(['success' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $docid = (string) ($request->query['docid'] ?? '');
        if ($docid === '') {
            return Response::json(['success' => false, 'error' => 'docid ist erforderlich'], 400);
        }

        try {
            $row = Capsule::table('intra_mitarbeiter_dokumente as pd')
                ->leftJoin('intra_users as u', 'pd.ausstellerid', '=', 'u.discord_id')
                ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
                ->leftJoin('intra_mitarbeiter as emp', 'pd.profileid', '=', 'emp.id')
                ->select(
                    'pd.id', 'pd.docid', 'pd.type', 'pd.erhalter', 'pd.ausstellungsdatum',
                    'pd.ausstellerid', 'pd.profileid', 'pd.timestamp',
                    'emp.fullname as empfaenger_fullname',
                )
                ->selectRaw('IFNULL(pd.is_archived, 0) as is_archived')
                ->selectRaw("COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name")
                ->where('pd.docid', $docid)
                ->first();

            if ($row === null) {
                return Response::json(['success' => false, 'error' => 'Dokument nicht gefunden'], 404);
            }

            $doc = (array) $row;

            // Wer es selbst ausgestellt hat, darf es auch ohne das
            // allgemeine Leserecht ansehen.
            $discordId = $_SESSION['discordtag'] ?? null;
            $isOwn = is_string($discordId) && $discordId !== '' && (string) ($doc['ausstellerid'] ?? '') === $discordId;
            if (!$isOwn && Gate::denies('document.view')) {
                return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
            }

            $base      = defined('BASE_PATH') ? (string) BASE_PATH : '/';
            $pdfExists = is_file(dirname(__DIR__, 4) . '/storage/documents/' . basename((string) $doc['docid']) . '.pdf');
            $issued    = $doc['ausstellungsdatum'];

            return Response::json([
                'success'  => true,
                'document' => [
                    'id'                          => (int) $doc['id'],
                    'docid'                       => $doc['docid'],
                    'type'                        => (int) $doc['type'],
                    'type_label'                  => PersonnelDocument::typeLabel((int) $doc['type']),
                    'erhalter'                    => $doc['erhalter'],
                    'empfaenger_fullname'         => $doc['empfaenger_fullname'],
                    'ersteller_name'              => $doc['ersteller_name'],
                    'ausstellungsdatum'           => $issued,
                    'ausstellungsdatum_formatted' => $issued ? date('d.m.Y', (int) strtotime((string) $issued)) : '',
                    'timestamp'                   => $doc['timestamp'],
                    'is_archived'                 => (bool) $doc['is_archived'],
                    'pdf_url'                     => $base . 'storage/documents/' . $doc['docid'] . '.pdf',
                    'pdf_exists'                  => $pdfExists,
                    'profileid'                   => (int) $doc['profileid'],
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('Dokument ' . $docid . ' konnte nicht geladen werden: ' . $e->getMessage());

            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 500);
        }
    }

    /** POST /api/documents/archive — archivieren oder zurückholen. */
    public function archiveDocument(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
        }

        try {
            // Den CSRF-Token prueft CsrfMiddleware, global am Router.
            $input = $request->json();

            $docid    = (string) ($input['docid'] ?? '');
            $archived = (bool) ($input['archived'] ?? true);

            if ($docid === '') {
                return Response::json(['success' => false, 'error' => 'docid ist erforderlich'], 400);
            }

            $affected = Capsule::table('intra_mitarbeiter_dokumente')
                ->where('docid', $docid)
                ->update(['is_archived' => $archived ? 1 : 0]);

            if ($affected === 0) {
                return Response::json(['success' => false, 'error' => 'Dokument nicht gefunden'], 404);
            }

            (new AuditLogger())->log(
                (int) ($_SESSION['userid'] ?? 0),
                'Dokument ' . ($archived ? 'archiviert' : 'wiederhergestellt') . " [ID: {$docid}]",
                null,
                'Mitarbeiter',
                1,
            );

            return Response::json(['success' => true, 'archived' => $archived]);
        } catch (\Throwable $e) {
            Logger::error('Dokument archivieren fehlgeschlagen: ' . $e->getMessage());

            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 500);
        }
    }
}
