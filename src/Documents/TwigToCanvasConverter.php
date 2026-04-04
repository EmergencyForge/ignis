<?php

namespace App\Documents;

/**
 * Konvertiert bestehende Twig-Templates (.html.twig) in Fabric.js Canvas-JSON
 * für den visuellen Template-Editor.
 *
 * Bildet die CSS-Positionen der Twig-Templates präzise auf Canvas-Koordinaten ab.
 * Unterstützt zwei Layouts:
 * - Urkunden-Stil: padding 10mm 18mm 22mm 18mm, roter Rahmen, Docheader-Tabelle, zentrierter Inhalt
 * - Brief-Stil: padding 20mm 25mm, Header links/rechts, Empfänger, Fließtext
 */
class TwigToCanvasConverter
{
    /** Pixel pro Millimeter bei 96 DPI */
    private const PX_PER_MM = 3.7795;

    private const PT_TO_PX = 1.333;

    private string $templatePath;

    public function __construct(string $templatePath = __DIR__ . '/../../dokumente/templates')
    {
        $this->templatePath = rtrim($templatePath, '/\\');
    }

    /**
     * Konvertiert ein Twig-Template in Canvas-JSON.
     */
    public function convert(string $filename, array $fields = []): array
    {
        $filepath = $this->templatePath . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($filepath)) {
            throw new \Exception("Template-Datei nicht gefunden: {$filename}");
        }

        $html = file_get_contents($filepath);
        $layout = $this->detectLayout($html, $filename);

        $objects = match ($layout) {
            'urkunde' => $this->convertUrkundeLayout($html, $fields),
            'brief' => $this->convertBriefLayout($html, $fields),
            default => $this->convertBriefLayout($html, $fields),
        };

