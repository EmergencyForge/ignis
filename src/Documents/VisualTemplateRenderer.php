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

        // Beispieldaten für Vorschau
        $fieldValues = array_merge($this->getPreviewDefaults(), $sampleData);
        $isDraft = $this->isTemplateDraft($templateId);

        return $this->renderCanvasToHtml($canvasData, $fieldValues, $isDraft);
    }

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

        // Text mit Platzhalter-Ersetzung
        $text = $obj['text'] ?? '';
        $text = $this->replacePlaceholders($text, $fieldValues);

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

        // Platzhalter-Werte sind bereits in replacePlaceholders() escaped.
        // Template-Text kommt aus dem Canvas-Editor (Admin-authored) und ist vertrauenswuerdig.
        // Nur nl2br fuer Zeilenumbrueche, kein weiteres htmlspecialchars (vermeidet Double-Escaping).
        $htmlText = nl2br($text);

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
        if (!$src) return "<!-- image not found -->\n";

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
            // Direkt suchen
            if (isset($fieldValues[$key])) {
                $val = $fieldValues[$key];
                return is_string($val) ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : $matches[0];
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
            return is_string($val) ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : $matches[0];
        }, $text);
    }

    /**
     * Löst die Bild-Quelle auf (Base64 für dompdf)
     */
    private function resolveImageSrc(array $obj): ?string
    {
        $custom = $obj['custom'] ?? [];
        $projectRoot = realpath(__DIR__ . '/../../');

        // 1. Template-Asset aus DB
        if (!empty($custom['assetId'])) {
            $base64 = $this->assetManager->getAsBase64((int) $custom['assetId']);
            if ($base64) return $base64;
        }

        // 2. System-Bilder (logo, wappen)
        if (!empty($custom['imageType'])) {
            $files = [
                'logo' => 'assets/img/schrift_fw_schwarz.png',
                'wappen' => 'assets/img/wappen_small.png',
            ];
            if (isset($files[$custom['imageType']])) {
                $path = $projectRoot . '/' . $files[$custom['imageType']];
                if (file_exists($path)) {
                    return $this->getImageAsBase64($path);
                }
            }
        }

        // 3. src-Feld auflösen (kann volle URL oder relativer Pfad sein)
        if (!empty($obj['src'])) {
            $src = $obj['src'];

            // Volle URL → extrahiere den Pfad nach dem Host
            // z.B. "http://localhost:3000/assets/img/logo.png" → "assets/img/logo.png"
            if (preg_match('#^https?://#', $src)) {
                $parsed = parse_url($src);
                $src = $parsed['path'] ?? '';
            }

            // Führenden Slash und BASE_PATH entfernen
            $src = ltrim($src, '/');
            $basePath = defined('BASE_PATH') ? trim(BASE_PATH, '/') : '';
            if ($basePath !== '' && str_starts_with($src, $basePath . '/')) {
                $src = substr($src, strlen($basePath) + 1);
            }

            // Bekannte System-Bilder erkennen
            if (strpos($src, 'assets/img/schrift_fw_schwarz') !== false) {
                $path = $projectRoot . '/assets/img/schrift_fw_schwarz.png';
                if (file_exists($path)) return $this->getImageAsBase64($path);
            }
            if (strpos($src, 'assets/img/wappen_small') !== false) {
                $path = $projectRoot . '/assets/img/wappen_small.png';
                if (file_exists($path)) return $this->getImageAsBase64($path);
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
     * Standard-Vorschaudaten
     */
    private function getPreviewDefaults(): array
    {
        return [
            'erhalter' => 'Max Mustermann',
            'anrede_text' => 'Herr',
            'geehrte' => 'geehrter',
            'zum' => 'zum',
            'ausstellungsdatum' => date('d.m.Y'),
            'document_id' => 'PREV-IEW0-0000',
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

    private function pxToMm(float $px): float
    {
        return round($px / self::PX_PER_MM, 2);
    }

    private function sanitizeFontFamily(string $font): string
    {
        // Nur erlaubte Fonts für dompdf
        $allowed = ['DejaVu Sans', 'Arial', 'Helvetica', 'Times New Roman', 'Courier New'];
        return in_array($font, $allowed) ? $font : 'DejaVu Sans';
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
