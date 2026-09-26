<?php
/**
 * Leerzustand nach dem gemeinsamen Baustein `ignis-empty` aus dem UI-Paket.
 *
 *   $empty = ['variant' => 'sm', 'tone' => 'ok', 'icon' => 'fa-check',
 *             'title' => 'Keine offenen Mängel', 'text' => '…'];
 *   require dirname(__DIR__) . '/partials/empty.php';
 *
 * Läuft im Scope des Aufrufers und weist deshalb nur Variablen mit dem
 * Präfix `empty` zu. Alles, was hineinkommt, wird hier escaped.
 */

$emptyE = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$emptyVariant = in_array($empty['variant'] ?? 'default', ['default', 'sm', 'first', 'inline'], true) ? ($empty['variant'] ?? 'default') : 'default';
$emptyTone = in_array($empty['tone'] ?? 'neutral', ['neutral', 'info', 'ok', 'warn', 'danger'], true) ? ($empty['tone'] ?? 'neutral') : 'neutral';
$emptyHeading = in_array($empty['heading'] ?? 3, [2, 3, 4], true) ? (int) ($empty['heading'] ?? 3) : 3;
$emptyIcon = preg_match('/^fa-[a-z0-9-]+$/', (string) ($empty['icon'] ?? '')) ? (string) $empty['icon'] : '';
$emptySmall = $emptyVariant !== 'default' && $emptyVariant !== 'first';

// Nur interne oder ausdruecklich erlaubte Ziele: /pfad (kein //host), ?query,
// #anker, https://, http://, mailto:. Alles andere (z.B. javascript:, data:)
// gilt als unsicher und wird verworfen statt gerendert.
$emptySafeUrl = static fn (string $url): bool => (bool) preg_match('~^(?:/(?!/)|\?|#|https://|http://|mailto:)~i', $url);

$emptyAction = static function (array $action) use ($emptyE, $emptySmall, $emptySafeUrl): string {
    $emptyBtnStyle = in_array($action['style'] ?? 'secondary', ['primary', 'secondary', 'ghost'], true) ? ($action['style'] ?? 'secondary') : 'secondary';
    $emptyBtnClass = 'ignis-btn ignis-btn--' . $emptyBtnStyle . ($emptySmall ? ' ignis-btn--sm' : '');
    $emptyBtnAttrs = '';
    foreach ($action['attrs'] ?? [] as $emptyAttrName => $emptyAttrValue) {
        if (!is_scalar($emptyAttrValue)) {
            continue;
        }
        if (preg_match('/^data-[a-z0-9-]+$/', (string) $emptyAttrName)) {
            $emptyBtnAttrs .= ' ' . $emptyAttrName . '="' . $emptyE($emptyAttrValue) . '"';
        }
    }
    $emptyBtnIcon = preg_match('/^fa-[a-z0-9-]+$/', (string) ($action['icon'] ?? '')) ? '<i class="fa-solid ' . $action['icon'] . '" aria-hidden="true"></i> ' : '';
    $emptyBtnLabel = $emptyBtnIcon . $emptyE($action['label'] ?? '');
    $emptyBtnHref = isset($action['href']) && $emptySafeUrl((string) $action['href']) ? (string) $action['href'] : null;
    return $emptyBtnHref !== null
        ? '<a class="' . $emptyBtnClass . '" href="' . $emptyE($emptyBtnHref) . '"' . $emptyBtnAttrs . '>' . $emptyBtnLabel . '</a>'
        : '<button type="button" class="' . $emptyBtnClass . '"' . $emptyBtnAttrs . '>' . $emptyBtnLabel . '</button>';
};

if ($emptyVariant === 'inline') {
    echo '<div class="ignis-empty ignis-empty--inline">';
    if ($emptyIcon !== '') {
        echo '<i class="fa-solid ' . $emptyIcon . '" aria-hidden="true"></i>';
    }
    echo '<span>' . $emptyE($empty['text'] ?? '') . '</span>';
    foreach ($empty['actions'] ?? [] as $emptyItem) {
        echo $emptyAction($emptyItem);
    }
    echo '</div>';
    return;
}

