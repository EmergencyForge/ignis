<table class="table table-striped" id="fireProtocolTable">
    <thead>
        <th scope="col">Status</th>
        <th scope="col">#</th>
        <th scope="col">Einsatzort</th>
        <th scope="col">Einsatzleiter</th>
        <th scope="col">Datum</th>
        <th scope="col"></th>
    </thead>
    <tbody>
        <?php
        $stmtFire = $pdo->prepare("
            SELECT 
                i.id,
                i.incident_number,
                i.location,
                i.keyword,
                i.started_at,
                i.status,
                i.finalized,
                i.finalized_at,
                i.leader_id,
                m.fullname AS leader_name
            FROM intra_fire_incidents i
            LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id
            WHERE i.leader_id = (SELECT id FROM intra_mitarbeiter WHERE discordtag = :discordtag)
            AND i.archived = 0
            ORDER BY i.created_at DESC
        ");
        $stmtFire->execute(['discordtag' => $_SESSION['discordtag']]);
        $fireRows = $stmtFire->fetchAll(PDO::FETCH_ASSOC);

        if (empty($fireRows)) {
            echo "<tr><td colspan='6'>
                <div class='empty-state'>
                    <div class='empty-state-icon'><i class='fa-solid fa-fire'></i></div>
                    <h6>Noch keine fireTab-Protokolle</h6>
                    <p>Deine abgeschlossenen Einsatzprotokolle aus dem fireTab erscheinen hier.</p>
                </div>
            </td></tr>";
        } else {
            foreach ($fireRows as $row) {
                $datetime = new DateTime($row['started_at']);
                $date = $datetime->format('d.m.Y | H:i');

                // Status Badge
                if (!$row['finalized']) {
                    $status = "<span class='badge text-bg-secondary'>In Bearbeitung</span>";
                } else {
                    $statusMap = [
                        0 => "<span class='badge'>Ungesehen</span>",
                        1 => "<span class='badge text-bg-warning'>In Prüfung</span>",
                        2 => "<span class='badge text-bg-success'>Freigegeben</span>",
                        3 => "<span class='badge text-bg-danger'>Ungenügend</span>",
                        4 => "<span class='badge text-bg-dark'>Ausgeblendet</span>",
                    ];
                    $status = $statusMap[(int)$row['status']] ?? "<span class='badge text-bg-secondary'>Unbekannt</span>";
                }

                echo "<tr>";
                echo "<td>" . $status . "</td>";
                echo "<td>" . htmlspecialchars($row['incident_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                echo "<td>" . htmlspecialchars($row['leader_name'] ?? 'Unbekannt') . "</td>";
                echo "<td><span style='display:none'>" . $row['started_at'] . "</span>" . $date . "</td>";
                echo "<td><a href='" . BASE_PATH . "einsatz/view.php?id={$row['id']}' class='btn btn-sm btn-soft-primary'><i class='fa-regular fa-eye'></i> Ansehen</a></td>";
                echo "</tr>";
            }
        }
        ?>
    </tbody>
</table>