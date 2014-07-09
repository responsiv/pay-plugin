<?php namespace Responsiv\Pay\Components;

use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\Type as TypeModel;
use System\Classes\ApplicationException;

class Payment extends ComponentBase
{

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
            'idParam' => [
                'title'       => 'Hash param name',
                'description' => 'The URL route parameter used for looking up the invoice by its hash.',
                'default'     => ':hash',
                'type'        => 'string'
            ],
            'invoicePage' => [
                'title'       => 'Invoice page',
                'description' => 'Name of the invoice page file for the invoice links.',
                'type'        => 'dropdown',
                'default'     => 'pay/pay'
            ],
            'invoicePageIdParam' => [
                'title'       => 'Invoice page param name',
                'description' => 'The expected parameter name used when creating links to the invoice page.',
                'type'        => 'string',
                'default'     => ':id',
            ],
        ];
    }

    public function getPropertyOptions($property)
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->page['invoice'] = $invoice = $this->getInvoice();
        $this->page['paymentTypes'] = TypeModel::listApplicable($invoice->country_id);
        $this->page['paymentType'] = $invoice ? $invoice->payment_type : null;
        $this->prepareVars();
    }

    public function getInvoice()
    {
        if ($this->invoice !== null)
            return $this->invoice;

        if (!$hash = $this->propertyOrParam('idParam'))
            return null;

        return $this->invoice = InvoiceModel::whereHash($hash)->first();
    }

    protected function prepareVars()
    {
        /*
         * Page links
         */
        $this->invoicePage = $this->page['invoicePage'] = $this->property('invoicePage');
        $this->invoicePageIdParam = $this->page['invoicePageIdParam'] = $this->property('invoicePageIdParam');
    }

    public function onUpdatePaymentType()
    {
        if (!$invoice = $this->getInvoice())
            throw new ApplicationException('Invoice not found!');

        if (!$typeId = post('payment_type'))
            throw new ApplicationException('Payment type not specified!');

        if (!$type = TypeModel::find($typeId))
            throw new ApplicationException('Payment type not found!');

        $invoice->payment_type = $type;
        $invoice->save();

        $this->page['invoice'] = $invoice;
        $this->page['paymentType'] = $type;
    }


}