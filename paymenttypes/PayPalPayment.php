<?php namespace Responsiv\Pay\PaymentTypes;

use Http;
use Redirect;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use ApplicationException;
use Exception;

/**
 * PayPalPayment
 */
class PayPalPayment extends GatewayBase
{
    /**
     * {@inheritDoc}
     */
    public function driverDetails()
    {
        return [
            'name' => 'PayPal',
            'description' => 'Accept payments using the PayPal REST API.'
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
        $host->rules['client_id'] = ['required'];
        $host->rules['client_secret'] = ['required'];

        if (!$host->exists) {
            $host->name = 'PayPal';
            $host->test_mode = true;
            $host->invoice_status = 'paid';
        }
    }

    /**
     * getInvoiceStatusOptions for status field options.
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
        return [
            'paypal_rest_invoices' => 'processApiInvoices',
            'paypal_rest_invoice_capture' => 'processApiInvoiceCapture'
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
     * getInvoicesUrl
     */
    public function getInvoicesUrl()
    {
        return $this->makeAccessPointLink('paypal_rest_invoices');
    }

    /**
     * getInvoiceCaptureUrl
     */
    public function getInvoiceCaptureUrl()
    {
        return $this->makeAccessPointLink('paypal_rest_invoice_capture');
    }

    /**
     * getPayPalEndpoint
     */
    public function getPayPalEndpoint()
    {
        $this->getHostObject()->test_mode
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
    }

    /**
     * getInvoiceBodyFields
     */
    public function getInvoiceBodyFields($invoice)
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
    public function processPaymentForm($data, $invoice)
    {
        // We do not need any code here since payments are processed on PayPal server.
    }

    /**
     * processApiInvoices
     */
    public function processApiInvoices($params)
    {
    }

    /**
     * processApiInvoiceCapture
     */
    public function processApiInvoiceCapture($params)
    {
    }
}
