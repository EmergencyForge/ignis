<table class="table table-striped" id="documentTable">
    <thead>
        <th scope="col">Status</th>
        <th scope="col">#</th>
        <th scope="col">Bearbeiter</th>
        <th scope="col">Datum</th>
        <th scope="col"></th>
    </thead>
    <tbody>
        <?php
        $stmtdivi = $pdo->prepare("
    SELECT 
        e.enr, 
        e.sendezeit, 
        e.protokoll_status, 
        e.bearbeiter, 
        e.freigegeben,
        e.freigeber_name,
        e.hidden_user
    FROM intra_edivi e
    JOIN intra_mitarbeiter m ON m.discordtag = :discordtag
    WHERE (
        e.pfname LIKE CONCAT('%', m.fullname, '%')
        OR e.fzg_transp_perso LIKE CONCAT('%', m.fullname, '%')
        OR e.fzg_transp_perso_2 LIKE CONCAT('%', m.fullname, '%')
        OR e.fzg_transp_perso_3 LIKE CONCAT('%', m.fullname, '%')
        OR e.fzg_na_perso LIKE CONCAT('%', m.fullname, '%')
        OR e.fzg_na_perso_2 LIKE CONCAT('%', m.fullname, '%')
        OR e.fzg_na_perso_3 LIKE CONCAT('%', m.fullname, '%')
    )
    AND e.hidden <> 1
    AND e.hidden_user <> 1
    ORDER BY e.sendezeit DESC
");
        $stmtdivi->execute(['discordtag' => $_SESSION['discordtag']]);
        $ediviRows = $stmtdivi->fetchAll(PDO::FETCH_ASSOC);

        if (empty($ediviRows)) {
            echo "<tr><td colspan='5'>
                <div class='empty-state'>
                    <div class='empty-state-icon'><i class='fa-solid fa-file-medical'></i></div>
                    <h6>Noch keine eNOTF-Protokolle</h6>
                    <p>Deine abgeschlossenen Einsatzprotokolle erscheinen hier automatisch.</p>
                </div>
            </td></tr>";
        } else {
            foreach ($ediviRows as $row) {
                $datetime = new DateTime($row['sendezeit']);
                $date = $datetime->format('d.m.Y | H:i');
                switch ($row['protokoll_status']) {
                    case 0:
                        $status = "<span class='ignis-chip ignis-chip--secondary'>Ungesehen</span>";
                        break;
                    case 1:
                        $status = "<span title='Prüfer: " . htmlspecialchars($row['bearbeiter']) . "' class='ignis-chip ignis-chip--warning'>in Prüfung</span>";
                        break;
                    case 2:
                        $status = "<span title='Prüfer: " . htmlspecialchars($row['bearbeiter']) . "' class='ignis-chip ignis-chip--success'>Geprüft</span>";
                        break;
                    case 4:
                        $status = "<span title='Prüfer: " . htmlspecialchars($row['bearbeiter']) . "' class='ignis-chip ignis-chip--dark'>Ausgeblendet</span>";
                        break;
                    default:
                        $status = "<span title='Prüfer: " . htmlspecialchars($row['bearbeiter']) . "' class='ignis-chip ignis-chip--danger'>Ungenügend</span>";
                        break;
                }

                switch ($row['freigegeben']) {
                    default:
                        $freigabe_status = "";
                        break;
                    case 1:
                        if ($row['hidden_user'] != 1) {
                            $freigabe_status = "<span title='Freigegeben von: " . htmlspecialchars($row['freigeber_name']) . "' class='ignis-chip ignis-chip--success'>F</span>";
                        } else {
                            $freigabe_status = "";
                        }
                        break;
                }

                echo "<tr>";
                echo "<td>" . $status . "</td>";
                echo "<td>" . htmlspecialchars($row['enr']) . " " . $freigabe_status . "</td>";
                echo "<td>" . (!empty($row['bearbeiter']) ? htmlspecialchars($row['bearbeiter']) : '---') . "</td>";
                echo "<td><span style='display:none'>" . $row['sendezeit'] . "</span>" . $date . "</td>";
                echo "<td><a href='" . \App\Helpers\EnotfUrl::protokoll($row['enr']) . "' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary'><i class='fa-regular fa-eye'></i> Ansehen</a></td>";
                echo "</tr>";
            }
        }
        ?>
    </tbody>
</table>