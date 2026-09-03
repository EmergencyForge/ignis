<table class="table table-striped twplus-table" id="fireProtocolTable">
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
        $fireRows = \Illuminate\Database\Capsule\Manager::table('intra_fire_incidents as i')
            ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
            ->where('i.leader_id', '=', function ($q) {
                $q->select('id')
                    ->from('intra_mitarbeiter')
                    ->where('discordtag', $_SESSION['discordtag']);
            })
            ->where('i.archived', 0)
            ->orderByDesc('i.created_at')
            ->get([
                'i.id',
                'i.incident_number',
                'i.location',
                'i.keyword',
                'i.started_at',
                'i.status',
                'i.finalized',
                'i.finalized_at',
                'i.leader_id',
                'm.fullname AS leader_name',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

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
                    $status = "<span class='ignis-chip ignis-chip--secondary'>In Bearbeitung</span>";
                } else {
                    $statusMap = [
                        0 => "<span class='ignis-chip'>Ungesehen</span>",
                        1 => "<span class='ignis-chip ignis-chip--warning'>In Prüfung</span>",
                        2 => "<span class='ignis-chip ignis-chip--success'>Freigegeben</span>",
                        3 => "<span class='ignis-chip ignis-chip--danger'>Ungenügend</span>",
                        4 => "<span class='ignis-chip ignis-chip--dark'>Ausgeblendet</span>",
                    ];
                    $status = $statusMap[(int)$row['status']] ?? "<span class='ignis-chip ignis-chip--secondary'>Unbekannt</span>";
                }

                echo "<tr>";
                echo "<td>" . $status . "</td>";
                echo "<td>" . htmlspecialchars($row['incident_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                echo "<td>" . htmlspecialchars($row['leader_name'] ?? 'Unbekannt') . "</td>";
                echo "<td><span style='display:none'>" . $row['started_at'] . "</span>" . $date . "</td>";
                echo "<td><a href='" . BASE_PATH . "firetab/view?id={$row['id']}' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary'><i class='fa-regular fa-eye'></i> Ansehen</a></td>";
                echo "</tr>";
            }
        }
        ?>
    </tbody>
</table>
