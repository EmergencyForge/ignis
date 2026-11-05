<?php

declare(strict_types=1);

use App\Documents\Editor\FieldChoices;
use Phinx\Migration\AbstractMigration;

/**
 * Dienstgrad und Berufsbezeichnung werden auf den drei Vorlagen wieder aus
 * den Stammdaten gewählt, wie im alten Dokumentensystem. Bisher waren es
 * Freitextfelder.
 *
 * Geändert wird nur das jeweilige Feld, und nur wenn es noch keine Liste
 * hat. Offene Entwürfe bekommen die Quelle mit: sie tragen ihre eigene Kopie
 * der Felder und zeigten sonst ein Textfeld, dessen Eingabe der SectionGuard
 * gegen die Liste der Vorlage verwirft.
 */
final class AddMasterDataChoicesToTemplates extends AbstractMigration
{
    private const FIELDS = [
        'Beförderungsurkunde'   => ['dienstgrad', FieldChoices::RANKS],
        'Ernennungsurkunde'     => ['dienstgrad', FieldChoices::RANKS],
        'Ausbildungszertifikat' => ['qualifikation', FieldChoices::AMB_SKILLS],
    ];

    public function up(): void
    {
        if (!$this->hasTable('intra_document_templates')) {
            return;
        }

        $connection = $this->getAdapter()->getConnection();
        $templates  = $connection->prepare('SELECT id, content FROM intra_document_templates WHERE name = ?');
        $drafts     = $connection->prepare("SELECT id, content FROM intra_documents WHERE template_id = ? AND status = 'entwurf'");

        foreach (self::FIELDS as $name => [$fieldId, $source]) {
            $templates->execute([$name]);
            foreach ($templates->fetchAll(PDO::FETCH_ASSOC) as $template) {
                $this->addSource('intra_document_templates', $template, $fieldId, $source);

                $drafts->execute([$template['id']]);
                foreach ($drafts->fetchAll(PDO::FETCH_ASSOC) as $draft) {
                    $this->addSource('intra_documents', $draft, $fieldId, $source);
                }
            }
        }
    }

    public function down(): void
    {
        // Die Quelle bleibt stehen. Ein älterer Stand ignoriert sie.
    }

    /** @param array{id: mixed, content: mixed} $row */
    private function addSource(string $table, array $row, string $fieldId, string $source): void
    {
        $content = json_decode((string) $row['content'], true);
        if (!is_array($content)) {
            return;
        }

        $changed = false;
        $content = $this->withSource($content, $fieldId, $source, $changed);
        if ($changed) {
            $this->getAdapter()->getConnection()
                ->prepare('UPDATE ' . $table . ' SET content = ? WHERE id = ?')
                ->execute([
                    json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    $row['id'],
                ]);
        }
    }

    /**
     * @param  array<mixed>  $node
     * @return array<mixed>
     */
    private function withSource(array $node, string $fieldId, string $source, bool &$changed): array
    {
        if (($node['type'] ?? null) === 'docField' && ($node['attrs']['fieldId'] ?? null) === $fieldId) {
            if (empty($node['attrs']['options']) && empty($node['attrs']['optionsSource'])) {
                $node['attrs']['optionsSource'] = $source;
                $changed                        = true;
            }

            return $node;
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $node['content'][$i] = $this->withSource($child, $fieldId, $source, $changed);
                }
            }
        }

        return $node;
    }
}
