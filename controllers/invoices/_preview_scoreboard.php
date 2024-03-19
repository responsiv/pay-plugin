<div class="scoreboard-item title-value">
    <h4>Invoice</h4>
    <p>#<?= $formModel->id ?></p>
    <p class="description">Created: <?= $formModel->created_at->toFormattedDateString() ?></p>
</div>
<?php if ($formModel->status): ?>
    <div class="scoreboard-item title-value">
        <h4>Status</h4>
        <p><?= $formModel->status->name ?></p>
        <p class="description">Since: <?= $formModel->updated_at->toFormattedDateString() ?></p>
    </div>
<?php endif ?>
<?php if ($formModel->user): ?>
    <div class="scoreboard-item title-value">
        <h4>Customer</h4>
        <p><?= $formModel->user->name ?></p>
        <p class="description">Email: <a href="mailto: <?= $formModel->user->email ?>"><?= $formModel->user->email ?></a></p>
    </div>
<?php endif ?>
<div class="scoreboard-item title-value">
    <h4>Total</h4>
    <p><?= $formModel->currency?->formatCurrency($formModel->total) ?: Currency::format($formModel->total) ?></p>
    <p class="description">Tax: <?= $formModel->currency?->formatCurrency($formModel->tax) ?: Currency::format($formModel->tax) ?></p>
</div>
