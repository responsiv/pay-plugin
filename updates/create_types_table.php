<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateTypesTable extends Migration
{

    public function up()
    {
        Schema::create('responsiv_pay_types', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code', 100)->index()->nullable();
            $table->string('class_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->text('config_data')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('responsiv_pay_types_countries', function($table)
        {
            $table->engine = 'InnoDB';
            $table->integer('type_id')->unsigned();
            $table->integer('country_id')->unsigned();
            $table->primary(['type_id', 'country_id']);
        });

        Schema::create('responsiv_pay_type_logs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->string('type_name')->nullable();
            $table->boolean('is_success')->default(false);
            $table->string('message')->nullable();
            $table->text('raw_response')->nullable();
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->string('ccv_response_code', 20)->nullable();
            $table->string('ccv_response_text')->nullable();
            $table->string('avs_response_code', 20)->nullable();
            $table->string('avs_response_text')->nullable();
            $table->integer('admin_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('responsiv_pay_types');
        Schema::drop('responsiv_pay_types_countries');
        Schema::drop('responsiv_pay_type_logs');
    }

}
