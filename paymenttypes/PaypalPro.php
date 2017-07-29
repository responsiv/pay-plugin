<?php namespace Responsiv\Pay\PaymentTypes;

use Request;
use Backend;
use Redirect;
use Validator;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use ApplicationException;
use ValidationException;
use SystemException;
use Exception;
use Omnipay\Omnipay;

class PaypalPro extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'PayPal Pro',
            'description' => 'PayPal Pro payment method with payment form hosted on your server'
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
            'api_signature' => 'required',
            'api_username' => 'required',
            'api_password'  => 'required',
        ];
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
     * Action field options
     */
    public function getCardActionOptions()
    {
        return [
            'purchase'  => 'Purchase',
            'authorize' => 'Authorization only'
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
    public function processPaymentForm($data, $invoice)
    {
        $host = $this->getHostObject();

        $rules = [
            'first_name'              => 'required',
            'last_name'               => 'required',
            'expiry_date_month'       => ['required', 'regex:/^[0-9]*$/'],
            'expiry_date_year'        => ['required', 'regex:/^[0-9]*$/'],
            'card_number'             => ['required', 'regex:/^[0-9]*$/'],
            'CVV'                     => ['required', 'regex:/^[0-9]*$/'],
        ];

        $validation = Validator::make($data, $rules);

        try {
            if ($validation->fails()) {
                throw new ValidationException($validation);
            }
        }
        catch (Exception $ex) {
            $invoice->logPaymentAttempt($ex->getMessage(), 0, [], [], null);
            throw $ex;
        }

        /*
         * Send payment request
         */
        $gateway = Omnipay::create('PayPal_Pro');
        $gateway->setSignature($host->api_signature);
        $gateway->setUsername($host->api_username);
        $gateway->setPassword($host->api_password);
        $gateway->setTestMode($host->test_mode);

        $formData = [
            'firstName'   => array_get($data, 'first_name'),
            'lastName'    => array_get($data, 'last_name'),
            'number'      => array_get($data, 'card_number'),
            'expiryMonth' => array_get($data, 'expiry_date_month'),
            'expiryYear'  => array_get($data, 'expiry_date_year'),
            'cvv'         => array_get($data, 'CVV'),
        ];

        $cardAction = $host->card_action == 'purchase' ? 'purchase' : 'authorize';
        $totals = (object) $invoice->getTotalDetails();

        $response = $gateway->$cardAction([
            'amount'   => $totals->total,
            'currency' => $totals->currency,
            'card'     => $formData
        ])->send();

        // Clean the credit card number
        $formData['number'] = '...'.substr($formData['number'], -4);

        if ($response->isSuccessful()) {
            $invoice->logPaymentAttempt('Successful payment', 1, $formData, null, null);
            $invoice->markAsPaymentProcessed();
            $invoice->updateInvoiceStatus($host->invoice_status);
        }
        else {
            $errorMessage = $response->getMessage();
            $invoice->logPaymentAttempt($errorMessage, 0, $formData, null, null);
            throw new ApplicationException($errorMessage);
        }
    }
}
