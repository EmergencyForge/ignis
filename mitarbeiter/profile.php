<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    header("Location: " . BASE_PATH . "login.php");
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Notifications\NotificationManager;
use App\Personnel\PersonalLogManager;

$userHelper = new UserHelper($pdo);

if (!Permissions::check(['admin', 'personnel.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    Flash::set('error', 'invalid-id');
    header("Location: " . BASE_PATH . "index.php");
}

//Abfrage der Nutzer ID vom Login
$userid = $_SESSION['userid'];

$stmt = $pdo->prepare("SELECT * FROM intra_mitarbeiter WHERE id = :id");
$stmt->execute(['id' => $_GET['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$edituseric = 'Unbekannt Unbekannt';
$editdg = null;

if ($_SESSION['discordtag'] != null) {
    $statement = $pdo->prepare("SELECT * FROM intra_mitarbeiter WHERE discordtag = :id");
    $statement->execute(array('id' => $_SESSION['discordtag']));
    $profile = $statement->fetch();

    if ($profile) {
        $edituseric = $profile['fullname'];
        $editdg = $profile['dienstgrad'];
    }
}

$openedID = $_GET['id'];
$edituser = $userHelper->getCurrentUserFullnameForAction();

// Initialize PersonalLogManager
$logManager = new PersonalLogManager($pdo);

$stmtg = $pdo->prepare("SELECT * FROM intra_mitarbeiter_dienstgrade WHERE id = :id");
$stmtg->execute(['id' => $row['dienstgrad']]);
$dginfo = $stmtg->fetch();

$stmtr = $pdo->prepare("SELECT * FROM intra_mitarbeiter_rdquali WHERE id = :id");
$stmtr->execute(['id' => $row['qualird']]);
$rdginfo = $stmtr->fetch();

$stmtf = $pdo->prepare("SELECT * FROM intra_mitarbeiter_fwquali WHERE id = :id");
$stmtf->execute(['id' => $row['qualifw2']]);
$fwginfo = $stmtf->fetch();

$bfqualtext = $fwginfo['shortname'];

if ($row['geschlecht'] == 0) {
    $dienstgradText = $dginfo['name_m'];
    $rdqualtext = $rdginfo['name_m'];
} elseif ($row['geschlecht'] ==  1) {
    $dienstgradText = $dginfo['name_w'];
    $rdqualtext = $rdginfo['name_w'];
} else {
    $dienstgradText = $dginfo['name'];
    $rdqualtext = $rdginfo['name'];
}

if (isset($_POST['new'])) {
    if ($_POST['new'] == 1) {
        // Get and sanitize input values
        $id = $_POST['id'];
        $fullname = $_POST['fullname'];
        $gebdatum = $_POST['gebdatum'];
        $dienstgrad = $_POST['dienstgrad'];
        $discordtag = $_POST['discordtag'];
        $telefonnr = $_POST['telefonnr'];
        $dienstnr = trim($_POST['dienstnr']);
        $qualird = $_POST['qualird'];

        // Validate dienstnr format: allow letters, numbers, and hyphens, but require at least one number
        if (!empty($dienstnr) && !preg_match('/^(?=.*[0-9])[A-Za-z0-9\-]+$/', $dienstnr)) {
            $_SESSION['message'] = 'Ungültiges Format für Dienstnummer. Muss mindestens eine Zahl enthalten (z.B. RD-001, BF01).';
            $_SESSION['message_type'] = 'danger';
            header("Location: " . BASE_PATH . "mitarbeiter/profile.php?id=" . $id);
            exit;
        }
        $qualifw2 = $_POST['qualifw2'];
        $geschlecht = $_POST['geschlecht'];
        $zusatzqual = $_POST['zusatzqual'];
        $pfp = trim($_POST['pfp'] ?? '');

        $charakterid = CHAR_ID ? ($_POST['charakterid'] ?? '') : '';

        $stmt = $pdo->prepare("SELECT * FROM intra_mitarbeiter WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Kein Datensatz gefunden.");
        }

        $currentDienstgrad = $data['dienstgrad'];
        $currentFullname = $data['fullname'];
        $currentGebdatum = $data['gebdatum'];
        $currentCharakterid = $data['charakterid'] ?? '';
        $currentDiscordtag = $data['discordtag'];
        $currentTelefonnr = $data['telefonnr'];
        $currentDienstnr = $data['dienstnr'];
        $currentQualird = $data['qualird'];
        $currentQualifw = $data['qualifw2'];
        $currentGeschlecht = $data['geschlecht'];
        $currentZusatzqual = $data['zusatz'];
        $currentPfp = $data['pfp'];

        $rdMapping = array(
            0 => "Keine",
            1 => "Rettungssanitäter/-in i. A.",
            2 => "Rettungssanitäter/-in",
            3 => "Notfallsanitäter/-in",
            4 => "Notarzt/ärztin",
            5 => "Ärztliche/-r Leiter/-in RD"
        );

        $bfqualiMapping = array(
            0 => "Keine",
            1 => "B1 - Grundausbildung",
            2 => "B2 - Maschinist/-in",
            3 => "B3 - Gruppenführer/-in",
            4 => "B4 - Zugführer/-in",
            5 => "B5 - B-Dienst",
            6 => "B6 - A-Dienst"
        );

        if ($currentDienstgrad != $dienstgrad) {
            $stmt = $pdo->prepare("UPDATE intra_mitarbeiter SET dienstgrad = :dienstgrad WHERE id = :id");
            $stmt->execute([
                'dienstgrad' => $dienstgrad,
                'id' => $id
            ]);

            $stmtcdg = $pdo->prepare("SELECT id,name FROM intra_mitarbeiter_dienstgrade WHERE id = :id");
            $stmtcdg->execute(['id' => $currentDienstgrad]);
            $cdginfo = $stmtcdg->fetch();

            $stmtndg = $pdo->prepare("SELECT id,name FROM intra_mitarbeiter_dienstgrade WHERE id = :id");
            $stmtndg->execute(['id' => $dienstgrad]);
            $ndginfo = $stmtndg->fetch();

            // Use PersonalLogManager for rank change
            $logManager->logRankChange($id, $cdginfo['name'], $ndginfo['name'], $edituser);
        }

        if ($currentQualird != $qualird) {
            $stmt = $pdo->prepare("UPDATE intra_mitarbeiter SET qualird = :qualird WHERE id = :id");
            $stmt->execute([
                'qualird' => $qualird,
                'id' => $id
            ]);

            $stmtcrg = $pdo->prepare("SELECT id,name FROM intra_mitarbeiter_rdquali WHERE id = :id");
            $stmtcrg->execute(['id' => $currentQualird]);
            $crginfo = $stmtcrg->fetch();

            $stmtnrg = $pdo->prepare("SELECT id,name FROM intra_mitarbeiter_rdquali WHERE id = :id");
            $stmtnrg->execute(['id' => $qualird]);
            $nrginfo = $stmtnrg->fetch();

            // Use PersonalLogManager for RD qualification change
            $logManager->logQualificationChange($id, 'RD', $crginfo['name'], $nrginfo['name'], $edituser);
        }

        if ($currentQualifw != $qualifw2) {
            $stmt = $pdo->prepare("UPDATE intra_mitarbeiter SET qualifw2 = :qualifw2 WHERE id = :id");
            $stmt->execute([
                'qualifw2' => $qualifw2,
                'id' => $id
            ]);

            $stmtcfg = $pdo->prepare("SELECT id,name FROM intra_mitarbeiter_fwquali WHERE id = :id");
            $stmtcfg->execute(['id' => $currentQualifw]);
            $cfginfo = $stmtcfg->fetch();

            $stmtnfg = $pdo->prepare("SELECT id,name FROM intra_mitarbeiter_fwquali WHERE id = :id");
            $stmtnfg->execute(['id' => $qualifw2]);
            $nfginfo = $stmtnfg->fetch();

            // Use PersonalLogManager for FW qualification change
            $logManager->logQualificationChange($id, 'FW', $cfginfo['name'], $nfginfo['name'], $edituser);
        }

        $dataChanged = (
            $currentFullname != $fullname ||
            $currentGebdatum != $gebdatum ||
            $currentDiscordtag != $discordtag ||
            $currentTelefonnr != $telefonnr ||
            $currentDienstnr != $dienstnr ||
            $currentGeschlecht != $geschlecht ||
            $currentZusatzqual != $zusatzqual ||
            $currentPfp != $pfp ||
            (CHAR_ID && $currentCharakterid != $charakterid)
        );

        if ($dataChanged) {
            if (empty($pfp)) {
                $pfp = '/assets/img/empty_user.png';
            }

            $setClauses = ['fullname = :fullname', 'gebdatum = :gebdatum', 'discordtag = :discordtag',
                'telefonnr = :telefonnr', 'dienstnr = :dienstnr', 'geschlecht = :geschlecht',
                'zusatz = :zusatzqual', 'pfp = :pfp'];
            $params = [
                'fullname' => $fullname, 'gebdatum' => $gebdatum, 'discordtag' => $discordtag,
                'telefonnr' => $telefonnr, 'dienstnr' => $dienstnr, 'geschlecht' => $geschlecht,
                'zusatzqual' => $zusatzqual, 'pfp' => $pfp, 'id' => $id
            ];
            if (CHAR_ID) {
                $setClauses[] = 'charakterid = :charakterid';
                $params['charakterid'] = $charakterid;
            }

            $setStr = implode(', ', $setClauses);
            $stmt = $pdo->prepare("UPDATE intra_mitarbeiter SET {$setStr} WHERE id = :id");
            $stmt->execute($params);

            // Use PersonalLogManager for profile modification
            $logManager->logProfileModification($id, $edituser);
        }

        $currentURL = $_SERVER['REQUEST_URI'];
        $parsedURL = parse_url($currentURL);
        parse_str($parsedURL['query'], $queryParams);
        unset($queryParams['edit']);
        $newQuery = http_build_query($queryParams);
        $modifiedURL = $parsedURL['path'] . ($newQuery ? '?' . $newQuery : '');

        header("Location: $modifiedURL");
        exit();
    } elseif ($_POST['new'] == 4) {
        $qualifikationen_fd = isset($_POST['fachdienste']) && is_array($_POST['fachdienste']) ? $_POST['fachdienste'] : [];
        $qualifd = json_encode($qualifikationen_fd);

        $stmt = $pdo->prepare("SELECT fachdienste FROM intra_mitarbeiter WHERE id = :id");
        $stmt->execute(['id' => $openedID]);
        $currentQualifd = $stmt->fetchColumn();

        if ($qualifd !== $currentQualifd) {
            $updateStmt = $pdo->prepare("UPDATE intra_mitarbeiter SET fachdienste = :fachdienste WHERE id = :id");
            $updateStmt->execute([
                'fachdienste' => $qualifd,
                'id' => $openedID
            ]);

            // Use PersonalLogManager for department modification
            $logManager->logDepartmentModification($openedID, $edituser);
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['new'] == 5) {
        $logContent = $_POST['content'];
        $logType = $_POST['noteType'];
        // Use PersonalLogManager for manual notes
        $logManager->addNote($openedID, $logType, $logContent, $edituser);

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['new'] == 6) {
        $erhalter = $_POST['erhalter'];
        $inhalt = $_POST['inhalt'] ?? NULL;
        $suspendtime = !empty($_POST['suspendtime']) ? $_POST['suspendtime'] : NULL;
        $erhalter_gebdat = $_POST['erhalter_gebdat'];
        $erhalter_rang = $_POST['erhalter_rang'] ?? NULL;
        $erhalter_rang_rd = $_POST['erhalter_rang_rd'] ?? NULL;
        $erhalter_quali = $_POST['erhalter_quali'] ?? NULL;
        $ausstellerid = $_POST['ausstellerid'];
        $aussteller_name = $_POST['aussteller_name'];
        $aussteller_rang = $_POST['aussteller_rang'];
        $profileid = $_POST['profileid'];
        $docType = $_POST['docType'];
        $anrede = $_POST['anrede'];
        $discordtag = $row['discordtag'];

        $ausstDtNr = in_array($docType, ['10', '11', '12', '13']) ? '10' : $docType;

        $ausstellungsdatum = date('Y-m-d', strtotime($_POST['ausstellungsdatum_' . $ausstDtNr] ?? $_POST['ausstellungsdatum_0']));

        do {
            $random_number = mt_rand(1000000, 9999999);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_mitarbeiter_dokumente WHERE docid = :docid");
            $stmt->execute(['docid' => $random_number]);
            $exists = $stmt->fetchColumn();
        } while ($exists > 0);

        $new_number = $random_number;

        $docStmt = $pdo->prepare("INSERT INTO intra_mitarbeiter_dokumente 
        (docid, type, anrede, erhalter, inhalt, suspendtime, erhalter_gebdat, erhalter_rang, erhalter_rang_rd, erhalter_quali, ausstellungsdatum, ausstellerid, profileid, aussteller_name, aussteller_rang, discordid) 
        VALUES (:docid, :type, :anrede, :erhalter, :inhalt, :suspendtime, :erhalter_gebdat, :erhalter_rang, :erhalter_rang_rd, :erhalter_quali, :ausstellungsdatum, :ausstellerid, :profileid, :aussteller_name, :aussteller_rang, :discordtag)");

        $docStmt->execute([
            'docid' => $new_number,
            'type' => $docType,
            'anrede' => $anrede,
            'erhalter' => $erhalter,
            'inhalt' => $inhalt,
            'suspendtime' => $suspendtime,
            'erhalter_gebdat' => $erhalter_gebdat,
            'erhalter_rang' => $erhalter_rang,
            'erhalter_rang_rd' => $erhalter_rang_rd,
            'erhalter_quali' => $erhalter_quali,
            'ausstellungsdatum' => $ausstellungsdatum,
            'ausstellerid' => $ausstellerid,
            'profileid' => $profileid,
            'aussteller_name' => $aussteller_name,
            'aussteller_rang' => $aussteller_rang,
            'discordtag' => $discordtag
        ]);

        // Use PersonalLogManager for document creation
        $logManager->logDocumentCreation($profileid, $new_number, $edituser);

        // Create notification for employee if they have a user account
        if (!empty($discordtag)) {
            $notificationManager = new NotificationManager($pdo);
            $recipientUserId = $notificationManager->getUserIdByDiscordTag($discordtag);

            if ($recipientUserId) {
                $docTypeNames = [
                    1 => 'Beförderungsurkunde',
                    2 => 'Ernennungsurkunde',
                    3 => 'Entlassungsurkunde',
                    4 => 'Zertifikat',
                    5 => 'Fachlehrgangszertifikat',
                    6 => 'Ausbildungszertifikat',
                    7 => 'Abmahnung',
                    8 => 'Kündigung',
                    9 => 'Dienstenthebung',
                    10 => 'Dienstentfernung'
                ];
                $docTypeName = $docTypeNames[$docType] ?? 'Dokument';

                $notificationManager->create(
                    $recipientUserId,
                    'dokument',
                    "Neues Dokument erstellt",
                    "Ein neues Dokument ({$docTypeName} #{$new_number}) wurde für Sie erstellt.",
                    BASE_PATH . "assets/functions/docredir.php?docid={$new_number}"
                );
            }
        }

        header('Location: ' . BASE_PATH . 'assets/functions/docredir.php?docid=' . $new_number, true, 302);
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = $row['fullname'] . " &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;
    include __DIR__ . "/../assets/components/_base/mitarbeiter/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="mitarbeiter">
    <?php include "../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <h1 class="mb-3">Mitarbeiterprofil</h1>
                    <?php
                    require __DIR__ . '/../assets/config/database.php';
                    $accountStatus = 'none'; // none, pending, active
                    $panelakte = null;
                    $pendingInvite = null;

                    if (isset($row['discordtag']) && !empty($row['discordtag'])) {
                        // Check for linked user account
                        $stmt = $pdo->prepare("SELECT u.id, u.username, COALESCE(m.fullname, u.fullname) as fullname, u.aktenid, u.is_active FROM intra_users u LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag WHERE u.discord_id = :discordtag");
                        $stmt->execute([':discordtag' => $row['discordtag']]);
                        $panelakte = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($panelakte) {
                            $accountStatus = $panelakte['is_active'] ? 'active' : 'inactive';
                        } else {
                            // Check for pending registration code (labeled with employee name)
                            $pendingStmt = $pdo->prepare("SELECT id, code, label, expires_at FROM intra_registration_codes WHERE is_used = 0 AND label LIKE :label AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC LIMIT 1");
                            $pendingStmt->execute([':label' => '%' . $row['fullname'] . '%']);
                            $pendingInvite = $pendingStmt->fetch(PDO::FETCH_ASSOC);
                            $accountStatus = $pendingInvite ? 'pending' : 'none';
                        }
                    }
                    ?>
                    <div class="d-flex align-items-center gap-2 mb-3 px-3 py-2 rounded-2" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                        <span class="fw-semibold" style="font-size: var(--font-size-sm);">Konto-Status:</span>
                        <?php if ($accountStatus === 'active'): ?>
                            <span class="badge text-bg-success"><i class="fa-solid fa-circle-check me-1"></i>Konto aktiv</span>
                            <?php if ($panelakte && Permissions::check(['admin', 'users.view'])): ?>
                                <a href="<?= BASE_PATH ?>benutzer/edit.php?id=<?= $panelakte['id'] ?>" class="text-decoration-none" style="font-size: var(--font-size-sm);">
                                    <?= htmlspecialchars($panelakte['fullname']) ?> (<?= htmlspecialchars($panelakte['username']) ?>)
                                </a>
                            <?php elseif ($panelakte): ?>
                                <span style="font-size: var(--font-size-sm);"><?= htmlspecialchars($panelakte['fullname']) ?> (<?= htmlspecialchars($panelakte['username']) ?>)</span>
                            <?php endif; ?>
                        <?php elseif ($accountStatus === 'inactive'): ?>
                            <span class="badge text-bg-secondary"><i class="fa-solid fa-circle-minus me-1"></i>Konto deaktiviert</span>
                            <?php if ($panelakte && Permissions::check(['admin', 'users.view'])): ?>
                                <a href="<?= BASE_PATH ?>benutzer/edit.php?id=<?= $panelakte['id'] ?>" class="text-decoration-none" style="font-size: var(--font-size-sm);">
                                    <?= htmlspecialchars($panelakte['username']) ?>
                                </a>
                            <?php endif; ?>
                        <?php elseif ($accountStatus === 'pending'): ?>
                            <span class="badge text-bg-warning"><i class="fa-solid fa-clock me-1"></i>Einladung ausstehend</span>
                            <?php if ($pendingInvite && $pendingInvite['expires_at']): ?>
                                <span style="font-size: var(--font-size-xs); opacity: 0.7;">Läuft ab: <?= (new DateTime($pendingInvite['expires_at']))->format('d.m.Y H:i') ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge text-bg-dark" style="opacity: 0.6;"><i class="fa-solid fa-circle-xmark me-1"></i>Kein Konto</span>
                            <?php if (Permissions::check(['admin', 'users.create'])): ?>
                                <button type="button" class="btn btn-soft-primary btn-sm" id="generateInviteBtn" style="font-size: var(--font-size-xs);" data-fullname="<?= htmlspecialchars($row['fullname']) ?>">
                                    <i class="fa-solid fa-paper-plane me-1"></i>Einladen
                                </button>
                                <span id="inviteResult" style="font-size: var(--font-size-xs);"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($_GET['new_created']) && $accountStatus === 'none' && Permissions::check(['admin', 'users.create'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" id="newCreatedBanner">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <strong>Mitarbeiter erfolgreich erstellt.</strong> Soll direkt ein Einladungslink für das Intranet generiert werden?
                            <button type="button" class="btn btn-sm btn-success ms-2" id="bannerInviteBtn" data-fullname="<?= htmlspecialchars($row['fullname']) ?>">
                                <i class="fa-solid fa-paper-plane me-1"></i>Einladungslink erstellen
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                        </div>
                    <?php endif; ?>
                    <?php
                    include __DIR__ . '/../assets/components/profiles/checks.php' ?>
                    <div class="row">
                        <div class="col-5 p-3 shadow-sm border ma-basedata">
                            <form id="profil" method="post">
                                <div class="row">
                                    <div class="col">
                                        <?php if (!isset($_GET['edit'])) { ?>
                                            <div class="btn btn-soft-primary btn-sm btn-icon" data-bs-toggle="modal" data-bs-target="#modalNewComment" title="Notiz anlegen"><i class="fa-solid fa-sticky-note"></i></div>
                                        <?php } ?>
                                        <?php if (!isset($_GET['edit']) && Permissions::check(['admin', 'personnel.documents.manage'])) { ?>
                                            <div class="btn btn-soft-primary btn-sm btn-icon" data-bs-toggle="modal" data-bs-target="#modalDokuCreate" title="Dokument erstellen"><i class="fa-solid fa-print"></i></div>
                                        <?php } ?>
                                        <?php if (!isset($_GET['edit']) && Permissions::check(['admin', 'personnel.edit'])) { ?>
                                            <a href="?id=<?= $_GET['id'] . (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') ?>&edit" class="btn btn-soft-primary btn-sm btn-icon" id="personal-edit" title="Profil bearbeiten"><i class="fa-solid fa-edit"></i></a>
                                            <div class="btn btn-soft-primary btn-sm btn-icon" data-bs-toggle="modal" data-bs-target="#modalFDQuali" title="Fachdienste bearbeiten"><i class="fa-solid fa-graduation-cap"></i></div>
                                        <?php } elseif (isset($_GET['edit']) && Permissions::check(['admin', 'personnel.edit'])) { ?>
                                            <a href="#" class="btn btn-soft-success btn-sm btn-icon" id="personal-save" onclick="document.getElementById('profil').submit()"><i class="fa-solid fa-save"></i></a>
                                            <a href="<?php echo removeEditParamFromURL(); ?>" class="btn btn-ghost btn-sm btn-icon"><i class="fa-solid fa-arrow-left"></i></a>
                                            <?php if (Permissions::check(['admin', 'personnel.delete'])) { ?>
                                                <div class="btn btn-outline-danger btn-sm btn-icon" id="personal-delete" data-bs-toggle="modal" data-bs-target="#modalPersoDelete"><i class="fa-solid fa-trash"></i></div>
                                        <?php }
                                        } ?>
                                    </div>
                                    <div class="col text-end" style="color:var(--tag-color)">Akten-ID: <?= $row['id'] ?></div>
                                </div>
                                <?php
                                // Function to remove the 'edit' parameter from the URL
                                function removeEditParamFromURL(): string
                                {
                                    $currentURL = $_SERVER['REQUEST_URI'];
                                    $parsedURL = parse_url($currentURL);
                                    parse_str($parsedURL['query'], $queryParams);
                                    unset($queryParams['edit']);
                                    $newQuery = http_build_query($queryParams);
                                    $modifiedURL = $parsedURL['path'] . '?' . $newQuery;
                                    return $modifiedURL;
                                }
                                ?>
                                <div class="w-100 text-center">
                                    <?php if (!isset($_GET['edit']) || !Permissions::check(['admin', 'personnel.edit'])) { ?>
                                        <?php
                                        // Profilbild anzeigen
                                        $profileImage = !empty($row['pfp']) ? $row['pfp'] : BASE_PATH . 'assets/img/empty_user.png';
                                        ?>
                                        <img src="<?= $profileImage ?>" alt="Profilbild" class="border" style="width: 120px; height: 120px; object-fit: cover;">

                                        <p class="mt-3">
                                            <?php if ($row['geschlecht'] == 0) {
                                                $geschlechtText = "Herr";
                                            } elseif ($row['geschlecht'] == 1) {
                                                $geschlechtText = "Frau";
                                            } else {
                                                $geschlechtText = "Divers";
                                            }
                                            $profileName = $geschlechtText . " " . $row['fullname'];
                                            ?>
                                        <h4 class="mt-0"><?= $profileName ?></h4>
                                        <?php
                                        if ($dginfo['badge']) {
                                        ?>
                                            <img src="<?= $dginfo['badge'] ?>" height='16px' width='auto' alt='Dienstgrad' />
                                        <?php } ?>
                                        <?= $dienstgradText ?><br>
                                        <?php if (!$rdginfo['none']) { ?>
                                            <span style="text-transform:none; color:var(--black)" class="badge text-bg-warning"><?= $rdqualtext ?></span>
                                        <?php }
                                        if (!$fwginfo['none']) { ?>
                                            <span style="text-transform:none" class="badge text-bg-danger"><?= $bfqualtext ?></span>
                                        <?php } ?>
                                        </p>
                                    <?php } else {
                                        // Bearbeitungsmodus: Profilbild-Input
                                        $currentPfp = !empty($row['pfp']) ? $row['pfp'] : '';
                                    ?>
                                        <div class="mb-3">
                                            <label for="pfp" class="form-label">Profilbild-URL</label>
                                            <input type="text" class="form-control" name="pfp" id="pfp"
                                                value="<?= htmlspecialchars($currentPfp) ?>"
                                                placeholder="/assets/img/empty_user.png">
                                            <small class="form-text text-muted">Gib eine URL oder einen relativen Pfad an. Leer = Standardbild</small>
                                        </div>
                                    <?php
                                        include __DIR__ . '/../assets/components/profiles/dienstgradselector_bf.php';
                                        include __DIR__ . '/../assets/components/profiles/dienstgradselector_rd.php';
                                        include __DIR__ . '/../assets/components/profiles/qualiselector.php';
                                    } ?>
                                    <hr class="my-3">
                                    <?php if (!isset($_GET['edit']) || !Permissions::check(['admin', 'personnel.edit'])) { ?>
                                        <table class="mx-auto w-100">
                                            <tbody class="text-start">
                                                <tr>
                                                    <td class="fw-bold">Geburtsdatum</td>
                                                    <td><?= $geburtstag ?></td>
                                                </tr>
                                                <?php if (CHAR_ID) : ?>
                                                    <tr>
                                                        <td class="fw-bold">Charakter-ID</td>
                                                        <td><?= $row['charakterid'] ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="fw-bold">Discord-ID</td>
                                                    <td><?= $row['discordtag'] ?? 'N. hinterlegt' ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Telefonnummer</td>
                                                    <td><?= $row['telefonnr'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Dienstnummer</td>
                                                    <td><?= $row['dienstnr'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Position</td>
                                                    <td><?= $row['zusatz'] ?? 'Keine' ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Einstellungsdatum</td>
                                                    <td><?= $einstellungsdatum ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <hr class="my-3">
                                        <div id="fd-container">
                                            <?php include __DIR__ . "/../assets/components/profiles/anzeige_fachdienste.php" ?>
                                        </div>
                                    <?php } elseif (isset($_GET['edit']) && Permissions::check(['admin', 'personnel.edit'])) { ?>
                                        <input type="hidden" name="id" id="id" value="<?= $_GET['id'] ?>" />
                                        <input type="hidden" name="new" value="1" />
                                        <table class="mx-auto w-100">
                                            <tbody class="text-start">
                                                <tr>
                                                    <td class="fw-bold">Vor- und Zuname</td>
                                                    <td class="col-8"><input class="form-control" type="text" name="fullname" id="fullname" value="<?= $row['fullname'] ?>"></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Geburtsdatum</td>
                                                    <td><input class="form-control" type="date" name="gebdatum" id="gebdatum" value="<?= $row['gebdatum'] ?>"></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Geschlecht</td>
                                                    <td>
                                                        <select name="geschlecht" id="geschlecht" class="form-select">
                                                            <option value="0" <?php if ($row['geschlecht'] == 0) echo 'selected' ?>>Männlich</option>
                                                            <option value="1" <?php if ($row['geschlecht'] == 1) echo 'selected' ?>>Weiblich</option>
                                                            <option value="2" <?php if ($row['geschlecht'] == 2) echo 'selected' ?>>Divers</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <?php if (CHAR_ID) : ?>
                                                    <tr>
                                                        <td class="fw-bold">Charakter-ID</td>
                                                        <td><input class="form-control" type="text" name="charakterid" id="charakterid" value="<?= $row['charakterid'] ?>"></td>
                                                    </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="fw-bold">Discord-ID</td>
                                                    <td><input class="form-control" type="text" inputmode="numeric" name="discordtag" id="discordtag" value="<?= $row['discordtag'] ?>" pattern="[0-9]{17,18}" maxlength="18" required>
                                                    <small class="form-text text-muted">17-18 stellige Discord-ID</small></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Telefonnummer</td>
                                                    <td><input class="form-control" type="text" name="telefonnr" id="telefonnr" value="<?= $row['telefonnr'] ?>"></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Dienstnummer</td>
                                                    <td class="dienstnr-container">
                                                        <input class="form-control" type="text" name="dienstnr" id="dienstnr" value="<?= $row['dienstnr'] ?>" pattern="^(?=.*[0-9])[A-Za-z0-9\-]+$" title="Muss mindestens eine Zahl enthalten. Buchstaben, Zahlen und Bindestriche erlaubt (z.B. RD-001, BF01)">
                                                        <div id="dienstnr-status" class="dienstnr-status"></div>
                                                        <div id="dienstnr-feedback" class="text-danger small" style="display: none;"></div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Position</td>
                                                    <td><input class="form-control" type="text" name="zusatzqual" id="zusatzqual" maxlength="255" value="<?= $row['zusatz'] ?>"></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-bold">Einstellungsdatum</td>
                                                    <td><input class="form-control" type="date" name="einstdatum" id="einstdatum" value="<?= $row['einstdatum'] ?>" readonly disabled></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    <?php } ?>
                                </div>
                            </form>
                        </div>
                        <div class="col ms-4">
                            <div class="p-3 shadow-sm border ma-comments mb-3">
                                <div class="comment-settings mb-3">
                                    <h4>Kommentare/Notizen</h4>
                                </div>
                                <div class="comment-container">
                                    <?php include __DIR__ . '/../assets/components/profiles/comments/main.php' ?>
                                </div>
                            </div>
                            <div class="p-3 shadow-sm border ma-logs">
                                <details<?php echo isset($_GET['logpage']) ? ' open' : ''; ?>>
                                    <summary class="mb-3" style="cursor: pointer;">
                                        <h5 class="d-inline">Systemprotokoll</h5>
                                    </summary>
                                    <div class="log-container">
                                        <?php include __DIR__ . '/../assets/components/profiles/logs/main.php' ?>
                                    </div>
                                    </details>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3 mb-4">
                        <div class="col p-3 shadow-sm border ma-documents">
                            <h4>Dokumente</h4>
                            <?php include __DIR__ . '/../assets/components/profiles/documents/main.php' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../assets/components/profiles/modals.php' ?>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
    <?php if (isset($_GET['edit']) && Permissions::check(['admin', 'personnel.edit'])): ?>
    <script src="<?= BASE_PATH ?>assets/js/dienstnr-check.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initDienstnrCheck({ basePath: '<?= BASE_PATH ?>', excludeId: <?= (int)$_GET['id'] ?> });
    });
    </script>
    <?php endif; ?>
    <script>
    (function() {
        function handleInviteClick(btn) {
            var fullname = btn.dataset.fullname;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Wird erstellt...';

            fetch('<?= BASE_PATH ?>api/personnel/generate-invite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ label: fullname })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.outerHTML = '<span style="font-size: var(--font-size-sm);"><i class="fa-solid fa-check text-success me-1"></i>' +
                        '<code class="user-select-all">' + data.inviteUrl + '</code></span>';
                    var resultEl = document.getElementById('inviteResult');
                    if (resultEl) resultEl.innerHTML = '';
                    var banner = document.getElementById('newCreatedBanner');
                    if (banner) {
                        banner.className = 'alert alert-success mb-3';
                        banner.innerHTML = '<i class="fa-solid fa-check me-2"></i><strong>Einladungslink erstellt!</strong> ' +
                            '<code class="user-select-all">' + data.inviteUrl + '</code>';
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Einladen';
                    if (typeof showToast === 'function') showToast(data.message || 'Fehler beim Erstellen', 'danger');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Einladen';
                if (typeof showToast === 'function') showToast('Fehler beim Erstellen des Einladungslinks', 'danger');
            });
        }

        var inviteBtn = document.getElementById('generateInviteBtn');
        if (inviteBtn) inviteBtn.addEventListener('click', function() { handleInviteClick(this); });

        var bannerBtn = document.getElementById('bannerInviteBtn');
        if (bannerBtn) bannerBtn.addEventListener('click', function() { handleInviteClick(this); });
    })();
    </script>
</body>

</html>