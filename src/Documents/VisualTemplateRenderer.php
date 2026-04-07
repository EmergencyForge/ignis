<?php

namespace App\Documents;

use PDO;

class VisualTemplateRenderer
{
    use DocumentRenderingTrait;

    private PDO $pdo;
    private TemplateAssetManager $assetManager;
    private TemplateLayoutManager $layoutManager;

    /** Pixel-to-mm conversion factor (96dpi) */
    private const PX_PER_MM = 3.7795;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->assetManager = new TemplateAssetManager($pdo);
        $this->layoutManager = new TemplateLayoutManager($pdo);
    }

    /**
     * Rendert ein Dokument mit visuellem Template zu HTML
     */
    public function renderDocument(array $doc): string
    {
        $layout = $this->layoutManager->getLayoutById((int) $doc['layout_id']);
        if (!$layout) {
            throw new \Exception('Visuelles Layout nicht gefunden für Template');
        }

        $canvasData = json_decode($layout['canvas_json'], true);
        // Falls doppelt JSON-encodiert
        if (is_string($canvasData)) {
            $canvasData = json_decode($canvasData, true);
        }
        if (!$canvasData || !is_array($canvasData)) {
            throw new \Exception('Ungültige Layout-Daten');
        }

        // Dokumentdaten vorbereiten
        $fieldValues = $this->prepareFieldValues($doc);
        $isDraft = !empty($doc['template_id']) && $this->isTemplateDraft((int) $doc['template_id']);

        return $this->renderCanvasToHtml($canvasData, $fieldValues, $isDraft);
    }

    /**
     * Rendert eine Vorschau (direkt mit Canvas-JSON, ohne gespeichertes Dokument)
     */
    public function renderPreview(int $templateId, array $sampleData = [], ?string $canvasJsonOverride = null): string
    {
        if ($canvasJsonOverride) {
            $canvasData = json_decode($canvasJsonOverride, true);
            // Falls doppelt JSON-encodiert (String-in-String)
            if (is_string($canvasData)) {
                $canvasData = json_decode($canvasData, true);
            }
        } else {
            $layout = $this->layoutManager->getLayout($templateId);
            if (!$layout) {
                return $this->renderEmptyPreview();
            }
            $canvasData = json_decode($layout['canvas_json'], true);
            if (is_string($canvasData)) {
                $canvasData = json_decode($canvasData, true);
            }
        }

        if (!$canvasData || !is_array($canvasData)) {
            return $this->renderEmptyPreview();
        }

        // Feldwerte aufloesen (DB-IDs → Texte, Datum formatieren, etc.)
        $fieldValues = $this->preparePreviewFieldValues($templateId, $sampleData);
        $isDraft = $this->isTemplateDraft($templateId);

        return $this->renderCanvasToHtml($canvasData, $fieldValues, $isDraft);
    }

    /** Feldnamen die HTML enthalten dürfen (Rich-Text) */
    private array $rawFields = [];

    /**
     * Konvertiert Canvas-JSON zu HTML
     */
    private function renderCanvasToHtml(array $canvasData, array $fieldValues, bool $isDraft = false): string
    {
        $objects = $canvasData['objects'] ?? [];
        $bgColor = $canvasData['background'] ?? '#ffffff';

        $elementsHtml = '';
        $objectCount = count($objects);
        foreach ($objects as $idx => $obj) {
            $rendered = $this->fabricObjectToHtml($obj, $fieldValues);
            $elementsHtml .= $rendered;
        }

        // Debug-Kommentar für Vorschau
        $elementsHtml .= "<!-- Rendered {$objectCount} objects -->\n";

        // Hintergrundbild
        $bgImageCss = '';
        if (!empty($canvasData['backgroundImage'])) {
            $bgSrc = $this->resolveImageSrc($canvasData['backgroundImage']);
            if ($bgSrc) {
                $bgImageCss = "background-image: url('{$bgSrc}'); background-size: cover; background-position: center;";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Dokument</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
        }
        .page {
            position: relative;
            width: 210mm;
            height: 297mm;
            background-color: {$bgColor};
            {$bgImageCss}
            overflow: hidden;
        }
        .draft-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            font-weight: bold;
            color: rgba(200, 0, 0, 0.12);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            white-space: nowrap;
            pointer-events: none;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="page">
        {$elementsHtml}
        {$this->renderDraftWatermark($isDraft)}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Konvertiert ein einzelnes Fabric.js-Objekt zu HTML
     */
    private function fabricObjectToHtml(array $obj, array $fieldValues): string
    {
        $type = strtolower($obj['type'] ?? '');

        switch ($type) {
            case 'textbox':
            case 'text':
            case 'i-text':
                return $this->renderTextElement($obj, $fieldValues);

            case 'image':
            case 'fabricimage':
                return $this->renderImageElement($obj);

            case 'rect':
                return $this->renderRectElement($obj);

            case 'line':
                return $this->renderLineElement($obj);

            case 'group':
            case 'activeselection':
                return $this->renderGroupElement($obj, $fieldValues);

            default:
                // Unbekannter Typ — debug-info als Kommentar
                return "<!-- unknown type: " . htmlspecialchars($obj['type'] ?? 'null') . " -->\n";
        }
    }

    /**
     * Rendert ein Text-Element
     */
    private function renderTextElement(array $obj, array $fieldValues): string
    {
        $left = $this->pxToMm($obj['left'] ?? 0);
        $top = $this->pxToMm($obj['top'] ?? 0);
        $width = $this->pxToMm(($obj['width'] ?? 100) * ($obj['scaleX'] ?? 1));
        $angle = $obj['angle'] ?? 0;

        // Seitenzahl-Element: Platzhalter-Text für Dompdf page_script
        $custom = $obj['custom'] ?? [];
        $isPageNumber = ($custom['elementType'] ?? '') === 'page_number';

        // Text mit Platzhalter-Ersetzung
        $text = $obj['text'] ?? '';
        if (!$isPageNumber) {
            $text = $this->replacePlaceholders($text, $fieldValues);
        }

        // CSS-Properties sammeln
        $css = [
            'position' => 'absolute',
            'left' => "{$left}mm",
            'top' => "{$top}mm",
            'width' => "{$width}mm",
            'font-family' => $this->sanitizeFontFamily($obj['fontFamily'] ?? 'DejaVu Sans'),
            'font-size' => round(($obj['fontSize'] ?? 14) / 1.333, 1) . 'pt',
            'color' => $this->sanitizeCssColor($obj['fill'] ?? '#000000'),
            'line-height' => $obj['lineHeight'] ?? 1.16,
            'text-align' => $obj['textAlign'] ?? 'left',
            'word-wrap' => 'break-word',
            'overflow' => 'hidden',
        ];

        if (!empty($obj['fontWeight']) && $obj['fontWeight'] !== 'normal') {
            $css['font-weight'] = $obj['fontWeight'];
        }
        if (!empty($obj['fontStyle']) && $obj['fontStyle'] !== 'normal') {
            $css['font-style'] = $obj['fontStyle'];
        }
        if (!empty($obj['underline'])) {
            $css['text-decoration'] = 'underline';
        }
        if (!empty($obj['backgroundColor'])) {
            $css['background-color'] = $this->sanitizeCssColor($obj['backgroundColor']);
        }
        if (!empty($obj['stroke']) && ($obj['strokeWidth'] ?? 0) > 0) {
            $css['border'] = ((int) ($obj['strokeWidth'] ?? 1)) . 'px solid ' . $this->sanitizeCssColor($obj['stroke']);
        }
        if (isset($obj['opacity']) && $obj['opacity'] < 1) {
            $css['opacity'] = $obj['opacity'];
        }
        if ($angle != 0) {
            $css['transform'] = "rotate({$angle}deg)";
            $css['transform-origin'] = 'top left';
        }

        $style = $this->cssArrayToString($css);

        // Text mit Character-Level Styles (bold, italic, underline pro Zeichen) zu HTML konvertieren
        $styles = $obj['styles'] ?? [];
        $htmlText = $this->renderStyledText($text, $styles, $obj);

        // Seitenzahl-Element: Data-Attribute für DocumentPDFGenerator
        if ($isPageNumber) {
            $format = htmlspecialchars($custom['pageNumberFormat'] ?? '{page} von {pages}', ENT_QUOTES, 'UTF-8');
            return "<div style=\"{$style}\" data-page-number=\"true\" data-pn-left=\"{$left}\" data-pn-top=\"{$top}\" data-pn-format=\"{$format}\">{$htmlText}</div>\n";
        }

        return "<div style=\"{$style}\">{$htmlText}</div>\n";
    }

    /**
     * Rendert ein Bild-Element
     */
    private function renderImageElement(array $obj): string
    {
        $left = $this->pxToMm($obj['left'] ?? 0);
        $top = $this->pxToMm($obj['top'] ?? 0);
        $angle = $obj['angle'] ?? 0;

        $src = $this->resolveImageSrc($obj);
        if (!$src) {
            $debugSrc = $obj['src'] ?? $obj['custom']['imageType'] ?? 'unknown';
            return "<!-- image not found: " . htmlspecialchars($debugSrc) . " -->\n";
        }

        // Fabric.js speichert die natürlichen Bildmaße in width/height
        // und die Skalierung in scaleX/scaleY.
        // Die Anzeigegröße ist: naturalWidth * scaleX (in Canvas-Pixel)
        $scaleX = $obj['scaleX'] ?? 1;
        $scaleY = $obj['scaleY'] ?? 1;
        $naturalW = $obj['width'] ?? 100;
        $naturalH = $obj['height'] ?? 100;

        $displayW = $naturalW * $scaleX;
        $displayH = $naturalH * $scaleY;

        // 1:1 Canvas → PDF Konvertierung (px → mm)
        $width = $this->pxToMm($displayW);
        $height = $this->pxToMm($displayH);

        $css = [
            'position' => 'absolute',
            'left' => "{$left}mm",
            'top' => "{$top}mm",
            'width' => "{$width}mm",
            'height' => "{$height}mm",
        ];

        if (isset($obj['opacity']) && $obj['opacity'] < 1) {
            $css['opacity'] = $obj['opacity'];
        }
        if ($angle != 0) {
            $css['transform'] = "rotate({$angle}deg)";
            $css['transform-origin'] = 'top left';
        }

        $style = $this->cssArrayToString($css);

        return "<img src=\"{$src}\" style=\"{$style}\" alt=\"\" />\n";
    }

    /**
     * Rendert ein Rechteck-Element
     */
    private function renderRectElement(array $obj): string
    {
        $left = $this->pxToMm($obj['left'] ?? 0);
        $top = $this->pxToMm($obj['top'] ?? 0);
        $width = $this->pxToMm(($obj['width'] ?? 100) * ($obj['scaleX'] ?? 1));
        $height = $this->pxToMm(($obj['height'] ?? 100) * ($obj['scaleY'] ?? 1));

        $css = [
            'position' => 'absolute',
            'left' => "{$left}mm",
            'top' => "{$top}mm",
            'width' => "{$width}mm",
            'height' => "{$height}mm",
        ];

        $fill = $obj['fill'] ?? 'transparent';
        if ($fill && $fill !== 'transparent') {
            $css['background-color'] = $this->sanitizeCssColor($fill);
        }

        if (!empty($obj['stroke']) && ($obj['strokeWidth'] ?? 0) > 0) {
            $css['border'] = ((int) ($obj['strokeWidth'] ?? 1)) . 'px solid ' . $this->sanitizeCssColor($obj['stroke']);
        }

        if (isset($obj['opacity']) && $obj['opacity'] < 1) {
            $css['opacity'] = $obj['opacity'];
        }

        $style = $this->cssArrayToString($css);
        return "<div style=\"{$style}\"></div>\n";
    }

    /**
     * Rendert ein Linien-Element
     */
    private function renderLineElement(array $obj): string
    {
        $left = $this->pxToMm($obj['left'] ?? 0);
        $top = $this->pxToMm($obj['top'] ?? 0);

        // Fabric.js Line hat x1,y1,x2,y2
        $x1 = $obj['x1'] ?? 0;
        $y1 = $obj['y1'] ?? 0;
        $x2 = $obj['x2'] ?? 0;
        $y2 = $obj['y2'] ?? 0;

        $lineWidth = $this->pxToMm(abs($x2 - $x1) * ($obj['scaleX'] ?? 1));
        $strokeWidth = (int) ($obj['strokeWidth'] ?? 1);
        $stroke = $this->sanitizeCssColor($obj['stroke'] ?? '#000000');

        $css = [
            'position' => 'absolute',
            'left' => "{$left}mm",
            'top' => "{$top}mm",
            'width' => "{$lineWidth}mm",
            'height' => '0',
            'border-top' => "{$strokeWidth}px solid {$stroke}",
        ];

        if (isset($obj['opacity']) && $obj['opacity'] < 1) {
            $css['opacity'] = $obj['opacity'];
        }

        $style = $this->cssArrayToString($css);
        return "<div style=\"{$style}\"></div>\n";
    }

    /**
     * Rendert eine Gruppe von Elementen
     */
    private function renderGroupElement(array $obj, array $fieldValues): string
    {
        $custom = $obj['custom'] ?? [];

        $groupLeft = $obj['left'] ?? 0;
        $groupTop = $obj['top'] ?? 0;
        $scaleX = $obj['scaleX'] ?? 1;
        $scaleY = $obj['scaleY'] ?? 1;
        $objects = $obj['objects'] ?? [];

        $html = '';
        foreach ($objects as $child) {
            $child['left'] = (($child['left'] ?? 0) * $scaleX) + $groupLeft;
            $child['top'] = (($child['top'] ?? 0) * $scaleY) + $groupTop;
            if (isset($child['scaleX'])) $child['scaleX'] *= $scaleX;
            if (isset($child['scaleY'])) $child['scaleY'] *= $scaleY;
            $html .= $this->fabricObjectToHtml($child, $fieldValues);
        }

        return $html;
    }


    /**
     * Ersetzt Platzhalter im Text
     */
    private function replacePlaceholders(string $text, array $fieldValues): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($fieldValues) {
            $key = $matches[1];
            // Rich-Text Felder nicht escapen (enthalten gültiges HTML)
            $isRaw = in_array($key, $this->rawFields);

            // Direkt suchen
            if (isset($fieldValues[$key])) {
                $val = $fieldValues[$key];
                if (!is_string($val)) return $matches[0];
                return $isRaw ? $val : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            }
            // Dot-Notation auflösen (z.B. issuer.fullname)
            $parts = explode('.', $key);
            $val = $fieldValues;
            foreach ($parts as $part) {
                if (is_array($val) && isset($val[$part])) {
                    $val = $val[$part];
                } else {
                    return $matches[0]; // Platzhalter beibehalten wenn nicht gefunden
                }
            }
            if (!is_string($val)) return $matches[0];
            return $isRaw ? $val : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }, $text);
    }

    /**
     * Löst die Bild-Quelle auf (Base64 für dompdf).
     * Behandelt: Asset-IDs, System-Bilder, URLs mit BASE_PATH, relative Pfade.
     */
    private function resolveImageSrc(array $obj): ?string
    {
        $custom = $obj['custom'] ?? [];
        $projectRoot = realpath(__DIR__ . '/../../');

        // 1. Template-Asset aus DB (hoechste Prioritaet)
        if (!empty($custom['assetId'])) {
            $base64 = $this->assetManager->getAsBase64((int) $custom['assetId']);
            if ($base64) return $base64;
        }

        // 2. System-Bilder ueber custom.imageType
        if (!empty($custom['imageType'])) {
            $resolved = $this->resolveSystemImage($custom['imageType'], $projectRoot);
            if ($resolved) return $resolved;
        }

        // 3. src-Feld auflösen
        if (!empty($obj['src'])) {
            // Bereits Base64? Direkt zurueckgeben.
            if (str_starts_with($obj['src'], 'data:')) {
                return $obj['src'];
            }

            $src = $this->normalizeSrcPath($obj['src']);

            // System-Bilder anhand des Dateinamens erkennen (egal welcher Pfad davor)
            $systemImages = [
                'schrift_fw_schwarz' => 'assets/img/schrift_fw_schwarz.png',
                'wappen_small' => 'assets/img/wappen_small.png',
            ];
            foreach ($systemImages as $needle => $file) {
                if (strpos($src, $needle) !== false) {
                    $path = $projectRoot . '/' . $file;
                    if (file_exists($path)) return $this->getImageAsBase64($path);
                }
            }

            // Storage-Assets
            if (strpos($src, 'storage/template-assets/') !== false) {
                $path = realpath($projectRoot . '/' . $src);
                if ($path && str_starts_with($path, realpath($projectRoot . '/storage/')) && file_exists($path)) {
                    return $this->getImageAsBase64($path);
                }
            }

            // Allgemeiner Fallback: relativer Pfad (mit Path-Traversal-Schutz)
            $localPath = realpath($projectRoot . '/' . $src);
            if ($localPath && str_starts_with($localPath, $projectRoot) && file_exists($localPath)) {
                return $this->getImageAsBase64($localPath);
            }
        }

        return null;
    }

    /**
     * Laedt ein System-Bild (Logo/Wappen) als Base64.
     */
    private function resolveSystemImage(string $imageType, string $projectRoot): ?string
    {
        $files = [
            'logo' => 'assets/img/schrift_fw_schwarz.png',
            'wappen' => 'assets/img/wappen_small.png',
        ];
        if (!isset($files[$imageType])) return null;

        $path = $projectRoot . '/' . $files[$imageType];
        return file_exists($path) ? $this->getImageAsBase64($path) : null;
    }

    /**
     * Normalisiert einen src-Pfad: entfernt Host, BASE_PATH, fuehrende Slashes.
     * Wandelt z.B. "http://localhost:3000/intraRP/assets/img/logo.png" in "assets/img/logo.png" um.
     */
    private function normalizeSrcPath(string $src): string
    {
        // Volle URL → extrahiere den Pfad
        if (preg_match('#^https?://#', $src)) {
            $parsed = parse_url($src);
            $src = $parsed['path'] ?? '';
        }

        // file:// Protokoll entfernen
        if (str_starts_with($src, 'file://')) {
            $src = substr($src, 7);
        }

        $src = ltrim($src, '/');

        // BASE_PATH entfernen (kann z.B. "intraRP/" sein)
        $basePath = defined('BASE_PATH') ? trim(BASE_PATH, '/') : '';
        if ($basePath !== '' && str_starts_with($src, $basePath . '/')) {
            $src = substr($src, strlen($basePath) + 1);
        }

        // Haeufige Pfad-Varianten normalisieren
        // z.B. "public/assets/img/..." → "assets/img/..."
        $src = preg_replace('#^public/#', '', $src);

        return $src;
    }

    /**
     * Konvertiert eine Bilddatei zu Base64
     */
    private function getImageAsBase64(string $path): ?string
    {
        if (!file_exists($path)) return null;

        $data = file_get_contents($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        $mimeType = $mimeTypes[$extension] ?? 'image/png';

        return "data:{$mimeType};base64," . base64_encode($data);
    }

    /**
     * Bereitet die Feld-Werte eines Dokuments vor
     */
    private function prepareFieldValues(array $doc): array
    {
        $customData = json_decode($doc['custom_data'] ?? '{}', true) ?: [];

        // Lade Aussteller-Daten
        $issuer = $this->getIssuerData((int) ($doc['ausstellerid'] ?? 0));

        // Anrede-Logik
        $anrede = (int) ($doc['anrede'] ?? 0);

        // Lade Template-Felder für Wert-Auflösung
        $templateFields = [];
        $templateConfig = [];
        if (!empty($doc['template_id'])) {
            $stmt = $this->pdo->prepare("
                SELECT tf.*, t.config
                FROM intra_dokument_template_fields tf
                JOIN intra_dokument_templates t ON tf.template_id = t.id
                WHERE tf.template_id = ?
                ORDER BY tf.sort_order
            ");
            $stmt->execute([$doc['template_id']]);
            $templateFields = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($templateFields[0]['config'])) {
                $templateConfig = json_decode($templateFields[0]['config'] ?? '{}', true) ?: [];
            }
        }

        // Rich-Text Felder sammeln (dürfen HTML enthalten)
        $this->rawFields = [];
        foreach ($templateFields as $field) {
            if (in_array($field['field_type'], ['richtext', 'textarea'])) {
                $this->rawFields[] = $field['field_name'];
            }
        }
        // 'inhalt' ist das Legacy-Feld für Begründungen (immer Rich-Text)
        if (!in_array('inhalt', $this->rawFields)) {
            $this->rawFields[] = 'inhalt';
        }

        // Verarbeite Felder: löse geschlechtsspezifische Werte und Select-Optionen auf
        $processedData = [];
        foreach ($templateFields as $field) {
            $fieldName = $field['field_name'];
            $fieldValue = $customData[$fieldName] ?? null;

            if ($field['gender_specific'] && $fieldValue !== null && $fieldValue !== '') {
                $options = $this->getFieldOptions($field['field_type'], $field['field_options'] ?? null);
                $processedData[$fieldName] = $this->resolveGenderSpecificValue($options, $fieldValue, $anrede);
            } elseif ($field['field_type'] === 'select' && $fieldValue !== null && $fieldValue !== '') {
                // Auch nicht-geschlechtsspezifische Selects auflösen
                $options = $this->getFieldOptions('select', $field['field_options'] ?? null);
                $resolved = $this->resolveSelectValue($options, $fieldValue);
                $processedData[$fieldName] = $resolved ?: $fieldValue;
            } else {
                $processedData[$fieldName] = $fieldValue;
            }
        }

        // Legacy: Dienstgrad-Text und Qualifikation auflösen (wie DocumentRenderer)
        $dienstgradText = '';
        if (isset($customData['erhalter_rang']) && $customData['erhalter_rang']) {
            $options = $this->getFieldOptions('db_dg', null);
            $dienstgradText = $this->resolveGenderSpecificValue($options, $customData['erhalter_rang'], $anrede);
        }

        $dienstgrad = '';
        if (isset($customData['erhalter_rang_rd']) && $customData['erhalter_rang_rd']) {
            $options = $this->getFieldOptions('db_rdq', null);
            $dienstgrad = $this->resolveGenderSpecificValue($options, $customData['erhalter_rang_rd'], $anrede);
        }

        $qualifikation = '';
        if (isset($customData['erhalter_quali']) && $customData['erhalter_quali'] !== null) {
            $qualiConfig = $templateConfig['fields']['erhalter_quali'] ?? null;
            if ($qualiConfig && isset($qualiConfig['options'])) {
                $qualifikation = $this->resolveGenderSpecificValue(
                    $qualiConfig['options'],
                    $customData['erhalter_quali'],
                    $anrede
                );
            }
        }

        $values = array_merge($customData, $processedData, [
            'erhalter' => $doc['erhalter'] ?? '',
            'anrede_text' => match ($anrede) { 0 => 'Herr', 1 => 'Frau', default => '' },
            'geehrte' => match ($anrede) { 0 => 'geehrter', 1 => 'geehrte', default => 'geehrte/-r' },
            'zum' => match ($anrede) { 0 => 'zum', 1 => 'zur', default => 'zum/zur' },
            'seine_ihre' => match ($anrede) { 0 => 'seine', 1 => 'ihre', default => 'seine/ihre' },
            'ihm_ihr' => match ($anrede) { 0 => 'ihm', 1 => 'ihr', default => 'ihm/ihr' },
            'dienstgrad_text' => $dienstgradText,
            'dienstgrad' => $dienstgrad,
            'qualifikation' => $qualifikation,
            'erhalter_gebdat_formatted' => !empty($doc['erhalter_gebdat'])
                ? $this->formatGermanDate($doc['erhalter_gebdat'])
                : '',
            'ausstellungsdatum' => !empty($doc['ausstellungsdatum'])
                ? date('d.m.Y', strtotime($doc['ausstellungsdatum']))
                : date('d.m.Y'),
            'document_id' => $doc['docid'] ?? '',
            'issuer' => [
                'fullname' => $issuer['fullname'] ?? '',
                'dienstgrad_text' => $issuer['dienstgrad_text'] ?? '',
                'zusatz' => $issuer['zusatz'] ?? '',
            ],
            'SYSTEM_NAME' => SYSTEM_NAME,
            'SERVER_CITY' => SERVER_CITY,
            'RP_ORGTYPE' => RP_ORGTYPE,
            'RP_STREET' => RP_STREET,
            'RP_ZIP' => RP_ZIP,
            'SERVER_NAME' => SERVER_NAME,
        ]);

        return $values;
    }

    /**
     * Bereitet Vorschau-Feldwerte auf: loest DB-IDs, Select-Werte und
     * geschlechtsspezifische Felder genau wie prepareFieldValues() auf.
     */
    private function preparePreviewFieldValues(int $templateId, array $sampleData): array
    {
        $defaults = $this->getPreviewDefaults();
        $anrede = (int) ($sampleData['anrede'] ?? 0);

        // Template-Felder laden fuer Wert-Aufloesung
        $templateFields = [];
        $templateConfig = [];
        $stmt = $this->pdo->prepare("
            SELECT tf.*, t.config
            FROM intra_dokument_template_fields tf
            JOIN intra_dokument_templates t ON tf.template_id = t.id
            WHERE tf.template_id = ?
            ORDER BY tf.sort_order
        ");
        $stmt->execute([$templateId]);
        $templateFields = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Rich-Text Felder sammeln
        $this->rawFields = ['inhalt'];
        foreach ($templateFields as $field) {
            if (in_array($field['field_type'], ['richtext', 'textarea'])) {
                $this->rawFields[] = $field['field_name'];
            }
        }
        if (!empty($templateFields[0]['config'])) {
            $templateConfig = json_decode($templateFields[0]['config'] ?? '{}', true) ?: [];
        }

        // Felder aufloesen: DB-IDs → Texte, geschlechtsspezifische Werte
        $processedData = [];
        foreach ($templateFields as $field) {
            $fieldName = $field['field_name'];
            $fieldValue = $sampleData[$fieldName] ?? null;

            if ($fieldValue === null || $fieldValue === '') {
                continue;
            }

            if ($field['gender_specific']) {
                $options = $this->getFieldOptions($field['field_type'], $field['field_options'] ?? null);
                $processedData[$fieldName] = $this->resolveGenderSpecificValue($options, $fieldValue, $anrede);
            } elseif (in_array($field['field_type'], ['select', 'db_dg', 'db_rdq'])) {
                $options = $this->getFieldOptions($field['field_type'], $field['field_options'] ?? null);
                $resolved = $this->resolveGenderSpecificValue($options, $fieldValue, $anrede);
                $processedData[$fieldName] = $resolved ?: $fieldValue;
            } else {
                $processedData[$fieldName] = $fieldValue;
            }
        }

        // Legacy-Dienstgrad/Quali aufloesen
        $dienstgradText = '';
        if (!empty($sampleData['erhalter_rang'])) {
            $options = $this->getFieldOptions('db_dg', null);
            $dienstgradText = $this->resolveGenderSpecificValue($options, $sampleData['erhalter_rang'], $anrede);
        }

        $dienstgrad = '';
        if (!empty($sampleData['erhalter_rang_rd'])) {
            $options = $this->getFieldOptions('db_rdq', null);
            $dienstgrad = $this->resolveGenderSpecificValue($options, $sampleData['erhalter_rang_rd'], $anrede);
        }

        $qualifikation = '';
        if (isset($sampleData['erhalter_quali']) && $sampleData['erhalter_quali'] !== '') {
            $qualiConfig = $templateConfig['fields']['erhalter_quali'] ?? null;
            if ($qualiConfig && isset($qualiConfig['options'])) {
                $qualifikation = $this->resolveGenderSpecificValue(
                    $qualiConfig['options'],
                    $sampleData['erhalter_quali'],
                    $anrede
                );
            }
        }

        // Ausstellungsdatum formatieren
        $ausstellungsdatum = date('d.m.Y');
        if (!empty($sampleData['ausstellungsdatum'])) {
            $ts = strtotime($sampleData['ausstellungsdatum']);
            if ($ts) $ausstellungsdatum = date('d.m.Y', $ts);
        }

        return array_merge($defaults, $sampleData, $processedData, [
            'anrede_text' => match ($anrede) { 0 => 'Herr', 1 => 'Frau', default => '' },
            'geehrte' => match ($anrede) { 0 => 'geehrter', 1 => 'geehrte', default => 'geehrte/-r' },
            'zum' => match ($anrede) { 0 => 'zum', 1 => 'zur', default => 'zum/zur' },
            'seine_ihre' => match ($anrede) { 0 => 'seine', 1 => 'ihre', default => 'seine/ihre' },
            'ihm_ihr' => match ($anrede) { 0 => 'ihm', 1 => 'ihr', default => 'ihm/ihr' },
            'dienstgrad_text' => $dienstgradText,
            'dienstgrad' => $dienstgrad,
            'qualifikation' => $qualifikation,
            'ausstellungsdatum' => $ausstellungsdatum,
        ]);
    }

    /**
     * Standard-Vorschaudaten
     */
    private function getPreviewDefaults(): array
    {
        return [
            'erhalter' => 'Max Mustermann',
            'anrede' => 'Herr',
            'anrede_text' => 'Herr',
            'geehrte' => 'geehrter',
            'zum' => 'zum',
            'seine_ihre' => 'seine',
            'ihm_ihr' => 'ihm',
            'ausstellungsdatum' => date('d.m.Y'),
            'ausstelldatum' => date('d.m.Y'),
            'erhalter_gebdat_formatted' => '01. Januar 2000',
            'formatted_date' => '01. Januar 2000',
            'document_id' => 'PREV-IEW0-0000',
            'dienstgrad_text' => 'Brandmeister',
            'dienstgrad' => 'Rettungssanitäter',
            'qualifikation' => 'Truppführer',
            'suspendstring' => 'bis auf unbestimmt',
            'inhalt' => 'Beispieltext für die Vorschau',
            'issuer' => [
                'fullname' => 'Aussteller Name',
                'dienstgrad_text' => 'Dienstgrad',
                'zusatz' => '',
            ],
            'SYSTEM_NAME' => defined('SYSTEM_NAME') ? SYSTEM_NAME : 'System',
            'SERVER_CITY' => defined('SERVER_CITY') ? SERVER_CITY : 'Stadt',
            'RP_ORGTYPE' => defined('RP_ORGTYPE') ? RP_ORGTYPE : 'Organisation',
            'RP_STREET' => defined('RP_STREET') ? RP_STREET : 'Straße 1',
            'RP_ZIP' => defined('RP_ZIP') ? RP_ZIP : '12345',
            'SERVER_NAME' => defined('SERVER_NAME') ? SERVER_NAME : 'Server',
        ];
    }

    private function renderDraftWatermark(bool $isDraft): string
    {
        if (!$isDraft) return '';
        return '<div class="draft-watermark">ENTWURF</div>';
    }

    /**
     * Prueft ob ein Template als Entwurf markiert ist (ueber config-JSON).
     */
    private function isTemplateDraft(int $templateId): bool
    {
        $stmt = $this->pdo->prepare("SELECT config FROM intra_dokument_templates WHERE id = :id");
        $stmt->execute(['id' => $templateId]);
        $config = $stmt->fetchColumn();
        if (!$config) return false;

        $configData = json_decode($config, true);
        return !empty($configData['is_draft']);
    }

    private function renderEmptyPreview(): string
    {
        return '<!DOCTYPE html><html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#666;"><p>Kein Layout vorhanden. Füge Elemente im Editor hinzu und speichere.</p></body></html>';
    }

    // resolveGenderSpecificValue, getFieldOptions, getIssuerData,
    // formatGermanDate, getImageAsBase64 — via DocumentRenderingTrait

    private function resolveSelectValue(array $options, $value): string
    {
        foreach ($options as $option) {
            if ($option['value'] == $value) {
                return $option['label'] ?? '';
            }
        }
        return '';
    }

    /**
     * Konvertiert Text mit Fabric.js Character-Level Styles zu HTML.
     * Erkennt bold, italic, underline, Farbe und Schriftgröße pro Zeichen
     * und erzeugt entsprechende <span>-Tags.
     */
    private function renderStyledText(string $text, array $styles, array $obj): string
    {
        if (empty($styles) || empty($text)) {
            return nl2br(($text));
        }

        $lines = explode("\n", $text);
        $htmlLines = [];

        // Objekt-Defaults (werden überschrieben wenn char-styles vorhanden)
        $defaultWeight = $obj['fontWeight'] ?? 'normal';
        $defaultStyle = $obj['fontStyle'] ?? 'normal';
        $defaultUnderline = !empty($obj['underline']);

        foreach ($lines as $lineIdx => $line) {
            $lineStyles = $styles[$lineIdx] ?? $styles[(string)$lineIdx] ?? [];

            if (empty($lineStyles)) {
                $htmlLines[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                continue;
            }

            // Zeichen einzeln durchgehen, Runs mit gleichen Styles gruppieren
            $chars = mb_str_split($line);
            $html = '';
            $currentSpan = null;
            $currentChars = '';

            foreach ($chars as $ci => $char) {
                $charStyle = $lineStyles[$ci] ?? $lineStyles[(string)$ci] ?? [];

                $isBold = ($charStyle['fontWeight'] ?? $defaultWeight) === 'bold'
                    || (isset($charStyle['fontWeight']) && (int)($charStyle['fontWeight']) >= 700);
                $isItalic = ($charStyle['fontStyle'] ?? $defaultStyle) === 'italic';
                $isUnderline = $charStyle['underline'] ?? $defaultUnderline;
                // textBackgroundColor ignorieren (das sind unsere Platzhalter-Highlights)
                $color = $charStyle['fill'] ?? null;
                $fontSize = $charStyle['fontSize'] ?? null;

                $styleKey = ($isBold ? 'b' : '') . ($isItalic ? 'i' : '') . ($isUnderline ? 'u' : '')
                    . ($color ? 'c' . $color : '') . ($fontSize ? 's' . $fontSize : '');

                if ($styleKey !== $currentSpan) {
                    // Vorherigen Run abschließen
                    if ($currentChars !== '') {
                        $html .= $this->wrapStyledRun($currentChars, $currentSpan);
                    }
                    $currentSpan = $styleKey;
                    $currentChars = '';
                }

                $currentChars .= htmlspecialchars($char, ENT_QUOTES, 'UTF-8');
            }

            // Letzten Run
            if ($currentChars !== '') {
                $html .= $this->wrapStyledRun($currentChars, $currentSpan);
            }

            $htmlLines[] = $html;
        }

        return implode("<br />\n", $htmlLines);
    }

    /**
     * Wickelt einen Text-Run in die passenden HTML-Tags.
     */
    private function wrapStyledRun(string $text, ?string $styleKey): string
    {
        if (!$styleKey) return $text;

        $hasBold = str_contains($styleKey, 'b');
        $hasItalic = str_contains($styleKey, 'i');
        $hasUnderline = str_contains($styleKey, 'u');

        // Inline-Styles für Farbe/Größe
        $inlineStyle = '';
        if (preg_match('/c(#[0-9a-fA-F]{3,8})/', $styleKey, $m)) {
            $inlineStyle .= 'color:' . $m[1] . ';';
        }
        if (preg_match('/s(\d+(?:\.\d+)?)/', $styleKey, $m)) {
            $inlineStyle .= 'font-size:' . round((float)$m[1] / 1.333, 1) . 'pt;';
        }

        if ($inlineStyle) {
            $text = "<span style=\"{$inlineStyle}\">{$text}</span>";
        }

        if ($hasUnderline) $text = "<u>{$text}</u>";
        if ($hasItalic) $text = "<em>{$text}</em>";
        if ($hasBold) $text = "<strong>{$text}</strong>";

        return $text;
    }

    private function pxToMm(float $px): float
    {
        return round($px / self::PX_PER_MM, 2);
    }

    private function sanitizeFontFamily(string $font): string
    {
        // Nur erlaubte Fonts für dompdf
        $allowed = ['DejaVu Sans', 'Arial', 'Helvetica', 'Times New Roman', 'Courier New'];
        $font = in_array($font, $allowed) ? $font : 'DejaVu Sans';

        // Multi-word Font-Namen müssen in CSS gequotet werden (Dompdf-Kompatibilität)
        $quoted = str_contains($font, ' ') ? "'{$font}'" : $font;

        // Fallback-Kette: gewählter Font + generischer Typ + DejaVu Sans als letzter Fallback
        $generic = match ($font) {
            'Times New Roman' => 'serif',
            'Courier New' => 'monospace',
            default => 'sans-serif',
        };

        if ($font === 'DejaVu Sans') {
            return "{$quoted}, Arial, {$generic}";
        }

        return "{$quoted}, 'DejaVu Sans', {$generic}";
    }

    private function cssArrayToString(array $css): string
    {
        $parts = [];
        foreach ($css as $prop => $val) {
            $parts[] = "{$prop}: {$val}";
        }
        return implode('; ', $parts);
    }

    /**
     * Sanitisiert einen CSS-Wert (Farben, Zahlen) gegen Injection.
     * Entfernt alles, was aus dem CSS-Kontext ausbrechen koennte.
     */
    private function sanitizeCssColor(string $value): string
    {
        // Erlaube: hex (#fff, #ffffff), rgb/rgba(), benannte Farben, transparent
        $value = trim($value);
        if ($value === '' || $value === 'transparent') {
            return $value;
        }
        // Hex-Farben
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            return $value;
        }
        // rgb/rgba
        if (preg_match('/^rgba?\(\s*[\d.,\s%]+\)$/i', $value)) {
            return $value;
        }
        // Benannte CSS-Farben (Whitelist der gaengigsten)
        $namedColors = ['black', 'white', 'red', 'green', 'blue', 'yellow', 'orange',
            'purple', 'gray', 'grey', 'pink', 'brown', 'navy', 'teal', 'maroon',
            'silver', 'olive', 'aqua', 'lime', 'fuchsia'];
        if (in_array(strtolower($value), $namedColors)) {
            return strtolower($value);
        }
        // Fallback: ungueltiger Wert
        return '#000000';
    }
}
