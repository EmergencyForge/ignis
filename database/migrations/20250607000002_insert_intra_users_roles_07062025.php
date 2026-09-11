<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed für die Default-Rollen: Admin, SGL, TL, QM-RD, Ausbilder, Personaler
 * und Gast (Default-Rolle) mit ihren Start-Permissions.
 */
class InsertIntraUsersRoles07062025 extends AbstractMigration
{
    public function up(): void
    {
        $rows = [
            ['id' => 1, 'priority' => 10,  'name' => 'Admin',      'color' => 'danger',    'permissions' => '["admin"]',                                                                                                                                                                                            'created_at' => '2025-03-23 22:17:15', 'default' => 0, 'admin' => 1],
            ['id' => 2, 'priority' => 100, 'name' => 'SGL',        'color' => 'primary',   'permissions' => '["application.view", "application.edit", "edivi.view", "personnel.view", "personnel.edit", "personnel.documents.manage", "users.view", "users.edit", "users.create", "files.upload", "files.log.view"]', 'created_at' => '2025-03-23 22:27:45', 'default' => 0, 'admin' => 0],
            ['id' => 3, 'priority' => 110, 'name' => 'TL',         'color' => 'primary',   'permissions' => '["personnel.view", "personnel.documents.manage"]',                                                                                                                                                       'created_at' => '2025-03-23 22:28:16', 'default' => 0, 'admin' => 0],
            ['id' => 4, 'priority' => 200, 'name' => 'QM-RD',      'color' => 'info',      'permissions' => '["personnel.view", "edivi.view", "edivi.edit"]',                                                                                                                                                         'created_at' => '2025-03-23 22:30:31', 'default' => 0, 'admin' => 0],
            ['id' => 5, 'priority' => 210, 'name' => 'Ausbilder',  'color' => 'success',   'permissions' => '["personnel.view", "personnel.documents.manage"]',                                                                                                                                                       'created_at' => '2025-03-23 22:31:57', 'default' => 0, 'admin' => 0],
            ['id' => 6, 'priority' => 220, 'name' => 'Personaler', 'color' => 'success',   'permissions' => '["personnel.view", "personnel.edit", "personnel.documents.manage"]',                                                                                                                                     'created_at' => '2025-03-23 22:32:18', 'default' => 0, 'admin' => 0],
            ['id' => 7, 'priority' => 999, 'name' => 'Gast',       'color' => 'secondary', 'permissions' => '[]',                                                                                                                                                                                                     'created_at' => '2025-03-23 22:33:25', 'default' => 1, 'admin' => 0],
        ];

        $this->table('intra_users_roles')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `intra_users_roles` WHERE `id` IN (1, 2, 3, 4, 5, 6, 7)');
    }
}
