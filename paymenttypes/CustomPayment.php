<?php namespace Responsiv\Pay\PaymentTypes;

use Responsiv\Pay\Classes\GatewayBase;

/**
 * CustomPayment type
 */
class CustomPayment extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function driverDetails()
    {
        return [
            'name' => 'Custom Payment Method',
            'description' => 'For creating payment forms with custom payment processing, such as offline payments.',
            'paymentForm' => false,
            'receiptPage' => false
        ];
    }

    /**
     * processPaymentForm
     */
    public function processPaymentForm($data, $order)
    {
    }

    /**
     * payOfflineMessage returns the payment instructions for custom payment
     * @return string
     */
    public function payOfflineMessage()
    {
        $host = $this->getHostObject();

        return $host->payment_instructions;
    }

    /**
     * allowNewInvoiceNotifications should return false to suppress the new order notification
     * when this payment method is assigned
     */
    public function allowNewInvoiceNotifications($host, $order)
    {
        return !$host->suppress_new_notification;
    }
}
