<?php namespace Responsiv\Pay\Components;

use Auth;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;

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
            'invoicePageIdParam' => [
                'title'       => 'Invoice page param name',
                'description' => 'The expected parameter name used when creating links to the invoice page.',
                'type'        => 'string',
                'default'     => ':id',
            ],
        ];
    }

    public function getInvoicePageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->invoices = $this->page['invoices'] = $this->loadInvoices();
        $this->invoicePage = $this->page['invoicePage'] = $this->property('invoicePage');
        $this->invoicePageIdParam = $this->page['invoicePageIdParam'] = $this->property('invoicePageIdParam');
    }

    protected function loadInvoices()
    {
        if (!($user = Auth::getUser()))
            throw new \Exception('You must be logged in');

        $invoices = InvoiceModel::orderBy('sent_at');
        $invoices->where('user_id', $user->id);
        return $invoices->get();
    }

}