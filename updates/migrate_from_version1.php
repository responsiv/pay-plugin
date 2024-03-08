<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        // if (!Schema::hasColumn('responsiv_currency_currencies', 'decimal_scale')) {
        //     Schema::table('responsiv_currency_currencies', function(Blueprint $table) {
        //         $table->integer('decimal_scale')->default(2);
        //     });
        // }

        // if (!Schema::hasColumn('responsiv_currency_exchange_converters', 'name')) {
        //     Schema::table('responsiv_currency_exchange_converters', function(Blueprint $table) {
        //         $table->string('name')->nullable();
        //     });
        // }
    }

    public function down()
    {
    }
};
