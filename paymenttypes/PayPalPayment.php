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
use Exception;

/**
 * PayPalPayment
 */
class PayPalPayment extends GatewayBase
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
            'name' => 'PayPal',
            'description' => 'Accept payments using the PayPal REST API.'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function initDriverHost($host)
    {
        $host->rules['client_id'] = 'required';
        $host->rules['client_secret'] = 'required';

        if (!$host->exists) {
            $host->name = 'PayPal';
            $host->test_mode = true;
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
    public function getPayPalEndpoint(): string
    {
        return $this->getHostObject()->test_mode
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * getPayPalClientId
     */
    public function getPayPalClientId(): string
    {
        return $this->getHostObject()->test_mode ? 'test' : $this->getHostObject()->client_id;
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
    public function renderPaymentScripts($currency = 'USD')
    {
        $queryParams = http_build_query([
            'client-id' => $this->getPayPalClientId(),
            'components' => 'buttons',
            'enable-funding' => 'venmo',
            'disable-funding' => 'paylater,card',
            'currency' => $currency,
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
        try {
            $invoice = $this->findInvoiceFromHash($params[0] ?? '');
            if ($invoice->isPaymentProcessed()) {
                throw new ApplicationException('Invoice already paid');
            }

            $paymentMethod = $invoice->getPaymentMethod();
            $token = $paymentMethod->generatePayPalAccessToken();
            $totals = $invoice->getTotalDetails();
            $total = Currency::fromBaseValue($totals['total']) ?? 0;
            //check if the currency has comma as decimal separator
            if (!empty($total) && strpos($total, ',') !== false) {
                $total = str_replace(',', '.', $total);
            }

            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $invoice->getUniqueId(),
                        'amount' => [
                            'currency_code' => $totals['currency'] ?? 'USD',
                            'value' => $total
                        ]
                    ]
                ],
            ];

            $baseUrl = $paymentMethod->getPayPalEndpoint();

            $response = Http::withToken($token)
                ->post("{$baseUrl}/v2/checkout/orders", $payload);

            if ($response->successful()) {
                $transactionId = $response->json('id');
                $invoice->logPaymentAttempt("Checkout Initiated: {$transactionId}", true, $payload, $response->json(), '');
            }
            else {
                $errorIssue = $response->json('details.0.issue');
                $errorDescription = $response->json('details.0.description');
                $invoice->logPaymentAttempt("{$errorIssue} {$errorDescription}", false, $payload, $response->json(), '');
            }

            return Response::json($response->json(), $response->status());
        }
        catch (ApplicationException $ex) {
            throw $this->newResponseError($ex->getMessage());
        }
        catch (Exception $ex) {
            Log::error($ex);
            throw $this->newResponseError('Failed to create order');
        }
    }

    /**
     * processApiInvoiceCapture captures payment for the created order to complete the transaction.
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders_capture
     */
    public function processApiInvoiceCapture($params)
    {
        try {
            $invoice = $this->findInvoiceFromHash($params[0] ?? '');
            $paymentMethod = $invoice->getPaymentMethod();
            $token = $paymentMethod->generatePayPalAccessToken();
            $orderId = $params[1] ?? '';
            $totals = $invoice->getTotalDetails();
            $baseUrl = $paymentMethod->getPayPalEndpoint();

            $payload = [
                'payment_source' => null
            ];

            $response = Http::withToken($token)
                ->post("{$baseUrl}/v2/checkout/orders/{$orderId}/capture", $payload);

            if (!$response->successful()) {
                $errorIssue = $response->json('details.0.issue');
                $errorDescription = $response->json('details.0.description');
                $invoice->logPaymentAttempt("{$errorIssue} {$errorDescription}", false, $payload, $response->json(), '');
                throw new Exception("{$errorIssue} {$errorDescription}");
            }
            elseif (!$invoice->isPaymentProcessed(true)) {
                if ($response->json('status') !== 'COMPLETED') {
                    throw new ApplicationException('Invalid response');
                }

                if ($response->json('purchase_units.0.reference_id') !== $invoice->getUniqueId()) {
                    throw new ApplicationException('Invalid invoice number');
                }

                if ($response->json('purchase_units.0.payments.captures.0.status') !== 'COMPLETED') {
                    throw new ApplicationException('Invalid response');
                }

                if (($matchedValue = $response->json('purchase_units.0.payments.captures.0.amount.value')) !== Currency::fromBaseValue($totals['total'])) {
                    throw new ApplicationException('Invalid invoice total - order total received is: ' . e($matchedValue));
                }

                $transactionStatus = $response->json('status');
                $transactionId = $response->json('id');
                $invoice->logPaymentAttempt("Transaction {$transactionStatus}: {$transactionId}", true, $payload, $response->json(), '');
                $invoice->markAsPaymentProcessed();
            }

            return Response::json(['cms_redirect' => $invoice->getReceiptUrl()] + $response->json(), $response->status());
        }
        catch (ApplicationException $ex) {
            throw $this->newResponseError($ex->getMessage());
        }
        catch (Exception $ex) {
            Log::error($ex);
            throw $this->newResponseError('Failed to capture a valid order');
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

        $response = Http::asForm()
            ->withBasicAuth($host->client_id, $host->client_secret)
            ->post("{$baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials'
            ]);

        return $response->json('access_token');
    }

    /**
     * findInvoiceFromHash
     */
    protected function findInvoiceFromHash($hash)
    {
        if (!$hash) {
            throw new ApplicationException('Invoice not found');
        }

        $invoice = $this->createInvoiceModel()->findByUniqueHash($hash);
        if (!$invoice) {
            throw new ApplicationException('Invoice not found');
        }

        $paymentMethod = $invoice->getPaymentMethod();
        if (!$paymentMethod) {
            throw new ApplicationException('Payment method not found');
        }

        if ($paymentMethod->getDriverClass() !== static::class) {
            throw new ApplicationException('Invalid payment method');
        }

        return $invoice;
    }

    /**
     * newResponseError
     */
    protected function newResponseError($message)
    {
        return new HttpResponseException(Response::json(['error' => $message], 500));
    }
}
