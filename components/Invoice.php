<?php namespace Responsiv\Pay\Components;

use Auth;
use Request;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;

class Invoice extends ComponentBase
{

    public function componentDetails()
    {
        return [
            'name'        => 'Invoice Component',
            'description' => 'Allow an owner to view their invoice by its identifier'
        ];
    }

    public function defineProperties()
    {
        return [
            'idParam' => [
                'title'       => 'ID param name',
                'description' => 'The URL route parameter used for looking up the invoice by its identifier.',
                'default'     => ':id',
                'type'        => 'string'
            ],
            'payPage' => [
                'title'       => 'Payment page',
                'description' => 'Name of the payment page file for the "Pay this invoice" links.',
                'type'        => 'dropdown',
                'default'     => 'pay/pay'
            ],
            'payPageIdParam' => [
                'title'       => 'Payment page param name',
                'description' => 'The expected parameter name used when creating links to the payment page.',
                'type'        => 'string',
                'default'     => ':hash',
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

        $this->prepareVars();
    }

    public function getInvoice()
    {
        if ($this->invoice !== null)
            return $this->invoice;

        if (!$id = $this->propertyOrParam('idParam'))
            return null;

        $invoice =  InvoiceModel::where('id', $id)->first();

        // $user = Auth::getUser();
        // if (!$user)
        //     $invoice = null;

        // if ($invoice && $invoice->user_id != $user->id)
        //     $invoice = null;

        return $this->invoice = $invoice;
    }

    protected function prepareVars()
    {
        /*
         * Page links
         */
        $this->payPage = $this->page['payPage'] = $this->property('payPage');
        $this->payPageIdParam = $this->page['payPageIdParam'] = $this->property('payPageIdParam');
    }

}