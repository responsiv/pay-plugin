<?php namespace Responsiv\Pay\Components;

use Auth;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use ApplicationException;

class Invoices extends ComponentBase
{
    /**
     * @var Responsiv\Pay\Models\Invoice Cached object
     */
    protected $invoices;

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name' => 'Invoices',
            'description' => 'Displays a list of invoices belonging to a user'
        ];
    }

    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [];
    }

    /**
     * onRun
     */
    public function onRun()
    {
        $this->page['invoices'] = $this->invoices();
    }

    /**
     * invoices
     */
    protected function invoices()
    {
        if ($this->invoices !== null) {
            return $this->invoices;
        }

        if (!$user = Auth::getUser()) {
            throw new ApplicationException('You must be logged in');
        }

        $invoices = InvoiceModel::orderBy('created_at', 'desc')
            ->applyUser($user)
            ->applyNotThrowaway()
            ->get()
        ;

        return $this->invoices = $invoices;
    }
}
