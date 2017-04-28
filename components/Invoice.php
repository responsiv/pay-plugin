<?php namespace Responsiv\Pay\Components;

use Auth;
use Request;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;

class Invoice extends ComponentBase
{
    /**
     * @var Responsiv\Pay\Models\Invoice Cached object
     */
    protected $invoice;

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
            'id' => [
                'title'       => 'Invoice ID',
                'description' => 'The URL route parameter used for looking up the invoice by its identifier.',
                'default'     => '{{ :id }}',
                'type'        => 'string'
            ],
            'payPage' => [
                'title'       => 'Payment page',
                'description' => 'Name of the payment page file for the "Pay this invoice" links.',
                'type'        => 'dropdown'
            ],
            'isPrimary' => [
                'title'       => 'Primary page',
                'description' => 'Link to this page when sending mail notifications.',
                'type'        => 'checkbox',
                'default'     => true,
                'showExternalParam' => false
            ],
        ];
    }

    public function getPropertyOptions($property)
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->page['payPage'] = $this->payPage();
        $this->page['invoice'] = $invoice = $this->invoice();

        if ($invoice) {
            $this->page->meta_title = $this->page->meta_title
                ? str_replace('%s', $invoice->getUniqueId(), $this->page->meta_title)
                : 'Invoice #'.$invoice->getUniqueId();
        }
    }

    protected function invoice()
    {
        if ($this->invoice !== null) {
            return $this->invoice;
        }

        if (!$id = $this->property('id')) {
            return null;
        }

        $invoice =  InvoiceModel::where('id', $id)->first();

        /*
         * Only users can view their own invoices
         */
        $user = Auth::getUser();
        if (!$user) {
            $invoice = null;
        }

        if ($invoice && $invoice->user_id != $user->id) {
            $invoice = null;
        }

        if ($invoice) {
            $invoice->setUrlPageName($this->payPage());
        }

        return $this->invoice = $invoice;
    }

    protected function payPage()
    {
        return $this->property('payPage');
    }

    /**
     * @deprecated Use $this->invoice()
     */
    public function getInvoice()
    {
        return $this->invoice();
    }
}
