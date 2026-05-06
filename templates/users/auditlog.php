<?php
/**
 * View: Globaler Audit-Log
 *
 * @var \Illuminate\Support\Collection                                        $entries     stdClass-Rows aus Capsule
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>       $usersById
 * @var \PDO                                                                  $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>users/list">Benutzer</a></span>
                        <span class="ignis-breadcrumb__item is-active">Audit Log</span>
                    </nav>
                    <div class="flex justify-between items-center mb-5">
                        <h1 class="mb-0">Audit Log</h1>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-audit">
                            <thead>
                                <tr>
                                    <th scope="col">Zeitstempel</th>
                                    <th scope="col">Modul</th>
                                    <th scope="col">Aktion</th>
                                    <th scope="col">Details</th>
                                    <th scope="col">Benutzer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry):
                                    $userId   = (int) ($entry->user ?? 0);
                                    $username = $usersById->get($userId)?->username ?? 'Unbekannt';
                                    $datetime = new DateTime($entry->timestamp);
                                    $date     = $datetime->format('d.m.Y  H:i:s');
                                ?>
                                    <tr>
                                        <td><span style="display:none"><?= htmlspecialchars($entry->timestamp) ?></span><?= htmlspecialchars($date) ?></td>
                                        <td class="font-bold"><?= htmlspecialchars($entry->module ?? '') ?></td>
                                        <td style="overflow-wrap:anywhere"><?= htmlspecialchars($entry->action ?? '') ?></td>
                                        <td style="overflow-wrap:anywhere"><?= htmlspecialchars($entry->details ?? '') ?></td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>users/edit?id=<?= $userId ?>"
                                               data-user-card="<?= $userId ?>">
                                                <?= htmlspecialchars($username) ?> (ID: <?= $userId ?>)
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#table-audit').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [25, 50, 100],
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Einträge')
            });
        });
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
