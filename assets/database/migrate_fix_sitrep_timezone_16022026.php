<?php
/**
 * Einmalige Migration: Korrigiert falsch gespeicherte Zeiten (Berlin statt UTC)
 * in intra_fire_incident_sitreps und intra_fire_incidents.
 *
 * Betroffen sind nur lokal erstellte Einträge (source IS NULL oder != 'leitstelle').
 * Via EMD-Sync erstellte Einträge (source = 'leitstelle') sind bereits korrekt in UTC.
 *
 * Wird über database-init.php ausgeführt ($pdo ist bereits verfügbar).
 */

// 1. Sitreps: Lokale Einträge von Berlin nach UTC korrigieren
$stmt = $pdo->prepare("
    SELECT id, report_time
    FROM intra_fire_incident_sitreps
    WHERE report_time IS NOT NULL
    AND (source IS NULL OR source != 'leitstelle')
");
$stmt->execute();
$sitreps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedSitreps = 0;
foreach ($sitreps as $row) {
    $dt = new DateTime($row['report_time'], new DateTimeZone('Europe/Berlin'));
    $dt->setTimezone(new DateTimeZone('UTC'));
    $utcTime = $dt->format('Y-m-d H:i:s');

    if ($utcTime !== $row['report_time']) {
        $upd = $pdo->prepare("UPDATE intra_fire_incident_sitreps SET report_time = ? WHERE id = ?");
        $upd->execute([$utcTime, $row['id']]);
        $updatedSitreps++;
    }
}
echo "Sitreps korrigiert: {$updatedSitreps} / " . count($sitreps) . "\n";

// 2. Incidents: started_at korrigieren
$stmt = $pdo->prepare("
    SELECT id, started_at
    FROM intra_fire_incidents
    WHERE started_at IS NOT NULL
");
$stmt->execute();
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedIncidents = 0;
foreach ($incidents as $row) {
    $dt = new DateTime($row['started_at'], new DateTimeZone('Europe/Berlin'));
    $dt->setTimezone(new DateTimeZone('UTC'));
    $utcTime = $dt->format('Y-m-d H:i:s');

    if ($utcTime !== $row['started_at']) {
        $upd = $pdo->prepare("UPDATE intra_fire_incidents SET started_at = ? WHERE id = ?");
        $upd->execute([$utcTime, $row['id']]);
        $updatedIncidents++;
    }
}
echo "Incidents korrigiert: {$updatedIncidents} / " . count($incidents) . "\n";
