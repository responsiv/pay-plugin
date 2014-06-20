<?php namespace Responsiv\Pay;

use Backend;
use System\Classes\PluginBase;

/**
 * Pay Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['RainLab.User'];

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
                    'types' => [
                        'label'       => 'Gateways',
                        'icon'        => 'icon-money',
                        'url'         => Backend::url('responsiv/pay/types'),
                        'permissions' => ['pay.access_gateways'],
                    ],
                    'taxes' => [
                        'label'       => 'Tax Tables',
                        'icon'        => 'icon-table',
                        'url'         => Backend::url('responsiv/pay/taxes'),
                        'permissions' => ['pay.access_taxes'],
                    ],
                ]

            ]
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Payment Settings',
                'description' => 'Manage currency configuration.',
                'icon'        => 'icon-usd',
                'class'       => 'Responsiv\Pay\Models\Settings',
                'sort'        => 100
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
        ];
    }

}
