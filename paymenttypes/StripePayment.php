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
     * {@inheritDoc}
     */
    public function registerAccessPoints()
    {
        return [
            'stripe_autoreturn' => 'processAutoreturn',
        ];
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
        try {
            $invoice = $this->findInvoiceFromHash(post('invoice_hash'));
            if ($invoice->isPaymentProcessed()) {
                throw new ApplicationException('Invoice already paid');
            }

            $host = $this->getHostObject();
            $baseUrl = $this->getStripeEndpoint();
            $totals = $invoice->getTotalDetails();
            $successUrl = $this->makeAccessPointLink('stripe_autoreturn');

            $payload = [
                'mode' => 'payment',
                'client_reference_id' => $invoice->getUniqueId(),
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'product_data' => [
                                'name' => 'Invoice ' . $invoice->getUniqueId(),
                                'description' => $this->getInvoiceDescription($invoice)
                            ],
                            'unit_amount' => (int) $totals['total'],
                            'currency' => $totals['currency'] ?? 'USD'
                        ]
                    ]
                ],
                'success_url' => "{$successUrl}/{$invoice->hash}/{CHECKOUT_SESSION_ID}",
                'cancel_url' => $invoice->getReceiptUrl()
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
                throw new ApplicationException($errorMessage);
            }

            return Redirect::to($response->json('url'));
        }
        catch (ApplicationException $ex) {
            throw $ex;
        }
        catch (Exception $ex) {
            Log::error($ex);
            throw new ApplicationException('Failed to create order');
        }
    }

    /**
     * processAutoreturn
     */
    public function processAutoreturn($params)
    {
        try {
            $invoice = $this->findInvoiceFromHash($params[0] ?? '');
            $sessionId = $params[1] ?? '';
            if ($invoice->isPaymentProcessed() || !$sessionId) {
                return Redirect::to($invoice->getReceiptUrl());
            }

            $paymentMethod = $invoice->getPaymentMethod();
            $totals = $invoice->getTotalDetails();
            $baseUrl = $this->getStripeEndpoint();

            $response = Http::withBasicAuth($paymentMethod->secret_key, '')
                ->get("{$baseUrl}/v1/checkout/sessions/{$sessionId}");

            if (!$response->successful()) {
                $errorMessage = $response->json('error.message');
                $invoice->logPaymentAttempt($errorMessage, false, $params, $response->json(), '');
                throw new ApplicationException($errorMessage);
            }
            elseif (!$invoice->isPaymentProcessed(true)) {
                if ($response->json('status') !== 'complete') {
                    throw new ApplicationException('Invalid response');
                }

                if ($response->json('client_reference_id') !== $invoice->getUniqueId()) {
                    throw new ApplicationException('Invalid invoice number');
                }

                if (($matchedValue = $response->json('amount_total')) !== (int) $totals['total']) {
                    throw new ApplicationException('Invalid invoice total - order total received is: ' . e($matchedValue));
                }

                $transactionStatus = $response->json('status');
                $transactionId = $response->json('id');
                $invoice->logPaymentAttempt("Transaction {$transactionStatus}: {$transactionId}", true, $params, $response->json(), '');
                $invoice->markAsPaymentProcessed();
            }

            return Redirect::to($invoice->getReceiptUrl());
        }
        catch (ApplicationException $ex) {
            throw $ex;
        }
        catch (Exception $ex) {
            Log::error($ex);
            throw new ApplicationException('Failed to capture a valid order');
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
