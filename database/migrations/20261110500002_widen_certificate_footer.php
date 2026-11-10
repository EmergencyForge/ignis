<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Die mitgelieferten Urkunden im Querformat schnitten die letzte Zeile des
 * Fußes ab: 30 mm Rand mit 10 mm Abstand lassen dem Fußband 18 mm, die drei
 * Zeilen brauchen gut 21. Umgestellt werden nur Vorlagen, die noch genau den
 * alten Rand haben; wer ihn selbst geändert hat, behält seinen.
 *
 * Wie beim Speichern im Editor: hängt an der jüngsten Fassung schon ein
 * Dokument, entsteht eine neue, sonst wird sie überschrieben.
 */
final class WidenCertificateFooter extends AbstractMigration
{
    private const OLD = ['bottom' => 30, 'footerDistance' => 10];
    private const NEW = ['bottom' => 35, 'footerDistance' => 8];

    public function up(): void
    {
        $this->move(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->move(self::NEW, self::OLD);
    }

    /**
     * @param array{bottom: int, footerDistance: int} $from
     * @param array{bottom: int, footerDistance: int} $to
     */
    private function move(array $from, array $to): void
    {
        if (!$this->hasTable('intra_document_templates')) {
            return;
        }
        $db       = $this->getAdapter()->getConnection();
        $template = $db->prepare('UPDATE intra_document_templates SET content = ? WHERE id = ?');
        $latest   = $db->prepare('SELECT v.id, v.version, EXISTS (SELECT 1 FROM intra_documents d WHERE d.template_version_id = v.id) AS used'
            . ' FROM intra_document_template_versions v WHERE v.template_id = ? ORDER BY v.version DESC LIMIT 1');
        $version  = $db->prepare('UPDATE intra_document_template_versions SET content = ? WHERE id = ?');
        $insert   = $db->prepare('INSERT INTO intra_document_template_versions (template_id, version, content) VALUES (?, ?, ?)');

        foreach ($db->query('SELECT id, content FROM intra_document_templates')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $content = json_decode((string) $row['content'], true);
            $layout  = is_array($content) ? ($content['layout'] ?? null) : null;
            if (!is_array($layout)
                || ($layout['orientation'] ?? null) !== 'landscape'
                || ($layout['margins'] ?? null) != ['top' => 25, 'right' => 35, 'bottom' => $from['bottom'], 'left' => 35]
                || ($layout['footerDistance'] ?? null) !== $from['footerDistance']) {
                continue;
            }
            $content['layout']['margins']['bottom'] = $to['bottom'];
            $content['layout']['footerDistance']    = $to['footerDistance'];
            $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $template->execute([$json, $row['id']]);
            $latest->execute([$row['id']]);
            $last = $latest->fetch(PDO::FETCH_ASSOC);
            $latest->closeCursor();
            if ($last === false) {
                continue;
            }
            if ((int) $last['used'] === 0) {
                $version->execute([$json, $last['id']]);
            } else {
                $insert->execute([$row['id'], (int) $last['version'] + 1, $json]);
            }
        }
    }
}
