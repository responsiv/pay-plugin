<?php

namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddVatIdToInvoiceContacts extends Migration
{
    public function up()
    {
        Schema::table('responsiv_pay_invoices', function ($table) {
            $table->string('vat_id', 50)->nullable();
        });
    }

    public function down()
    {
        if (Schema::hasColumn('responsiv_pay_invoices', 'vat_id')) {
            Schema::table('responsiv_pay_invoices', function ($table) {
                $table->dropColumn('vat_id');
            });
        }
    }
}
