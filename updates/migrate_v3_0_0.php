<?php

use October\Rain\Database\Schema\Blueprint;
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

        if (!Schema::hasColumn('responsiv_pay_invoices', 'currency_code')) {
            Schema::table('responsiv_pay_invoices', function(Blueprint $table) {
                $table->string('currency_code', 3)->nullable();
            });
        }
    }

    public function down()
    {
    }
};
