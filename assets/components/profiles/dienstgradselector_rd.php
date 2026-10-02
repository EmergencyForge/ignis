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
    <select class="ignis-input" data-custom-dropdown="true" name="qualird" id="qualird">
        <?php foreach ($rdqsel as $data): ?>
            <option value="<?= (int) $data['id'] ?>"<?= $rdq == $data['id'] ? ' selected' : '' ?>><?= htmlspecialchars($data['name']) ?></option>
        <?php endforeach; ?>
    </select>
    </div>
</div>
