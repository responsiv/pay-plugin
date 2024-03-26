<?php namespace Responsiv\Pay\Controllers;

use File;
use BackendMenu;
use Responsiv\Pay\Classes\GatewayManager;
use Backend\Classes\Controller;
use ApplicationException;
use Exception;

/**
 * PaymentMethods Backend Controller
 */
class PaymentMethods extends Controller
{
    /**
     * @var array implement behaviors in this controller.
     */
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
     * @var string driverAlias
     */
    public $driverAlias;

    /**
     * @var string driverClass
     */
    protected $driverClass;

    /**
     * @var array required permissions
     */
    public $requiredPermissions = [];

    /**
     * __construct
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'types');
    }

    /**
     * beforeDisplay
     */
    public function beforeDisplay()
    {
        GatewayManager::createPartials();
    }

    /**
     * index_onLoadAddPopup
     */
    protected function index_onLoadAddPopup()
    {
        try {
            $gateways = GatewayManager::instance()->listGateways();
            $gateways->sortBy('name');
            $this->vars['gateways'] = $gateways;
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->makePartial('add_gateway_form');
    }

    /**
     * listInjectRowClass
     */
    public function listInjectRowClass($record, $definition = null)
    {
        if (!$record->is_enabled) {
            return 'safe disabled';
        }
    }

    /**
     * create
     */
    public function create($driverAlias = null)
    {
        try {
            if (!$driverAlias) {
                throw new ApplicationException('Missing a gateway code');
            }

            $this->driverAlias = $driverAlias;
            $this->asExtension('FormController')->create();
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }
    }

    /**
     * formBeforeSave
     */
    public function formBeforeSave($model)
    {
        if (post('PaymentMethod[is_enabled]')) {
            $this->formSetSaveValue('is_enabled_edit', true);
        }
    }

    /**
     * formExtendModel
     */
    public function formExtendModel($model)
    {
        if (!$model->exists) {
            $model->applyDriverClass($this->getDriverClass());
        }

        return $model;
    }

    /**
     * formExtendFields
     */
    public function formExtendFields($widget)
    {
        $model = $widget->model;
        $config = $model->getFieldConfig();
        $widget->addFields($config->fields, 'primary');

        // Add the set up help partial
        $setupPartial = $model->getPartialPath().'/_setup_help.php';
        if (File::exists($setupPartial)) {
            $widget->addFields([
                'setup_help' => [
                    'type' => 'partial',
                    'tab'  => 'Help',
                    'path' => $setupPartial,
                ]
            ], 'primary');
        }

        // Hide return page for unsupported drivers
        if (!$model->hasReturnPage()) {
            $widget->getField('return_page')?->hidden();
        }
    }

    /**
     * getDriverClass
     */
    protected function getDriverClass()
    {
        $alias = post('driver_alias', $this->driverAlias);

        if ($this->gatewayClass !== null) {
            return $this->gatewayClass;
        }

        if (!$gateway = GatewayManager::instance()->findByAlias($alias)) {
            throw new ApplicationException("Unable to find driver: {$alias}");
        }

        return $this->gatewayClass = $gateway->class;
    }
}
