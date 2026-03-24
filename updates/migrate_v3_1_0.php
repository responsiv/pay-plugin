<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('responsiv_pay_invoices', 'credit_applied')) {
            Schema::table('responsiv_pay_invoices', function(Blueprint $table) {
                $table->bigInteger('credit_applied')->nullable()->default(0);
            });
        }
    }

    public function down()
    {
    }
};
