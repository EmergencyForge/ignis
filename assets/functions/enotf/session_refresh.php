<?php
/**
 * eNOTF Session Refresh
 * Synchronisiert die PHP-Session-Crew-Daten mit der Datenbank.
 * Wird bei jedem Seitenaufruf eingebunden, damit nach Crew-Änderungen
 * (Tausch, Beitritt, etc.) die Daten aktuell sind.
 *
 * Voraussetzung: $pdo muss verfügbar sein, Session muss gestartet sein.
 */
if (!empty($_SESSION['enotf_session_token'])) {
    $__refreshStmt = $pdo->prepare("
        SELECT s.fahrername, s.fahrerquali, s.beifahrername, s.beifahrerquali,
               s.praktikantname, s.praktikantquali, s.active
        FROM intra_enotf_session_members m
        JOIN intra_enotf_sessions s ON s.id = m.session_id
        WHERE m.session_token = :token
        LIMIT 1
    ");
    $__refreshStmt->execute([':token' => $_SESSION['enotf_session_token']]);
    $__refreshData = $__refreshStmt->fetch(PDO::FETCH_ASSOC);

    if ($__refreshData && (int)$__refreshData['active'] === 1) {
        $_SESSION['fahrername']      = $__refreshData['fahrername'];
        $_SESSION['fahrerquali']     = $__refreshData['fahrerquali'];
        $_SESSION['beifahrername']   = $__refreshData['beifahrername'];
        $_SESSION['beifahrerquali']  = $__refreshData['beifahrerquali'];
        $_SESSION['praktikantname']  = $__refreshData['praktikantname'];
        $_SESSION['praktikantquali'] = $__refreshData['praktikantquali'];
    }

    unset($__refreshStmt, $__refreshData);
}
