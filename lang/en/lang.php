<?php

return [
    'name' => 'Pay',
    'description' => 'Invoicing and Accounting',
    'add_payment_method' => 'Add Payment Method',
    'menu' => [
        'payments' => 'Payments',
        'invoices' => 'Invoices',
        'tax' => 'Tax Rules',
        'gateways' => 'Gateways',
    ],
    'settings' => [
        'name' => 'Payment settings',
        'description' => 'Manage payment configuration'
    ],
    'invoice_template' => [
        'name' => 'Invoice template',
        'description' => 'Customize the template used for invoices.',
    ],
    'invoice' => [
        'change_status_title' => 'Change Invoice Status',
        'current_status_name' => 'Current status: :name',
    ],
    'profile' => [
        'unset_default' => '":profile" is already default and cannot be unset as default.',
    ],
    'options' => [
        'any_country' => '* - Any country',
        'any_state' => '* - Any state',
    ],
];
