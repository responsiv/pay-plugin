<?php namespace Responsiv\Pay;

use October\Rain\Database\Updates\Seeder;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Pay\Models\InvoiceTemplate;

class SeedAllTables extends Seeder
{

    public function run()
    {
        InvoiceStatus::create(['is_enabled' => true, 'name' => 'Draft', 'code' => 'draft']);
        InvoiceStatus::create(['is_enabled' => true, 'name' => 'Approved', 'code' => 'approved']);
        InvoiceStatus::create(['is_enabled' => true, 'name' => 'Paid', 'code' => 'paid']);
        InvoiceStatus::create(['is_enabled' => true, 'name' => 'Void', 'code' => 'void']);

        InvoiceTemplate::create([
            'name' => 'Default template',
            'code' => 'default',
            'is_default' => true
        ]);
    }

}
