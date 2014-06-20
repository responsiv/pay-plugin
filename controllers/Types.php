<?php namespace Responsiv\Pay\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Responsiv\Pay\Classes\GatewayManager;
use Responsiv\Pay\Models\Type as TypeModel;
use Exception;

/**
 * Types Back-end Controller
 */
class Types extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $gatewayAlias;
    protected $gatewayClass;

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'types');
    }

    protected function index_onLoadAddPopup()
    {
        try {
            $gateways = GatewayManager::instance()->listGateways(true);
            usort($gateways, array($this, 'sortPaymentGateways'));
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
        if (!$record->is_enabled)
            return 'safe disabled';
    }

    public function create($gatewayAlias)
    {
        try {
            $this->gatewayAlias = $gatewayAlias;
            $this->getClassExtension('Backend.Behaviors.FormController')->create();
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }
    }

    public function formCreateModelObject()
    {
        $model = new TypeModel;
        $model->applyGatewayClass($this->getGatewayClass());
        return $model;
    }

    public function formExtendFields($widget)
    {
        $model = $widget->model;
        $config = $model->getFieldConfig();
        $widget->addFields($config->fields, 'primary');
    }

    protected function getGatewayClass()
    {
        $alias = post('gateway_alias', $this->gatewayAlias);

        if ($this->gatewayClass !== null)
            return $this->gatewayClass;

        if (!$gateway = GatewayManager::instance()->findByAlias($alias))
            throw new Exception('Unable to find gateway with alias '. $alias);

        return $this->gatewayClass = $gateway->class;
    }

    private function sortPaymentGateways($a, $b)
    {
        return strcasecmp($a->name, $b->name);
    }

}