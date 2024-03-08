<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_logs', function($table) {
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->string('payment_method_name')->nullable();
            $table->boolean('is_success')->default(false);
            $table->string('message')->nullable();
            $table->text('raw_response')->nullable();
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->integer('admin_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_logs');
    }
};
