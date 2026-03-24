<?php
    $activeCurrencyCode = Currency::getActiveCode();
    $creditBalance = \Responsiv\Pay\Models\CreditNote::getBalanceForUser($formModel, $activeCurrencyCode);
    $creditHistory = \Responsiv\Pay\Models\CreditNote::getHistoryForUser($formModel, $activeCurrencyCode);
?>
<div id="userCreditTab">
    <div class="d-flex justify-content-between align-items-center my-3">
        <h4 class="fw-normal mb-0">
            <?= __("Credit Balance") ?>:
            <?= Currency::format($creditBalance) ?>
        </h4>
        <?= Ui::popupButton("Adjust Credit", 'onLoadAdjustCreditForm')
            ->ajaxData(['user_id' => $formModel->id])
            ->size(500)
            ->icon('icon-plus')
            ->outline()
            ->primary() ?>
    </div>

    <div class="list-widget-container mx-0">
        <div class="list-widget list-scrollable-container list-flush">
            <div class="control-list">
                <table class="table data" data-control="rowlink">
                    <thead>
                        <tr>
                            <th><span><?= __("Date") ?></span></th>
                            <th><span><?= __("Type") ?></span></th>
                            <th><span><?= __("Reason") ?></span></th>
                            <th><span><?= __("Amount") ?></span></th>
                            <th><span><?= __("Source") ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($creditHistory)): ?>
                            <?php foreach ($creditHistory as $note): ?>
                                <tr>
                                    <td>
                                        <?= e($note->issued_at?->toFormattedDateString()) ?>
                                    </td>
                                    <td>
                                        <?= e(__(ucfirst($note->type))) ?>
                                    </td>
                                    <td>
                                        <?= e($note->reason) ?>
                                    </td>
                                    <td>
                                        <?php if ($note->type === 'debit'): ?>
                                            -<?= Currency::format($note->amount) ?>
                                        <?php else: ?>
                                            <?= Currency::format($note->amount) ?>
                                        <?php endif ?>
                                    </td>
                                    <td>
                                        <?php if ($note->invoice_id): ?>
                                            <a href="<?= Backend::url('responsiv/pay/invoices/preview/'.$note->invoice_id) ?>">
                                                <?= __("Invoice") ?> #<?= e($note->invoice_id) ?>
                                            </a>
                                        <?php elseif ($note->issued_by_user): ?>
                                            <?= e($note->issued_by_user->full_name) ?>
                                        <?php else: ?>
                                            <?= __("System") ?>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php else: ?>
                            <tr class="no-data">
                                <td colspan="100" class="nolink">
                                    <p class="no-data">
                                        <?= __("No credit history found for this customer.") ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
