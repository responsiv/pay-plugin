<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_methods_countries', function($table) {
            $table->integer('payment_method_id')->unsigned();
            $table->integer('country_id')->unsigned();
            $table->primary(['payment_method_id', 'country_id'], 'method_country');
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_methods_countries');
    }
};
