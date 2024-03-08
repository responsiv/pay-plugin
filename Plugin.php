<?php namespace Responsiv\Pay;

use Lang;
use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

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
            'name' => "Pay",
            'description' => "Invoicing and Accounting",
            'author' => 'Responsiv Software',
            'icon' => 'icon-credit-card',
            'homepage' => 'https://github.com/responsiv/pay-plugin'
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
     * registerPaymentTypes registers any payment gateways implemented in this plugin.
     *
     * The gateways must be returned in the following format:
     *
     * [DriverName1::class => 'alias'],
     * [DriverName2::class => 'anotherAlias']
     */
    public function registerPaymentTypes()
    {
        return [
            \Responsiv\Pay\PaymentTypes\CustomPayment::class => 'custom',
            \Responsiv\Pay\PaymentTypes\PayPalRestPayment::class => 'paypal-rest',
        ];
    }

    public function registerNavigation()
    {
        return [
            'pay' => [
                'label' => "Payments",
                'url' => Backend::url('responsiv/pay/invoices'),
                'icon' => 'icon-credit-card',
                'iconSvg' => 'plugins/responsiv/pay/assets/images/pay-icon.svg',
                'permissions' => ['responsiv.pay.*'],
                'order' => 420,

                'sideMenu' => [
                    'invoices' => [
                        'label' => "Invoices",
                        'icon' => 'icon-file-text-o',
                        'url' => Backend::url('responsiv/pay/invoices'),
                        'permissions' => ['responsiv.pay.access_invoices'],
                    ],
                    'taxes' => [
                        'label' => "Tax Rules",
                        'icon' => 'icon-table',
                        'url' => Backend::url('responsiv/pay/taxes'),
                        'permissions' => ['responsiv.pay.manage_taxes'],
                        'order' => 500,
                    ],
                    'types' => [
                        'label' => "Gateways",
                        'icon' => 'icon-money',
                        'url' => Backend::url('responsiv/pay/paymentmethods'),
                        'permissions' => ['responsiv.pay.manage_gateways'],
                        'order' => 510,
                    ],
                ]
            ]
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => "Payment settings",
                'description' => "Manage payment configuration",
                'icon' => 'icon-credit-card',
                'class' => 'Responsiv\Pay\Models\Settings',
                'category' => SettingsManager::CATEGORY_SHOP,
                'permissions' => ['responsiv.pay.access_settings'],
                'order' => 520,
            ],
            'invoice_template' => [
                'label' => "Invoice template",
                'description' => "Customize the template used for invoices.",
                'icon' => 'icon-file-excel-o',
                'url' => Backend::url('responsiv/pay/invoicetemplates'),
                'category' => SettingsManager::CATEGORY_SHOP,
                'permissions' => ['responsiv.pay.access_settings'],
                'order' => 520,
            ]
        ];
    }
}
