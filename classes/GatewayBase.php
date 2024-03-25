<?php namespace Responsiv\Pay\Classes;

use Url;
use System\Classes\DriverBehavior;
use SystemException;

/**
 * GatewayBase represents the generic payment type.
 * All other payment types must be derived from this class
 */
abstract class GatewayBase extends DriverBehavior
{
    /**
     * driverDetails returns information about the payment type
     * Must return array:
     *
     * [
     *      'name' => 'Authorize.net',
     *      'description' => 'Authorize.net simple integration method with hosted payment form.',
     *      'offline' => false,
     * ]
     *
     * @return array
     */
    public function driverDetails()
    {
        return [
            'name' => 'Unknown',
            'description' => 'Unknown payment gateway.',
            'offline' => false
        ];
    }

    /**
     * processPaymentForm processes payment using passed data.
     *
     * Supported return values:
     * - Redirect object
     * - false: Prevent a redirect
     * - null: Standard redirect
     *
     * @param array $data Posted payment form data.
     * @param \Model $invoice Invoice model object.
     * @return mixed
     */
    abstract public function processPaymentForm($data, $invoice);

    /**
     * getPartialPath render setup help
     * @return string
     */
    public function getPartialPath()
    {
        return $this->configPath;
    }

    /**
     * registerAccessPoints registers a hidden page with specific URL. Use this method for cases when you
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
     * getDataTableOptions is used for datatable form fields used in the field configuration
     */
    public function getDataTableOptions($attribute, $field, $data)
    {
        return [];
    }

    /**
     * makeAccessPointLink is a utility function, creates a link to a registered access point.
     * @param  string $code Key used to define the access point
     * @return string
     */
    public function makeAccessPointLink($code)
    {
        return Url::to('api_responsiv_pay/'.$code);
    }

    /**
     * isApplicable returns true if the payment type is applicable for a specified invoice amount
     * @param float $amount Specifies an invoice amount
     * @return true
     */
    public function isApplicable($amount)
    {
        return true;
    }

    /**
     * invoiceAfterCreate is called when an invoice with this payment method is created
     * @param \Responsiv\Shop\Models\PaymentMethod $host
     * @param \Responsiv\Pay\Models\Invoice $invoice
     */
    public function invoiceAfterCreate($host, $invoice)
    {
    }

    /**
     * hasPaymentForm
     */
    public function hasPaymentForm()
    {
        return !($this->driverDetails()['offline'] ?? false);
    }

    /**
     * renderPaymentScripts provides an opportunity to inject global scripts to the page
     */
    public function renderPaymentScripts()
    {
        return '';
    }

    /**
     * beforeRenderPaymentForm is called before the payment form is rendered
     */
    public function beforeRenderPaymentForm()
    {
    }

    /**
     * beforeRenderPaymentProfileForm is called before the payment profile form is rendered
     */
    public function beforeRenderPaymentProfileForm()
    {
    }

    /**
     * payOfflineMessage returns payment instructions for offline types.
     * @return string
     */
    public function payOfflineMessage()
    {
        return '';
    }

    /**
     * allowNewInvoiceNotifications should return false to suppress the new invoice notification
     * when this payment method is assigned
     */
    public function allowNewInvoiceNotifications($host, $invoice)
    {
        return true;
    }

    //
    // Payment Profiles
    //

    /**
     * hasPaymentProfiles should return TRUE if the gateway supports user payment profiles.
     * The payment gateway must implement the updateUserProfile(), deleteUserProfile() and payFromProfile() methods if this method returns true.
     */
    public function hasPaymentProfiles()
    {
        return false;
    }

    /**
     * updateUserProfile creates a user profile on the payment gateway. If the profile already exists the method should update it.
     * @param \RainLab\User\Models\User $user User object to create a profile for
     * @param array $data Posted payment form data
     * @return \Responsiv\Pay\Models\UserProfile Returns the user profile object
     */
    public function updateUserProfile($user, $data)
    {
        throw new SystemException('The updateUserProfile() method is not supported by the payment gateway.');
    }

    /**
     * deleteUserProfile deletes a user profile from the payment gateway.
     * @param \RainLab\User\Models\User $user User object
     * @param \Responsiv\Pay\Models\UserProfile $profile User profile object
     */
    public function deleteUserProfile($user, $profile)
    {
        throw new SystemException('The deleteUserProfile() method is not supported by the payment gateway.');
    }

    /**
     * payFromProfile creates a payment transaction from an existing payment profile.
     * @param \Responsiv\Pay\Models\Invoice $invoice An invoice object to pay
     */
    public function payFromProfile($invoice)
    {
        throw new SystemException('The payFromProfile() method is not supported by the payment gateway.');
    }

    //
    // Abstract
    //

    /**
     * createInvoiceModel creates an instance of the invoice model
     */
    protected function createInvoiceModel()
    {
        return new \Responsiv\Pay\Models\Invoice;
    }

    /**
     * createInvoiceStatusModel creates an instance of the invoice status model
     */
    protected function createInvoiceStatusModel()
    {
        return new \Responsiv\Pay\Models\InvoiceStatus;
    }
}
