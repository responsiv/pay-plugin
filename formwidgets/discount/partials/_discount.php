<?php if ($this->previewMode): ?>
    <span class="form-control"><?= $field->value ? e($field->value) : '&nbsp;' ?></span>
<?php else: ?>
    <div
        id="<?= $this->getId() ?>"
        class="input-group">
        <select
            name="<?= $name ?>[symbol]"
            id="<?= $this->getId('type') ?>"
            class="form-select w-auto flex-grow-0">
            <?php if ($fixedPrice): ?>
                <option value="" <?= !$symbol ? 'selected' : '' ?>>Set Price</option>
            <?php endif ?>
            <option value="-" <?= $symbol === '-' ? 'selected' : '' ?>>Amount (-)</option>
            <option value="%" <?= $symbol === '%' ? 'selected' : '' ?>>Percentage (%)</option>
        </select>
        <input
            inputmode="numeric"
            name="<?= $name ?>[amount]"
            id="<?= $this->getId('value') ?>"
            value="<?= e($amount) ?>"
            placeholder="<?= e(trans($field->placeholder)) ?>"
            class="form-control flex-grow-1"
            autocomplete="off"
            <?= $field->getAttributes() ?>
        />
    </div>
<?php endif ?>
