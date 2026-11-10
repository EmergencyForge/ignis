<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Phinx\Db\Adapter\MysqlAdapter;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Migration 20261110500002: Urkunden mit dem alten Rand bekommen Platz für
 * die ganze Fußzeile, selbst geänderte Ränder bleiben.
 */
final class WidenCertificateFooterMigrationTest extends IntegrationTestCase
{
    private function migration(): \WidenCertificateFooter
    {
        require_once dirname(__DIR__, 3) . '/database/migrations/20261110500002_widen_certificate_footer.php';

        $adapter = new MysqlAdapter(['name' => (string) $_ENV['DB_NAME']]);
        $adapter->setConnection($this->pdo);

        $migration = new \WidenCertificateFooter('test', 20261110500002);
        $migration->setAdapter($adapter);

        return $migration;
    }

    private function template(int $bottom, int $footerDistance): int
    {
        $content = json_encode(['format' => 2, 'layout' => [
            'paper' => 'A4', 'orientation' => 'landscape',
            'margins' => ['top' => 25, 'right' => 35, 'bottom' => $bottom, 'left' => 35],
            'headerDistance' => 8, 'footerDistance' => $footerDistance,
        ]]);
        $this->pdo->prepare('INSERT INTO intra_document_templates (name, category, content, is_active) VALUES (?, ?, ?, 1)')
            ->execute(['Testurkunde ' . $bottom, 'Zertifikat', $content]);
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO intra_document_template_versions (template_id, version, content) VALUES (?, 1, ?)')->execute([$id, $content]);

        return $id;
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} Vorlage und jüngste Fassung */
    private function layouts(int $id): array
    {
        $template = $this->pdo->query('SELECT content FROM intra_document_templates WHERE id = ' . $id)->fetchColumn();
        $version  = $this->pdo->query('SELECT content FROM intra_document_template_versions WHERE template_id = ' . $id . ' ORDER BY version DESC LIMIT 1')->fetchColumn();

        return [json_decode((string) $template, true)['layout'], json_decode((string) $version, true)['layout']];
    }

    #[Test]
    public function nur_der_alte_rand_wird_umgestellt(): void
    {
        $old  = $this->template(30, 10);
        $own  = $this->template(40, 10);

        $this->migration()->up();

        foreach ($this->layouts($old) as $layout) {
            $this->assertSame(35, $layout['margins']['bottom']);
            $this->assertSame(8, $layout['footerDistance']);
        }
        $this->assertSame(40, $this->layouts($own)[0]['margins']['bottom']);
        $this->assertSame(10, $this->layouts($own)[0]['footerDistance']);

        $this->migration()->down();
        $this->assertSame(30, $this->layouts($old)[0]['margins']['bottom']);
    }
}
