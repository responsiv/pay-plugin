<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('responsiv/pay/invoices') ?>">Invoices</a></li>
        <li class="breadcrumb-item active"><?= e(trans($this->pageTitle)) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>

    <div class="scoreboard">
        <div data-control="toolbar">
            <?= $this->makePartial('preview_scoreboard') ?>
        </div>
    </div>

    <div class="form-buttons buttons-offset">
        <div class="loading-indicator-container">
            <?= $this->makePartial('preview_toolbar') ?>
        </div>
    </div>

    <div class="control-tabs content-tabs tabs-offset" data-control="tab">
        <ul class="nav nav-tabs">
            <li class="active"><a href="#invoiceTemplate">Invoice</a></li>
            <li><a href="#statusLog">Status History</a></li>
            <li><a href="#typeLog">Pay Attempts</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active">
                <div class="padded-container">
                    <?= $this->makePartial('invoice_iframe') ?>
                </div>
            </div>
            <div class="tab-pane">
                <div class="relation-flush">
                    <?= $this->relationRender('status_log') ?>
                </div>
            </div>
            <div class="tab-pane">
                <div class="relation-flush">
                    <?= $this->relationRender('payment_log') ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <p class="flash-message static error"><?= e(trans($this->fatalError)) ?></p>
    <p><a href="<?= Backend::url('responsiv/pay/invoices') ?>" class="btn btn-default">Return to Invoices List</a></p>

<?php endif ?>
