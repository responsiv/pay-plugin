<?php namespace Responsiv\Pay\Models\Invoice;

use Mail;
use Responsiv\Pay\Models\InvoiceTemplate;
use ApplicationException;

/**
 * HasInvoiceEmail adds email sending support to the Invoice model.
 */
trait HasInvoiceEmail
{
    /**
     * sendInvoiceEmail sends the invoice to the customer email, optionally
     * with a PDF attachment. Accepts an options array:
     *
     * - `recipient`: override the recipient email address
     * - `subject`: override the email subject line
     * - `message`: override the email body text
     * - `attach_pdf`: whether to attach the PDF (default true)
     * - `send_copy`: send a copy to `copy_email`
     * - `copy_email`: the email address to send a copy to
     */
    public function sendInvoiceEmail(array $options = []): void
    {
        $recipient = $options['recipient'] ?? $this->email ?: $this->user?->email;
        if (!$recipient) {
            throw new ApplicationException(__("No email address found for this invoice."));
        }

        $subject = $options['subject'] ?? null;
        $customMessage = $options['message'] ?? null;
        $attachPdf = $options['attach_pdf'] ?? true;
        $sendCopy = $options['send_copy'] ?? false;
        $copyEmail = $options['copy_email'] ?? null;

        // Generate PDF if needed
        $pdfContent = null;
        $fileName = null;
        if ($attachPdf) {
            $template = $this->template ?: InvoiceTemplate::getDefault();
            if (!$template) {
                throw new ApplicationException(__("No invoice template found."));
            }

            $pdfContent = $template->renderInvoicePdf($this);
            $fileName = 'invoice-' . $this->getUniqueId() . '.pdf';
        }

        $vars = [
            'invoice' => $this,
            'customMessage' => $customMessage,
        ];

        Mail::sendTo($recipient, 'pay:invoice', $vars, function ($message) use ($subject, $pdfContent, $fileName, $sendCopy, $copyEmail) {
            if ($subject) {
                $message->subject($subject);
            }

            if ($pdfContent) {
                $message->attachData($pdfContent, $fileName, ['mime' => 'application/pdf']);
            }

            if ($sendCopy && $copyEmail) {
                $message->bcc($copyEmail);
            }
        });

        $this->sent_at = $this->freshTimestamp();
        $this->save();
    }
}
