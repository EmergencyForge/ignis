<?php

/**
 * View: Übersicht — offene Protokolle + Quicklinks (eNOTF v2) — v1-Optik.
 *
 * Markup/Optik = plugins/enotf/templates/enotf/overview.php: Topbar mit
 * Abmelden/Fahrzeuginfo/Fahrtenbuch, Protokoll-Liste als edivi__einsatz-
 * Zeilen mit Swipe-to-Delete, Quicklinks als Bootstrap-Accordions mit
 * edivi__nidabutton-Kacheln. Technik = v2: Protokoll-Links auf den
 * v2-Editor, delete_all als POST (v2-Sicherheitsfix), Logout über das
 * v1-Modal, aber mit POST-Formularen (mode=self|all) statt GET-Links.
 * Fahrzeuginfo/Fahrtenbuch verlinken auf die v1-Seiten (bewusst
 * weiterverwendet, kein Nachbau).
 *
 * @var array<int,array<string,mixed>>               $protokolle
 * @var array<int,array<string,mixed>>               $categories
 * @var array<string,array<int,array<string,mixed>>> $linksByCategory
 * @var array                                        $crew
 */

use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$pinEnabled = EnotfV2Policy::pinEnabled() ? 'true' : 'false';

// Crew nach Position für die Topbar (Fahrer links, Beifahrer/Praktikant rechts)
$crewByPos = [];
foreach (($crew['members'] ?? []) as $member) {
    $crewByPos[$member['position']] = $member['name'];
}

