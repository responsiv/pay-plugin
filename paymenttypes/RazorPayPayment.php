<?php namespace Responsiv\Pay\PaymentTypes;

use Log;
use Http;
use Currency;
use Response;
use Responsiv\Pay\Classes\GatewayBase;
use Responsiv\Pay\Models\InvoiceStatus;
use Illuminate\Http\Exceptions\HttpResponseException;
use ApplicationException;
use Exception;

/**
 * RazorPayPayment
 */
class RazorPayPayment extends GatewayBase
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
            'name' => 'RazorPay',
            'description' => 'Accept payments using RazorPay.'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function initDriverHost($host)
    {
        $host->rules['key_id'] = 'required';
        $host->rules['key_secret'] = 'required';

        if (!$host->exists) {
            $host->name = 'RazorPay';
            $host->test_mode = true;
        }
    }

    /**
     * validateDriverHost
     */
    public function validateDriverHost($host)
    {
        if (!$host->key_id) {
            return;
        }

        if ($host->test_mode && !str_starts_with($host->key_id, 'rzp_test_')) {
            throw new \ValidationException(['key_id' => "Test Key ID must start with 'rzp_test_'."]);
        }

        if (!$host->test_mode && !str_starts_with($host->key_id, 'rzp_live_')) {
            throw new \ValidationException(['key_id' => "Production Key ID must start with 'rzp_live_'."]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function registerAccessPoints()
    {
        return [
            'razorpay_create_order' => 'processCreateOrder',
            'razorpay_verify' => 'processVerifyPayment',
            'razorpay_webhook' => 'processWebhook',
        ];
    }

    /**
     * getCreateOrderUrl
     */
    public function getCreateOrderUrl()
    {
        return $this->makeAccessPointLink('razorpay_create_order');
    }

    /**
     * getVerifyUrl
     */
    public function getVerifyUrl()
    {
        return $this->makeAccessPointLink('razorpay_verify');
    }

    /**
     * getWebhookUrl
     */
    public function getWebhookUrl()
    {
        return $this->makeAccessPointLink('razorpay_webhook');
    }

    /**
     * getApiEndpoint
     */
    public function getApiEndpoint($path = '')
    {
        return 'https://api.razorpay.com' . $path;
    }

    /**
     * processPaymentForm
     */
    public function processPaymentForm($data, $invoice)
    {
        // Payments are processed via the RazorPay JS popup
    }

    /**
     * processCreateOrder creates a RazorPay order for the invoice.
     * @see https://razorpay.com/docs/api/orders/
     */
    public function processCreateOrder($params)
    {
        try {
            $invoice = $this->findInvoiceFromHash($params[0] ?? '');
            if ($invoice->isPaymentProcessed()) {
                throw new ApplicationException('Invoice already paid');
            }

            $host = $this->getHostObject();
            $totals = $invoice->getTotalDetails();

            $payload = [
                'amount' => (int) $totals['amount_due'],
                'currency' => $totals['currency'] ?? 'INR',
                'receipt' => $invoice->getUniqueId(),
                'payment_capture' => 1,
            ];

            $response = Http::withBasicAuth($host->key_id, $host->key_secret)
                ->post($this->getApiEndpoint('/v1/orders'), $payload);

            if ($response->successful()) {
                $orderId = $response->json('id');
                $invoice->logPaymentAttempt(
                    "Order Created: {$orderId}",
                    true, $payload, $response->json(), ''
                );
            }
            else {
                $errorDescription = $response->json('error.description', 'Unknown error');
                $invoice->logPaymentAttempt(
                    $errorDescription,
                    false, $payload, $response->json(), ''
                );
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
     * processVerifyPayment verifies the payment signature after the
     * RazorPay popup completes successfully.
     * @see https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/#15-verify-payment-signature
     */
    public function processVerifyPayment($params)
    {
        try {
            $invoice = $this->findInvoiceFromHash($params[0] ?? '');
            if ($invoice->isPaymentProcessed()) {
                return Response::json(['cms_redirect' => $invoice->getReceiptUrl()]);
            }

            $host = $this->getHostObject();

            $paymentId = request()->input('razorpay_payment_id');
            $orderId = request()->input('razorpay_order_id');
            $signature = request()->input('razorpay_signature');

            if (!$paymentId || !$orderId || !$signature) {
                throw new ApplicationException('Missing payment verification data');
            }

            // Verify HMAC-SHA256 signature
            $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $host->key_secret);

            $requestData = compact('paymentId', 'orderId', 'signature');

            if (!hash_equals($expectedSignature, $signature)) {
                $invoice->logPaymentAttempt(
                    'Signature verification failed',
                    false, $requestData, [], ''
                );
                throw new ApplicationException('Payment verification failed');
            }

            $invoice->logPaymentAttempt(
                "Payment Verified: {$paymentId}",
                true, $requestData, [], ''
            );
            $invoice->markAsPaymentProcessed();

            return Response::json(['cms_redirect' => $invoice->getReceiptUrl()]);
        }
        catch (ApplicationException $ex) {
            throw $this->newResponseError($ex->getMessage());
        }
        catch (Exception $ex) {
            Log::error($ex);
            throw $this->newResponseError('Failed to verify payment');
        }
    }

    /**
     * checkPaymentStatus polls RazorPay to check if a pending order has
     * since been captured. Returns true if the payment was confirmed.
     * @see https://razorpay.com/docs/api/orders/#fetch-payments-for-an-order
     */
    public function checkPaymentStatus($invoice): bool
    {
        $paymentLog = $invoice->payment_log()
            ->where('is_success', true)
            ->latest()
            ->first();

        $orderId = $paymentLog?->response_data['id'] ?? null;
        if (!$orderId) {
            return false;
        }

        $host = $this->getHostObject();

        $response = Http::withBasicAuth($host->key_id, $host->key_secret)
            ->get($this->getApiEndpoint("/v1/orders/{$orderId}/payments"));

        if (!$response->successful()) {
            return false;
        }

        $items = $response->json('items') ?? [];
        foreach ($items as $payment) {
            if (($payment['status'] ?? '') === 'captured' && !$invoice->isPaymentProcessed()) {
                $invoice->logPaymentAttempt(
                    "Status Check CAPTURED: {$payment['id']}",
                    true, [], $payment, ''
                );
                $invoice->markAsPaymentProcessed();
                return true;
            }
        }

        return false;
    }

    /**
     * processWebhook handles asynchronous RazorPay webhook events.
     * @see https://razorpay.com/docs/webhooks/
     */
    public function processWebhook($params)
    {
        try {
            $host = $this->getHostObject();
            $webhookSecret = $host->webhook_secret;
            if (!$webhookSecret) {
                return Response::make('Webhook not configured', 200);
            }

            $body = request()->getContent();
            $actualSignature = request()->header('X-Razorpay-Signature');

            if (!$actualSignature) {
                return Response::make('Missing signature', 400);
            }

            $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
            if (!hash_equals($expectedSignature, $actualSignature)) {
                Log::warning('RazorPay webhook signature verification failed');
                return Response::make('Invalid signature', 403);
            }

            $event = json_decode($body, true);
            if (!$event) {
                return Response::make('Invalid payload', 400);
            }

            $eventType = $event['event'] ?? '';

            switch ($eventType) {
                case 'payment.captured':
                    $payment = $event['payload']['payment']['entity'] ?? [];
                    if ($invoice = $this->findInvoiceFromWebhookPayment($payment)) {
                        if (!$invoice->isPaymentProcessed()) {
                            $invoice->logPaymentAttempt(
                                "Webhook payment.captured: {$payment['id']}",
                                true, [], $event, $body
                            );
                            $invoice->markAsPaymentProcessed();
                        }
                    }
                    break;

                case 'payment.failed':
                    $payment = $event['payload']['payment']['entity'] ?? [];
                    if ($invoice = $this->findInvoiceFromWebhookPayment($payment)) {
                        $invoice->logPaymentAttempt(
                            "Webhook payment.failed: {$payment['id']}",
                            false, [], $event, $body
                        );
                    }
                    break;

                case 'refund.processed':
                    $refund = $event['payload']['refund']['entity'] ?? [];
                    $paymentId = $refund['payment_id'] ?? null;
                    if ($paymentId) {
                        $payment = $event['payload']['payment']['entity']
                            ?? ['order_id' => null, 'id' => $paymentId];
                        if ($invoice = $this->findInvoiceFromWebhookPayment($payment)) {
                            $invoice->logPaymentAttempt(
                                "Webhook refund.processed: {$refund['id']}",
                                true, [], $event, $body
                            );
                            $invoice->updateInvoiceStatus(
                                InvoiceStatus::STATUS_REFUNDED,
                                'Refunded via RazorPay'
                            );
                        }
                    }
                    break;
            }

            return Response::make('OK', 200);
        }
        catch (Exception $ex) {
            Log::error('RazorPay webhook error: ' . $ex->getMessage());
            return Response::make('Error', 500);
        }
    }

    /**
     * findInvoiceFromWebhookPayment locates the invoice associated with
     * a RazorPay payment entity by fetching the order receipt.
     */
    protected function findInvoiceFromWebhookPayment(array $payment)
    {
        $orderId = $payment['order_id'] ?? null;
        if (!$orderId) {
            return null;
        }

        try {
            $host = $this->getHostObject();
            $response = Http::withBasicAuth($host->key_id, $host->key_secret)
                ->get($this->getApiEndpoint("/v1/orders/{$orderId}"));

            if (!$response->successful()) {
                return null;
            }

            $receipt = $response->json('receipt');
            if (!$receipt) {
                return null;
            }

            return $this->createInvoiceModel()->findByUniqueId($receipt);
        }
        catch (Exception $ex) {
            Log::warning('RazorPay order lookup failed: ' . $ex->getMessage());
            return null;
        }
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