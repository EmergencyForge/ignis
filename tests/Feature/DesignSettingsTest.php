<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SiteCode;
use EmergencyForge\Theme\CustomCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Settings > System > Design: who may save what, audit entries, delivery
 * under /theme/<hash> and safe mode.
 */
final class DesignSettingsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::forgetConfig();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::forgetConfig();
    }

    private static function forgetConfig(): void
    {
        (new \ReflectionProperty(\App\Config\ConfigManager::class, 'configCache'))->setValue(null, null);
    }

    /** @param list<string> $permissions */
    private function login(array $permissions): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username]);
    }

    private static function stored(string $key): ?string
    {
        return Capsule::table('intra_config')->where('config_key', $key)->value('config_value');
    }

    /** @return object{action:string, details:?string, context:?string}|null */
    private static function audit(string $action): ?object
    {
        return Capsule::table('intra_audit_log')->where('action', $action)->orderByDesc('id')->first(['action', 'details', 'context']);
    }

    #[Test]
    public function without_admin_the_page_and_saving_are_refused(): void
    {
        $this->login(['personnel.view']);

        $this->assertForbidden($this->get('/settings/system/design'));
        $this->assertForbidden($this->post('/settings/system/design/css', ['scss' => 'a { color: red; }']));
        $this->assertNull(self::stored(SiteCode::CSS));
    }

    #[Test]
    public function scss_is_stored_compiled_with_audit_entry_and_served(): void
    {
        $this->login(['admin']);

        $response = $this->post('/settings/system/design/css', ['scss' => "\$c: #123456;\n.ignis-card { border-color: \$c; }"]);

        $this->assertRedirect($response, '/settings/system/design?tab=css');
        $css = '.ignis-card{border-color:#123456}';
        $this->assertSame($css, self::stored(SiteCode::CSS));
        $entry = self::audit('Eigenes CSS geändert');
        $this->assertNotNull($entry);
        $context = json_decode((string) $entry->context, true);
        $this->assertSame('leer', $context['custom_css_before']);
        $this->assertSame(CustomCode::hash($css), $context['custom_css_after']);
        $this->assertStringNotContainsString('#123456', (string) $entry->details);

        $hash = CustomCode::hash($css);
        $this->assertBodyContains('theme/' . $hash . '.css', $this->get('/settings/system/design'));

        // Without a session too: the login page loads it.
        $_SESSION = [];
        $file = $this->get('/theme/' . $hash . '.css');
        $this->assertOk($file);
        $this->assertSame($css, $file->body);
        $this->assertSame(CustomCode::CACHE_CONTROL, $file->headers['Cache-Control'] ?? null);
        $this->assertSame('nosniff', $file->headers['X-Content-Type-Options'] ?? null);
        $this->assertNotFound($this->get('/theme/0000000000000000.css'));
    }

    #[Test]
    public function invalid_scss_keeps_the_input_and_stores_nothing(): void
    {
        $this->login(['admin']);

        $response = $this->post('/settings/system/design/css', ['scss' => "a {\n  color: \$nope;\n}"]);

        $this->assertStatus(422, $response);
        $this->assertBodyContains('Fehler in Zeile 2', $response);
        $this->assertBodyContains('color: $nope;', $response);
        $this->assertNull(self::stored(SiteCode::CSS));
    }

    #[Test]
    public function editor_styles_are_stored_and_handed_to_the_editor(): void
    {
        $this->login(['admin']);

        $response = $this->post('/settings/system/design/styles', ['styles' => [
            ['label' => 'Hinweis', 'key' => 'hinweis', 'kind' => 'block', 'css' => 'color: #b45309;'],
            ['label' => '', 'key' => '', 'kind' => 'block', 'css' => ''],
        ]]);

        $this->assertRedirect($response, '/settings/system/design?tab=styles');
        $this->assertSame(['hinweis'], SiteCode::styles()->keys());
        $this->assertNotNull(self::audit('Editor-Stile geändert'));
        $this->assertBodyContains('&quot;key&quot;:&quot;hinweis&quot;', $this->get('/settings/system/design'));
    }

    #[Test]
    public function style_css_that_leaves_its_block_is_rejected(): void
    {
        $this->login(['admin']);

        $response = $this->post('/settings/system/design/styles', ['styles' => [
            ['label' => 'Böse', 'key' => 'boese', 'kind' => 'inline', 'css' => 'color: red } body { display: none'],
        ]]);

        $this->assertStatus(422, $response);
        $this->assertBodyContains('ist im CSS eines Stils nicht erlaubt', $response);
        $this->assertNull(self::stored(SiteCode::STYLES));
    }

    #[Test]
    public function javascript_needs_full_admin(): void
    {
        $this->login(['admin']);

        $this->assertBodyNotContains('name="js_head"', $this->get('/settings/system/design'));
        $this->assertForbidden($this->post('/settings/system/design/js', ['js_head' => 'alert(1)']));
        $this->assertNull(self::stored(SiteCode::JS_HEAD));
    }

    #[Test]
    public function full_admin_saves_javascript_and_safe_mode_turns_it_off(): void
    {
        $this->login(['full_admin']);

        $this->assertBodyContains('name="js_head"', $this->get('/settings/system/design'));
        $this->post('/settings/system/design/js', ['js_head' => "window.x = 1;\r\n", 'js_body' => '']);
        $this->assertSame("window.x = 1;\n", self::stored(SiteCode::JS_HEAD));
        $this->assertNull(self::stored(SiteCode::JS_BODY));
        $this->assertNotNull(self::audit('Eigenes JavaScript geändert'));

        $script = 'theme/' . CustomCode::hash("window.x = 1;\n") . '.js';
        $this->assertBodyContains($script, $this->get('/settings/system/design'));

        $safe = $this->get('/settings/system/design', ['query' => ['safemode' => '1']]);
        $this->assertBodyNotContains($script, $safe);
        $this->assertBodyContains('Abgesicherter Modus', $safe);

        // Stays on for the session until switched off.
        $this->assertBodyNotContains($script, $this->get('/settings/system/design'));
        $this->assertBodyContains($script, $this->get('/settings/system/design', ['query' => ['safemode' => '0']]));
    }
}
