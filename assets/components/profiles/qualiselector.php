<?php
$bfqsel = \App\Models\FdSkill::query()
    ->orderBy('priority')
    ->get(['id', 'name', 'shortname', 'priority'])
    ->toArray();
?>

<div class="twplus-form-section">
    <div>
        <label class="twplus-form-section__label" for="qualifw2">Feuerwehr</label>
        <div class="twplus-form-section__hint">Aktuelle feuerwehrtechnische Qualifikation.</div>
    </div>
    <div>
    <select class="ignis-input" data-custom-dropdown="true" name="qualifw2" id="qualifw2">
        <?php foreach ($bfqsel as $data): ?>
            <option value="<?= (int) $data['id'] ?>"<?= $bfq2 == $data['id'] ? ' selected' : '' ?>><?= htmlspecialchars($data['name']) ?></option>
        <?php endforeach; ?>
    </select>
    </div>
</div>
