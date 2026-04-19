<?= Form::open(['id' => 'invoiceEmailForm']) ?>

    <div class="modal-header">
        <h4 class="modal-title">
            <?= e(__("Email Invoice")) ?>
        </h4>
        <button type="button" class="btn-close" data-dismiss="popup"></button>
    </div>

    <?php if (!$this->fatalError): ?>
        <input type="hidden" name="invoice_id" value="<?= e($invoiceId) ?>" />

        <div class="modal-body">
            <?php if ($invoice->sent_at): ?>
                <p class="text-muted">
                    <?= __("This invoice was last emailed on :date.", [
                        'date' => $invoice->sent_at->toFormattedDateString()
                    ]) ?>
                </p>
            <?php endif ?>

            <div class="form-group text-field span-full">
                <label class="form-label">
                    <?= __("To") ?>
                </label>
                <input
                    type="email"
                    name="recipient_email"
                    value="<?= e($recipientEmail) ?>"
                    class="form-control"
                    required />
            </div>

            <div class="form-group text-field span-full">
                <label class="form-label">
                    <?= __("Subject") ?>
                </label>
                <input
                    type="text"
                    name="subject"
                    value="<?= e($defaultSubject) ?>"
                    class="form-control" />
            </div>

            <div class="form-group textarea-field span-full" data-field-name="message">
                <label class="form-label">
                    <?= __("Message") ?>
                </label>
                <textarea
                    name="message"
                    class="form-control"
                    rows="5"><?= e($defaultMessage) ?></textarea>
            </div>

            <div class="form-group checkboxlist-field span-left">
                <div class="form-check">
                    <input
                        type="hidden"
                        name="attach_pdf"
                        value="0" />
                    <input
                        type="checkbox"
                        name="attach_pdf"
                        value="1"
                        id="attachPdfCheckbox"
                        class="form-check-input"
                        checked />
                    <label class="form-check-label" for="attachPdfCheckbox">
                        <?= __("Attach PDF") ?>
                    </label>
                </div>
            </div>

            <div class="form-group checkboxlist-field span-right">
                <div class="form-check">
                    <input
                        type="hidden"
                        name="send_copy"
                        value="0" />
                    <input
                        type="checkbox"
                        name="send_copy"
                        value="1"
                        id="sendCopyCheckbox"
                        class="form-check-input" />
                    <label class="form-check-label" for="sendCopyCheckbox">
                        <?= __("Send myself a copy") ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <?= Ui::ajaxButton("Send Email", 'onSendInvoiceEmail')
                ->loadingPopup()
                ->primary() ?>

            <span class="btn-text">
                <span class="button-separator">
                    <?= __("or") ?>
                </span>
                <?= Ui::button(__("Cancel"))->dismissPopup()->textLink() ?>
            </span>
        </div>

    <?php else: ?>

        <div class="modal-body">
            <p class="flash-message static error">
                <?= e(trans($this->fatalError)) ?>
            </p>
        </div>
        <div class="modal-footer">
            <?= Ui::button(__("Close"))->dismissPopup() ?>
        </div>

    <?php endif ?>

<?= Form::close() ?>
