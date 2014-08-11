<?php namespace Responsiv\Pay\Interfaces;

/**
 * This contract represents an Payment Method
 */
interface PaymentMethod
{

    /**
     * Returns the gateway class name implemented by this payment type.
     * Example: Responsiv\Pay\PaymentTypes\PaypalStandard
     * @return string
     */
    public function getGatewayClass();

}
