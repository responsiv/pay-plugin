<?php namespace Responsiv\Pay\Controllers\Invoices;

use Flash;
use Redirect;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Pay\Models\InvoiceStatusLog;
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
     * onRestoreExtraSet
     */
    public function onChangeInvoiceStatus($productId = null)
    {
        $statusId = post('InvoiceStatusLog[status]');
        if (!$statusId) {
            throw new ValidationException(['status' => __("Please select a new status for the invoice.")]);
        }

        $isPaymentAction = (int) InvoiceStatus::getPaidStatus()?->getKey() === (int) $statusId;
        $comment = post('InvoiceStatusLog[comment]');

        $processed = 0;
        $invoices = $this->getInvoiceStatusInvoicesFromPost();
        foreach ($invoices as $invoice) {
            try {
                if ($isPaymentAction && $invoice->submitManualPayment($comment)) {
                    $processed++;
                }
                elseif ($invoice->updateInvoiceStatus($statusId, $comment)) {
                    $processed++;
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
        $fields = '$/responsiv/pay/models/invoicestatuslog/fields.yaml';

        $statusLog = new InvoiceStatusLog;

        $config = $this->makeConfig($fields);
        $config->arrayName = 'InvoiceStatusLog';
        $config->model = $statusLog;
        $widget = $this->makeWidget(\Backend\Widgets\Form::class, $config);
        $widget->bindToController();

        if (($preset = post('status_preset')) && ($statusObj = InvoiceStatus::findByCode($preset))) {
            $widget->getField('status')->value($statusObj->id)->readOnly();
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
            case 'void':
                return "Void Invoice";
            default:
                return "Change Invoice Status";
        }
    }
}
