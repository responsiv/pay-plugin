<?php namespace Responsiv\Pay;

use October\Rain\Database\Updates\Seeder;
use Responsiv\Pay\Models\InvoiceStatus;

class SeedAllTables extends Seeder
{

    public function run()
    {
        InvoiceStatus::create(['is_enabled' => true, 'name' => 'Unpaid', 'code' => 'new']);
        InvoiceStatus::create(['is_enabled' => true, 'name' => 'Paid', 'code' => 'paid']);
    }

}