$emptyClass = 'ignis-empty' . ($emptyVariant === 'default' ? '' : ' ignis-empty--' . $emptyVariant);
echo '<div class="' . $emptyClass . '" data-tone="' . $emptyTone . '">';

$emptyCols = max(0, min(6, (int) ($empty['ghostColumns'] ?? 0)));
if ($emptyVariant === 'first' && $emptyCols > 0) {
    echo '<div class="ignis-empty__ghost" aria-hidden="true" style="--ghost-cols: ' . $emptyCols . '">';
    echo str_repeat('<div>' . str_repeat('<i></i>', $emptyCols + 2) . '</div>', 5);
    echo '</div>';
}
if ($emptyIcon !== '') {
    echo '<span class="ignis-empty__glyph" aria-hidden="true"><i class="fa-solid ' . $emptyIcon . '"></i></span>';
}
echo '<h' . $emptyHeading . ' class="ignis-empty__title">' . $emptyE($empty['title'] ?? '') . '</h' . $emptyHeading . '>';
if (($empty['text'] ?? '') !== '') {
    echo '<p class="ignis-empty__text">' . $emptyE($empty['text']) . '</p>';
}
// Die Zeile erscheint nur, wenn es einen Suchbegriff oder einen Filter mit sicherem Ziel gibt.
$emptyQuery = '';
if (($empty['query']['term'] ?? '') !== '') {
    $emptyQuery .= '<span class="ignis-empty__term">„' . $emptyE($empty['query']['term']) . '“</span>';
}
foreach ($empty['query']['filters'] ?? [] as $emptyItem) {
    $emptyFilterHref = (string) ($emptyItem['removeHref'] ?? '');
    if (!$emptySafeUrl($emptyFilterHref)) {
        continue;
    }
    $emptyQuery .= '<a class="ignis-empty__filter" href="' . $emptyE($emptyFilterHref) . '">' . $emptyE($emptyItem['label'] ?? '')
        . ' <i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="ignis-sr-only">entfernen</span></a>';
}
if ($emptyQuery !== '') {
    echo '<div class="ignis-empty__query">' . $emptyQuery . '</div>';
}
if (($empty['tips'] ?? []) !== []) {
    echo '<ul class="ignis-empty__tips">';
    foreach ($empty['tips'] as $emptyItem) {
        echo '<li>' . $emptyE($emptyItem) . '</li>';
    }
    echo '</ul>';
}
if (($empty['steps'] ?? []) !== []) {
    $emptyDone = count(array_filter($empty['steps'], static fn (array $step): bool => ($step['state'] ?? '') === 'done'));
    $emptyTotal = count($empty['steps']);
    echo '<div class="ignis-empty__progress"><span>' . $emptyDone . ' von ' . $emptyTotal . ' erledigt</span><i style="--progress: ' . (int) round($emptyDone / $emptyTotal * 100) . '%"></i></div>';
    echo '<ol class="ignis-empty__steps">';
    foreach ($empty['steps'] as $emptyIndex => $emptyItem) {
        $emptyState = in_array($emptyItem['state'] ?? 'todo', ['done', 'current', 'todo'], true) ? ($emptyItem['state'] ?? 'todo') : 'todo';
        echo '<li data-state="' . $emptyState . '"><span class="ignis-empty__step-no">'
            . ($emptyState === 'done' ? '<i class="fa-solid fa-check" aria-hidden="true"></i><span class="ignis-sr-only">erledigt</span>' : (string) ($emptyIndex + 1))
            . '</span><span>' . $emptyE($emptyItem['label'] ?? '') . '</span>'
            . (isset($emptyItem['action']) ? $emptyAction($emptyItem['action']) : '') . '</li>';
    }
    echo '</ol>';
}
if (($empty['actions'] ?? []) !== []) {
    echo '<div class="ignis-empty__actions">';
    foreach ($empty['actions'] as $emptyItem) {
        echo $emptyAction($emptyItem);
    }
    echo '</div>';
}
if (($empty['code'] ?? '') !== '') {
    echo '<p class="ignis-empty__code">' . $emptyE($empty['code']) . '</p>';
}
echo '</div>';
