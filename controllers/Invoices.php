<?php namespace Responsiv\Pay\Controllers;

use Flash;
use Backend;
use Redirect;
use BackendMenu;
use Backend\Classes\Controller;
use Responsiv\Currency\Models\Currency as CurrencyModel;
use Responsiv\Pay\Models\InvoiceStatusLog;

/**
 * Invoices Back-end Controller
 */
class Invoices extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'invoices');
    }

    public function listExtendQuery($query)
    {
        return $query->applyNotThrowaway();
    }

    public function preview($recordId = null, $context = null)
    {
        $this->bodyClass = 'slim-container';
        $this->vars['currency'] = CurrencyModel::getPrimary();
        return $this->asExtension('FormController')->preview($recordId, $context);
    }

    public function preview_onDelete($recordId = null)
    {
        return $this->asExtension('FormController')->update_onDelete($recordId);
    }

    public function preview_onLoadChangeStatusForm($recordId = null)
    {
        try {
            $invoice = $this->formFindModelObject($recordId);
            $this->vars['currentStatus'] = isset($invoice->status->name) ? $invoice->status->name : '???';
            $this->vars['widget'] = $this->makeStatusFormWidget($invoice);
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->makePartial('change_status_form');
    }

    public function preview_onChangeStatus($recordId = null)
    {
        $invoice = $this->formFindModelObject($recordId);

        $widget = $this->makeStatusFormWidget($invoice);

        $data = $widget->getSaveData();

        $markPaid = array_get($data, 'mark_paid') && !$invoice->isPaymentProcessed();

        if ($markPaid) {
            $paymentComment = sprintf(
                'Payment taken by %s (#%s)',
                $this->user->full_name,
                $this->user->id
            );

            $invoice->submitManualPayment($paymentComment);
        }

        InvoiceStatusLog::createRecord($data['status'], $invoice, $data['comment']);

        Flash::success('Invoice status updated.');

        return Backend::redirect(sprintf('responsiv/pay/invoices/preview/%s', $invoice->id));
    }

    protected function makeStatusFormWidget($invoice)
    {
        $model = new InvoiceStatusLog;
        $model->invoice = $invoice;

        $config = $this->makeConfig('~/plugins/responsiv/pay/models/invoicestatuslog/fields.yaml');
        $config->model = $model;
        $config->arrayName = 'InvoiceStatusLog';
        $config->alias = 'statusLog';
        $config->context = 'create';

        return $this->makeWidget('Backend\Widgets\Form', $config);
    }

    public function formExtendRefreshData($host, $data)
    {
        if (empty($data['user'])) {
            return;
        }

        $host->model->user = $data['user'];
        if (!$user = $host->model->user) {
            return;
        }

        $data['first_name'] = $user->name;
        $data['last_name'] = $user->surname;
        $data['email'] = $user->email;
        $data['phone'] = $user->phone;
        $data['company'] = $user->company;
        $data['street_addr'] = $user->street_addr;
        $data['city'] = $user->city;
        $data['zip'] = $user->zip;
        $data['country'] = $user->country_id;
        $data['state'] = $user->state_id;
        return $data;
    }
}
