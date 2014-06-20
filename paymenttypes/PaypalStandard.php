<?php namespace Responsiv\Pay\PaymentTypes;

use Backend;
use Cms\Classes\Page;
use Responsiv\Pay\Models\Settings;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceLog;
use Responsiv\Pay\Classes\GatewayBase;
use Cms\Classes\Controller as CmsController;
use System\Classes\ApplicationException;
use October\Rain\Network\Http;

class PaypalStandard extends GatewayBase
{

    /**
     * {@inheritDoc}
     */
    public function gatewayDetails()
    {
        return [
            'name'        => 'PayPal Standard',
            'description' => 'PayPal Standard payment method with payment form hosted on PayPal server'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function defineFormFields()
    {
        return 'fields.yaml';
    }

    /**
     * {@inheritDoc}
     */
    public function defineValidationRules()
    {
        return [
            'business_email' => ['required', 'email']
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function defineRelationships($hostObj)
    {
        $hostObj->belongsTo['invoice_status'] = ['Responsiv\Pay\Models\InvoiceStatus'];
    }

    /**
     * {@inheritDoc}
     */
    public function initConfigData($hostObj)
    {
        $hostObj->test_mode = true;
    }

    /**
     * {@inheritDoc}
     */
    public function registerAccessPoints()
    {
        return array(
            'paypal_standard_autoreturn' => 'processAutoreturn',
            'paypal_standard_ipn'        => 'processIpn'
        );
    }

    /**
     * Cancel page field options
     */
    public function getCancelPageOptions($keyValue = -1)
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Get the URL to Paypal's servers
     */
    public function getFormAction($hostObj)
    {
        if ($hostObj->test_mode)
            return "https://www.sandbox.paypal.com/cgi-bin/webscr";
        else
            return "https://www.paypal.com/cgi-bin/webscr";
    }
}

