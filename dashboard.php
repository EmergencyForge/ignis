<?php
require_once __DIR__ . '/assets/config/config.php';
?>
<!DOCTYPE html>
<html lang="de">

<head>
  <?php
  $SITE_TITLE = 'Dashboard';
  include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="dashboard" class="container-full position-relative">
  <div class="twplus-page">
    <div class="row mt-3">
      <div class="col-4 mx-auto text-center">
        <img src="<?php echo SYSTEM_LOGO ?>" alt="<?php echo SYSTEM_NAME ?>" style="height:128px;width:auto">
      </div>
    </div>

    <div class="row">
      <div class="col" id="cards">
        <?php
        require __DIR__ . '/assets/config/database.php';
        
        // Optimiert: Eine einzige JOIN-Query statt N+1 Queries
        $sql = "
            SELECT 
                c.id as category_id,
                c.title as category_title,
                c.priority as category_priority,
                t.id as tile_id,
                t.title as tile_title,
                t.url as tile_url,
                t.icon as tile_icon,
                t.priority as tile_priority
            FROM intra_dashboard_categories c
            LEFT JOIN intra_dashboard_tiles t ON t.category = c.id
            ORDER BY c.priority ASC, t.priority ASC
        ";
        $stmt = $pdo->query($sql);
        $allData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daten nach Kategorien gruppieren
        $categories = [];
        foreach ($allData as $row) {
            $catId = $row['category_id'];
            if (!isset($categories[$catId])) {
                $categories[$catId] = [
                    'id' => $catId,
                    'title' => $row['category_title'],
                    'tiles' => []
                ];
            }
            // Nur hinzufügen wenn Tile existiert (LEFT JOIN kann NULL liefern)
            if ($row['tile_id'] !== null) {
                $categories[$catId]['tiles'][] = [
                    'id' => $row['tile_id'],
                    'title' => $row['tile_title'],
                    'url' => $row['tile_url'],
                    'icon' => $row['tile_icon']
                ];
            }
        }
        
        foreach ($categories as $row) {
          $result2 = $row['tiles'];
        ?>
          <section class="mb-8">
            <h2 class="mb-3"><?= htmlspecialchars($row['title']) ?></h2>
            <div class="twplus-link-grid">
              <?php foreach ($result2 as $tile) { ?>
                <a href="<?= htmlspecialchars($tile['url']) ?>" class="twplus-link-card">
                  <span class="twplus-link-card__icon"><i class="<?= htmlspecialchars($tile['icon']) ?>" aria-hidden="true"></i></span>
                  <span class="twplus-link-card__body">
                    <span class="twplus-link-card__title"><?= htmlspecialchars($tile['title']) ?></span>
                    <span class="twplus-link-card__description">Bereich öffnen</span>
                  </span>
                  <i class="fa-solid fa-chevron-right twplus-link-card__arrow" aria-hidden="true"></i>
                </a>
              <?php } ?>
            </div>
          </section>
        <?php }
        if (empty($categories)) {
          echo '<div class="alert alert-warning" role="alert">Es wurde noch kein Dashboard konfiguriert. Bitte konfiguriere dein Dashboard in der <a class="fw-bold link-underline" href="' . BASE_PATH . 'settings/dashboard/index.php">Administrationsoberfläche</a>.</div>';
        } ?>
      </div>
    </div>

  </div>
  <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>
