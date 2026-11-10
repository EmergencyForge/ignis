<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Helpers\ProtocolDetection;
use App\Logging\Logger;
use App\Models\EditorDocument;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use EmergencyForge\Editor\Migration\SectionsToPackage;
use EmergencyForge\Editor\Package;
use EmergencyForge\Editor\PageShell;
use EmergencyForge\Training\VerificationCode;
use RuntimeException;

/**
 * Macht aus dem Dokumentpaket das PDF, das beim Ausstellen auf der Platte
 * landet und von da an ausgeliefert wird.
 *
 * HTML entsteht nur im Editor-Paket: `PageShell::html()` rendert Textkörper
 * und Bänder über den `Renderer`, der eine Whitelist von Knoten und Marken
 * kennt, und baut das Seitengerüst mit Rändern, Kopf- und Fußzeilen und
 * Hoch- oder Querformat aus dem Layout. Meldet er einen Fehler, wird nicht
 * ausgestellt. Ein halb verstandenes Dokument darf keine Urkunde werden.
 * Bilder kommen als Data-URI aus {@see TemplateImageSource}, weil Dompdf
 * nichts nachlädt.
 *
 * Editor-PDFs liegen unter `storage/private/editor-documents/` und werden nur
 * vom berechtigten Dokument-Endpunkt ausgeliefert.
 */
