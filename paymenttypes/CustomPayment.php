<?php namespace Responsiv\Pay\PaymentTypes;

use Responsiv\Pay\Classes\GatewayBase;

/**
 * CustomPayment type
 */
class CustomPayment extends GatewayBase
{
    /**
     * @var string driverFields defines form fields for this driver
     */
    public $driverFields = 'fields.yaml';

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
     * getCustomPaymentPage
     */
    public function getCustomPaymentPage()
    {
        return $this->getHostObject()?->payment_page;
    }

    /**
     * payOfflineMessage returns the payment instructions for custom payment
     * @return string
     */
    public function payOfflineMessage()
    {
        return $this->getHostObject()?->payment_instructions;
    }
}
