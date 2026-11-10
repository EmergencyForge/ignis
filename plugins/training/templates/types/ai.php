<?php
/**
 * Fragen für den Pool einer Lehrgangsart von der KI vorschlagen lassen,
 * über die EmergencyForge-Konsole.
 *
 * @var \Plugin\Training\Models\CourseType $type
 * @var array{available:bool,enabled:bool,budgetCents:int,spentCents:int,remainingCents:int}|null $state
 * @var bool $acknowledged
 * @var bool $admin
 * @var list<array{unit:\Plugin\Training\Models\Unit,module:string,kind:string,label:string,icon:string,converted:bool}> $sources
 * @var array<int, string> $articles  weitere Wissensartikel zur Auswahl
 * @var list<\Plugin\Training\Models\Topic> $topics
 * @var array<string, mixed>|null $draft  Eingaben nach einem Fehler
 */

use EmergencyForge\Training\Question\QuestionType;
use Plugin\Training\AiDrafts;

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = 'Fragen mit KI vorschlagen';
$base         = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$typeUrl      = $base . 'training/types/' . (int) $type->id;
$poolUrl      = $typeUrl . '#fragenpool';
$e            = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$money        = static fn (int $cents): string => number_format($cents / 100, 2, ',', '.') . ' $';

$ready    = $state !== null && $state['available'] && $state['enabled'] && $state['remainingCents'] > 0;
$chosen   = $draft !== null ? array_map('intval', (array) $draft['units']) : array_map(static fn (array $s): int => $s['unit']->id, $sources);
$picked   = array_map('intval', (array) ($draft['articles'] ?? []));
$count    = (int) ($draft['count'] ?? 10);
$kinds    = (array) ($draft['types'] ?? []);
$topicId  = $draft['topic_id'] ?? null;
$blocked  = !$acknowledged && !$admin;
ob_start();
?>
    <link rel="stylesheet" href="<?= $e(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page" style="max-width: 52rem;">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $e($typeUrl) ?>"><?= $e($type->name) ?></a></span> <span class="ignis-breadcrumb__item"><a href="<?= $e($poolUrl) ?>">Fragenpool</a></span> <span class="ignis-breadcrumb__item" aria-current="page">KI</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= $e($SITE_TITLE) ?></h1>
                    <p class="twplus-page-header__description"><?= $e($type->name) ?>. Die KI schreibt Fragen nur aus dem Material, das du hier auswählst.</p>
                </div>
            </div>

            <?php if (!$ready || $blocked): ?>
                <?php
                $empty = match (true) {
                    $state === null      => ['icon' => 'fa-plug-circle-xmark', 'title' => 'Keine Verbindung zur Konsole', 'text' => 'KI-Vorschläge laufen über die EmergencyForge-Konsole. Diese Instanz ist nicht mit ihr verbunden, oder sie ist gerade nicht erreichbar.'],
                    !$state['available'] => ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Die Konsole bietet keine KI an', 'text' => 'Auf dieser Konsole ist die KI nicht eingerichtet.'],
                    !$state['enabled']   => ['icon' => 'fa-lock', 'title' => 'Für diese Instanz nicht freigeschaltet', 'text' => 'EmergencyForge schaltet die KI je Instanz in der Konsole frei.'],
                    !$ready              => ['icon' => 'fa-hourglass-end', 'title' => 'Das Budget für diesen Monat ist aufgebraucht', 'text' => 'Ab dem Monatsersten geht es weiter. Verbraucht: ' . $money($state['spentCents']) . ' von ' . $money($state['budgetCents']) . '.'],
                    default              => ['icon' => 'fa-user-shield', 'title' => 'Erst ein Administrator', 'text' => 'Bevor jemand Material an die KI gibt, bestätigt ein Administrator einmal für diese Instanz, dass es an Anthropic geht.'],
                };
                $empty['actions'] = [['label' => 'Zum Fragenpool', 'href' => $poolUrl]];
                require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                ?>
            <?php else: ?>
            <form method="post" action="<?= $e($typeUrl . '/questions/ai') ?>" enctype="multipart/form-data" class="ignis-card" onsubmit="var b=this.querySelector('[type=submit]');b.disabled=true;b.lastChild.textContent=' Die KI schreibt, das dauert etwa eine Minute …';">
                <?= csrf_field() ?>
                <div class="ignis-card__body grid gap-4">
                    <?php if (!$acknowledged): ?>
                        <div class="ignis-alert ignis-alert--warn">
                            <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
                            <div class="ignis-alert__body">
                                <div class="ignis-alert__title">Einmal bestätigen</div>
                                Das ausgewählte Material geht über die EmergencyForge-Konsole an Anthropic, um die Fragen zu schreiben. Personenbezogene Daten gehören nicht hinein.
                                <div class="mt-2"><label class="ignis-checkbox"><input type="checkbox" name="acknowledge" value="1" required><span>Verstanden, für diese Instanz freigeben</span></label></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <fieldset class="ignis-field m-0">
                        <legend class="ignis-field__label">Material aus dem Lehrplan</legend>
                        <?php $module = null; foreach ($sources as $source): ?>
                            <?php if ($source['module'] !== $module): $module = $source['module']; ?>
                                <p class="tr-meta mt-2 mb-1"><?= $e($module) ?></p>
                            <?php endif; ?>
                            <div><label class="ignis-checkbox">
                                <input type="checkbox" name="units[]" value="<?= (int) $source['unit']->id ?>"<?= in_array($source['unit']->id, $chosen, true) ? ' checked' : '' ?>>
                                <span><i class="fa-solid <?= $e($source['icon']) ?>" aria-hidden="true"></i> <?= $e($source['label']) ?><?php if ($source['converted']): ?> <span class="tr-meta">· als PDF</span><?php endif; ?></span>
                            </label></div>
                        <?php endforeach; ?>
                        <?php if ($sources === []): ?>
                            <p class="ignis-field__hint m-0">Der Lehrplan hat noch keine Artikel, PDFs oder Wissensartikel. Wähle unten Wissensartikel oder lade eine PDF hoch.</p>
                        <?php endif; ?>
                    </fieldset>

                    <?php if ($articles !== []): ?>
                        <div class="ignis-field m-0">
                            <label class="ignis-field__label" for="ai-articles">Weitere Wissensartikel</label>
                            <select class="ignis-input" id="ai-articles" name="articles[]" multiple data-custom-dropdown="true" data-search-threshold="0" data-placeholder="Artikel suchen und hinzufügen">
                                <?php foreach ($articles as $articleId => $title): ?>
                                    <option value="<?= (int) $articleId ?>"<?= in_array($articleId, $picked, true) ? ' selected' : '' ?>><?= $e($title) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ignis-field__hint">Optional, aus der ganzen Wissensdatenbank. Zusammen mit dem Lehrplan höchstens <?= AiDrafts::MAX_SOURCES ?> Quellen je Durchgang.</p>
                        </div>
                    <?php endif; ?>

                    <div class="ignis-file ignis-file--dropzone" data-ignis-file>
                        <input type="file" id="ai-file" name="file" class="ignis-file__input" accept="application/pdf,.pdf">
                        <label for="ai-file" class="ignis-file__zone">
                            <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                            <span class="ignis-file__title">Weitere PDF ziehen oder <span class="ignis-file__link">wählen</span></span>
                            <span class="ignis-file__hint">Nur für diesen Durchgang, ignis speichert sie nicht. Alle PDFs zusammen max. 20 MB.</span>
                        </label>
                        <div class="ignis-file__selected" hidden></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="ignis-field m-0">
                            <label class="ignis-field__label" for="ai-count">Wie viele Fragen</label>
                            <input type="number" class="ignis-input" id="ai-count" name="count" value="<?= $count ?>" min="1" max="30" style="max-width: 8rem;">
                            <p class="ignis-field__hint">Höchstens 30 auf einmal.</p>
                        </div>
                        <div class="ignis-field m-0">
                            <label class="ignis-field__label" for="ai-topic">Thema</label>
                            <select class="ignis-input" id="ai-topic" name="topic_id" data-custom-dropdown="true">
                                <option value="">Passend zuordnen lassen</option>
                                <?php foreach ($topics as $topic): ?>
                                    <option value="<?= (int) $topic->id ?>"<?= $topicId === $topic->id ? ' selected' : '' ?>><?= $e($topic->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ignis-field__hint">Optional. Ohne Vorgabe ordnet die KI jedem Entwurf ein Thema zu.</p>
                        </div>
                    </div>

                    <fieldset class="ignis-field m-0">
                        <legend class="ignis-field__label">Arten</legend>
                        <div class="grid gap-x-4 gap-y-1" style="grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));">
                            <?php foreach (QuestionType::cases() as $kind): if ($kind === QuestionType::FreeText) continue; ?>
                                <label class="ignis-checkbox"><input type="checkbox" name="types[]" value="<?= $e($kind->value) ?>"<?= in_array($kind->value, $kinds, true) ? ' checked' : '' ?>><span><?= $e($kind->label()) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="ignis-field__hint">Optional. Ohne Auswahl mischt die KI. Freitext schlägt sie nicht vor, weil der immer von Hand bewertet wird.</p>
                    </fieldset>
                </div>
                <div class="ignis-card__footer">
                    <span class="tr-meta mr-auto">Noch <?= $e($money($state['remainingCents'])) ?> in diesem Monat</span>
                    <a href="<?= $e($poolUrl) ?>" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Fragen vorschlagen</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
