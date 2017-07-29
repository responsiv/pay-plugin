<?php namespace Responsiv\Pay\Classes;

use Str;
use Url;
use File;
use System\Classes\ModelBehavior;
use SystemException;

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

        if (!$model) {
            return;
        }

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
        if (!$host->exists) {
            $this->initConfigData($host);
        }

        // Apply validation rules
        $host->rules = array_merge($host->rules, $this->defineValidationRules());
    }

    /**
     * Returns the host object with configuration.
     * @return Responsiv\Pay\Models\PaymentMethod
     */
    public function getHostObject()
    {
        return $this->model;
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
     * Important! Payment gateway access point names should have a prefix.
     * @return array Returns an array containing page URLs and methods to call for each URL:
     * return ['paypal_autoreturn' => 'processPaypalAutoreturn']. The processing methods must be declared
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
        return Url::to('api_responsiv_pay/'.$code);
    }

    /**
     * Returns true if the payment type is applicable for a specified invoice amount
     * @param float $amount Specifies an invoice amount
     * @return true
     */
    public function isApplicable($amount)
    {
        return true;
    }

    /**
     * Processes payment using passed data.
     *
     * Supported return values:
     * - Redirect object
     * - false: Prevent a redirect
     * - null: Standard redirect
     *
     * @param array $data Posted payment form data.
     * @param Model $invoice Invoice model object.
     * @return mixed
     */
    public function processPaymentForm($data, $invoice) { }

    /**
     * This method is called before the payment form is rendered
     */
    public function beforeRenderPaymentForm() { }

    /**
     * This method is called before the payment profile form is rendered
     */
    public function beforeRenderPaymentProfileForm() { }

    //
    // Payment Profiles
    //

    /**
     * This method should return TRUE if the gateway supports user payment profiles.
     * The payment gateway must implement the updateUserProfile(), deleteUserProfile() and payFromProfile() methods if this method returns true.
     */
    public function supportsPaymentProfiles()
    {
        return false;
    }

    /**
     * Creates a user profile on the payment gateway. If the profile already exists the method should update it.
     * @param \RainLab\User\Models\User $user User object to create a profile for
     * @param array $data Posted payment form data
     * @return \RainLab\Pay\Models\UserProfile Returns the user profile object
     */
    public function updateUserProfile($user, $data)
    {
        throw new SystemException('The updateUserProfile() method is not supported by the payment gateway.');
    }

    /**
     * Deletes a user profile from the payment gateway.
     * @param \RainLab\User\Models\User $user User object
     * @param \RainLab\Pay\Models\UserProfile $profile User profile object
     */
    public function deleteUserProfile($user, $profile)
    {
        throw new SystemException('The deleteUserProfile() method is not supported by the payment gateway.');
    }

    /**
     * Creates a payment transaction from an existing payment profile.
     * @param \RainLab\Pay\Models\Invoice $invoice An order object to pay
     */
    public function payFromProfile($invoice)
    {
        throw new SystemException('The payFromProfile() method is not supported by the payment gateway.');
    }

    //
    // Abstract
    //

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

