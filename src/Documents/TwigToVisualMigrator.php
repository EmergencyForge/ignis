<?php

namespace App\Documents;

use PDO;

/**
 * Migriert bestehende Twig-Templates zu visuellen JSON-Layouts.
 *
 * Die 10 System-Templates folgen bekannten Layout-Patterns.
 * Statt HTML-Parsing wird pro Template-Typ ein vorkonfiguriertes
 * JSON-Layout generiert, das die gleiche visuelle Struktur abbildet.
 */
class TwigToVisualMigrator
{
    private PDO $pdo;
    private TemplateLayoutManager $layoutManager;

    /** Pixel pro mm bei 96dpi */
    private const PX = 3.7795;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->layoutManager = new TemplateLayoutManager($pdo);
    }

    /**
     * Migriert alle Twig-Templates zu visuellen Layouts
     * @return array Ergebnisse pro Template
     */
    public function migrateAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM intra_dokument_templates
            WHERE editor_type = 'twig' OR editor_type IS NULL
        ");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($templates as $template) {
            try {
                $this->migrate((int) $template['id'], $template);
                $results[] = ['id' => $template['id'], 'name' => $template['name'], 'status' => 'ok'];
            } catch (\Exception $e) {
                $results[] = ['id' => $template['id'], 'name' => $template['name'], 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Migriert ein einzelnes Template
     */
    public function migrate(int $templateId, ?array $template = null): void
    {
        if (!$template) {
            $stmt = $this->pdo->prepare("SELECT * FROM intra_dokument_templates WHERE id = :id");
            $stmt->execute(['id' => $templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$template) {
            throw new \Exception("Template {$templateId} nicht gefunden");
        }

        // Lade Template-Felder
        $stmt = $this->pdo->prepare("
            SELECT * FROM intra_dokument_template_fields
            WHERE template_id = :id ORDER BY sort_order ASC
        ");
        $stmt->execute(['id' => $templateId]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Erkenne Template-Typ und generiere passendes Layout
        $templateFile = $template['template_file'] ?? '';
        $objects = $this->generateLayoutForTemplate($templateFile, $template, $fields);

        $canvasJson = json_encode([
            'version' => '6.0.0',
            'objects' => $objects,
            'background' => '#ffffff',
        ]);

        // Speichere Layout
        $this->layoutManager->saveLayout($templateId, $canvasJson);
    }

    /**
     * Generiert Canvas-Objekte basierend auf dem Template-Typ
     */
    private function generateLayoutForTemplate(string $templateFile, array $template, array $fields): array
    {
        // Ermittle Template-Kategorie
        $type = $this->detectTemplateType($templateFile);

        switch ($type) {
            case 'urkunde': // ernennung, befoerderung
                return $this->generateUrkundeLayout($template, $fields);

            case 'zertifikat': // ausbildung, lehrgang, fachlehrgang
                return $this->generateZertifikatLayout($template, $fields);

            case 'schreiben': // abmahnung, kuendigung, entlassung, dienstenthebung, dienstentfernung
                return $this->generateSchreibenLayout($template, $fields);

            default:
                return $this->generateGenericLayout($template, $fields);
        }
    }

    private function detectTemplateType(string $file): string
    {
        $urkundeFiles = ['ernennung.html.twig', 'befoerderung.html.twig'];
        $zertifikatFiles = ['ausbildung.html.twig', 'lehrgang.html.twig', 'fachlehrgang.html.twig'];

        if (in_array($file, $urkundeFiles)) return 'urkunde';
        if (in_array($file, $zertifikatFiles)) return 'zertifikat';
        return 'schreiben';
    }

    /**
     * Urkunden-Layout (ernennung, befoerderung)
     * Zentriertes Layout mit dekorativem Rahmen
     */
    private function generateUrkundeLayout(array $template, array $fields): array
    {
        $objects = [];

        // Dekorativer Rahmen
        $objects[] = $this->makeRect(13, 7, 184, 272, 'transparent', '#dc0814', 4);

        // Header-Tabelle als Textblöcke
        $objects[] = $this->makeText('Version 1.0', 18, 12, 30, 8.5, ['custom' => ['elementType' => 'static_text']]);
        $objects[] = $this->makeFieldText(
            'Ernennungsurkunde' . "\n" . '{{ RP_ORGTYPE }} {{ SERVER_CITY }}',
            55, 12, 100, 10,
            ['textAlign' => 'center', 'fontWeight' => 'bold', 'custom' => ['elementType' => 'static_text']]
        );

        // Wappen
        $objects[] = $this->makeSystemImage('wappen', 165, 10, 25, 25);

        // Seite
        $objects[] = $this->makeText('Seite', 18, 22, 30, 8.5, ['fontWeight' => 'bold', 'custom' => ['elementType' => 'static_text']]);

        // Titel
        $objects[] = $this->makeText('URKUNDE', 18, 48, 174, 20, [
            'textAlign' => 'center', 'fontWeight' => 'bold', 'fill' => '#dc0814',
            'fontSize' => 20 * self::PX / 3.7795,
            'custom' => ['elementType' => 'static_text'],
        ]);

        // Inhalt (zentriert)
        $yPos = 72;
        $objects[] = $this->makeFieldText('Im Namen der Stadt {{ SERVER_CITY }}', 18, $yPos, 174, 11.5, ['textAlign' => 'center']);
        $yPos += 10;
        $objects[] = $this->makeFieldText('wird {{ anrede_text }}', 18, $yPos, 174, 11.5, ['textAlign' => 'center']);
        $yPos += 12;
        $objects[] = $this->makeFieldText('{{ erhalter }}', 18, $yPos, 174, 15, ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => 15 * self::PX / 3.7795]);
        $yPos += 14;

        // Template-spezifische Felder einfügen
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            // Überspringe Standard-Felder die bereits im Layout sind
            if (in_array($fieldName, ['erhalter', 'erhalter_gebdat', 'anrede', 'ausstellungsdatum'])) continue;

            $objects[] = $this->makeFieldText(
                '{{ ' . $fieldName . ' }}',
                18, $yPos, 174, 12,
                [
                    'textAlign' => 'center',
                    'custom' => ['elementType' => 'field_placeholder', 'fieldName' => $fieldName, 'fieldLabel' => $field['field_label']],
                ]
            );
            $yPos += 12;
        }

        // Datum + Aussteller-Block
        $yPos = max($yPos + 10, 200);
        $objects[] = $this->makeFieldText('{{ SERVER_CITY }}, den {{ ausstellungsdatum }}', 18, $yPos, 100, 10);
        $yPos += 8;
        $objects[] = $this->makeFieldText('Ihr Zeichen: {{ document_id }}', 18, $yPos, 100, 9, ['fill' => '#333333']);
        $yPos += 10;
        $objects[] = $this->makeFieldText('{{ issuer.fullname }}', 18, $yPos, 100, 10, ['fontWeight' => 'bold']);
        $yPos += 6;
        $objects[] = $this->makeFieldText('{{ issuer.dienstgrad_text }}', 18, $yPos, 100, 10);
        $yPos += 8;

        // Elektronisch-Hinweis
        $objects[] = $this->makeText(
            '— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —',
            18, $yPos, 174, 8,
            ['fontStyle' => 'italic', 'fill' => '#666666', 'custom' => ['elementType' => 'static_text']]
        );

        // Disclaimer
        $objects[] = $this->makeRect(13, 272, 184, 10, '#dc0814', 'transparent', 0);
        $objects[] = $this->makeText(
            'Diese fiktive Urkunde ist lediglich für das Roleplay-Projekt "{{ SERVER_NAME }}" ausgelegt.',
            14, 273, 182, 7.5,
            ['fill' => '#ffffff', 'textAlign' => 'center', 'custom' => ['elementType' => 'static_text']]
        );

        return $objects;
    }

    /**
     * Zertifikat-Layout (ausbildung, lehrgang, fachlehrgang)
     */
    private function generateZertifikatLayout(array $template, array $fields): array
    {
        $objects = [];

        // Rahmen
        $objects[] = $this->makeRect(13, 7, 184, 272, 'transparent', '#dc0814', 4);

        // Header
        $objects[] = $this->makeText('Version 1.0', 18, 12, 30, 8.5, ['custom' => ['elementType' => 'static_text']]);
        $objects[] = $this->makeFieldText(
            '{{ RP_ORGTYPE }} {{ SERVER_CITY }}',
            55, 12, 100, 10,
            ['textAlign' => 'center', 'fontWeight' => 'bold']
        );
        $objects[] = $this->makeSystemImage('wappen', 165, 10, 25, 25);

        // Titel
        $objects[] = $this->makeText('ZERTIFIKAT', 18, 48, 174, 20, [
            'textAlign' => 'center', 'fontWeight' => 'bold', 'fill' => '#dc0814',
            'fontSize' => 20 * self::PX / 3.7795,
            'custom' => ['elementType' => 'static_text'],
        ]);

        // Inhalt
        $yPos = 75;
        $objects[] = $this->makeFieldText('{{ anrede_text }}', 18, $yPos, 174, 11.5, ['textAlign' => 'center']);
        $yPos += 10;
        $objects[] = $this->makeFieldText('{{ erhalter }}', 18, $yPos, 174, 15, ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => 15 * self::PX / 3.7795]);
        $yPos += 16;

        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            if (in_array($fieldName, ['erhalter', 'erhalter_gebdat', 'anrede', 'ausstellungsdatum'])) continue;

            $objects[] = $this->makeFieldText(
                $field['field_label'] . ': {{ ' . $fieldName . ' }}',
                18, $yPos, 174, 11,
                ['textAlign' => 'center', 'custom' => ['elementType' => 'field_placeholder', 'fieldName' => $fieldName, 'fieldLabel' => $field['field_label']]]
            );
            $yPos += 10;
        }

        // Footer
        $yPos = max($yPos + 15, 210);
        $objects[] = $this->makeFieldText('{{ SERVER_CITY }}, den {{ ausstellungsdatum }}', 18, $yPos, 100, 10);
        $yPos += 8;
        $objects[] = $this->makeFieldText('Ihr Zeichen: {{ document_id }}', 18, $yPos, 100, 9, ['fill' => '#333333']);
        $yPos += 10;
        $objects[] = $this->makeFieldText('{{ issuer.fullname }}', 18, $yPos, 100, 10, ['fontWeight' => 'bold']);
        $yPos += 6;
        $objects[] = $this->makeFieldText('{{ issuer.dienstgrad_text }}', 18, $yPos, 100, 10);
        $yPos += 8;
        $objects[] = $this->makeText(
            '— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —',
            18, $yPos, 174, 8,
            ['fontStyle' => 'italic', 'fill' => '#666666', 'custom' => ['elementType' => 'static_text']]
        );

        // Disclaimer
        $objects[] = $this->makeRect(13, 272, 184, 10, '#dc0814', 'transparent', 0);
        $objects[] = $this->makeText(
            'Dieses fiktive Zertifikat ist lediglich für das Roleplay-Projekt ausgelegt.',
            14, 273, 182, 7.5,
            ['fill' => '#ffffff', 'textAlign' => 'center', 'custom' => ['elementType' => 'static_text']]
        );

        return $objects;
    }

    /**
     * Schreiben-Layout (abmahnung, kuendigung, entlassung, etc.)
     * Brief-artiges Layout
     */
    private function generateSchreibenLayout(array $template, array $fields): array
    {
        $objects = [];

        // Header (Org-Info links, Logo rechts)
        $objects[] = $this->makeFieldText('{{ RP_ORGTYPE }} {{ SERVER_CITY }}', 25, 20, 100, 10);
        $objects[] = $this->makeFieldText('{{ RP_STREET }}', 25, 27, 100, 10);
        $objects[] = $this->makeFieldText('{{ RP_ZIP }} {{ SERVER_CITY }}', 25, 34, 100, 10);

        // Logo rechts
        $objects[] = $this->makeSystemImage('logo', 140, 20, 45, 20);

        // Datum rechts
        $objects[] = $this->makeText('Datum', 145, 42, 30, 10, ['custom' => ['elementType' => 'static_text']]);
        $objects[] = $this->makeFieldText('{{ ausstellungsdatum }}', 145, 48, 40, 12, ['fontWeight' => 'bold']);

        // Empfänger
        $objects[] = $this->makeFieldText('{{ anrede_text }}', 25, 65, 80, 11);
        $objects[] = $this->makeFieldText('{{ erhalter }}', 25, 72, 100, 11);
        $objects[] = $this->makeFieldText('{{ RP_ZIP }} {{ SERVER_CITY }}', 25, 79, 100, 11);

        // Titel
        $templateName = $template['name'] ?? 'Dokument';
        $objects[] = $this->makeText($templateName, 25, 95, 160, 15, [
            'fontWeight' => 'bold', 'fontSize' => 15 * self::PX / 3.7795,
            'custom' => ['elementType' => 'static_text'],
        ]);

        // Felder als Inhalt
        $yPos = 115;
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            if (in_array($fieldName, ['erhalter', 'erhalter_gebdat', 'anrede', 'ausstellungsdatum'])) continue;

            if ($field['field_type'] === 'richtext' || $field['field_type'] === 'textarea') {
                $objects[] = $this->makeText($field['field_label'] . ':', 25, $yPos, 160, 11, ['fontWeight' => 'bold', 'custom' => ['elementType' => 'static_text']]);
                $yPos += 7;
                $objects[] = $this->makeFieldText('{{ ' . $fieldName . ' }}', 25, $yPos, 160, 20, [
                    'custom' => ['elementType' => 'field_placeholder', 'fieldName' => $fieldName, 'fieldLabel' => $field['field_label']],
                ]);
                $yPos += 22;
            } else {
                $objects[] = $this->makeFieldText(
                    $field['field_label'] . ': {{ ' . $fieldName . ' }}',
                    25, $yPos, 160, 11,
                    ['custom' => ['elementType' => 'field_placeholder', 'fieldName' => $fieldName, 'fieldLabel' => $field['field_label']]]
                );
                $yPos += 10;
            }
        }

        // Footer
        $yPos = max($yPos + 10, 220);
        $objects[] = $this->makeFieldText('{{ SERVER_CITY }}, den {{ ausstellungsdatum }}', 25, $yPos, 100, 10);
        $yPos += 8;
        $objects[] = $this->makeFieldText('Ihr Zeichen: {{ document_id }}', 25, $yPos, 100, 9, ['fill' => '#333333']);
        $yPos += 10;
        $objects[] = $this->makeFieldText('{{ issuer.fullname }}', 25, $yPos, 100, 10, ['fontWeight' => 'bold']);
        $yPos += 6;
        $objects[] = $this->makeFieldText('{{ issuer.dienstgrad_text }}', 25, $yPos, 100, 10);
        $yPos += 8;
        $objects[] = $this->makeText(
            '— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —',
            25, $yPos, 160, 8,
            ['fontStyle' => 'italic', 'fill' => '#666666', 'custom' => ['elementType' => 'static_text']]
        );

        return $objects;
    }

    /**
     * Generisches Layout für unbekannte Templates
     */
    private function generateGenericLayout(array $template, array $fields): array
    {
        return $this->generateSchreibenLayout($template, $fields);
    }

    // --- Helper: Canvas-Objekte erstellen ---

    private function makeText(string $text, float $leftMm, float $topMm, float $widthMm, float $fontSizePt, array $extra = []): array
    {
        $obj = [
            'type' => 'textbox',
            'left' => $leftMm * self::PX,
            'top' => $topMm * self::PX,
            'width' => $widthMm * self::PX,
            'text' => $text,
            'fontFamily' => 'DejaVu Sans',
            'fontSize' => $fontSizePt * self::PX / 3.7795,
            'fill' => '#000000',
            'lineHeight' => 1.4,
            'custom' => $extra['custom'] ?? ['elementType' => 'static_text'],
        ];

        unset($extra['custom']);
        return array_merge($obj, $extra);
    }

    private function makeFieldText(string $text, float $leftMm, float $topMm, float $widthMm, float $fontSizePt, array $extra = []): array
    {
        $custom = $extra['custom'] ?? ['elementType' => 'field_placeholder', 'fieldName' => ''];
        $extra['custom'] = $custom;
        return $this->makeText($text, $leftMm, $topMm, $widthMm, $fontSizePt, $extra);
    }

    private function makeRect(float $leftMm, float $topMm, float $widthMm, float $heightMm, string $fill, string $stroke, int $strokeWidth): array
    {
        return [
            'type' => 'rect',
            'left' => $leftMm * self::PX,
            'top' => $topMm * self::PX,
            'width' => $widthMm * self::PX,
            'height' => $heightMm * self::PX,
            'fill' => $fill,
            'stroke' => $stroke,
            'strokeWidth' => $strokeWidth,
            'custom' => ['elementType' => 'shape'],
        ];
    }

    private function makeSystemImage(string $imageType, float $leftMm, float $topMm, float $widthMm, float $heightMm): array
    {
        $files = [
            'logo' => '/assets/img/schrift_fw_schwarz.png',
            'wappen' => '/assets/img/wappen_small.png',
        ];

        return [
            'type' => 'image',
            'left' => $leftMm * self::PX,
            'top' => $topMm * self::PX,
            'width' => $widthMm * self::PX,
            'height' => $heightMm * self::PX,
            'scaleX' => 1,
            'scaleY' => 1,
            'src' => $files[$imageType] ?? '',
            'custom' => ['elementType' => 'system_image', 'imageType' => $imageType],
        ];
    }
}
