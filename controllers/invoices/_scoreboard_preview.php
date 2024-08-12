<div class="scoreboard-item title-value">
    <h4><?= __("Invoice") ?></h4>
    <p><?= e($formModel->invoice_number) ?></p>
    <p class="description"><?= __("Created") ?>: <?= $formModel->created_at->toFormattedDateString() ?></p>
</div>

<?php if ($formModel->status): ?>
    <div class="scoreboard-item title-value">
        <h4><?= __("Status") ?></h4>
        <p>
            <span class="scoreboard-colorpicker" style="--background-color:<?= e($formModel->status->color_background) ?>;--foreground-color:<?= e($formModel->status->color_background) ?>"></span>
            <?= $formModel->status->name ?>
        </p>
        <p class="description"><?= __("Since") ?>: <?= $formModel->updated_at->toFormattedDateString() ?></p>
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
        <p class="description"><?= __("Email") ?>: <?= Html::mailto($formModel->email ?: $formModel->user->email) ?></a></p>
    </div>
<?php endif ?>

<div class="scoreboard-item title-value">
    <h4><?= __("Total") ?></h4>
    <p><?= $formModel->currency?->formatCurrency($formModel->total) ?: Currency::format($formModel->total) ?></p>
    <p class="description">
        <?= __("Tax") ?>: <?= $formModel->currency?->formatCurrency($formModel->tax) ?: Currency::format($formModel->tax) ?>
    </p>
</div>
