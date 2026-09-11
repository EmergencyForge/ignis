<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Überführt bestehende Dokumente ins Template-System: mappt den alten
 * numerischen Dokumenttyp auf eine template_id und sammelt die verstreuten
 * Altspalten (erhalter_rang, erhalter_quali, inhalt, suspendtime, ...) als
 * JSON in custom_data ein.
 */
class MigrateExistingDocuments30092025 extends AbstractMigration
{
    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // Alter Dokumenttyp → template_id
        $mapping = [
            0 => 14, // Ernennungsurkunde
            1 => 2,  // Beförderungsurkunde
            2 => 3,  // Entlassungsurkunde
            5 => 5,  // Ausbildungszertifikat
            6 => 6,  // Lehrgangszertifikat
            7 => 7,  // Fachlehrgang
            10 => 10, // Abmahnung
            11 => 11, // Dienstenthebung
            12 => 12, // Dienstentfernung
            13 => 13,  // Kündigung
        ];

        $assign = $pdo->prepare(
            'UPDATE intra_mitarbeiter_dokumente
             SET template_id = ?
             WHERE type = ? AND (template_id IS NULL OR template_id = 0)'
        );

        foreach ($mapping as $type => $templateId) {
            $assign->execute([$templateId, $type]);
        }

        // Altspalten als JSON nach custom_data überführen
        $stmt = $pdo->query(
            'SELECT id, type, erhalter_rang, erhalter_rang_rd, erhalter_quali, inhalt, suspendtime
             FROM intra_mitarbeiter_dokumente
             WHERE custom_data IS NULL'
        );

        $update = $pdo->prepare('UPDATE intra_mitarbeiter_dokumente SET custom_data = ? WHERE id = ?');

        while ($doc = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $customData = [];

            if ($doc['erhalter_rang']) $customData['erhalter_rang'] = $doc['erhalter_rang'];
            if ($doc['erhalter_rang_rd']) $customData['erhalter_rang_rd'] = $doc['erhalter_rang_rd'];
            if ($doc['erhalter_quali']) $customData['erhalter_quali'] = $doc['erhalter_quali'];
            if ($doc['inhalt']) $customData['inhalt'] = $doc['inhalt'];
            if ($doc['suspendtime']) $customData['suspendtime'] = $doc['suspendtime'];

            if (!empty($customData)) {
                $update->execute([json_encode($customData), $doc['id']]);
            }
        }
    }

    public function down(): void
    {
        // Nicht sauber umkehrbar: Nach dem Up ist nicht mehr unterscheidbar,
        // welche template_id/custom_data-Werte von dieser Migration stammen
        // und welche später regulär gesetzt wurden. Die Quellspalten bleiben
        // ohnehin unverändert erhalten — no-op.
    }
}
