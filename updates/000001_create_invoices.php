<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoices', function($table) {
            $table->increments('id');
            $table->string('hash')->nullable()->index();
            $table->string('user_ip')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('street_addr')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('tax_id_number')->nullable();
            $table->bigInteger('total')->nullable();
            $table->bigInteger('subtotal')->nullable();
            $table->bigInteger('discount')->nullable();
            $table->bigInteger('tax')->nullable();
            $table->bigInteger('tax_discount')->nullable();
            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('prices_include_tax')->default(false)->nullable();
            $table->text('tax_data')->nullable();
            $table->string('return_page')->nullable();
            $table->boolean('is_throwaway')->default(false);
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('template_id')->unsigned()->nullable()->index();
            $table->integer('payment_method_id')->unsigned()->nullable()->index();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->integer('currency_id')->unsigned()->nullable()->index();
            $table->integer('state_id')->unsigned()->nullable()->index();
            $table->integer('country_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoices');
    }
};
