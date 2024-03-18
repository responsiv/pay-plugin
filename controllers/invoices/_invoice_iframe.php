<?php
    $invoice = $formModel;
    $template = $invoice->template;
?>
<?php if ($template): ?>

    <iframe id="<?= $this->getId('invoiceIframe') ?>" style="width: 100%; height: 500px; padding: 0 10px" frameborder="0"></iframe>
    <script type="text/template" id="<?= $this->getId('invoiceContents') ?>">
        <?= $template->renderInvoice($invoice) ?>
    </script>
    <script>
        (function($){
            var invoiceContents,
                invoiceFrame = $('#<?= $this->getId('invoiceIframe') ?>')

            $(document).render(function(){
                var frameContents = invoiceFrame.contents().find('html')
                invoiceContents = $('#<?= $this->getId('invoiceContents') ?>').html()
                frameContents.html(invoiceContents)
                invoiceFrame.height(frameContents.height() + 100)
            })

            invoiceFrame.on('print.invoice', function(){
               var printWindow = window.open('','','left=0,top=0,width=950,height=500,toolbar=0,scrollbars=0,status=0')
               printWindow.document.write(invoiceContents)
               printWindow.document.close()
               printWindow.focus()
               printWindow.print()
               printWindow.close()
            })
        })(window.jQuery);
    </script>

<?php else: ?>
    <p class="flash-message static error">Invoice template not found</p>
<?php endif ?>