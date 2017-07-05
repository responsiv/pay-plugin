<?php

return [
    'name' => 'Pay',
    'description' => 'Pedido e Contabilidade',
    'add_payment_method' => 'Adicionar método de pagamento',
    'menu' => [
        'payments' => 'Pagamentos',
        'invoices' => 'Pedidos',
        'tax' => 'Tabela de taxas',
        'gateways' => 'Gateways',
    ],
    'settings' => [
        'name' => 'Configurações de pagamento',
        'description' => 'Gerenciar configuração de pagamento'
    ],
    'invoice_template' => [
        'name' => 'Pedidos template',
        'description' => 'Customize o template usado para os pedidos.',
    ],
    'invoice' => [
        'change_status_title' => 'Alterar status do pedido',
        'current_status_name' => 'Status atual: :name',
    ],
];
