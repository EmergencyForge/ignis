<?php

namespace App\Documents;

use PDO;

class TemplateLayoutManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

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
            $stmt = $this->pdo->prepare("
                UPDATE intra_dokument_template_layouts
                SET is_active = 0
                WHERE template_id = :template_id AND is_active = 1
            ");
            $stmt->execute(['template_id' => $templateId]);

            $newVersion = $existing['version'] + 1;
        } else {
            $newVersion = 1;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO intra_dokument_template_layouts
            (template_id, version, canvas_json, page_width_mm, page_height_mm, is_active, created_by)
            VALUES (:template_id, :version, :canvas_json, :page_width_mm, :page_height_mm, 1, :created_by)
        ");

        $stmt->execute([
            'template_id' => $templateId,
            'version' => $newVersion,
            'canvas_json' => $canvasJson,
            'page_width_mm' => $pageWidthMm ?? 210.00,
            'page_height_mm' => $pageHeightMm ?? 297.00,
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        $layoutId = (int) $this->pdo->lastInsertId();

        // Template mit neuem Layout verknüpfen
        $stmt = $this->pdo->prepare("
            UPDATE intra_dokument_templates
            SET layout_id = :layout_id, editor_type = 'visual'
            WHERE id = :template_id
        ");
        $stmt->execute([
            'layout_id' => $layoutId,
            'template_id' => $templateId,
        ]);

        return $layoutId;
    }

    /**
     * Lädt das aktive Layout für ein Template
     */
    public function getLayout(int $templateId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM intra_dokument_template_layouts
            WHERE template_id = :template_id AND is_active = 1
            ORDER BY version DESC
            LIMIT 1
        ");
        $stmt->execute(['template_id' => $templateId]);
        $layout = $stmt->fetch(PDO::FETCH_ASSOC);

        return $layout ?: null;
    }

    /**
     * Lädt ein Layout anhand seiner ID
     */
    public function getLayoutById(int $layoutId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_dokument_template_layouts WHERE id = :id");
        $stmt->execute(['id' => $layoutId]);
        $layout = $stmt->fetch(PDO::FETCH_ASSOC);

        return $layout ?: null;
    }

    /**
     * Lädt alle Versionen eines Template-Layouts
     */
    public function getLayoutVersions(int $templateId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, version, is_active, created_by, created_at, updated_at
            FROM intra_dokument_template_layouts
            WHERE template_id = :template_id
            ORDER BY version DESC
        ");
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Stellt eine bestimmte Layout-Version als aktiv wieder her
     */
    public function restoreVersion(int $templateId, int $layoutId): bool
    {
        // Alle Versionen deaktivieren
        $stmt = $this->pdo->prepare("
            UPDATE intra_dokument_template_layouts
            SET is_active = 0
            WHERE template_id = :template_id
        ");
        $stmt->execute(['template_id' => $templateId]);

        // Gewählte Version aktivieren
        $stmt = $this->pdo->prepare("
            UPDATE intra_dokument_template_layouts
            SET is_active = 1
            WHERE id = :id AND template_id = :template_id
        ");
        $result = $stmt->execute([
            'id' => $layoutId,
            'template_id' => $templateId,
        ]);

        // Template layout_id aktualisieren
        if ($result) {
            $stmt = $this->pdo->prepare("
                UPDATE intra_dokument_templates
                SET layout_id = :layout_id
                WHERE id = :template_id
            ");
            $stmt->execute([
                'layout_id' => $layoutId,
                'template_id' => $templateId,
            ]);
        }

        return $result;
    }

    /**
     * Löscht alle Layouts eines Templates
     */
    public function deleteLayouts(int $templateId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM intra_dokument_template_layouts WHERE template_id = :template_id");
        return $stmt->execute(['template_id' => $templateId]);
    }
}
