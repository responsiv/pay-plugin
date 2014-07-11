<?php namespace Responsiv\Pay\Classes;

use Str;
use URL;
use File;
use System\Classes\ModelBehavior;
use Responsiv\Pay\Models\InvoiceTypeLog;

/**
 * Represents the generic payment type.
 * All other payment types must be derived from this class
 */
class GatewayBase extends ModelBehavior
{
    use \System\Traits\ConfigMaker;

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
    public function boot($host)
    {
        // Define model relations
        $this->defineRelationships($host);

        // Set default data
        if (!$host->exists)
            $this->initConfigData($host);

        // Apply validation rules
        $host->rules = array_merge($host->rules, $this->defineValidationRules());
    }

    /**
     * Defines any required model relationships.
     * @param  Model $host
     */
    public function defineRelationships($host){}

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
     * This function is called before an invoice status deletion.
     * Use this method to check whether the payment type
     * references an invoice status. If so, throw ApplicationException
     * with explanation why the status cannot be deleted.
     * @param Model         $host   Model object containing configuration fields values.
     * @param InvoiceStatus $status Specifies a status to be deleted.
     */
    public function statusDeletionCheck($host, $status) { }

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
     * @param bool  $isAdmin Determines whether the function is called from the administration area.
     */
    public function processPaymentForm($data, $host, $invoice, $isAdmin = false) { }

    /**
     * This method is called when an invoice with this payment method is created
     * @param $host Model object containing configuration fields values
     * @param $invoice Invoice object
     */
    public function invoiceAfterCreate($host, $invoice) { }

    /**
     * Adds a log record to the invoice payment attempts log.
     * @param mixed  $invoice           Invoice object the payment attempt is belongs to.
     * @param string $message           Log message.
     * @param bool   $isSuccess         Indicates that the attempt was successful.
     * @param array  $requestArray      An array containing data posted to the payment gateway.
     * @param array  $responseArray     An array containing data received from the payment gateway.
     * @param string $responseText      Raw gateway response text.
     * @param string $ccvResponseCode   Card code verification response code.
     * @param string $ccvResponseText   Card code verification response text.
     * @param string $avsResponseCode   Address verification response code.
     * @param string $avsResponseText   Address verification response text.
     */
    protected function logPaymentAttempt(
        $invoice,
        $message,
        $isSuccess,
        $requestArray,
        $responseArray,
        $responseText,
        $ccvResponseCode = null,
        $ccvResponseText = null,
        $avsResponseCode = null,
        $avsResponseText = null
    ) {
        $info = $this->gatewayDetails();

        $record = new InvoiceTypeLog;
        $record->message = $message;
        $record->invoice_id = $invoice->id;
        $record->payment_type_name = $info['name'];
        $record->is_success = $isSuccess;

        $record->raw_response = $responseText;
        $record->request_data = $requestArray;
        $record->response_data = $responseArray;

        $record->ccv_response_code = $ccvResponseCode;
        $record->ccv_response_text = $ccvResponseText;
        $record->avs_response_code = $avsResponseCode;
        $record->avs_response_text = $avsResponseText;

        $record->save();
    }

    /**
     * This method returns true for non-offline payment types
     */
    public function hasPaymentForm()
    {
        $info = $this->gatewayDetails();
        return array_key_exists('offline', $info) && $info['offline'] ? false : true;
    }

    /**
     * This method is called before the payment form is rendered
     * @param $host Model object containing configuration fields values
     */
    public function beforeRenderPaymentForm($host) { }

}

