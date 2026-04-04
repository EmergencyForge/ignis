<?php

namespace App\Documents;

use PDO;
use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentPDFGenerator
{
    private PDO $pdo;
    private DocumentRenderer $renderer;
    private string $storagePath;

    public function __construct(PDO $pdo, DocumentRenderer $renderer, string $storagePath = __DIR__ . '/../../storage/documents')
    {
        $this->pdo = $pdo;
        $this->renderer = $renderer;
        $this->storagePath = $storagePath;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Generiert PDF für ein Dokument und speichert es
     * @param int $dbId Database ID (nicht docid!)
     */
    public function generateAndStore(int $dbId): string
    {
        // Rendere HTML
        $html = $this->renderer->renderDocument($dbId);

        // Hole die docid für den Dateinamen
        $stmt = $this->pdo->prepare("
        SELECT d.docid, t.template_file 
        FROM intra_mitarbeiter_dokumente d
        JOIN intra_dokument_templates t ON d.template_id = t.id
        WHERE d.id = :id
        ");
        $stmt->execute(['id' => $dbId]);
        $docInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$docInfo) {
            throw new \Exception("Dokument mit ID {$dbId} nicht gefunden");
        }

        $docid = $docInfo['docid'];
        $templateFile = $docInfo['template_file'];

        // Generiere Dateiname basierend auf docid
        $filename = $this->generateFilename($docid);
        $filepath = $this->storagePath . DIRECTORY_SEPARATOR . $filename;

        // Konfiguriere Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('dpi', 150);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        // Seitenzahl: Position aus dem HTML extrahieren (data-page-number Elemente)
        // oder Fallback auf hardcoded Positionen für Legacy-Templates
        $this->applyPageNumbers($html, $dompdf, $templateFile);

        file_put_contents($filepath, $dompdf->output());

        // Update Datenbank mit Pfad
        $this->updateDocumentPath($dbId, $filename);

        return $filepath;
    }

    /**
     * Generiert Dateinamen basierend auf docid
     * @param string $docid Die alphanumerische Dokument-ID
     */
    private function generateFilename(string $docid): string
    {
        return $docid . '.pdf';
    }

    /**
     * Seitenzahlen auf das PDF anwenden.
     * Erkennt data-page-number Elemente im HTML (vom Visual Editor) oder
     * fällt auf Legacy-Positionen für bekannte Twig-Templates zurück.
     */
    private function applyPageNumbers(string $html, Dompdf $dompdf, string $templateFile): void
    {
        $mmToPt = 2.8346;

        // 1. Prüfe ob das HTML data-page-number Elemente enthält (Visual Editor)
        if (preg_match('/data-page-number="true"\s+data-pn-left="([^"]+)"\s+data-pn-top="([^"]+)"(?:\s+data-pn-format="([^"]*)")?/', $html, $m)) {
            $leftMm = (float) $m[1];
            $topMm = (float) $m[2];
            $format = html_entity_decode($m[3] ?? '{page} von {pages}', ENT_QUOTES, 'UTF-8');

            $x = $leftMm * $mmToPt;
            $y = $topMm * $mmToPt;

            $canvas = $dompdf->getCanvas();
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($x, $y, $format) {
                $font = $fontMetrics->getFont("DejaVu Sans");
                $text = str_replace(['{page}', '{pages}'], [$pageNumber, $pageCount], $format);
                $canvas->text($x, $y, $text, $font, 8.5, [0, 0, 0]);
            });
            return;
        }

        // 2. Legacy-Fallback: hardcoded Positionen für bekannte Twig-Templates
        $templatesWithPageNumbers = [
            'ernennung.html.twig',
            'befoerderung.html.twig',
            'ausbildung.html.twig',
            'fachlehrgang.html.twig',
            'entlassung.html.twig',
        ];

        if (in_array($templateFile, $templatesWithPageNumbers)) {
            $canvas = $dompdf->getCanvas();
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $font = $fontMetrics->getFont("DejaVu Sans");
                $text = "$pageNumber von $pageCount";
                $canvas->text(57, 68, $text, $font, 8.5, [0, 0, 0]);
            });
        }
    }

    /**
     * Aktualisiert PDF-Pfad in der Datenbank
     * @param int $dbId Database ID
     */
    private function updateDocumentPath(int $dbId, string $filename): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE intra_mitarbeiter_dokumente 
            SET pdf_path = :pdf_path, 
                pdf_generated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->execute([
            'pdf_path' => $filename,
            'id' => $dbId
        ]);
    }

    /**
     * Holt oder generiert PDF
     * @param int $dbId Database ID
     */
    public function getPDF(int $dbId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT pdf_path FROM intra_mitarbeiter_dokumente WHERE id = :id
        ");
        $stmt->execute(['id' => $dbId]);
        $pdfPath = $stmt->fetchColumn();

        $fullPath = $this->storagePath . DIRECTORY_SEPARATOR . $pdfPath;

        if ($pdfPath && file_exists($fullPath)) {
            return $fullPath;
        }

        // PDF existiert nicht, generiere neu
        return $this->generateAndStore($dbId);
    }

    /**
     * Streamt PDF zum Browser
     * @param int $dbId Database ID
     */
    public function streamPDF(int $dbId, bool $inline = true): void
    {
        $filepath = $this->getPDF($dbId);

        if (!$filepath || !file_exists($filepath)) {
            http_response_code(404);
            echo "Dokument nicht gefunden";
            return;
        }

        $disposition = $inline ? 'inline' : 'attachment';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filepath);
        exit;
    }

    /**
     * Generiert PDF neu
     * @param int $dbId Database ID
     */
    public function regeneratePDF(int $dbId): string
    {
        $stmt = $this->pdo->prepare("
            SELECT pdf_path FROM intra_mitarbeiter_dokumente WHERE id = :id
        ");
        $stmt->execute(['id' => $dbId]);
        $oldPath = $stmt->fetchColumn();

        // Lösche alte PDF falls vorhanden
        if ($oldPath) {
            $fullPath = $this->storagePath . DIRECTORY_SEPARATOR . $oldPath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        return $this->generateAndStore($dbId);
    }

    /**
     * Löscht PDF
     * @param int $dbId Database ID
     */
    public function deletePDF(int $dbId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT pdf_path FROM intra_mitarbeiter_dokumente WHERE id = :id
        ");
        $stmt->execute(['id' => $dbId]);
        $pdfPath = $stmt->fetchColumn();

        if ($pdfPath) {
            $fullPath = $this->storagePath . DIRECTORY_SEPARATOR . $pdfPath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            $stmt = $this->pdo->prepare("
                UPDATE intra_mitarbeiter_dokumente 
                SET pdf_path = NULL, pdf_generated_at = NULL 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $dbId]);

            return true;
        }

        return false;
    }

    /**
     * Generiert mehrere PDFs
     * @param array $dbIds Array von Database IDs
     */
    public function generateBulk(array $dbIds): array
    {
        $results = [];

        foreach ($dbIds as $dbId) {
            try {
                $filepath = $this->generateAndStore($dbId);
                $results[$dbId] = [
                    'success' => true,
                    'path' => $filepath
                ];
            } catch (\Exception $e) {
                $results[$dbId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
