<?php namespace Responsiv\Pay;

use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

/**
 * Pay Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * @var array require plugins
     */
    public $require = [
        'RainLab.User',
        'RainLab.UserPlus',
        'RainLab.Location',
        'Responsiv.Currency'
    ];

    /**
     * pluginDetails returns information about this plugin.
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

    /**
     * register the service provider.
     */
    public function register()
    {
        $this->registerSingletons();
    }

    /**
     * boot the module events.
     */
    public function boot()
    {
    }

    /**
     * registerSingletons
     */
    protected function registerSingletons()
    {
        $this->app->singleton('pay.gateways', \Responsiv\Pay\Classes\GatewayManager::class);
    }

    /**
     * registerComponents
     */
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
                'tab' => 'Pay',
                'label' => 'Access invoices'
            ],
            'responsiv.pay.manage_taxes' => [
                'tab' => 'Pay',
                'label' => 'Manage taxes'
            ],
            'responsiv.pay.manage_gateways' => [
                'tab' => 'Pay',
                'label' => 'Manage gateways'
            ],
            'responsiv.pay.access_settings' => [
                'tab' => 'Pay',
                'label' => 'Access settings'
            ],
        ];
    }

    /**
     * registerPaymentGateways registers any payment gateways implemented in this plugin.
     *
     * The gateways must be returned in the following format:
     *
     * [DriverName1::class => 'alias'],
     * [DriverName2::class => 'anotherAlias']
     */
    public function registerPaymentGateways()
    {
        return [
            \Responsiv\Pay\PaymentTypes\PayPalPayment::class => 'paypal',
            \Responsiv\Pay\PaymentTypes\StripePayment::class => 'stripe',
            \Responsiv\Pay\PaymentTypes\CustomPayment::class => 'custom',
        ];
    }

    /**
     * registerNavigation
     */
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
                        'label' => "Tax Classes",
                        'icon' => 'icon-table',
                        'url' => Backend::url('responsiv/pay/taxes'),
                        'permissions' => ['responsiv.pay.manage_taxes'],
                        'order' => 500,
                    ],
                    'types' => [
                        'label' => "Payment Methods",
                        'icon' => 'icon-money',
                        'url' => Backend::url('responsiv/pay/paymentmethods'),
                        'permissions' => ['responsiv.pay.manage_gateways'],
                        'order' => 510,
                    ],
                ]
            ]
        ];
    }

    /**
     * registerSettings
     */
    public function registerSettings()
    {
        return [
            // 'settings' => [
            //     'label' => "Payment Settings",
            //     'description' => "Manage payment configuration",
            //     'icon' => 'icon-credit-card',
            //     'class' => 'Responsiv\Pay\Models\Settings',
            //     'category' => SettingsManager::CATEGORY_SHOP,
            //     'permissions' => ['responsiv.pay.access_settings'],
            //     'order' => 520,
            // ],
            'invoice_settings' => [
                'label' => "Invoice Settings",
                'description' => "Customize invoice templates and settings.",
                'icon' => 'icon-file-excel-o',
                'url' => Backend::url('responsiv/pay/invoicetemplates'),
                'category' => SettingsManager::CATEGORY_SHOP,
                'permissions' => ['responsiv.pay.access_settings'],
                'order' => 900,
            ]
        ];
    }

    /**
     * registerFormWidgets
     */
    public function registerFormWidgets()
    {
        return [
            \Responsiv\Pay\FormWidgets\Discount::class => 'discount'
        ];
    }
}
