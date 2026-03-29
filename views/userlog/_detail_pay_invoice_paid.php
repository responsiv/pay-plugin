<?php
$invoiceUrl = Backend::url("responsiv/pay/invoices/preview/{$record->invoice_id}");
$invoiceLink = '<a href="'.e($invoiceUrl).'">'.__("Invoice #:id", ['id' => $record->invoice_id]).'</a>';
?>
<?= __(":name paid :invoice for :total", [
    'name' => $record->actor_user_name_linked,
    'invoice' => $invoiceLink,
    'total' => Currency::format($record->invoice_total ?: 0),
]) ?>
