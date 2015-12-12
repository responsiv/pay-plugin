<?php namespace Responsiv\Pay\PaymentTypes;

use Request;
use Backend;
use Redirect;
use Validator;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use SystemException;
use ApplicationException;
use ValidationException;
use Exception;
use Omnipay\Omnipay;

class Stripe extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Stripe',
            'description' => 'Stripe payment method with payment form hosted on your server.'
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
            'secret_key' => 'required',
            'publishable_key' => 'required',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function initConfigData($host)
    {
        $host->invoice_status = $this->createInvoiceStatusModel()->getStatusPaid();
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
    public function processPaymentForm($data, $host, $invoice)
    {
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
            if ($validation->fails())
                throw new ValidationException($validation);
        }
        catch (Exception $ex) {
            $invoice->logPaymentAttempt($ex->getMessage(), 0, [], [], null);
            throw $ex;
        }

        if (!$paymentMethod = $invoice->getPaymentMethod())
            throw new ApplicationException('Payment method not found');

        /*
         * Send payment request
         */
        $gateway = Omnipay::create('Stripe');
        $gateway->initialize(array(
            'apiKey' => $host->secret_key
        ));

        $formData = [
            'firstName'   => array_get($data, 'first_name'),
            'lastName'    => array_get($data, 'last_name'),
            'number'      => array_get($data, 'card_number'),
            'expiryMonth' => array_get($data, 'expiry_date_month'),
            'expiryYear'  => array_get($data, 'expiry_date_year'),
            'cvv'         => array_get($data, 'CVV'),
        ];

        $totals = (object) $invoice->getTotalDetails();

        $response = $gateway->purchase([
            'amount'   => $totals->total,
            'currency' => $totals->currency,
            'card'     => $formData
        ])->send();

        // Clean the credit card number
        $formData['number'] = '...'.substr($formData['number'], -4);

        if ($response->isSuccessful()) {
            $invoice->logPaymentAttempt('Successful payment', 1, $formData, null, null);
            $invoice->markAsPaymentProcessed();
            $invoice->updateInvoiceStatus($paymentMethod->invoice_status);
        }
        else {
            $errorMessage = $response->getMessage();
            $invoice->logPaymentAttempt($errorMessage, 0, $formData, null, null);
            throw new ApplicationException($errorMessage);
        }

    }


}
