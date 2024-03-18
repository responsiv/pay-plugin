<?php
    $lockerValue = is_numeric($field->value) ? $field->value * 100 : 0;
?>
<input
    type="number"
    step="any"
    id="<?= $field->getId('locker') ?>"
    value="<?= e($lockerValue) ?>"
    placeholder="<?= e(trans($field->placeholder)) ?>"
    class="form-control"
    oninput="$('#<?= $field->getId() ?>').val($(this).val() / 100)"
    autocomplete="off"
    <?= $field->hasAttribute('pattern') ? '' : 'pattern="-?\d+(\.\d+)?"' ?>
    <?= $field->hasAttribute('maxlength') ? '' : 'maxlength="255"' ?>
    <?= $field->getAttributes() ?>
/>
<input
    type="hidden"
    name="<?= $field->getName() ?>"
    id="<?= $field->getId() ?>"
    value="<?= e($field->value ?? 0.00) ?>"
/>
