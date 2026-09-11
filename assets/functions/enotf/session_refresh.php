<?php
/**
 * eNOTF Session Refresh
 * Synchronisiert die PHP-Session-Crew-Daten mit der Datenbank.
 * Wird bei jedem Seitenaufruf eingebunden, damit nach Crew-Änderungen
 * (Tausch, Beitritt, etc.) die Daten aktuell sind.
 *
 * Voraussetzung: Session muss gestartet sein.
 */
if (!empty($_SESSION['enotf_session_token'])) {
    $__refreshData = \Illuminate\Database\Capsule\Manager::table('intra_enotf_session_members as m')
        ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
        ->where('m.session_token', $_SESSION['enotf_session_token'])
        ->first([
            's.fahrername',
            's.fahrerquali',
            's.beifahrername',
            's.beifahrerquali',
            's.praktikantname',
            's.praktikantquali',
            's.active',
        ]);

    if ($__refreshData && (int)$__refreshData->active === 1) {
        $_SESSION['fahrername']      = $__refreshData->fahrername;
        $_SESSION['fahrerquali']     = $__refreshData->fahrerquali;
        $_SESSION['beifahrername']   = $__refreshData->beifahrername;
        $_SESSION['beifahrerquali']  = $__refreshData->beifahrerquali;
        $_SESSION['praktikantname']  = $__refreshData->praktikantname;
        $_SESSION['praktikantquali'] = $__refreshData->praktikantquali;
    }

    unset($__refreshData);
}
