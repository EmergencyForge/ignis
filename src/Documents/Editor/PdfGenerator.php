<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Logging\Logger;
use App\Models\EditorDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use EmergencyForge\Editor\PrintStyles;
use EmergencyForge\Editor\Renderer;
use RuntimeException;

/**
 * Macht aus dem Dokument-JSON das PDF, das beim Ausstellen auf der Platte
 * landet und von da an ausgeliefert wird.
 *
 * Der Weg von JSON zu HTML führt ausschließlich über den `Renderer` des
 * Editor-Pakets: er kennt eine Whitelist von Knoten und Marken und
 * verwirft alles andere. Meldet er einen Fehler, wird nicht gerendert und
 * nicht ausgestellt — ein halb verstandenes Dokument darf keine Urkunde
 * werden.
 *
 * Die Dateien liegen unter `storage/documents/<docid>.pdf`, wo auch die
 * des alten Systems liegen. Die Kennungen kollidieren nicht: die alten
 * sind zwölfstellig mit Bindestrichen, die neuen siebenstellig numerisch.
 */
final class PdfGenerator
{
    /**
     * Rendert und speichert; liefert den Pfad relativ zum Projektordner,
     * wie er in `pdf_path` steht.
     *
     * @param  array<string,string>  $variables
     */
    public function generate(EditorDocument $document, array $variables): string
    {
        $result = (new Renderer())->render($document->content, $variables);

        if ($result->error) {
            throw new RuntimeException(
                'Dokument "' . $document->docid . '" konnte nicht sicher gerendert werden — Ausstellen abgebrochen.',
            );
        }

        foreach ($result->warnings as $warning) {
            Logger::warning('Dokument ' . $document->docid . ': ' . $warning);
        }

        $dompdf = new Dompdf($this->options());
        $dompdf->loadHtml($this->html($result->html), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dir = $this->documentsDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('PDF-Verzeichnis konnte nicht angelegt werden.');
        }

        $relativePath = 'storage/documents/' . $document->docid . '.pdf';

        // Bricht der Vorgang zwischen dem Schreiben und dem Commit der
        // umgebenden Transaktion ab, bleibt eine PDF-Datei liegen, auf die
        // kein Dokument zeigt. Harmlos: `pdf_path` wird erst nach
        // erfolgreichem Schreiben gesetzt, und ein zweiter Anlauf
        // überschreibt dieselbe Datei.
        $written = @file_put_contents(dirname(__DIR__, 3) . '/' . $relativePath, $dompdf->output());
        if ($written === false) {
            throw new RuntimeException('PDF-Datei konnte nicht gespeichert werden.');
        }

        return $relativePath;
    }

    /**
     * Liest das gespeicherte PDF. `null`, wenn keines hinterlegt ist oder
     * der Pfad aus dem Dokumentenordner herauszeigt.
     */
    public function read(EditorDocument $document): ?string
    {
        if ($document->pdf_path === null) {
            return null;
        }

        $dir       = $this->documentsDir();
        $candidate = $dir . '/' . basename($document->pdf_path);

        // basename() allein reicht nicht: ein Symlink im Ordner könnte
        // weiterhin nach draußen zeigen.
        $real    = realpath($candidate);
        $realDir = realpath($dir);
        if ($real === false || $realDir === false || !str_starts_with($real, $realDir)) {
            return null;
        }

        $contents = file_get_contents($real);

        return $contents === false ? null : $contents;
    }

    private function options(): Options
    {
        $options = new Options();
        // Nichts aus dem Netz nachladen und kein PHP im HTML ausführen:
        // das Dokument-JSON kommt aus der Datenbank und damit von jedem,
        // der Dokumente schreiben darf.
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsHtml5ParserEnabled(true);

        return $options;
    }

    private function html(string $bodyHtml): string
    {
        $editorCss = PrintStyles::css();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="de">
        <head>
        <meta charset="utf-8">
        <style>
            @page { margin: 20mm 18mm; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1a1a1a; }
            p { margin: 0 0 0.75em; }
            table { border-collapse: collapse; width: 100%; margin: 0 0 0.75em; }
            th, td { border: 1px solid #999999; padding: 0.35em 0.5em; vertical-align: top; }
            th { background: #eeeeee; font-weight: bold; text-align: left; }
        {$editorCss}
        </style>
        </head>
        <body>
        {$bodyHtml}
        </body>
        </html>
        HTML;
    }

    private function documentsDir(): string
    {
        return dirname(__DIR__, 3) . '/storage/documents';
    }
}
