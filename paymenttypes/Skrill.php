<?php namespace Responsiv\Pay\PaymentTypes;

use Http;
use Backend;
use Cms\Classes\Page;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Pay\Classes\GatewayBase;
use Cms\Classes\Controller as CmsController;
use ApplicationException;

class Skrill extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Skrill',
            'description' => 'Skrill payment method with payment form hosted on Skrill server'
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
            'skrill_return_url' => 'processReturnUrl',
            'skrill_status_url' => 'processStatusUrl'
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
     * Get the URL to Skrill's servers
     */
    public function getFormAction()
    {
        return "https://www.skrill.com/app/payment.pl";
    }

    public function getReturnUrl()
    {
        return $this->makeAccessPointLink('skrill_return_url');
    }

    public function getStatusUrl()
    {
        return $this->makeAccessPointLink('skrill_status_url');
    }

    public function getHiddenFields($invoice)
    {
        $host = $this->getHostObject();
        $result = [];

        /*
         * Billing information
         */
        $customerDetails = (object) $invoice->getCustomerDetails();

        $result['firstname'] = $customerDetails->first_name;
        $result['lastname'] = $customerDetails->last_name;
        $result['address'] = $customerDetails->street_addr;
        $result['city'] = $customerDetails->city;
        $result['country'] = $customerDetails->country;
        $result['state'] = $customerDetails->state;
        $result['postal_code'] = $customerDetails->zip;
        $result['phone_number'] = $customerDetails->phone;

        /*
         * Invoice items
         */
        $itemIndex = 2;
        foreach ($invoice->getLineItemDetails() as $item) {
            $item = (object) $item;
            $result['amount'.$itemIndex.'description'] = $item->description;
            $result['amount'.$itemIndex] = round($item->price, 2);
            $itemIndex++;
        }

        $totals = (object) $invoice->getTotalDetails();
        $invoiceId = $invoice->getUniqueId();
        $invoiceHash = $invoice->getUniqueHash();

        /*
         * Payment set up
         */
        $result['amount'] = $totals->total;
        $result['transaction_id'] = $invoiceId;
        $result['pay_to_email'] = $host->business_email;
        $result['currency'] = $totals->currency;
        $result['language'] = 'EN';
        $result['merchant_fields'] = "field1";
        $result['field1'] = $invoiceId;
        $result['status_url'] = $this->getStatusUrl().'/'.$invoiceHash;

        $result['return_url'] = $this->getReturnUrl().'/'.$invoiceHash;

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
         * We do not need any code here since payments are processed on Skrill server.
         */
    }

    public function processStatusUrl($params)
    {
        try
        {
            $invoice = null;

            sleep(5);

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

            if ($paymentMethod->getGatewayClass() != 'Responsiv\Pay\PaymentTypes\Skrill') {
                throw new ApplicationException('Invalid payment method');
            }

            /*
             * Validate the Skrill signature
             */
            $fieldString = $_POST['merchant_id'] .
                $_POST['transaction_id'] .
                strtoupper(md5($paymentMethod->secret_word)) .
                $_POST['mb_amount'] .
                $_POST['mb_currency'] .
                $_POST['status'];

            /*
             * Ensure the signature is valid, the status code == 2,
             * and that the money is going to you
             */
            if (strtoupper(md5($fieldString)) == $_POST['md5sig']
                && $_POST['status'] == 2
                && $_POST['pay_to_email'] == $paymentMethod->business_email) {

                // Valid transaction
                if ($invoice->markAsPaymentProcessed()) {
                    $invoice->logPaymentAttempt('Successful payment', 1, [], $_POST, $fieldString);
                    $invoice->updateInvoiceStatus($paymentMethod->invoice_status);
                }
            }
            else {
                // Invalid transaction. Abort
                $invoice->logPaymentAttempt('Invalid payment notification', 0, [], $_POST, $fieldString);
            }
        }
        catch (Exception $ex) {
            if ($invoice) {
                $invoice->logPaymentAttempt($ex->getMessage(), 0, [], $_POST, null);
            }

            throw new ApplicationException($ex->getMessage());
        }
    }

    public function processReturnUrl($params)
    {
        try
        {
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

            if ($paymentMethod->getGatewayClass() != 'Responsiv\Pay\PaymentTypes\Skrill') {
                throw new ApplicationException('Invalid payment method');
            }

            $googleTrackingCode = 'utm_nooverride=1';
            if (!$returnPage = $invoice->getReceiptUrl()) {
                throw new ApplicationException('Skrill Standard Receipt page is not found');
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

}