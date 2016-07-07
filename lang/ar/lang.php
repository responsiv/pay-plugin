<?php

return [
    'name' => 'الدفع',
    'description' => 'فواتير ومحاسبة',
    'add_payment_method' => 'أضف طريقة الدفع',
    'menu' => [
        'payments' => 'المدفوعات',
        'invoices' => 'الفواتير',
        'tax' => 'الجداول الضريبية',
        'gateways' => 'خدمة الدفع الالكتروني',
    ],
    'settings' => [
        'name' => 'إعدادات الدفع',
        'description' => 'إدارة التكوين دفع'
    ],
    'invoice_template' => [
        'name' => 'قالب الفواتير',
        'description' => 'تعديل القالب المستخدم للفواتير.',
    ],
    'invoice' => [
        'change_status_title' => 'تغيير مرتبة الفاتورة',
        'current_status_name' => ':name :المرتبة الحالية',
    ],
];
