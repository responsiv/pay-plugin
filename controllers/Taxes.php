<?php namespace Responsiv\Pay\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Taxes Back-end Controller
 */
class Taxes extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'taxes');
    }
}
