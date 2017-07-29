<?php namespace Responsiv\Pay\PaymentTypes;

use Url;
use Request;
use Backend;
use Redirect;
use Validator;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use PayPal\Service\AdaptivePaymentsService;
use PayPal\Types\Common\RequestEnvelope;
use PayPal\Types\AP\Receiver;
use PayPal\Types\AP\ReceiverList;
use PayPal\Types\AP\PreapprovalRequest;
use PayPal\Types\AP\CancelPreapprovalRequest;
use Responsiv\Currency\Models\Currency as CurrencyModel;
use ApplicationException;
use ValidationException;
use SystemException;
use Exception;

class PaypalAdaptive extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'PayPal Adaptive',
            'description' => 'The Adaptive Payments API allows merchants and developers to pay almost anyone and set up automated payments.'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function defineFormFields()
    {
        return 'fields.yaml';
    }

    /**
     * {@inheritDoc}
     */
    public function defineValidationRules()
    {
        return [
            'business_email' => 'required',
            'api_signature' => 'required',
            'api_username' => 'required',
            'api_password'  => 'required',
        ];
    }

    /**
     * Status field options.
     */
    public function getInvoiceStatusOptions()
    {
        return $this->createInvoiceStatusModel()->listStatuses();
    }

    /**
     * {@inheritDoc}
     */
    public function initConfigData($host)
    {
        $host->test_mode = true;
        $host->invoice_status = $this->createInvoiceStatusModel()->getPaidStatus();
    }

    /**
     * Returns the application ID. A hard coded value is used for test sites.
     */
    public function getAppId()
    {
        $host = $this->getHostObject();

        return $host->test_mode ? 'APP-80W284485P519543T' : $host->api_application;
    }

    //
    // Payment Profiles
    //

    /**
     * {@inheritDoc}
     */
    public function supportsPaymentProfiles()
    {
        return true;
    }

    /**
     * Creates a user profile on the payment gateway. If the profile already exists the method should update it.
     * @param \RainLab\User\Models\User $user User object to create a profile for
     * @param array $data Posted payment form data
     * @return \RainLab\Pay\Models\UserProfile Returns the user profile object
     */
    public function updateUserProfile($user, $data)
    {
        $host = $this->getHostObject();

        /*
         * PayPal SDK
         */
        $requestEnvelope = new RequestEnvelope('en_US');

        $preapprovalRequest = new PreapprovalRequest(
            $requestEnvelope,
            post('cancel_url', Url::full()),
            $this->getCurrencyCode(),
            post('return_url', Url::full()),
            date('Y-m-d')
        );

        /*
         * Prepare a preapproval
         */
        $service = new AdaptivePaymentsService($this->getAccountAndConfig());

        try {
            $response = $service->Preapproval($preapprovalRequest);
        }
        catch (Exception $ex) {
            throw new ApplicationException($this->getDetailedExceptionMessage($ex));
        }

        $ack = strtoupper($response->responseEnvelope->ack);

        if ($ack != 'SUCCESS') {
            throw new ApplicationException(array_get($response, 'error', 'Server returned a bad response'));
        }

        /*
         * Save token to profile
         */
        $token = $response->preapprovalKey;
        $profile = $host->findUserProfile($user);

        if (!$profile) {
            $profile = $host->initUserProfile($user);
        }

        $profile->setProfileData([
            'preapproval_key' => $token,
        ], $token);

        /*
         * Redirect to authorization
         */
        $endpoint = $host->test_mode
            ? 'https://www.sandbox.paypal.com/webscr'
            : 'https://www.paypal.com/webscr';

        $payPalUrl = $endpoint.'&cmd=_ap-preapproval&preapprovalkey='.$token;

        return Redirect::to($payPalUrl);
    }

    /**
     * Deletes a user profile from the payment gateway.
     * @param \RainLab\User\Models\User $user User object
     * @param \RainLab\Pay\Models\UserProfile $profile User profile object
     */
    public function deleteUserProfile($user, $profile)
    {
        if (!isset($profile->profile_data['preapproval_key'])) {
            return;
        }

        $token = $profile->profile_data['preapproval_key'];

        /*
         * PayPal SDK
         */
        $requestEnvelope = new RequestEnvelope('en_US');

        $cancelPreapprovalReq = new CancelPreapprovalRequest(
            $requestEnvelope,
            $token
        );

        /*
         * Prepare cancellation
         */
        $service = new AdaptivePaymentsService($this->getAccountAndConfig());

        try {
            $response = $service->CancelPreapproval($cancelPreapprovalReq);
        }
        catch (Exception $ex) {
            throw new ApplicationException($this->getDetailedExceptionMessage($ex));
        }

        $ack = strtoupper($response->responseEnvelope->ack);

        if ($ack != 'SUCCESS') {
            throw new ApplicationException(array_get($response, 'error', 'Server returned a bad response'));
        }
    }

    /**
     * Creates a payment transaction from an existing payment profile.
     * @param \RainLab\Pay\Models\Invoice $invoice An order object to pay
     */
    public function payFromProfile($invoice)
    {
        $customerDetails = (object) $invoice->getCustomerDetails();

        /*
         * Receiver
         */
        $receiverList = $this->prepareReceiverList($invoice);
        $requestEnvelope = new RequestEnvelope('en_US');

        /*
         * Profile
         */
        $profile = $host->findUserProfile($invoice->user);

        if (
            !$profile ||
            !isset($profile->profile_data['preapproval_key'])
        ) {
            throw new ApplicationException('Payment profile not found');
        }

        $token = $profile->profile_data['preapproval_key'];

        /*
         * PayPal SDK
         */
        $actionType = 'PAY';

        $payRequest = new PayRequest(
            $requestEnvelope,
            $actionType,
            post('cancel_url', Url::full()),
            $this->getCurrencyCode(),
            $receiverList,
            post('return_url', Url::full())
        );

        /*
         * SENDER – Sender pays all fees (for personal, implicit simple/parallel payments; do not use for chained or unilateral payments)
         * PRIMARYRECEIVER – Primary receiver pays all fees (chained payments only)
         * EACHRECEIVER – Each receiver pays their own fee (default, personal and unilateral payments)
         * SECONDARYONLY – Secondary receivers pay all fees (use only for chained payments with one secondary receiver)
         */
        $payRequest->feesPayer = 'EACHRECEIVER';
        $payRequest->preapprovalKey = $token;

        /*
         * Prepare payment
         */
        $service = new AdaptivePaymentsService($this->getAccountAndConfig());

        try {
            $response = $service->Pay($payRequest);
        }
        catch (Exception $ex) {
            throw new ApplicationException($this->getDetailedExceptionMessage($ex));
        }

        $ack = strtoupper($response->responseEnvelope->ack);

        if ($ack != 'SUCCESS') {
            throw new ApplicationException(array_get($response, 'error', 'Server returned a bad response'));
        }

        /*
         * Handle response
         */
        $data = [
            'actionType' => $actionType,
            'status' => $response->paymentExecStatus,
            'payKey' => $response->payKey
        ];

        if ($response->paymentExecStatus == 'COMPLETED') {
            $invoice->logPaymentAttempt('Successful payment', 1, $data, null, null);
            $invoice->markAsPaymentProcessed();
            $invoice->updateInvoiceStatus($host->invoice_status);
        }
        else {
            $errorMessage = 'Payment status is not completed: '. $response->paymentExecStatus;
            $invoice->logPaymentAttempt($errorMessage, 0, $data, null, null);
            throw new ApplicationException($errorMessage);
        }
    }

    //
    // Helpers
    //

    protected function prepareReceiverList($invoice)
    {
        $host = $this->getHostObject();
        $totals = (object) $invoice->getTotalDetails();

        $receiver = new Receiver;
        $receiver->email = $host->business_email;
        $receiver->amount = $totals->total;
        $receiver->primary = 'true';
        $receiver->invoiceId = $invoice->getUniqueId();

        /*
         * GOODS – This is a payment for non-digital goods
         * SERVICE – This is a payment for services (default)
         * PERSONAL – This is a person-to-person payment
         * CASHADVANCE – This is a person-to-person payment for a cash advance
         * DIGITALGOODS – This is a payment for digital goods
         * BANK_MANAGED_WITHDRAWAL – This is a person-to-person payment for bank withdrawals, available only with special permission.
        */
        $receiver->paymentType = 'SERVICE';

        return new ReceiverList([$receiver]);
    }

    /**
     * Returns a detailed exception message from a PayPal exception
     * @param Exception $ex
     * @return string
     */
    protected function getDetailedExceptionMessage($ex)
    {
        if ($ex instanceof \PayPal\Exception\PPConnectionException) {
            return 'Error connecting to ' . $ex->getUrl();
        }
        elseif ($ex instanceof \PayPal\Exception\PPConfigurationException) {
            return 'Error at '.$ex->getLine().' in '.$ex->getFile();
        }
        elseif (
            $ex instanceof \PayPal\Exception\PPInvalidCredentialException ||
            $ex instanceof \PayPal\Exception\PPMissingCredentialException
        ) {
            return $ex->errorMessage();
        }

        return 'An unknown error occured in the payment gateway.';
    }

    /**
     * Returns the currency code to use for this gateway.
     * @return string
     */
    protected function getCurrencyCode()
    {
        $currency = CurrencyModel::getPrimary();

        return $currency ? $currency->currency_code : 'USD';
    }

    /**
     * Creates a configuration array containing credentials
     * and other required configuration parameters.
     * @return array
     */
    protected function getAccountAndConfig()
    {
        $host = $this->getHostObject();

        $config = [
            'mode' => $host->test_mode ? 'sandbox' : 'live',
        ];

        $account = [
            'acct1.UserName' => $host->api_username,
            'acct1.Password' => $host->api_password,
            'acct1.Signature' => $host->api_signature,
            'acct1.AppId' => $host->api_application,
        ];

        // Testing
        $account = [
            'acct1.UserName' => "jb-us-seller_api1.paypal.com",
            'acct1.Password' => "WX4WTU3S8MY44S7F",
            'acct1.Signature' => "AFcWxV21C7fd0v3bYYYRCpSSRl31A7yDhhsPUU2XhtMoZXsWHFxu-RWy",
            'acct1.AppId' => "APP-80W284485P519543T"
        ];

        return $config + $account;
    }
}
