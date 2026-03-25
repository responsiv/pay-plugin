<?php namespace Responsiv\Pay\Controllers\Invoices;

use Flash;
use Currency;
use Redirect;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Pay\Models\CreditNote;
use Responsiv\Pay\Models\Setting as PaySetting;
use ApplicationException;
use ValidationException;
use Exception;

/**
 * HasInvoiceStatus in the controller
 */
trait HasInvoiceStatus
{
    /**
     * onLoadChangeInvoiceStatusForm
     */
    public function onLoadChangeInvoiceStatusForm()
    {
        try {
            $this->vars['popupTitle'] = $this->getInvoiceStatusPopupTitle();
            $this->vars['formWidget'] = $this->getInvoiceStatusFormWidget();
            $this->vars['invoiceIds'] = (array) post('checked');
            $this->vars['invoiceId'] = post('invoice_id');
            $this->vars['statusPreset'] = post('status_preset');
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->makePartial('invoice_status_manage_form');
    }

    /**
     * onChangeInvoiceStatus
     */
    public function onChangeInvoiceStatus($productId = null)
    {
        $statusId = post('InvoiceStatusLog[status]');
        if (!$statusId) {
            throw new ValidationException(['status' => __("Please select a new status for the invoice.")]);
        }

        $isPaymentAction = (int) InvoiceStatus::getPaidStatus()?->getKey() === (int) $statusId;
        $isRefundAction = (int) InvoiceStatus::getRefundedStatus()?->getKey() === (int) $statusId;
        $comment = post('InvoiceStatusLog[comment]');

        // Refund-specific fields (from preset flow)
        $issueCredit = post('InvoiceStatusLog[issue_credit]');
        $refundAmountInput = post('InvoiceStatusLog[refund_amount]');

        $processed = 0;
        $invoices = $this->getInvoiceStatusInvoicesFromPost();
        foreach ($invoices as $invoice) {
            try {
                if ($isPaymentAction && $invoice->submitManualPayment($comment)) {
                    $processed++;
                }
                elseif ($invoice->updateInvoiceStatus($statusId, $comment)) {
                    $processed++;

                    // Issue credit note when refunding
                    if ($isRefundAction) {
                        $this->processRefundCredit(
                            $invoice,
                            $issueCredit,
                            $refundAmountInput,
                            $comment
                        );
                    }
                }
            }
            catch (Exception $ex) {
                Flash::error($ex->getMessage());
                break;
            }
        }

        if ($processed) {
            Flash::success(__("Updated the invoice status successfully"));
        }

        if (post('invoice_id')) {
            return Redirect::refresh();
        }

        return $this->listRefresh();
    }

    /**
     * processRefundCredit issues a credit note when store credit is requested
     */
    protected function processRefundCredit($invoice, $issueCredit, $refundAmountInput, $comment)
    {
        // Bulk flow (no checkbox shown) does not issue credit
        if ($issueCredit === null) {
            $issueCredit = false;
        }

        if (!$issueCredit) {
            return;
        }

        // Convert display amount to base value, or use full invoice total
        $refundAmount = $refundAmountInput !== null
            ? Currency::toBaseValue($refundAmountInput)
            : $invoice->total;

        if ($refundAmount > 0 && $invoice->user) {
            CreditNote::issueRefund(
                $invoice->user,
                $invoice,
                $refundAmount,
                $comment ?: __("Refund for invoice #:id", ['id' => $invoice->id])
            );
        }
    }

    /**
     * getInvoiceStatusInvoicesFromPost
     */
    protected function getInvoiceStatusInvoicesFromPost()
    {
        $invoiceIds = (array) post('invoice_id', post('checked'));
        $invoices = Invoice::whereIn('id', $invoiceIds)->get();

        foreach ($invoices as $invoice) {
            if (!in_array($invoice->id, $invoiceIds)) {
                throw new ApplicationException(__("Invoice #:id not found", ['id' => $invoice->id]));
            }
        }

        return $invoices;
    }

    /**
     * getInvoiceStatusFormWidget
     */
    protected function getInvoiceStatusFormWidget()
    {
        $preset = post('status_preset');
        $isRefund = $preset === 'refunded';

        $fields = $isRefund
            ? '$/responsiv/pay/models/invoicestatuslog/fields_refund.yaml'
            : '$/responsiv/pay/models/invoicestatuslog/fields.yaml';

        $statusLog = new InvoiceStatusLog;

        $config = $this->makeConfig($fields);
        $config->arrayName = 'InvoiceStatusLog';
        $config->model = $statusLog;
        $widget = $this->makeWidget(\Backend\Widgets\Form::class, $config);
        $widget->bindToController();

        if ($preset && ($statusObj = InvoiceStatus::findByCode($preset))) {
            $widget->getField('status')->value($statusObj->id)->readOnly();
        }

        // Set refund amount default to invoice total, hide credit when disabled
        if ($isRefund) {
            if (!PaySetting::isCreditEnabled()) {
                $widget->getField('issue_credit')->hidden();
                $widget->getField('refund_amount')->hidden();
            }
            elseif (($invoiceId = post('invoice_id')) && ($invoice = Invoice::find($invoiceId))) {
                $widget->getField('refund_amount')->value($invoice->total);
            }
        }

        return $widget;
    }

    /**
     * getInvoiceStatusPopupTitle returns a unique title for a status preset
     */
    protected function getInvoiceStatusPopupTitle()
    {
        switch (post('status_preset')) {
            case 'paid':
                return "Add Payment";
            case 'approved':
                return "Approve Invoice";
            case 'refunded':
                return "Refund Invoice";
            case 'void':
                return "Void Invoice";
            default:
                return "Change Invoice Status";
        }
    }
}
