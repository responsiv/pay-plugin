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

<div class="control-tabs content-tabs tabs-inset" data-control="tab">
    <ul class="nav nav-tabs">
        <li class="active"><a href="#invoiceTemplate"><?= __("Invoice") ?></a></li>
        <li><a href="#statusLog"><?= __("Status History") ?></a></li>
        <li><a href="#typeLog"><?= __("Payment Attempts") ?></a></li>
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
    </div>
</div>

<?php endif ?>
