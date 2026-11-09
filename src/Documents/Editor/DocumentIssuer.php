<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Logging\Logger;
use App\Models\EditorDocument;
use App\Models\Personnel;
use App\Notifications\NotificationManager;
use App\Personnel\AccountLink;
use App\Personnel\PersonalLogManager;
use App\Policies\DocumentPolicy;
use EmergencyForge\Editor\PackageGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;

/**
 * Macht aus einem Entwurf ein ausgestelltes Dokument: Werte einfrieren,
 * Status wechseln, PDF erzeugen, Verlauf und Benachrichtigung beim
 * Mitarbeiter. Ohne Sitzung nutzbar, damit auch andere Abläufe (etwa der
 * Abschluss eines Lehrgangs) Urkunden ausstellen können.
 *
 * Statuswechsel und PDF liegen in einer Transaktion: scheitert das Rendern,
 * rollt der Statuswechsel mit zurück und der Entwurf bleibt ein Entwurf.
 * Der Wechsel selbst ist ein bedingtes UPDATE auf den Entwurfsstatus, die
 * Zeilenzahl entscheidet, wer bei zwei gleichzeitigen Klicks gewinnt.
 */
final class DocumentIssuer
{
    public function __construct(
        private readonly NotificationManager $notifications,
        private readonly PersonalLogManager $log = new PersonalLogManager(),
    ) {
    }

