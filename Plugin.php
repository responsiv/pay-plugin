<?php namespace Responsiv\Pay;

use Backend;
use System\Classes\PluginBase;
use Lang;

/**
 * Pay Plugin Information File
 */
class Plugin extends PluginBase
{
    public $require = [
        'RainLab.User',
        'RainLab.UserPlus',
        'RainLab.Location',
        'Responsiv.Currency'
    ];

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
            \Responsiv\Pay\Components\Payment::class  => 'payment',
            \Responsiv\Pay\Components\Invoice::class  => 'invoice',
            \Responsiv\Pay\Components\Invoices::class => 'invoices',
            \Responsiv\Pay\Components\Profile::class  => 'payProfile',
            \Responsiv\Pay\Components\Profiles::class => 'payProfiles',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'responsiv.pay.access_invoices' => [
                'tab'   => 'Pay',
                'label' => 'Access invoices'
            ],
            'responsiv.pay.manage_taxes' => [
                'tab'   => 'Pay',
                'label' => 'Manage taxes'
            ],
            'responsiv.pay.manage_gateways' => [
                'tab'   => 'Pay',
                'label' => 'Manage gateways'
            ],
            'responsiv.pay.access_settings' => [
                'tab'   => 'Pay',
                'label' => 'Access settings'
            ],
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
            \Responsiv\Pay\PaymentTypes\PaypalStandard::class => 'paypal-standard',
            \Responsiv\Pay\PaymentTypes\PaypalAdaptive::class => 'paypal-adaptive',
            \Responsiv\Pay\PaymentTypes\PaypalPro::class      => 'paypal-pro',
            \Responsiv\Pay\PaymentTypes\Offline::class        => 'offline',
            \Responsiv\Pay\PaymentTypes\Skrill::class         => 'skrill',
            \Responsiv\Pay\PaymentTypes\Stripe::class         => 'stripe',
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
                'permissions' => ['responsiv.pay.*'],
                'order'       => 520,

                'sideMenu' => [
                    'invoices' => [
                        'label'       => Lang::get('responsiv.pay::lang.menu.invoices'),
                        'icon'        => 'icon-file-text-o',
                        'url'         => Backend::url('responsiv/pay/invoices'),
                        'permissions' => ['responsiv.pay.access_invoices'],
                    ],
                    'taxes' => [
                        'label'       => Lang::get('responsiv.pay::lang.menu.tax'),
                        'icon'        => 'icon-table',
                        'url'         => Backend::url('responsiv/pay/taxes'),
                        'permissions' => ['responsiv.pay.manage_taxes'],
                        'order'       => 500,
                    ],
                    'types' => [
                        'label'       => Lang::get('responsiv.pay::lang.menu.gateways'),
                        'icon'        => 'icon-money',
                        'url'         => Backend::url('responsiv/pay/paymentmethods'),
                        'permissions' => ['responsiv.pay.manage_gateways'],
                        'order'       => 510,
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
                'permissions' => ['responsiv.pay.access_settings'],
                'order'       => 520,
            ],
            'invoice_template' => [
                'label'       => Lang::get('responsiv.pay::lang.invoice_template.name'),
                'description' => Lang::get('responsiv.pay::lang.invoice_template.description'),
                'icon'        => 'icon-file-excel-o',
                'url'         => Backend::url('responsiv/pay/invoicetemplates'),
                'category'    => Lang::get('responsiv.pay::lang.name'),
                'permissions' => ['responsiv.pay.access_settings'],
                'order'       => 520,
            ]
        ];
    }
}
