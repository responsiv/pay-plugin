<?php namespace Responsiv\Pay\Classes;

use Str;
use URL;
use File;
use System\Classes\ModelBehavior;

/**
 * Represents the generic payment type.
 * All other payment types must be derived from this class
 */
class GatewayBase extends ModelBehavior
{
    use \System\Traits\ConfigMaker;

    protected $invoiceModel = 'Responsiv\Pay\Models\Invoice';

    protected $invoiceStatusModel = 'Responsiv\Pay\Models\InvoiceStatus';

    /**
     * Returns information about the payment type
     * Must return array:
     * 
     * [
     *      'name'        => 'Authorize.net',
     *      'description' => 'Authorize.net simple integration method with hosted payment form.'
     * ]
     *
     * @return array
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Unknown',
            'description' => 'Unknown payment gateway.'
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

        /*
         * Parse the config
         */
        $this->configPath = $this->guessConfigPathFrom($this);
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
    public function boot($host)
    {
        // Set default data
        if (!$host->exists)
            $this->initConfigData($host);

        // Apply validation rules
        $host->rules = array_merge($host->rules, $this->defineValidationRules());
    }

    /**
     * Extra field configuration for the payment type.
     */
    public function defineFormFields()
    {
        return 'fields.yaml';
    }

    /**
     * Initializes configuration data when the payment method is first created.
     * @param  Model $host
     */
    public function initConfigData($host){}

    /**
     * Defines validation rules for the custom fields.
     * @return array
     */
    public function defineValidationRules()
    {
        return [];
    }

    /**
     * Render setup help
     * @return string
     */
    public function getPartialPath()
    {
        return $this->configPath;
    }

    /**
     * Registers a hidden page with specific URL. Use this method for cases when you
     * need to have a hidden landing page for a specific payment gateway. For example,
     * PayPal needs a landing page for the auto-return feature.
     * Important! Payment module access point names should have a prefix.
     * @return array Returns an array containing page URLs and methods to call for each URL:
     * return array('paypal_autoreturn'=>'processPaypalAutoreturn'). The processing methods must be declared
     * in the payment type class. Processing methods must accept one parameter - an array of URL segments
     * following the access point. For example, if URL is /paypal_autoreturn/1234 an array with single
     * value '1234' will be passed to processPaypalAutoreturn method.
     */
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
     * Returns true if the payment type is applicable for a specified invoice amount
     * @param float $amount Specifies an invoice amount
     * @param $host Model object to add fields to
     * @return true
     */
    public function isApplicable($amount, $host)
    {
        return true;
    }

    /**
     * Processes payment using passed data.
     * @param array $data Posted payment form data.
     * @param Model $host Type model object containing configuration fields values.
     * @param Model $invoice Invoice model object.
     */
    public function processPaymentForm($data, $host, $invoice) { }

    /**
     * This method is called before the payment form is rendered
     * @param $host Model object containing configuration fields values
     */
    public function beforeRenderPaymentForm($host) { }

    /**
     * Creates an instance of the invoice model
     */
    protected function createInvoiceModel()
    {
        $class = '\\'.ltrim($this->invoiceModel, '\\');
        $model = new $class();
        return $model;
    }

    /**
     * Creates an instance of the invoice status model
     */
    protected function createInvoiceStatusModel()
    {
        $class = '\\'.ltrim($this->invoiceStatusModel, '\\');
        $model = new $class();
        return $model;
    }
}

