<?php namespace Responsiv\Pay\PaymentTypes;

use Backend;
use Redirect;
use Cms\Classes\Page;
use October\Rain\Network\Http;
use Responsiv\Pay\Classes\GatewayBase;
use ApplicationException;

class PaypalStandard extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'PayPal Standard',
            'description' => 'PayPal Standard payment method with payment form hosted on PayPal server'
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
            'business_email' => ['required', 'email']
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
     * Status field options.
     */
    public function getInvoiceStatusOptions()
    {
        return $this->createInvoiceStatusModel()->listStatuses();
    }

    /**
     * {@inheritDoc}
     */
    public function registerAccessPoints()
    {
        return array(
            'paypal_standard_autoreturn' => 'processAutoreturn',
            'paypal_standard_ipn'        => 'processIpn'
        );
    }

    /**
     * Cancel page field options
     */
    public function getCancelPageOptions()
    {
        return Page::getNameList();
    }

    /**
     * Get the URL to Paypal's servers
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

    public function getAutoreturnUrl()
    {
        return $this->makeAccessPointLink('paypal_standard_autoreturn');
    }

    public function getIpnUrl()
    {
        return $this->makeAccessPointLink('paypal_standard_ipn');
    }

    public function getHiddenFields($invoice)
    {
        $host = $this->getHostObject();
        $result = [];

        /*
         * Billing information
         */
        $customerDetails = (object) $invoice->getCustomerDetails();

        $result['first_name'] = $customerDetails->first_name;
        $result['last_name'] = $customerDetails->last_name;
        $result['address1'] = $customerDetails->street_addr;
        $result['city'] = $customerDetails->city;
        $result['country'] = $customerDetails->country;
        $result['state'] = $customerDetails->state;
        $result['zip'] = $customerDetails->zip;
        $result['night_phone_a'] = $customerDetails->phone;

        /*
         * Invoice items
         */
        $itemIndex = 1;
        foreach ($invoice->getLineItemDetails() as $item) {
            $item = (object) $item;
            $result['item_name_'.$itemIndex] = $item->description;
            $result['amount_'.$itemIndex] = round($item->price, 2);
            $result['quantity_'.$itemIndex] = $item->quantity;
            $itemIndex++;
        }

        $totals = (object) $invoice->getTotalDetails();
        $invoiceId = $invoice->getUniqueId();
        $invoiceHash = $invoice->getUniqueHash();

        /*
         * Payment set up
         */
        $result['no_shipping'] = 1;
        $result['cmd'] = '_cart';
        $result['upload'] = 1;
        $result['tax_cart'] = number_format($totals->tax, 2, '.', '');
        $result['invoice'] = $invoiceId;
        $result['business'] = $host->business_email;
        $result['currency_code'] = $totals->currency;
        $result['tax'] = number_format($totals->tax, 2, '.', '');

        $result['notify_url'] = $this->getIpnUrl().'/'.$invoiceHash;
        $result['return'] = $this->getAutoreturnUrl().'/'.$invoiceHash;

        if ($host->cancel_page) {
            $result['cancel_return'] = Page::url($host->cancel_page, [
                'invoice_id' => $invoiceId,
                'invoice_hash' => $invoiceHash
            ]);
        }

        $result['bn'] = 'October.Responsiv.Pay.Plugin';
        $result['charset'] = 'utf-8';

        foreach ($result as $key => $value) {
            $result[$key] = str_replace("\n", ' ', $value);
        }

        return $result;
    }

    public function processPaymentForm($data, $invoice)
    {
        /*
         * We do not need any code here since payments are processed on PayPal server.
         */
    }

    public function processIpn($params)
    {
        try {
            $invoice = null;

            sleep(5);

            $hash = array_key_exists(0, $params) ? $params[0] : null;
            if (!$hash)
                throw new ApplicationException('Invoice not found');

            $invoice = $this->createInvoiceModel()->findByUniqueHash($hash);
            if (!$invoice)
                throw new ApplicationException('Invoice not found');

            if (!$paymentMethod = $invoice->getPaymentMethod())
                throw new ApplicationException('Payment method not found');

            if ($paymentMethod->getGatewayClass() != 'Responsiv\Pay\PaymentTypes\PaypalStandard')
                throw new ApplicationException('Invalid payment method');

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

            if (!$invoice->isPaymentProcessed(true)) {
                if (input('mc_gross') != $this->getPaypalTotal($invoice)) {
                    $invoice->logPaymentAttempt('Invalid invoice total received via IPN: '.input('mc_gross'), 0, [], $_POST, $response);
                }
                else {

                    if (strpos($response, 'VERIFIED') !== false) {
                        if ($invoice->markAsPaymentProcessed()) {
                            $invoice->logPaymentAttempt('Successful payment', 1, [], $_POST, $response);
                            $invoice->updateInvoiceStatus($paymentMethod->invoice_status);
                        }
                    } else {
                        $invoice->logPaymentAttempt('Invalid payment notification', 0, [], $_POST, $response);
                    }
                }
            }
        }
        catch (Exception $ex) {
            if ($invoice) {
                $invoice->logPaymentAttempt($ex->getMessage(), 0, [], $_POST, null);
            }

            throw new ApplicationException($ex->getMessage());
        }
    }

    public function processAutoreturn($params)
    {
        try {
            $invoice = null;
            $response = null;

            $hash = array_key_exists(0, $params) ? $params[0] : null;
            if (!$hash) {
                throw new ApplicationException('Invoice not found');
            }

            $invoice = $this->createInvoiceModel()->findByUniqueHash($hash);
            if (!$invoice) {
                throw new ApplicationException('Invoice not found');
            }

            if (!$paymentMethod = $invoice->getPaymentMethod()) {
                throw new ApplicationException('Payment method not found');
            }

            if ($paymentMethod->getGatewayClass() != 'Responsiv\Pay\PaymentTypes\PaypalStandard') {
                throw new ApplicationException('Invalid payment method');
            }

            /*
             * PDT request
             */
            if (!$invoice->isPaymentProcessed(true)) {
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

                /*
                 * Mark invoice as paid
                 */
                if (strpos($response, 'SUCCESS') !== false) {
                    $matches = [];

                    if (!preg_match('/^invoice=(.*)$/m', $response, $matches)) {
                        throw new ApplicationException('Invalid response');
                    }

                    if (trim($matches[1]) != $invoice->getUniqueId()) {
                        throw new ApplicationException('Invalid invoice number');
                    }

                    if (!preg_match('/^mc_gross=([0-9\.]+)$/m', $response, $matches)) {
                        throw new ApplicationException('Invalid response');
                    }

                    if ($matches[1] != $this->getPaypalTotal($invoice)) {
                        throw new ApplicationException('Invalid invoice total - invoice total received is '.$matches[1]);
                    }

                    if ($invoice->markAsPaymentProcessed()) {
                        $invoice->logPaymentAttempt('Successful payment', 1, [], $_GET, $response);
                        $invoice->updateInvoiceStatus($paymentMethod->invoice_status);
                    }
                }
            }

            $googleTrackingCode = 'utm_nooverride=1';
            if (!$returnPage = $invoice->getReceiptUrl()) {
                throw new ApplicationException('PayPal Standard Receipt page is not found');
            }

            return Redirect::to($returnPage.'?'.$googleTrackingCode);
        }
        catch (Exception $ex)
        {
            if ($invoice) {
                $invoice->logPaymentAttempt($ex->getMessage(), 0, [], $_GET, $response);
            }

            throw new ApplicationException($ex->getMessage());
        }
    }

    /**
     * Communicate with the paypal endpoint
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
     * Used to determine the invoice total as seen by PayPal
     */
    private function getPaypalTotal($invoice)
    {
        $invoiceTotal = 0;

        // Add up individual invoice items
        foreach ($invoice->getLineItemDetails() as $item) {
            $item = (object) $item;
            $itemPrice = round($item->price, 2);
            $invoiceTotal = $invoiceTotal + ($item->quantity * $itemPrice);
        }

        // Invoice items tax
        $itemTax = round($invoice->tax, 2);
        $invoiceTotal = $invoiceTotal + $itemTax;

        return $invoiceTotal;
    }

}
