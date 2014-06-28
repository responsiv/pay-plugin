<?php namespace Responsiv\Pay\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

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

    /**
     * Add the custom invoice partial to the preview form
     */
    // protected function formExtendFieldsBefore($host)
    // {
    //     if ($host->getContext() != 'preview') return;

    //     $fields = [
    //         'invoice_iframe' => [
    //             'tab' => 'invoice',
    //             'type' => 'partial',
    //         ]
    //     ];

    //     $host->addTabFields($fields, 'primary');
    // }
}