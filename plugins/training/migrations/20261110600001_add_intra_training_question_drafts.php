<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fragen, die die KI über die EmergencyForge-Konsole vorgeschlagen hat.
 * `ai_draft` steht, bis jemand die Frage anschaltet; `source` bleibt danach
 * als Herkunft stehen („Skript.pdf, S. 12“). Dazu die einmalige Bestätigung
 * eines Admins, versteckt in `intra_config`.
 */
final class AddIntraTrainingQuestionDrafts extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_training_questions')
            ->addColumn('ai_draft', 'boolean', ['default' => false, 'after' => 'is_active'])
            ->addColumn('source', 'string', ['limit' => 255, 'null' => true, 'after' => 'ai_draft'])
            ->update();

        if ($this->fetchRow("SELECT 1 FROM intra_config WHERE config_key = 'TRAINING_AI_ACKNOWLEDGED'") === false) {
            $this->table('intra_config')->insert([
                'config_key'    => 'TRAINING_AI_ACKNOWLEDGED',
                'config_value'  => '',
                'config_type'   => 'string',
                'category'      => 'funktionen',
                'description'   => 'Ausbildung: Ein Admin hat bestätigt, dass Material für KI-Fragenentwürfe an Anthropic geht.',
                'is_editable'   => 0,
                'display_order' => 999,
            ])->saveData();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'TRAINING_AI_ACKNOWLEDGED'");
        $this->table('intra_training_questions')->removeColumn('source')->removeColumn('ai_draft')->update();
    }
}
