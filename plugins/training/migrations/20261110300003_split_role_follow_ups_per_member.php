<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Eine Folgeaufgabe an eine Rolle wird zu einer Aufgabe je aktivem Konto
 * der Rolle, jede mit eigenem Erledigt-Stand, wie in Lex. `role_id` bleibt
 * als Herkunft stehen.
 */
final class SplitRoleFollowUpsPerMember extends AbstractMigration
{
    private const TABLE = 'intra_training_follow_ups';

    public function up(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }
        // In einem Schritt, damit der Fremdschlüssel auf participation_id nie
        // ohne Index dasteht.
        $this->execute('ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX uniq_follow_up_member (participation_id, user_id), DROP INDEX participation_id');

        $this->execute(
            'INSERT INTO ' . self::TABLE . ' (participation_id, personnel_id, user_id, role_id, title, due_on, created_at, updated_at)'
            . ' SELECT f.participation_id, f.personnel_id, u.id, f.role_id, f.title, f.due_on, f.created_at, NOW()'
            . ' FROM ' . self::TABLE . ' f JOIN intra_users u ON u.role = f.role_id AND u.is_active = 1'
            . ' WHERE f.user_id IS NULL AND f.role_id IS NOT NULL AND f.done_at IS NULL'
        );
        $this->execute('DELETE FROM ' . self::TABLE . ' WHERE user_id IS NULL AND role_id IS NOT NULL AND done_at IS NULL');
    }

    public function down(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }
        // Zurück auf eine Aufgabe je Teilnahme: die älteste bleibt.
        $this->execute(
            'DELETE f FROM ' . self::TABLE . ' f JOIN ' . self::TABLE . ' g'
            . ' ON g.participation_id = f.participation_id AND g.id < f.id'
        );
        $this->execute('UPDATE ' . self::TABLE . ' SET user_id = NULL WHERE role_id IS NOT NULL');
        $this->execute('ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX participation_id (participation_id), DROP INDEX uniq_follow_up_member');
    }
}
