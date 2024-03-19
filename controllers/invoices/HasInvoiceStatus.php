<?php namespace Responsiv\Pay\Controllers\Invoices;

use Flash;
use Redirect;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Backend\Models\UserPreference;
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
            $this->vars['formWidget'] = $this->getInvoiceStatusFormWidget();
            $this->vars['invoiceIds'] = (array) post('checked');
            $this->vars['invoiceId'] = post('invoice_id');
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
        $sendNotifications = (bool) post('InvoiceStatusLog[send_notifications]');
        if (!$statusId) {
            throw new ValidationException(['status' => __("Please select a new status for the invoice.")]);
        }

        $processed = 0;
        $invoices = $this->getInvoiceStatusInvoicesFromPost();
        foreach ($invoices as $invoice) {
            try {
                if ($invoice->status_id == $statusId) {
                    throw new ApplicationException(__("Invoice #:id new status matches the current status", ['id' => $invoice->id]));
                }

                InvoiceStatusLog::createRecord(
                    $statusId,
                    $invoice,
                    post('InvoiceStatusLog[comment]'),
                    $sendNotifications
                );

                $processed++;
            }
            catch (Exception $ex) {
                Flash::error($ex->getMessage());
                break;
            }
        }

        if ($processed) {
            UserPreference::forUser()->set('pay::invoices.change_status_notify', $sendNotifications);
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
        $statusLog->send_notifications = UserPreference::forUser()->get('pay::invoices.change_status_notify', false);

        $config = $this->makeConfig($fields);
        $config->arrayName = 'InvoiceStatusLog';
        $config->model = $statusLog;
        $widget = $this->makeWidget(\Backend\Widgets\Form::class, $config);
        $widget->bindToController();

        return $widget;
    }
}
