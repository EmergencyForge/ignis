<?php
$rdqsel = \App\Models\AmbSkill::query()
    ->orderBy('priority')
    ->get(['id', 'name', 'priority'])
    ->toArray();
?>

<div class="twplus-form-section">
    <div>
        <label class="twplus-form-section__label" for="qualird">Rettungsdienst</label>
        <div class="twplus-form-section__hint">Aktuelle rettungsdienstliche Qualifikation.</div>
    </div>
    <div>
    <select class="ignis-input" name="qualird" id="qualird">
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
