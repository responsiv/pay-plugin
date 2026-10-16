<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('responsiv_pay_invoice_items', 'is_tax_exempt')) {
            Schema::table('responsiv_pay_invoice_items', function(Blueprint $table) {
                $table->boolean('is_tax_exempt')->default(false);
            });
        }
    }

    public function down()
    {
    }
};
