<?php namespace Responsiv\Pay\Components;

use Auth;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use ApplicationException;

class Invoices extends ComponentBase
{

    public $invoices;

    public function componentDetails()
    {
        return [
            'name'        => 'Invoices',
            'description' => 'Displays a list of invoices belonging to a user'
        ];
    }

    public function defineProperties()
    {
        return [
            'invoicePage' => [
                'title'       => 'Invoice page',
                'description' => 'Name of the invoice page file for the invoice links. This property is used by the default component partial.',
                'type'        => 'dropdown',
            ],
        ];
    }

    public function getInvoicePageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->invoicePage = $this->page['invoicePage'] = $this->property('invoicePage');
        $this->invoices = $this->page['invoices'] = $this->loadInvoices();
    }

    protected function loadInvoices()
    {
        if (!$user = Auth::getUser()) {
            throw new ApplicationException('You must be logged in');
        }

        $invoices = InvoiceModel::orderBy('sent_at');
        $invoices = $invoices->where('user_id', $user->id)->get();

        $invoices->each(function($invoice){
            $invoice->setUrlPageName($this->invoicePage);
        });

        return $invoices;
    }

}