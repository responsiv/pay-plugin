<?php namespace Responsiv\Pay\Components;

use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use Illuminate\Http\RedirectResponse;
use ApplicationException;

class Payment extends ComponentBase
{
    /**
     * @var Responsiv\Pay\Models\Invoice Cached object
     */
    protected $invoice;

    public function componentDetails()
    {
        return [
            'name'        => 'Payment Component',
            'description' => 'Allows the payment of an invoice by its hash'
        ];
    }

    public function defineProperties()
    {
        return [
            'hash' => [
                'title'       => 'Invoice Hash',
                'description' => 'The URL route parameter used for looking up the invoice by its hash.',
                'default'     => '{{ :hash }}',
                'type'        => 'string'
            ],
            'invoicePage' => [
                'title'       => 'Invoice page',
                'description' => 'Name of the invoice page file for the invoice links.',
                'type'        => 'dropdown'
            ],
        ];
    }

    public function getPropertyOptions($property)
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->page['invoice'] = $this->invoice();
        $this->page['invoicePage'] = $this->invoicePage();
        $this->page['paymentMethods'] = $this->paymentMethods();
        $this->page['paymentMethod'] = $this->paymentMethod();

        if (post('submit_payment')) {
            $this->onPay();
        }
    }

    public function invoice()
    {
        if ($this->invoice !== null) {
            return $this->invoice;
        }

        if (!$hash = $this->property('hash')) {
            return null;
        }

        $invoice = InvoiceModel::whereHash($hash)->first();

        if ($invoice) {
            $invoice->setUrlPageName($this->invoicePage());
        }

        return $this->invoice = $invoice;
    }

    public function invoicePage()
    {
        return $this->property('invoicePage');
    }

    public function paymentMethod()
    {
        return ($invoice = $this->invoice()) ? $invoice->payment_method : null;
    }

    public function paymentMethods()
    {
        $countryId = ($invoice = $this->invoice()) ? $invoice->country_id : null;

        return TypeModel::listApplicable($countryId);
    }

    //
    // AJAX
    //

    public function onUpdatePaymentType()
    {
        if (!$invoice = $this->invoice()) {
            throw new ApplicationException('Invoice not found!');
        }

        if (!$methodId = post('payment_method')) {
            throw new ApplicationException('Payment type not specified!');
        }

        if (!$method = TypeModel::find($methodId)) {
            throw new ApplicationException('Payment type not found!');
        }

        $invoice->payment_method = $method;
        $invoice->save();

        $this->page['invoice'] = $invoice;
        $this->page['paymentMethod'] = $method;
    }

    public function onPay($invoice = null)
    {
        if (!$invoice = $this->invoice()) {
            return;
        }

        if (!$paymentMethod = $invoice->payment_method) {
            return;
        }

        /*
         * Pay from profile
         */
        if (post('pay_from_profile') && post('pay_from_profile') == 1) {
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

        /*
         * Custom response
         */
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }
        elseif ($redirect === false) {
            return;
        }

        /*
         * Standard response
         */
        if (!$returnPage = $invoice->getReceiptUrl()) {
            return;
        }

        return Redirect::to($returnPage);
    }

    /**
     * Returns the logged in user, if available, and touches
     * the last seen timestamp.
     * @return RainLab\User\Models\User
     */
    public function user()
    {
        if (!$user = Auth::getUser()) {
            return null;
        }

        return $user;
    }

    /**
     * @deprecated Use $this->invoice()
     */
    public function getInvoice()
    {
        return $this->invoice();
    }
}
