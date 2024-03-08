<?= Form::open(['id' => 'addGatewayForm']) ?>
    <div class="modal-header">
        <h4 class="modal-title"><?= __("Add Payment Method") ?></h4>
        <button type="button" class="btn-close" data-dismiss="popup"></button>
    </div>
    <div class="modal-body">
        <?php if ($this->fatalError): ?>
            <p class="flash-message static error"><?= $fatalError ?></p>
        <?php else: ?>
            <div class="control-simplelist is-selectable is-scrollable size-large" data-control="simplelist">
                <ul>
                    <?php foreach ($gateways as $gateway): ?>
                        <li>
                            <a href="<?= Backend::url('responsiv/pay/paymentmethods/create/' . $gateway->alias) ?>">
                                <h5 class="heading"><?= $gateway->name ?></h5>
                                <p class="description"><?= $gateway->description ?></p>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>
    </div>
    <div class="modal-footer">
        <?= Ui::button(__("Cancel"))->dismissPopup() ?>
    </div>
<?= Form::close() ?>
