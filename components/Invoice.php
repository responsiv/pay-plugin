<?php namespace Responsiv\Pay\Components;

use Auth;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;

/**
 * Invoice
 */
class Invoice extends ComponentBase
{
    /**
     * @var Responsiv\Pay\Models\Invoice Cached object
     */
    protected $invoice;

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name' => 'Invoice Component',
            'description' => 'Allow an owner to view their invoice by its identifier'
        ];
    }

    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [
            'id' => [
                'title' => 'Invoice ID',
                'description' => 'The URL route parameter used for looking up the invoice by its identifier.',
                'default' => '{{ :id }}',
                'type' => 'string'
            ],
            'isDefault' => [
                'title' => 'Default View',
                'type' => 'checkbox',
                'description' => 'Used as default entry point when viewing an invoice.',
                'showExternalParam' => false
            ],
        ];
    }

    /**
     * onRun
     */
    public function onRun()
    {
        $this->page['invoice'] = $invoice = $this->invoice();

        if ($invoice) {
            $this->page->meta_title = $this->page->meta_title
                ? str_replace('%s', $invoice->getUniqueId(), $this->page->meta_title)
                : 'Invoice #'.$invoice->getUniqueId();
        }
    }

    /**
     * invoice
     */
    protected function invoice()
    {
        if ($this->invoice !== null) {
            return $this->invoice;
        }

        if (!$id = $this->property('id')) {
            return null;
        }

        $invoice =  InvoiceModel::where('id', $id)->first();

        // Only users can view their own invoices
        $user = Auth::user();
        if (!$user) {
            $invoice = null;
        }

        if ($invoice && $invoice->user_id != $user->id) {
            $invoice = null;
        }

        return $this->invoice = $invoice;
    }

    /**
     * @deprecated Use $this->invoice()
     */
    public function getInvoice()
    {
        return $this->invoice();
    }
}
