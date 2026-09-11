<?php

/**
 * View: Logout-Bestätigung / Abgemeldet (eNOTF v2) — v1-Optik.
 *
 * Markup/Optik = plugins/enotf/templates/enotf/loggedout.php (die
 * edivi__login-buttons-Leiste). Technik = v2: GET zeigt diese Seite
 * OHNE Side-Effects (DB-Write nur auf POST) —
 *   - mit aktiver Crew-Session: Bestätigung mit den beiden Logout-
 *     Optionen als POST-Formulare (mode=self|all),
 *   - ohne Session: v1s "Sie sind nicht angemeldet!"-Leiste mit
 *     Login-Link.
 *
 * @var bool  $hasCrewSession
 * @var array $crew  {vehicle, vehicle_label, members[]}
 */

use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$pinEnabled = EnotfV2Policy::pinEnabled() ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $__v1Title = 'eNOTF';
    require __DIR__ . '/_v1head.php';
    ?>
    <style>
        /* Die v1-Leiste stylt nur <a> — die POST-Buttons (v2-Logout)
           bekommen dieselbe Optik */
        .edivi__login-buttons button {
            font-size: 1.4rem;
            padding: 22px 30px;
            text-decoration: none;
            width: 100%;
            border: 2px solid #191919;
            background-color: var(--main-color);
            color: var(--white);
            cursor: pointer;
        }

        .edivi__login-buttons button:hover {
            background-color: var(--main-color-dimmed);
        }

        .edivi__login-buttons form {
            display: flex;
            width: 100%;
        }

        /* Frage-Zeile über den Aktionen: linksbündig mit Luft, wie der
           Text in v1s einzeiliger Leiste */
        .edivi__login-buttons .ev2-logout-question {
            justify-content: flex-start !important;
            padding: 22px 30px !important;
        }

        /* v1 stylt nur .col/.col-3 als Flex-Zellen — die col-4-Zellen
           der Aktionszeile brauchen dieselbe Behandlung */
        .edivi__login-buttons .col-4 {
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 !important;
        }
    </style>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" data-pin-enabled="<?= $pinEnabled ?>">
    <div class="container-fluid" id="edivi__container">
        <div class="h-full">
            <div id="edivi__content">
                <div class="edivi__login-buttons">
                    <?php if ($hasCrewSession): ?>
                        <?php $names = array_map(static fn ($m) => $m['name'], $crew['members'] ?? []); ?>
                        <div class="row">
                            <div class="col ev2-logout-question">
                                Angemeldet auf&nbsp;<strong><?= $e($crew['vehicle_label'] ?? $crew['vehicle']) ?></strong><?= $names !== [] ? '&nbsp;(' . $e(implode(', ', $names)) . ')' : '' ?>&nbsp;— wie möchten Sie sich abmelden?
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <form method="post" action="<?= $e(EnotfV2Url::page('loggedout')) ?>">
                                    <input type="hidden" name="mode" value="self">
                                    <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>">
                                    <button type="submit">mich abmelden</button>
                                </form>
                            </div>
                            <div class="col-4">
                                <form method="post" action="<?= $e(EnotfV2Url::page('loggedout')) ?>">
                                    <input type="hidden" name="mode" value="all">
                                    <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>">
                                    <button type="submit">alle abmelden</button>
                                </form>
                            </div>
                            <div class="col-4">
                                <a href="<?= $e(EnotfV2Url::page('overview')) ?>">zurück</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col">
                                Sie sind nicht angemeldet!
                            </div>
                            <div class="col-3">
                                <a href="<?= $e(EnotfV2Url::page('login')) ?>">anmelden</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
