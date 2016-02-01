<?php namespace Responsiv\Pay\Components;

use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use ApplicationException;

class Payment extends ComponentBase
{

    public $invoicePage;

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
        $this->prepareVars();
        $this->page['invoice'] = $invoice = $this->getInvoice();

        if ($invoice) {
            $this->page['paymentMethods'] = TypeModel::listApplicable($invoice->country_id);
            $this->page['paymentMethod'] = $invoice ? $invoice->payment_method : null;
        }

        if (post('submit_payment')) {
            $this->onPay();
        }
    }

    public function getInvoice()
    {
        if ($this->invoice !== null) {
            return $this->invoice;
        }

        if (!$hash = $this->property('hash')) {
            return null;
        }

        $invoice = InvoiceModel::whereHash($hash)->first();

        if ($invoice) {
            $invoice->setUrlPageName($this->invoicePage);
        }

        return $this->invoice = $invoice;
    }

    protected function prepareVars()
    {
        /*
         * Page links
         */
        $this->invoicePage = $this->page['invoicePage'] = $this->property('invoicePage');
    }

    public function onUpdatePaymentType()
    {
        if (!$invoice = $this->getInvoice()) {
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
        if (!$invoice = $this->getInvoice()) {
            return;
        }

        if (!$paymentMethod = $invoice->payment_method) {
            return;
        }

        $redirect = $paymentMethod->processPaymentForm(post(), $paymentMethod, $invoice);
        if ($redirect === false) {
            return;
        }

        if (!$returnPage = $invoice->getReceiptUrl()) {
            return;
        }

        return Redirect::to($returnPage);
    }

}