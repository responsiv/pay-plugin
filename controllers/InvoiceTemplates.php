<?php namespace Responsiv\Pay\Controllers;

use Backend;
use Redirect;
use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;

/**
 * InvoicesTemplates Back-end Controller
 */
class InvoiceTemplates extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class
    ];

    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Responsiv.Pay', 'invoice_template');
    }

    public function index()
    {
        return Backend::redirect('responsiv/pay/invoicetemplates/update/1');
    }

    /**
     * Add dynamic syntax fields
     */
    public function formExtendFields($host)
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
