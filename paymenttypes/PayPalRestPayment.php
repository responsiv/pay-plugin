<?php namespace Responsiv\Pay\PaymentTypes;

use Http;
use Redirect;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use ApplicationException;
use Exception;

/**
 * PayPalRestPayment
 */
class PayPalRestPayment extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function driverDetails()
    {
        return [
            'name' => 'PayPal REST API Method',
            'description' => 'Accept payments using the PayPal using a dynamic payment form hosted on your server.'
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
    public function initDriverHost($host)
    {
        $host->rules['business_email'] = ['required', 'email'];

        if (!$host->exists) {
            $host->test_mode = true;
            $host->order_status = $this->createOrderStatusModel()->getPaidStatus();
        }
    }

    /**
     * getOrderStatusOptions for status field options.
     */
    public function getOrderStatusOptions()
    {
        return $this->createOrderStatusModel()->listStatuses();
    }

    /**
     * {@inheritDoc}
     */
    public function registerAccessPoints()
    {
        return [
            'paypal_rest_orders' => 'processApiOrders',
            'paypal_rest_order_capture' => 'processApiOrderCapture'
        ];
    }

    /**
     * getCancelPageOptions
     */
    public function getCancelPageOptions()
    {
        return Page::getNameList();
    }

    /**
     * getOrdersUrl
     */
    public function getOrdersUrl()
    {
        return $this->makeAccessPointLink('paypal_rest_orders');
    }

    /**
     * getOrderCaptureUrl
     */
    public function getOrderCaptureUrl()
    {
        return $this->makeAccessPointLink('paypal_rest_order_capture');
    }

    /**
     * getOrderBodyFields
     */
    public function getOrderBodyFields($order)
    {
        return [
            'cart' => [
                ['id' => 1, 'quantity' => 7],
                ['id' => 2, 'quantity' => 9],
            ]
        ];
    }

    /**
     * processPaymentForm
     */
    public function processPaymentForm($data, $order)
    {
        // We do not need any code here since payments are processed on PayPal server.
    }

    /**
     * processApiOrders
     */
    public function processApiOrders($params)
    {
    }

    /**
     * processApiOrderCapture
     */
    public function processApiOrderCapture($params)
    {
    }
}
