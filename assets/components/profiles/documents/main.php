<?php

use App\Auth\Permissions;
?>

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
        dk.name as category_name,
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
        $stmt->execute(['profileid' => $openedID]);
        $dokuresult = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $arten = [
            0 => "Ernennungsurkunde",
            1 => "Beförderungsurkunde",
            2 => "Entlassungsurkunde",
            3 => "Ausbildungsvertrag",
            5 => "Ausbildungszertifikat",
            6 => "Lehrgangszertifikat",
            7 => "Lehrgangszertifikat (Fachdienste)",
            10 => "Schriftliche Abmahnung",
            11 => "Vorläufige Dienstenthebung",
            12 => "Dienstentfernung",
            13 => "Außerordentliche Kündigung",
            99 => "Eigenes Dokument"
        ];

        foreach ($dokuresult as $doks) {
            $austdatum = date("d.m.Y", strtotime($doks['ausstellungsdatum']));

            // Dokumenttyp bestimmen
            if ($doks['type'] == 99 && !empty($doks['template_name'])) {
                $docart = $doks['template_name'];
            } else {
                $docart = isset($arten[$doks['type']]) ? $arten[$doks['type']] : 'Unbekannt';
            }

            // Direkter PDF-Pfad
            $pdfPath = BASE_PATH . "storage/documents/" . $doks['docid'] . ".pdf";

            // Badge-Farbe bestimmen
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
            echo "<td><span class='badge $bg'>" . htmlspecialchars($docart) . "</span></td>";
            echo "<td>" . htmlspecialchars($doks['docid']) .  "</td>";
            echo "<td>" . htmlspecialchars($doks['ersteller_name']) . "</td>";
            echo "<td>" . htmlspecialchars($austdatum) . "</td>";
            echo "<td>";
            echo "<a href='$pdfPath' class='btn btn-sm btn-soft-primary' target='_blank'><i class='fa-solid fa-eye'></i> Ansehen</a> ";
            // echo "<a href='$pdfPath' download class='btn btn-sm btn-success'><i class='las la-download'></i></a>";

            if (Permissions::check('admin')) {
                echo " <a href='" . BASE_PATH . "mitarbeiter/dokument-delete.php?id={$doks['docid']}&pid=$openedID' class='btn btn-sm btn-outline-danger btn-icon'><i class='fa-solid fa-trash'></i></a>";
            }

            echo "</td>";
            echo "</tr>";
        }
        ?>

    </tbody>
</table>