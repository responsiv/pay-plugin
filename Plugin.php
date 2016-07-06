<?php namespace Responsiv\Pay;

use Backend;
use System\Classes\PluginBase;
use Lang;

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
            'name'        => Lang::get('responsiv.pay::lang.name'),
            'description' => Lang::get('responsiv.pay::lang.description'),
            'author'      => 'Responsiv Internet',
            'icon'        => 'icon-credit-card',
            'homepage'    => 'https://github.com/responsiv/pay-plugin'
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
                'label'       => Lang::get('responsiv.pay::lang.menu.payments'),
                'url'         => Backend::url('responsiv/pay/invoices'),
                'icon'        => 'icon-credit-card',
                'iconSvg'     => 'plugins/responsiv/pay/assets/images/pay-icon.svg',
                'permissions' => ['pay.*'],
                'order'       => 500,

                'sideMenu' => [
                    'invoices' => [
                        'label'       => Lang::get('responsiv.pay::lang.menu.invoices'),
                        'icon'        => 'icon-file-text-o',
                        'url'         => Backend::url('responsiv/pay/invoices'),
                        'permissions' => ['pay.access_invoices'],
                    ],
                    'taxes' => [
                        'label'       => Lang::get('responsiv.pay::lang.menu.tax'),
                        'icon'        => 'icon-table',
                        'url'         => Backend::url('responsiv/pay/taxes'),
                        'permissions' => ['pay.manage_taxes'],
                    ],
                    'types' => [
                        'label'       => Lang::get('responsiv.pay::lang.menu.gateways'),
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
            'settings' => [
                'label'       => Lang::get('responsiv.pay::lang.settings.name'),
                'description' => Lang::get('responsiv.pay::lang.settings.description'),
                'icon'        => 'icon-credit-card',
                'class'       => 'Responsiv\Pay\Models\Settings',
                'category'    => Lang::get('responsiv.pay::lang.name'),
                'order'       => 500,
            ],
            'invoice_template' => [
                'label'       => Lang::get('responsiv.pay::lang.invoice_template.name'),
                'description' => Lang::get('responsiv.pay::lang.invoice_template.description'),
                'icon'        => 'icon-file-excel-o',
                'url'         => Backend::url('responsiv/pay/invoicetemplates'),
                'category'    => Lang::get('responsiv.pay::lang.name'),
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
