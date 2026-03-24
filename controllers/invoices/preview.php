<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('responsiv/pay/invoices') ?>">Invoices</a></li>
        <li class="breadcrumb-item active"><?= e(trans($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if ($this->fatalError): ?>
    <?= $this->formRenderDesign() ?>
<?php else: ?>

<div class="scoreboard">
    <div data-control="toolbar">
        <?= $this->makePartial('scoreboard_preview') ?>
    </div>
</div>

<div class="loading-indicator-container mb-3">
    <?= $this->makePartial('preview_toolbar') ?>
</div>

<?php
    $hasCreditApplications = $formModel->credit_applications->count() > 0;
    $hasCreditNotesFrom = $formModel->credit_notes_from->count() > 0;
    $hasCreditTab = $hasCreditApplications || $hasCreditNotesFrom;
?>
<div class="control-tabs content-tabs tabs-inset" data-control="tab">
    <ul class="nav nav-tabs">
        <li class="active"><a href="#invoiceTemplate"><?= __("Invoice") ?></a></li>
        <li><a href="#statusLog"><?= __("Status History") ?></a></li>
        <li><a href="#typeLog"><?= __("Payment Attempts") ?></a></li>
        <?php if ($hasCreditTab): ?>
            <li><a href="#creditNotes"><?= __("Credit Notes") ?></a></li>
        <?php endif ?>
    </ul>
    <div class="tab-content">
        <div class="tab-pane active">
            <div class="p-4 my-4 border">
                <?= $this->makePartial('invoice_iframe') ?>
            </div>
        </div>
        <div class="tab-pane">
            <h4 class="my-3 fw-normal"><?= __("Status Log") ?></h4>
            <?= $this->relationRender('status_log') ?>
        </div>
        <div class="tab-pane">
            <h4 class="my-3 fw-normal"><?= __("Payment Log") ?></h4>
            <?= $this->relationRender('payment_log') ?>
        </div>
        <?php if ($hasCreditTab): ?>
            <div class="tab-pane">
                <?php if ($hasCreditApplications): ?>
                    <h4 class="my-3 fw-normal"><?= __("Credit Applied to This Invoice") ?></h4>
                    <?= $this->relationRender('credit_applications') ?>
                <?php endif ?>
                <?php if ($hasCreditNotesFrom): ?>
                    <h4 class="my-3 fw-normal"><?= __("Credit Notes Issued from This Invoice") ?></h4>
                    <div class="list-widget-container mx-0">
                        <div class="list-widget list-scrollable-container list-flush">
                            <div class="control-list">
                                <table class="table data" data-control="rowlink">
                                    <thead>
                                        <tr>
                                            <th><span><?= __("ID") ?></span></th>
                                            <th><span><?= __("Type") ?></span></th>
                                            <th><span><?= __("Amount") ?></span></th>
                                            <th><span><?= __("Reason") ?></span></th>
                                            <th><span><?= __("Issued") ?></span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($formModel->credit_notes_from as $note): ?>
                                            <tr>
                                                <td>
                                                    <?= e($note->id) ?>
                                                </td>
                                                <td>
                                                    <?= e(__(ucfirst($note->type))) ?>
                                                </td>
                                                <td>
                                                    <?= $formModel->getCurrencyObject()?->formatCurrency($note->amount) ?: Currency::format($note->amount) ?>
                                                </td>
                                                <td>
                                                    <?= e($note->reason) ?>
                                                </td>
                                                <td>
                                                    <?= e($note->issued_at?->toFormattedDateString()) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        <?php endif ?>
    </div>
</div>

<?php endif ?>
