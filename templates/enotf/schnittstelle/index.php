<?php
/**
 * View: eNOTF Arrivalboard (Krankenhaussicht)
 *
 * @var \PDO $pdo
 */

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$ziel = $_GET['klinik'] ?? NULL;
$zielName = '';

// Zielname für die Anzeige ermitteln
if ($ziel) {
    if (strpos($ziel, 'poi_') === 0) {
        // POI-basiertes Krankenhaus
        $poiId = substr($ziel, 4);
        $stmt = $pdo->prepare("SELECT name FROM intra_edivi_pois WHERE id = :id");
        $stmt->execute(['id' => $poiId]);
        $poiData = $stmt->fetch(PDO::FETCH_ASSOC);
        $zielName = $poiData ? $poiData['name'] : 'Unbekanntes Krankenhaus';
    } else {
        // Klassisches Ziel
        $stmt = $pdo->prepare("SELECT name FROM intra_edivi_ziele WHERE identifier = :identifier");
        $stmt->execute(['identifier' => $ziel]);
        $zielData = $stmt->fetch(PDO::FETCH_ASSOC);
        $zielName = $zielData ? $zielData['name'] : 'Unbekanntes Ziel';
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Arrivalboard &rsaquo; eNOTF';
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>

</head>

<body data-bs-theme="dark" style="overflow-x:hidden; display: flex; flex-direction: column; min-height: 100vh;" id="edivi__arrivalboard">
    <div class="container-fluid" style="flex: 1;">
        <div class="row h-100">
            <div class="col" id="edivi__content">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-center">Ankunft</th>
                            <th colspan="2">Verdachtsdiagnose</th>
                            <th>Anmeldetext</th>
                            <th class="text-center">Kreislauf</th>
                            <th class="text-center">GCS</th>
                            <th class="text-center">Intubiert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pdo->prepare("UPDATE intra_edivi_prereg SET active = 0 WHERE active = 1 AND arrival IS NOT NULL AND arrival < NOW() - INTERVAL 10 MINUTE")->execute();
                        if ($ziel) {
                            $stmt = $pdo->prepare("SELECT * FROM intra_edivi_prereg WHERE ziel = :ziel AND active = 1 ORDER BY arrival ASC");
                            $stmt->bindParam(':ziel', $ziel);
                        } else {
                            $stmt = $pdo->prepare("SELECT * FROM intra_edivi_prereg WHERE active = 1 ORDER BY arrival ASC");
                        }
                        $stmt->execute();
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($result as $row) {
                            if (!empty($row['arrival'])) {
                                $row['arrival'] = (new DateTime($row['arrival']))->format('H:i');
                            } else {
                                $row['arrival'] = '—';
                            }

                            if ($row['priority'] == 1) {
                                $rowClass = 'edivi__arrivalboard-prio-1';
                            } elseif ($row['priority'] == 2) {
                                $rowClass = 'edivi__arrivalboard-prio-2';
                            } else {
                                $rowClass = 'edivi__arrivalboard-prio-0';
                            }

                            if ($row['geschlecht'] == 1) {
                                $row['geschlecht'] = '<i class="fa-solid fa-venus"></i>';
                            } elseif ($row['geschlecht'] == 0) {
                                $row['geschlecht'] = '<i class="fa-solid fa-mars"></i>';
                            } elseif ($row['geschlecht'] == 2) {
                                $row['geschlecht'] = '<i class="fa-solid fa-mars-and-venus"></i>';
                            } else {
                                $row['geschlecht'] = '<i class="fa-solid fa-question" style="opacity:0.5"></i>';
                            }

                            if (empty($row['alter'])) {
                                $row['alter'] = '—';
                            }

                            if ($row['kreislauf'] == 1) {
                                $row['kreislauf'] = 'stabil';
                            } else {
                                $row['kreislauf'] = '<span style="color:red">instabil</span>';
                            }

                            if ($row['intubiert'] == 0) {
                                $row['intubiert'] = 'nein';
                            } else {
                                $row['intubiert'] = '<span style="color:red">ja</span>';
                            }

                        ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="edivi__arrivalboard-time">
                                    <span><?= $row['arrival'] ?></span><br>
                                    <?= $row['fahrzeug'] ?>
                                </td>
                                <td><?= $row['diagnose'] ?></td>
                                <td class="edivi__arrivalboard-gender"><?= $row['geschlecht'] ?><br>
                                    <?= $row['alter'] ?></td>
                                <td class="edivi__arrivalboard-text"><?= $row['text'] ?></td>
                                <td class="text-center"><?= $row['kreislauf'] ?></td>
                                <td class="text-center"><?= $row['gcs'] ?></td>
                                <td class="text-center"><?= $row['intubiert'] ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <footer class="py-2 text-center text-white" style="background-color: #131313;">
        <div class="grid grid-cols-3 items-center">
            <div class="flex items-center ps-4" style="font-size:2rem">
                eNOTFArrivalboard
                <?php if ($ziel && !empty($zielName)): ?>
                    <span style="font-size:0.9rem; opacity:0.6; margin-left:1rem;"><?= htmlspecialchars($zielName) ?></span>
                <?php endif; ?>
            </div>
            <div class="flex justify-center">
                <img src="https://web-assets.emergencyforge.de/images/defaultLogo.webp" alt="EmergencyForge Logo" height="48px" width="auto">
            </div>
            <div class="flex items-center justify-end">
                <button id="sound-toggle" onclick="toggleSound()" style="background:none;border:1px solid rgba(255,255,255,0.3);color:#fff;border-radius:6px;padding:4px 12px;cursor:pointer;font-size:1.2rem;margin-right:1rem;opacity:1;transition:opacity 0.2s" title="Benachrichtigungston deaktivieren">
                    <i class="fa-solid fa-bell" id="sound-icon"></i>
                </button>
                <div class="mr-3 flex flex-col items-end">
                    <span id="current-time"><?= $currentTime ?></span>
                    <span id="current-date"><?= $currentDate ?></span>
                </div>
            </div>
        </div>
    </footer>
    <?php
    include __DIR__ . '/../../../assets/functions/enotf/clock.php';
    ?>
    <script>
        // Sound state: enabled by default, AudioContext created eagerly
        var audioCtx = null;
        var soundEnabled = true;

        try {
            audioCtx = new(window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('AudioContext creation failed:', e);
        }

        // Resume suspended AudioContext on first user interaction anywhere on page
        document.addEventListener('click', function resumeAudio() {
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
            document.removeEventListener('click', resumeAudio);
        }, {
            once: true
        });

        function toggleSound() {
            if (soundEnabled) {
                soundEnabled = false;
                if (audioCtx) {
                    audioCtx.close();
                    audioCtx = null;
                }
                document.getElementById('sound-icon').className = 'fa-solid fa-bell-slash';
                document.getElementById('sound-toggle').style.opacity = '0.7';
                document.getElementById('sound-toggle').title = 'Benachrichtigungston aktivieren';
            } else {
                try {
                    audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                    if (audioCtx.state === 'suspended') audioCtx.resume();
                    soundEnabled = true;
                    document.getElementById('sound-icon').className = 'fa-solid fa-bell';
                    document.getElementById('sound-toggle').style.opacity = '1';
                    document.getElementById('sound-toggle').title = 'Benachrichtigungston deaktivieren';
                    playBellSound();
                } catch (e) {
                    console.warn('AudioContext creation failed:', e);
                }
            }
        }

        function playBellSound() {
            if (!soundEnabled || !audioCtx) return;
            try {
                if (audioCtx.state === 'suspended') audioCtx.resume();
                // Bell-like tone: two oscillators for a richer sound
                [830, 1660].forEach(function(freq) {
                    var osc = audioCtx.createOscillator();
                    var gain = audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
                    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 1.5);
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(audioCtx.currentTime);
                    osc.stop(audioCtx.currentTime + 1.5);
                });
            } catch (e) {
                console.warn('Audio playback failed:', e);
            }
        }

        (function() {
            var basePath = '<?= BASE_PATH ?>';
            var ziel = <?= json_encode($ziel) ?>;
            var knownIds = new Set(<?= json_encode(array_map(function ($r) {
                                        return (int)$r['id'];
                                    }, $result)) ?>);

            function escapeHtml(str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            function formatRow(row) {
                var arrival = '\u2014';
                if (row.arrival) {
                    try {
                        var d = new Date(row.arrival.replace(' ', 'T'));
                        arrival = d.toLocaleTimeString('de-DE', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        });
                    } catch (e) {
                        arrival = row.arrival;
                    }
                }

                var rowClass = 'edivi__arrivalboard-prio-0';
                if (row.priority == 1) rowClass = 'edivi__arrivalboard-prio-1';
                else if (row.priority == 2) rowClass = 'edivi__arrivalboard-prio-2';

                var geschlecht;
                if (row.geschlecht == 1) geschlecht = '<i class="fa-solid fa-venus"></i>';
                else if (row.geschlecht == 0) geschlecht = '<i class="fa-solid fa-mars"></i>';
                else if (row.geschlecht == 2) geschlecht = '<i class="fa-solid fa-mars-and-venus"></i>';
                else geschlecht = '<i class="fa-solid fa-question" style="opacity:0.5"></i>';

                var alter = row.alter || '\u2014';
                var kreislauf = row.kreislauf == 1 ? 'stabil' : '<span style="color:red">instabil</span>';
                var intubiert = row.intubiert == 0 ? 'nein' : '<span style="color:red">ja</span>';

                return '<tr class="' + rowClass + '">' +
                    '<td class="edivi__arrivalboard-time"><span>' + escapeHtml(arrival) + '</span><br>' + escapeHtml(row.fahrzeug) + '</td>' +
                    '<td>' + escapeHtml(row.diagnose) + '</td>' +
                    '<td class="edivi__arrivalboard-gender">' + geschlecht + '<br>' + escapeHtml(alter) + '</td>' +
                    '<td class="edivi__arrivalboard-text">' + escapeHtml(row.text) + '</td>' +
                    '<td class="text-center">' + kreislauf + '</td>' +
                    '<td class="text-center">' + escapeHtml(row.gcs) + '</td>' +
                    '<td class="text-center">' + intubiert + '</td>' +
                    '</tr>';
            }

            function pollPreregistrations() {
                var url = basePath + 'api/enotf/prereg.php' + (ziel ? '?klinik=' + encodeURIComponent(ziel) : '');
                fetch(url)
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(json) {
                        if (!json.success) return;
                        var rows = json.data;
                        var newIds = new Set(rows.map(function(r) {
                            return parseInt(r.id);
                        }));

                        // Check for new entries
                        var hasNew = false;
                        newIds.forEach(function(id) {
                            if (!knownIds.has(id)) hasNew = true;
                        });

                        if (hasNew) {
                            playBellSound();
                        }

                        knownIds = newIds;

                        // Update table
                        var tbody = document.querySelector('#edivi__content table tbody');
                        if (tbody) {
                            tbody.innerHTML = rows.map(formatRow).join('');
                        }
                    })
                    .catch(function(err) {
                        console.warn('Polling error:', err);
                    });
            }

            // Poll every 15 seconds
            setInterval(pollPreregistrations, 15000);
        })();
    </script>
</body>

</html>