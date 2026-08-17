<?php
$stmt = $pdo->prepare("SELECT id,name,shortname,priority FROM intra_mitarbeiter_fwquali ORDER BY priority ASC");
$stmt->execute();
$bfqsel = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="twplus-form-section">
    <div>
        <label class="twplus-form-section__label" for="qualifw2">Feuerwehr</label>
        <div class="twplus-form-section__hint">Aktuelle feuerwehrtechnische Qualifikation.</div>
    </div>
    <div>
    <select class="form-select" name="qualifw2" id="qualifw2">
        <?php foreach ($bfqsel as $data) {
            if ($bfq2 == $data['id']) {
                echo "<option value='{$data['id']}' selected='selected'>{$data['name']}</option>";
            } else {
                echo "<option value='{$data['id']}'>{$data['name']}</option>";
            }
        } ?>
    </select>
    </div>
</div>
