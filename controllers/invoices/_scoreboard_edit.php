<div data-control="toolbar">
    <?php if ($formModel->exists): ?>
        <div class="scoreboard-item title-value">
            <h4><?= __("Invoice") ?></h4>
            <p><?= e($formModel->invoice_number) ?></p>
            <p class="description"><?= __("Created") ?>: <?= $formModel->created_at->toFormattedDateString() ?></p>
        </div>
    <?php endif ?>

    <?php if ($formModel->user): ?>
        <div class="scoreboard-item title-value">
            <h4><?= __("User") ?></h4>
            <p>
                <a href="<?= Backend::url('user/users/preview/'.$formModel->user_id) ?>">
                    <?= e($formModel->user->full_name) ?>
                </a>
            </p>
            <p class="description"><?= __("Email") ?>: <?= Html::mailto($formModel->user->email) ?></a></p>
        </div>
    <?php endif ?>

    <div class="scoreboard-item title-value">
        <h4><?= __("Subtotal") ?></h4>
        <p><?= Currency::format($formModel->subtotal ?: 0) ?></p>
        <p class="description">
            <?= __("Discounts") ?>: <?= Currency::format($formModel->discount ?: 0) ?>
        </p>
    </div>

    <div class="scoreboard-item title-value">
        <h4><?= __("Total") ?></h4>
        <p><?= Currency::format($formModel->total) ?></p>
        <p class="description">
            <?= __("Tax") ?>: <?= Currency::format($formModel->tax) ?>
        </p>
    </div>
</div>
