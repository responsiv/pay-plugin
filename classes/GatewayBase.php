<?php namespace Responsiv\Pay\Classes;

use URL;
use System\Classes\ModelBehavior;

/**
 * Represents the generic payment type.
 * All other payment types must be derived from this class
 */
class GatewayBase extends ModelBehavior
{
    use \System\Traits\ConfigMaker;

    public function gatewayDetails()
    {
        return [
            'name'        => 'Unknown',
            'description' => 'Unknown payment gateway'
        ];
    }

    /**
     * @var mixed Extra field configuration for the payment type.
     */
    protected $fieldConfig;

    /**
     * Constructor
     */
    public function __construct($model = null)
    {
        parent::__construct($model);

        // Option A: (@todo Determine which is faster by benchmark)
        // $relativePath = strtolower(str_replace('\\', '/', get_class($model)));
        // $this->configPath = ['modules/' . $relativePath, 'plugins/' . $relativePath];

        // Option B:
        $this->configPath = $this->guessConfigPathFrom($this);

        /*
         * Parse the config
         */
        $this->fieldConfig = $this->makeConfig($this->defineFormFields());

        if (!$model)
            return;

        $this->boot($model);
    }

    /**
     * Boot method called when the payment gateway is first loaded
     * with an existing model.
     * @return array
     */
    public function boot($hostObj)
    {
        if (!$hostObj->exists)
            $this->initConfigData($hostObj);

        // Apply validation rules
        $hostObj->rules = array_merge($hostObj->rules, $this->defineValidationRules());

        // Define model relations
        $this->defineRelationships($hostObj);
    }

    /**
     * Defines any required model relationships.
     * @param  Model $hostObj
     */
    public function defineRelationships($hostObj){}

    /**
     * Extra field configuration for the payment type.
     */
    public function defineFormFields()
    {
        return 'fields.yaml';
    }

    /**
     * Initializes configuration data when the payment method is first created.
     * @param  Model $hostObj
     */
    public function initConfigData($hostObj){}

    /**
     * Defines validation rules for the custom fields.
     * @return array
     */
    public function defineValidationRules()
    {
        return [];
    }

    public function registerAccessPoints()
    {
        return [];
    }

    /**
     * Returns the field configuration used by this model.
     */
    public function getFieldConfig()
    {
        return $this->fieldConfig;
    }

    /**
     * Utility function, creates a link to a registered access point.
     * @param  string $code Key used to define the access point
     * @return string
     */
    public function makeAccessPointLink($code)
    {
        return URL::to('api_responsiv_pay/'.$code);
    }

    /**
     * Processes payment using passed data
     * @param array $data Posted payment form data
     * @param $hostObj Type model object containing configuration fields values
     * @param $invoice Invoice model object
     * @param $isAdmin Determines whether the function is called from the administration area
     */
    public function processPaymentForm($data, $hostObj, $invoice, $isAdmin = false) { }

}

