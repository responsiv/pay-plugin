<?php namespace Responsiv\Pay\Controllers\Invoices;

use Flash;
use BackendAuth;
use Responsiv\Pay\Models\Invoice;
use System\Classes\MailManager;
use System\Models\MailTemplate;
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
     * getInvoiceEmailDefaultMessage renders the default email body
     * from the mail template so developers can customize it.
     */
    protected function getInvoiceEmailDefaultMessage($invoice): string
    {
        $template = MailTemplate::findOrMakeTemplate('pay:invoice');
        if (!$template) {
            return '';
        }

        return trim(MailManager::instance()->renderText(
            $template->content_html,
            ['invoice' => $invoice]
        ));
    }
}
