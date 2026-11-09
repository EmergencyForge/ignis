<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Jede Rolle darf Lehrgänge sehen und sich anmelden. Leiten, Prüfungen
 * und Verwaltung verteilt die Fraktion selbst.
 */
class GrantTrainingViewToRoles extends AbstractMigration
{
    public function up(): void
    {
        foreach ($this->fetchAll('SELECT id, permissions FROM intra_users_roles') as $role) {
            $permissions = json_decode((string) ($role['permissions'] ?? ''), true);
            $permissions = is_array($permissions) ? array_values($permissions) : [];
            if (in_array('training.view', $permissions, true)) {
                continue;
            }
            $permissions[] = 'training.view';
            $this->execute('UPDATE intra_users_roles SET permissions = ? WHERE id = ?', [json_encode($permissions), $role['id']]);
        }
    }

    public function down(): void
    {
        foreach ($this->fetchAll('SELECT id, permissions FROM intra_users_roles') as $role) {
            $permissions = json_decode((string) ($role['permissions'] ?? ''), true);
            if (!is_array($permissions)) {
                continue;
            }
            $this->execute('UPDATE intra_users_roles SET permissions = ? WHERE id = ?', [json_encode(array_values(array_diff($permissions, ['training.view']))), $role['id']]);
        }
    }
}
