<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * intra_users_roles.permissions: deutsch -> englisch.
 *
 * Phase I der URL-Migration. Die Permission-Catalog-Renames in
 * `config/permissions.php` (z.B. `mitarbeiter.view` -> `personnel.view`)
 * werden in den persistierten Rollen-Zuweisungen nachgezogen — sonst
 * verlieren bestehende Rollen ihre Berechtigungen, weil der Code jetzt
 * englische Strings prueft, in der DB aber noch deutsche stehen.
 *
 * Idempotent: REPLACE auf bereits-englischen Strings macht nichts.
 * Mehrfache Ausfuehrung ist sicher (z.B. wenn die Migration im Setup
 * mehrfach getriggert wird).
 *
 * intra_users_roles.permissions ist ein JSON-Array von Strings, daher
 * wird mit dem fuehrenden `"<alt>.` als Anchor gesucht — das verhindert
 * False-Matches (z.B. wuerde rohes 'manv' auch in Klartext-Beschreibungen
 * matchen, was wir nicht wollen).
 */
class TranslatePermissionStrings extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('intra_users_roles')) {
            return;
        }

        $pdo = $this->getAdapter()->getConnection();

        $map = [
            'mitarbeiter' => 'personnel',
            'antrag'      => 'forms',
            'manv'        => 'mci',
            'einsatz'     => 'firetab',
            'fahrt'       => 'logbook',
            'fahrtenbuch' => 'logbook',
        ];

        foreach ($map as $alt => $neu) {
            $pdo->exec(sprintf(
                "UPDATE intra_users_roles SET permissions = REPLACE(permissions, %s, %s) WHERE permissions LIKE %s",
                $pdo->quote('"' . $alt . '.'),
                $pdo->quote('"' . $neu . '.'),
                $pdo->quote('%"' . $alt . '.%'),
            ));
        }
    }

    public function down(): void
    {
        // Bewusst kein Down-Path — die Translation ist eine Einbahnstrasse.
    }
}
