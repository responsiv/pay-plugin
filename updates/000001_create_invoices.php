<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoices', function($table) {
            $table->increments('id');
            $table->integer('template_id')->unsigned()->nullable()->index();
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->string('user_ip', 15)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('street_addr')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('vat_id', 50)->nullable(); // @todo rename to tax_id_number
            $table->integer('state_id')->unsigned()->nullable()->index();
            $table->integer('country_id')->unsigned()->nullable()->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('tax_discount', 15, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->string('currency', 10)->nullable(); // @todo rename currency_code
            $table->text('tax_data')->nullable();
            $table->integer('payment_method_id')->unsigned()->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('hash', 40)->nullable()->index();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->string('return_page')->nullable();
            $table->boolean('is_throwaway')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoices');
    }
};
