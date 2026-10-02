<?php
/** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Rank> $dgsel */
$dgsel = \App\Models\Rank::query()
    ->orderBy('priority')
    ->get(['id', 'name', 'badge']);
?>

<div class="twplus-form-section">
    <div>
        <label class="twplus-form-section__label" for="dienstgrad">Dienstgrad</label>
        <div class="twplus-form-section__hint">Legt Dienstgrad und Darstellung im Profil fest.</div>
    </div>
    <div>
    <select class="ignis-input" data-custom-dropdown="true" name="dienstgrad" id="dienstgrad">
        <?php foreach ($dgsel as $data): ?>
            <option value="<?= (int) $data->id ?>"<?= $dg == $data->id ? ' selected' : '' ?> data-image="<?= htmlspecialchars((string) $data->badgeUrl()) ?>"><?= htmlspecialchars($data->name) ?></option>
        <?php endforeach; ?>
    </select>
    </div>
</div>
