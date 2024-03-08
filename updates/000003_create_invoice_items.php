<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_items', function($table) {
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('tax_class_id')->unsigned()->nullable()->index();
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('tax_discount', 15, 2)->default(0);
            $table->integer('sort_order')->nullable();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_items');
    }
};
