<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateTypesTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_methods', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->index()->nullable();
            $table->string('class_name')->nullable();
            $table->text('description')->nullable();
            $table->text('config_data')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('responsiv_pay_methods_countries', function($table)
        {
            $table->engine = 'InnoDB';
            $table->integer('payment_method_id')->unsigned();
            $table->integer('country_id')->unsigned();
            $table->primary(['payment_method_id', 'country_id'], 'method_country');
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_methods');
        Schema::dropIfExists('responsiv_pay_methods_countries');
    }
}
