<?php
$stmt = $pdo->prepare("SELECT id,name,priority FROM intra_mitarbeiter_dienstgrade ORDER BY priority ASC");
$stmt->execute();
$dgsel = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="form-floating">
    <select class="form-select mt-3" name="dienstgrad" id="dienstgrad">
        <?php foreach ($dgsel as $data) {
            if ($dg == $data['id']) {
                echo "<option value='{$data['id']}' selected='selected'>{$data['name']}</option>";
            } else {
                echo "<option value='{$data['id']}'>{$data['name']}</option>";
            }
        } ?>
    </select>
    <label for="dienstgrad">Rank</label>
</div>