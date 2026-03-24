<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_credit_notes', function($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->bigInteger('amount');
            $table->string('currency_code', 3)->nullable();
            $table->string('reason')->nullable();
            $table->string('type')->nullable();
            $table->integer('issued_by')->unsigned()->nullable()->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_credit_notes');
    }
};
