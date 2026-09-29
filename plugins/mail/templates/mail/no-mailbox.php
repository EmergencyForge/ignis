<?php
/**
 * View: Mail ohne eigenes Postfach. Postfächer gehören Mitarbeitern und
 * entstehen mit dem Mitarbeiter; das Konto ist fest am Postfach
 * hinterlegt. Die Seite sagt, woran es liegt und wer das ändert, statt
 * eines nackten „kein Zugriff“.
 *
 * @var string $state  inactive = eigenes Postfach gesperrt oder stillgelegt,
 *                     unassigned = Mitarbeiter da, aber kein Postfach an diesem Konto,
 *                     none = Konto mit keinem Mitarbeiter verknüpft
 */

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = 'Mail';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1>Mail</h1>
                </div>
            </div>
            <?php
            $empty = match ($state) {
                'inactive' => [
                    'variant' => 'first',
                    'tone'    => 'warn',
                    'icon'    => 'fa-lock',
                    'title'   => 'Dein Postfach ist nicht aktiv',
                    'text'    => 'Es ist gesperrt oder stillgelegt, etwa weil du im Archiv-Dienstgrad stehst. Freigeben kann es die Postfachverwaltung.',
                ],
                'unassigned' => [
                    'variant' => 'first',
                    'tone'    => 'warn',
                    'icon'    => 'fa-user-lock',
                    'title'   => 'Dein Konto ist keinem Postfach zugeordnet',
                    'text'    => 'Zu deinem Mitarbeiter gibt es kein Postfach, das eindeutig zu diesem Konto gehört. Die Postfachverwaltung ordnet es zu.',
                ],
                default => [
                    'variant' => 'first',
                    'tone'    => 'info',
                    'icon'    => 'fa-inbox',
                    'title'   => 'Noch kein Postfach',
                    'text'    => 'Ein Postfach bekommt jeder Mitarbeiter. Dein Konto ist mit keinem Mitarbeiter verknüpft; das erledigt die Personalverwaltung über die Discord-ID in der Personalakte.',
                ],
            };
            require dirname(__DIR__, 4) . '/templates/partials/empty.php';
            ?>
        </div>
    </div>