        return [
            'version' => '6.4.2',
            'objects' => $objects,
            'background' => '#ffffff',
        ];
    }

    private function detectLayout(string $html, string $filename): string
    {
        if (str_contains($html, 'border-frame') && str_contains($html, 'docheader')) {
            return 'urkunde';
        }
        if (str_contains($html, 'header clearfix') || str_contains($html, 'header-right')) {
            return 'brief';
        }
        $urkundenTemplates = ['ernennung', 'befoerderung', 'entlassung', 'ausbildung', 'fachlehrgang', 'lehrgang'];
        foreach ($urkundenTemplates as $name) {
            if (str_contains($filename, $name)) return 'urkunde';
        }
        return 'brief';
    }

    // =========================================================================
    // Urkunden-Layout
    // CSS: body padding 10mm 18mm 22mm 18mm
    // border-frame: fixed, top 7mm, left 13mm, right 13mm, bottom 18mm
    // docheader: 3-spaltig (18% | 64% | 18%), 1px solid #000, 2mm padding
    // h1: 20pt bold #dc0814, center, margin 12mm 0 10mm 0
    // .content: center, 11.5pt, line-height 1.5, p margin 4mm
    // .important: 15pt bold, margin 6mm
    // .disclaimer: fixed bottom 7mm, left/right 13mm, bg #dc0814, 7.5pt white
    // =========================================================================

    private function convertUrkundeLayout(string $html, array $fields): array
    {
        $o = []; // objects

        // Seitenränder (body padding: 10mm 18mm 22mm 18mm)
        $padTop = 10;
        $padRight = 18;
        $padBottom = 22;
        $padLeft = 18;
        $pageW = 210;

        // Nutzbare Breite innerhalb Padding
        $contentW = $pageW - $padLeft - $padRight; // 174mm

        // --- Roter Rahmen (.border-frame: top 7mm, left/right 13mm, bottom 18mm) ---
        $frameTop = 7;
        $frameLeft = 13;
        $frameRight = 13;
        $frameBottom = 18;
        $o[] = $this->rect(
            $this->mm($frameLeft), $this->mm($frameTop),
            $this->mm($pageW - $frameLeft - $frameRight), $this->mm(297 - $frameTop - $frameBottom),
            ['stroke' => '#dc0814', 'strokeWidth' => 4, 'fill' => '']
        );

        // --- Docheader-Tabelle ---
        // 3 Spalten: 18% | 64% | 18% der contentW (174mm)
        // Starts at body padding (18mm left, 10mm top)
        $tblTop = $padTop;
        $tblLeft = $padLeft;
        $col1W = round($contentW * 0.18);  // ~31mm
        $col2W = round($contentW * 0.64);  // ~111mm
        $col3W = $contentW - $col1W - $col2W; // ~32mm

        // Zeilenhöhen: obere Zeile ~12mm, untere Zeile ~6mm (Seite)
        $row1H = 12;
        $row2H = 6;
        $tblH = $row1H + $row2H;

        // Zelle 1,1: Version (nur obere Zeile hoch)
        // Da rowspan=2 für Spalte 2+3, ist Spalte 1 in 2 Zeilen aufgeteilt
        $o[] = $this->rect($this->mm($tblLeft), $this->mm($tblTop), $this->mm($col1W), $this->mm($row1H), [
            'stroke' => '#000000', 'strokeWidth' => 1, 'fill' => '',
        ]);
        $o[] = $this->text('Version 1.0', $this->mm($tblLeft + 2), $this->mm($tblTop + 2), $this->mm($col1W - 4), [
            'fontSize' => 8.5, 'fontWeight' => 'bold',
        ]);

        // Zelle 2,1: Seite
        $o[] = $this->rect($this->mm($tblLeft), $this->mm($tblTop + $row1H), $this->mm($col1W), $this->mm($row2H), [
            'stroke' => '#000000', 'strokeWidth' => 1, 'fill' => '',
        ]);
        $o[] = $this->text('Seite', $this->mm($tblLeft + 2), $this->mm($tblTop + $row1H + 1), $this->mm($col1W - 4), [
            'fontSize' => 8.5, 'fontWeight' => 'bold',
        ]);

        // Zelle 1-2,2: Dokumenttitel + Org (rowspan=2, volle Höhe)
        $col2Left = $tblLeft + $col1W;
        $o[] = $this->rect($this->mm($col2Left), $this->mm($tblTop), $this->mm($col2W), $this->mm($tblH), [
            'stroke' => '#000000', 'strokeWidth' => 1, 'fill' => '',
        ]);
        $docTitle = $this->extractDocTitle($html);
        $o[] = $this->text($docTitle, $this->mm($col2Left + 2), $this->mm($tblTop + 3), $this->mm($col2W - 4), [
            'fontSize' => 8.5, 'fontWeight' => 'bold', 'textAlign' => 'center',
        ]);
        $o[] = $this->text('{{ RP_ORGTYPE }} {{ SERVER_CITY }}', $this->mm($col2Left + 2), $this->mm($tblTop + 9), $this->mm($col2W - 4), [
            'fontSize' => 8.5, 'textAlign' => 'center',
            'custom' => ['elementType' => 'system_var', 'varName' => 'RP_ORGTYPE'],
        ]);

        // Zelle 1-2,3: Wappen (rowspan=2, volle Höhe)
        $col3Left = $col2Left + $col2W;
        $o[] = $this->rect($this->mm($col3Left), $this->mm($tblTop), $this->mm($col3W), $this->mm($tblH), [
            'stroke' => '#000000', 'strokeWidth' => 1, 'fill' => '',
        ]);
        // Wappen-Platzhalter Text (wird im Editor durch Bild ersetzt)
        $o[] = $this->text('Wappen', $this->mm($col3Left + 2), $this->mm($tblTop + 5), $this->mm($col3W - 4), [
            'fontSize' => 8, 'textAlign' => 'center', 'fill' => '#999999',
            'custom' => ['elementType' => 'static_text'],
        ]);

        // --- Haupttitel: h1 (margin: 12mm 0 10mm 0, 20pt bold #dc0814, center) ---
        $y = $tblTop + $tblH + 6 + 12; // nach Tabelle (6mm margin-bottom) + 12mm margin-top
        $hauptTitel = $this->extractMainTitle($html);
        $o[] = $this->text($hauptTitel, $this->mm($padLeft), $this->mm($y), $this->mm($contentW), [
            'fontSize' => 20, 'fontWeight' => 'bold', 'fill' => '#dc0814', 'textAlign' => 'center',
        ]);
        $y += 10 + 10; // h1 Höhe (~10mm) + margin-bottom 10mm

        // --- Content-Blöcke (.content: 11.5pt, center, p margin 4mm) ---
        $contentBlocks = $this->extractContentBlocks($html);

        foreach ($contentBlocks as $block) {
            $text = $block['text'];
            $isImportant = $block['important'] ?? false;
            $extraMarginTop = $block['marginTop'] ?? 0;

            if ($extraMarginTop > 0) {
                $y += $extraMarginTop;
            } else {
                $y += $isImportant ? 6 : 4; // .important margin 6mm, normal p margin 4mm
            }

            $fontSize = $isImportant ? 15 : 11.5;
            $fontWeight = $isImportant ? 'bold' : 'normal';

            // Erkennung ob Platzhalter
            $custom = ['elementType' => 'static_text'];
            if (preg_match('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $text, $m)) {
                $custom = ['elementType' => 'field_placeholder', 'fieldName' => $m[1]];
            }

            $o[] = $this->text($text, $this->mm($padLeft), $this->mm($y), $this->mm($contentW), [
                'fontSize' => $fontSize, 'fontWeight' => $fontWeight,
                'textAlign' => 'center', 'lineHeight' => 1.5,
                'custom' => $custom,
            ]);

            // Geschätzte Texthöhe
            $lineH = $fontSize * self::PT_TO_PX / self::PX_PER_MM;
            $y += $lineH * 1.2;
        }

        // --- Footer-Bereich (linksbündig, unterhalb des Contents) ---
        // .date-location: margin-top 12mm, 10pt
        $y += 12;
        $o[] = $this->text('{{ SERVER_CITY }}, den {{ ausstellungsdatum }}', $this->mm($padLeft), $this->mm($y), $this->mm(100), [
            'fontSize' => 10,
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'SERVER_CITY'],
        ]);

        // .document-reference: margin-top 4mm, 9pt, #333
        $y += 7;
        $o[] = $this->text('Ihr Zeichen: {{ document_id }}', $this->mm($padLeft), $this->mm($y), $this->mm(100), [
            'fontSize' => 9, 'fill' => '#333333',
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'document_id', 'fieldLabel' => 'Dokumenten-ID'],
        ]);

        // .issuer-info: margin-top 6mm, 10pt
        $y += 8;
        $o[] = $this->text('{{ issuer.fullname }}', $this->mm($padLeft), $this->mm($y), $this->mm(80), [
            'fontSize' => 10, 'fontWeight' => 'bold',
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'issuer.fullname', 'fieldLabel' => 'Aussteller-Name'],
        ]);
        $y += 5;
        $o[] = $this->text('{{ issuer.dienstgrad_text }}', $this->mm($padLeft), $this->mm($y), $this->mm(80), [
            'fontSize' => 10,
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'issuer.dienstgrad_text', 'fieldLabel' => 'Aussteller-Dienstgrad'],
        ]);

        // .electronic-note: margin-top 2mm, 8pt italic #666
        $y += 7;
        $o[] = $this->text('— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —', $this->mm($padLeft), $this->mm($y), $this->mm($contentW), [
            'fontSize' => 8, 'fontStyle' => 'italic', 'fill' => '#666666',
        ]);

        // --- Disclaimer-Leiste (.disclaimer: fixed bottom 7mm, left/right 13mm) ---
        if (str_contains($html, 'disclaimer')) {
            $discTop = 297 - $frameBottom; // bottom 18mm → top = 279mm
            $discH = $frameBottom - $frameBottom + 11; // ~11mm hoch
            $discTop = 297 - 18; // position: bottom 7mm → Unterseite bei 290mm, Leiste ~11mm hoch → top ~279mm

            $o[] = $this->rect($this->mm($frameLeft), $this->mm(279), $this->mm($pageW - $frameLeft - $frameRight), $this->mm(11), [
                'fill' => '#dc0814', 'stroke' => '', 'strokeWidth' => 0,
            ]);

            $disclaimerText = $this->extractDisclaimer($html);
            $o[] = $this->text($disclaimerText, $this->mm($frameLeft + 2), $this->mm(280), $this->mm($pageW - $frameLeft - $frameRight - 4), [
                'fontSize' => 7.5, 'fill' => '#ffffff', 'textAlign' => 'center', 'lineHeight' => 1.3,
            ]);
        }

        return $o;
    }

    // =========================================================================
    // Brief-Layout
    // CSS: body padding 20mm 25mm (top/bottom, left/right)
    // .header-right: float right, width 35%
    // .header-left: width 60%, 10pt, line-height 1.3
    // .logo-placeholder: padding 5mm 2.5mm, margin-bottom 4mm
    // .date-box: margin-top 4mm; .date-label: 10pt; .date-value: 12pt bold
    // .recipient: margin 10mm 0, 11pt, line-height 1.5
    // .title: 15pt bold, margin 12mm 0 8mm 0
    // .letter-content: 11pt, line-height 1.6, p margin 4mm
    // .reasoning: 2px solid #000, padding 1mm, margin 6mm 0, min-height 30mm
    // =========================================================================

    private function convertBriefLayout(string $html, array $fields): array
    {
        $o = [];

        $padTop = 20;
        $padLR = 25;
        $pageW = 210;
        $contentW = $pageW - $padLR * 2; // 160mm

        // Berechnung der Spaltenbreiten (CSS: header-right 35%, header-left 60%)
        $rightW = round($contentW * 0.35);  // ~56mm
        $leftW = round($contentW * 0.60);   // ~96mm
        $rightLeft = $pageW - $padLR - $rightW; // rechte Spalte Startposition

        // --- Header Links: Org-Info (.header-left: 10pt, line-height 1.3) ---
        $y = $padTop;
        $o[] = $this->text('{{ RP_ORGTYPE }} {{ SERVER_CITY }}', $this->mm($padLR), $this->mm($y), $this->mm($leftW), [
            'fontSize' => 10, 'lineHeight' => 1.3,
            'custom' => ['elementType' => 'system_var', 'varName' => 'RP_ORGTYPE'],
        ]);
        $o[] = $this->text('{{ RP_STREET }}', $this->mm($padLR), $this->mm($y + 5), $this->mm($leftW), [
            'fontSize' => 10, 'lineHeight' => 1.3,
            'custom' => ['elementType' => 'system_var', 'varName' => 'RP_STREET'],
        ]);
        $o[] = $this->text('{{ RP_ZIP }} {{ SERVER_CITY }}', $this->mm($padLR), $this->mm($y + 10), $this->mm($leftW), [
            'fontSize' => 10, 'lineHeight' => 1.3,
            'custom' => ['elementType' => 'system_var', 'varName' => 'RP_ZIP'],
        ]);

        // --- Header Rechts: Logo (.logo-placeholder: padding 5mm 2.5mm, margin-bottom 4mm) ---
        // Logo-Bereich: padding 5mm oben/unten, 2.5mm links/rechts
        if (str_contains($html, 'logo_base64')) {
            $o[] = $this->text('[Logo]', $this->mm($rightLeft), $this->mm($y), $this->mm($rightW), [
                'fontSize' => 9, 'fill' => '#666666', 'textAlign' => 'center',
                'custom' => ['elementType' => 'system_image', 'imageType' => 'logo'],
            ]);
        }

        // --- Header Rechts: Datum (.date-box: margin-top 4mm) ---
        $dateY = $y + 5 + 4 + 5 + 4; // nach Logo (5mm pad + ~Höhe + 4mm margin-bottom) + 4mm date-box margin
        // .date-label: 10pt, margin-bottom 2mm
        $o[] = $this->text('Datum', $this->mm($rightLeft), $this->mm($dateY), $this->mm($rightW), [
            'fontSize' => 10, 'textAlign' => 'right',
            'custom' => ['elementType' => 'static_text'],
        ]);
        // .date-value: 12pt bold
        $o[] = $this->text('{{ ausstellungsdatum }}', $this->mm($rightLeft), $this->mm($dateY + 5), $this->mm($rightW), [
            'fontSize' => 12, 'fontWeight' => 'bold', 'textAlign' => 'right',
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'ausstellungsdatum', 'fieldLabel' => 'Ausstellungsdatum'],
        ]);

        // --- Empfänger (.recipient: margin 10mm 0, 11pt, line-height 1.5) ---
        // .header margin-bottom 8mm + clear + recipient margin-top 10mm
        $recipientY = $padTop + 8 + 10 + 10;
        $o[] = $this->text('{{ anrede_text }}', $this->mm($padLR), $this->mm($recipientY), $this->mm($leftW), [
            'fontSize' => 11, 'lineHeight' => 1.5,
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'anrede_text', 'fieldLabel' => 'Anrede'],
        ]);
        $o[] = $this->text('{{ erhalter }}', $this->mm($padLR), $this->mm($recipientY + 6), $this->mm($leftW), [
            'fontSize' => 11, 'lineHeight' => 1.5,
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'erhalter', 'fieldLabel' => 'Empfänger-Name'],
        ]);
        $o[] = $this->text('{{ RP_ZIP }} {{ SERVER_CITY }}', $this->mm($padLR), $this->mm($recipientY + 12), $this->mm($leftW), [
            'fontSize' => 11, 'lineHeight' => 1.5,
            'custom' => ['elementType' => 'system_var', 'varName' => 'RP_ZIP'],
        ]);

        // --- Titel (.title: 15pt bold, margin 12mm 0 8mm 0) ---
        $titleY = $recipientY + 12 + 10 + 12; // nach Empfänger (10mm margin-bottom) + 12mm title margin-top
        $briefTitle = $this->extractBriefTitle($html);
        $o[] = $this->text($briefTitle, $this->mm($padLR), $this->mm($titleY), $this->mm($contentW), [
            'fontSize' => 15, 'fontWeight' => 'bold',
        ]);

        // --- Brieftext (.letter-content: 11pt, line-height 1.6, p margin 4mm) ---
        $y = $titleY + 8 + 8; // Titelhöhe (~8mm) + margin-bottom 8mm
        $letterLines = $this->extractLetterContent($html);

        foreach ($letterLines as $line) {
            $text = $line['text'];
            $type = $line['type'] ?? 'text';

            if ($type === 'reasoning') {
                // .reasoning: 2px solid #000, padding 1mm, margin 6mm 0, min-height 30mm
                $y += 6; // margin-top 6mm
                $o[] = $this->rect($this->mm($padLR), $this->mm($y), $this->mm($contentW), $this->mm(30), [
                    'stroke' => '#000000', 'strokeWidth' => 2, 'fill' => '',
                ]);
                $o[] = $this->text($text, $this->mm($padLR + 1), $this->mm($y + 1), $this->mm($contentW - 2), [
                    'fontSize' => 11, 'lineHeight' => 1.6,
                    'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'inhalt', 'fieldLabel' => 'Inhalt/Begründung'],
                ]);
                $y += 30 + 6; // min-height 30mm + margin-bottom 6mm
            } else {
                $y += 4; // p margin-top 4mm
                $custom = ['elementType' => 'static_text'];
                if (preg_match('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $text, $m)) {
                    $custom = ['elementType' => 'field_placeholder', 'fieldName' => $m[1]];
                }
                $o[] = $this->text($text, $this->mm($padLR), $this->mm($y), $this->mm($contentW), [
                    'fontSize' => 11, 'lineHeight' => 1.6,
                    'custom' => $custom,
                ]);
                // Texthöhe: ~5mm pro Zeile bei 11pt
                $estimatedLines = max(1, ceil(mb_strlen($text) / 70));
                $y += $estimatedLines * 5;
            }
        }

        // --- Zusätzliche Template-Felder die nicht im HTML vorkommen ---
        $placedFields = ['erhalter', 'erhalter_gebdat', 'anrede', 'ausstellungsdatum', 'inhalt'];
        foreach ($fields as $f) {
            if (in_array($f['field_name'], $placedFields)) continue;
            if (str_contains($html, '{{ ' . $f['field_name'] . ' }}')
                || str_contains($html, '{{' . $f['field_name'] . '}}')) continue;

            $y += 4;
            if (in_array($f['field_type'], ['richtext', 'textarea'])) {
                $o[] = $this->text($f['field_label'] . ':', $this->mm($padLR), $this->mm($y), $this->mm($contentW), [
                    'fontSize' => 11, 'fontWeight' => 'bold',
                ]);
                $y += 7;
                $o[] = $this->text('{{ ' . $f['field_name'] . ' }}', $this->mm($padLR), $this->mm($y), $this->mm($contentW), [
                    'fontSize' => 11, 'lineHeight' => 1.6,
                    'custom' => ['elementType' => 'field_placeholder', 'fieldName' => $f['field_name'], 'fieldLabel' => $f['field_label']],
                ]);
                $y += 20;
            } else {
                $o[] = $this->text($f['field_label'] . ': {{ ' . $f['field_name'] . ' }}', $this->mm($padLR), $this->mm($y), $this->mm($contentW), [
                    'fontSize' => 11,
                    'custom' => ['elementType' => 'field_placeholder', 'fieldName' => $f['field_name'], 'fieldLabel' => $f['field_label']],
                ]);
                $y += 8;
            }
        }

        // --- Footer ---
        // .date-location: margin-top 12mm, 10pt
        $y += 12;
        $o[] = $this->text('{{ SERVER_CITY }}, den {{ ausstellungsdatum }}', $this->mm($padLR), $this->mm($y), $this->mm(100), [
            'fontSize' => 10,
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'SERVER_CITY'],
        ]);

        // .document-reference: margin-top 4mm, 9pt, #333
        $y += 6;
        $o[] = $this->text('Ihr Zeichen: {{ document_id }}', $this->mm($padLR), $this->mm($y), $this->mm(100), [
            'fontSize' => 9, 'fill' => '#333333',
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'document_id', 'fieldLabel' => 'Dokumenten-ID'],
        ]);

        // .issuer-info: margin-top 6mm, 10pt
        $y += 8;
        $o[] = $this->text('{{ issuer.fullname }}', $this->mm($padLR), $this->mm($y), $this->mm(80), [
            'fontSize' => 10, 'fontWeight' => 'bold',
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'issuer.fullname', 'fieldLabel' => 'Aussteller-Name'],
        ]);
        $y += 5;
        $o[] = $this->text('{{ issuer.dienstgrad_text }}', $this->mm($padLR), $this->mm($y), $this->mm(80), [
            'fontSize' => 10,
            'custom' => ['elementType' => 'field_placeholder', 'fieldName' => 'issuer.dienstgrad_text', 'fieldLabel' => 'Aussteller-Dienstgrad'],
        ]);

        // .electronic-note: margin-top 2mm, 8pt italic #666
        $y += 7;
        $o[] = $this->text('— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —', $this->mm($padLR), $this->mm($y), $this->mm($contentW), [
            'fontSize' => 8, 'fontStyle' => 'italic', 'fill' => '#666666',
        ]);

        return $o;
    }

    // =========================================================================
    // HTML-Parsing
    // =========================================================================

    private function extractDocTitle(string $html): string
    {
        // <td ... text-align: center ...><strong>Ernennungsurkunde</strong>
        if (preg_match('/<td[^>]*text-align:\s*center[^>]*>.*?<strong>([^<]+)<\/strong>/s', $html, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/<title>([^›<]+)/i', $html, $m)) {
            return trim($m[1]);
        }
        return 'Dokument';
    }

    private function extractMainTitle(string $html): string
    {
        if (preg_match('/<h1>([^<]+)<\/h1>/i', $html, $m)) {
            return trim($m[1]);
        }
        return 'URKUNDE';
    }

    private function extractBriefTitle(string $html): string
    {
        if (preg_match('/<div class="title">([^<]+)<\/div>/i', $html, $m)) {
            return trim($m[1]);
        }
        return 'Dokument';
    }

    private function extractDisclaimer(string $html): string
    {
        if (preg_match('/<div class="disclaimer">\s*(.*?)\s*<\/div>/s', $html, $m)) {
            $text = strip_tags($m[1]);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            return preg_replace('/\s+/', ' ', trim($text));
        }
        return '';
    }

    /**
     * Extrahiert Content-Blöcke aus dem Urkunden-Layout (.content div).
     * Liest margin-top Inline-Styles und class="important".
     */
    private function extractContentBlocks(string $html): array
    {
        $blocks = [];

        if (!preg_match('/<div class="content">(.*?)<\/div>\s*\n?\s*<div class="date-location"/s', $html, $contentMatch)) {
            return $blocks;
        }

        $content = $contentMatch[1];
        preg_match_all('/<p([^>]*)>(.*?)<\/p>/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attrs = $match[1];
            $innerHtml = $match[2];

            $text = strip_tags(trim($innerHtml));
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);

            $isImportant = str_contains($attrs, 'important');

            // Inline margin-top extrahieren
            $extraMargin = 0;
            if (preg_match('/margin-top:\s*(\d+)mm/', $attrs, $mm)) {
                $extraMargin = (int)$mm[1];
            }

            if (!empty($text)) {
                $blocks[] = [
                    'text' => $text,
                    'important' => $isImportant,
                    'marginTop' => $extraMargin,
                ];
            }
        }

        return $blocks;
    }

    /**
     * Extrahiert den Brieftext (.letter-content) inkl. Reasoning-Box.
     */
    private function extractLetterContent(string $html): array
    {
        $lines = [];

        if (!preg_match('/<div class="letter-content">(.*?)<\/div>\s*\n?\s*<div class="date-location"/s', $html, $match)) {
            return $lines;
        }

        $content = $match[1];

        // Reasoning-Box vorhanden?
        if (preg_match('/<div class="reasoning">\s*(.*?)\s*<\/div>/s', $content, $reasonMatch)) {
            // Alles vor der Reasoning-Box
            $beforeReasoning = substr($content, 0, strpos($content, '<div class="reasoning">'));
            $this->extractParagraphs($beforeReasoning, $lines);

            // Reasoning-Box
            $reasonText = strip_tags(trim($reasonMatch[1]));
            $reasonText = html_entity_decode($reasonText, ENT_QUOTES, 'UTF-8');
            $lines[] = ['text' => $reasonText ?: '{{ inhalt }}', 'type' => 'reasoning'];
        } else {
            // Nur Absätze und Field-Sections
            // field-section Behandlung
            if (str_contains($content, 'field-section')) {
                preg_match_all('/<div class="field-section">\s*<strong>([^<]+)<\/strong>\s*<div class="field-box">([^<]*(?:<[^>]*>[^<]*)*)<\/div>\s*<\/div>/s', $content, $fieldMatches, PREG_SET_ORDER);
                foreach ($fieldMatches as $fm) {
                    $label = trim($fm[1]);
                    $value = strip_tags(trim($fm[2]));
                    $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                    $lines[] = ['text' => $label . ' ' . trim($value), 'type' => 'text'];
                }
            }
            // Normale Absätze
            $this->extractParagraphs($content, $lines);
        }

        return $lines;
    }

    private function extractParagraphs(string $html, array &$lines): void
    {
        preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $html, $matches);

        foreach ($matches[1] as $innerHtml) {
            $text = strip_tags(trim($innerHtml));
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);

            if (!empty($text)) {
                $lines[] = ['text' => $text, 'type' => 'text'];
            }
        }
    }

    // =========================================================================
    // Fabric.js Object Builders
    // =========================================================================

    /** mm → Canvas-Pixel */
    private function mm(float $mm): float
    {
        return round($mm * self::PX_PER_MM, 1);
    }

    private function text(string $text, float $left, float $top, float $width, array $opts = []): array
    {
        $obj = [
            'type' => 'textbox',
            'left' => $left,
            'top' => $top,
            'width' => $width,
            'text' => $text,
            'fontSize' => round(($opts['fontSize'] ?? 11) * self::PT_TO_PX),
            'fontFamily' => 'DejaVu Sans',
            'fill' => $opts['fill'] ?? '#000000',
            'textAlign' => $opts['textAlign'] ?? 'left',
            'lineHeight' => $opts['lineHeight'] ?? 1.16,
            'originX' => 'left',
            'originY' => 'top',
            'custom' => $opts['custom'] ?? ['elementType' => 'static_text'],
        ];

        if (!empty($opts['fontWeight']) && $opts['fontWeight'] !== 'normal') {
            $obj['fontWeight'] = $opts['fontWeight'];
        }
        if (!empty($opts['fontStyle']) && $opts['fontStyle'] !== 'normal') {
            $obj['fontStyle'] = $opts['fontStyle'];
        }

        return $obj;
    }

    private function rect(float $left, float $top, float $width, float $height, array $opts = []): array
    {
        $obj = [
            'type' => 'rect',
            'left' => $left,
            'top' => $top,
            'width' => $width,
            'height' => $height,
            'fill' => $opts['fill'] ?? '',
            'originX' => 'left',
            'originY' => 'top',
            'custom' => ['elementType' => 'shape'],
        ];

        if (!empty($opts['stroke'])) {
            $obj['stroke'] = $opts['stroke'];
            $obj['strokeWidth'] = $opts['strokeWidth'] ?? 1;
        }

        return $obj;
    }
}
