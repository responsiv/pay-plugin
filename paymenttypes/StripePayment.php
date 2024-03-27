<?php namespace Responsiv\Pay\PaymentTypes;

use Log;
use Http;
use Redirect;
use Responsiv\Pay\Classes\GatewayBase;
use ApplicationException;
use ValidationException;
use Exception;

/**
 * StripePayment
 */
class StripePayment extends GatewayBase
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
            'name' => 'Stripe',
            'description' => 'Accept payments using Stripe.'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function initDriverHost($host)
    {
        $host->rules['secret_key'] = 'required';
        $host->bindEvent('model.beforeValidate', [$this, 'validateClientSecret']);

        if (!$host->exists) {
            $host->name = 'Stripe';
            $host->test_mode = true;
        }
    }

    /**
     * validateClientSecret
     */
    public function validateClientSecret()
    {
        $host = $this->getHostObject();

        if (!$host->secret_key) {
            return;
        }

        if ($host->test_mode && !str_starts_with($host->secret_key, 'sk_test_')) {
            throw new ValidationException(['secret_key' => "Test secret key must start with 'sk_test_'."]);
        }

        if (!$host->test_mode && !str_starts_with($host->secret_key, 'sk_live_')) {
            throw new ValidationException(['secret_key' => "Production secret key must start with 'sk_live_'."]);
        }
    }

    /**
     * getHiddenFields
     */
    public function getHiddenFields($invoice)
    {
        return [
            'invoice_hash' => $invoice->hash
        ];
    }

    /**
     * getStripeEndpoint
     */
    public function getStripeEndpoint()
    {
        return 'https://api.stripe.com';
    }

    /**
     * processPaymentForm
     */
    public function processPaymentForm($data, $order)
    {
        $invoice = $this->findInvoiceFromHash(post('invoice_hash'));

        $totals = $invoice->getTotalDetails();

        if ($invoice->isPaymentProcessed()) {
            throw new ApplicationException('Invoice already paid');
        }

        try {
            $host = $this->getHostObject();
            $baseUrl = $this->getStripeEndpoint();
            $receiptUrl = $invoice->getReceiptUrl();

            $payload = [
                'mode' => 'payment',
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'product_data' => [
                                'name' => 'Invoice ' .$invoice->getUniqueId(),
                                'description' => $this->getInvoiceDescription($invoice)
                            ],
                            'unit_amount' => (int) $totals['total'],
                            'currency' => $totals['currency'] ?? 'USD'
                        ]
                    ]
                ],
                'success_url' => $receiptUrl,
                'cancel_url' => $receiptUrl
            ];

            $response = Http::asForm()
                ->withBasicAuth($host->secret_key, '')
                ->post("{$baseUrl}/v1/checkout/sessions", $payload);

            if ($response->successful()) {
                $transactionId = $response->json('id');
                $invoice->logPaymentAttempt("Checkout Initiated: {$transactionId}", true, $payload, $response->json(), '');
            }
            else {
                $errorMessage = $response->json('error.message');
                $invoice->logPaymentAttempt($errorMessage, false, $payload, $response->json(), '');
                throw new Exception($errorMessage);
            }

            return Redirect::to($response->json('url'));
        }
        catch (Exception $ex) {
            Log::error($ex);
            throw new ApplicationException('Failed to create order');
        }
    }

    /**
     * getInvoiceDescription returns a basic invoice description
     */
    protected function getInvoiceDescription($invoice)
    {
        $items = [];

        foreach ($invoice->items as $item) {
            $items[] = "{$item->quantity}x {$item->description}";
        }

        return implode(', ', $items);
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
}
