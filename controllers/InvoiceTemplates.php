<?php namespace Responsiv\Pay\Controllers;

use Backend;
use Redirect;
use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;

/**
 * InvoicesTemplates backend controller
 */
class InvoiceTemplates extends Controller
{
    /**
     * @var array implement extensions
     */
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class
    ];

    /**
     * @var array formConfig configuration.
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var array listConfig configuration.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * __construct
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Responsiv.Pay', 'invoice_settings');
    }

    /**
     * formExtendFields add dynamic syntax fields
     */
    public function formExtendFields($host)
    {
        $fields = $host->model->getFormSyntaxFields();
        if (!is_array($fields)) {
            return;
        }

        $defaultTab = "Invoice";
        foreach ($fields as $field => $params) {
            if (!isset($params['tab'])) {
                $params['tab'] = $defaultTab;
                $fields[$field] = $params;
            }
        }

        $host->addTabFields($fields, 'primary');
    }
}
