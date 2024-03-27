<?php namespace Responsiv\Pay\Components;

use Auth;
use Redirect;
use Cms\Classes\ComponentBase;
use RainLab\User\Models\User;
use Responsiv\Pay\Models\PaymentMethod;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Illuminate\Http\RedirectResponse;
use ApplicationException;

/**
 * Payment screen for an existing invoice.
 */
class Payment extends ComponentBase
{
    /**
     * @var \Responsiv\Pay\Models\Invoice invoice object
     */
    protected $invoice;

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name' => 'Payment',
            'description' => 'Payment screen for an existing invoice.'
        ];
    }

    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [
            'isDefault' => [
                'title' => 'Default View',
                'type' => 'checkbox',
                'description' => 'Used as default entry point when paying an invoice.',
                'showExternalParam' => false
            ],
        ];
    }

    /**
     * onRun
     */
    public function onRun()
    {
        $this->prepareVars();
    }

    /**
     * prepareVars
     */
    protected function prepareVars()
    {
        $this->page['invoice'] = $this->invoice();
        $this->page['paymentMethods'] = $this->listAvailablePaymentMethods();
    }

    /**
     * invoice
     */
    public function invoice()
    {
        return $this->invoice ??= InvoiceModel::findByInvoiceHash($this->param('hash'));
    }

    /**
     * onUpdatePaymentMethod
     */
    public function onUpdatePaymentMethod()
    {
        $invoice = $this->invoice();
        if (!$invoice) {
            return;
        }

        $invoice->payment_method_id = post('payment_method');
        $invoice->unsetRelation('payment_method');
        $invoice->save();

        $this->prepareVars();
    }

    /**
     * listAvailablePaymentMethods
     */
    protected function listAvailablePaymentMethods()
    {
        $result = [];

        $invoice = $this->invoice();
        if (!$invoice) {
            return [];
        }

        $availableOptions = PaymentMethod::listApplicable([
            'countryId' => $invoice->country_id,
            'totalPrice' => $invoice->total
        ]);

        foreach ($availableOptions as $key => $option) {
            $result[$key] = $option;
        }

        return $result;
    }

    /**
     * onPay
     */
    public function onPay($invoice = null)
    {
        if (!$invoice = $this->invoice()) {
            return;
        }

        if (!$paymentMethod = $invoice->payment_method) {
            return;
        }

        // Pay from profile
        if (post('pay_from_profile')) {
            $redirect = true;

            if (!$user = $this->user()) {
                throw new ApplicationException('Please log in to pay using the stored details');
            }

            if ($invoice->user_id != $user->id) {
                throw new ApplicationException('The invoice does not belong to your account');
            }

            $paymentMethod->payFromProfile($invoice);
        }
        else {
            $redirect = $paymentMethod->processPaymentForm(post(), $invoice);
        }

        // Custom response
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }
        elseif ($redirect === false) {
            return;
        }

        // Standard response
        if (!$receiptPage = $invoice->getReceiptUrl()) {
            return;
        }

        return Redirect::to($receiptPage);
    }

    /**
     * user returns the logged in user
     */
    public function user(): ?User
    {
        return Auth::user();
    }
}