final class PdfGenerator
{
    /**
     * Schrift und Tabellen. Ränder, Bänder und Seitenformat kommen aus dem
     * Layout über PageShell. Nichts hier darf `html` einen Rand geben, Dompdf
     * legt die @page-Ränder darauf.
     */
    private const PRODUCT_CSS = <<<'CSS'
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1a1a1a; }
    p { margin: 0 0 0.75em; }
    table { border-collapse: collapse; width: 100%; margin: 0 0 0.75em; }
    th, td { border: 1px solid #999999; padding: 0.35em 0.5em; vertical-align: top; }
    th { background: #eeeeee; font-weight: bold; text-align: left; }
    CSS;

    /** Prüfcode-Streifen: so viel mm wächst der untere Rand, so groß ist der QR-Code. */
    private const STRIP   = 16;
    private const QR_SIZE = 12;

    /**
     * Rendert und speichert; liefert den Pfad relativ zum Projektordner,
     * wie er in `pdf_path` steht.
     *
     * @param  array<string,string>  $variables
     */
    public function generate(EditorDocument $document, array $variables): string
    {
        // Ein Platzhalter ohne Wert bleibt leer, `{{name}}` gehört nicht in
        // eine Urkunde. Ausgestellt wird so etwas erst nach Rückfrage
        // (DocumentIssuer::issue()).
        $package = SectionsToPackage::convert($document->content);
        $code    = $document->verification_code;
        if ($code !== null) {
            $package = self::reserveStrip(Package::normalize($package));
        }
        $shell = PageShell::html($package, $variables, self::PRODUCT_CSS, new TemplateImageSource());

        if ($shell->error) {
            throw new RuntimeException(
                'Dokument "' . $document->docid . '" konnte nicht sicher gerendert werden. Ausstellen abgebrochen.',
            );
        }
        $html = $code !== null ? self::withStrip($shell->html, $code, $package['layout']['firstPageDifferent'] === true) : $shell->html;

        foreach ($shell->warnings as $warning) {
            Logger::warning('Dokument ' . $document->docid . ': ' . $warning);
        }

        $options = $this->options();
        // Zwei Läufe, wenn das Dokument die Gesamtseitenzahl zeigt.
        $pdf = PageShell::render($html, static function (string $html) use ($options, $shell): array {
            $dompdf = new Dompdf($options);
            $dompdf->setCallbacks(PageShell::callbacks());
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $shell->orientation);
            $dompdf->render();

            return [(string) $dompdf->output(), $dompdf->getCanvas()->get_page_count()];
        });

        $dir = $this->documentsDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('PDF-Verzeichnis konnte nicht angelegt werden.');
        }

        $relativePath = 'storage/private/editor-documents/' . $document->docid . '.pdf';

        // Bricht der Vorgang zwischen dem Schreiben und dem Commit der
        // umgebenden Transaktion ab, bleibt eine PDF-Datei liegen, auf die
        // kein Dokument zeigt. Harmlos: `pdf_path` wird erst nach
        // erfolgreichem Schreiben gesetzt, und ein zweiter Anlauf
        // überschreibt dieselbe Datei.
        $written = @file_put_contents(dirname(__DIR__, 3) . '/' . $relativePath, $pdf);
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
        if (!$document->isIssued() || $document->pdf_path === null) {
            return null;
        }

        $name = $document->docid . '.pdf';
        if (!in_array($document->pdf_path, [
            'storage/private/editor-documents/' . $name,
            'storage/documents/' . $name,
        ], true)) {
            return null;
        }

        $candidate = dirname(__DIR__, 3) . '/' . $document->pdf_path;
        $dir = dirname($candidate);

        // Symlinks dürfen nicht aus dem erlaubten Ordner herausführen.
        $real    = realpath($candidate);
        $realDir = realpath($dir);
        if ($real === false || $realDir === false || !is_file($real)
            || !str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $contents = file_get_contents($real);

        return $contents === false ? null : $contents;
    }

    /**
     * Macht den unteren Rand um den Streifen größer, damit er und die
     * Fußzeile der Vorlage zusammen hineinpassen.
     *
     * ponytail: Ränder über 80 mm kappt das Layout, dann schneidet das
     * Fußband unten ab.
     *
     * @param array<string,mixed> $package normalisiertes Paket
     * @return array<string,mixed>
     */
    private static function reserveStrip(array $package): array
    {
        $package['layout']['margins']['bottom'] += self::STRIP;
        if (is_array($package['layout']['firstPageMargins'] ?? null)) {
            $package['layout']['firstPageMargins']['bottom'] += self::STRIP;
        }

        return $package;
    }

    /**
     * Setzt den Streifen oben in die Fußbänder von PageShell, also direkt
     * unter den Inhalt und über die Fußzeile der Vorlage. Hat die Vorlage
     * keine Fußzeile, entsteht ein Band nur für den Streifen.
     */
    private static function withStrip(string $html, string $code, bool $firstPageDifferent): string
    {
        $strip = self::verificationStrip($code);
        foreach ($firstPageDifferent ? ['efe-foot-first', 'efe-foot-rest'] : ['efe-foot-rest'] as $id) {
            $open = '<div id="' . $id . '" class="efe-band efe-band--foot">';
            $html = str_contains($html, $open)
                ? str_replace($open, $open . $strip, $html)
                : str_replace('<div class="efe-body">', $open . $strip . '</div><div class="efe-body">', $html);
        }
        // Ändert PageShell sein Gerüst, soll das Ausstellen scheitern statt ohne Prüfcode zu drucken.
        if (!str_contains($html, $strip)) {
            throw new RuntimeException('Der Prüfcode ließ sich nicht in den Fuß setzen.');
        }

        return $html;
    }

    /** QR-Code auf die Prüfseite, daneben Code und Adresse zum Abtippen. */
    private static function verificationStrip(string $code): string
    {
        $qr = (new QRCode(new QROptions([
            'eccLevel'         => EccLevel::M,
            'drawLightModules' => false,
            'connectPaths'     => true,
            'quietzoneSize'    => 0,
        ])))->render(ProtocolDetection::buildPublicUrl('verify?code=' . $code));
        $address = (string) preg_replace('#^https?://#', '', ProtocolDetection::buildPublicUrl('verify'));

        return sprintf(
            '<div style="height: %2$dmm; margin: %1$dmm 0 %1$dmm; font-size: 7pt; font-style: normal; line-height: 1.4; text-align: left; color: #5b6478;">'
            . '<img src="%3$s" alt="" style="width: %2$dmm; height: %2$dmm; float: left; margin-right: 3mm;">'
            . '<div style="padding-top: 3mm;">Echtheit prüfen: <b style="color: #1a1a1a; font-family: \'DejaVu Sans Mono\', monospace; font-size: 8pt;">%4$s</b><br>%5$s</div></div>',
            (self::STRIP - self::QR_SIZE) / 2,
            self::QR_SIZE,
            htmlspecialchars($qr),
            htmlspecialchars(VerificationCode::format($code)),
            htmlspecialchars($address),
        );
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

    private function documentsDir(): string
    {
        return dirname(__DIR__, 3) . '/storage/private/editor-documents';
    }
}
