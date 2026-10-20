<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Crew-Sessions mit Ablauf und Grund fürs Ende.
 *
 * - last_activity: letzte echte Nutzung (Seitenaufruf, Speichern). Der
 *   10-Sekunden-Abgleich zählt nicht, sonst liefe eine Session ewig.
 * - ended_reason: warum eine Session inaktiv wurde (logout, replaced,
 *   expired, out_of_service, deleted). Die Abmelde-Seite nennt damit den
 *   Grund, statt nur „nicht angemeldet“ zu zeigen.
 * - ENOTF_SESSION_IDLE_HOURS: nach so vielen Stunden ohne Nutzung endet
 *   die Anmeldung auf dem Fahrzeug.
 */
class AlterIntraEnotfSessions20102026Activity extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('intra_enotf_sessions');
        if (!$table->hasColumn('last_activity')) {
            $table->addColumn('last_activity', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'after' => 'active'])->update();
            $this->execute('UPDATE intra_enotf_sessions SET last_activity = updated_at');
        }
        if (!$table->hasColumn('ended_reason')) {
            $table->addColumn('ended_reason', 'string', ['limit' => 32, 'null' => true, 'default' => null, 'after' => 'last_activity'])->update();
        }

        if (!$this->fetchRow("SELECT config_key FROM intra_config WHERE config_key = 'ENOTF_SESSION_IDLE_HOURS'")) {
            $row = [
                'config_key'    => 'ENOTF_SESSION_IDLE_HOURS',
                'config_value'  => '12',
                'config_type'   => 'integer',
                'category'      => 'enotf',
                'description'   => 'Anmeldung endet nach',
                'is_editable'   => 1,
                'display_order' => 16,
            ];
            if ($this->table('intra_config')->hasColumn('hint')) {
                $row['hint'] = 'So lange darf eine Besatzung auf dem Fahrzeug nichts tun, bevor ihre Anmeldung endet.';
            }
            $this->table('intra_config')->insert([$row])->saveData();
        }
    }

    public function down(): void
    {
        $table = $this->table('intra_enotf_sessions');
        if ($table->hasColumn('ended_reason')) {
            $table->removeColumn('ended_reason')->update();
        }
        if ($table->hasColumn('last_activity')) {
            $table->removeColumn('last_activity')->update();
        }
        $this->execute("DELETE FROM intra_config WHERE config_key = 'ENOTF_SESSION_IDLE_HOURS'");
    }
}
