<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\Personnel;

/**
 * PersonnelPolicy — Single Source of Truth für Mitarbeiter-Permissions.
 *
 *   viewList()    — Mitarbeiter-Liste ansehen
 *   view()        — Einzel-Profil ansehen
 *   create()      — Neuen Mitarbeiter anlegen
 *   update()      — Profil bearbeiten
 *   delete()      — Mitarbeiter löschen
 *   manageDocs()  — Dokumente verwalten (anlegen/archivieren/löschen)
 *   deleteComments() — Profil-Kommentare löschen
 */
class PersonnelPolicy
{
    public static function viewList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'personnel.view']);
    }

    public static function view(?Personnel $target = null): bool
    {
        return Permissions::check(['admin', 'personnel.view']);
    }

    public static function create(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'personnel.edit']);
    }

    public static function update(?Personnel $target = null): bool
    {
        return Permissions::check(['admin', 'personnel.edit']);
    }

    public static function delete(?Personnel $target = null): bool
    {
        return Permissions::check(['admin', 'personnel.delete']);
    }

    public static function manageDocs(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'personnel.documents.manage']);
    }

    /**
     * Darf der Aktor ein fremdes Personaldokument einsehen? (Eigene
     * Dokumente prüft der Controller separat anhand der Aussteller-ID.)
     */
    public static function viewDoc(mixed $context = null): bool
    {
        return Permissions::check([
            'admin',
            'personnel.documents.manage',
            'personnel.documents.view',
            'personnel.view',
        ]);
    }

    public static function deleteComments(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'personnel.comment.delete']);
    }
}
