<table class="table table-striped" id="documentTable">
    <thead>
        <th scope="col">Dokumenten-Typ</th>
        <th scope="col">#</th>
        <th scope="col">Ersteller</th>
        <th scope="col">Am</th>
        <th scope="col"></th>
    </thead>
    <tbody>
        <?php
        $userQuery = "SELECT id FROM intra_mitarbeiter WHERE discordtag = :discordtag LIMIT 1";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute(['discordtag' => $_SESSION['discordtag']]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo "<tr><td colspan='5'>
                <div class='empty-state'>
                    <div class='empty-state-icon'><i class='fa-solid fa-user-plus'></i></div>
                    <h6>Kein Mitarbeiterprofil verknüpft</h6>
                    <p>Um Dokumente zu sehen, muss ein Mitarbeiterprofil mit deinem Konto verknüpft sein.</p>
                </div>
            </td></tr>";
        } else {
            $profileid = $userData['id'];

            $query = "
        SELECT
            pd.docid,
            pd.ausstellerid,
            pd.ausstellungsdatum,
            pd.type,
            pd.template_id,
            pd.aussteller_name,
            pd.pdf_path,
            u.discord_id AS user_id,
            COALESCE(m.fullname, u.fullname) as fullname,
            u.aktenid,
            t.name as template_name,
            t.category as template_category,
            dk.color as category_color,
            COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name
        FROM intra_mitarbeiter_dokumente pd
        LEFT JOIN intra_users u ON pd.ausstellerid = u.discord_id
        LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
        LEFT JOIN intra_dokument_templates t ON pd.template_id = t.id
        LEFT JOIN intra_dokument_kategorien dk ON t.category_id = dk.id
        WHERE pd.profileid = :profileid
        ORDER BY pd.ausstellungsdatum DESC
    ";

            $stmt = $pdo->prepare($query);
            $stmt->execute(['profileid' => $profileid]);
            $dokuresult = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Typ-Labels zentral aus DocumentTemplateManager

            if (empty($dokuresult)) {
                echo "<tr><td colspan='5'>
                <div class='empty-state'>
                    <div class='empty-state-icon'><i class='fa-solid fa-folder-open'></i></div>
                    <h6>Noch keine Dokumente</h6>
                    <p>Hier werden deine persönlichen Dokumente wie Urkunden und Zertifikate angezeigt.</p>
                </div>
            </td></tr>";
            } else {
                foreach ($dokuresult as $doks) {
                    $austdatum = date("d.m.Y", strtotime($doks['ausstellungsdatum']));

                    $docart = \App\Documents\DocumentTemplateManager::getDocumentTypeLabel(
                        (int) $doks['type'], $doks['template_name'] ?? null
                    );

                    $pdfPath = BASE_PATH . "storage/documents/" . $doks['docid'] . ".pdf";

                    if ($doks['type'] == 99 && !empty($doks['category_color'])) {
                        $bg = $doks['category_color'];
                    } elseif ($doks['type'] == 99) {
                        $bg = match ($doks['template_category']) {
                            'urkunde' => 'text-bg-secondary',
                            'zertifikat' => 'text-bg-dark',
                            'schreiben' => 'text-bg-warning',
                            default => 'text-bg-info'
                        };
                    } elseif ($doks['type'] <= 3) {
                        $bg = "text-bg-secondary";
                    } elseif ($doks['type'] == 5 || $doks['type'] == 6 || $doks['type'] == 7) {
                        $bg = "text-bg-dark";
                    } elseif ($doks['type'] >= 10 && $doks['type'] <= 13) {
                        $bg = "text-bg-danger";
                    } else {
                        $bg = "text-bg-secondary";
                    }

                    echo "<tr>";
                    echo "<td><span class='ignis-chip $bg'>" . htmlspecialchars($docart) . "</span></td>";
                    echo "<td>" . htmlspecialchars($doks['docid']) .  "</td>";
                    echo "<td>" . htmlspecialchars($doks['ersteller_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($austdatum) . "</td>";
                    echo "<td>";
                    echo "<a href='$pdfPath' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary' target='_blank'><i class='fa-regular fa-eye'></i> Ansehen</a>";
                    echo "</td>";
                    echo "</tr>";
                }
            }
        }
        ?>
    </tbody>
</table>