date_default_timezone_set('Europe/Berlin');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $__v1Title = 'eNOTF';
    require __DIR__ . '/_v1head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>" data-base-path="<?= BASE_PATH ?>" data-session-token="<?= $e($_SESSION['enotf_session_token'] ?? '') ?>">
    <!-- ── Topbar: v1-Nachbau (assets/components/enotf/topbar.php, Overview-Variante) ── -->
    <div class="container-fluid" id="edivi__topbar">
        <div class="row">
            <div class="col flex align-items-center">
                <a href="javascript:void(0)" class="edivi__iconlink" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i><br>
                    <small>Abmelden</small>
                </a>
                <a href="<?= $e(EnotfUrl::page('fahrzeuginfo')) ?>" class="edivi__iconlink">
                    <i class="fa-solid fa-truck-medical"></i><br>
                    <small>Fahrzeuginfo</small>
                </a>
                <a href="<?= $e(EnotfUrl::page('fahrtenbuch')) ?>" class="edivi__iconlink">
                    <i class="fa-solid fa-book"></i><br>
                    <small>Fahrtenbuch</small>
                </a>
            </div>
            <div class="col text-end flex justify-content-end align-items-center">
                <?php
                // Angemeldete Crew → Link führt zur Abmelde-Bestätigung,
                // ohne Crew zur Anmeldung (mit Prefill).
                $crewAngemeldet = !empty($crewByPos['fahrer']) || !empty($crewByPos['beifahrer']) || !empty($crewByPos['praktikant']);
                $crewLinkUrl    = $crewAngemeldet ? EnotfV2Url::page('loggedout') : EnotfV2Url::page('login') . '?prefill=1';
                ?>
                <a href="<?= $e($crewLinkUrl) ?>" class="d-flex flex-column align-items-center no-underline text-reset self-stretch justify-content-between" id="topbar-crew-display" style="font-size: 0.85rem; line-height: 1.2; padding: 5px 15px;">
                    <div class="flex align-items-start">
                        <div class="d-flex flex-column align-items-end justify-content-start">
                            <span data-crew-name="fahrername"><?= $e($crewByPos['fahrer'] ?? '') ?></span>
                        </div>
                        <div class="d-flex flex-column align-items-start ml-3">
                            <span data-crew-name="beifahrername" class="<?= empty($crewByPos['beifahrer']) ? 'hidden' : '' ?>"><?= $e($crewByPos['beifahrer'] ?? '') ?></span>
                            <span data-crew-name="praktikantname" class="<?= empty($crewByPos['praktikant']) ? 'hidden' : '' ?>"><?= $e($crewByPos['praktikant'] ?? '') ?></span>
                        </div>
                    </div>
                    <small style="font-size: 0.65rem;" data-crew-linklabel><?= $crewAngemeldet ? 'Abmelden' : 'Anmelden' ?></small>
                </a>
                <div class="d-flex flex-column align-items-start mr-3" style="font-size: 0.95rem; gap: 4px; padding-left: 15px; border-left: 2px solid #424242;">
                    <span id="leitstelle-conn-icon" title="Verbindung zur Leitstelle">
                        <i class="fa-solid fa-tower-broadcast" style="color: #ffffff;"></i>
                    </span>
                    <span id="session-conn-icon" title="Session-Verbindung">
                        <i class="fa-solid fa-network-wired" style="color: #ffffff;"></i>
                    </span>
                </div>
                <div class="d-flex flex-column align-items-end mr-3" style="padding-left: 15px; border-left: 2px solid #424242;">
                    <span id="current-time"><?= date('H:i') ?></span>
                    <span id="current-date"><?= date('d.m.Y') ?></span>
                </div>
                <a href="https://github.com/intraRP/intraRP" target="_blank">
                    <img src="https://web-assets.emergencyforge.de/images/defaultLogo.webp" alt="EmergencyForge Logo" height="64px" width="auto">
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid" id="edivi__container">
        <div class="row h-full">
            <div class="col" id="edivi__content">
                <div class="hr my-2" style="color:transparent"></div>
                <div class="row">
                    <div class="col-8">
                        <div class="row">
                            <div class="col flex justify-content-start align-items-center">
                                <h4 class="fw-bold">Einsatzprotokolle</h4>
                            </div>
                            <div class="col flex justify-content-end align-items-center gap-2">
                                <button type="button" class="edivi__nidabutton" style="display:inline-block" onclick="window.location.reload();" title="Seite neu laden"><i class="fa-solid fa-rotate-right"></i></button>
                                <a href="<?= $e(EnotfV2Url::page('create')) ?>" class="edivi__nidabutton" style="display:inline-block"><i class="fa-solid fa-plus" title="Neuen Einsatz erstellen"></i></a>
                            </div>
                        </div>
                        <div class="row pl-3">
                            <div class="col edivi__box p-4" style="overflow-x: hidden; overflow-y:auto; height: 70vh;">
                                <?php
                                // v1-Label: A, B, … Z, AA, BB, …
                                $protLabelFromIndex = static function (int $i): string {
                                    $repeat  = intdiv($i, 26) + 1;
                                    $charIdx = $i % 26;
                                    return str_repeat(chr(65 + $charIdx), $repeat);
                                };

                                $rank = 0;
                                foreach ($protokolle as $row):
                                    $label = $protLabelFromIndex($rank++);

                                    $edatum    = !empty($row['edatum']) ? (new DateTime((string) $row['edatum']))->format('d.m.Y') : '—';
                                    $ezeit     = !empty($row['ezeit']) ? (new DateTime((string) $row['ezeit']))->format('H:i') : '—';
                                    $patgebdat = !empty($row['patgebdat']) ? (new DateTime((string) $row['patgebdat']))->format('d.m.Y') : '—';
                                    $patname   = !empty($row['patname']) ? $row['patname'] : '—';

                                    $canDelete = ((int) ($row['createdby'] ?? 0) === 2);
                                    $protType  = ((int) ($row['prot_by'] ?? 0) === 1) ? 'NA' : 'NF';

                                    // Zielort aufbereiten (v1-Logik)
                                    $zielInfo = '';
                                    if (!empty($row['ziel_poi']) || !empty($row['ziel_adresse'])) {
                                        $zielParts = [];
                                        if (!empty($row['ziel_poi'])) {
                                            $zielParts[] = $e($row['ziel_poi']);
                                        }
                                        if (!empty($row['ziel_adresse'])) {
                                            $zielAddr = json_decode((string) $row['ziel_adresse'], true);
                                            if (is_array($zielAddr)) {
                                                if (!empty($zielAddr['hnr'])) $zielParts[] = $e($zielAddr['hnr']);
                                                if (!empty($zielAddr['ort'])) $zielParts[] = $e($zielAddr['ort']);
                                            }
                                        }
                                        if (!empty($zielParts)) {
                                            $zielInfo = implode(', ', $zielParts);
                                        }
                                    }
                                ?>
                                    <div class="edivi__einsatz-wrapper" data-enr="<?= $e($row['enr']) ?>" data-can-delete="<?= $canDelete ? '1' : '0' ?>">
                                        <div class="edivi__einsatz-delete-bg">
                                            <i class="fa-solid fa-trash"></i>
                                        </div>
                                        <div class="edivi__einsatz-container edivi__einsatz-swipeable">
                                            <a href="<?= $e(EnotfV2Url::protokoll((string) $row['enr'])) ?>" class="edivi__einsatz-link" draggable="false">
                                                <div class="row edivi__einsatz edivi__einsatz-set">
                                                    <div class="col-2 edivi__einsatz-type px-3"><?php if ((int) ($row['createdby'] ?? 0) === 1): ?><i class="fa-solid fa-bell" style="color:#fff;font-size:1.4rem;margin-right:10px;"></i><?php endif; ?><span><?= $e($label) ?></span></div>
                                                    <div class="col edivi__einsatz-enr"><span>#<?= $e($row['enr']) ?> <span class="edivi__einsatz-cat"><?= $protType ?></span></span><?= $edatum ?><br><?= $ezeit ?> Uhr</div>
                                                    <div class="col-8 edivi__einsatz-name px-3"><span>Patient:</span><strong><?= $e($patname) ?> * <?= $patgebdat ?></strong><?php if ($zielInfo !== ''): ?><small><i class="fa-solid fa-bed" style="margin-right:4px;"></i><?= $zielInfo ?></small><?php endif; ?></div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($protokolle !== []): ?>
                            <div class="row pl-3">
                                <div class="col p-0" style="margin: 10px 0;">
                                    <button type="button" class="edivi__nidabutton w-100" id="ev2-delete-all" name="delete_all">alle löschen</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <?php foreach ($categories as $index => $category):
                            $accordion_id = 'accordion' . $category['id'];
                            $heading_id = 'heading' . $category['id'];
                            $collapse_id = 'collapse' . $category['id'];
                        ?>
                            <div class="row<?= $index > 0 ? ' mt-2' : '' ?>">
                                <div class="col">
                                    <div class="accordion" id="<?= $accordion_id ?>" data-theme="dark">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="<?= $heading_id ?>">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" aria-expanded="true" aria-controls="<?= $collapse_id ?>">
                                                    <?= $e($category['name']) ?>
                                                </button>
                                            </h2>
                                            <div id="<?= $collapse_id ?>" class="accordion-collapse collapse show" aria-labelledby="<?= $heading_id ?>" data-bs-parent="#<?= $accordion_id ?>">
                                                <div class="accordion-body">
                                                    <?php
                                                    $quicklinks = $linksByCategory[$category['slug']] ?? [];

                                                    if (empty($quicklinks)) {
                                                        echo '<p class="text-muted">Keine Links verfügbar.</p>';
                                                    } else {
                                                        // Legacy-DB-Werte: 'col' (auto-fill) wurde im alten
                                                        // Design visuell als 50%-Tile gerendert → 'col-6'.
                                                        $colWidthMap = [
                                                            'col'    => 'col-6',
                                                            'col-3'  => 'col-3',
                                                            'col-4'  => 'col-4',
                                                            'col-6'  => 'col-6',
                                                            'col-12' => 'col-12',
                                                        ];
                                                        echo '<div class="row g-2">';
                                                        foreach ($quicklinks as $link) {
                                                            $span = $colWidthMap[$link['col_width'] ?? 'col-6'] ?? 'col-6';
                                                            echo '<div class="' . $e($span) . '">';
                                                            echo '<a href="' . $e($link['url']) . '" class="edivi__nidabutton w-100 d-block text-center text-decoration-none">';
                                                            echo '<i class="' . $e($link['icon'] ?: 'fa-solid fa-link') . '"></i> ' . $e($link['title']);
                                                            echo '</a>';
                                                            echo '</div>';
                                                        }
                                                        echo '</div>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- delete_all als POST (v2-Sicherheitsfix) — ausgelöst über den Button + Confirm -->
    <form method="post" action="<?= $e(EnotfV2Url::page('overview')) ?>" id="ev2-delete-all-form" style="display:none;">
        <input type="hidden" name="delete_all" value="1">
        <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>">
    </form>

    <style>
        .edivi__einsatz-wrapper {
            position: relative;
            overflow: hidden;
            margin-bottom: 2px;
        }

        .edivi__einsatz-delete-bg {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 68px;
            background-color: #dc3545;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 0;
            display: grid;
            place-items: center;
        }

        .edivi__einsatz-swipeable {
            position: relative;
            transition: transform 0.2s ease;
            background-color: #191919;
            z-index: 1;
            user-select: none;
            -webkit-user-select: none;
            width: 100%;
        }

        .edivi__einsatz-swipeable a {
            -webkit-user-drag: none;
            user-drag: none;
        }

        .edivi__einsatz-wrapper.swiped .edivi__einsatz-swipeable {
            transform: translateX(-80px);
        }
    </style>
    <script>
        // Swipe-to-Delete (v1 overview.php 1:1) — Einzellöschen läuft über
        // den v2-Endpoint delete-protocol (403 bei Leitstellen-Protokollen;
        // das v1-Pendant hängt hinter hartem User-Auth → 401 für Crews
        // ohne Panel-Login)
        (function() {
            const wrappers = document.querySelectorAll('.edivi__einsatz-wrapper');
            let currentlyOpen = null;
            let justSwiped = false; // Verhindert, dass Click-Event den Swipe rückgängig macht

            wrappers.forEach(wrapper => {
                const swipeable = wrapper.querySelector('.edivi__einsatz-swipeable');
                const deleteBtn = wrapper.querySelector('.edivi__einsatz-delete-bg');
                const link = wrapper.querySelector('.edivi__einsatz-link');
                const enr = wrapper.dataset.enr;
                const canDelete = wrapper.dataset.canDelete === '1';

                let startX = 0;
                let currentX = 0;
                let hasMoved = false;

                // Verhindere natives Drag-Verhalten auf dem Link
                link.addEventListener('dragstart', (e) => e.preventDefault());

                // Link-Klick abfangen und nur bei echtem Klick (ohne Swipe) navigieren
                link.addEventListener('click', (e) => {
                    if (hasMoved || wrapper.classList.contains('swiped')) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });

                function settleSwipe() {
                    swipeable.style.transition = 'transform 0.2s ease';
                    const diff = startX - currentX;

                    if (diff > 40) {
                        if (currentlyOpen && currentlyOpen !== wrapper) {
                            currentlyOpen.classList.remove('swiped');
                            currentlyOpen.querySelector('.edivi__einsatz-swipeable').style.transform = '';
                        }
                        wrapper.classList.add('swiped');
                        swipeable.style.transform = 'translateX(-80px)';
                        currentlyOpen = wrapper;
                        justSwiped = true;
                        setTimeout(() => {
                            justSwiped = false;
                        }, 300);
                    } else {
                        wrapper.classList.remove('swiped');
                        swipeable.style.transform = '';
                        if (currentlyOpen === wrapper) {
                            currentlyOpen = null;
                        }
                    }

                    setTimeout(() => {
                        hasMoved = false;
                    }, 100);
                }

                // Touch events
                swipeable.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    currentX = startX;
                    hasMoved = false;
                    swipeable.style.transition = 'none';
                }, {
                    passive: true
                });

                swipeable.addEventListener('touchmove', (e) => {
                    currentX = e.touches[0].clientX;
                    const diff = startX - currentX;

                    if (Math.abs(diff) > 5) {
                        hasMoved = true;
                    }

                    if (diff > 0 && diff <= 80) {
                        swipeable.style.transform = `translateX(-${diff}px)`;
                    }
                }, {
                    passive: true
                });

                swipeable.addEventListener('touchend', settleSwipe);

                // Mouse events für Desktop
                let mouseDown = false;

                swipeable.addEventListener('mousedown', (e) => {
                    startX = e.clientX;
                    currentX = startX;
                    mouseDown = true;
                    hasMoved = false;
                    swipeable.style.transition = 'none';
                    e.preventDefault();
                });

                swipeable.addEventListener('mousemove', (e) => {
                    if (!mouseDown) return;
                    currentX = e.clientX;
                    const diff = startX - currentX;

                    if (Math.abs(diff) > 5) {
                        hasMoved = true;
                    }

                    if (diff > 0 && diff <= 80) {
                        swipeable.style.transform = `translateX(-${diff}px)`;
                    }
                });

                swipeable.addEventListener('mouseleave', () => {
                    if (mouseDown) {
                        mouseDown = false;
                        settleSwipe();
                    }
                });

                swipeable.addEventListener('mouseup', () => {
                    if (!mouseDown) return;
                    mouseDown = false;
                    settleSwipe();
                });

                // Delete button click
                deleteBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    function resetSwipe() {
                        wrapper.classList.remove('swiped');
                        swipeable.style.transform = '';
                        currentlyOpen = null;
                    }

                    if (!canDelete) {
                        await showAlert('Protokolle der Leitstelle können nicht gelöscht werden.', {
                            title: 'Nicht erlaubt',
                            type: 'error'
                        });
                        resetSwipe();
                        return;
                    }

                    const confirmed = await showConfirm('Möchten Sie dieses Protokoll wirklich löschen?', {
                        title: 'Protokoll löschen',
                        confirmText: 'Löschen',
                        cancelText: 'Abbrechen',
                        danger: true
                    });

                    if (!confirmed) {
                        resetSwipe();
                        return;
                    }

                    try {
                        const response = await fetch('<?= $e(EnotfV2Url::api('delete-protocol')) ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                enr: enr
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            wrapper.style.transition = 'opacity 0.3s ease, height 0.3s ease';
                            wrapper.style.opacity = '0';
                            wrapper.style.height = '0';
                            wrapper.style.overflow = 'hidden';
                            setTimeout(() => wrapper.remove(), 300);
                        } else {
                            await showAlert(data.message || 'Fehler beim Löschen des Protokolls.', {
                                title: 'Fehler',
                                type: 'error'
                            });
                            resetSwipe();
                        }
                    } catch (error) {
                        console.error('Fehler:', error);
                        await showAlert('Fehler beim Löschen des Protokolls.', {
                            title: 'Fehler',
                            type: 'error'
                        });
                        resetSwipe();
                    }
                });
            });

            // Schließen bei Klick außerhalb
            document.addEventListener('click', (e) => {
                if (justSwiped) return; // Ignoriere Klicks direkt nach einem Swipe
                if (currentlyOpen && !currentlyOpen.contains(e.target)) {
                    currentlyOpen.classList.remove('swiped');
                    currentlyOpen.querySelector('.edivi__einsatz-swipeable').style.transform = '';
                    currentlyOpen = null;
                }
            });
        })();

        // Alle löschen: bestätigen, dann POST delete_all (v2-Funktion)
        (function() {
            var btn = document.getElementById('ev2-delete-all');
            if (!btn) return;
            btn.addEventListener('click', async function() {
                const confirmed = await showConfirm(
                    'Wirklich ALLE offenen Protokolle dieses Fahrzeugs löschen? Sie werden als vom Benutzer gelöscht markiert.', {
                        title: 'Alle Protokolle löschen',
                        confirmText: 'Alle löschen',
                        cancelText: 'Abbrechen',
                        danger: true
                    });
                if (confirmed) document.getElementById('ev2-delete-all-form').submit();
            });
        })();
    </script>
    <!-- Logout Modal (v1-Optik; Abmelden läuft als POST — v2-Sicherheitsfix) -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Abmelden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    Wie möchten Sie sich abmelden?
                </div>
                <div class="modal-footer">
                    <form method="post" action="<?= $e(EnotfV2Url::page('loggedout')) ?>" style="display:inline;">
                        <input type="hidden" name="mode" value="self">
                        <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>">
                        <button type="submit" class="ignis-btn">Mich abmelden</button>
                    </form>
                    <form method="post" action="<?= $e(EnotfV2Url::page('loggedout')) ?>" style="display:inline;">
                        <input type="hidden" name="mode" value="all">
                        <input type="hidden" name="_csrf" value="<?= $e(\Plugin\EnotfV2\Http\Csrf::token()) ?>">
                        <button type="submit" class="ignis-btn ignis-btn--danger">Alle abmelden</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
    <!-- Crew-Session-Live-Sync (10s-Poll gegen die v2-Session-API):
         Redirect bei deaktivierter Session, Crew-Spans + Session-Icon -->
    <script defer src="<?= asset('plugins/enotf-v2/assets/session-sync.js') ?>"></script>
    <?php require __DIR__ . '/_share-qm-assets.php'; // Teilen-/QM-Dialoge + Share-Anfragen-Poll ?>
</body>

</html>
