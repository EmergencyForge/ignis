<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\InboxController;
use App\Models\Notification;
use App\Notifications\NotificationManager;
use App\Plugins\Plugin;
use App\Plugins\PluginLoader;
use App\Plugins\PluginManifest;
use App\Security\CsrfProtection;
use App\Support\NavigationCounters;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Posteingang (App\Notifications\NotificationManager mit Typ-Registry):
 * notify() landet beim Empfänger, die Seite /inbox listet mit Filtern,
 * das Popover der Glocke kommt ohne Hülle mit Limit, gelesen wird einzeln
 * oder alles, ein Typ ohne Recht bleibt unsichtbar, ein Plugin-Typ aus
 * dem Manifest wird aufgelöst, ein Typ ohne Handler erscheint roh.
 */
final class InboxTest extends FeatureTestCase
{
    private int $userId = 0;

    /**
     * @param list<string> $permissions
     */
    private function login(array $permissions = ['full_admin']): int
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
        NavigationCounters::reset();

        return $this->userId = $user->id;
    }

    private function manager(): NotificationManager
    {
        return $this->container->get(NotificationManager::class);
    }

    #[Test]
    public function notify_landet_beim_empfaenger_und_zaehlt_an_glocke_und_sidebar(): void
    {
        $me = $this->login();
        $other = FixtureFactory::user();

        $created = $this->manager()->notify('antrag', [$me, $other->id, $me, 0], [
            'title'   => 'Ihr Antrag #7 wurde bearbeitet',
            'message' => 'Status: Genehmigt.',
            'link'    => '/forms/view?antrag=7',
        ]);
        $this->assertSame(2, $created, 'Je Empfänger ein Eintrag, doppelte und ungültige Ids fallen weg.');
        $this->assertSame(0, $this->manager()->notify('gibt_es_nicht', [$me], ['title' => 'x']));
        $this->assertSame(1, $this->manager()->count($me));
        $this->assertSame(1, $this->manager()->count($other->id));

        $page = $this->get('/inbox');
        $this->assertOk($page);
        $this->assertBodyContains('Eine ungelesene Benachrichtigung.', $page);
        $this->assertBodyContains('Ihr Antrag #7 wurde bearbeitet', $page);
        $this->assertBodyContains('Status: Genehmigt.', $page);
        $this->assertBodyContains('class="ignis-inbox__day">Heute<', $page);
        $this->assertBodyContains('ignis-chip--sm">Anträge<', $page);
        // Der Eintrag führt über open zum Ziel; der Zähler steht an der Glocke und in der Sidebar.
        $this->assertMatchesRegularExpression('~href="/inbox/(\d+)/open" class="ignis-inbox__link"~', $page->body);
        $this->assertBodyContains('aria-label="Posteingang, 1 ungelesen"', $page);
        $this->assertBodyContains('class="ignis-topbar__badge notification-poll-badge">1<', $page);
        $this->assertBodyContains('class="ignis-sidebar__count notification-poll-badge">1<', $page);
        $this->assertBodyContains('data-ignis-inbox="/inbox/popover"', $page);
        $this->assertBodyNotContains('notifications/index', $page);
        $this->assertSame(1, NavigationCounters::for('inbox'));
    }

    #[Test]
    public function popover_ohne_huelle_mit_limit(): void
    {
        $me = $this->login();
        for ($i = 1; $i <= 7; $i++) {
            $this->manager()->notify('system', [$me], ['title' => "Meldung {$i}", 'link' => '/index']);
        }

        $popover = $this->get('/inbox/popover');

        $this->assertOk($popover);
        $this->assertBodyNotContains('<html', $popover);
        $this->assertBodyNotContains('ignis-topbar', $popover);
        $this->assertSame(InboxController::POPOVER_LIMIT, substr_count($popover->body, 'ignis-inbox-popover__item'));
        $this->assertBodyContains('Meldung 7', $popover);
        $this->assertBodyNotContains('Meldung 1<', $popover);
        $this->assertBodyContains('7 ungelesen · Alle gelesen', $popover);
        $this->assertBodyContains('data-ignis-inbox-read', $popover);
        $this->assertBodyContains('href="/inbox?filter=unread" class="ignis-menu__link" role="menuitem">2 weitere', $popover);
        $this->assertBodyContains('href="/inbox" class="ignis-menu__link"', $popover);
    }

    #[Test]
    public function gelesen_einzeln_alle_und_beim_oeffnen(): void
    {
        $me = $this->login();
        $this->manager()->notify('dokument', [$me], ['title' => 'Dokument A', 'link' => '/personnel/document-view?docid=1']);
        $this->manager()->notify('dokument', [$me], ['title' => 'Dokument B']);
        $this->manager()->notify('dokument', [$me], ['title' => 'Dokument C']);
        $ids = Notification::query()->where('user_id', $me)->orderBy('id')->pluck('id')->all();
        $this->assertCount(3, $ids);

        // Einzeln, mit CSRF-Token; ohne Token ändert sich nichts.
        $denied = $this->post('/inbox/read', ['id' => (string) $ids[1], 'csrf_token' => 'falsch']);
        $this->assertSame(403, $denied->status);
        $this->assertSame(3, $this->manager()->count($me));

        $one = $this->post('/inbox/read', ['id' => (string) $ids[1], 'return' => 'inbox?filter=unread', 'csrf_token' => CsrfProtection::getToken()]);
        $this->assertRedirect($one, '/inbox?filter=unread');
        $this->assertSame(2, $this->manager()->count($me));

        // Öffnen setzt gelesen und geht zum Ziel; ohne Ziel zurück zur Seite.
        $open = $this->get('/inbox/' . $ids[0] . '/open');
        $this->assertRedirect($open, '/personnel/document-view?docid=1');
        $this->assertSame(1, $this->manager()->count($me));
        $this->assertRedirect($this->get('/inbox/' . $ids[2] . '/open'), '/inbox');
        $this->assertSame(0, $this->manager()->count($me));
        $this->assertRedirect($this->get('/inbox/999999999/open'), '/inbox');

        // Ungelesen-Filter zeigt nichts mehr, die Seite alles.
        $unread = $this->get('/inbox', ['query' => ['filter' => 'unread']]);
        $this->assertBodyContains('Nichts gefunden', $unread);
        $all = $this->get('/inbox');
        $this->assertBodyContains('Dokument B', $all);
        $this->assertBodyNotContains('is-unread', $all);

        // Alle gelesen: zwei neue, ein Post ohne id.
        $this->manager()->notify('dokument', [$me], ['title' => 'Dokument D']);
        $this->manager()->notify('dokument', [$me], ['title' => 'Dokument E']);
        $all = $this->post('/inbox/read', ['csrf_token' => CsrfProtection::getToken()]);
        $this->assertRedirect($all, '/inbox');
        $this->assertSame(0, $this->manager()->count($me));
        // Fremde Einträge bleiben unberührt.
        $other = FixtureFactory::user();
        $this->manager()->notify('dokument', [$other->id], ['title' => 'Fremd']);
        $this->post('/inbox/read', ['csrf_token' => CsrfProtection::getToken()]);
        $this->assertSame(1, $this->manager()->count($other->id));
    }

    #[Test]
    public function typ_ohne_recht_erscheint_nicht(): void
    {
        $me = $this->login(['calendar.view']);
        $this->manager()->notify('vehicle_defect', [$me], ['title' => 'Neuer Defekt: Bremsen', 'link' => '/settings/vehicles/defects/index?vehicle=1']);
        $this->manager()->notify('calendar', [$me], ['title' => 'Termin angelegt: Übung']);

        $this->assertSame(1, $this->manager()->count($me));
        $page = $this->get('/inbox');
        $this->assertBodyContains('Termin angelegt: Übung', $page);
        $this->assertBodyNotContains('Neuer Defekt: Bremsen', $page);
        $this->assertBodyNotContains('Fahrzeugmängel', $page, 'Auch der Typ-Filter kennt den Typ nicht.');
        $this->assertBodyNotContains('lex-topbar', $page);

        // Mit dem Recht ist der Eintrag da.
        $_SESSION['permissions'] = ['calendar.view', 'vehicles.view'];
        NavigationCounters::reset();
        $this->assertSame(2, $this->manager()->count($me));
        $page = $this->get('/inbox', ['query' => ['type' => 'vehicle_defect']]);
        $this->assertBodyContains('Neuer Defekt: Bremsen', $page);
        $this->assertBodyNotContains('Termin angelegt: Übung', $page);
    }

    #[Test]
    public function typ_ohne_handler_erscheint_roh(): void
    {
        $me = $this->login();
        Notification::query()->insert([
            'user_id' => $me,
            'type'    => 'altes_plugin',
            'title'   => 'Aus einem abgeschalteten Plugin',
            'message' => 'Der Text bleibt lesbar.',
            'link'    => '/legacy/target',
        ]);

        $page = $this->get('/inbox');
        $this->assertBodyContains('Aus einem abgeschalteten Plugin', $page);
        $this->assertBodyContains('ignis-chip--sm ignis-chip--secondary">altes_plugin<', $page);
        $this->assertBodyContains('fa-solid fa-bell', $page);
        $this->assertSame(1, $this->manager()->count($me));

        $id = (int) Notification::query()->where('user_id', $me)->value('id');
        $this->assertRedirect($this->get('/inbox/' . $id . '/open'), '/legacy/target');
    }

    #[Test]
    public function plugin_typ_aus_dem_manifest_wird_aufgeloest(): void
    {
        $me = $this->login(['good.view']);

        $dir = dirname(__DIR__) . '/Unit/Plugins/fixtures/plugins/good';
        require_once $dir . '/src/Notifications/WidgetType.php';
        $plugin = new Plugin(PluginManifest::fromArray(require $dir . '/manifest.php'), $dir);
        $loader = new class([$plugin]) extends PluginLoader {
            /** @param list<Plugin> $stubbed */
            public function __construct(private readonly array $stubbed)
            {
            }

            public function active(): array
            {
                return $this->stubbed;
            }
        };
        $container = $this->container;
        $this->assertInstanceOf(\DI\Container::class, $container);
        $previous = [
            PluginLoader::class        => $container->get(PluginLoader::class),
            NotificationManager::class => $container->get(NotificationManager::class),
            InboxController::class     => $container->get(InboxController::class),
        ];
        $manager = new NotificationManager($loader);
        $container->set(PluginLoader::class, $loader);
        $container->set(NotificationManager::class, $manager);
        $container->set(InboxController::class, new InboxController($manager));

        try {
            $this->assertSame(1, $manager->notify('widget', [$me], ['title' => 'Widget fertig', 'message' => 'Widget #42 ist gebaut.']));
            NavigationCounters::reset();

            $page = $this->get('/inbox');
            $this->assertBodyContains('Widget fertig', $page);
            $this->assertBodyContains('ignis-chip--sm">Widgets<', $page);
            $this->assertBodyContains('fa-solid fa-puzzle-piece', $page);
            $id = (int) Notification::query()->where('user_id', $me)->value('id');
            // Kein Link gespeichert: der Handler leitet ihn aus dem Text ab.
            $this->assertRedirect($this->get('/inbox/' . $id . '/open'), '/good/widgets/42');

            // Ohne good.view versteckt der Handler den Typ.
            $_SESSION['permissions'] = [];
            NavigationCounters::reset();
            $this->assertSame([], $manager->forUser($me));
            $this->assertArrayNotHasKey('widget', $manager->visibleTypes());
        } finally {
            foreach ($previous as $id => $instance) {
                $container->set($id, $instance);
            }
        }
    }
}
