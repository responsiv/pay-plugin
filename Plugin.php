<?php namespace Responsiv\Pay;

use Backend;
use System\Classes\PluginBase;

/**
 * Pay Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['RainLab.User', 'RainLab.Location', 'Responsiv.Currency'];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Pay',
            'description' => 'Invoicing and Accounting',
            'author'      => 'Responsiv Internet',
            'icon'        => 'icon-credit-card'
        ];
    }

    public function registerComponents()
    {
        return [
            'Responsiv\Pay\Components\Payment'  => 'payment',
            'Responsiv\Pay\Components\Invoice'  => 'invoice',
            'Responsiv\Pay\Components\Invoices' => 'invoices',
        ];
    }

    public function registerNavigation()
    {
        return [
            'pay' => [
                'label'       => 'Payments',
                'url'         => Backend::url('responsiv/pay/invoices'),
                'icon'        => 'icon-credit-card',
                'permissions' => ['pay.*'],
                'order'       => 500,

                'sideMenu' => [
                    'invoices' => [
                        'label'       => 'Invoices',
                        'icon'        => 'icon-file-text-o',
                        'url'         => Backend::url('responsiv/pay/invoices'),
                        'permissions' => ['pay.access_invoices'],
                    ],
                    'taxes' => [
                        'label'       => 'Tax tables',
                        'icon'        => 'icon-table',
                        'url'         => Backend::url('responsiv/pay/taxes'),
                        'permissions' => ['pay.manage_taxes'],
                    ],
                    'types' => [
                        'label'       => 'Gateways',
                        'icon'        => 'icon-money',
                        'url'         => Backend::url('responsiv/pay/paymentmethods'),
                        'permissions' => ['pay.manage_gateways'],
                        'order'       => 500,
                    ],
                ]

            ]
        ];
    }

    public function registerSettings()
    {
        return [
            'invoice_template' => [
                'label'       => 'Invoice template',
                'description' => 'Customize the template used for invoices.',
                'icon'        => 'icon-file-excel-o',
                'url'         => Backend::url('responsiv/pay/invoicetemplates'),
                'category'    => 'Payments',
                'order'       => 500,
            ]
        ];
    }

    /**
     * Registers any payment gateways implemented in this plugin.
     * The gateways must be returned in the following format:
     * ['className1' => 'alias'],
     * ['className2' => 'anotherAlias']
     */
    public function registerPaymentGateways()
    {
        return [
            'Responsiv\Pay\PaymentTypes\PaypalStandard' => 'paypal-standard',
            'Responsiv\Pay\PaymentTypes\PaypalPro'      => 'paypal-pro',
            'Responsiv\Pay\PaymentTypes\Offline'        => 'offline',
            'Responsiv\Pay\PaymentTypes\Skrill'         => 'skrill',
            'Responsiv\Pay\PaymentTypes\Stripe'         => 'stripe',
        ];
    }

}
