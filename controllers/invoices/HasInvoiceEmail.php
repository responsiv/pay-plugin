<?php namespace Responsiv\Pay\Controllers\Invoices;

use Flash;
use BackendAuth;
use Responsiv\Pay\Models\Invoice;
use Exception;

/**
 * HasInvoiceEmail in the controller
 */
trait HasInvoiceEmail
{
    /**
     * onLoadSendInvoiceEmailForm
     */
    public function onLoadSendInvoiceEmailForm()
    {
        try {
            $invoiceId = post('invoice_id');
            $invoice = Invoice::findOrFail($invoiceId);

            $this->vars['invoice'] = $invoice;
            $this->vars['invoiceId'] = $invoiceId;
            $this->vars['recipientEmail'] = $invoice->email ?: $invoice->user?->email;
            $this->vars['defaultSubject'] = __("Invoice :number", [
                'number' => $invoice->invoice_number
            ]);
            $this->vars['defaultMessage'] = $this->getInvoiceEmailDefaultMessage($invoice);
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->makePartial('invoice_email_form');
    }

    /**
     * onSendInvoiceEmail
     */
    public function onSendInvoiceEmail()
    {
        $invoiceId = post('invoice_id');
        $invoice = Invoice::findOrFail($invoiceId);

        $invoice->sendInvoiceEmail([
            'recipient' => post('recipient_email'),
            'subject' => post('subject'),
            'message' => post('message'),
            'attach_pdf' => (bool) post('attach_pdf'),
            'send_copy' => (bool) post('send_copy'),
            'copy_email' => BackendAuth::getUser()?->email,
        ]);

        Flash::success(__("Invoice emailed successfully."));
    }

    /**
     * getInvoiceEmailDefaultMessage returns the default email body
     */
    protected function getInvoiceEmailDefaultMessage($invoice): string
    {
        return __("Hello :name\n\nPlease find your invoice attached.\n\nThank you for your business.", [
            'name' => $invoice->first_name
        ]);
    }
}
