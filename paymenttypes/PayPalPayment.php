<?php namespace Responsiv\Pay\PaymentTypes;

use Log;
use Html;
use Http;
use Currency;
use Response;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use Illuminate\Http\Exceptions\HttpResponseException;
use ApplicationException;
use SystemException;
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
        return $this->getHostObject()->test_mode
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * getPayPalNamespace
     */
    public function getPayPalNamespace(): string
    {
        return 'paypal';
    }

    /**
     * renderPaymentScripts
     */
    public function renderPaymentScripts()
    {
        $queryParams = http_build_query([
            'client-id' => 'test',
            'components' => 'buttons',
            'enable-funding' => 'venmo',
            'disable-funding' => 'paylater,card',
        ]);

        $scriptParams = [
            'data-sdk-integration-source' => 'integrationbuilder_sc',
            'data-namespace' => $this->getPayPalNamespace()
        ];

        return Html::script("https://www.paypal.com/sdk/js?{$queryParams}", $scriptParams);
    }

    /**
     * processPaymentForm
     */
    public function processPaymentForm($data, $invoice)
    {
        // We do not need any code here since payments are processed on PayPal server.
    }

    /**
     * processApiInvoices create an order to start the transaction.
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_create
     */
    public function processApiInvoices($params)
    {
        $invoice = $this->findInvoiceFromHash($params[0] ?? '');

        $paymentMethod = $invoice->getPaymentMethod();

        $token = $paymentMethod->generatePayPalAccessToken();

        $totals = $invoice->getTotalDetails();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $totals['currency'] ?? 'USD',
                        'value' => Currency::fromBaseValue($totals['total'])
                    ]
                ]
            ],
        ];

        try {
            $baseUrl = $paymentMethod->getPayPalEndpoint();

            $response = Http::withToken($token)
                ->post("{$baseUrl}/v2/checkout/orders", $payload);

            return Response::json($response->json(), $response->status());
        }
        catch (Exception $ex) {
            Log::error($ex);
            $this->throwResponseError('Failed to create order');
        }
    }

    /**
     * processApiInvoiceCapture captures payment for the created order to complete the transaction.
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_capture
     */
    public function processApiInvoiceCapture($params)
    {
        $invoice = $this->findInvoiceFromHash($params[0] ?? '');

        $paymentMethod = $invoice->getPaymentMethod();

        $token = $paymentMethod->generatePayPalAccessToken();

        $orderId = $params[1] ?? '';

        try {
            $baseUrl = $paymentMethod->getPayPalEndpoint();

            $response = Http::withToken($token)
                ->post("{$baseUrl}/v2/checkout/orders/{$orderId}/capture");

            return Response::json($response->json(), $response->status());
        }
        catch (Exception $ex) {
            Log::error($ex);
            $this->throwResponseError('Failed to create order');
        }
    }

    /**
     * generatePayPalAccessToken generate an OAuth 2.0 access token for authenticating with PayPal REST APIs.
     * @see https://developer.paypal.com/api/rest/authentication/
     */
    public function generatePayPalAccessToken()
    {
        $host = $this->getHostObject();
        $baseUrl = $this->getPayPalEndpoint();

        try {
            $response = Http::asForm()
                ->withBasicAuth($host->client_id, $host->client_secret)
                ->post("{$baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials'
                ]);

            return $response->json('access_token');
        }
        catch (Exception $ex) {
            Log::error($ex);
            $this->throwResponseError('Failed to generate access token');
        }
    }

    /**
     * findInvoiceFromHash
     */
    protected function findInvoiceFromHash($hash)
    {
        if (!$hash) {
            $this->throwResponseError('Invoice not found');
        }

        $invoice = $this->createInvoiceModel()->findByUniqueHash($hash);
        if (!$invoice) {
            $this->throwResponseError('Invoice not found');
        }

        $paymentMethod = $invoice->getPaymentMethod();
        if (!$paymentMethod) {
            $this->throwResponseError('Payment method not found');
        }

        if ($paymentMethod->getDriverClass() !== static::class) {
            $this->throwResponseError('Invalid payment method');
        }

        return $invoice;
    }

    /**
     * throwResponseError
     */
    protected function throwResponseError($message)
    {
        throw new HttpResponseException(Response::json(['error' => $message], 500));
    }
}
