<?php

namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddCurrencyToInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('responsiv_pay_invoices', function ($table) {
            $table->string('currency', 10)->nullable();
        });
    }

    public function down()
    {
        if (Schema::hasColumn('responsiv_pay_invoices', 'currency')) {
            Schema::table('responsiv_pay_invoices', function ($table) {
                $table->dropColumn('currency');
            });
        }
    }
}
