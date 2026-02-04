<?php
$currentFd = $row['fachdienste'];
if (!empty($currentFd)) {
    $fdDecode = json_decode($currentFd, true);

    $stmtfd = $pdo->query("SELECT sgnr, sgname FROM intra_mitarbeiter_fdquali");
    $fdNamen = [];
    while ($fd = $stmtfd->fetch(PDO::FETCH_ASSOC)) {
        $fdNamen[$fd['sgnr']] = $fd['sgname'];
    }

    $fdGroups = [];
    foreach ($fdDecode as $fdValue) {
        if (preg_match('/^\d{3}$/', $fdValue)) {
            $groupKey = substr($fdValue, 0, 2) . "0";
            $fdGroups[$groupKey][] = $fdValue;
        }
    }

    if (empty($fdGroups)) {
        echo "<p class='mb-0'>Keine Fachdienste hinterlegt</p>";
    } else {
        ksort($fdGroups);
        foreach ($fdGroups as $groupKey => $items) {
            echo "<div class='abteilung-container'>";
            echo "<p class='abteilung mb-0'>Abteilung $groupKey</p>";
            foreach ($items as $item) {
                $fdNameText = $fdNamen[$item] ?? "Unknown";
                echo '<span class="badge text-bg-secondary">' . htmlspecialchars($fdNameText) . '</span>';
            }
            echo "</div>";
        }
    }
}
