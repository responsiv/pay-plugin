<?php

return [
    'name' => 'Оплата',
    'description' => 'Выставление счетов и бухгалтерия',
    'add_payment_method' => 'Добавить метод оплаты',
    'menu' => [
        'payments' => 'Платежи',
        'invoices' => 'Счета',
        'tax' => 'Налоговые ставки',
        'gateways' => 'Шлюзы',
    ],
    'settings' => [
        'name' => 'Настройка платежей',
        'description' => 'Управление конфигурацией'
    ],
    'invoice_template' => [
        'name' => 'Шаблон счета',
        'description' => 'Управление шаблоном выставления счетов',
    ],
    'invoice' => [
        'change_status_title' => 'Изменить статус счета',
        'current_status_name' => 'Текущий статус: :name',
    ],
];
