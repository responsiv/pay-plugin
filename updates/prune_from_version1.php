<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        $columnsToPrune = [
            'tax_discount',
        ];

        foreach ($columnsToPrune as $column) {
            if (Schema::hasColumn('responsiv_pay_invoice_items', $column)) {
                Schema::table('responsiv_pay_invoice_items', function(Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        $columnsToPrune = [
            'admin_id',
        ];

        foreach ($columnsToPrune as $column) {
            if (Schema::hasColumn('responsiv_pay_invoice_status_logs', $column)) {
                Schema::table('responsiv_pay_invoice_status_logs', function(Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down()
    {
    }
};
