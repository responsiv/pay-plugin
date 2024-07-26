<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_status_logs', function($table) {
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->mediumText('comment')->nullable();
            $table->bigInteger('updated_user_id')->unsigned()->nullable();
            $table->bigInteger('created_user_id')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_status_logs');
    }
};
