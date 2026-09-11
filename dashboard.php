<?php
require_once __DIR__ . '/assets/config/config.php';

use App\Auth\Permissions;

// Kategorien mit ihren Kacheln in einer Abfrage.
$allData = \Illuminate\Database\Capsule\Manager::table('intra_dashboard_categories as c')
    ->leftJoin('intra_dashboard_tiles as t', 't.category', '=', 'c.id')
    ->orderBy('c.priority')
    ->orderBy('t.priority')
    ->get([
        'c.id as category_id',
        'c.title as category_title',
        't.id as tile_id',
        't.title as tile_title',
        't.url as tile_url',
        't.icon as tile_icon',
    ])
    ->map(fn ($row) => (array) $row)
    ->all();

$categories = [];
foreach ($allData as $row) {
    $catId = $row['category_id'];
    if (!isset($categories[$catId])) {
        $categories[$catId] = ['id' => $catId, 'title' => $row['category_title'], 'tiles' => []];
    }
    // LEFT JOIN: Kategorie ohne Kacheln liefert NULL
    if ($row['tile_id'] !== null) {
        $categories[$catId]['tiles'][] = ['title' => $row['tile_title'], 'url' => $row['tile_url'], 'icon' => $row['tile_icon']];
    }
}

$canConfigure = Permissions::check(['admin', 'dashboard.manage']);

// Die Seite rendert durch die Hülle (templates/layouts/admin.php):
// Inhalt puffern, App\Helpers\Layout legt Topbar und Sidebar drumherum.
ob_start();
?>
  <div class="twplus-page">
    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item is-active">Schnellzugriffe</span></nav>

    <div class="page-header twplus-page-header mb-4">
      <div class="twplus-page-header__copy">
        <p class="twplus-page-header__eyebrow"><?= htmlspecialchars((string) SYSTEM_NAME) ?></p>
        <h1>Schnellzugriffe</h1>
        <p class="twplus-page-header__description">Die Bereiche und Links, die in der Dashboard-Konfiguration hinterlegt sind.</p>
      </div>
      <?php if ($canConfigure): ?>
        <div class="header-actions twplus-page-header__actions">
          <a href="<?= BASE_PATH ?>settings/dashboard/index" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Konfigurieren</a>
        </div>
      <?php endif; ?>
    </div>

    <?php // hosting-self-test.js blendet den Hinweis ein, wenn /api/health nicht antwortet oder Prüfungen fehlschlagen. ?>
    <div
      id="hosting-self-test"
      class="ignis-alert ignis-alert--warn mb-4"
      role="status"
      aria-live="polite"
      hidden
      data-base-path="<?= htmlspecialchars(BASE_PATH, ENT_QUOTES) ?>"
    >
      <i class="fa-solid fa-triangle-exclamation ignis-alert__icon" aria-hidden="true"></i>
      <div class="ignis-alert__body">
        <strong data-hosting-self-test-title>Hosting-Konfiguration prüfen</strong>
        <span data-hosting-self-test-message></span>
        <a
          class="font-bold underline"
          href="https://github.com/EmergencyForge/ignis#hosting-und-url-rewriting"
          target="_blank"
          rel="noopener noreferrer"
        >Hosting-Hilfe öffnen</a>
      </div>
    </div>

    <?php if ($categories === []): ?>
      <div class="ignis-alert ignis-alert--warn" role="alert">
        <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
        <div class="ignis-alert__body">
          Es wurde noch kein Dashboard konfiguriert.
          <?php if ($canConfigure): ?>
            Lege Kategorien und Verlinkungen in der <a class="font-bold underline" href="<?= BASE_PATH ?>settings/dashboard/index">Dashboard-Konfiguration</a> an.
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div id="cards">
      <?php foreach ($categories as $category): ?>
        <section class="mb-8">
          <h2 class="mb-3"><?= htmlspecialchars((string) $category['title']) ?></h2>
          <?php if ($category['tiles'] === []): ?>
            <p class="text-sm text-[var(--text-3)]">Noch keine Verlinkungen in dieser Kategorie.</p>
          <?php endif; ?>
          <div class="twplus-link-grid">
            <?php foreach ($category['tiles'] as $tile): ?>
              <a href="<?= htmlspecialchars((string) $tile['url']) ?>" class="twplus-link-card">
                <span class="twplus-link-card__icon"><i class="<?= htmlspecialchars((string) $tile['icon']) ?>" aria-hidden="true"></i></span>
                <span class="twplus-link-card__body">
                  <span class="twplus-link-card__title"><?= htmlspecialchars((string) $tile['title']) ?></span>
                  <span class="twplus-link-card__description">Bereich öffnen</span>
                </span>
                <i class="fa-solid fa-chevron-right twplus-link-card__arrow" aria-hidden="true"></i>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  </div>
  <script type="module" src="<?= BASE_PATH ?>assets/js/modules/hosting-self-test.js"></script>
<?php
echo \App\Helpers\Layout::render('admin', (string) ob_get_clean(), ['SITE_TITLE' => 'Schnellzugriffe', 'bodyId' => 'dashboard']);
