<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_logs', function($table) {
            $table->increments('id');
            $table->string('payment_method_name')->nullable();
            $table->boolean('is_success')->default(false);
            $table->text('message')->nullable();
            $table->mediumText('request_data')->nullable();
            $table->mediumText('response_data')->nullable();
            $table->mediumText('card_data')->nullable();
            $table->mediumText('raw_response')->nullable();
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->bigInteger('updated_user_id')->unsigned()->nullable();
            $table->bigInteger('created_user_id')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_logs');
    }
};
