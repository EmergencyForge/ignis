<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;

/**
 * DocumentPolicy: Permissions rund um Mitarbeiter-Dokumente.
 *
 *   view():          eigene / fremde Dokumente ansehen
 *   manage():        anlegen, ändern, regenerieren, hochladen, ausblenden, …
 *   resetTemplate(): Template-Defaults wiederherstellen (admin-only)
 *   viewAudit():     Dokumenten-Audit-Trail einsehen (admin-only)
 *
 * „view" ist absichtlich laxer als „manage": User mit
 * `personnel.documents.view` dürfen fremde Dokumente sehen, wenn sie
 * keine manage-Rechte haben. Das eigene Dokument darf jeder sehen;
 * diese Owner-Check-Logik bleibt im Controller, damit die Policy nicht
 * die Request-Context-Details kennen muss.
 */
class DocumentPolicy
{
    public static function view(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'personnel.documents.manage', 'personnel.documents.view']);
    }

    public static function manage(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'personnel.documents.manage']);
    }

    /**
     * Rechte, die eine Vorlage für ein Feld oder einen Bereich verlangen kann
     * („Wer füllt aus“), in der Form, die der Editor erwartet.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function whoOptions(): array
    {
        $labels = (require dirname(__DIR__, 2) . '/config/permissions.php')['Personal'] ?? [];

        return array_map(
            static fn (string $key): array => ['key' => $key, 'label' => (string) ($labels[$key] ?? $key)],
            ['personnel.documents.manage', 'personnel.documents.approve'],
        );
    }

    /** Ob die angemeldete Person ein Feld mit diesem `who` ausfüllen darf. */
    public static function mayFill(string $who): bool
    {
        return Permissions::check(['admin', $who]);
    }

    public static function resetTemplate(mixed $context = null): bool
    {
        return Permissions::check(['admin']);
    }

    public static function viewAudit(mixed $context = null): bool
    {
        return Permissions::check(['admin']);
    }
}
