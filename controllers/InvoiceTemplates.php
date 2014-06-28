<?php namespace Responsiv\Pay\Controllers;

use Backend;
use Redirect;
use BackendMenu;
use Backend\Classes\Controller;

/**
 * InvoicesTemplates Back-end Controller
 */
class InvoiceTemplates extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController'
    ];

    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'invoicetemplates');
    }

    public function index()
    {
        return Redirect::to(Backend::url('responsiv/pay/invoicetemplates/update/1'));
    }

    /**
     * Add dynamic syntax fields
     */
    protected function formExtendFields($host)
    {
        $fields = $host->model->getFormSyntaxFields();
        if (!is_array($fields)) return;

        $defaultTab = 'Invoice';
        foreach ($fields as $field => $params) {
            if (!isset($params['tab'])) {
                $params['tab'] = $defaultTab;
                $fields[$field] = $params;
            }
        }
        $host->addTabFields($fields, 'primary');
    }
}