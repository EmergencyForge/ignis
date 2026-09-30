<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\ReleaseNotes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleaseNotesTest extends TestCase
{
    #[Test]
    public function renders_headings_lists_bold_text_and_escapes_the_rest(): void
    {
        $markdown = "# Titel\n## Neu\n### Details\n- Punkt <b>1</b>\n* Punkt 2\n\n- Punkt 3\nText mit **fett** & <script>\n#Kein Titel\n\nNormaler Text\n- Letzter";

        self::assertSame(
            '<h4>Titel</h4><h5>Neu</h5><h6>Details</h6>'
            . '<ul><li>Punkt &lt;b&gt;1&lt;/b&gt;</li><li>Punkt 2</li><li>Punkt 3</li></ul>'
            . '<p>Text mit <strong>fett</strong> &amp; &lt;script&gt;</p>'
            . '<p>#Kein Titel</p><p>Normaler Text</p><ul><li>Letzter</li></ul>',
            ReleaseNotes::toHtml($markdown)
        );
    }
}
