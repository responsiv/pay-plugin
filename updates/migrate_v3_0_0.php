<?php

use October\Rain\Database\Updates\Migration;
use Responsiv\Pay\Models\InvoiceStatus;

return new class extends Migration
{
    public function up()
    {
        if (!InvoiceStatus::findByCode('refunded')) {
            InvoiceStatus::create([
                'is_enabled' => true,
                'name' => 'Refunded',
                'code' => 'refunded'
            ]);
        }
    }

    public function down()
    {
    }
};
