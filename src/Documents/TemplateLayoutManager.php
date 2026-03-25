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

    /**
     * Speichert oder aktualisiert ein Canvas-Layout für ein Template
     */
    public function saveLayout(int $templateId, string $canvasJson, ?float $pageWidthMm = null, ?float $pageHeightMm = null): int
    {
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
