<?= Form::open(['id' => 'disableForm']) ?>
    <div class="modal-header flex-row-reverse">
        <button type="button" class="close" data-dismiss="popup">&times;</button>
        <h4 class="modal-title"><?= __("Change Invoice Status") ?></h4>
    </div>
    <div class="modal-body">

        <?php if ($this->fatalError): ?>
            <p class="flash-message static error"><?= $fatalError ?></p>
        <?php endif ?>

        <p><?= e(__("Current status: :name", ['name'=> $currentStatus])) ?></p>

        <div class="form-preview">
            <?= $widget->render() ?>
        </div>

    </div>

    <div class="modal-footer">
        <button
            type="submit"
            class="btn btn-primary"
            data-request="onChangeStatus"
            data-request-confirm="Are you sure?"
            data-popup-load-indicator>
            <?= e(trans('backend::lang.form.save')) ?>
        </button>
        <button
            type="button"
            class="btn btn-default"
            data-dismiss="popup">
            <?= e(trans('backend::lang.form.cancel')) ?>
        </button>
    </div>
<?= Form::close() ?>