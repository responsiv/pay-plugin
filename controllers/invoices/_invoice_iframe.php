<?php
    $invoice = $formModel;
    $template = $invoice->template;
?>
<?php if ($template): ?>

    <iframe id="<?= $this->getId('invoiceIframe') ?>" style="width: 100%; height: 500px; padding: 0 10px" frameborder="0"></iframe>
    <template id="<?= $this->getId('invoiceContents') ?>">
        <?= $template->renderInvoice($invoice) ?>
    </template>
    <script>
        oc.pageReady().then(function() {
            var invoiceFrame = document.getElementById('<?= $this->getId('invoiceIframe') ?>');
            if (!invoiceFrame || invoiceFrame.offsetParent === null) {
                return;
            }

            var templateEl = document.getElementById('<?= $this->getId('invoiceContents') ?>');
            var invoiceContents = templateEl.innerHTML;

            invoiceFrame.srcdoc = invoiceContents;
            invoiceFrame.onload = function() {
                var body = invoiceFrame.contentWindow.document.body;
                if (body) {
                    invoiceFrame.style.height = (body.scrollHeight + 100) + 'px';
                }
            };

            invoiceFrame.addEventListener('print', function() {
                var printWindow = window.open('', '', 'left=0,top=0,width=950,height=500,toolbar=0,scrollbars=0,status=0');
                printWindow.document.write(invoiceContents);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
                setTimeout(function() { printWindow.close(); }, 0);
            });
        });
    </script>

<?php else: ?>
    <p class="flash-message static error">Invoice template not found</p>
<?php endif ?>
