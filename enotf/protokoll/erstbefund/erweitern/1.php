<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
}
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';

use App\Auth\Permissions;

$daten = array();

if (isset($_GET['enr'])) {
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);
    $daten = $stmt->fetch(PDO::FETCH_ASSOC);
    if (count($daten) == 0) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }
} else {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

$ist_freigegeben = ($daten['freigegeben'] == 1);
$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;
$enr = $daten['enr'];
$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');
$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

// Body silhouette path (realistic human shape)
$bodyPath = "M104.265,117.959c-0.304,3.58,2.126,22.529,3.38,29.959c0.597,3.52,2.234,9.255,1.645,12.3 c-0.841,4.244-1.084,9.736-0.621,12.934c0.292,1.942,1.211,10.899-0.104,14.175c-0.688,1.718-1.949,10.522-1.949,10.522 c-3.285,8.294-1.431,7.886-1.431,7.886c1.017,1.248,2.759,0.098,2.759,0.098c1.327,0.846,2.246-0.201,2.246-0.201 c1.139,0.943,2.467-0.116,2.467-0.116c1.431,0.743,2.758-0.627,2.758-0.627c0.822,0.414,1.023-0.109,1.023-0.109 c2.466-0.158-1.376-8.05-1.376-8.05c-0.92-7.088,0.913-11.033,0.913-11.033c6.004-17.805,6.309-22.53,3.909-29.24 c-0.676-1.937-0.847-2.704-0.536-3.545c0.719-1.941,0.195-9.748,1.072-12.848c1.692-5.979,3.361-21.142,4.231-28.217 c1.169-9.53-4.141-22.308-4.141-22.308c-1.163-5.2,0.542-23.727,0.542-23.727c2.381,3.705,2.29,10.245,2.29,10.245 c-0.378,6.859,5.541,17.342,5.541,17.342c2.844,4.332,3.921,8.442,3.921,8.747c0,1.248-0.273,4.269-0.273,4.269l0.109,2.631 c0.049,0.67,0.426,2.977,0.365,4.092c-0.444,6.862,0.646,5.571,0.646,5.571c0.92,0,1.931-5.522,1.931-5.522 c0,1.424-0.348,5.687,0.42,7.295c0.919,1.918,1.595-0.329,1.607-0.78c0.243-8.737,0.768-6.448,0.768-6.448 c0.511,7.088,1.139,8.689,2.265,8.135c0.853-0.407,0.073-8.506,0.073-8.506c1.461,4.811,2.569,5.577,2.569,5.577 c2.411,1.693,0.92-2.983,0.585-3.909c-1.784-4.92-1.839-6.625-1.839-6.625c2.229,4.421,3.909,4.257,3.909,4.257 c2.174-0.694-1.9-6.954-4.287-9.953c-1.218-1.528-2.789-3.574-3.245-4.789c-0.743-2.058-1.304-8.674-1.304-8.674 c-0.225-7.807-2.155-11.198-2.155-11.198c-3.3-5.282-3.921-15.135-3.921-15.135l-0.146-16.635 c-1.157-11.347-9.518-11.429-9.518-11.429c-8.451-1.258-9.627-3.988-9.627-3.988c-1.79-2.576-0.767-7.514-0.767-7.514 c1.485-1.208,2.058-4.415,2.058-4.415c2.466-1.891,2.345-4.658,1.206-4.628c-0.914,0.024-0.707-0.733-0.707-0.733 C115.068,0.636,104.01,0,104.01,0h-1.688c0,0-11.063,0.636-9.523,13.089c0,0,0.207,0.758-0.715,0.733 c-1.136-0.03-1.242,2.737,1.215,4.628c0,0,0.572,3.206,2.058,4.415c0,0,1.023,4.938-0.767,7.514c0,0-1.172,2.73-9.627,3.988 c0,0-8.375,0.082-9.514,11.429l-0.158,16.635c0,0-0.609,9.853-3.922,15.135c0,0-1.921,3.392-2.143,11.198 c0,0-0.563,6.616-1.303,8.674c-0.451,1.209-2.021,3.255-3.249,4.789c-2.408,2.993-6.455,9.24-4.29,9.953 c0,0,1.689,0.164,3.909-4.257c0,0-0.046,1.693-1.827,6.625c-0.35,0.914-1.839,5.59,0.573,3.909c0,0,1.117-0.767,2.569-5.577 c0,0-0.779,8.099,0.088,8.506c1.133,0.555,1.751-1.047,2.262-8.135c0,0,0.524-2.289,0.767,6.448 c0.012,0.451,0.673,2.698,1.596,0.78c0.779-1.608,0.429-5.864,0.429-7.295c0,0,0.999,5.522,1.933,5.522 c0,0,1.099,1.291,0.648-5.571c-0.073-1.121,0.32-3.422,0.369-4.092l0.106-2.631c0,0-0.274-3.014-0.274-4.269 c0-0.311,1.078-4.415,3.921-8.747c0,0,5.913-10.488,5.532-17.342c0,0-0.082-6.54,2.299-10.245c0,0,1.69,18.526,0.545,23.727 c0,0-5.319,12.778-4.146,22.308c0.864,7.094,2.53,22.237,4.226,28.217c0.886,3.094,0.362,10.899,1.072,12.848 c0.32,0.847,0.152,1.627-0.536,3.545c-2.387,6.71-2.083,11.436,3.921,29.24c0,0,1.848,3.945,0.914,11.033 c0,0-3.836,7.892-1.379,8.05c0,0,0.192,0.523,1.023,0.109c0,0,1.327,1.37,2.761,0.627c0,0,1.328,1.06,2.463,0.116 c0,0,0.91,1.047,2.237,0.201c0,0,1.742,1.175,2.777-0.098c0,0,1.839,0.408-1.435-7.886c0,0-1.254-8.793-1.945-10.522 c-1.318-3.275-0.387-12.251-0.106-14.175c0.453-3.216,0.21-8.695-0.618-12.934c-0.606-3.038,1.035-8.774,1.641-12.3 c1.245-7.423,3.685-26.373,3.38-29.959l1.008,0.354C103.809,118.312,104.265,117.959,104.265,117.959z";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
    <style>
        .bodymap-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 0;
            height: 100%;
        }

        .bodymap-views {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 32px;
        }

        .bodymap-view-label {
            text-align: center;
            font-size: 0.7rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .bodymap-svg {
            height: calc(100vh - 200px);
            max-height: 560px;
            min-height: 180px;
            width: auto;
        }

        .bodymap-svg-back {
            height: calc(100vh - 200px);
            max-height: 560px;
            min-height: 180px;
            width: auto;
            opacity: 0.6;
        }

        /* Zone styling */
        .bodymap-zone {
            fill: rgba(60, 60, 60, 0.4);
            stroke: none;
            cursor: pointer;
            transition: fill 0.25s ease;
        }

        .bodymap-zone:hover {
            filter: brightness(1.4);
        }

        .bodymap-zone.bodymap-zone-selected {
            stroke: #fff;
            stroke-width: 0.8;
            stroke-dasharray: 2 1;
        }

        .bodymap-zone[data-severity="1"] {
            fill: rgba(60, 60, 60, 0.4);
        }

        .bodymap-zone[data-severity="2"] {
            fill: rgba(46, 204, 113, 0.55);
        }

        .bodymap-zone[data-severity="3"] {
            fill: rgba(241, 196, 15, 0.55);
        }

        .bodymap-zone[data-severity="4"] {
            fill: rgba(231, 76, 60, 0.6);
        }

        .bodymap-zone[data-severity="99"] {
            fill: rgba(120, 120, 120, 0.45);
            stroke: #888;
            stroke-width: 0.3;
            stroke-dasharray: 1.5 1;
        }

        .bodymap-outline {
            fill: none;
            stroke: #999;
            stroke-width: 0.4;
            pointer-events: none;
        }

        .bodymap-zone-divider {
            stroke: #777;
            stroke-width: 0.3;
            stroke-dasharray: 1.5 1;
            pointer-events: none;
        }

        /* Panel */
        .bodymap-panel {
            width: 100%;
            max-width: 400px;
        }


        .bodymap-legend {
            display: none;
        }

        .bodymap-legend h6,
        .bodymap-legend-item,
        .bodymap-legend-color {
            display: none;
        }

        .bodymap-detail {
            padding: 10px;
            background: #252525;
            border: 1px solid #444;
            display: none;
        }

        .bodymap-detail.active {
            display: block;
        }

        .bodymap-detail h6 {
            font-size: 0.85rem;
            color: #fff;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #444;
        }

        .bodymap-detail-severity {
            display: flex;
            gap: 4px;
            margin-bottom: 8px;
        }

        .bodymap-detail-severity button {
            flex: 1;
            padding: 8px 4px;
            border: 1px solid #555;
            background: #333;
            color: #ccc;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .bodymap-detail-severity button:hover { background: #444; }

        .bodymap-detail-severity button.active-keine {
            background: #444; border-color: #888; color: #fff;
        }
        .bodymap-detail-severity button.active-leicht {
            background: rgba(46, 204, 113, 0.35); border-color: #2ecc71; color: #2ecc71;
        }
        .bodymap-detail-severity button.active-mittel {
            background: rgba(241, 196, 15, 0.35); border-color: #f1c40f; color: #f1c40f;
        }
        .bodymap-detail-severity button.active-schwer {
            background: rgba(231, 76, 60, 0.35); border-color: #e74c3c; color: #e74c3c;
        }
        .bodymap-detail-severity button.active-nu {
            background: rgba(120, 120, 120, 0.35); border-color: #888; color: #aaa;
        }

        .bodymap-detail-woundtype {
            display: none;
            gap: 4px;
        }

        .bodymap-detail-woundtype.visible {
            display: flex;
        }

        .bodymap-detail-woundtype button {
            flex: 1;
            padding: 8px 4px;
            border: 1px solid #555;
            background: #333;
            color: #ccc;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .bodymap-detail-woundtype button:hover { background: #444; }

        .bodymap-detail-woundtype button.active {
            background: rgba(52, 152, 219, 0.35); border-color: #3498db; color: #3498db;
        }

        .bodymap-detail-label {
            font-size: 0.7rem;
            color: #888;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 500px) {
            .bodymap-views {
                gap: 8px;
            }
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="erstbefund" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php include __DIR__ . '/../../../../assets/components/enotf/topbar.php'; ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awfrei_1,zyanose_1"><span>Atemwege</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_symptome,b_auskult"><span>Atmung</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/kreislauf/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_kreislauf,c_puls_rad,c_puls_reg"><span>Kreislauf</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/index.php?enr=<?= $daten['enr'] ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3"><span>Neurologie</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/erweitern/index.php?enr=<?= $daten['enr'] ?>" class="active"><span>Erweitern</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/ekg/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_ekg"><span>EKG-Befund</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/psychisch/index.php?enr=<?= $daten['enr'] ?>" data-requires="psych"><span>psych. Zustand</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/messwerte/index.php?enr=<?= $daten['enr'] ?>" data-requires="spo2,atemfreq,rrsys,herzfreq,bz"><span>Messwerte</span></a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <input type="checkbox" class="btn-check" id="erweitern-ohne-path"
                                data-quickfill='{"v_muster_k": 1, "v_muster_w": 1, "v_muster_t": 1, "v_muster_a": 1, "v_muster_al": 1, "v_muster_bl": 1}'
                                autocomplete="off">
                            <label for="erweitern-ohne-path" class="edivi__unauffaellig">keine</label>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/erweitern/1.php?enr=<?= $daten['enr'] ?>" class="active"><span>Verletzungen</span></a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/erweitern/2.php?enr=<?= $daten['enr'] ?>"><span>Schmerzen</span></a>
                        </div>

                        <!-- Body Map -->
                        <div class="col d-flex" style="padding: 0;">
                            <div class="bodymap-container">
                                <div class="bodymap-views">
                                    <!-- Front view -->
                                    <div>
                                        <div class="bodymap-view-label">Vorne</div>
                                        <svg class="bodymap-svg" viewBox="55 0 96 210" xmlns="http://www.w3.org/2000/svg">
                                            <defs>
                                                <clipPath id="body-clip-front">
                                                    <path d="<?= $bodyPath ?>" />
                                                </clipPath>
                                            </defs>

                                            <g clip-path="url(#body-clip-front)">
                                                <!-- Head (inkl. Hals) -->
                                                <rect class="bodymap-zone" data-field="v_muster_k" data-label="Schädel-Hirn"
                                                    x="0" y="0" width="206" height="28" />
                                                <!-- Thorax -->
                                                <rect class="bodymap-zone" data-field="v_muster_t" data-label="Thorax"
                                                    x="83" y="28" width="40" height="34" />
                                                <!-- Abdomen -->
                                                <rect class="bodymap-zone" data-field="v_muster_a" data-label="Abdomen"
                                                    x="83" y="62" width="40" height="28" />
                                                <!-- Left Leg -->
                                                <rect class="bodymap-zone" data-field="v_muster_bl" data-label="Untere Extremitäten"
                                                    x="83" y="90" width="20" height="120" />
                                                <!-- Right Leg -->
                                                <rect class="bodymap-zone" data-field="v_muster_bl" data-label="Untere Extremitäten"
                                                    x="103" y="90" width="20" height="120" />
                                                <!-- Arms (after legs for hand priority) -->
                                                <rect class="bodymap-zone" data-field="v_muster_al" data-label="Obere Extremitäten"
                                                    x="0" y="28" width="83" height="182" />
                                                <rect class="bodymap-zone" data-field="v_muster_al" data-label="Obere Extremitäten"
                                                    x="123" y="28" width="83" height="182" />
                                            </g>

                                            <path class="bodymap-outline" d="<?= $bodyPath ?>" />

                                            <g clip-path="url(#body-clip-front)" style="pointer-events: none;">
                                                <line class="bodymap-zone-divider" x1="0" y1="28" x2="206" y2="28" />
                                                <line class="bodymap-zone-divider" x1="83" y1="28" x2="83" y2="90" />
                                                <line class="bodymap-zone-divider" x1="123" y1="28" x2="123" y2="90" />
                                                <line class="bodymap-zone-divider" x1="83" y1="62" x2="123" y2="62" />
                                                <line class="bodymap-zone-divider" x1="83" y1="90" x2="123" y2="90" />
                                                <line class="bodymap-zone-divider" x1="103" y1="90" x2="103" y2="210" />
                                            </g>
                                        </svg>
                                    </div>

                                    <!-- Back view -->
                                    <div>
                                        <div class="bodymap-view-label">Hinten</div>
                                        <svg class="bodymap-svg-back" viewBox="55 0 96 210" xmlns="http://www.w3.org/2000/svg">
                                            <defs>
                                                <clipPath id="body-clip-back">
                                                    <path d="<?= $bodyPath ?>" />
                                                </clipPath>
                                            </defs>

                                            <g clip-path="url(#body-clip-back)">
                                                <!-- Entire back as non-interactive background -->
                                                <rect style="fill: rgba(40,40,40,0.3); pointer-events: none;"
                                                    x="0" y="0" width="206" height="210" />
                                                <!-- Wirbelsäule — narrow spine strip -->
                                                <rect class="bodymap-zone" data-field="v_muster_w" data-label="Wirbelsäule"
                                                    x="99" y="16" width="8" height="74" rx="2" />
                                            </g>

                                            <path class="bodymap-outline" d="<?= $bodyPath ?>" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Right Panel -->
                                <div class="bodymap-panel">
                                    <div class="bodymap-legend">
                                        <h6>Legende</h6>
                                        <div class="bodymap-legend-item">
                                            <div class="bodymap-legend-color" style="background: rgba(60,60,60,0.5); border: 1px solid #555;"></div>
                                            <span>Keine Verletzung</span>
                                        </div>
                                        <div class="bodymap-legend-item">
                                            <div class="bodymap-legend-color" style="background: rgba(46,204,113,0.6); border: 1px solid #2ecc71;"></div>
                                            <span>Leicht</span>
                                        </div>
                                        <div class="bodymap-legend-item">
                                            <div class="bodymap-legend-color" style="background: rgba(241,196,15,0.6); border: 1px solid #f1c40f;"></div>
                                            <span>Mittel</span>
                                        </div>
                                        <div class="bodymap-legend-item">
                                            <div class="bodymap-legend-color" style="background: rgba(231,76,60,0.65); border: 1px solid #e74c3c;"></div>
                                            <span>Schwer</span>
                                        </div>
                                        <div class="bodymap-legend-item">
                                            <div class="bodymap-legend-color" style="background: rgba(120,120,120,0.5); border: 1px dashed #888;"></div>
                                            <span>Nicht untersucht</span>
                                        </div>
                                    </div>

                                    <div class="bodymap-detail" id="bodymap-detail">
                                        <h6 id="bodymap-detail-title">Region</h6>

                                        <div class="bodymap-detail-label">Schweregrad</div>
                                        <div class="bodymap-detail-severity" id="bodymap-severity-btns">
                                            <button type="button" data-value="1">Keine</button>
                                            <button type="button" data-value="2">Leicht</button>
                                            <button type="button" data-value="3">Mittel</button>
                                            <button type="button" data-value="4">Schwer</button>
                                        </div>

                                        <div class="bodymap-detail-label" style="margin-top: 6px;">Wundart</div>
                                        <div class="bodymap-detail-woundtype" id="bodymap-woundtype-btns">
                                            <button type="button" data-value="1">Offen</button>
                                            <button type="button" data-value="2">Geschlossen</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden inputs for compatibility -->
                        <?php
                        $bodyFields = [
                            'v_muster_k' => $daten['v_muster_k'],
                            'v_muster_k1' => $daten['v_muster_k1'],
                            'v_muster_w' => $daten['v_muster_w'],
                            'v_muster_w1' => $daten['v_muster_w1'],
                            'v_muster_t' => $daten['v_muster_t'],
                            'v_muster_t1' => $daten['v_muster_t1'],
                            'v_muster_a' => $daten['v_muster_a'],
                            'v_muster_a1' => $daten['v_muster_a1'],
                            'v_muster_al' => $daten['v_muster_al'],
                            'v_muster_al1' => $daten['v_muster_al1'],
                            'v_muster_bl' => $daten['v_muster_bl'],
                            'v_muster_bl1' => $daten['v_muster_bl1'],
                        ];
                        foreach ($bodyFields as $name => $val) :
                        ?>
                            <input type="hidden" name="<?= $name ?>" value="<?= htmlspecialchars($val ?? '') ?>" data-ignore-autosave />
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../assets/functions/enotf/clock.php';
    ?>

    <script>
    // Fix SVG clip-path url(#id) references for iframe/CEF (FiveM) compatibility
    (function() {
        var baseUrl = window.location.href.split('#')[0];
        document.querySelectorAll('[clip-path]').forEach(function(el) {
            var val = el.getAttribute('clip-path');
            if (val && val.indexOf('url(#') === 0) {
                el.setAttribute('clip-path', val.replace('url(#', 'url(' + baseUrl + '#'));
            }
        });
    })();

    (function() {
        const enr = <?= json_encode($enr) ?>;
        const basePath = <?= json_encode(BASE_PATH) ?>;
        const isReadOnly = <?= $ist_freigegeben ? 'true' : 'false' ?>;

        const woundTypeMap = {
            'v_muster_k': 'v_muster_k1', 'v_muster_w': 'v_muster_w1',
            'v_muster_t': 'v_muster_t1', 'v_muster_a': 'v_muster_a1',
            'v_muster_al': 'v_muster_al1', 'v_muster_bl': 'v_muster_bl1'
        };

        const fieldLabels = {
            'v_muster_k': 'Schädel-Hirn', 'v_muster_w': 'Wirbelsäule',
            'v_muster_t': 'Thorax', 'v_muster_a': 'Abdomen',
            'v_muster_al': 'Obere Extremitäten', 'v_muster_bl': 'Untere Extremitäten'
        };

        // All severity fields
        const severityFields = ['v_muster_k', 'v_muster_w', 'v_muster_t', 'v_muster_a', 'v_muster_al', 'v_muster_bl'];

        const severityClasses = { '1': 'active-keine', '2': 'active-leicht', '3': 'active-mittel', '4': 'active-schwer', '99': 'active-nu' };

        let selectedField = null;

        // Get effective severity: treat null/empty/0 as "1" (keine)
        function getSeverity(field) {
            if (!window.__dynamicDaten) return '1';
            var val = String(window.__dynamicDaten[field] || '');
            return (val === '2' || val === '3' || val === '4' || val === '99') ? val : '1';
        }

        function initZones() {
            document.querySelectorAll('.bodymap-zone').forEach(function(zone) {
                zone.setAttribute('data-severity', getSeverity(zone.getAttribute('data-field')));
            });
        }

        function updateZoneColors(field, severity) {
            document.querySelectorAll('.bodymap-zone[data-field="' + field + '"]').forEach(function(zone) {
                zone.setAttribute('data-severity', String(severity));
            });
        }

        function highlightSelected(field) {
            document.querySelectorAll('.bodymap-zone').forEach(function(zone) {
                zone.classList.toggle('bodymap-zone-selected', zone.getAttribute('data-field') === field);
            });
        }

        function saveField(field, value, label, clearNull) {
            if (isReadOnly) return;
            var ajaxData = { enr: enr, field: field };
            if (!clearNull) ajaxData.value = value;

            $.ajax({
                url: basePath + 'api/enotf/save-fields.php',
                type: 'POST',
                data: ajaxData,
                success: function() {
                    if (window.showToast) window.showToast("Feld gespeichert", 'success');
                    var h = document.querySelector('input[type="hidden"][name="' + field + '"]');
                    if (h) h.value = clearNull ? '' : value;
                    if (window.__dynamicDaten) window.__dynamicDaten[field] = clearNull ? null : value;
                },
                error: function() {
                    if (window.showToast) window.showToast("Fehler beim Speichern von '" + label + "'", 'error');
                }
            });
        }

        // Default empty fields to "keine" (1) locally for UI display only — no AJAX
        function autoInitKeineFields() {
            if (!window.__dynamicDaten) return;
            severityFields.forEach(function(field) {
                var val = window.__dynamicDaten[field];
                if (val === null || val === '' || val === undefined || val === 0 || val === '0') {
                    saveField(field, '1', fieldLabels[field] || field);
                }
            });
        }

        function showDetail(field) {
            selectedField = field;
            highlightSelected(field);

            var panel = document.getElementById('bodymap-detail');
            panel.classList.add('active');
            document.getElementById('bodymap-detail-title').textContent = fieldLabels[field] || field;

            var currentSeverity = getSeverity(field);
            document.getElementById('bodymap-severity-btns').querySelectorAll('button').forEach(function(btn) {
                btn.className = '';
                if (btn.getAttribute('data-value') === currentSeverity) {
                    btn.classList.add(severityClasses[currentSeverity] || '');
                }
            });

            var isInjured = (currentSeverity === '2' || currentSeverity === '3' || currentSeverity === '4');
            var woundBtns = document.getElementById('bodymap-woundtype-btns');
            woundBtns.classList.toggle('visible', isInjured);

            if (isInjured) {
                var woundField = woundTypeMap[field];
                var currentWound = window.__dynamicDaten ? String(window.__dynamicDaten[woundField] || '') : '';
                woundBtns.querySelectorAll('button').forEach(function(btn) {
                    btn.classList.toggle('active', btn.getAttribute('data-value') === currentWound);
                });
            }
        }

        // SVG zone clicks
        document.querySelectorAll('.bodymap-zone').forEach(function(zone) {
            zone.addEventListener('click', function(e) {
                e.preventDefault();
                var field = this.getAttribute('data-field');
                showDetail(field);
            });
        });

        // Severity buttons
        document.getElementById('bodymap-severity-btns').addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn || !selectedField) return;

            var value = btn.getAttribute('data-value');
            var label = fieldLabels[selectedField] || selectedField;

            // Update local data immediately so showDetail sees the new value
            if (window.__dynamicDaten) window.__dynamicDaten[selectedField] = value;

            updateZoneColors(selectedField, value);
            saveField(selectedField, value, label, false);

            if (value === '1' || value === '99') {
                if (window.__dynamicDaten) window.__dynamicDaten[woundTypeMap[selectedField]] = null;
                saveField(woundTypeMap[selectedField], null, label + ' (Wundart)', true);
            }

            showDetail(selectedField);
        });

        // Wound type buttons
        document.getElementById('bodymap-woundtype-btns').addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn || !selectedField) return;

            saveField(woundTypeMap[selectedField], btn.getAttribute('data-value'),
                (fieldLabels[selectedField] || selectedField) + ' (Wundart)', false);

            document.getElementById('bodymap-woundtype-btns').querySelectorAll('button').forEach(function(b) {
                b.classList.toggle('active', b === btn);
            });
        });

        // Quickfill sync
        $(document).on('change', '#erweitern-ohne-path', function() {
            setTimeout(function() {
                initZones();
                // Reset all wound types when quickfill sets everything to "keine"
                severityFields.forEach(function(field) {
                    if (getSeverity(field) === '1') {
                        var woundField = woundTypeMap[field];
                        var currentWound = window.__dynamicDaten ? window.__dynamicDaten[woundField] : null;
                        if (currentWound !== null && currentWound !== '' && currentWound !== undefined) {
                            saveField(woundField, null, (fieldLabels[field] || field) + ' (Wundart)', true);
                        }
                    }
                });
                if (selectedField) showDetail(selectedField);
            }, 600);
        });

        // Init
        function tryInit() {
            if (!window.__dynamicDaten) return false;
            autoInitKeineFields();
            initZones();
            return true;
        }

        if (!tryInit()) {
            var w = setInterval(function() { if (tryInit()) clearInterval(w); }, 100);
        }
    })();
    </script>

    <?php if ($ist_freigegeben) : ?>
        <script>
            document.querySelectorAll('.bodymap-zone').forEach(function(z) {
                z.style.pointerEvents = 'none';
                z.style.cursor = 'default';
            });
            var detailPanel = document.querySelector('.bodymap-detail');
            if (detailPanel) detailPanel.style.display = 'none';
            document.querySelectorAll('input, textarea').forEach(function(e) { e.setAttribute('readonly', 'readonly'); });
            document.querySelectorAll('select').forEach(function(e) { e.setAttribute('disabled', 'disabled'); });
            document.querySelectorAll('.btn-check, .form-check-input').forEach(function(e) { e.setAttribute('disabled', 'disabled'); });
        </script>
    <?php endif; ?>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>
