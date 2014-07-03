<?php namespace Responsiv\Pay\Controllers;

use Flash;
use Backend;
use Redirect;
use BackendMenu;
use Backend\Classes\Controller;
use Responsiv\Pay\Models\InvoiceStatusLog;

/**
 * Invoices Back-end Controller
 */
class Invoices extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController',
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'invoices');
    }

    public function preview($recordId = null, $context = null)
    {
        $this->bodyClass = 'slim-container';
        return $this->getClassExtension('Backend.Behaviors.FormController')->preview($recordId, $context);
    }

    public function preview_onDelete($recordId = null)
    {
        return $this->getClassExtension('Backend.Behaviors.FormController')->update_onDelete($recordId);
    }

    public function preview_onLoadChangeStatusForm($recordId = null)
    {
        try {
            $invoice = $this->formFindModelObject($recordId);
            $this->vars['currentStatus'] = isset($invoice->status->name) ? $invoice->status->name : '???';
            $this->vars['widget'] = $this->makeStatusFormWidget();
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->makePartial('change_status_form');
    }

    public function preview_onChangeStatus($recordId = null)
    {
        $invoice = $this->formFindModelObject($recordId);
        $widget = $this->makeStatusFormWidget();
        $data = $widget->getSaveData();
        InvoiceStatusLog::createRecord($data['status'], $invoice, $data['comment']);
        Flash::success('Invoice status updated successfully');
        return Redirect::to(Backend::url(sprintf('responsiv/pay/invoices/preview/%s', $invoice->id)));
    }

    protected function makeStatusFormWidget()
    {
        $config = $this->makeConfig('@/plugins/responsiv/pay/models/invoicestatuslog/fields.yaml');
        $config->model = new InvoiceStatusLog;
        $config->arrayName = 'InvoiceStatusLog';
        $config->alias = 'statusLog';
        return $this->makeWidget('Backend\Widgets\Form', $config);
    }

    public function formExtendRefreshData($host, $data)
    {
        if (empty($data['user']))
            return;

        $host->model->user = $data['user'];
        if (!$user = $host->model->user)
            return;

        $data['first_name'] = $user->name;
        $data['email'] = $user->email;
        $data['phone'] = $user->phone;
        $data['company'] = $user->company;
        return $data;
    }
}