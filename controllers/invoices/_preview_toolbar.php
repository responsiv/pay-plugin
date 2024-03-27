<?php
    $statusCode = $formModel->status_code;
?>
<div class="control-toolbar form-toolbar" data-control="toolbar">
    <?= Ui::button("Back", 'responsiv/pay/invoices')
        ->icon('icon-arrow-left')
        ->outline() ?>

    <div class="toolbar-divider"></div>

    <?php if ($statusCode === 'draft'): ?>
        <?= Ui::popupButton("Approve", 'onLoadChangeInvoiceStatusForm')
            ->ajaxData(['invoice_id' => $formModel->id, 'status_preset' => 'approved'])
            ->size(500)
            ->icon('icon-check')
            ->outline() ?>
    <?php elseif ($statusCode === 'approved'): ?>
        <?= Ui::popupButton("Add Payment", 'onLoadChangeInvoiceStatusForm')
            ->ajaxData(['invoice_id' => $formModel->id, 'status_preset' => 'paid'])
            ->size(500)
            ->icon('icon-money')
            ->outline() ?>
    <?php elseif ($statusCode === 'paid'): ?>
        <?= Ui::popupButton("Mark Refunded", 'onLoadChangeInvoiceStatusForm')
            ->ajaxData(['invoice_id' => $formModel->id, 'status_preset' => 'void'])
            ->size(500)
            ->icon('icon-ban')
            ->outline() ?>
    <?php endif ?>

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
