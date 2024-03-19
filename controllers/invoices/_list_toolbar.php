<div data-control="toolbar">
    <?= Ui::button("New Invoice", 'responsiv/pay/invoices/create')
        ->icon('icon-plus')
        ->primary() ?>

    <div class="toolbar-divider"></div>

    <?= Ui::popupButton("Change Status", 'onLoadChangeInvoiceStatusForm')
        ->listCheckedTrigger()
        ->listCheckedRequest()
        ->size(500)
        ->icon('ph ph-arrow-fat-lines-right')
        ->secondary() ?>

    <?=
        /**
         * @event pay.invoices.extendListToolbar
         * Fires when product list toolbar is rendered.
         *
         * Example usage:
         *
         *     Event::listen('pay.invoices.extendListToolbar', function (
         *         (Shop\Controllers\Products) $controller
         *     ) {
         *         return $controller->makePartial('~/path/to/partial');
         *     });
         *
         */
        $this->fireViewEvent('pay.invoices.extendListToolbar');
    ?>
</div>
