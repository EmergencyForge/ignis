<?php
try {
    // Modify created_by, updated_by, finalized_by columns to allow NULL in intra_fire_incidents
    $pdo->exec("ALTER TABLE intra_fire_incidents MODIFY created_by INT NULL");
    $pdo->exec("ALTER TABLE intra_fire_incidents MODIFY updated_by INT NULL");
    $pdo->exec("ALTER TABLE intra_fire_incidents MODIFY finalized_by INT NULL");

    // Modify created_by column in intra_fire_incident_vehicles
    $pdo->exec("ALTER TABLE intra_fire_incident_vehicles MODIFY created_by INT NULL");

    // Modify created_by column in intra_fire_incident_sitreps
    $pdo->exec("ALTER TABLE intra_fire_incident_sitreps MODIFY created_by INT NULL");

    // Modify created_by column in intra_fire_incident_log
    $pdo->exec("ALTER TABLE intra_fire_incident_log MODIFY created_by INT NULL");
} catch (PDOException $e) {
    echo $e->getMessage();
}
