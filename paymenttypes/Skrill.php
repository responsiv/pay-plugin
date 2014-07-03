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

class Skrill extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'Skrill',
            'description' => 'Skrill payment method with payment form hosted on Skrill server'
        ];
    }

}