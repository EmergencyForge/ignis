<?php

namespace App\Documents;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateLayout;
use Illuminate\Database\Capsule\Manager as Capsule;

class TemplateLayoutManager
{
    /** Maximale Groesse des Canvas-JSON in Bytes (5 MB) */
    private const MAX_CANVAS_JSON_SIZE = 5 * 1024 * 1024;

    /** Erlaubte Fabric.js-Objekttypen */
    private const ALLOWED_OBJECT_TYPES = [
        'textbox', 'text', 'i-text',
        'image', 'fabricimage',
        'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'path',
        'line',
        'group', 'activeselection',
    ];

    /**
     * Validiert Canvas-JSON-Struktur und -Groesse.
     *
     * @throws \InvalidArgumentException bei ungueltigem JSON
     */
    public function validateCanvasJson(string $json): void
    {
        if (strlen($json) > self::MAX_CANVAS_JSON_SIZE) {
            throw new \InvalidArgumentException(
                'Canvas-JSON ueberschreitet das Limit von ' . (self::MAX_CANVAS_JSON_SIZE / 1024 / 1024) . ' MB'
            );
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            // Evtl. doppelt encodiert
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            if (!is_array($data)) {
                throw new \InvalidArgumentException('Ungueltiges Canvas-JSON-Format');
            }
        }

        $objects = $data['objects'] ?? [];
        if (!is_array($objects)) {
            throw new \InvalidArgumentException('Canvas-JSON muss ein objects-Array enthalten');
        }

        if (count($objects) > 500) {
            throw new \InvalidArgumentException('Zu viele Objekte im Canvas (max. 500)');
        }

        foreach ($objects as $idx => $obj) {
            if (!isset($obj['type'])) {
                throw new \InvalidArgumentException("Objekt #{$idx} hat keinen Typ");
            }
            $type = strtolower($obj['type']);
            if (!in_array($type, self::ALLOWED_OBJECT_TYPES)) {
                throw new \InvalidArgumentException("Unbekannter Objekttyp: {$type}");
            }
            // Numerische Bounds pruefen (verhindet absurde Werte)
            foreach (['left', 'top'] as $prop) {
                if (isset($obj[$prop]) && abs((float) $obj[$prop]) > 50000) {
                    throw new \InvalidArgumentException("Objekt #{$idx}: {$prop}-Wert ausserhalb des erlaubten Bereichs");
                }
            }
            foreach (['width', 'height'] as $prop) {
                if (isset($obj[$prop]) && ((float) $obj[$prop] < 0 || (float) $obj[$prop] > 50000)) {
                    throw new \InvalidArgumentException("Objekt #{$idx}: {$prop}-Wert ungueltig");
                }
            }
        }
    }

    /**
     * Speichert oder aktualisiert ein Canvas-Layout für ein Template
     */
    public function saveLayout(int $templateId, string $canvasJson, ?float $pageWidthMm = null, ?float $pageHeightMm = null): int
    {
        $this->validateCanvasJson($canvasJson);
        // Prüfe ob bereits ein aktives Layout existiert
        $existing = $this->getLayout($templateId);

        if ($existing) {
            // Aktuelle Version deaktivieren
            DocumentTemplateLayout::where('template_id', $templateId)
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            $newVersion = $existing['version'] + 1;
        } else {
            $newVersion = 1;
        }

        $layout = DocumentTemplateLayout::create([
            'template_id'    => $templateId,
            'version'        => $newVersion,
            'canvas_json'    => $canvasJson,
            'page_width_mm'  => $pageWidthMm ?? 210.00,
            'page_height_mm' => $pageHeightMm ?? 297.00,
            'is_active'      => 1,
            'created_by'     => $_SESSION['user_id'] ?? null,
        ]);

        $layoutId = (int) $layout->id;

        // Template mit neuem Layout verknüpfen
        DocumentTemplate::where('id', $templateId)->update([
            'layout_id'   => $layoutId,
            'editor_type' => 'visual',
        ]);

        return $layoutId;
    }

    /**
     * Lädt das aktive Layout für ein Template
     */
    public function getLayout(int $templateId): ?array
    {
        $layout = DocumentTemplateLayout::where('template_id', $templateId)
            ->where('is_active', 1)
            ->orderByDesc('version')
            ->first();

        return $layout?->getAttributes();
    }

    /**
     * Lädt ein Layout anhand seiner ID
     */
    public function getLayoutById(int $layoutId): ?array
    {
        $layout = DocumentTemplateLayout::find($layoutId);

        return $layout?->getAttributes();
    }

    /**
     * Lädt alle Versionen eines Template-Layouts
     */
    public function getLayoutVersions(int $templateId): array
    {
        return Capsule::table('intra_dokument_template_layouts')
            ->where('template_id', $templateId)
            ->orderByDesc('version')
            ->get(['id', 'version', 'is_active', 'created_by', 'created_at', 'updated_at'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Stellt eine bestimmte Layout-Version als aktiv wieder her
     */
    public function restoreVersion(int $templateId, int $layoutId): bool
    {
        // Alle Versionen deaktivieren
        DocumentTemplateLayout::where('template_id', $templateId)
            ->update(['is_active' => 0]);

        // Gewählte Version aktivieren
        DocumentTemplateLayout::where('id', $layoutId)
            ->where('template_id', $templateId)
            ->update(['is_active' => 1]);

        // Template layout_id aktualisieren
        DocumentTemplate::where('id', $templateId)->update(['layout_id' => $layoutId]);

        return true;
    }

    /**
     * Löscht alle Layouts eines Templates
     */
    public function deleteLayouts(int $templateId): bool
    {
        DocumentTemplateLayout::where('template_id', $templateId)->delete();
        return true;
    }
}
