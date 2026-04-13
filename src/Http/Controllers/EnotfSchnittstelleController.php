<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\FiveMSupport;

/**
 * EnotfSchnittstelleController — externe Schnittstellen für eNOTF.
 *
 * Alle Endpoints sind public (kein User-Login). Klinikcode/Hospital-Login
 * läuft über Session-Variablen, die in den jeweiligen Templates inline
 * verwaltet werden — sie sind eng mit der Page-Logik verwoben und werden
 * 1:1 portiert.
 *
 * - index.php       — Arrivalboard (Krankenhaus-Sicht der ankommenden Voranmeldungen)
 * - klinikcode.php  — Code-Eingabe für Druckansicht eines einzelnen Protokolls
 * - voranmeldung.php— Voranmeldung-Form (PIN-protected via pin_middleware)
 * - hospital-availability.php — Klinik-Personal-Login zur Verfügbarkeitsmeldung
 * - api-prereg.php  — Redirect-Stub zur API
 */
class EnotfSchnittstelleController extends Controller
{
    public function index(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->renderView('enotf/schnittstelle/index', []);
    }

    public function klinikcode(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->renderView('enotf/schnittstelle/klinikcode', []);
    }

    public function voranmeldung(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        // PIN-Middleware: nur wenn ENOTF_USE_PIN aktiv und Session keinen Bypass hat
        if (\App\Policies\EnotfPolicy::pinEnabled()
            && !\App\Policies\EnotfPolicy::pinExempt()
            && !\App\Policies\EnotfPolicy::hasKlinikAccess()
            && !\App\Policies\EnotfPolicy::pinVerified()
        ) {
            if (basename($_SERVER['PHP_SELF']) !== 'lockscreen.php') {
                $_SESSION['pin_return_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            }
            $_SESSION['pin_verified'] = false;
            unset($_SESSION['pin_last_activity']);
            header('Location: ' . \App\Helpers\EnotfUrl::page('lockscreen'));
            exit;
        }
        $this->renderView('enotf/schnittstelle/voranmeldung', []);
    }

    public function hospitalAvailability(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->renderView('enotf/schnittstelle/hospital-availability', []);
    }

    /**
     * Stub-Wrapper für api-prereg — leitet weiter an api/enotf/prereg.php.
     */
    public function apiPrereg(): void
    {
        require dirname(__DIR__, 3) . '/src/LegacyApi/enotf/prereg.php';
    }
}
