<?php
$dgsel = \App\Models\Rank::query()
    ->orderBy('priority')
    ->get(['id', 'name', 'priority'])
    ->toArray();
?>

<div class="twplus-form-section">
    <div>
        <label class="twplus-form-section__label" for="dienstgrad">Rank</label>
        <div class="twplus-form-section__hint">Legt Dienstgrad und Darstellung im Profil fest.</div>
    </div>
    <div>
    <select class="ignis-input" name="dienstgrad" id="dienstgrad">
        <?php foreach ($dgsel as $data) {
            if ($dg == $data['id']) {
                echo "<option value='{$data['id']}' selected='selected'>{$data['name']}</option>";
            } else {
                echo "<option value='{$data['id']}'>{$data['name']}</option>";
            }
        } ?>
    </select>
    </div>
</div>
