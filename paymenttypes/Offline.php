<?php namespace Responsiv\Pay\PaymentTypes;

use Backend;
use Cms\Classes\Page;
use Responsiv\Pay\Models\Settings;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Pay\Classes\GatewayBase;
use Cms\Classes\Controller as CmsController;
use System\Classes\ApplicationException;
use October\Rain\Network\Http;

class Offline extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Offline Payment',
            'description' => 'For creating payment forms with offline payment processing'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function defineRelationships($host)
    {
        $host->belongsTo['invoice_status'] = ['Responsiv\Pay\Models\InvoiceStatus'];
    }

    /**
     * Returns the payment instructions for offline payment
     * @param  Model $host
     * @param  Model $invoice
     * @return string
     */
    public function getPaymentInstructions($host, $invoice)
    {
        return $host->payment_instructions;
    }

}