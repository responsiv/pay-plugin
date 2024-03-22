<?php namespace Responsiv\Pay\PaymentTypes;

use Http;
use Redirect;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use ApplicationException;
use Exception;

/**
 * StripePayment
 */
class StripePayment extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function driverDetails()
    {
        return [
            'name' => 'Stripe',
            'description' => 'Accept payments using Stripe.'
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
     * processPaymentForm
     */
    public function processPaymentForm($data, $order)
    {
        // We do not need any code here since payments are processed on PayPal server.
    }
}
