<?php
require_once __DIR__ . '/assets/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php
  $SITE_TITLE = 'Dashboard';
  include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="dashboard" class="container-full position-relative">
  <div class="container-full mx-5">
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
          <div class="mb-5">
            <div class="row">
              <div class="col mb-3">
                <h2><?= htmlspecialchars($row['title']) ?></h2>
              </div>
            </div>

            <?php
            $chunkedTiles = array_chunk($result2, 6);
            foreach ($chunkedTiles as $tileRow) {
            ?>
              <div class="row mb-3">
                <?php foreach ($tileRow as $tile) { ?>
                  <div class="col-md-2"> <!-- 12 / 6 = 2 per tile -->
                    <a href="<?= htmlspecialchars($tile['url']) ?>">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-fa mb-3 text-center d-block">
                            <i class="<?= htmlspecialchars($tile['icon']) ?>"></i>
                          </div>
                          <h5 class="card-title text-center fw-bold">
                            <?= htmlspecialchars($tile['title']) ?>
                          </h5>
                        </div>
                      </div>
                    </a>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>
          </div>
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