<?php namespace Responsiv\Pay\PaymentTypes;

use Http;
use Redirect;
use Cms\Classes\Page;
use Responsiv\Shop\Classes\PaymentTypeBase;
use ApplicationException;
use Exception;

/**
 * PayPalStandardPayment
 */
class PayPalStandardPayment extends PaymentTypeBase
{
    /**
     * {@inheritDoc}
     */
    public function driverDetails()
    {
        return [
            'name' => 'PayPal Standard',
            'description' => 'PayPal Standard payment method with payment form hosted on PayPal server.'
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
            'paypal_standard_autoreturn' => 'processAutoreturn',
            'paypal_standard_ipn' => 'processIpn'
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
     * getFormAction gets the URL to Paypal's servers
     */
    public function getFormAction()
    {
        $host = $this->getHostObject();

        if ($host->test_mode) {
            return "https://www.sandbox.paypal.com/cgi-bin/webscr";
        }
        else {
            return "https://www.paypal.com/cgi-bin/webscr";
        }
    }

    /**
     * getAutoreturnUrl
     */
    public function getAutoreturnUrl()
    {
        return $this->makeAccessPointLink('paypal_standard_autoreturn');
    }

    /**
     * getIpnUrl
     */
    public function getIpnUrl()
    {
        return $this->makeAccessPointLink('paypal_standard_ipn');
    }

    /**
     * getHiddenFields
     */
    public function getHiddenFields($order)
    {
        $host = $this->getHostObject();
        $result = [];

        // Billing information
        $userDetails = (object) $order->getUserDetails();

        $result['first_name'] = $userDetails->first_name;
        $result['last_name'] = $userDetails->last_name;
        $result['address1'] = $userDetails->address_line1;
        $result['address2'] = $userDetails->address_line2;
        $result['city'] = $userDetails->city;
        $result['country'] = $userDetails->country;
        $result['state'] = $userDetails->state;
        $result['zip'] = $userDetails->zip;
        $result['night_phone_a'] = $userDetails->phone;

        // Order items
        $itemIndex = 1;
        foreach ($order->getLineItemDetails() as $item) {
            $item = (object) $item;
            $result['item_name_'.$itemIndex] = $item->description;
            $result['amount_'.$itemIndex] = round($item->price, 2);
            $result['quantity_'.$itemIndex] = $item->quantity;
            $itemIndex++;
        }

        $totals = (object) $order->getTotalDetails();
        $orderId = $order->getUniqueId();
        $orderHash = $order->getUniqueHash();

        // Payment set up
        $result['no_shipping'] = 1;
        $result['cmd'] = '_cart';
        $result['upload'] = 1;
        $result['tax_cart'] = number_format($totals->tax, 2, '.', '');
        $result['invoice'] = $orderId;
        $result['business'] = $host->business_email;
        $result['currency_code'] = $totals->currency;
        $result['tax'] = number_format($totals->tax, 2, '.', '');

        $result['notify_url'] = $this->getIpnUrl().'/'.$orderHash;
        $result['return'] = $this->getAutoreturnUrl().'/'.$orderHash;

        if ($host->cancel_page) {
            $result['cancel_return'] = Page::url($host->cancel_page, [
                'order_id' => $orderId,
                'order_hash' => $orderHash
            ]);
        }

        $result['bn'] = 'Responsiv.Shop';
        $result['charset'] = 'utf-8';

        foreach ($result as $key => $value) {
            $result[$key] = str_replace("\n", ' ', $value);
        }

        return $result;
    }

    /**
     * processPaymentForm
     */
    public function processPaymentForm($data, $order)
    {
        // We do not need any code here since payments are processed on PayPal server.
    }

    /**
     * processIpn
     */
    public function processIpn($params)
    {
        try {
            $order = null;

            sleep(5);

            $hash = array_key_exists(0, $params) ? $params[0] : null;
            if (!$hash) {
                throw new ApplicationException('Order not found');
            }

            $order = $this->createOrderModel()->findByUniqueHash($hash);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            if (!$paymentMethod = $order->getPaymentMethod()) {
                throw new ApplicationException('Payment method not found');
            }

            if ($paymentMethod->getDriverClass() !== \Responsiv\Pay\PaymentTypes\PayPalStandardPayment::class) {
                throw new ApplicationException('Invalid payment method');
            }

            $endpoint = $paymentMethod->test_mode
                ? "www.sandbox.paypal.com/cgi-bin/webscr"
                : "www.paypal.com/cgi-bin/webscr";

            $fields = $_POST;
            if ($paymentMethod->test_mode) {
                foreach ($fields as $key => $value) {
                    // Replace every \n that isn't part of \r\n with \r\n
                    // to prevent an invalid response from PayPal
                    $fields[$key] = preg_replace("~(?<!\r)\n~","\r\n",$value);
                }
            }

            $fields['cmd'] = '_notify-validate';

            $response = $this->postData($endpoint, $fields);

            if (!$order->isPaymentProcessed(true)) {
                if (input('mc_gross') != $this->getPaypalTotal($order)) {
                    $order->logPaymentAttempt('Invalid order total received via IPN: '.input('mc_gross'), 0, [], $_POST, $response);
                }
                else {

                    if (strpos($response, 'VERIFIED') !== false) {
                        if ($order->markAsPaymentProcessed()) {
                            $order->logPaymentAttempt('Successful payment', 1, [], $_POST, $response);
                            $order->updateOrderStatus($paymentMethod->order_status);
                        }
                    } else {
                        $order->logPaymentAttempt('Invalid payment notification', 0, [], $_POST, $response);
                    }
                }
            }
        }
        catch (Exception $ex) {
            if ($order) {
                $order->logPaymentAttempt($ex->getMessage(), 0, [], $_POST, null);
            }

            throw new ApplicationException($ex->getMessage());
        }
    }

    /**
     * processAutoreturn
     */
    public function processAutoreturn($params)
    {
        try {
            $order = null;
            $response = null;

            $hash = array_key_exists(0, $params) ? $params[0] : null;
            if (!$hash) {
                throw new ApplicationException('Order not found');
            }

            $order = $this->createOrderModel()->findByUniqueHash($hash);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            if (!$paymentMethod = $order->getPaymentMethod()) {
                throw new ApplicationException('Payment method not found');
            }

            if ($paymentMethod->getDriverClass() !== \Responsiv\Pay\PaymentTypes\PayPalStandardPayment::class) {
                throw new ApplicationException('Invalid payment method');
            }

            // PDT request
            if (!$order->isPaymentProcessed(true)) {
                $transaction = input('tx');
                if (!$transaction) {
                    throw new ApplicationException('Invalid transaction value');
                }

                $endpoint = $paymentMethod->test_mode
                    ? "www.sandbox.paypal.com/cgi-bin/webscr"
                    : "www.paypal.com/cgi-bin/webscr";

                $fields = [
                    'cmd' => '_notify-synch',
                    'tx'  => $transaction,
                    'at'  => $paymentMethod->pdt_token
                ];

                $response = $this->postData($endpoint, $fields);

                // Mark order as paid
                if (strpos($response, 'SUCCESS') !== false) {
                    $matches = [];

                    if (!preg_match('/^invoice=(.*)$/m', $response, $matches)) {
                        throw new ApplicationException('Invalid response');
                    }

                    if (trim($matches[1]) != $order->getUniqueId()) {
                        throw new ApplicationException('Invalid order number');
                    }

                    if (!preg_match('/^mc_gross=([0-9\.]+)$/m', $response, $matches)) {
                        throw new ApplicationException('Invalid response');
                    }

                    if ($matches[1] != $this->getPaypalTotal($order)) {
                        throw new ApplicationException('Invalid order total - order total received is '.$matches[1]);
                    }

                    if ($order->markAsPaymentProcessed()) {
                        $order->logPaymentAttempt('Successful payment', 1, [], $_GET, $response);
                        $order->updateOrderStatus($paymentMethod->order_status);
                    }
                }
            }

            $googleTrackingCode = 'utm_nooverride=1';
            if (!$returnPage = $order->getReceiptUrl()) {
                throw new ApplicationException('PayPal Standard Receipt page is not found');
            }

            return Redirect::to($returnPage.'?'.$googleTrackingCode);
        }
        catch (Exception $ex) {
            if ($order) {
                $order->logPaymentAttempt($ex->getMessage(), 0, [], $_GET, $response);
            }

            throw new ApplicationException($ex->getMessage());
        }
    }

    /**
     * postData is used to communicate with the PayPal endpoint
     */
    private function postData($endpoint, $fields)
    {
        return Http::post('https://'.$endpoint, function($http) use ($fields) {
            $http->noRedirect();
            $http->timeout(30);
            $http->data($fields);
        });
    }

    /**
     * getPaypalTotal is used to determine the order total as seen by PayPal
     */
    private function getPaypalTotal($order)
    {
        $orderTotal = 0;

        // Add up individual order items
        foreach ($order->getLineItemDetails() as $item) {
            $item = (object) $item;
            $itemPrice = round($item->price, 2);
            $orderTotal = $orderTotal + ($item->quantity * $itemPrice);
        }

        // Order items tax
        $itemTax = round($order->tax, 2);
        $orderTotal = $orderTotal + $itemTax;

        return $orderTotal;
    }

}
