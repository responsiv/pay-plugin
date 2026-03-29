<?= __(":name paid an invoice for :total", [
    'name' => $record->actor_user_name_linked,
    'total' => Currency::format($record->invoice_total ?: 0),
]) ?>
