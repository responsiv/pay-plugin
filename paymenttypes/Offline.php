<?php namespace Responsiv\Pay\PaymentTypes;

use Backend;
use Responsiv\Pay\Classes\GatewayBase;

class Offline extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Offline Payment',
            'description' => 'For creating payment forms with offline payment processing'
        ];
    }

    /**
     * Returns the payment instructions for offline payment
     * @param  Model $invoice
     * @return string
     */
    public function getPaymentInstructions($invoice)
    {
        $host = $this->getHostObject();

        return $host->payment_instructions;
    }
}
