<?php
$stmt = $pdo->prepare("SELECT id,name,priority FROM intra_mitarbeiter_rdquali ORDER BY priority ASC");
$stmt->execute();
$rdqsel = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="twplus-form-section">
    <div>
        <label class="twplus-form-section__label" for="qualird">Rettungsdienst</label>
        <div class="twplus-form-section__hint">Aktuelle rettungsdienstliche Qualifikation.</div>
    </div>
    <div>
    <select class="form-select" name="qualird" id="qualird">
        <?php foreach ($rdqsel as $data) {
            if ($rdq == $data['id']) {
                echo "<option value='{$data['id']}' selected='selected'>{$data['name']}</option>";
            } else {
                echo "<option value='{$data['id']}'>{$data['name']}</option>";
            }
        } ?>
    </select>
    </div>
</div>
