<table class="table table-striped" id="documentTable">
    <thead>
        <th scope="col">Typ</th>
        <th scope="col">Status</th>
        <th scope="col">#</th>
        <th scope="col">Bearbeiter</th>
        <th scope="col">Am</th>
        <th scope="col"></th>
    </thead>
    <tbody>
        <?php
        $query = "
    SELECT 
        a.uniqueid,
        at.name as typ_name,
        at.icon as typ_icon,
        a.cirs_status,
        a.cirs_manager,
        a.time_added
    FROM intra_antraege a
    JOIN intra_antrag_typen at ON a.antragstyp_id = at.id
    WHERE a.discordid = ?
    ORDER BY a.time_added DESC
";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['discordtag']]);
        $appresult = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($appresult)) {
            echo "<tr><td colspan='6'>
                <div class='empty-state'>
                    <div class='empty-state-icon'><i class='fa-solid fa-clipboard'></i></div>
                    <h6>Noch keine Anträge</h6>
                    <p>Reiche deinen ersten Antrag über den Button oben rechts ein.</p>
                </div>
            </td></tr>";
        } else {
            foreach ($appresult as $row) {
                $adddat = date("d.m.Y | H:i", strtotime($row['time_added']));
                $cirs_state = "Unbekannt";
                $badge_color = "ignis-chip--dark";

                switch ($row['cirs_status']) {
                    case 0:
                        $cirs_state = "In Bearbeitung";
                        $badge_color = "ignis-chip--info";
                        break;
                    case 1:
                        $cirs_state = "Abgelehnt";
                        $badge_color = "ignis-chip--danger";
                        break;
                    case 2:
                        $cirs_state = "Aufgeschoben";
                        $badge_color = "ignis-chip--warning";
                        break;
                    case 3:
                        $cirs_state = "Angenommen";
                        $badge_color = "ignis-chip--success";
                        break;
                }

                echo "<tr>
                    <td>
                        <i class='" . htmlspecialchars($row['typ_icon']) . " me-1'></i>
                        <span class='small'>" . htmlspecialchars($row['typ_name']) . "</span>
                    </td>
                    <td><span class='ignis-chip {$badge_color}'>" . $cirs_state . "</span></td>
                    <td><strong>{$row['uniqueid']}</strong></td>
                    <td>" . (!empty($row['cirs_manager']) ? htmlspecialchars($row['cirs_manager']) : '---') . "</td>
                    <td><span style='display:none'>{$row['time_added']}</span>{$adddat}</td>
                    <td>
                        <a class='ignis-btn ignis-btn--soft-primary ignis-btn--sm' href='" . BASE_PATH . "forms/view?antrag={$row['uniqueid']}'>
                            <i class='fa-regular fa-eye'></i> Ansehen
                        </a>
                    </td>
                </tr>";
            }
        }
        ?>
    </tbody>
</table>