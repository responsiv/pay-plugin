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
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'invoices');
    }
}