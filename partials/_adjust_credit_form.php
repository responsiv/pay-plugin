<?= Form::open(['id' => 'adjustCreditForm']) ?>

    <div class="modal-header">
        <h4 class="modal-title"><?= __("Adjust Credit") ?></h4>
        <button type="button" class="btn-close" data-dismiss="popup"></button>
    </div>

    <input type="hidden" name="user_id" value="<?= e($userId) ?>" />

    <div class="modal-body">
        <?= $formWidget->render() ?>
    </div>

    <div class="modal-footer">
        <?= Ui::ajaxButton(__("Submit"), 'onAdjustCredit')
            ->loadingPopup()
            ->primary() ?>

        <span class="btn-text">
            <span class="button-separator"><?= __("or") ?></span>
            <?= Ui::button(__("Cancel"))->dismissPopup()->textLink() ?>
        </span>
    </div>

<?= Form::close() ?>
