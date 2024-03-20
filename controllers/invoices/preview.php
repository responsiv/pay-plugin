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
    <div class="control-toolbar form-toolbar" data-control="toolbar">
        <?= Ui::button("Back", 'responsiv/pay/invoices')
            ->icon('icon-arrow-left')
            ->outline() ?>

        <div class="toolbar-divider"></div>

        <?= Ui::popupButton("Change Status", 'onLoadChangeInvoiceStatusForm')
            ->ajaxData(['order_id' => $formModel->id])
            ->size(500)
            ->icon('ph ph-arrow-fat-lines-right')
            ->outline() ?>

        <?= Ui::button("Edit", 'responsiv/pay/invoices/update/'.$formModel->id)
            ->icon('icon-pencil')
            ->outline()
            ->primary() ?>

        <div class="toolbar-divider"></div>

        <a
            href="javascript:;"
            onclick="$('#<?= $this->getId('invoiceIframe') ?>').trigger('print.invoice')"
            class="btn btn-outline-default">
            <i class="icon-print"></i>
            Print Invoice
        </a>
        <? /* @todo
        <a
            href="#"
            class="btn btn-default">
            Email invoice
        </a>
        */ ?>

        <div class="toolbar-divider"></div>

        <?= Ui::ajaxButton("Delete", 'onDelete')
            ->icon('icon-delete')
            ->confirmMessage("Are you sure?")
            ->loadingMessage("Deleting invoice...")
            ->outline()
            ->danger() ?>
    </div>
</div>

<div class="control-tabs content-tabs tabs-inset" data-control="tab">
    <ul class="nav nav-tabs">
        <li class="active"><a href="#invoiceTemplate"><?= __("Invoice") ?></a></li>
        <li><a href="#statusLog"><?= __("Status History") ?></a></li>
        <li><a href="#typeLog"><?= __("Pay Attempts") ?></a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane active">
            <div class="py-4">
                <?= $this->makePartial('invoice_iframe') ?>
            </div>
        </div>
        <div class="tab-pane">
            <h4 class="my-3 fw-normal">Status Log</h4>
            <?= $this->relationRender('status_log') ?>
        </div>
        <div class="tab-pane">
            <h4 class="my-3 fw-normal">Payment Log</h4>
            <?= $this->relationRender('payment_log') ?>
        </div>
    </div>
</div>

<?php endif ?>
