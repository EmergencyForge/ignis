<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Signature;
use Plugin\Mail\SignatureTemplate;
use Tests\FeatureTestCase;

/**
 * Platzhalter in Signaturen: Vorschau mit den eigenen Angaben in den
 * Mail-Einstellungen und im Signatur-Editor des Postfachs, Platzhalter
 * auch in der eigenen Signatur, eingesetzt beim Verfassen.
 */
final class SignaturePlaceholdersTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    #[Test]
    public function einstellungen_zeigen_platzhalter_und_eine_vorschau_mit_den_eigenen_angaben(): void
    {
        $admin = $this->member('Ada Admin');
        $admin['person']->forceFill(['zusatz' => 'Wachabteilungsleiterin'])->save();
        $this->loginAs($admin['user'], ['mail.use', 'mail.admin']);

        $page = $this->get('/settings/mail');
        $this->assertOk($page);
        foreach (array_keys(SignatureTemplate::catalog()) as $key) {
            $this->assertBodyContains('data-mail-variable="' . $key . '"', $page);
        }
        $this->assertBodyContains('Vorschau mit deinen Angaben', $page);
        $this->assertBodyContains('id="mail-default-signature-preview"', $page);
        // Die Werte stehen als Editor-Knoten im Attribut, escaped.
        $this->assertBodyContains('&quot;absender.position&quot;:[{&quot;type&quot;:&quot;text&quot;,&quot;text&quot;:&quot;Wachabteilungsleiterin&quot;}]', $page);
        $this->assertBodyContains('&quot;absender.mailadresse&quot;:[{&quot;type&quot;:&quot;text&quot;,&quot;text&quot;:&quot;' . $admin['mailbox']->address . '&quot;}]', $page);
    }

    #[Test]
    public function werte_in_der_vorschau_sind_escaped(): void
    {
        $admin = $this->member('Ada Admin');
        $admin['person']->forceFill(['zusatz' => '<img src=x onerror=alert(1)>'])->save();
        $this->loginAs($admin['user'], ['mail.use', 'mail.admin']);

        $page = $this->get('/settings/mail');
        $this->assertBodyNotContains('<img src=x onerror=alert(1)>', $page);
        $this->assertBodyContains('&lt;img src=x onerror=alert(1)&gt;', $page);
    }

    #[Test]
    public function eigene_signatur_mit_platzhaltern_folgt_dem_profil(): void
    {
        $alice = $this->member('Alice Absender');
        Signature::query()->create(['mailbox_id' => $alice['mailbox']->id, 'body_json' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gruß, '], ['type' => 'docVariable', 'attrs' => ['name' => 'absender.name']]]],
            ['type' => 'paragraph', 'content' => [['type' => 'docVariable', 'attrs' => ['name' => 'absender.position']]]],
            ['type' => 'paragraph', 'content' => [['type' => 'docVariable', 'attrs' => ['name' => 'gibt.es.nicht']]]],
        ]]]);
        $this->loginAs($alice['user']);

        $compose = $this->get('/mail/compose');
        $this->assertBodyContains('&quot;text&quot;:&quot;Alice Absender&quot;', $compose);
        // Ohne Position und mit unbekanntem Platzhalter fallen die Zeilen weg.
        $this->assertBodyNotContains('docVariable', $compose);
        $this->assertBodyNotContains('gibt.es.nicht', $compose);

        $alice['person']->forceFill(['zusatz' => 'Zugführerin'])->save();
        $this->loginAs($alice['user']); // neuer Request: Postfach-Cache leeren
        $this->assertBodyContains('&quot;text&quot;:&quot;Zugführerin&quot;', $this->get('/mail/compose'));
    }

    #[Test]
    public function ohne_eigene_steht_die_vorlage_mit_platzhaltern_im_editor(): void
    {
        $alice = $this->member('Alice Absender');
        Capsule::table('intra_config')->where('config_key', 'MAIL_DEFAULT_SIGNATURE')->update(['config_value' => json_encode(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'docVariable', 'attrs' => ['name' => 'absender.name']]]],
        ]])]);
        $this->loginAs($alice['user']);

        $form = $this->get('/mail/signature');
        $this->assertOk($form);
        $this->assertMatchesRegularExpression('~id="mail-signature-editor"[^>]*data-efe-content="[^"]*docVariable~', $form->body);
        $this->assertBodyContains('&quot;absender.name&quot;:[{&quot;type&quot;:&quot;text&quot;,&quot;text&quot;:&quot;Alice Absender&quot;}]', $form);
    }
}
