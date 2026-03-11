<?php namespace Responsiv\Pay\PaymentTypes;

use Log;
use Html;
use Http;
use Currency;
use Response;
use Cms\Classes\Page;
use Responsiv\Pay\Classes\GatewayBase;
use Responsiv\Pay\Models\InvoiceStatus;
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
            'paypal_rest_invoice_capture' => 'processApiInvoiceCapture',
            'paypal_rest_webhook' => 'processWebhook'
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
            'client-id' => $this->getHostObject()->client_id,
            'currency' => Currency::getActiveCode(),
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
                // Order already captured (e.g. duplicate callback) - treat as success
                if ($response->json('details.0.issue') === 'ORDER_ALREADY_CAPTURED') {
                    return Response::json(['cms_redirect' => $invoice->getReceiptUrl()] + ($response->json() ?: []), 200);
                }

                $errorIssue = $response->json('details.0.issue');
                $errorDescription = $response->json('details.0.description');
                $invoice->logPaymentAttempt("{$errorIssue} {$errorDescription}", false, $payload, $response->json(), '');
                throw new Exception("{$errorIssue} {$errorDescription}");
            }
            elseif (!$invoice->isPaymentProcessed(true)) {
                $validStatuses = ['COMPLETED', 'PENDING'];

                if (!in_array($response->json('status'), $validStatuses)) {
                    throw new ApplicationException('Invalid response status: ' . $response->json('status'));
                }

                if ($response->json('purchase_units.0.reference_id') !== $invoice->getUniqueId()) {
                    throw new ApplicationException('Invalid invoice number');
                }

                if (!in_array($response->json('purchase_units.0.payments.captures.0.status'), $validStatuses)) {
                    throw new ApplicationException('Invalid capture status: ' . $response->json('purchase_units.0.payments.captures.0.status'));
                }

                $matchedValue = $response->json('purchase_units.0.payments.captures.0.amount.value');
                $expectedValue = Currency::fromBaseValue($totals['total']);
                if (!empty($expectedValue) && strpos($expectedValue, ',') !== false) {
                    $expectedValue = str_replace(',', '.', $expectedValue);
                }
                if ($matchedValue !== $expectedValue) {
                    throw new ApplicationException('Invalid invoice total - order total received is: ' . e($matchedValue) . ', expected: ' . e($expectedValue));
                }

                $captureStatus = $response->json('purchase_units.0.payments.captures.0.status');
                $transactionId = $response->json('id');
                $invoice->logPaymentAttempt("Transaction {$captureStatus}: {$transactionId}", true, $payload, $response->json(), '');

                if ($captureStatus === 'COMPLETED') {
                    $invoice->markAsPaymentProcessed();
                }
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
     * getWebhookUrl
     */
    public function getWebhookUrl()
    {
        return $this->makeAccessPointLink('paypal_rest_webhook');
    }

    /**
     * processWebhook handles asynchronous PayPal webhook events, such as
     * PAYMENT.CAPTURE.COMPLETED for pending transactions and
     * PAYMENT.CAPTURE.REFUNDED for refunds.
     * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post
     */
    public function processWebhook($params)
    {
        try {
            $host = $this->getHostObject();
            $webhookId = $host->webhook_id;
            if (!$webhookId) {
                return Response::make('Webhook not configured', 200);
            }

            $body = request()->getContent();
            $event = json_decode($body, true);
            if (!$event) {
                return Response::make('Invalid payload', 400);
            }

            // Verify webhook signature
            if (!$this->verifyWebhookSignature($webhookId, $body)) {
                Log::warning('PayPal webhook signature verification failed');
                return Response::make('Invalid signature', 403);
            }

            $eventType = $event['event_type'] ?? '';
            $resourceId = $event['resource']['id'] ?? null;

            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    if ($invoice = $this->findInvoiceFromWebhookEvent($event)) {
                        if (!$invoice->isPaymentProcessed()) {
                            $invoice->logPaymentAttempt(
                                "Webhook PAYMENT.CAPTURE.COMPLETED: {$resourceId}",
                                true, [], $event, $body
                            );
                            $invoice->markAsPaymentProcessed();
                        }
                    }
                    break;

                case 'PAYMENT.CAPTURE.REFUNDED':
                    if ($invoice = $this->findInvoiceFromWebhookEvent($event)) {
                        $invoice->logPaymentAttempt(
                            "Webhook PAYMENT.CAPTURE.REFUNDED: {$resourceId}",
                            true, [], $event, $body
                        );
                        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_REFUNDED, 'Refunded via PayPal');
                    }
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                    if ($invoice = $this->findInvoiceFromWebhookEvent($event)) {
                        $invoice->logPaymentAttempt(
                            "Webhook PAYMENT.CAPTURE.DENIED: {$resourceId}",
                            false, [], $event, $body
                        );
                        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_VOID, 'Payment denied by PayPal');
                    }
                    break;
            }

            return Response::make('OK', 200);
        }
        catch (Exception $ex) {
            Log::error('PayPal webhook error: ' . $ex->getMessage());
            return Response::make('Error', 500);
        }
    }

    /**
     * findInvoiceFromWebhookEvent locates the invoice associated with a
     * PayPal webhook event by checking resource fields and falling back
     * to an order lookup.
     */
    protected function findInvoiceFromWebhookEvent(array $event)
    {
        $resource = $event['resource'] ?? [];

        $referenceId = $resource['custom_id']
            ?? $resource['invoice_id']
            ?? null;

        if (!$referenceId) {
            $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            if ($orderId) {
                $referenceId = $this->lookupReferenceIdFromOrder($orderId);
            }
        }

        if (!$referenceId) {
            return null;
        }

        return $this->createInvoiceModel()->findByUniqueId($referenceId);
    }

    /**
     * verifyWebhookSignature verifies the PayPal webhook signature using the
     * PayPal verification API endpoint.
     * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post
     */
    protected function verifyWebhookSignature(string $webhookId, string $body): bool
    {
        $headers = request()->headers;

        $payload = [
            'auth_algo' => $headers->get('paypal-auth-algo'),
            'cert_url' => $headers->get('paypal-cert-url'),
            'transmission_id' => $headers->get('paypal-transmission-id'),
            'transmission_sig' => $headers->get('paypal-transmission-sig'),
            'transmission_time' => $headers->get('paypal-transmission-time'),
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($body, true),
        ];

        $token = $this->generatePayPalAccessToken();
        $baseUrl = $this->getPayPalEndpoint();

        $response = Http::withToken($token)
            ->post("{$baseUrl}/v1/notifications/verify-webhook-signature", $payload);

        return $response->successful()
            && $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * lookupReferenceIdFromOrder fetches the reference_id from a PayPal order
     * when the webhook event doesn't include it directly.
     */
    protected function lookupReferenceIdFromOrder(string $orderId): ?string
    {
        try {
            $token = $this->generatePayPalAccessToken();
            $baseUrl = $this->getPayPalEndpoint();

            $response = Http::withToken($token)
                ->get("{$baseUrl}/v2/checkout/orders/{$orderId}");

            if ($response->successful()) {
                return $response->json('purchase_units.0.reference_id');
            }
        }
        catch (Exception $ex) {
            Log::warning('PayPal order lookup failed: ' . $ex->getMessage());
        }

        return null;
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