    /**
     * @param  ?int                  $issuerPersonnelId Mitarbeiter für die Aussteller-Platzhalter
     * @param  ?int                  $userId            Konto, das als `issued_by` gespeichert wird
     * @param  array<string,string>  $extraVariables    Werte, die den Katalog ergänzen oder übersteuern
     * @param  bool                  $acceptMissing     Platzhalter ohne Wert dürfen leer bleiben
     * @return list<string> Beschriftungen der Platzhalter, die leer geblieben sind
     *
     * @throws DocumentNotIssued
     */
    public function issue(
        EditorDocument $document,
        ?int $issuerPersonnelId,
        ?int $userId,
        array $extraVariables = [],
        bool $acceptMissing = false,
    ): array {
        if ($document->isIssued()) {
            throw new DocumentNotIssued('Dieses Dokument wurde bereits ausgestellt.', true);
        }

        $issuer    = $issuerPersonnelId === null ? null : Personnel::query()->find($issuerPersonnelId);
        $leftEmpty = [];

        try {
            $issued = Capsule::connection()->transaction(function () use (&$document, &$leftEmpty, $issuer, $userId, $extraVariables, $acceptMissing): bool {
                // Inhalt, Pflichtfelder und PDF bleiben bis zum Commit derselbe Stand.
                $locked = EditorDocument::query()->with(['mitarbeiter', 'templateVersion'])->lockForUpdate()->find($document->id);
                if ($locked === null || $locked->isIssued()) {
                    return false;
                }
                $document  = $locked;
                $leftEmpty = [];

                // Mit der Fassung zählt auch ein unveränderter Vorschlagstext
                // als offen.
                $frozen = array_merge(VariableCatalog::resolve([
                    'mitarbeiter' => $document->mitarbeiter,
                    'docid'       => $document->docid,
                    'aussteller'  => $issuer,
                ]), $extraVariables);
                $catalog = VariableCatalog::catalog();
                $missing = [];
                foreach ((new PackageGuard())->openItems($document->content, $frozen, $document->templateVersion?->content) as $item) {
                    if ($item['type'] === 'variable') {
                        $leftEmpty[] = $catalog[$item['id']] ?? $item['id'];
                        continue;
                    }
                    $missing[] = $this->openItemText($item);
                }
                if ($missing !== []) {
                    throw new DocumentNotIssued(
                        'Dokument „' . $document->title . '" kann noch nicht ausgestellt werden. '
                        . 'Noch offen: ' . implode(', ', $missing) . '.',
                    );
                }

                // Platzhalter ohne Wert halten nicht auf wie ein offenes
                // Pflichtfeld: oft fehlt nur etwas, das jemand anderes
                // pflegt (Titel, Dienstnummer). Ausgestellt wird aber erst,
                // wenn der Schreiber bestätigt hat, dass die Stellen leer
                // bleiben dürfen. Die Rückfrage im Editor schickt dafür
                // accept_missing=1 mit.
                if ($leftEmpty !== [] && !$acceptMissing) {
                    throw new DocumentNotIssued(
                        'Dokument „' . $document->title . '" wurde nicht ausgestellt. Diese Platzhalter haben '
                        . 'keinen Wert und blieben leer: ' . implode(', ', $leftEmpty) . '. Trag die Angaben nach '
                        . 'oder bestätige beim Ausstellen, dass sie leer bleiben dürfen.',
                    );
                }

                $now = date('Y-m-d H:i:s');

                $affected = Capsule::table('intra_documents')
                    ->where('id', $document->id)
                    ->where('status', EditorDocument::STATUS_DRAFT)
                    ->update([
                        'status'        => EditorDocument::STATUS_ISSUED,
                        'frozen_values' => json_encode($frozen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'issued_by'     => $userId,
                        'issued_at'     => $now,
                        'updated_at'    => $now,
                    ]);

                if ($affected === 0) {
                    return false;
                }

                $pdfPath = (new PdfGenerator())->generate($document, $frozen);
                Capsule::table('intra_documents')->where('id', $document->id)->update(['pdf_path' => $pdfPath]);

                return true;
            });
        } catch (DocumentNotIssued $e) {
            throw $e;
        } catch (RuntimeException $e) {
            Logger::error('Ausstellen von Dokument ' . $document->docid . ' fehlgeschlagen: ' . $e->getMessage());
            throw new DocumentNotIssued('Das Dokument konnte nicht als PDF ausgestellt werden. Der Entwurf bleibt unverändert.');
        }

        if (!$issued) {
            throw new DocumentNotIssued('Dieses Dokument wurde inzwischen ausgestellt.', true);
        }

        $this->tellPersonnel($document, $issuer, $userId);

        return $leftEmpty;
    }

    private function tellPersonnel(EditorDocument $document, ?Personnel $issuer, ?int $userId): void
    {
        $mitarbeiter = $document->mitarbeiter;
        if ($mitarbeiter === null) {
            return;
        }

        $base  = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $link  = $base . 'documents/' . $document->id;
        $title = htmlspecialchars((string) $document->title);

        $this->log->addEntry(
            (int) $mitarbeiter->id,
            PersonalLogManager::TYPE_DOCUMENT,
            sprintf('Dokument <a href="%s">%s</a> (%s) wurde ausgestellt.', htmlspecialchars($link), $title, htmlspecialchars((string) $document->docid)),
            $issuer->fullname ?? ($userId !== null ? 'Admin #' . $userId : 'System'),
            ['change_type' => 'document_issued', 'document_id' => (int) $document->id, 'docid' => $document->docid],
        );

        $recipient = AccountLink::userFor((int) $mitarbeiter->id)?->id;
        if ($recipient !== null) {
            $this->notifications->notify('dokument', [(int) $recipient], [
                'title'   => 'Neues Dokument ausgestellt',
                'message' => 'Für dich wurde „' . $document->title . '" ausgestellt.',
                'link'    => $link,
            ]);
        }
    }

    /**
     * Ein offener Punkt aus PackageGuard::openItems() als Text für die
     * Meldung, mit dem Recht, falls der Verfasser ihn nicht selbst ausfüllt.
     *
     * @param array{type: string, id: string, label: string, region: string, reason: string, who: ?string} $item
     */
    private function openItemText(array $item): string
    {
        $text = $item['label'] . ($item['reason'] === 'minLength' ? ' (zu kurz)' : '');
        if ($item['who'] !== null) {
            $labels = array_column(DocumentPolicy::whoOptions(), 'label', 'key');
            $text  .= ', füllt aus: ' . ($labels[$item['who']] ?? $item['who']);
        }

        return $text;
    }
}
