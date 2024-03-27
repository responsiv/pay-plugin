<?php namespace Responsiv\Pay\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Taxes Backend Controller
 */
class Taxes extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * @var string formConfig file
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string listConfig file
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array required permissions
     */
    public $requiredPermissions = [];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'taxes');
    }

    /**
     * formCheckPermission checks if a custom permission has been specified
     */
    public function formCheckPermission(string $name)
    {
        // Remove delete button for shipping tax class
        if ($name === 'modelDelete' && $this->formGetModel()?->is_system) {
            return false;
        }

        return $this->asExtension('FormController')->formCheckPermission($name);
    }
}
