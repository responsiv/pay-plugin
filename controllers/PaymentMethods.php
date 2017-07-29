<?php namespace Responsiv\Pay\Controllers;

use File;
use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use Responsiv\Pay\Classes\GatewayManager;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use ApplicationException;
use Exception;

/**
 * Payment Methods Back-end Controller
 */
class PaymentMethods extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $gatewayAlias;
    protected $gatewayClass;

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'types');

        GatewayManager::createPartials();
    }

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
     * {@inheritDoc}
     */
    public function listInjectRowClass($record, $definition = null)
    {
        if (!$record->is_enabled) {
            return 'safe disabled';
        }
    }

    public function create($gatewayAlias = null)
    {
        try {
            if (!$gatewayAlias) {
                throw new ApplicationException('Missing a gateway code');
            }

            $this->gatewayAlias = $gatewayAlias;
            $this->asExtension('FormController')->create();
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }
    }

    public function formExtendModel($model)
    {
        if (!$model->exists) {
            $model->applyGatewayClass($this->getGatewayClass());
        }

        return $model;
    }

    public function formExtendFields($widget)
    {
        $model = $widget->model;
        $config = $model->getFieldConfig();
        $widget->addFields($config->fields, 'primary');

        /*
         * Add the set up help partial
         */
        $setupPartial = $model->getPartialPath().'/setup_help.htm';
        if (File::exists($setupPartial)) {
            $widget->addFields([
                'setup_help' => [
                    'type' => 'partial',
                    'tab'  => 'Help',
                    'path' => $setupPartial,
                ]
            ], 'primary');
        }
    }

    protected function getGatewayClass()
    {
        $alias = post('gateway_alias', $this->gatewayAlias);

        if ($this->gatewayClass !== null) {
            return $this->gatewayClass;
        }

        if (!$gateway = GatewayManager::instance()->findByAlias($alias)) {
            throw new ApplicationException('Unable to find gateway: '. $alias);
        }

        return $this->gatewayClass = $gateway->class;
    }
}
