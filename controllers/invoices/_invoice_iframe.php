<?php
    $invoice = $formModel;
    $template = $invoice->template;
?>
<?php if ($template): ?>

    <iframe id="<?= $this->getId('invoiceIframe') ?>" style="width: 100%; height: 500px; padding: 0 10px; box-sizing: border-box" frameborder="0"></iframe>
    <template id="<?= $this->getId('invoiceContents') ?>">
        <?= $template->renderInvoice($invoice) ?>
    </template>
    <script>
        (function($){
            var invoiceContents,
                invoiceFrame = $('#<?= $this->getId('invoiceIframe') ?>')

            $(document).render(function(){
                if (!invoiceFrame.is(':visible')) {
                    return;
                }

                var templateEl = document.getElementById('<?= $this->getId('invoiceContents') ?>');
                invoiceContents = templateEl.innerHTML;
                var iframe = invoiceFrame[0];
                iframe.srcdoc = invoiceContents;
                iframe.onload = function() {
                    var body = iframe.contentWindow.document.body;
                    if (body) {
                        invoiceFrame.height(body.scrollHeight + 100);
                    }
                };
            })

            invoiceFrame.on('print.invoice', function(){
               var printWindow = window.open('','','left=0,top=0,width=950,height=500,toolbar=0,scrollbars=0,status=0');
               printWindow.document.write(invoiceContents);
               printWindow.document.close();
               printWindow.focus();
               printWindow.print();
               printWindow.onafterprint = window.close;
            })
        })(window.jQuery);
    </script>

<?php else: ?>
    <p class="flash-message static error">Invoice template not found</p>
<?php endif ?>
