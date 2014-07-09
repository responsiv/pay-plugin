<?php namespace Responsiv\Pay\PaymentTypes;

use Backend;
use Cms\Classes\Page;
use Responsiv\Pay\Models\Settings;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Pay\Classes\GatewayBase;
use Cms\Classes\Controller as CmsController;
use System\Classes\ApplicationException;
use October\Rain\Network\Http;

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
    public function defineRelationships($host)
    {
        $host->belongsTo['invoice_status'] = ['Responsiv\Pay\Models\InvoiceStatus'];
    }

    /**
     * {@inheritDoc}
     */
    public function initConfigData($host)
    {
        $host->test_mode = true;
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
    public function getCancelPageOptions($keyValue = -1)
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Get the URL to Paypal's servers
     */
    public function getFormAction($host)
    {
        if ($host->test_mode)
            return "https://www.sandbox.paypal.com/cgi-bin/webscr";
        else
            return "https://www.paypal.com/cgi-bin/webscr";
    }

    public function getHiddenFields($host, $invoice, $isAdmin = false)
    {
        $result = [];

        /*
         * Billing information
         */
        $result['first_name'] = $invoice->first_name;
        $result['last_name'] = $invoice->last_name;

        $result['address1'] = $invoice->street_addr;
        $result['city'] = $invoice->city;

        if ($invoice->country)
            $result['country'] = $invoice->country->code;

        if ($invoice->state)
            $result['state'] = $invoice->state->code;

        $result['zip'] = $invoice->zip;
        $result['night_phone_a'] = $invoice->phone;

        /*
         * Invoice items
         */
        $itemIndex = 1;
        foreach ($invoice->items as $item) {
            $result['item_name_'.$itemIndex] = $item->description;
            $result['amount_'.$itemIndex] = round($item->price, 2);
            $result['quantity_'.$itemIndex] = $item->quantity;
            $itemIndex++;
        }

        /*
         * Payment set up
         */
        $result['no_shipping'] = 1;
        $result['cmd'] = '_cart';
        $result['upload'] = 1;
        $result['tax_cart'] = number_format($invoice->tax, 2, '.', '');
        $result['invoice'] = $invoice->id;
        $result['business'] = $host->business_email;
        $result['currency_code'] = Settings::get('currency_code', 'USD');
        $result['tax'] = number_format($invoice->tax, 2, '.', '');

        $result['notify_url'] = $this->makeAccessPointLink('api_pay_paypal_ipn/'.$invoice->hash);

        if (!$isAdmin) {
            $result['return'] = $this->makeAccessPointLink('api_pay_paypal_autoreturn/'.$invoice->hash);

            if ($host->cancel_page) {
                $controller = new CmsController;
                $result['cancel_return'] = $controller->pageUrl($host->cancel_page, [
                    'invoice_id' => $invoice->id,
                    'invoice_hash' => $invoice->hash
                ]);
            }
        }
        else {
            $result['return'] = $this->makeAccessPointLink('api_pay_paypal_autoreturn/'.$invoice->hash.'/admin');
            $result['cancel_return'] = Backend::url('responsiv/pay/invoices/pay/'.$invoice->id.'?'.uniqid());
        }

        $result['bn'] = 'October.Responsiv.Pay.Plugin';
        $result['charset'] = 'utf-8';

        foreach ($result as $key => $value) {
            $result[$key] = str_replace("\n", ' ', $value);
        }

        return $result;
    }

    public function processPaymentForm($data, $host, $invoice, $isAdmin = false)
    {
        /*
         * We do not need any code here since payments are processed on PayPal server.
         */
    }

    public function processIpn($params)
    {
        try
        {
            $invoice = null;

            sleep(5);

            $hash = array_key_exists(0, $params) ? $params[0] : null;
            if (!$hash)
                throw new ApplicationException('Invoice not found');

            $invoice = Invoice::whereHas($hash)->first();
            if (!$invoice)
                throw new ApplicationException('Invoice not found');

            if (!$invoice->payment_type)
                throw new ApplicationException('Payment method not found');

            if ($invoice->payment_type->class_name != 'Responsiv\Pay\PaymentTypes\PaypalStandard')
                throw new ApplicationException('Invalid payment method');

            $endpoint = $invoice->payment_type->test_mode
                ? "www.sandbox.paypal.com/cgi-bin/webscr"
                : "www.paypal.com/cgi-bin/webscr";

            $fields = $_POST;
            if ($invoice->payment_type->test_mode) {
                foreach ($fields as $key => $value) {
                    // Replace every \n that isn't part of \r\n with \r\n 
                    // to prevent an invalid response from PayPal
                    $fields[$key] = preg_replace("~(?<!\r)\n~","\r\n",$value);
                }
            }

            $fields['cmd'] = '_notify-validate';

            $response = $this->postData($endpoint, $fields);

            if (!$invoice->isPaymentProcessed(true)) {
                if (post('mc_gross') != $this->getPaypalTotal($invoice)) {
                    $this->logPaymentAttempt($invoice, 'Invalid invoice total received via IPN: '.Settings::formatCurrency(post('mc_gross')), 0, [], $_POST, $response);
                }
                else {

                    if (strpos($response, 'VERIFIED') !== false) {
                        if ($invoice->markAsPaymentProcessed()) {
                            $this->logPaymentAttempt($invoice, 'Successful payment', 1, [], $_POST, $response);
                            InvoiceStatusLog::createRecord($invoice->payment_type->invoice_status, $invoice);
                        }
                    } else {
                        $this->logPaymentAttempt($invoice, 'Invalid payment notification', 0, [], $_POST, $response);
                    }
                }
            }
        }
        catch (Exception $ex) {
            if ($invoice)
                $this->logPaymentAttempt($invoice, $ex->getMessage(), 0, [], $_POST, null);

            throw new ApplicationException($ex->getMessage());
        }
    }

    public function processAutoreturn($params)
    {
        try
        {
            $invoice = null;
            $response = null;

            $hash = array_key_exists(0, $params) ? $params[0] : null;
            if (!$hash)
                throw new ApplicationException('Invoice not found');

            $invoice = Invoice::whereHas($hash)->first();
            if (!$invoice)
                throw new ApplicationException('Invoice not found');

            if (!$invoice->payment_type)
                throw new ApplicationException('Payment method not found');

            if ($invoice->payment_type->class_name != 'Responsiv\Pay\PaymentTypes\PaypalStandard')
                throw new ApplicationException('Invalid payment method');

            /*
             * PDT request
             */
            if (!$invoice->isPaymentProcessed(true)) {
                $transaction = post('tx');
                if (!$transaction)
                    throw new ApplicationException('Invalid transaction value');

                $endpoint = $invoice->payment_type->test_mode
                    ? "www.sandbox.paypal.com/cgi-bin/webscr"
                    : "www.paypal.com/cgi-bin/webscr";

                $fields = [
                    'cmd' => '_notify-synch',
                    'tx'  => $transaction,
                    'at'  => $invoice->payment_type->pdt_token
                ];

                $response = $this->postData($endpoint, $fields);

                /*
                 * Mark invoice as paid
                 */
                if (strpos($response, 'SUCCESS') !== false) {
                    $matches = [];

                    if (!preg_match('/^invoice=([0-9]*)/m', $response, $matches))
                        throw new ApplicationException('Invalid response');

                    if ($matches[1] != $invoice->id)
                        throw new ApplicationException('Invalid invoice number');

                    if (!preg_match('/^mc_gross=([0-9\.]+)/m', $response, $matches))
                        throw new ApplicationException('Invalid response');

                    if ($matches[1] != $this->getPaypalTotal($invoice))
                        throw new ApplicationException('Invalid invoice total - invoice total received is '.$matches[1]);

                    if ($invoice->markAsPaymentProcessed()) {
                        $this->logPaymentAttempt($invoice, 'Successful payment', 1, [], $_GET, $response);
                        InvoiceStatusLog::createRecord($invoice->payment_type->invoice_status, $invoice);
                    }
                }
            }

            $googleTrackingCode = 'utm_nooverride=1';
            $returnPage = $invoice->getReceiptUrl();
            if ($returnPage)
                Phpr::$response->redirect($returnPage.'?'.$googleTrackingCode);
            else
                throw new ApplicationException('PayPal Standard Receipt page is not found');

        }
        catch (Exception $ex)
        {
            if ($invoice)
                $this->logPaymentAttempt($invoice, $ex->getMessage(), 0, [], Phpr::$request->get_fields, $response);

            throw new ApplicationException($ex->getMessage());
        }
    }

    public function statusDeletionCheck($host, $status)
    {
        if ($host->invoice_status == $status->id)
            throw new ApplicationException('Status cannot be deleted because it is used in PayPal Standard payment method');
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
        foreach ($invoice->items as $item) {
            $itemPrice = round($item->price, 2);
            $invoiceTotal = $invoiceTotal + ($item->quantity * $itemPrice);
        }

        // Invoice items tax
        $itemTax = round($invoice->tax, 2);
        $invoiceTotal = $invoiceTotal + $itemTax;

        return $invoiceTotal;
    }

}

